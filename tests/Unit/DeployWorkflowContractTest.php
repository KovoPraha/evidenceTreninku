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

        // Hostingový omezený shell zakazuje `php -r`; validace configu proto
        // běží v nahraném bin/deploy-preflight.php a workflow žádné `php -r`
        // na serveru nespouští.
        $preflight = $this->source('bin/deploy-preflight.php');
        self::assertStringNotContainsString('php -r', $workflow);
        self::assertStringContainsString('AUTH_RATE_LIMIT_PEPPER', $preflight);
        self::assertStringContainsString('strlen($pepper) < 32', $preflight);
        self::assertStringContainsString('DEPLOY_PROBE', $preflight);
        self::assertStringContainsString('JE_LOKALNE', $preflight);
        self::assertStringContainsString("PHP_SAPI !== 'cli'", $preflight);
        self::assertStringContainsString('APP_BASE_URL', $preflight);
        self::assertStringContainsString("'warnings' => \$warnings", $preflight);
        self::assertStringContainsString('shopBankSettingsEffective', $preflight);
        self::assertStringContainsString('Bankovní účet e-shopu není kompletně a platně nastavený', $preflight);
        self::assertStringContainsString("!== 'https'", $preflight);
        self::assertStringContainsString("!== strtolower(\$appHost)", $preflight);
        self::assertStringContainsString("jq -r '.warnings[]?'", $workflow);
        self::assertStringContainsString('::warning title=Produkční konfigurace::', $workflow);

        // Omezený shell hostingu blokuje argumenty skriptů i externí proměnné
        // prostředí; hodnoty proto nastavují vygenerované putenv() bootstrapy
        // a na serveru se spouští výhradně holé `php soubor.php`.
        self::assertStringContainsString("putenv('DEPLOY_PROBE=1');", $workflow);
        self::assertStringContainsString("php '\$DEPLOY_BASE/run-preflight.php'", $workflow);
        self::assertStringContainsString("putenv('MIGRATE_ACTION=\$AKCE');", $workflow);
        self::assertStringContainsString('php run-migrate-apply.php', $workflow);
        self::assertStringContainsString('php run-migrate-check.php', $workflow);
        self::assertStringContainsString("rm -f '\$RELEASE_DIR/run-migrate-apply.php'", $workflow);
        self::assertStringContainsString("putenv('BACKUP_TARGET_DIR=' . dirname(__DIR__) . '/.kis-backups');", $workflow);
        self::assertStringContainsString("php '\$DEPLOY_BASE/run-backup.php'", $workflow);

        // Hosting může blokovat IP rozsahy GitHub runnerů; smoke test má proto
        // SSH fallback přes nahraný bin/deploy-smoke.php se stejným putenv
        // bootstrapem. Skutečná HTTP chyba z přímého testu fallback nespouští.
        $smokeScript = $this->source('bin/deploy-smoke.php');
        self::assertStringContainsString("PHP_SAPI !== 'cli'", $smokeScript);
        self::assertStringContainsString('DEPLOY_PROBE', $smokeScript);
        self::assertStringContainsString('SMOKE_URL', $smokeScript);
        self::assertStringContainsString("putenv('SMOKE_URL=\$WEB_URL/index.php');", $workflow);
        self::assertStringContainsString("php '\$DEPLOY_BASE/run-smoke.php'", $workflow);
        self::assertStringContainsString('--retry-all-errors', $workflow);
        self::assertStringNotContainsString('php bin/migrate.php --', $workflow);
        self::assertStringContainsString('MIGRATE_ACTION', $this->source('bin/migrate.php'));
        self::assertStringContainsString('BACKUP_TARGET_DIR', $this->source('bin/db-backup.php'));

        // Cíl kis.kovopraha.cz je parametrizovaný přes GitHub Variables
        // a fail-closed kontrolu jejich přítomnosti.
        self::assertStringContainsString('APP_HOST: ${{ vars.KIS_APP_HOST }}', $workflow);
        self::assertStringContainsString('WEB_URL: ${{ vars.KIS_WEB_URL }}', $workflow);
        self::assertStringContainsString('REMOTE_DIR: ${{ vars.KIS_REMOTE_DIR }}', $workflow);
        self::assertStringContainsString('- name: Ověřit konfigurační Variables', $workflow);
        self::assertStringContainsString('environment: production', $workflow);
        self::assertStringContainsString('group: produkce-kis', $workflow);

        // Chroot server nemá funkční $HOME; deploy stav žije relativně v data/.
        self::assertStringContainsString('RELEASE_DIR: data/.kis-deploy/releases/', $workflow);
        self::assertStringContainsString('BACKUP_DIR: data/.kis-backups', $workflow);
        self::assertStringContainsString("cd '\$RELEASE_DIR'", $workflow);
        self::assertStringContainsString("'\$RELEASE_DIR/' '\$REMOTE_DIR/'", $workflow);
        self::assertStringContainsString("test -s '\$REMOTE_DIR/kis_rollover_a06_admin.php'", $workflow);
        self::assertStringContainsString('composer install --no-dev', $workflow);
        self::assertLessThan(
            strpos($workflow, '- name: Připravit kompletní release mimo webroot'),
            strpos($workflow, 'composer install --no-dev')
        );
        self::assertStringContainsString('/vendor/', $this->source('.gitignore'));
        self::assertStringNotContainsString('$HOME/$RELEASE_DIR', $workflow);
        self::assertStringNotContainsString('.evidence-deploy', $workflow);
        self::assertStringNotContainsString('.evidence-backups', $workflow);

        self::assertStringContainsString("--exclude='config.php'", $workflow);
        self::assertStringContainsString('Odstranit kopii produkční konfigurace z release', $workflow);
        self::assertStringNotContainsString('./ "$SSH_USER@$SSH_HOST:$REMOTE_DIR/"', $workflow);
    }

    public function testBackupOwnershipIncludesCurrentMigrationTables(): void
    {
        $backup = $this->source('bin/db-backup.php');

        self::assertStringContainsString("'auth_login_limits'", $backup);
        self::assertStringContainsString(
            "EVIDENCE_OWNERSHIP_CONTRACT_VERSION = '2026-08-19.1'",
            $backup
        );
        foreach (['source_candidate_id', 'source_run_id', 'origin', 'created_by_trainer_id'] as $column) {
            self::assertStringContainsString("'{$column}'", $backup);
        }
        foreach (['shop_orders', 'shop_catalog_admin_events', 'club_events', 'account_person_roles', 'fio_account_movements', 'club_roster_members', 'club_team_series', 'training_roster_links', 'club_program_enrollments', 'club_program_events', 'club_event_roster_targets', 'club_roster_rollover_runs', 'public_self_profiles', 'public_velodrome_reservation_events', 'child_access_accounts', 'child_access_events', 'public_velodrome_cart_items', 'public_velodrome_order_items', 'club_event_cart_items', 'club_event_order_items', 'kis_import_source_artifacts', 'password_reset_tokens', 'club_member_charges', 'club_member_charge_events', 'kis_import_payment_rows', 'kis_import_sandbox_promotions', 'kis_import_sandbox_items', 'kis_import_sandbox_events', 'kis_import_charge_promotions', 'kis_import_charge_promotion_items', 'kis_import_charge_promotion_events', 'shop_member_category_rules', 'shop_member_product_prices', 'shop_member_price_events', 'family_calendar_feeds', 'family_calendar_feed_events', 'family_weekly_summary_preferences', 'family_weekly_summaries', 'family_weekly_summary_events', 'member_charge_reminder_preferences', 'member_charge_reminders', 'member_charge_reminder_events', 'stripe_webhook_events', 'athlete_registration_request_details', 'athlete_registration_consent_snapshots', 'athlete_private_files', 'osoba_citlive_udaje', 'osoba_citlive_pristupy'] as $table) {
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
        self::assertStringContainsString("mariadb-version: ['10.3', '11.4']", $workflow);
        self::assertStringContainsString('image: mariadb:${{ matrix.mariadb-version }}', $workflow);
        self::assertStringContainsString('MARIADB_ALLOW_EMPTY_ROOT_PASSWORD', $workflow);
        self::assertStringContainsString('extensions: mbstring, pdo_mysql', $workflow);
        self::assertStringContainsString('php tests/Support/ChildAccessMariaDbSmoke.php', $workflow);
        self::assertStringContainsString('php tests/Support/KisHobbyTransitionMariaDbSmoke.php', $workflow);
        self::assertStringContainsString('php tests/Support/DatabaseBackupMariaDbSmoke.php', $workflow);
        self::assertStringContainsString('php tests/Support/ShopManualCatalogOriginMariaDbSmoke.php', $workflow);
    }

    public function testCheckoutActionUsesPinnedNode24ReleaseInEveryWorkflow(): void
    {
        $pin = 'actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1';
        foreach (['tests.yml', 'deploy-production.yml', 'production-drills.yml'] as $name) {
            $workflow = $this->source('.github/workflows/' . $name);
            self::assertStringContainsString($pin, $workflow);
            self::assertStringNotContainsString('actions/checkout@v', $workflow);
            self::assertStringNotContainsString('actions/checkout@34e114876b0b11c390a56381ad16ebd13914f8d5', $workflow);
        }
    }

    public function testProductionDrillRestoresEphemerallyAndCleanupIsNarrowlyScoped(): void
    {
        $workflow = $this->source('.github/workflows/production-drills.yml');
        $cleanup = $this->source('bin/production-test-cleanup.php');

        self::assertStringContainsString('obnovit-zalohu-izolovane', $workflow);
        self::assertStringContainsString('obnovit-tri-zalohy-izolovane', $workflow);
        self::assertStringContainsString('overit-databazove-invarianty', $workflow);
        self::assertStringContainsString('deaktivovat-testovaci-ucty', $workflow);
        self::assertStringContainsString('vytvorit-kis-test-admina', $workflow);
        self::assertStringContainsString("inputs.potvrzeni", $workflow);
        self::assertStringContainsString("= 'PROVEST'", $workflow);
        self::assertStringContainsString('sha256sum -c', $workflow);
        self::assertStringContainsString('mariadb:11.4', $workflow);
        self::assertStringContainsString("-Nse 'SELECT 1'", $workflow);
        self::assertStringContainsString('test "$READY" = \'1\'', $workflow);
        self::assertStringContainsString('CREATE DATABASE kis_restore', $workflow);
        self::assertStringContainsString("jq -r '.tables | to_entries[]", $workflow);
        self::assertStringContainsString("then COUNT=3", $workflow);
        self::assertStringContainsString('test "${#BACKUPS[@]}" = "$COUNT"', $workflow);
        self::assertStringContainsString('CONTAINER="kis-restore-$GITHUB_RUN_ID-$VERIFIED"', $workflow);
        self::assertStringNotContainsString('upload-artifact', $workflow);
        self::assertStringNotContainsString('rsync', $workflow);

        self::assertStringContainsString("PHP_SAPI !== 'cli'", $cleanup);
        self::assertStringContainsString("KIS_TEST_CLEANUP_CONFIRM", $cleanup);
        self::assertStringContainsString('secrets.KIS_TEST_ADMIN_PASSWORD', $workflow);
        self::assertStringContainsString('provision-production-test-admin.php', $workflow);
        self::assertStringContainsString("'^kis-e2e-[0-9]+@velocota[.]com$'", $cleanup);
        self::assertStringContainsString('SET aktivni=0,session_version=session_version+1', $cleanup);
        self::assertStringNotContainsString('DELETE FROM verejni_uzivatele', $cleanup);
    }

    public function testProductionInvariantDrillIsGuardedAndSelectOnly(): void
    {
        $workflow = $this->source('.github/workflows/production-drills.yml');
        $invariants = $this->source('bin/production-invariants.php');

        self::assertStringContainsString("KIS_INVARIANT_CONFIRM=OVERIT", $workflow);
        self::assertStringContainsString("php '\$DEPLOY_BASE/run-production-invariants.php'", $workflow);
        self::assertStringContainsString("PHP_SAPI !== 'cli'", $invariants);
        self::assertStringContainsString("\$confirm !== 'OVERIT'", $invariants);
        self::assertStringContainsString('information_schema.TABLES', $invariants);
        self::assertStringContainsString('invalid_shop_order_totals', $invariants);
        self::assertStringContainsString('orphan_training_assignments_people', $invariants);
        self::assertStringNotContainsString('UPDATE ', $invariants);
        self::assertStringNotContainsString('DELETE ', $invariants);
        self::assertStringNotContainsString('INSERT ', $invariants);
        self::assertStringNotContainsString('FOR UPDATE', $invariants);
    }

    private function source(string $relativePath): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $relativePath);
        self::assertIsString($source);
        return $source;
    }
}
