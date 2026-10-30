<?php

declare(strict_types=1);

use PHPUnit\Framework\Assert;

require_once dirname(__DIR__, 2) . '/includes/training_closure.php';
require_once dirname(__DIR__, 2) . '/includes/accounting_export.php';
require_once dirname(__DIR__, 2) . '/includes/family_portal.php';

/** Continue the same approved, paid family/program case into attendance and a real export. */
function parentJourneyComplete(PDO $pdo, int $accountId, int $paymentId): void
{
    $pdo->exec('ALTER TABLE treneri ADD COLUMN aktivni INTEGER NOT NULL DEFAULT 1');
    foreach (['staff_user_positions(trainer_id INTEGER,position_code TEXT)',
        'staff_superadmins(trainer_id INTEGER)',
        'treninky(id INTEGER PRIMARY KEY,datum TEXT,delka REAL,napln TEXT,kategorie TEXT)',
        'trenink_trener(trenink_id INTEGER,trener_id INTEGER)',
        'trenink_sportovec(trenink_id INTEGER,sportovec_id INTEGER)',
        'trenink_mereni(trenink_id INTEGER,mereni_id INTEGER)',
        'planovane_treninky(id INTEGER PRIMARY KEY,trener_id INTEGER,datum TEXT,trenink_id INTEGER)',
        'training_roster_links(id INTEGER PRIMARY KEY,plan_id INTEGER,trenink_id INTEGER,team_id INTEGER)',
        'training_roster_expected(link_id INTEGER,sportovec_id INTEGER)'] as $schema) {
        $pdo->exec('CREATE TABLE ' . $schema);
    }
    foreach (['20261023020000_training_closure', '20261022220000_accounting_ledger',
        '20261022221000_accounting_documents', '20261022222000_accounting_adjustments',
        '20261022223000_accounting_exports'] as $id) {
        $migration = require dirname(__DIR__, 2) . '/migrations/' . $id . '.php';
        $migration['up']($pdo);
        Assert::assertTrue($migration['verify']($pdo));
    }
    $pdo->exec("INSERT INTO staff_user_positions VALUES(7,'sports_lead'),(7,'finance_manager')");
    $_SESSION = ['trener_id' => 7, 'role' => 'admin', 'staff_positions' => ['sports_lead', 'finance_manager'],
        'staff_active_position' => 'sports_lead', 'staff_superadmin' => false];
    $date = date('Y-m-d', strtotime('-2 days'));
    $pdo->prepare("INSERT INTO treninky VALUES(100,?,1.5,'Synthetic program attendance','draha')")->execute([$date]);
    $closure = trainingClosureClose($pdo, 100, 0, ['actual_started_at' => $date . ' 15:00:00',
        'actual_ended_at' => $date . ' 16:30:00', 'participant_ids' => [101], 'coach_ids' => [7],
        'reason' => 'LOCALHOST confirmed synthetic attendance.', 'confirmed' => true, 'record_source' => 'paper'], 7);
    Assert::assertSame([101], $closure['participant_ids']);
    Assert::assertSame(90, trainingClosedActivitiesForPerson($pdo, 101, $date, $date)[0]['duration_minutes']);
    Assert::assertTrue(familyPortalCanViewPerson($pdo, $accountId, 101));
    Assert::assertFalse(familyPortalCanViewPerson($pdo, 11, 101));
    $_SESSION['staff_active_position'] = 'finance_manager';
    $today = date('Y-m-d');
    $reason = 'LOCALHOST synthetic parent journey completion.';
    $account = accountingLedgerCreateAccount($pdo, ['code' => 'W15', 'name' => 'Synthetic', 'kind' => 'bank',
        'opened_on' => $date, 'opening_minor' => 0, 'evidence' => $reason], 7, $reason, true);
    $movement = accountingLedgerReconcileSource($pdo, 'payments', $paymentId, (int) $account['id'], $today, $reason, 7, $reason, true);
    $repeat = accountingLedgerReconcileSource($pdo, 'payments', $paymentId, (int) $account['id'], $today, $reason, 7, $reason, true);
    Assert::assertSame($movement['id'], $repeat['id']);
    Assert::assertSame(10000, (int) $movement['amount_minor']);
    $directory = sys_get_temp_dir() . '/w15-journey-' . bin2hex(random_bytes(8));
    mkdir($directory, 0700);
    $oldPrivate = getenv('APP_PRIVATE_STORAGE_ROOT');
    putenv('APP_PRIVATE_STORAGE_ROOT=' . $directory);
    try {
        $export = accountingExportCreate($pdo, $date, $today, 'w15-export', 7, $reason, true);
        $snapshot = json_decode($export['snapshot_json'], true, 512, JSON_THROW_ON_ERROR);
        Assert::assertSame(10000, array_sum(array_column($snapshot['movements'], 'amount_minor')));
        Assert::assertCount(1, $snapshot['movements']);
        Assert::assertSame('NEÚPLNÝ', $snapshot['status'], 'Synthetic technical proof is not accountant sign-off.');
        $download = accountingExportDownload($pdo, (int) $export['id'], 7);
        Assert::assertSame($export['archive_sha256'], hash_file('sha256', $download['path']));
        $pdo->prepare("UPDATE account_person_roles SET status='revoked' WHERE account_id=? AND sportovec_id=101")
            ->execute([$accountId]);
        Assert::assertFalse(familyPortalCanViewPerson($pdo, $accountId, 101));
        Assert::assertSame($export['archive_sha256'], hash_file('sha256', $download['path']));
        $pdo->exec("DELETE FROM staff_user_positions WHERE trainer_id=7 AND position_code='finance_manager'");
        try {
            accountingExportDownload($pdo, (int) $export['id'], 7);
            Assert::fail('Revoked finance permission must deny the existing session.');
        } catch (DomainException) {
            Assert::assertTrue(true);
        }
    } finally {
        putenv($oldPrivate === false ? 'APP_PRIVATE_STORAGE_ROOT' : 'APP_PRIVATE_STORAGE_ROOT=' . $oldPrivate);
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            if ($file->isDir() && !$file->isLink()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($directory);
    }
}
