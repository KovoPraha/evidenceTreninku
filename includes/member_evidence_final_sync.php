<?php

declare(strict_types=1);

require_once __DIR__ . '/member_evidence_cutover.php';

const MEMBER_EVIDENCE_FINAL_SYNC_MARKER = '{"action":"final_kis_sync"}';

function memberEvidenceOpeningState(PDO $pdo, bool $lock = false): array
{
    $suffix = $lock && $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
    $rows = $pdo->query('SELECT fingerprint,summary_json FROM member_evidence_cutover_runs ORDER BY created_at,fingerprint' . $suffix)
        ->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) !== 1) {
        throw new RuntimeException('Poslední synchronizace vyžaduje jednoznačný dokončený počáteční převod.');
    }
    $state = json_decode($rows[0]['summary_json'], true, 512, JSON_THROW_ON_ERROR);
    if (!($state['final_sync_pending'] ?? false)) {
        throw new RuntimeException('Poslední synchronizace nebyla součástí schváleného přechodu.');
    }
    return $state + ['cutover_fingerprint' => $rows[0]['fingerprint']];
}

/** Explicit reviewed delta only: absence from an export never means delete a person or entitlement. */
function memberEvidenceFinalSyncPreview(PDO $pdo, array $config): array
{
    $state = memberEvidenceOpeningState($pdo);
    $on = kisRosterDate((string) $state['opening_on']);
    $source = (string) ($config['source_sha256'] ?? '');
    $reason = kisRosterText((string) ($config['reason'] ?? ''), 1000, 'Důvod synchronizace');
    if (!preg_match('/^[a-f0-9]{64}$/D', $source)) {
        throw new InvalidArgumentException('Chybí otisk konkrétního nového exportu.');
    }
    $operations = [];
    $keys = [];
    foreach ((array) ($config['operations'] ?? []) as $item) {
        $action = (string) ($item['action'] ?? '');
        if ($action === 'add') {
            $teamId = (int) ($item['team_id'] ?? 0);
            $personId = (int) ($item['person_id'] ?? 0);
            $from = kisRosterDate((string) ($item['valid_from'] ?? ''));
            $until = empty($item['valid_to']) ? null : kisRosterDate((string) $item['valid_to']);
            if (min($teamId, $personId) < 1 || $from < $on || ($until !== null && $until < $from)) {
                throw new InvalidArgumentException('Nové členství nesmí začínat před počátečním dnem.');
            }
            $query = $pdo->prepare("SELECT id FROM club_teams WHERE id=? AND status='active' AND team_kind='roster' AND season_id IS NULL");
            $query->execute([$teamId]);
            if (!$query->fetchColumn()) {
                throw new RuntimeException('Vyžaduje se explicitně mapovaná trvalá soupiska.');
            }
            $query = $pdo->prepare("SELECT id FROM sportovci WHERE id=? AND stav_clenstvi<>'archiv'");
            $query->execute([$personId]);
            if (!$query->fetchColumn()) {
                throw new RuntimeException('Neznámá osoba musí nejprve projít ověřeným registračním postupem.');
            }
            $query = $pdo->prepare("SELECT id FROM club_roster_members WHERE team_id=? AND sportovec_id=? AND status<>'cancelled' "
                . 'AND valid_from<=? AND (valid_to IS NULL OR valid_to>=?)');
            $query->execute([$teamId, $personId, $until ?? '9999-12-31', $from]);
            if ($query->fetchColumn()) {
                throw new RuntimeException('Zařazení již pokrývá část období; synchronizace nesmí vytvořit duplicitu.');
            }
            $key = 'add:' . $teamId . ':' . $personId;
            $operations[] = ['action' => 'add', 'team_id' => $teamId, 'person_id' => $personId, 'valid_from' => $from, 'valid_to' => $until];
        } elseif ($action === 'end') {
            $id = (int) ($item['member_id'] ?? 0);
            $until = kisRosterDate((string) ($item['valid_to'] ?? ''));
            $query = $pdo->prepare('SELECT * FROM club_roster_members WHERE id=?');
            $query->execute([$id]);
            $member = $query->fetch(PDO::FETCH_ASSOC);
            if (!$member || $member['source'] !== 'manual' || !str_starts_with((string) $member['source_ref'], 'kis-opening:')
                || $member['status'] !== 'active' || $until < $on || $until < $member['valid_from']
                || ($member['valid_to'] !== null && $until > $member['valid_to'])) {
                throw new RuntimeException('Ukončit lze pouze výslovně vybraný převzatý interval, nikoli placený nebo novější nárok.');
            }
            $from = (new DateTimeImmutable($until))->modify('+1 day')->format('Y-m-d');
            $impact = kisRosterHierarchyImpact($pdo, (int) $member['team_id'], $from);
            if ($impact['blocked']) {
                throw new RuntimeException('Před ukončením vyřešte dotčené odpovědi, přihlášky nebo náhrady.');
            }
            $key = 'end:' . $id;
            $operations[] = ['action' => 'end', 'member_id' => $id, 'valid_to' => $until];
        } else {
            throw new InvalidArgumentException('Synchronizace přijímá jen výslovné přidání nebo ukončení členství.');
        }
        if (isset($keys[$key])) {
            throw new InvalidArgumentException('Synchronizace obsahuje duplicitní operaci.');
        }
        $keys[$key] = true;
    }
    $payload = ['contract' => 'member-evidence-final-sync-v1', 'opening_on' => $on,
        'cutover_fingerprint' => $state['cutover_fingerprint'], 'source_sha256' => $source,
        'reason' => $reason, 'operations' => $operations,
        'snapshot' => memberEvidenceCutoverSnapshot($pdo, ['scope' => 'application_backup'])];
    return $payload + ['fingerprint' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE))];
}

function memberEvidenceFinalSyncApply(PDO $pdo, array $manifest, string $fingerprint, int $actor, string $backupHash, bool $writesPaused): array
{
    $payload = $manifest;
    unset($payload['fingerprint']);
    if (!$writesPaused || $pdo->inTransaction() || !preg_match('/^[a-f0-9]{64}$/D', $backupHash)
        || !hash_equals(hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)), $fingerprint)) {
        throw new InvalidArgumentException('Synchronizace vyžaduje platný manifest, zálohu a pozastavené zápisy.');
    }
    $pdo->beginTransaction();
    try {
        operationalCaseActor($pdo, $actor, 'registrar');
        memberEvidenceOpeningState($pdo, true);
        $query = $pdo->prepare('SELECT fingerprint,result_json FROM member_roster_operations WHERE request_json=?');
        $query->execute([MEMBER_EVIDENCE_FINAL_SYNC_MARKER]);
        $previous = $query->fetch(PDO::FETCH_ASSOC);
        if ($previous) {
            if (!hash_equals($previous['fingerprint'], $fingerprint)) {
                throw new RuntimeException('Jedna poslední synchronizace už proběhla; další dávka není povolena.');
            }
            $pdo->commit();
            return json_decode($previous['result_json'], true, 512, JSON_THROW_ON_ERROR) + ['idempotent' => true];
        }
        $snapshot = memberEvidenceCutoverSnapshot($pdo, ['scope' => 'application_backup'], true);
        if ($snapshot !== $manifest['snapshot']) {
            throw new RuntimeException('Databáze se od náhledu synchronizace změnila.');
        }
        $fresh = memberEvidenceFinalSyncPreview($pdo, $manifest);
        if (!hash_equals($fingerprint, $fresh['fingerprint'])) {
            throw new RuntimeException('Náhled synchronizace už není aktuální.');
        }
        foreach ($manifest['operations'] as $index => $item) {
            if ($item['action'] === 'add') {
                kisRosterGrantPeriod(
                    $pdo,
                    $item['team_id'],
                    $item['person_id'],
                    $actor,
                    'manual',
                    $item['valid_from'],
                    $item['valid_to'],
                    $manifest['reason'],
                    'kis-final:' . $manifest['source_sha256'] . ':' . $index
                );
            } else {
                kisRosterEndPeriod($pdo, $item['member_id'], $actor, $item['valid_to'], $manifest['reason']);
            }
        }
        $after = memberEvidenceCutoverSnapshot($pdo, ['scope' => 'application_backup']);
        foreach ($snapshot as $table => $state) {
            if (!in_array($table, ['club_roster_members', 'club_roster_events'], true) && ($after[$table] ?? null) !== $state) {
                throw new RuntimeException('Parita zachovaných dat synchronizace selhala: ' . $table);
            }
        }
        $result = ['fingerprint' => $fingerprint, 'source_sha256' => $manifest['source_sha256'],
            'opening_on' => $manifest['opening_on'], 'operations' => count($manifest['operations']),
            'final_sync_pending' => false, 'preserved_parity' => true, 'backup_sha256' => $backupHash];
        $pdo->prepare('INSERT INTO member_roster_operations(fingerprint,actor_trainer_id,request_json,result_json) VALUES(?,?,?,?)')
            ->execute([$fingerprint, $actor, MEMBER_EVIDENCE_FINAL_SYNC_MARKER, json_encode($result, JSON_THROW_ON_ERROR)]);
        $pdo->commit();
        return $result;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}
