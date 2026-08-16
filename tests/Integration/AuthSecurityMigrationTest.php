<?php
declare(strict_types=1);

namespace Tests\Integration;

use EvidenceMigrationCatalog;
use EvidenceMigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/migration_runner.php';

final class AuthSecurityMigrationTest extends TestCase
{
    public function testActualMigrationUpgradesIsolatedBaselineDatabaseAndIsIdempotent(): void
    {
        $pdo = $this->baselineDatabase();
        $catalog = EvidenceMigrationCatalog::load(dirname(__DIR__, 2) . '/migrations');
        $runner = new EvidenceMigrationRunner($pdo, $catalog);

        $first = $runner->apply();
        $second = $runner->apply();

        self::assertTrue($first['current']);
        self::assertTrue($second['current']);
        self::assertSame(
            [
                '20260802120000_auth_revocation_rate_limit',
                '20260802133000_one_time_tokens',
                '20260802170000_shop_catalog_staging',
                '20260802190000_shop_catalog_review',
                '20260802210000_shop_canonical_catalog',
                '20260802230000_account_person_roles',
                '20260802233000_account_person_claim_requests',
                '20260803090000_shop_product_publication',
                '20260803110000_club_events',
                '20260803130000_club_event_registrations',
                '20260803150000_club_event_terms',
                '20260803170000_club_event_waitlist',
                '20260803190000_club_event_notifications',
                '20260803210000_club_event_notification_admin',
                '20260803230000_shop_checkout',
                '20260804010000_shop_order_fulfillment',
                '20260804030000_shop_order_refunds',
                '20260804050000_shop_coupons',
                '20260804070000_fio_readonly_import',
                '20260804090000_kis_teams_rosters',
                '20260804110000_kis_roster_policies',
                '20260804120000_shop_item_beneficiaries',
                '20260804130000_training_roster_bridge',
                '20260804140000_club_programs',
                '20260804150000_club_event_roster_targets',
                '20260804160000_club_program_lifecycle',
                '20260804170000_kis_roster_rollover_execution',
                '20260804180000_public_velodrome',
                '20260804190000_child_access_accounts',
                '20260804200000_public_velodrome_shop',
                '20260804210000_shop_order_expiration',
                '20260804230000_club_event_shop',
                '20260804233000_kis_import_source_artifacts',
                '20260804234000_shop_coupon_applicability',
                '20260804234500_kis_import_preview_integrity',
                '20260804234700_kis_import_sandbox_promotion',
                '20260804234800_kis_import_field_contract',
                '20260804234900_kis_import_parity_report',
                '20260804234950_member_charge_target',
                '20260804234975_kis_member_charge_promotion',
                '20260804235000_club_program_repeat_enrollment',
                '20260804235500_public_profile_token_rotation',
                '20260804235900_password_reset_tokens',
                '20260805000000_unified_accounts_public_schedule',
                '20260805010000_family_calendar_feeds',
                '20260805010000_shop_member_pricing',
                '20260805020000_member_charge_reminders',
                '20260805030000_member_charge_reminder_admin',
                '20260805040000_family_weekly_summaries',
                '20260805050000_sports_measurement_contract',
                '20260809090000_stripe_checkout',
                '20260810003000_legacy_training_support_tables',
                '20260816143000_athlete_registration_foundation',
                '20260816170000_shop_payment_received_notification',
                '20260816180000_registration_terms_scope',
            ],
            array_keys($catalog)
        );
        self::assertSame(1, $this->sessionVersion($pdo, 'treneri', 1));
        self::assertSame(1, $this->sessionVersion($pdo, 'verejni_uzivatele', 1));
        self::assertTrue($this->tableExists($pdo, 'auth_login_limits'));
        self::assertTrue($this->tableExists($pdo, 'shop_catalog_import_runs'));
        self::assertTrue($this->tableExists($pdo, 'child_access_accounts'));
        self::assertTrue($this->tableExists($pdo, 'child_access_events'));
        self::assertTrue($this->tableExists($pdo, 'cviky'));
        self::assertTrue($this->tableExists($pdo, 'gs_kategorie'));
        self::assertTrue($this->tableExists($pdo, 'gs_linky'));
        self::assertTrue($this->tableExists($pdo, 'gs_link_targets'));
        self::assertTrue($this->tableExists($pdo, 'athlete_registration_request_details'));
        self::assertTrue($this->tableExists($pdo, 'athlete_registration_consent_snapshots'));
        self::assertTrue($this->tableExists($pdo, 'athlete_private_files'));
        self::assertTrue($this->tableExists($pdo, 'osoba_citlive_udaje'));
        self::assertTrue($this->tableExists($pdo, 'osoba_citlive_pristupy'));
        self::assertTrue($this->columnExists($pdo, 'account_person_claim_requests', 'request_kind'));
        self::assertTrue($this->columnExists($pdo, 'account_person_claim_requests', 'contract_version'));
        self::assertTrue($this->columnExists($pdo, 'club_event_notifications', 'order_id'));
        self::assertTrue($this->columnExists($pdo, 'club_event_term_versions', 'scope_type'));
        self::assertTrue($this->columnExists($pdo, 'club_event_term_versions', 'scope_key'));
        self::assertTrue($this->columnExists($pdo, 'club_event_term_versions', 'consent_purpose'));
        self::assertSame(
            4,
            (int)$pdo->query("SELECT COUNT(*) FROM club_event_term_versions WHERE scope_type='athlete_registration'")->fetchColumn()
        );
        self::assertTrue($this->tableExists($pdo, 'password_reset_tokens'));
        self::assertTrue($this->tableExists($pdo, 'family_calendar_feeds'));
        self::assertTrue($this->tableExists($pdo, 'family_calendar_feed_events'));
        self::assertTrue($this->tableExists($pdo, 'member_charge_reminder_preferences'));
        self::assertTrue($this->tableExists($pdo, 'member_charge_reminders'));
        self::assertTrue($this->tableExists($pdo, 'member_charge_reminder_events'));
        self::assertTrue($this->columnExists($pdo, 'member_charge_reminder_events', 'actor_type'));
        self::assertTrue($this->columnExists($pdo, 'member_charge_reminder_events', 'actor_id'));
        self::assertTrue($this->tableExists($pdo, 'family_weekly_summary_preferences'));
        self::assertTrue($this->tableExists($pdo, 'family_weekly_summaries'));
        self::assertTrue($this->tableExists($pdo, 'family_weekly_summary_events'));
        self::assertTrue($this->columnExists($pdo, 'mereni_zaznamy', 'contract_version'));
        self::assertTrue($this->columnExists($pdo, 'mereni_zaznamy', 'distance_unit'));
        self::assertTrue($this->columnExists($pdo, 'mereni_zaznamy', 'distance_meters'));
        self::assertTrue($this->columnExists($pdo, 'mereni_zaznamy', 'duration_ms'));
        self::assertTrue($this->columnExists($pdo, 'mereni_zaznamy', 'rpe_value'));
        self::assertTrue($this->columnExists($pdo, 'zavod_sportovec', 'result_contract_version'));
        self::assertTrue($this->columnExists($pdo, 'zavod_sportovec', 'result_status'));
        self::assertTrue($this->columnExists($pdo, 'zavod_sportovec', 'result_time_ms'));
        self::assertSame('10 km', $pdo->query('SELECT vzdalenost FROM mereni_zaznamy WHERE id=1')->fetchColumn());
        self::assertSame('30 min', $pdo->query('SELECT cas FROM mereni_zaznamy WHERE id=1')->fetchColumn());
        self::assertNull($pdo->query('SELECT contract_version FROM mereni_zaznamy WHERE id=1')->fetchColumn());
        self::assertSame('01:00', $pdo->query('SELECT cas FROM zavod_sportovec WHERE zavod_id=1')->fetchColumn());
        self::assertNull($pdo->query('SELECT result_contract_version FROM zavod_sportovec WHERE zavod_id=1')->fetchColumn());
        self::assertTrue($this->tableExists($pdo, 'shop_member_category_rules'));
        self::assertTrue($this->tableExists($pdo, 'shop_member_product_prices'));
        self::assertTrue($this->tableExists($pdo, 'shop_member_price_events'));
        self::assertTrue($this->tableExists($pdo, 'kis_import_source_artifacts'));
        self::assertTrue($this->tableExists($pdo, 'public_velodrome_cart_items'));
        self::assertTrue($this->tableExists($pdo, 'public_velodrome_order_items'));
        self::assertTrue($this->columnExists($pdo, 'shop_orders', 'payment_expires_at'));
        self::assertTrue($this->columnExists($pdo, 'shop_orders', 'expired_at'));
        self::assertTrue($this->columnExists($pdo, 'shop_coupons', 'applicability_mask'));
        self::assertTrue($this->columnExists($pdo, 'shop_coupon_redemptions', 'eligible_subtotal_minor'));
        self::assertTrue($this->columnExists($pdo, 'shop_coupon_redemptions', 'applicability_mask_snapshot'));
        self::assertTrue($this->columnExists($pdo, 'club_program_enrollments', 'active_token'));
        self::assertTrue($this->columnExists($pdo, 'verejni_uzivatele', 'trener_id'));
        self::assertTrue($this->columnExists($pdo, 'planovane_treninky', 'je_verejny'));
        self::assertTrue(\trainer_password_is_modern_hash((string)$pdo->query('SELECT heslo FROM treneri WHERE id=1')->fetchColumn()));
        self::assertTrue($this->indexExists($pdo, 'idx_auth_login_limits_blocked'));
        self::assertTrue($this->indexExists($pdo, 'uq_verejni_uzivatele_trener'));
        self::assertTrue($this->indexExists($pdo, 'idx_planovane_treninky_verejne'));
        self::assertSame(
            one_time_token_hash(ONE_TIME_TOKEN_EMAIL_VERIFICATION, str_repeat('a', 64)),
            $pdo->query('SELECT verifikacni_token FROM verejni_uzivatele WHERE id = 1')->fetchColumn()
        );
        self::assertSame(
            one_time_token_hash(ONE_TIME_TOKEN_BOOKING_APPROVAL, str_repeat('b', 48)),
            $pdo->query('SELECT potvrzovaci_token FROM verejne_rezervace WHERE id = 1')->fetchColumn()
        );
        self::assertNotFalse(
            $pdo->query('SELECT verifikacni_token_expires_at FROM verejni_uzivatele WHERE id = 1')->fetchColumn()
        );
        self::assertNotFalse(
            $pdo->query('SELECT potvrzovaci_token_expires_at FROM verejne_rezervace WHERE id = 1')->fetchColumn()
        );
        self::assertSame(
            1,
            (int)$pdo->query(
                "SELECT COUNT(*) FROM evidence_schema_migrations "
                . "WHERE id = '20260802120000_auth_revocation_rate_limit'"
            )->fetchColumn()
        );
    }

    private function baselineDatabase(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('CREATE TABLE nastaveni (klic TEXT PRIMARY KEY, hodnota TEXT NOT NULL)');
        $statement = $pdo->prepare('INSERT INTO nastaveni (klic, hodnota) VALUES (:key, :value)');
        $statement->execute(['key' => 'schema_version', 'value' => \LEGACY_SCHEMA_VERSION]);

        $pdo->exec('CREATE TABLE treneri (id INTEGER PRIMARY KEY, jmeno TEXT, email TEXT UNIQUE, heslo TEXT, role TEXT, aktivni INTEGER NOT NULL DEFAULT 1)');
        $pdo->exec("INSERT INTO treneri (id,jmeno,email,heslo,role,aktivni) VALUES (1,'Admin','admin@example.test','x','admin',1)");
        $pdo->exec('CREATE TABLE sportovci (id INTEGER PRIMARY KEY)');
        $pdo->exec('CREATE TABLE sportovist (id INTEGER PRIMARY KEY)');
        $pdo->exec('CREATE TABLE individualni_lekce (id INTEGER PRIMARY KEY)');
        $pdo->exec('CREATE TABLE mereni_zaznamy (id INTEGER PRIMARY KEY, vzdalenost TEXT, cas TEXT, rpe TEXT)');
        $pdo->exec("INSERT INTO mereni_zaznamy VALUES (1, '10 km', '30 min', 'lehké')");
        $pdo->exec('CREATE TABLE zavod_sportovec (zavod_id INTEGER, sportovec_id INTEGER, cas TEXT)');
        $pdo->exec("INSERT INTO zavod_sportovec VALUES (1, 1, '01:00')");
        $pdo->exec("CREATE TABLE planovane_treninky (id INTEGER PRIMARY KEY, datum TEXT NOT NULL, stav TEXT NOT NULL DEFAULT 'planovany')");
        $pdo->exec(
            'CREATE TABLE verejni_uzivatele ('
            . 'id INTEGER PRIMARY KEY, aktivni INTEGER NOT NULL DEFAULT 1, '
            . 'verifikacni_token TEXT NULL, registrovan TEXT NOT NULL)'
        );
        $pdo->exec(
            "INSERT INTO verejni_uzivatele (id, aktivni, verifikacni_token, registrovan) "
            . "VALUES (1, 1, '" . str_repeat('a', 64) . "', '2026-08-02 08:00:00')"
        );
        $pdo->exec(
            'CREATE TABLE verejne_rezervace ('
            . 'id INTEGER PRIMARY KEY, lekce_id INTEGER NULL, stav TEXT NOT NULL DEFAULT \'ceka\', '
            . 'slot_cas_od TEXT NULL, potvrzovaci_token TEXT NULL, cas_rezervace TEXT NOT NULL)'
        );
        $pdo->exec(
            "INSERT INTO verejne_rezervace (id, potvrzovaci_token, cas_rezervace) "
            . "VALUES (1, '" . str_repeat('b', 48) . "', '2026-08-02 08:00:00')"
        );

        return $pdo;
    }

    private function sessionVersion(PDO $pdo, string $table, int $id): int
    {
        return (int)$pdo->query(
            'SELECT session_version FROM ' . $table . ' WHERE id = ' . $id
        )->fetchColumn();
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        $statement = $pdo->prepare(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = :name"
        );
        $statement->execute(['name' => $table]);
        return (bool)$statement->fetchColumn();
    }

    private function columnExists(PDO $pdo,string $table,string $column):bool
    {
        foreach($pdo->query('PRAGMA table_info('.$table.')')->fetchAll(PDO::FETCH_ASSOC)as$definition){
            if(($definition['name']??null)===$column)return true;
        }
        return false;
    }

    private function indexExists(PDO $pdo, string $index): bool
    {
        $statement = $pdo->prepare(
            "SELECT 1 FROM sqlite_master WHERE type = 'index' AND name = :name"
        );
        $statement->execute(['name' => $index]);
        return (bool)$statement->fetchColumn();
    }
}
