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

    public function testIdentityRemapRetainsEvidenceOnPreviousPerson(): void
    {
        $pdo=$this->database(false);
        $pdo->exec("INSERT INTO sportovci VALUES(1,'Eva','Původní',NULL,'','','aktivni'),(2,'Eva','Opravená',NULL,'','','aktivni')");
        $pdo->exec("INSERT INTO treneri VALUES(7,'Registrář','registrar@example.invalid')");
        $pdo->exec('CREATE TABLE person_identity_reviews(id INTEGER PRIMARY KEY,sportovec_id INTEGER)');
        $pdo->exec('CREATE TABLE person_identity_review_events(id INTEGER PRIMARY KEY,review_id INTEGER,actor_trainer_id INTEGER,before_json TEXT,after_json TEXT,created_at TEXT)');
        $pdo->exec('INSERT INTO person_identity_reviews VALUES(1,2)');
        $st=$pdo->prepare('INSERT INTO person_identity_review_events VALUES(?,?,?,?,?,?)');
        $st->execute([1,1,7,'{}','{"sportovec_id":1,"reason":"Původní doložení"}','2026-10-22 10:00:00']);
        $st->execute([2,1,7,'{"sportovec_id":1}','{"sportovec_id":2,"reason":"Oprava po ověření originálu"}','2026-10-22 11:00:00']);
        $old=personAuditTimeline($pdo,1);$new=personAuditTimeline($pdo,2);
        self::assertSame([2,1],array_column($old['events'],'source_id'));
        self::assertSame([2,1],array_column($new['events'],'source_id'));
        self::assertSame('Oprava po ověření originálu',$old['events'][0]['reason']);
    }

    public function testRegistrarHistoryIsVisibleOnlyForItsPersonWithoutContactPayloads(): void
    {
        $pdo = $this->database(false);
        $pdo->exec("INSERT INTO sportovci VALUES(1,'LOCALHOST','Jedna',NULL,'','','aktivni'),(2,'LOCALHOST','Druhá',NULL,'','','aktivni')");
        $pdo->exec("INSERT INTO treneri VALUES(7,'LOCALHOST Registrář','registrar@example.invalid')");
        $pdo->exec('CREATE TABLE sportovec_history(id INTEGER PRIMARY KEY,sportovec_id INTEGER,created_by INTEGER,event_type TEXT,detail TEXT,old_json TEXT,new_json TEXT,created_at TEXT)');
        $stmt = $pdo->prepare('INSERT INTO sportovec_history VALUES(?,?,?,?,?,?,?,?)');
        $stmt->execute([1,1,7,'profile_edit',null,'{}','{"telefon":"PRIVATE_CONTACT_SENTINEL"}','2026-10-23 10:00:00']);
        $stmt->execute([2,1,7,'status_manual',null,'{"stav_clenstvi":"cekajici"}','{"stav_clenstvi":"aktivni","stav_duvod":"LOCALHOST doložené rozhodnutí"}','2026-10-23 11:00:00']);
        $stmt->execute([3,2,7,'note','FOREIGN_NOTE_SENTINEL','{}','{}','2026-10-23 12:00:00']);
        $timeline = personAuditTimeline($pdo, 1);
        self::assertSame([2,1], array_column($timeline['events'], 'source_id'));
        self::assertSame(['person_history','person_history'], array_column($timeline['events'], 'source'));
        self::assertSame('cekajici', $timeline['events'][0]['from_status']);
        self::assertSame('aktivni', $timeline['events'][0]['to_status']);
        self::assertSame('LOCALHOST doložené rozhodnutí', $timeline['events'][0]['reason']);
        self::assertSame('LOCALHOST Registrář', $timeline['events'][0]['actor_label']);
        self::assertSame('sportovec_karta.php?sportovec_id=1&tab=historie#historie', $timeline['events'][0]['source_link']);
        self::assertStringNotContainsString('PRIVATE_CONTACT_SENTINEL', json_encode($timeline));
        self::assertStringNotContainsString('FOREIGN_NOTE_SENTINEL', json_encode($timeline));
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
