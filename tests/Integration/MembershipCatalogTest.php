<?php

declare(strict_types=1);

namespace Tests\Integration;

use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\MonthlyMembershipFixture as Fixture;

require_once dirname(__DIR__).'/Support/MonthlyMembershipFixture.php';
require_once dirname(__DIR__, 2).'/includes/membership_setup.php';

final class MembershipCatalogTest extends TestCase
{
    private PDO $pdo;
    private mixed $session;

    protected function setUp(): void
    {
        $this->session = $_SESSION ?? [];
        $this->pdo = Fixture::database();
        $this->pdo->exec("ALTER TABLE club_teams ADD COLUMN team_kind TEXT DEFAULT 'roster';"
            . "INSERT INTO club_teams(id,name,status,team_kind) VALUES(100,'LOCALHOST Závodní tým','active','group'),"
            . "(101,'LOCALHOST MTB','active','roster'),(102,'LOCALHOST Silnice','active','roster'),(103,'LOCALHOST Dráha','active','roster');"
            . 'CREATE TABLE club_team_edges(id INTEGER PRIMARY KEY,parent_team_id INTEGER,child_team_id INTEGER,valid_from TEXT,valid_to TEXT);'
            . "INSERT INTO club_team_edges VALUES(1,100,101,'2020-01-01',NULL),(2,100,102,'2020-01-01',NULL),(3,100,103,'2020-01-01',NULL);");
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->session;
    }

    private function configure(string $interval = 'month', bool $group = true): int
    {
        $this->pdo->exec('UPDATE shop_variants SET amount_minor=220000 WHERE id=1');
        return \monthlyMembershipConfigure($this->pdo, [
            'variant_id' => 1, 'team_id' => $group ? 100 : 10, 'team_ids' => $group ? [101,102,103] : [],
            'due_day' => 15, 'billing_interval' => $interval, 'terms_text' => 'LOCALHOST syntetický tarif se schváleným intervalem '.$interval,
            'issuer_id' => 1, 'rule_id' => 1, 'document_kind' => 'contribution_confirmation', 'confirmed' => '1',
        ]);
    }

    private function subscribe(int $tariff, array $teams, string $date = '2026-11-05'): int
    {
        $hash = $this->pdo->query('SELECT terms_sha256 FROM monthly_membership_tariffs WHERE id='.$tariff)->fetchColumn();
        return \monthlyMembershipSubscribe(
            $this->pdo,
            20,
            1,
            $tariff,
            ['name' => 'LOCALHOST Rodič', 'address' => 'LOCALHOST Praha'],
            $hash,
            hash('sha256', 'LOCALHOST selection'),
            true,
            new DateTimeImmutable($date),
            $teams
        );
    }

    public function testMultipleDisciplinesCreateOneChargeAndReplayDoesNotDuplicateGrants(): void
    {
        $tariff = $this->configure();
        $id = $this->subscribe($tariff, [103,101,101]);
        self::assertSame($id, $this->subscribe($tariff, [101,103]));
        self::assertSame(220000, (int)$this->pdo->query('SELECT SUM(amount_minor) FROM club_member_charges')->fetchColumn());
        self::assertSame([101,103], array_map('intval', $this->pdo->query('SELECT team_id FROM monthly_membership_team_grants ORDER BY team_id')->fetchAll(PDO::FETCH_COLUMN)));
        self::assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM club_roster_members WHERE team_id=100')->fetchColumn());
        $this->expectException(\DomainException::class);
        $this->subscribe($tariff, [102]);
    }

    public function testApprovedRecurringProgramCanPassPublicationWithoutOneTimeOffer(): void
    {
        require_once dirname(__DIR__).'/Support/ShopAccountingPublicationFixture.php';
        \shopAccountingPublicationFixture($this->pdo);
        $this->pdo->exec("ALTER TABLE shop_products ADD COLUMN visibility TEXT DEFAULT 'visible'");
        self::assertFalse(\shopCatalogPublicationReadiness($this->pdo, 1)['ready']);
        $this->configure();
        $ready = \shopCatalogPublicationReadiness($this->pdo, 1);
        self::assertTrue($ready['ready'], implode(' ', $ready['blockers']));
        $this->pdo->exec("UPDATE monthly_membership_tariffs SET status='inactive'");
        self::assertFalse(\shopCatalogPublicationReadiness($this->pdo, 1)['ready']);
    }

    public function testSelectionOutsideTariffOrEmptyCannotCreateDebt(): void
    {
        $tariff = $this->configure();
        foreach ([[],[10],[100],[101,999]] as $teams) {
            try {
                $this->subscribe($tariff, $teams);
                self::fail('Unapproved selection accepted.');
            } catch (\DomainException) {
                self::assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM monthly_memberships')->fetchColumn());
                self::assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM club_member_charges')->fetchColumn());
            }
        }
    }

    public function testChangedHierarchyRejectsStaleSelectionAtomically(): void
    {
        $tariff = $this->configure();
        $this->pdo->exec("UPDATE club_team_edges SET valid_to='2026-11-04' WHERE child_team_id=103");
        try {
            $this->subscribe($tariff, [101,103]);
            self::fail('Expired edge accepted.');
        } catch (\DomainException) {
            self::assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM monthly_memberships')->fetchColumn());
        }
    }

    public function testCancellationEndsOnlyThisMembershipsSelectedGrants(): void
    {
        $id = $this->subscribe($this->configure(), [101,103]);
        $other = \kisRosterGrantPeriod($this->pdo, 101, 1, 7, 'manual', '2026-11-01', null, 'LOCALHOST jiné nezávislé členství.', 'LOCALHOST-other');
        self::assertSame('2026-11-30', \monthlyMembershipCancel($this->pdo, 20, $id, true, new DateTimeImmutable('2026-11-10')));
        self::assertSame(2, (int)$this->pdo->query("SELECT COUNT(*) FROM club_roster_members WHERE source='shop' AND valid_to='2026-11-30'")->fetchColumn());
        self::assertNull($this->pdo->query('SELECT valid_to FROM club_roster_members WHERE id='.$other['id'])->fetchColumn());
    }

    public function testAnnualBillingCoversTwelveMonthsAndKeepsLeapYearEndAndStandingPayment(): void
    {
        $id = $this->subscribe($this->configure('year', false), [], '2023-03-31');
        $charge = $this->pdo->query('SELECT * FROM club_member_charges')->fetch(PDO::FETCH_ASSOC);
        self::assertSame('2024-02-29', $charge['period_to']);
        self::assertSame('2023-04-07', $charge['due_on']);
        self::assertSame(0, \monthlyMembershipTransaction($this->pdo, fn () => \monthlyMembershipGenerateLocked($this->pdo, $id, '2024-02-29')));
        self::assertSame(1, \monthlyMembershipTransaction($this->pdo, fn () => \monthlyMembershipGenerateLocked($this->pdo, $id, '2024-03-01')));
        self::assertSame(0, \monthlyMembershipTransaction($this->pdo, fn () => \monthlyMembershipGenerateLocked($this->pdo, $id, '2024-03-01')));
        self::assertSame('2025-02-28', \monthlyMembershipCancel($this->pdo, 20, $id, true, new DateTimeImmutable('2024-04-01')));
        self::assertSame(0, \monthlyMembershipTransaction($this->pdo, fn () => \monthlyMembershipGenerateLocked($this->pdo, $id, '2026-03-01')));
        $movement = Fixture::movement($this->pdo, 'LOCALHOST-ANNUAL', '4400', '2024-04-02');
        self::assertTrue(\monthlyMembershipImportMovement($this->pdo, $movement, 'CZ6508000000192000145399'));
        self::assertSame(2, (int)$this->pdo->query("SELECT COUNT(*) FROM club_member_charges WHERE status='paid'")->fetchColumn());
    }

    public function testIssuerCreatesVersionsAndNeverResetsSeries(): void
    {
        $this->pdo->exec('UPDATE accounting_document_series SET next_number=42');
        $tariff = $this->configure();
        $input = ['name' => 'LOCALHOST nový klub', 'address' => 'LOCALHOST Praha', 'registration_id' => 'LOCALHOST',
            'confirmed' => '1', 'approval_evidence' => 'LOCALHOST potvrzené údaje'];
        $id = \membershipSaveIssuer($this->pdo, $input);
        self::assertGreaterThan(1, $id);
        self::assertSame(1, (int)$this->pdo->query('SELECT issuer_id FROM monthly_membership_tariffs WHERE id='.$tariff)->fetchColumn());
        self::assertSame(42, (int)$this->pdo->query('SELECT next_number FROM accounting_document_series WHERE series_year=2026')->fetchColumn());
        \membershipSaveIssuer($this->pdo, $input);
        self::assertSame(1, (int)$this->pdo->query("SELECT COUNT(*) FROM accounting_rules WHERE category_code='CLEN' AND activity='KLUB'")->fetchColumn());
        $input['address'] = '';
        try {
            \membershipSaveIssuer($this->pdo, $input);
            self::fail('Incomplete issuer accepted.');
        } catch (\InvalidArgumentException) {
            self::assertSame(3, (int)$this->pdo->query('SELECT COUNT(*) FROM accounting_issuer_versions')->fetchColumn());
        }
    }
}
