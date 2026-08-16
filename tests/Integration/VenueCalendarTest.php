<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/venue_calendar.php';

final class VenueCalendarTest extends TestCase
{
    public function testPlannedAndRecordedUnreservedPlansAreReturnedWithTrainingLink(): void
    {
        $pdo = $this->database();
        $this->insertPlan($pdo, 1, 'planovany', null, null, 'Plán');
        $this->insertPlan($pdo, 2, 'evidovany', null, 200, 'Evidence');
        $this->insertPlan($pdo, 3, 'zruseny', null, null, 'Zrušený');

        $plans = \venueCalendarUnreservedPlans($pdo, '2026-08-16', '2026-08-16');

        self::assertSame([1, 2], array_column($plans, 'id'));
        self::assertSame('evidovany', $plans[1]['stav']);
        self::assertSame(200, (int)$plans[1]['trenink_id']);
    }

    public function testPlanToEvidenceAndReservationInOneSubmissionRendersExactlyOnce(): void
    {
        $pdo = $this->database();
        $this->insertPlan($pdo, 10, 'planovany', null, null, 'Jeden blok');

        $pdo->beginTransaction();
        $reservationId = \venueCalendarCreateTrainingReservation(
            $pdo, 1, 7, '2026-08-16', '16:00', '17:30', 2, 500, 10
        );
        $pdo->prepare("UPDATE planovane_treninky SET trenink_id=?,stav='evidovany' WHERE id=?")
            ->execute([500, 10]);
        $pdo->commit();

        self::assertSame($reservationId, (int)$pdo->query(
            'SELECT rezervace_id FROM planovane_treninky WHERE id=10'
        )->fetchColumn());
        $reservationCount = (int)$pdo->query(
            "SELECT COUNT(*) FROM rezervace_sportovist WHERE datum='2026-08-16' AND sportoviste_id=1"
        )->fetchColumn();
        $planCount = count(\venueCalendarUnreservedPlans($pdo, '2026-08-16', '2026-08-16', 1));
        self::assertSame(1, $reservationCount + $planCount, 'Linked reservation and recorded plan must render once.');
    }

    public function testHistoricalTrainingLinkDeduplicatesPlanWithoutReservationId(): void
    {
        $pdo = $this->database();
        $this->insertPlan($pdo, 20, 'evidovany', null, 600, 'Historický plán');
        $pdo->exec(
            "INSERT INTO rezervace_sportovist(sportoviste_id,trener_id,datum,cas_od,cas_do,kapacita_dilu,trenink_id,lekce_id) "
            . "VALUES(1,7,'2026-08-16','16:00','17:30',1,600,NULL)"
        );

        self::assertSame([], \venueCalendarUnreservedPlans($pdo, '2026-08-16', '2026-08-16', 1));
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM rezervace_sportovist')->fetchColumn());
    }

    public function testCapacityFailureIsExplicitAndCreatesNoReservationOrLink(): void
    {
        $pdo = $this->database();
        $this->insertPlan($pdo, 30, 'planovany', null, null, 'Plná kapacita');
        $pdo->exec(
            "INSERT INTO rezervace_sportovist(sportoviste_id,trener_id,datum,cas_od,cas_do,kapacita_dilu,trenink_id,lekce_id) "
            . "VALUES(1,7,'2026-08-16','16:00','17:30',5,700,NULL)"
        );

        $pdo->beginTransaction();
        try {
            \venueCalendarCreateTrainingReservation($pdo, 1, 7, '2026-08-16', '16:30', '17:00', 1, 701, 30);
            self::fail('Full venue capacity must reject the reservation.');
        } catch (\VenueCalendarException $exception) {
            self::assertStringContainsString('obsazeno 5 z 5 dílů', $exception->getMessage());
            $pdo->rollBack();
        }
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM rezervace_sportovist')->fetchColumn());
        self::assertNull($pdo->query('SELECT rezervace_id FROM planovane_treninky WHERE id=30')->fetchColumn());
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT)');
        $pdo->exec('CREATE TABLE skupiny(id INTEGER PRIMARY KEY,nazev TEXT)');
        $pdo->exec('CREATE TABLE sportovist(id INTEGER PRIMARY KEY,nazev TEXT,max_kapacita INTEGER,aktivni INTEGER)');
        $pdo->exec('CREATE TABLE planovane_treninky(id INTEGER PRIMARY KEY,trener_id INTEGER,skupina_id INTEGER,datum TEXT,cas_od TEXT,cas_do TEXT,sportoviste_id INTEGER,rezervace_id INTEGER,trenink_id INTEGER,nazev TEXT,stav TEXT)');
        $pdo->exec('CREATE TABLE rezervace_sportovist(id INTEGER PRIMARY KEY AUTOINCREMENT,sportoviste_id INTEGER,trener_id INTEGER,datum TEXT,cas_od TEXT,cas_do TEXT,kapacita_dilu INTEGER,trenink_id INTEGER,lekce_id INTEGER)');
        $pdo->exec("INSERT INTO treneri VALUES(7,'Trenér'); INSERT INTO skupiny VALUES(1,'U15'); INSERT INTO sportovist VALUES(1,'Velodrom',5,1)");
        return $pdo;
    }

    private function insertPlan(PDO $pdo, int $id, string $state, ?int $reservationId, ?int $trainingId, string $name): void
    {
        $statement = $pdo->prepare(
            'INSERT INTO planovane_treninky VALUES(?,7,1,\'2026-08-16\',\'16:00\',\'17:30\',1,?,?,?,?)'
        );
        $statement->execute([$id, $reservationId, $trainingId, $name, $state]);
    }
}
