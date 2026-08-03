<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/person_audit_timeline.php';

final class PersonAuditTimelineTest extends TestCase
{
    public function testTimelineIsPersonScopedSortedAndResolvesActorsWithoutNPlusOne(): void
    {
        $pdo = $this->database();
        $pdo->exec("INSERT INTO sportovci VALUES (1,'Eva','Jedna','2014-01-01','e@test','123','aktivni'),(2,'Cizi','Dite','2013-01-01','','','aktivni')");
        $pdo->exec("INSERT INTO treneri VALUES (7,'Admin','admin@test')");
        $pdo->exec("INSERT INTO verejni_uzivatele VALUES (10,'Rodic','Jedna')");
        $pdo->exec("INSERT INTO account_person_roles VALUES (20,10,1),(21,10,2)");
        $pdo->exec("INSERT INTO account_person_role_events VALUES (100,20,7,'approve',NULL,'approved','overeno','2026-01-01 10:00:00'),(101,21,7,'approve',NULL,'approved','cizi','2026-01-01 11:00:00')");
        $pdo->exec("INSERT INTO public_profile_events VALUES (200,10,1,'update','{\"note\":\"upraven telefon\"}','2026-01-02 10:00:00'),(201,10,2,'update','{}','2026-01-03 10:00:00')");

        $timeline = personAuditTimeline($pdo, 1, 1, 10);
        self::assertSame([200, 100], array_column($timeline['events'], 'source_id'));
        self::assertSame(['public_profile', 'identity_relation'], array_column($timeline['events'], 'source'));
        self::assertSame('Rodic Jedna', $timeline['events'][0]['actor_label']);
        self::assertSame('Admin', $timeline['events'][1]['actor_label']);
        self::assertSame('upraven telefon', $timeline['events'][0]['reason']);
        self::assertFalse($timeline['has_next']);
    }

    public function testPaginationAndMissingOptionalSourcesAreSafe(): void
    {
        $pdo = $this->database(false);
        $pdo->exec("INSERT INTO sportovci VALUES (1,'Eva','Jedna','2014-01-01','','','aktivni')");
        for ($id = 1; $id <= 12; $id++) {
            $pdo->prepare('INSERT INTO public_profile_events VALUES (?,?,?,?,?,?)')->execute([
                $id, 999, 1, 'update', '{broken-json', sprintf('2026-01-%02d 10:00:00', $id),
            ]);
        }
        $first = personAuditTimeline($pdo, 1, 1, 10);
        $second = personAuditTimeline($pdo, 1, 2, 10);
        self::assertCount(10, $first['events']);
        self::assertTrue($first['has_next']);
        self::assertCount(2, $second['events']);
        self::assertTrue($second['has_previous']);
        self::assertNull($first['events'][0]['reason']);
    }

    public function testSearchEscapesWildcardsAndIdLookupIsExact(): void
    {
        $pdo = $this->database(false);
        $pdo->exec("INSERT INTO sportovci VALUES (1,'Eva','Sto%','2014-01-01','','','aktivni'),(12,'Eva','Jina','2014-01-01','','','aktivni')");
        self::assertSame([1], array_column(personAuditSearch($pdo, 'Sto%'), 'id'));
        self::assertSame([1], array_column(personAuditSearch($pdo, '1'), 'id'));
    }

    private function database(bool $withIdentity = true): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE sportovci(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT,narozeni TEXT,email TEXT,telefon TEXT,stav_clenstvi TEXT)');
        $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT,email TEXT)');
        $pdo->exec('CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT)');
        $pdo->exec('CREATE TABLE public_profile_events(id INTEGER PRIMARY KEY,account_id INTEGER,sportovec_id INTEGER,action TEXT,payload_json TEXT,created_at TEXT)');
        if ($withIdentity) {
            $pdo->exec('CREATE TABLE account_person_roles(id INTEGER PRIMARY KEY,account_id INTEGER,sportovec_id INTEGER)');
            $pdo->exec('CREATE TABLE account_person_role_events(id INTEGER PRIMARY KEY,relation_id INTEGER,actor_trainer_id INTEGER,action TEXT,from_status TEXT,to_status TEXT,note TEXT,created_at TEXT)');
        }
        return $pdo;
    }
}
