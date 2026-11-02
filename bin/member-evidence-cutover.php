<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__) . '/includes/member_evidence_cutover.php';
require_once dirname(__DIR__) . '/includes/member_evidence_final_sync.php';

/** Credentials are supplied through environment variables, never arguments or output. */
function memberEvidenceCutoverMain(array $arguments): void
{
    $options = getopt('', ['inventory','preview:','apply:','output:','confirm:','actor:','backup:','backup-sha256:',

    'writes-paused','sync-preview:','sync-apply:','source:']);
    $modes = array_intersect(['inventory','preview','apply','sync-preview','sync-apply'], array_keys($options));
    if (count($modes) !== 1) {
        throw new InvalidArgumentException('Použití: --inventory | --preview=config.json --output=manifest.json | '
            . '--apply=manifest.json --confirm=SHA256 --actor=ID --backup=soubor --backup-sha256=SHA256 --writes-paused | '
            . '--sync-preview=config.json --source=export --output=manifest.json | '
            . '--sync-apply=manifest.json --source=export --confirm=SHA256 --actor=ID --backup=soubor --backup-sha256=SHA256 --writes-paused');
    }
    $dsn = (string) getenv('MEMBER_EVIDENCE_DSN');
    if ($dsn === '' || !preg_match('/^(mysql|sqlite):/', $dsn)) {
        throw new InvalidArgumentException('Nastavte MEMBER_EVIDENCE_DSN a případně MEMBER_EVIDENCE_DB_USER/DB_PASSWORD.');
    }
    $pdo = new PDO(
        $dsn,
        getenv('MEMBER_EVIDENCE_DB_USER') ?: null,
        getenv('MEMBER_EVIDENCE_DB_PASSWORD') ?: null,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $pdo->exec('PRAGMA foreign_keys=ON');
    }
    $mode = array_values($modes)[0];
    $sync = in_array($mode, ['sync-preview','sync-apply'], true);
    $syncInput = $sync ? memberEvidenceReadJson((string) $options[$mode]) : [];
    if ($sync) {
        $source = (string) ($options['source'] ?? '');
        if (!is_file($source) || filesize($source) < 1
            || !hash_equals((string) ($syncInput['source_sha256'] ?? ''), (string) hash_file('sha256', $source))) {
            throw new RuntimeException('Konkrétní zdrojový export chybí nebo neodpovídá otisku synchronizace.');
        }
    }
    if (in_array($mode, ['apply','sync-apply'], true)) {
        $manifest = $sync ? $syncInput : memberEvidenceReadJson((string) $options['apply']);
        $backup = (string) ($options['backup'] ?? '');
        $expectedBackup = (string) ($options['backup-sha256'] ?? '');
        if (!is_file($backup) || filesize($backup) < 1 || !preg_match('/^[a-f0-9]{64}$/D', $expectedBackup)
            || !hash_equals($expectedBackup, (string) hash_file('sha256', $backup))) {
            throw new RuntimeException('Záloha chybí nebo její obsah neodpovídá ověřenému otisku.');
        }
        $apply = $sync ? 'memberEvidenceFinalSyncApply' : 'memberEvidenceApply';
        $result = $apply(
            $pdo,
            $manifest,
            (string) ($options['confirm'] ?? ''),
            (int) ($options['actor'] ?? 0),
            $expectedBackup,
            isset($options['writes-paused'])
        );
        echo json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
        return;
    }
    $pdo->beginTransaction();
    try {
        $result = $sync ? memberEvidenceFinalSyncPreview($pdo, $syncInput) : ($mode === 'preview'
            ? memberEvidencePreview($pdo, memberEvidenceReadJson((string) $options['preview']))
            : ['contract' => 'member-evidence-inventory-v1','tables' => memberEvidenceSnapshot($pdo),
                'foreign_keys' => memberEvidenceForeignKeys($pdo),
                'teams' => $pdo->query('SELECT id,code,name,season_id,standing_code,team_kind,activity_kind,status FROM club_teams ORDER BY id')->fetchAll(),


                'legacy_memberships' => $pdo->query("SELECT id,team_id,status,valid_from,valid_to FROM club_roster_members WHERE source='kis_shadow' ORDER BY id")->fetchAll()]);
        $pdo->rollBack();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
    $output = (string) ($options['output'] ?? '');
    $parent = realpath(dirname($output));
    $root = realpath(dirname(__DIR__));
    if ($output === '' || $parent === false || $root === false || is_file($output)
        || str_starts_with(strtolower($parent . DIRECTORY_SEPARATOR), strtolower($root . DIRECTORY_SEPARATOR))) {
        throw new RuntimeException('Zvolte nový soubor v existujícím soukromém adresáři mimo webový kořen.');
    }
    $stream = fopen($output, 'x');
    if ($stream === false) {
        throw new RuntimeException('Nelze vytvořit soukromý manifest.');
    }
    try {
        $json = json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) .

     PHP_EOL;
        if (fwrite($stream, $json) !== strlen($json)) {
            throw new RuntimeException('Neúplný zápis manifestu.');
        }
    } finally {
        fclose($stream);
    }
    echo json_encode(['ok' => true,'mode' => $mode,'file' => basename($output),'fingerprint' => $result['fingerprint'] ??
    null], JSON_THROW_ON_ERROR) . PHP_EOL;
}

function memberEvidenceReadJson(string $path): array
{
    if (!is_file($path) || filesize($path) > 20000000) {
        throw new InvalidArgumentException('Neplatný soubor konfigurace nebo manifestu.');
    }
    $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($data)) {
        throw new InvalidArgumentException('Konfigurace musí být JSON objekt.');
    }
    return $data;
}

try {
    memberEvidenceCutoverMain($argv);
} catch (Throwable $error) {
    // PDO failures can contain connection details; emit only a safe diagnosis.
    fwrite(STDERR, ($error instanceof PDOException ? 'Databázová operace selhala; převod nebyl potvrzen.' : $error->getMessage())
    . PHP_EOL);
    exit(1);
}
