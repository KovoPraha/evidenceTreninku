<?php

declare(strict_types=1);

require_once __DIR__ . '/member_evidence_snapshot.php';
require_once __DIR__ . '/operational_cases.php';
require_once __DIR__ . '/member_evidence_opening.php';
require_once __DIR__ . '/member_evidence_groups.php';

/** In-place IDs preserve all references and historical documents. No name-based merging. */
function memberEvidencePreview(PDO $pdo, array $config, bool $lock = false): array
{
    $on = kisRosterDate((string) ($config['cutover_on'] ?? ''));
    $reason = kisRosterText((string) ($config['reason'] ?? ''), 1000, 'Důvod přechodu');
    $teams = [];
    $codes = [];
    foreach ((array) ($config['teams'] ?? []) as $item) {
        $id = (int) ($item['id'] ?? 0);
        if ($id < 1 || isset($teams[$id])) {
            throw new InvalidArgumentException('Mapa obsahuje neplatné nebo duplicitní ID soupisky.');
        }
        $code = kisRosterCode((string) ($item['standing_code'] ?? ''));
        $name = kisRosterText((string) ($item['name'] ?? ''), 160, 'Název soupisky');
        $activity = kisRosterEnum(
            (string) ($item['activity_kind'] ?? ''),
            ['circle','competitive','other'],
            'Zaměření'
        );
        $query = $pdo->prepare('SELECT * FROM club_teams WHERE id=?');
        $query->execute([$id]);
        $before = $query->fetch(PDO::FETCH_ASSOC);
        if (!$before || $before['team_kind'] !== 'roster' || $before['status'] !== 'active') {
            throw new RuntimeException('Převod vyžaduje existující aktivní soupisku #' . $id);
        }
        foreach (['code', 'name'] as $field) {
            if (isset($item['expected_' . $field]) && $item['expected_' . $field] !== $before[$field]) {
                throw new RuntimeException('Původní soupiska neodpovídá schválené mapě.');
            }
        }
        $query = $pdo->prepare('SELECT id FROM club_teams WHERE (standing_code=? OR code=?) AND id<>?');
        $query->execute([$code,$code,$id]);
        if ($query->fetchColumn() || isset($codes[$code])) {
            throw new RuntimeException('Kód trvalé soupisky není jedinečný.');
        }
        $codes[$code] = true;
        $teams[$id] = ['id' => $id,'standing_code' => $code,'name' => $name,'activity_kind' => $activity];
    }
    ksort($teams);
    $groups = memberEvidenceGroupsPreview($pdo, $config, $teams, $on);
    $opening = memberEvidenceOpeningRows($pdo, $config, $on);
    $detached = memberEvidenceDetachClaims($pdo, $config);
    $deletions = memberEvidenceDeletionRows($pdo, (array) ($config['delete'] ?? []), $on, $detached);
    if (!$teams && !$deletions) {
        throw new InvalidArgumentException('Prázdný plán nemůže uzavřít přechod evidence.');
    }
    foreach ($pdo->query("SELECT id FROM club_teams WHERE season_id IS NOT NULL AND status='active'")->fetchAll(PDO::FETCH_COLUMN) as $id) {
        if (!isset($teams[(int) $id])) {
            throw new RuntimeException('V mapě chybí aktivní původní soupiska #' . (int) $id);
        }
    }
    $snapshot = memberEvidenceCutoverSnapshot($pdo, $config, $lock);
    $members = [];
    foreach (array_keys($teams) as $id) {
        foreach ([$on,(new DateTimeImmutable($on))->modify('+1 day')->format('Y-m-d'),
            (new DateTimeImmutable($on))->modify('+1 year')->format('Y-m-d')] as $date) {
            $ids = kisRosterEffectiveMemberIds($pdo, $id, $date);
            $members[$id][$date] = ['count' => count($ids),'sha256' => hash('sha256', json_encode(
                $ids,
                JSON_THROW_ON_ERROR
            ))];
        }
    }
    $deleteCounts = array_map('count', $deletions);
    $payload = ['contract' => 'member-evidence-cutover-v1','config' => $config,'cutover_on' => $on,'reason'
    => $reason,
        'teams' => array_values($teams),'groups' => $groups,'opening_count' => count($opening),'detached_claim_count' => count($detached),
        'delete_counts' => $deleteCounts,'snapshot' => $snapshot,'membership'
    => $members];
    return $payload + ['fingerprint' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE))];
}

/** Caller must stop writers/workers and supply the verified backup file's digest. */
function memberEvidenceApply(
    PDO $pdo,
    array $manifest,
    string $approvedFingerprint,
    int $actor,
    string $backupHash,
    bool $writesPaused
): array {
    if (!$writesPaused || !preg_match('/^[a-f0-9]{64}$/D', $approvedFingerprint)
        || !preg_match('/^[a-f0-9]{64}$/D', $backupHash) || $pdo->inTransaction()) {
        throw new InvalidArgumentException('Přechod vyžaduje schválený otisk, ověřenou zálohu a pozastavené zápisy.');
    }
    $payload = $manifest;
    unset($payload['fingerprint']);
    if (!hash_equals($approvedFingerprint, hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)))) {
        throw new RuntimeException('Manifest neodpovídá schválenému otisku.');
    }
    if (($manifest['cutover_on'] ?? '') > date('Y-m-d')) {
        throw new RuntimeException('Přechod nelze aplikovat před rozhodným dnem.');
    }
    $pdo->beginTransaction();
    try {
        operationalCaseActor($pdo, $actor, 'registrar');
        $query = $pdo->prepare('SELECT summary_json FROM member_evidence_cutover_runs WHERE fingerprint=?');
        $query->execute([$approvedFingerprint]);
        $previous = $query->fetchColumn();
        if ($previous !== false) {
            $pdo->commit();
            return json_decode($previous, true, 512, JSON_THROW_ON_ERROR) + ['idempotent' => true];
        }
        // Lock the approved backup scope before checking the plan again. Never disable FK checks.
        $snapshot = memberEvidenceCutoverSnapshot($pdo, $manifest['config'], true);
        if ($snapshot !== $manifest['snapshot']) {
            throw new RuntimeException('Databáze se od náhledu změnila. Vytvořte nový manifest a zálohu.');
        }
        $fresh = memberEvidencePreview($pdo, $manifest['config']);
        if (!hash_equals($approvedFingerprint, $fresh['fingerprint'])) {
            throw new RuntimeException('Náhled přechodu už není aktuální.');
        }
        foreach ($manifest['teams'] as $team) {
            $query = $pdo->prepare('SELECT * FROM club_teams WHERE id=?');
            $query->execute([$team['id']]);
            $before = $query->fetch(PDO::FETCH_ASSOC);
            $pdo->prepare('UPDATE club_teams SET season_id=NULL,series_id=NULL,standing_code=?,code=?,name=?,activity_kind=? WHERE id=?')
                ->execute([$team['standing_code'],$team['standing_code'],$team['name'],$team['activity_kind'],

    $team['id']]);
            kisRosterStructureEvent(
                $pdo,
                'team',
                $team['id'],
                $actor,
                'standing_cutover',
                $manifest['reason'],
                $before,
                $team
            );
        }
        $opening = memberEvidenceOpeningRows($pdo, $manifest['config'], $manifest['cutover_on']);
        $detached = memberEvidenceDetachClaims($pdo, $manifest['config']);
        $rows = memberEvidenceDeletionRows($pdo, $manifest['config']['delete'] ?? [], $manifest['cutover_on'], $detached);
        foreach ($detached as $claim) {
            $pdo->prepare('UPDATE kis_legacy_roster_claims SET member_id=NULL WHERE id=?')->execute([$claim['id']]);
        }
        memberEvidenceApplyOpening($pdo, $opening, $manifest['cutover_on'], $actor, $manifest['reason']);
        foreach (['kis_current_roster_claims','kis_legacy_roster_claims','club_roster_events','club_roster_members'] as $table) {
            foreach (array_keys($rows[$table] ?? []) as $id) {
                $pdo->prepare('DELETE FROM ' . memberEvidenceIdentifier($table) . ' WHERE id=?')->execute([$id]);
            }
        }
        $createdGroups = memberEvidenceGroupsApply($pdo, $manifest['groups'], $manifest['cutover_on'], $actor, $manifest['reason']);
        $after = memberEvidenceCutoverSnapshot($pdo, $manifest['config']);
        $changed = ['club_teams','club_roster_structure_events',...array_keys($rows)];
        if ($opening) {
            $changed = array_merge($changed, ['club_roster_members', 'club_roster_events']);
        }
        if ($detached) {
            $changed[] = 'kis_legacy_roster_claims';
        }
        if ($createdGroups) {
            $changed = array_merge($changed, ['club_roster_events', 'club_team_edges']);
        }
        foreach ($snapshot as $table => $state) {
            if (!in_array($table, $changed, true) && ($after[$table] ?? null) !== $state) {
                throw new RuntimeException('Parita zachovaných dat selhala: ' . $table);
            }
        }
        foreach ($manifest['membership'] as $id => $dates) {
            foreach ($dates as $date => $expected) {
                $members = kisRosterEffectiveMemberIds($pdo, (int) $id, $date);
                if ($expected !== ['count' => count($members),'sha256' => hash('sha256', json_encode(
                    $members,
                    JSON_THROW_ON_ERROR
                ))]) {
                    throw new RuntimeException('Parita členství po přechodu selhala.');
                }
            }
        }
        $result = ['fingerprint' => $approvedFingerprint,'cutover_on' => $manifest['cutover_on'],
            'converted_teams' => count($manifest['teams']),'created_groups' => count($createdGroups),
            'created_edges' => array_sum(array_map(static fn (array $group): int => count($group['child_ids']), $manifest['groups'])),
            'group_membership_parity' => true,'opening_count' => count($opening),
            'opening_on' => $manifest['cutover_on'],
            'final_sync_pending' => (bool) ($manifest['config']['opening']['final_sync_pending'] ?? false),
            'detached_claim_count' => count($detached),'deleted' => $manifest['delete_counts'],'preserved_parity'
    => true];
        $pdo->prepare('INSERT INTO member_evidence_cutover_runs(fingerprint,actor_trainer_id,backup_sha256,summary_json) VALUES(?,?,?,?)')
            ->execute([$approvedFingerprint,$actor,$backupHash,json_encode($result, JSON_THROW_ON_ERROR)]);
        $pdo->commit();
        return $result;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}
