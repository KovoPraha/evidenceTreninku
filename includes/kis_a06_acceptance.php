<?php
declare(strict_types=1);

require_once __DIR__ . '/kis_roster.php';

final class KisA06Exception extends RuntimeException {}

/** @return list<array{code:string,label:string,required_action:string}> */
function kisA06Steps(): array
{
    return [
        ['code' => 'LOCAL-U15-2026', 'label' => 'Věkový postup U15 → U17', 'required_action' => 'age_progression'],
        ['code' => 'LOCAL-DRAHA-2026', 'label' => 'Přenos disciplíny Dráha', 'required_action' => 'carry_forward'],
        ['code' => 'LOCAL-U13-2026', 'label' => 'U13 → U15 se zachovanou výjimkou', 'required_action' => 'skip_exception'],
    ];
}

/** @return array<string,mixed> */
function kisA06Scenario(PDO $pdo): array
{
    $seasonStatement = $pdo->prepare("SELECT id,code,name FROM club_seasons WHERE code='RACE-2027' AND status='active' LIMIT 1");
    $seasonStatement->execute();
    $targetSeason = $seasonStatement->fetch(PDO::FETCH_ASSOC);
    if (!$targetSeason) throw new KisA06Exception('Chybí cílová localhost sezona RACE-2027. Obnovte demo data.');

    $teamStatement = $pdo->prepare(
        'SELECT t.id,t.code,t.name,s.name AS season_name,ser.rollover_policy '
        . 'FROM club_teams t JOIN club_seasons s ON s.id=t.season_id '
        . 'LEFT JOIN club_team_series ser ON ser.id=t.series_id '
        . "WHERE t.code=? AND t.status='active' LIMIT 1"
    );
    $runStatement = $pdo->prepare(
        'SELECT id,moved_count,skipped_count,preview_fingerprint,reason,created_at '
        . 'FROM club_roster_rollover_runs WHERE source_team_id=? AND target_season_id=? '
        . 'ORDER BY id DESC LIMIT 1'
    );
    $runActionStatement = $pdo->prepare(
        'SELECT COUNT(*) FROM club_roster_rollover_run_items WHERE run_id=? AND action=?'
    );
    $rows = [];
    $pendingFingerprints = [];
    foreach (kisA06Steps() as $definition) {
        $teamStatement->execute([$definition['code']]);
        $team = $teamStatement->fetch(PDO::FETCH_ASSOC);
        if (!$team) throw new KisA06Exception('Chybí localhost soupiska ' . $definition['code'] . '. Obnovte demo data.');
        $runStatement->execute([(int)$team['id'], (int)$targetSeason['id']]);
        $run = $runStatement->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($run !== null) {
            $runActionStatement->execute([(int)$run['id'], $definition['required_action']]);
            if ((int)$runActionStatement->fetchColumn() < 1) {
                throw new KisA06Exception('Existující běh ' . $definition['code'] . ' neobsahuje očekávaný krok. Obnovte demo data.');
            }
        }
        $preview = null;
        $requirementMet = false;
        if ($run === null) {
            $preview = kisRosterPreviewRollover($pdo, (int)$team['id'], (int)$targetSeason['id']);
            foreach ($preview['proposals'] as $proposal) {
                if ((string)$proposal['action'] === $definition['required_action']) $requirementMet = true;
            }
            if (!$requirementMet) {
                throw new KisA06Exception('Náhled ' . $definition['code'] . ' neobsahuje očekávaný krok ' . $definition['required_action'] . '. Obnovte demo data.');
            }
            $pendingFingerprints[$definition['code']] = (string)$preview['fingerprint'];
        }
        $rows[] = $definition + ['team' => $team, 'preview' => $preview, 'run' => $run];
    }
    $batchFingerprint = hash('sha256', json_encode($pendingFingerprints, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    return [
        'target_season' => $targetSeason,
        'rows' => $rows,
        'pending_fingerprints' => $pendingFingerprints,
        'batch_fingerprint' => $batchFingerprint,
        'complete' => $pendingFingerprints === [],
    ];
}

/** @return array{results:list<array<string,mixed>>,idempotent_count:int,moved_count:int,skipped_count:int} */
function kisA06Execute(PDO $pdo, int $actorId, string $reason, bool $confirmed, string $batchFingerprint, array $fingerprints): array
{
    if (!$confirmed) throw new InvalidArgumentException('Provedení A06 vyžaduje explicitní potvrzení.');
    if ($actorId < 1) throw new InvalidArgumentException('Provedení A06 vyžaduje administrátora.');
    $reason = kisRosterText($reason, 900, 'Důvod A06');
    $scenario = kisA06Scenario($pdo);
    if ($scenario['complete']) return ['results' => [], 'idempotent_count' => 0, 'moved_count' => 0, 'skipped_count' => 0];
    if (!hash_equals((string)$scenario['batch_fingerprint'], $batchFingerprint)) {
        throw new KisA06Exception('Souhrnný náhled A06 se mezitím změnil. Obnovte stránku.');
    }
    $normalized = [];
    foreach ($fingerprints as $code => $fingerprint) {
        if (is_string($code) && is_string($fingerprint)) $normalized[$code] = $fingerprint;
    }
    if ($normalized !== $scenario['pending_fingerprints']) {
        throw new KisA06Exception('Jednotlivé náhledy A06 se mezitím změnily. Obnovte stránku.');
    }

    $results = [];
    $moved = $skipped = $idempotent = 0;
    foreach ($scenario['rows'] as $row) {
        $code = (string)$row['code'];
        if (!isset($normalized[$code])) continue;
        $result = kisRosterExecuteRollover(
            $pdo,
            (int)$row['team']['id'],
            (int)$scenario['target_season']['id'],
            $actorId,
            'LOCALHOST A06: ' . $reason,
            true,
            $normalized[$code]
        );
        $results[] = ['code' => $code, 'label' => $row['label']] + $result;
        $moved += (int)$result['moved_count'];
        $skipped += (int)$result['skipped_count'];
        $idempotent += !empty($result['idempotent']) ? 1 : 0;
    }
    return ['results' => $results, 'idempotent_count' => $idempotent, 'moved_count' => $moved, 'skipped_count' => $skipped];
}
