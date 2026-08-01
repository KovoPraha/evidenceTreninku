<?php
declare(strict_types=1);

namespace Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/kis_parity_contract.php';

final class KisParityContractTest extends TestCase
{
    public function testValidFixtureIsDeterministicAndMissingCountIsInformational(): void
    {
        $input = $this->fixture('parity-valid.json');
        $report = \kisParityContractEvaluate($input);

        self::assertSame('valid', $report['status']);
        self::assertSame(2, $report['summary']['total_rows']);
        self::assertSame(2, $report['summary']['classified_rows']);
        self::assertSame(0, $report['summary']['blocker_rows']);
        self::assertSame(1, $report['summary']['counts']['matched_same']);
        self::assertSame(1, $report['summary']['counts']['ignored']);
        self::assertSame(
            ['kis:row-001', 'kis:row-002'],
            array_column($report['rows'], 'source_ref')
        );
        self::assertSame([
            'count' => 2,
            'informational_only' => true,
            'archive_action' => 'never',
        ], $report['summary']['missing_in_run']);

        self::assertSame(
            json_encode($report, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            json_encode(\kisParityContractEvaluate($input), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
        );
    }

    public function testBlockerFixtureClassifiesEachRowExactlyOnce(): void
    {
        $report = \kisParityContractEvaluate($this->fixture('parity-invalid.json'));

        self::assertSame('blocked', $report['status']);
        self::assertSame(4, $report['summary']['total_rows']);
        self::assertSame(4, $report['summary']['classified_rows']);
        self::assertSame(4, $report['summary']['blocker_rows']);
        self::assertSame(2, $report['summary']['counts']['conflict']);
        self::assertSame(1, $report['summary']['counts']['new']);
        self::assertSame(1, $report['summary']['counts']['unexplained']);
        self::assertCount(4, array_unique(array_column($report['rows'], 'source_ref')));

        $duplicateRows = array_values(array_filter(
            $report['rows'],
            static fn(array $row): bool => $row['reason'] === 'duplicate_target'
        ));
        self::assertCount(2, $duplicateRows);
        self::assertSame(['conflict', 'conflict'], array_column($duplicateRows, 'category'));
        self::assertSame(
            ['evidence:member-009', 'evidence:member-009'],
            array_column($duplicateRows, 'target_ref')
        );
    }

    public function testUnknownOrPiiFieldsAreRejectedWithoutEchoingTheirValue(): void
    {
        $input = $this->fixture('parity-valid.json');
        $input['rows'][0]['email'] = 'secret@example.invalid';

        try {
            \kisParityContractEvaluate($input);
            self::fail('Unknown fields must be rejected.');
        } catch (InvalidArgumentException $e) {
            self::assertSame('root.rows[0] contains an unsupported field', $e->getMessage());
            self::assertStringNotContainsString('secret@example.invalid', $e->getMessage());
        }
    }

    public function testReferencesAndCanonicalReasonsAreStrict(): void
    {
        $withEmailRef = $this->fixture('parity-valid.json');
        $withEmailRef['rows'][0]['source_ref'] = 'secret@example.invalid';
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be an opaque non-PII reference');
        \kisParityContractEvaluate($withEmailRef);
    }

    public function testReasonMustMatchCategory(): void
    {
        $input = $this->fixture('parity-valid.json');
        $input['rows'][0]['reason'] = 'signals_equal';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('reason does not match its category');
        \kisParityContractEvaluate($input);
    }

    /** @return array<string, mixed> */
    private function fixture(string $name): array
    {
        $json = file_get_contents(dirname(__DIR__) . '/fixtures/kis/' . $name);
        self::assertNotFalse($json);
        $decoded = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        return $decoded;
    }
}
