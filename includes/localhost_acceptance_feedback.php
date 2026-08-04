<?php
declare(strict_types=1);

final class LocalhostAcceptanceFeedbackException extends RuntimeException
{
}

function localhostAcceptanceFeedbackPath(string $root): string
{
    return rtrim($root, '/\\') . '/var/acceptance-feedback.json';
}

/** @return array{version:int,updated_at:?string,scenarios:array<string,array<string,mixed>>} */
function localhostAcceptanceFeedbackLoad(string $root): array
{
    $empty = ['version' => 1, 'updated_at' => null, 'scenarios' => []];
    $path = localhostAcceptanceFeedbackPath($root);
    if (!is_file($path)) {
        return $empty;
    }
    if (is_link($path) || filesize($path) > 1048576) {
        throw new LocalhostAcceptanceFeedbackException('Soubor výsledků není bezpečné načíst.');
    }
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        throw new LocalhostAcceptanceFeedbackException('Výsledky se nepodařilo načíst.');
    }
    try {
        if (!flock($handle, LOCK_SH)) {
            throw new LocalhostAcceptanceFeedbackException('Výsledky jsou právě používány.');
        }
        $json = stream_get_contents($handle);
        flock($handle, LOCK_UN);
    } finally {
        fclose($handle);
    }
    try {
        $data = json_decode((string)$json, true, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        throw new LocalhostAcceptanceFeedbackException('Soubor výsledků je poškozený.', 0, $exception);
    }
    if (!is_array($data) || ($data['version'] ?? null) !== 1 || !is_array($data['scenarios'] ?? null)) {
        throw new LocalhostAcceptanceFeedbackException('Soubor výsledků má neplatnou strukturu.');
    }
    return ['version' => 1, 'updated_at' => is_string($data['updated_at'] ?? null) ? $data['updated_at'] : null, 'scenarios' => $data['scenarios']];
}

/** @param list<string> $allowedScenarioIds @param array<string,mixed> $input */
function localhostAcceptanceFeedbackSave(string $root, string $scenarioId, array $input, int $actorTrainerId, array $allowedScenarioIds): array
{
    if ($actorTrainerId < 1 || !in_array($scenarioId, $allowedScenarioIds, true)) {
        throw new InvalidArgumentException('Neplatný scénář nebo administrátor.');
    }
    $result = (string)($input['result'] ?? 'not_tested');
    $importance = (string)($input['importance'] ?? 'none');
    if (!in_array($result, ['not_tested', 'pass', 'partial', 'fail', 'blocked'], true)) {
        throw new InvalidArgumentException('Neplatný výsledek scénáře.');
    }
    if (!in_array($importance, ['none', 'blocks', 'important', 'idea'], true)) {
        throw new InvalidArgumentException('Neplatná důležitost připomínky.');
    }
    $observed = localhostAcceptanceFeedbackText($input['observed'] ?? '', 4000, 'Pozorované chování');
    $expected = localhostAcceptanceFeedbackText($input['expected'] ?? '', 4000, 'Očekávané chování');
    $now = (new DateTimeImmutable('now'))->format(DATE_ATOM);
    $data = localhostAcceptanceFeedbackLoad($root);
    $data['updated_at'] = $now;
    $data['scenarios'][$scenarioId] = [
        'result' => $result,
        'importance' => $importance,
        'observed' => $observed,
        'expected' => $expected,
        'updated_at' => $now,
        'actor_trainer_id' => $actorTrainerId,
    ];

    $directory = dirname(localhostAcceptanceFeedbackPath($root));
    if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
        throw new LocalhostAcceptanceFeedbackException('Adresář pro výsledky se nepodařilo vytvořit.');
    }
    $path = localhostAcceptanceFeedbackPath($root);
    if (is_link($path)) {
        throw new LocalhostAcceptanceFeedbackException('Výsledky nelze zapsat přes symbolický odkaz.');
    }
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
    $handle = fopen($path, 'c+b');
    if ($handle === false) {
        throw new LocalhostAcceptanceFeedbackException('Výsledky se nepodařilo otevřít pro zápis.');
    }
    try {
        if (!flock($handle, LOCK_EX)) {
            throw new LocalhostAcceptanceFeedbackException('Výsledky jsou právě používány.');
        }
        if (!ftruncate($handle, 0) || rewind($handle) === false || fwrite($handle, $json) !== strlen($json) || !fflush($handle)) {
            throw new LocalhostAcceptanceFeedbackException('Výsledky se nepodařilo bezpečně uložit.');
        }
        flock($handle, LOCK_UN);
    } finally {
        fclose($handle);
    }
    return $data['scenarios'][$scenarioId];
}

function localhostAcceptanceFeedbackText(mixed $value, int $maxLength, string $label): string
{
    $value = trim((string)$value);
    if (mb_strlen($value, 'UTF-8') > $maxLength) {
        throw new InvalidArgumentException($label . ' smí mít nejvýše ' . $maxLength . ' znaků.');
    }
    return $value;
}

/** @param list<array<string,mixed>> $scenarios @param array<string,mixed> $feedback */
function localhostAcceptanceFeedbackMarkdown(array $scenarios, array $feedback): string
{
    $resultLabels = ['not_tested' => 'NOT TESTED', 'pass' => 'PASS', 'partial' => 'PARTIAL', 'fail' => 'FAIL', 'blocked' => 'BLOCKED'];
    $importanceLabels = ['none' => '—', 'blocks' => 'blokuje', 'important' => 'důležité', 'idea' => 'námět'];
    $lines = ['# Výsledky localhost akceptace A01–A10', '', 'Exportováno: ' . (new DateTimeImmutable('now'))->format(DATE_ATOM), '', '| Scénář | Výsledek | Důležitost | Pozorováno | Očekáváno |', '|---|---|---|---|---|'];
    foreach ($scenarios as $scenario) {
        $id = (string)$scenario['id'];
        $row = is_array($feedback[$id] ?? null) ? $feedback[$id] : [];
        $result = (string)($row['result'] ?? 'not_tested');
        $importance = (string)($row['importance'] ?? 'none');
        $lines[] = '| ' . $id
            . ' | ' . ($resultLabels[$result] ?? 'NOT TESTED')
            . ' | ' . ($importanceLabels[$importance] ?? '—')
            . ' | ' . localhostAcceptanceFeedbackMarkdownCell((string)($row['observed'] ?? ''))
            . ' | ' . localhostAcceptanceFeedbackMarkdownCell((string)($row['expected'] ?? '')) . ' |';
    }
    $lines[] = '';
    $lines[] = '> Soubor neobsahuje hesla ani automaticky načtená osobní data. Před commitem zkontrolujte ručně zadané poznámky.';
    return implode(PHP_EOL, $lines) . PHP_EOL;
}

function localhostAcceptanceFeedbackMarkdownCell(string $value): string
{
    $value = preg_replace('/\R/u', '<br>', trim($value)) ?? '';
    return str_replace('|', '\\|', $value) ?: '—';
}
