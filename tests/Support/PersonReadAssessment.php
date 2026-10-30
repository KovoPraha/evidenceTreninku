<?php

declare(strict_types=1);

require_once __DIR__ . '/FamilyOverviewPageFixture.php';
require_once dirname(__DIR__, 2) . '/includes/athlete_registration.php';
require_once dirname(__DIR__, 2) . '/includes/sportovec_editor_details.php';

$schema = 'w76_person_' . bin2hex(random_bytes(8));
$admin = w72Connection();
$admin->exec('CREATE DATABASE `' . $schema . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
try {
    $pdo = w72Connection($schema, true);
    w72Seed($pdo);
    $pdo->exec("INSERT INTO account_person_roles(account_id,sportovec_id,relation_role,status,valid_from,created_by_trainer_id,decision_note)"
        . " VALUES(10,8,'guardian','approved','2020-01-01',7,'Synthetic')");
    $claim = $pdo->prepare('INSERT INTO account_person_claim_requests(account_id,requested_role,claimed_jmeno,claimed_prijmeni,'
        . "claimed_narozeni,requester_message,status) VALUES(?,'guardian','Synthetic','History','2017-01-01',?,'rejected')");
    $claim->execute([11, 'FOREIGN_SENTINEL']);
    $inserted = 0;
    $results = [];
    foreach ([0, 100, 1000] as $size) {
        $pdo->beginTransaction();
        for ($i = $inserted; $i < $size; $i++) {
            $claim->execute([10, str_repeat('Synthetic local history. ', 20)]);
        }
        $pdo->commit();
        $inserted = $size;
        $pdo->profile = [];
        $pdo->profiling = true;
        $claims = accountPersonClaimListForAccount($pdo, 10);
        $people = accountPersonEligibleParticipants($pdo, 10);
        $terms = athleteRegistrationCurrentTerms($pdo);
        $pdo->profiling = false;
        if (count($claims) !== $size || count($people) !== 1 || (int) $people[0]['sportovec_id'] !== 8
            || str_contains(json_encode($claims, JSON_THROW_ON_ERROR), 'FOREIGN_SENTINEL') || count($pdo->profile) !== 7) {
            throw new RuntimeException('R03 read scope or fixed query budget changed.');
        }
        $results[] = ['historical_claims' => $size, 'sql' => count($pdo->profile),
            'fetched_rows' => array_sum(array_column($pdo->profile, 'fetched_rows')), 'eligible_people' => count($people),
            'terms' => count($terms), 'foreign_data_absent' => true];
    }
    $pdo->exec("UPDATE account_person_roles SET status='revoked' WHERE account_id=10");
    if (accountPersonEligibleParticipants($pdo, 10) !== []) {
        throw new RuntimeException('Fresh role revocation was not observed.');
    }
    $pdo->exec("UPDATE account_person_roles SET status='approved' WHERE account_id=10");
    $pdo->exec('UPDATE verejni_uzivatele SET email_overeno=0 WHERE id=10');
    if (accountPersonEligibleParticipants($pdo, 10) !== []) {
        throw new RuntimeException('Unverified account retained eligible participants.');
    }
    $pdo->profile = [];
    $pdo->profiling = true;
    $rosters = sportovecEditorRosters($pdo, 8, '2026-10-30');
    $payments = sportovecEditorPayments($pdo, 8);
    $pdo->profiling = false;
    $editor = ['sql' => count($pdo->profile), 'fetched_rows' => array_sum(array_column($pdo->profile, 'fetched_rows')),
        'roster_counts' => array_map('count', $rosters), 'payment_counts' => array_map('count', $payments),
        'queries' => $pdo->profile];
    echo json_encode(['database_version' => $pdo->query('SELECT VERSION()')->fetchColumn(), 'migrated_schema' => true,
        'production_mutations' => false, 'r03' => $results, 'fresh_revocation' => true, 'unverified_account_denied' => true,
        'r04_editor' => $editor], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
} finally {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $admin->exec('DROP DATABASE `' . $schema . '`');
}
