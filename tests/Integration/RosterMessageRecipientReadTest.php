<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\QueryProfilePdo;

require_once dirname(__DIR__, 2) . '/includes/roster_message_recipient.php';
require_once dirname(__DIR__) . '/Support/QueryProfilePdo.php';

final class RosterMessageRecipientReadTest extends TestCase
{
    public function testOneRecipientDoesNotFetchTheWholeAudience(): void
    {
        $pdo = $this->database(1000);
        $pdo->profiling = true;
        self::assertTrue(\rosterMessageRecipientAllowed($pdo, $this->row()));
        self::assertLessThanOrEqual(8, count($pdo->profile));
        self::assertLessThanOrEqual(10, array_sum(array_column($pdo->profile, 'fetched_rows')));
    }

    public function testDecisionMatchesPreviewAndSeesEverySubsequentRevocation(): void
    {
        foreach ([
            '',
            "UPDATE account_person_roles SET status='revoked' WHERE account_id=1001",
            "UPDATE account_person_roles SET relation_role='payer' WHERE account_id=1001",
            "UPDATE account_person_roles SET valid_to=CURRENT_TIMESTAMP WHERE account_id=1001",
            "UPDATE account_person_roles SET valid_from='2999-01-01' WHERE account_id=1001",
            "UPDATE verejni_uzivatele SET aktivni=0 WHERE id=1001",
            "UPDATE verejni_uzivatele SET email_overeno=0 WHERE id=1001",
            "UPDATE verejni_uzivatele SET email='OTHER@example.test' WHERE id=1001",
            "UPDATE club_roster_members SET status='cancelled' WHERE sportovec_id=1",
            "UPDATE club_roster_members SET status='removed',valid_to=NULL WHERE sportovec_id=1",
            "UPDATE club_roster_members SET status='removed',valid_to=CURRENT_DATE WHERE sportovec_id=1",
            "UPDATE club_roster_members SET valid_to='2020-01-02' WHERE sportovec_id=1",
            "UPDATE club_team_edges SET valid_to='2020-01-02'",
            "UPDATE club_teams SET status='archived' WHERE id=10",
            "UPDATE club_seasons SET status='archived' WHERE id=1",
            'UPDATE club_teams SET season_id=NULL WHERE id=10',
            "UPDATE club_teams SET status='archived' WHERE id=11",
            'DROP TABLE club_roster_members',
        ] as $mutation) {
            $pdo = $this->database(3);
            self::assertTrue(\rosterMessageRecipientAllowed($pdo, $this->row()));
            if ($mutation !== '') {
                $pdo->exec($mutation);
            }
            self::assertSame($this->decision($pdo, false), $this->decision($pdo, true), $mutation);
        }
        $pdo = $this->database(3);
        $row = $this->row();
        $row['recipient_email'] = 'PERSON1@example.test';
        self::assertFalse(\rosterMessageRecipientAllowed($pdo, $row), 'Email comparison must remain case-sensitive PHP equality.');
        $pdo->exec("INSERT INTO account_person_roles VALUES(9,1001,2,'self','approved','2020-01-01',NULL)");
        $pdo->exec("UPDATE account_person_roles SET status='revoked' WHERE sportovec_id=1");
        self::assertTrue(\rosterMessageRecipientAllowed($pdo, $this->row()), 'Another current child/person still authorizes the same recipient.');
    }

    private function decision(PDO $pdo, bool $targeted): bool|string
    {
        try {
            if ($targeted) {
                return \rosterMessageRecipientAllowed($pdo, $this->row());
            }
            $preview = \rosterMessagePreview($pdo, 10, 'Synthetic', 'Synthetic');
            foreach ($preview['recipients'] as $recipient) {
                if ($recipient['account_id'] === 1001 && $recipient['email'] === 'person1@example.test') {
                    return true;
                }
            }
            return false;
        } catch (\Throwable $exception) {
            return get_class($exception);
        }
    }

    private function row(): array
    {
        return ['team_id' => 10, 'account_id' => 1001, 'recipient_email' => 'person1@example.test',
            'subject_plain' => 'Synthetic', 'body_plain' => 'Synthetic'];
    }

    private function database(int $size): QueryProfilePdo
    {
        $pdo = new QueryProfilePdo('sqlite::memory:');
        $pdo->exec('CREATE TABLE club_seasons(id INTEGER PRIMARY KEY,name TEXT,status TEXT);'
            . 'CREATE TABLE club_teams(id INTEGER PRIMARY KEY,season_id INTEGER,name TEXT,status TEXT);'
            . 'CREATE TABLE club_team_edges(parent_team_id INTEGER,child_team_id INTEGER,valid_from TEXT,valid_to TEXT);'
            . 'CREATE TABLE club_roster_members(team_id INTEGER,sportovec_id INTEGER,status TEXT,valid_from TEXT,valid_to TEXT);'
            . 'CREATE TABLE account_person_roles(id INTEGER PRIMARY KEY,account_id INTEGER,sportovec_id INTEGER,'
            . 'relation_role TEXT,status TEXT,valid_from TEXT,valid_to TEXT);'
            . 'CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY,email TEXT,jmeno TEXT,prijmeni TEXT,aktivni INTEGER,email_overeno INTEGER)');
        $pdo->exec("INSERT INTO club_seasons VALUES(1,'Synthetic','active');"
            . "INSERT INTO club_teams VALUES(10,1,'Group','active'),(11,1,'Roster','active');"
            . "INSERT INTO club_team_edges VALUES(10,11,'2020-01-01',NULL),(11,10,'2020-01-01',NULL)");
        for ($i = 1; $i <= $size; $i++) {
            $pdo->prepare("INSERT INTO club_roster_members VALUES(11,?,'active','2020-01-01',NULL)")->execute([$i]);
            $pdo->prepare("INSERT INTO verejni_uzivatele VALUES(?,?,'Synthetic','Synthetic',1,1)")->execute([1000 + $i, 'person' . $i . '@example.test']);
            $pdo->prepare("INSERT INTO account_person_roles VALUES(?,?,?,'guardian','approved','2020-01-01',NULL)")->execute([$i, 1000 + $i, $i]);
        }
        return $pdo;
    }
}
