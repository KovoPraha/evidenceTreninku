<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

if (!defined('EVIDENCE_BACKUP_LIBRARY_ONLY')) define('EVIDENCE_BACKUP_LIBRARY_ONLY', true);
require_once dirname(__DIR__, 2) . '/bin/db-backup.php';

final class DatabaseBackupOwnershipContractTest extends TestCase
{
    public function testContractRegistersWriteContractChangesOnly(): void
    {
        $contract = EVIDENCE_OWNED_COLUMN_CONTRACT;

        // R7: životní cyklus verzí podmínek a neměnný důkaz souhlasu.
        self::assertSame(['status', 'archived_at', 'archived_by_trainer_id', 'title_plain', 'custom_required', 'custom_visible'], $contract['club_event_term_versions']);
        self::assertSame(['title_plain', 'custom_consent_id', 'custom_required', 'custom_visible'], $contract['athlete_registration_text_drafts']);
        self::assertContains('club_registration_consents', EVIDENCE_TABLES);
        self::assertContains('club_registration_consent_acceptances', EVIDENCE_TABLES);
        self::assertSame(
            ['account_id', 'terms_snapshot_json', 'terms_accepted_at', 'terms_accepted_by_account_id'],
            $contract['club_program_enrollments']
        );
        self::assertSame(['payer_account_id','payer_name_snapshot','payer_relation_snapshot'],$contract['club_program_admissions']);
        self::assertSame(
            ['program_terms_snapshot_json', 'program_terms_accepted_at', 'program_terms_accepted_by_account_id'],
            $contract['shop_order_items']
        );
        // R1: řádek katalogu už nemusí pocházet z importu.
        self::assertContains('origin', $contract['shop_products']);
        self::assertContains('origin', $contract['shop_variants']);
        self::assertSame(['archived_at'], $contract['shop_coupons']);
        self::assertSame(
            ['activity_kind', 'planning_status', 'visibility', 'participant_fee_minor', 'legacy_race_id'],
            $contract['club_events']
        );

        // R6 je volitelný obchodní atribut, ne změna zápisového kontraktu.
        self::assertArrayNotHasKey('club_program_offers', $contract);
        foreach ($contract as $table => $columns) {
            self::assertNotContains('birth_year_from', $columns, $table);
            self::assertNotContains('birth_year_to', $columns, $table);
        }

        foreach (array_keys($contract) as $table) {
            self::assertContains($table, EVIDENCE_TABLES, $table . ' musí být vlastněná tabulka.');
        }
    }

    public function testEveryOwnedTableIsListedOnceAndTheNewOnesAreThere(): void
    {
        self::assertContains('club_payment_returns', EVIDENCE_TABLES);
        foreach (['club_migration_mutex','club_migration_batches','club_migration_items','club_migration_maps','club_migration_events','club_cutover_runs','club_cutover_entries','club_cutover_checkpoints','club_cutover_reviews'] as $table) {
            self::assertContains($table, EVIDENCE_TABLES);
        }
        foreach (['club_expense_cases','club_expense_claims','club_expense_advances','club_expense_consumptions','club_expense_transfers','club_expense_events','club_reward_rules','club_reward_claims','club_reward_versions','club_reward_decisions','club_reward_payouts','financial_decisions'] as $table) {
            self::assertContains($table, EVIDENCE_TABLES);
        }
        self::assertContains('athlete_registration_text_drafts', EVIDENCE_TABLES);
        self::assertContains('fio_sync_state', EVIDENCE_TABLES);
        self::assertSame(array_values(array_unique(EVIDENCE_TABLES)), array_values(EVIDENCE_TABLES));
        foreach (['shop_bank_settings', 'shop_bank_settings_events', 'shop_attribute_definitions', 'shop_attribute_choices', 'shop_attribute_definition_events', 'shop_category_meta', 'shop_category_meta_events', 'club_event_links', 'club_event_people', 'club_event_planned_participants', 'club_event_vehicle_reservations', 'club_program_physical_teams', 'club_program_recurrence_rules', 'club_team_edges', 'member_payer_variable_symbols', 'member_payer_receipts', 'member_payer_receipt_allocations', 'member_payer_receipt_events', 'sumup_webhook_events'] as $table) {
            self::assertContains($table, EVIDENCE_TABLES);
        }
    }

    /**
     * Záloha běží před migracemi vydání, které ji následuje. Manifest proto
     * hlásí zvlášť očekávání kódu a zvlášť skutečnost snímku — bez varování,
     * které by bylo při každém vydání červené.
     */
    public function testManifestReportsTheSnapshotRealityNotOnlyTheExpectation(): void
    {
        $productionState = [
            'shop_products' => [
                ['name' => 'id', 'binary' => false],
                ['name' => 'source_candidate_id', 'binary' => false],
                ['name' => 'source_run_id', 'binary' => false],
            ],
            'shop_variants' => [['name' => 'source_candidate_id', 'binary' => false]],
            'club_event_term_versions' => [['name' => 'id', 'binary' => false]],
        ];

        $present = \ownedColumnsPresentInSnapshot($productionState);

        self::assertSame(['source_candidate_id', 'source_run_id'], $present['shop_products']);
        self::assertSame(['source_candidate_id'], $present['shop_variants']);
        self::assertArrayNotHasKey('club_event_term_versions', $present, 'Tabulka bez jediného sloupce kontraktu se nehlásí.');
        self::assertArrayNotHasKey('shop_order_items', $present, 'Tabulka mimo snímek se nehlásí.');
        self::assertNotSame(EVIDENCE_OWNED_COLUMN_CONTRACT, $present);
    }

    public function testAfterTheMigrationsTheSnapshotMatchesTheContract(): void
    {
        $afterRelease = [];
        foreach (EVIDENCE_OWNED_COLUMN_CONTRACT as $table => $columns) {
            $afterRelease[$table] = array_map(
                static fn(string $column): array => ['name' => $column, 'binary' => false],
                array_merge(['id'], $columns)
            );
        }
        self::assertSame(EVIDENCE_OWNED_COLUMN_CONTRACT, \ownedColumnsPresentInSnapshot($afterRelease));
        self::assertSame([], \ownedColumnsPresentInSnapshot([]));
    }
}
