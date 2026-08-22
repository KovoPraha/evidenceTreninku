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
        self::assertSame(['status', 'archived_at', 'archived_by_trainer_id'], $contract['club_event_term_versions']);
        self::assertSame(
            ['terms_snapshot_json', 'terms_accepted_at', 'terms_accepted_by_account_id'],
            $contract['club_program_enrollments']
        );
        self::assertSame(
            ['program_terms_snapshot_json', 'program_terms_accepted_at', 'program_terms_accepted_by_account_id'],
            $contract['shop_order_items']
        );
        // R1: řádek katalogu už nemusí pocházet z importu.
        self::assertContains('origin', $contract['shop_products']);
        self::assertContains('origin', $contract['shop_variants']);
        self::assertSame(['archived_at'], $contract['shop_coupons']);

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
        self::assertSame(array_values(array_unique(EVIDENCE_TABLES)), array_values(EVIDENCE_TABLES));
        foreach (['shop_bank_settings', 'shop_bank_settings_events', 'shop_attribute_definitions', 'shop_attribute_choices', 'shop_attribute_definition_events', 'shop_category_meta', 'shop_category_meta_events'] as $table) {
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
