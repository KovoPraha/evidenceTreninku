<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class DeployWorkflowContractTest extends TestCase
{
    public function testBackupMigrationAndReleaseActivationHaveSafeOrder(): void
    {
        $workflow = $this->source('.github/workflows/deploy-production.yml');

        $backup = strpos($workflow, '- name: Vytvořit a ověřit produkční zálohu');
        $prepare = strpos($workflow, '- name: Připravit kompletní release mimo webroot');
        $migrate = strpos($workflow, '- name: Aplikovat migrace z připraveného release');
        $activate = strpos($workflow, '- name: Aktivovat ověřený release');
        $smoke = strpos($workflow, '- name: HTTP smoke test bez tajného tokenu');

        foreach ([$backup, $prepare, $migrate, $activate, $smoke] as $position) {
            self::assertNotFalse($position);
        }
        self::assertLessThan($prepare, $backup);
        self::assertLessThan($migrate, $prepare);
        self::assertLessThan($activate, $migrate);
        self::assertLessThan($smoke, $activate);

        self::assertStringContainsString('AUTH_RATE_LIMIT_PEPPER', $workflow);
        self::assertStringContainsString('strlen(\\$pepper) < 32', $workflow);
        self::assertStringContainsString('RELEASE_DIR: .evidence-deploy/releases/', $workflow);
        self::assertStringContainsString('cd \\"\\$HOME/$RELEASE_DIR\\"', $workflow);
        self::assertStringContainsString('\\"\\$HOME/$RELEASE_DIR/\\"', $workflow);
        self::assertStringContainsString("--exclude='config.php'", $workflow);
        self::assertStringContainsString('Odstranit kopii produkční konfigurace z release', $workflow);
        self::assertStringNotContainsString('./ "$SSH_USER@$SSH_HOST:$REMOTE_DIR/"', $workflow);
    }

    public function testBackupOwnershipIncludesCurrentMigrationTables(): void
    {
        $backup = $this->source('bin/db-backup.php');

        self::assertStringContainsString("'auth_login_limits'", $backup);
        self::assertStringContainsString(
            "EVIDENCE_OWNERSHIP_CONTRACT_VERSION = '2026-08-05.2'",
            $backup
        );
        foreach (['shop_orders', 'club_events', 'account_person_roles', 'fio_account_movements', 'club_roster_members', 'club_team_series', 'training_roster_links', 'club_program_enrollments', 'club_event_roster_targets', 'club_roster_rollover_runs', 'public_self_profiles', 'public_velodrome_reservation_events', 'child_access_accounts', 'child_access_events', 'public_velodrome_cart_items', 'public_velodrome_order_items', 'club_event_cart_items', 'club_event_order_items', 'kis_import_source_artifacts', 'password_reset_tokens', 'club_member_charges', 'club_member_charge_events', 'kis_import_payment_rows', 'kis_import_sandbox_promotions', 'kis_import_sandbox_items', 'kis_import_sandbox_events', 'kis_import_charge_promotions', 'kis_import_charge_promotion_items', 'kis_import_charge_promotion_events', 'shop_member_category_rules', 'shop_member_product_prices', 'shop_member_price_events', 'family_calendar_feeds', 'family_calendar_feed_events', 'member_charge_reminder_preferences', 'member_charge_reminders', 'member_charge_reminder_events'] as $table) {
            self::assertStringContainsString("'{$table}'", $backup);
        }
    }

    public function testEveryPermanentMigrationTableBelongsToBackupOwnership(): void
    {
        $backup = $this->source('bin/db-backup.php');
        preg_match_all("/^\\s*'([a-z0-9_]+)',\\s*$/m", $backup, $ownedMatches);
        $owned = array_fill_keys($ownedMatches[1], true);
        $missing = [];
        foreach (glob(dirname(__DIR__, 2) . '/migrations/*.php') ?: [] as $migration) {
            $source = file_get_contents($migration);
            self::assertIsString($source);
            preg_match_all('/CREATE TABLE(?: IF NOT EXISTS)?\\s+`?([A-Za-z0-9_]+)/i', $source, $createdMatches);
            foreach ($createdMatches[1] as $table) {
                if (str_ends_with($table, '_next')) continue;
                if (!isset($owned[$table])) $missing[$table] = basename($migration);
            }
        }
        self::assertSame([], $missing, 'Migrační tabulky chybí v EVIDENCE_TABLES: ' . json_encode($missing));
    }

    public function testContinuousIntegrationRunsDedicatedMariaDbSmokes(): void
    {
        $workflow = $this->source('.github/workflows/tests.yml');

        self::assertStringContainsString('mariadb-smoke:', $workflow);
        self::assertStringContainsString('image: mariadb:11.4', $workflow);
        self::assertStringContainsString('MARIADB_ALLOW_EMPTY_ROOT_PASSWORD', $workflow);
        self::assertStringContainsString('extensions: mbstring, pdo_mysql', $workflow);
        self::assertStringContainsString('php tests/Support/ChildAccessMariaDbSmoke.php', $workflow);
        self::assertStringContainsString('php tests/Support/KisHobbyTransitionMariaDbSmoke.php', $workflow);
        self::assertStringContainsString('php tests/Support/DatabaseBackupMariaDbSmoke.php', $workflow);
    }

    private function source(string $relativePath): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $relativePath);
        self::assertIsString($source);
        return $source;
    }
}
