<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/person_match.php';

final class PersonMatchV1Test extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->exec(
            'CREATE TABLE sportovci('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT,jmeno TEXT,prijmeni TEXT,narozeni TEXT NULL,'
            . 'kis_external_id TEXT NULL,hash TEXT,email TEXT,uci INTEGER DEFAULT 0,'
            . 'stav_clenstvi TEXT,stav_manualni INTEGER DEFAULT 0)'
        );
        $this->pdo->exec(
            'CREATE TABLE ucto_audit_log('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT,uzivatel_id INTEGER,akce TEXT,tabulka TEXT,'
            . 'zaznam_id INTEGER,detail TEXT,ip_adresa TEXT,user_agent TEXT)'
        );
    }

    /** @dataProvider exactProvider */
    public function testT1T2T3AndT9ExactNormalization(
        string $storedFirst,
        string $storedLast,
        string $inputFirst,
        string $inputLast
    ): void {
        $this->insert(1, $storedFirst, $storedLast, '2012-03-01');
        $result = personMatchV1($this->pdo, [
            'jmeno' => $inputFirst,
            'prijmeni' => $inputLast,
            'narozeni' => '2012-03-01',
        ]);
        self::assertSame(PERSON_MATCH_EXACT, $result['level']);
        self::assertSame([1], array_column($result['candidates'], 'id'));
    }

    public static function exactProvider(): array
    {
        return [
            'T1 exact' => ['Jan', 'Novák', 'Jan', 'Novák'],
            'T2 spaces and case' => ['Jan', 'Novák', '  jan ', '  NOVAK  '],
            'T3 diacritics' => ['Tereza', 'Nováková', 'Tereza', 'Novakova'],
            'T9 hyphen and diacritics' => ['Marie-Anna', 'Kučerová', 'Marie Anna', 'Kucerova'],
        ];
    }

    public function testT4NicknameIsP1Similarity(): void
    {
        $this->insert(1, 'Jan', 'Novák', '2012-03-01');
        $result = personMatchV1($this->pdo, ['jmeno'=>'Honza','prijmeni'=>'Novák','narozeni'=>'2012-03-01']);
        self::assertSame(PERSON_MATCH_SIMILARITY, $result['level']);
        self::assertContains('P1', $result['candidates'][0]['rules']);
    }

    public function testT5MissingExistingBirthDateIsP2Similarity(): void
    {
        $this->insert(1, 'Petr', 'Svoboda', null);
        $result = personMatchV1($this->pdo, ['jmeno'=>'Petr','prijmeni'=>'Svoboda','narozeni'=>'2010-07-12']);
        self::assertSame(PERSON_MATCH_SIMILARITY, $result['level']);
        self::assertContains('P2', $result['candidates'][0]['rules']);
    }

    public function testT6SwappedDayAndMonthIsP3Similarity(): void
    {
        $this->insert(1, 'Eva', 'Dvořáková', '2013-04-05');
        $result = personMatchV1($this->pdo, ['jmeno'=>'Eva','prijmeni'=>'Dvořáková','narozeni'=>'2013-05-04']);
        self::assertSame(PERSON_MATCH_SIMILARITY, $result['level']);
        self::assertContains('P3', $result['candidates'][0]['rules']);
    }

    public function testT7SameYearIsP4Similarity(): void
    {
        $this->insert(1, 'Eva', 'Dvořáková', '2013-04-05');
        $result = personMatchV1($this->pdo, ['jmeno'=>'Eva','prijmeni'=>'Dvořáková','narozeni'=>'2013-11-20']);
        self::assertSame(PERSON_MATCH_SIMILARITY, $result['level']);
        self::assertSame(['P4'], $result['candidates'][0]['rules']);
    }

    public function testT8DifferentYearHasNoMatch(): void
    {
        $this->insert(1, 'Jan', 'Novák', '2012-03-01');
        $result = personMatchV1($this->pdo, ['jmeno'=>'Jan','prijmeni'=>'Novák','narozeni'=>'2009-03-01']);
        self::assertSame(PERSON_MATCH_NONE, $result['level']);
        self::assertSame([], $result['candidates']);
    }

    public function testT10AllExactCandidatesAreReturned(): void
    {
        $this->insert(1, 'Jan', 'Novák', '2012-03-01');
        $this->insert(2, 'Jan', 'Novák', '2012-03-01');
        $result = personMatchV1($this->pdo, ['jmeno'=>'Jan','prijmeni'=>'Novák','narozeni'=>'2012-03-01']);
        self::assertSame(2, $result['exact_count']);
        self::assertSame([1, 2], array_column($result['candidates'], 'id'));
    }

    public function testT11OverrideAuditContainsActorCandidateReasonAndCreatedPerson(): void
    {
        $this->insert(10, 'Jan', 'Novák', '2012-03-01');
        $result = personMatchV1($this->pdo, ['jmeno'=>'Jan','prijmeni'=>'Novák','narozeni'=>'2012-03-01']);
        personMatchV1Audit($this->pdo, 7, 'override_create', $result, 11, 'Jsou to dvojčata.', ['source'=>'test']);
        $row = $this->pdo->query('SELECT * FROM ucto_audit_log')->fetch();
        $detail = json_decode((string)$row['detail'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(7, $detail['actor_trainer_id']);
        self::assertSame([10], $detail['candidate_ids']);
        self::assertSame(11, $detail['created_person_id']);
        self::assertSame('Jsou to dvojčata.', $detail['reason']);
    }

    public function testManualPersonHasWaitingManualStateAndNoKisExternalId(): void
    {
        $id = personMatchV1CreateManual($this->pdo, [
            'jmeno'=>'Nová','prijmeni'=>'Osoba','narozeni'=>'2014-02-03','email'=>'',
        ]);
        $row = $this->pdo->query('SELECT * FROM sportovci WHERE id=' . $id)->fetch();
        self::assertSame('cekajici', $row['stav_clenstvi']);
        self::assertSame(1, (int)$row['stav_manualni']);
        self::assertNull($row['kis_external_id']);
        self::assertNotSame('', $row['hash']);
    }

    private function insert(int $id, string $first, string $last, ?string $birth): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO sportovci(id,jmeno,prijmeni,narozeni,kis_external_id,hash,email) VALUES(?,?,?,?,NULL,?,?)'
        );
        $statement->execute([$id, $first, $last, $birth, 'hash-' . $id, '']);
    }
}
