<?php

declare(strict_types=1);

require_once __DIR__ . '/coach_scope.php';
require_once __DIR__ . '/roster_hierarchy.php';
require_once __DIR__ . '/trainer_password_reset.php';

const TRAINER_PILOT_KEY = 'trainer_pilot_20261031_v1';
const TRAINER_PILOT_CODE = 'TEST-COACH-20261031';
const TRAINER_PILOT_NAME = 'TEST - Trenérský pilot 20261031';
const TRAINER_PILOT_REASON = 'Vlastníkem schválený oddělený trenérský pilot se syntetickými sportovci, 31. 10. 2026.';
const TRAINER_PILOT_ACTOR_NAME = 'SYSTEM - Offline trainer pilot 20261031';
const TRAINER_PILOT_ACTOR_KEY = 'trainer_pilot_audit_actor_20261031_v1';

/** Operational authority comes from the protected CLI workflow, never from a public account. */
function trainerPilotValidateOperation(array $operation): void
{
    if (PHP_SAPI !== 'cli' || ($operation['confirmation'] ?? '') !== 'VYTVORIT-TEST-PILOT'
        || ($operation['workflow_ref'] ?? '') !== 'KovoPraha/evidenceTreninku/.github/workflows/trainer-pilot-production.yml@refs/heads/main'
        || !preg_match('/^[A-Za-z0-9-]{1,39}$/D', (string)($operation['actor'] ?? ''))
        || !preg_match('/^[1-9][0-9]{0,19}$/D', (string)($operation['run_id'] ?? ''))) {
        throw new RuntimeException('pilot_operation_authorization_invalid');
    }
}

function trainerPilotCreateAuditActor(PDO $pdo, array $operation): void
{
    trainerPilotValidateOperation($operation);
    if (!$pdo->inTransaction()) {
        throw new RuntimeException('pilot_transaction_required');
    }
    $marker = trainerPilotRows($pdo, 'SELECT hodnota FROM nastaveni WHERE klic=?', [TRAINER_PILOT_ACTOR_KEY]);
    if ($marker !== []) {
        return; // Existing identity is checked strictly by trainerPilotOwner; never repaired.
    }
    if (trainerPilotRows($pdo, 'SELECT id FROM treneri WHERE LOWER(TRIM(jmeno))=LOWER(?)', [TRAINER_PILOT_ACTOR_NAME]) !==
    []) {
        throw new RuntimeException('pilot_audit_actor_collision');
    }
    $pdo->prepare("INSERT INTO treneri(jmeno,email,heslo,role,aktivni,session_version) VALUES(?,'',?,'trener',0,1)")
        ->execute([TRAINER_PILOT_ACTOR_NAME, trainer_password_hash(bin2hex(random_bytes(32)))]);
    $record = ['trainer_id' => (int)$pdo->lastInsertId(), 'operation' => $operation];
    $pdo->prepare('INSERT INTO nastaveni(klic,hodnota) VALUES(?,?)')
        ->execute([TRAINER_PILOT_ACTOR_KEY, json_encode($record, JSON_THROW_ON_ERROR)]);
}

function trainerPilotRows(PDO $pdo, string $sql, array $params = []): array
{
    $query = $pdo->prepare($sql);
    $query->execute($params);
    return $query->fetchAll(PDO::FETCH_ASSOC);
}

/** A unique existing owner is required; this operation never elevates that owner. */
function trainerPilotOwner(PDO $pdo, string $name, bool $forUpdate = false, bool $operational = false): array
{
    $name = trim($name);
    if ($name === '' || mb_strlen($name) > 100) {
        throw new RuntimeException('pilot_owner_name_invalid');
    }
    $lock = $forUpdate && $pdo->inTransaction() && $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' :
    '';
    $rows = trainerPilotRows($pdo, 'SELECT id,jmeno,email,heslo,role,aktivni,session_version FROM treneri WHERE LOWER(TRIM(jmeno))=LOWER(?)'
    . $lock, [$name]);
    if (count($rows) !== 1 || (int)$rows[0]['aktivni'] !== ($operational ? 0 : 1)) {
        throw new RuntimeException('pilot_owner_not_unique_active');
    }
    $owner = $rows[0];
    $owner['positions'] = array_column(trainerPilotRows(
        $pdo,
        'SELECT position_code FROM staff_user_positions WHERE trainer_id=? ORDER BY position_code' . $lock,
        [$owner['id']]
    ), 'position_code');
    $owner['superadmin'] = trainerPilotRows($pdo, 'SELECT trainer_id FROM staff_superadmins WHERE trainer_id=?' .

     $lock, [$owner['id']]) !== [];
    if ($operational) {
        $marker = trainerPilotRows($pdo, 'SELECT hodnota FROM nastaveni WHERE klic=?', [TRAINER_PILOT_ACTOR_KEY]);
        $record = $marker === [] ? [] : json_decode($marker[0]['hodnota'], true, 512, JSON_THROW_ON_ERROR);
        if (PHP_SAPI !== 'cli' || $name !== TRAINER_PILOT_ACTOR_NAME || $owner['email'] !== '' || $owner['role'] !== 'trener'
            || $owner['positions'] !== [] || $owner['superadmin'] || (int)($record['trainer_id'] ?? 0) !== (int)$owner['id']) {
            throw new RuntimeException('pilot_audit_actor_drift');
        }
        trainerPilotValidateOperation($record['operation'] ?? []);
    }
    $owner['can_provision'] = $owner['superadmin'] || in_array('sports_lead', $owner['positions'], true);
    $owner['fingerprint'] = hash('sha256', json_encode(
        [$rows[0], $owner['positions'], $owner['superadmin']],
        JSON_THROW_ON_ERROR
    ));
    $target = trainerPasswordResetTarget($pdo, (string)$owner['email']);
    $owner['reset_eligible'] = $target !== null && (int)$target['trainer_id'] === (int)$owner['id'];
    return $owner;
}

function trainerPilotRecord(PDO $pdo): ?array
{
    $rows = trainerPilotRows($pdo, 'SELECT hodnota FROM nastaveni WHERE klic=?', [TRAINER_PILOT_KEY]);
    return $rows === [] ? null : json_decode((string)$rows[0]['hodnota'], true, 512, JSON_THROW_ON_ERROR);
}

/** Read-only diagnostics; no recipient, password, token or real athlete identities in the result. */
function trainerPilotStatus(PDO $pdo, string $ownerName, string $email = ''): array
{
    $operational = $ownerName === TRAINER_PILOT_ACTOR_NAME;
    if ($operational && trainerPilotRows($pdo, 'SELECT hodnota FROM nastaveni WHERE klic=?', [TRAINER_PILOT_ACTOR_KEY]) ===
    []) {
        if (trainerPilotRecord($pdo) !== null
            || trainerPilotRows($pdo, 'SELECT id FROM treneri WHERE LOWER(TRIM(jmeno))=LOWER(?)', [$ownerName]) !== []) {
            throw new RuntimeException('pilot_audit_actor_collision');
        }
        return ['read_only' => true, 'created' => false, 'operational_mode' => true, 'audit_actor_created'
    => false,
            'owner' => ['unique_active' => false, 'can_provision' => false, 'changed' => false]];
    }
    $owner = trainerPilotOwner($pdo, $ownerName, false, $operational);
    $result = ['read_only' => true, 'created' => false, 'owner' => [
        'unique_active' => !$operational, 'positions' => $owner['positions'], 'superadmin' => $owner['superadmin'],


        'can_provision' => $owner['can_provision'], 'reset_eligible' => $owner['reset_eligible'], 'changed'
    => false,
    ], 'operational_mode' => $operational, 'audit_actor_disabled' => $operational, 'audit_actor_created'
    => $operational];
    $record = trainerPilotRecord($pdo);
    if ($record === null) {
        return $result;
    }
    if (($record['version'] ?? null) !== 1 || (int)$record['owner_id'] !== (int)$owner['id']
        || $operational !== (($record['operational_authorization'] ?? []) !== [])
        || ($email !== '' && !hash_equals($record['email_sha256'], hash('sha256', strtolower(trim($email)))))) {
        throw new RuntimeException('pilot_record_identity_mismatch');
    }
    if ($operational) {
        trainerPilotValidateOperation($record['operational_authorization']);
    }
    $trainer = trainerPilotRows($pdo, 'SELECT id,jmeno,email,role,aktivni FROM treneri WHERE id=?', [$record['trainer_id']]);
    $positions = trainerPilotRows(
        $pdo,
        'SELECT position_code,is_default FROM staff_user_positions WHERE trainer_id=?',
        [$record['trainer_id']]
    );
    if (count($trainer) !== 1 || $trainer[0]['jmeno'] !== TRAINER_PILOT_NAME || $trainer[0]['role'] !== 'trener'
        || (int)$trainer[0]['aktivni'] !== 1 || !hash_equals($record['email_sha256'], hash('sha256', $trainer[0]['email']))
        || count($positions) !== 1 || $positions[0]['position_code'] !== 'coach' || (int)$positions[0]['is_default'] !== 1
        || trainerPilotRows($pdo, 'SELECT trainer_id FROM staff_superadmins WHERE trainer_id=?', [$record['trainer_id']])
    !== []) {
        throw new RuntimeException('pilot_account_drift');
    }
    $team = trainerPilotRows($pdo, 'SELECT code,name,status FROM club_teams WHERE id=?', [$record['team_id']]);
    $members = array_map('intval', array_column(trainerPilotRows(
        $pdo,
        'SELECT sportovec_id FROM club_roster_members WHERE team_id=? ORDER BY sportovec_id',
        [$record['team_id']]
    ), 'sportovec_id'));
    if (count($team) !== 1 || $team[0]['code'] !== TRAINER_PILOT_CODE || $team[0]['name'] !== TRAINER_PILOT_NAME
        || $team[0]['status'] !== 'active' || $members !== $record['member_ids']) {
        throw new RuntimeException('pilot_roster_drift');
    }
    $grants = trainerPilotRows($pdo, 'SELECT * FROM coach_scope_grants WHERE trainer_id=?', [$record['trainer_id']]);
    if (count($grants) !== 1 || (int)$grants[0]['id'] !== (int)$record['grant_id'] || $grants[0]['scope_kind'] !== 'team'
        || (int)$grants[0]['scope_id'] !== (int)$record['team_id'] || $grants[0]['valid_from'] !== $record['valid_from']
        || $grants[0]['valid_to'] !== $record['valid_to'] || $grants[0]['revoked_at'] !== null) {
        throw new RuntimeException('pilot_grant_drift');
    }
    $visible = array_map('intval', array_column(trainerPilotRows(
        $pdo,
        'SELECT s.id FROM sportovci s WHERE ' . coachScopePersonPredicateForTrainer($pdo, (int)$record['trainer_id']) .

     ' ORDER BY s.id'
    ), 'id'));
    if (array_diff($visible, $record['member_ids']) !== []) {
        throw new RuntimeException('pilot_outside_scope_visible');
    }
    $target = trainerPasswordResetTarget($pdo, $trainer[0]['email']);
    if ($target === null || (int)$target['trainer_id'] !== (int)$record['trainer_id']) {
        throw new RuntimeException('pilot_reset_channel_drift');
    }
    return array_merge($result, ['created' => true, 'trainer_id' => (int)$record['trainer_id'], 'team_id'
    => (int)$record['team_id'],
        'test_name' => TRAINER_PILOT_NAME, 'member_ids' => $record['member_ids'], 'outside_test_person_id'
    => $record['outside_id'],
        'valid_from' => $record['valid_from'], 'valid_to' => $record['valid_to'], 'visible_test_people'
    => count($visible),
        'scope_ready' => $visible === $record['member_ids'], 'outside_scope_visible' => false, 'reset_eligible'
    => true,
        'operation_run_id' => $record['operational_authorization']['run_id'] ?? null,
        'full_interactive_uat_verified' => false]);
}

/** Creates only this owned fixture; an existing fixture is verified, never repaired or extended. */
function trainerPilotCreate(PDO $pdo, string $ownerName, string $email, array $operation = []): array
{
    $operational = $operation !== [];
    if ($operational) {
        trainerPilotValidateOperation($operation);
        if ($ownerName !== TRAINER_PILOT_ACTOR_NAME) {
            throw new RuntimeException('pilot_operation_owner_invalid');
        }
    }
    $reason = TRAINER_PILOT_REASON . ($operational ? ' Protected workflow: ' . $operation['actor'] . '; run ' .

     $operation['run_id'] . '.' : '');
    $email = strtolower(trim($email));
    if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('pilot_email_invalid');
    }
    $pdo->beginTransaction();
    try {
        if ($operational) {
            trainerPilotCreateAuditActor($pdo, $operation);
        }
        $owner = trainerPilotOwner($pdo, $ownerName, true, $operational);
        if (!$operational && !$owner['can_provision']) {
            throw new RuntimeException('pilot_owner_permission_required');
        }
        if (trainerPilotRecord($pdo) !== null) {
            $result = trainerPilotStatus($pdo, $ownerName, $email);
            $pdo->rollBack();
            return $result + ['changed' => false];
        }
        $lock = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        if (trainerPilotRows(
            $pdo,
            'SELECT id FROM treneri WHERE LOWER(TRIM(email))=? OR jmeno=?' . $lock,
            [$email, TRAINER_PILOT_NAME]
        ) !== []
            || trainerPilotRows(
                $pdo,
                'SELECT id FROM verejni_uzivatele WHERE LOWER(TRIM(email))=?' . $lock,
                [$email]
            ) !== []
            || trainerPilotRows($pdo, 'SELECT id FROM club_teams WHERE code=?', [TRAINER_PILOT_CODE]) !== []
            || trainerPilotRows($pdo, 'SELECT id FROM sportovci WHERE prijmeni=?', [TRAINER_PILOT_CODE]) !== []) {
            throw new RuntimeException('pilot_identity_collision');
        }
        $pdo->prepare('INSERT INTO nastaveni(klic,hodnota) VALUES(?,?)')->execute([TRAINER_PILOT_KEY, '{}']);
        // Scope predicates use the database clock; do not mix it with PHP's timezone.
        $now = new DateTimeImmutable((string)$pdo->query('SELECT CURRENT_TIMESTAMP')->fetchColumn(), new DateTimeZone('UTC'));
        $from = $now->format('Y-m-d');
        $to = $now->modify('+14 days')->format('Y-m-d');
        $hash = trainer_password_hash(bin2hex(random_bytes(32)));
        $pdo->prepare("INSERT INTO treneri(jmeno,email,heslo,role,aktivni,session_version) VALUES(?,?,?,'trener',1,1)")
            ->execute([TRAINER_PILOT_NAME, $email, $hash]);
        $trainerId = (int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO staff_user_positions(trainer_id,position_code,is_default,assigned_by_trainer_id) VALUES(?,'coach',1,?)")
            ->execute([$trainerId, $owner['id']]);
        $pdo->prepare(('INSERT INTO staff_position_assignment_events(trainer_id,actor_trainer_id,action,before_json,after_json,reason)'
    .
    ' VALUES(?,?,?,?,?,?)'))
            ->execute([$trainerId, $owner['id'], 'provision_test_coach', '{}', '{"positions":["coach"],"superadmin":false}',

     $reason]);
        $team = kisRosterCreateStandingTeam(
            $pdo,
            (int)$owner['id'],
            TRAINER_PILOT_CODE,
            TRAINER_PILOT_NAME,
            'roster',
            $reason
        );
        $people = [];
        foreach (['TEST Alfa', 'TEST Beta', 'TEST Mimo rozsah'] as $firstName) {
            $pdo->prepare("INSERT INTO sportovci(jmeno,prijmeni,narozeni,email,telefon,hash,uci,stav_clenstvi) VALUES(?,?,'2000-01-01','','',?,0,'aktivni')")
                ->execute([$firstName, TRAINER_PILOT_CODE, bin2hex(random_bytes(24))]);
            $people[] = (int)$pdo->lastInsertId();
        }
        foreach (array_slice($people, 0, 2) as $personId) {
            kisRosterGrantPeriod(
                $pdo,
                (int)$team['id'],
                $personId,
                (int)$owner['id'],
                'admin',
                $from,
                $to,
                $reason,
                TRAINER_PILOT_KEY . ':' . $personId
            );
        }
        $grant = ['trainer_id' => $trainerId, 'scope_kind' => 'team', 'scope_id' => (int)$team['id'],
            'valid_from' => $now->format('Y-m-d H:i:s'), 'valid_to' => $now->modify('+14 days')->format('Y-m-d H:i:s'),

     'reason' => $reason];
        $pdo->prepare(('INSERT INTO coach_scope_grants(trainer_id,scope_kind,scope_id,valid_from,valid_to,reason,created_by_trainer_id'
    .
    ') VALUES(?,?,?,?,?,?,?)'))
            ->execute([...array_values($grant), $owner['id']]);
        $grantId = (int)$pdo->lastInsertId();
        coachScopeEvent($pdo, $grantId, (int)$owner['id'], 'grant', $grant);
        $record = ['version' => 1, 'owner_id' => (int)$owner['id'], 'trainer_id' => $trainerId, 'team_id'
    => (int)$team['id'],
            'member_ids' => array_slice($people, 0, 2), 'outside_id' => $people[2], 'grant_id' => $grantId,


            'valid_from' => $grant['valid_from'], 'valid_to' => $grant['valid_to'], 'email_sha256' => hash(
                'sha256',
                $email
            ),
            'operational_authorization' => $operation];
        $pdo->prepare('UPDATE nastaveni SET hodnota=? WHERE klic=?')->execute([json_encode($record, JSON_THROW_ON_ERROR),

     TRAINER_PILOT_KEY]);
        $result = trainerPilotStatus($pdo, $ownerName, $email);
        if (!$result['scope_ready']) {
            throw new RuntimeException('pilot_scope_not_ready');
        }
        if (!hash_equals($owner['fingerprint'], trainerPilotOwner($pdo, $ownerName, true, $operational)['fingerprint'])) {
            throw new RuntimeException('pilot_owner_changed');
        }
        $pdo->commit();
        return array_replace($result, ['read_only' => false, 'changed' => true]);
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}
