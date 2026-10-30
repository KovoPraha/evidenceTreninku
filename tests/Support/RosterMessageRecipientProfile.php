<?php

declare(strict_types=1);

require_once __DIR__ . '/FamilyOverviewPageFixture.php';
require_once dirname(__DIR__, 2) . '/includes/roster_message_recipient.php';

$schema = 'w112_recipient_' . bin2hex(random_bytes(8));
$admin = w72Connection();
$admin->exec('CREATE DATABASE `' . $schema . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
try {
    $pdo = w72Connection($schema, true);
    w72Seed($pdo);
    $person = $pdo->prepare("INSERT INTO sportovci(id,jmeno,prijmeni,narozeni) VALUES(?,'Synthetic','Member','2010-01-01')");
    $account = $pdo->prepare('INSERT INTO verejni_uzivatele(id,jmeno,prijmeni,email,aktivni,email_overeno,registrovan) '
        . "VALUES(?,'Synthetic','Parent',?,1,1,CURRENT_TIMESTAMP)");
    $role = $pdo->prepare('INSERT INTO account_person_roles(account_id,sportovec_id,relation_role,status,valid_from,created_by_trainer_id,decision_note) '
        . "VALUES(?,?,'guardian','approved','2020-01-01',7,'Synthetic')");
    $member = $pdo->prepare('INSERT INTO club_roster_members(team_id,sportovec_id,status,source,valid_from,created_by_trainer_id) '
        . "VALUES(1,?,'active','manual','2020-01-01',7)");
    $row = ['team_id' => 1, 'account_id' => 10001, 'recipient_email' => 'person1@example.test',
        'subject_plain' => 'Synthetic', 'body_plain' => 'Synthetic'];
    $baseline = static function (PDO $db) use ($row): bool {
        $preview = rosterMessagePreview($db, 1, 'Synthetic', 'Synthetic');
        foreach ($preview['recipients'] as $recipient) {
            if ((int) $recipient['account_id'] === $row['account_id'] && $recipient['email'] === $row['recipient_email']) {
                return true;
            }
        }
        return false;
    };
    $current = static fn (PDO $db): bool => rosterMessageRecipientAllowed($db, $row);
    $plain = w72Connection($schema);
    $previous = 0;
    $results = [];
    foreach ([100, 1000, 10000] as $size) {
        $pdo->beginTransaction();
        for ($i = $previous + 1; $i <= $size; $i++) {
            $id = 10000 + $i;
            $person->execute([$id]);
            $account->execute([$id, 'person' . $i . '@example.test']);
            $role->execute([$id, $id]);
            $member->execute([$id]);
        }
        $pdo->commit();
        foreach (['baseline' => $baseline, 'targeted' => $current] as $mode => $call) {
            $pdo->profile = [];
            $pdo->profiling = true;
            if (!$call($pdo)) {
                throw new RuntimeException('Authorized synthetic recipient rejected.');
            }
            $pdo->profiling = false;
            $counts = ['sql' => count($pdo->profile), 'rows' => array_sum(array_column($pdo->profile, 'fetched_rows'))];
            if ($mode === 'targeted' && ($counts['sql'] > 8 || $counts['rows'] > 10)) {
                throw new RuntimeException('Target recipient budget exceeded.');
            }
            $samples = [];
            for ($sample = -2; $sample < 10; $sample++) {
                memory_reset_peak_usage();
                $memory = memory_get_usage();
                $start = hrtime(true);
                $allowed = $call($plain);
                $ms = (hrtime(true) - $start) / 1e6;
                if (!$allowed) {
                    throw new RuntimeException('Unstable recipient decision.');
                }
                if ($sample >= 0) {
                    $samples[] = ['ms' => $ms, 'extra_peak_bytes' => memory_get_peak_usage() - $memory];
                }
            }
            $results[] = ['synthetic_members_added' => $size, 'mode' => $mode] + $counts + ['warmups' => 2, 'samples' => $samples];
        }
        $pdo->exec("UPDATE account_person_roles SET status='revoked' WHERE account_id=10001");
        if ($baseline($pdo) || $current($pdo)) {
            throw new RuntimeException('Revoked recipient still authorized.');
        }
        $pdo->exec("UPDATE account_person_roles SET status='approved' WHERE account_id=10001");
        $previous = $size;
    }
    echo json_encode(['schema' => 'full catalog, FK enabled, random localhost DB with cleanup',
        'results' => $results, 'revocation_each_size' => 'pass', 'transport' => 'none'], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
} finally {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $pdo = $plain = null;
    $admin->exec('DROP DATABASE `' . $schema . '`');
}
