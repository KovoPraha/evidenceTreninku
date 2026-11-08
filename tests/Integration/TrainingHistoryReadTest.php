<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\QueryProfilePdo;
use Tests\Support\TrainingHistoryFixture as Fixture;
use Tests\Support\TrainingHistoryBaseline as Baseline;

require_once dirname(__DIR__) . '/Support/QueryProfilePdo.php';
require_once dirname(__DIR__) . '/Support/RosterReadDatabase.php';
require_once dirname(__DIR__) . '/Support/TrainingHistoryFixture.php';
require_once dirname(__DIR__) . '/Support/TrainingHistoryBaseline.php';
require_once dirname(__DIR__, 2) . '/includes/training_history_read.php';

final class TrainingHistoryReadTest extends TestCase
{
    use \Tests\Support\RosterReadDatabase;

    private function seeded(int $size = 151): QueryProfilePdo
    {
        $pdo = $this->database();
        Fixture::seed($pdo, $size);
        return $pdo;
    }

    public function testAllPagesReconstructBothOriginalProjectionsWithEveryFilter(): void
    {
        $pdo = $this->seeded();
        foreach ([false, true] as $rich) {
            foreach (['1=1' => [], 'tg.skupina_id=? AND tp.podskupina_id=?' => [2, 1],
                'tt.trener_id IN (?)' => [2], 't.kategorie=?' => ['mtb'],
                '(t.napln LIKE ? OR t.poznamka LIKE ?)' => ['%session 12%', '%session 12%'],
                't.id<0' => []] as $where => $params) {
                foreach ([0, 1, 2, 3, 99] as $team) {
                    $sql = Fixture::sql($pdo, $rich, $where);
                    $old = Baseline::read($pdo, $sql, $params, $team);
                    $all = [];
                    $last = \TrainingHistoryRead::page($pdo, $sql, $params, $team, 1000000);
                    self::assertSame(count($old['rows']), $last['total']);
                    self::assertSame($last['pages'], $last['page']);
                    for ($page = 1; $page <= $last['pages']; $page++) {
                        $new = \TrainingHistoryRead::page($pdo, $sql, $params, $team, $page);
                        $expected = array_slice($old['rows'], ($page - 1) * 25, 25);
                        self::assertSame($expected, $new['rows']);
                        self::assertLessThanOrEqual(25, count($new['rows']));
                        $ids = array_column($new['rows'], 'id');
                        $labels = \trainingRosterBridgeTrainingLabels($pdo, $new['rows']);
                        foreach ($ids as $id) {
                            self::assertSame($old['labels'][$id] ?? null, $labels[$id] ?? null);
                        }
                        self::assertSame(Fixture::participantGroups(array_values(array_filter($old['participants'], static fn (array $p): bool => in_array($p['trenink_id'], $ids)))), Fixture::participantGroups(Fixture::participants($pdo, $ids)));
                        array_push($all, ...$new['rows']);
                        if ($rich) {
                            self::assertSame(array_sum(array_column($old['rows'], 'delka')), $new['hours']);
                            $categories = [];
                            foreach ($old['rows'] as $r) {
                                $categories[$r['kategorie']] = ($categories[$r['kategorie']] ?? 0) + 1;
                            }
                            self::assertSame($categories, $new['categories']);
                        }
                    }
                    self::assertSame($old['rows'], $all);
                    self::assertCount(count(array_unique(array_column($all, 'id'))), $all);
                }
            }
        }
    }

    public function testOnlyPageDetailsAreFetchedAndChangesRemainFresh(): void
    {
        $pdo = $this->seeded(1200);
        $sql = Fixture::sql($pdo, true);
        $pdo->profiling = true;
        $pdo->profile = [];
        $page = \TrainingHistoryRead::page($pdo, $sql, [], 0, 1);
        self::assertSame([1200, 25], array_column($pdo->profile, 'fetched_rows'));
        self::assertLessThanOrEqual(25, max(array_map(static fn (array $q): int => count($q['parameters']), $pdo->profile)));
        $pdo->profiling = false;
        $id = (int) $page['rows'][0]['id'];
        $pdo->exec("UPDATE treninky SET poznamka='Changed' WHERE id=" . $id);
        self::assertSame('Changed', \TrainingHistoryRead::page($pdo, $sql, [], 0, 1)['rows'][0]['poznamka']);
        $pdo->exec('DELETE FROM trenink_trener WHERE trenink_id=' . $id);
        self::assertSame(1199, \TrainingHistoryRead::page($pdo, $sql, [], 0, 1)['total']);
    }

    public function testPaginationRetainsFiltersAndEscapesLinks(): void
    {
        self::assertSame(1, \TrainingHistoryRead::requested(['history_page' => []]));
        self::assertSame(1000000, \TrainingHistoryRead::requested(['history_page' => '99999999999999999']));
        ob_start();
        \TrainingHistoryRead::navigation(['page' => 2,'pages' => 5,'total' => 101], ['q' => '<tag>','trenere' => [1,2],'team_id' => 3]);
        $html = ob_get_clean();
        self::assertStringNotContainsString('<tag>', $html);
        self::assertStringContainsString('history_page=3', $html);
        self::assertStringContainsString('team_id=3', $html);
        self::assertStringContainsString('trenere%5B1%5D=2', $html);
        self::assertStringContainsString('101', $html);
    }
}
