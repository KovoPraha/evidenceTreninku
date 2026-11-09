<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\ParentJourneyAcceptanceFixture;

require_once dirname(__DIR__, 2) . '/includes/shop_program_quick_checkout.php';
require_once dirname(__DIR__, 2) . '/includes/athlete_registration_admin.php';
require_once dirname(__DIR__, 2) . '/includes/quick_account_password.php';
require_once dirname(__DIR__, 2) . '/includes/club_event_notification.php';
require_once dirname(__DIR__) . '/Support/ParentJourneyAcceptanceFixture.php';

/** No persistent database, external payment provider or real mail transport. */
final class ParentJourneyAcceptanceTest extends TestCase
{
    private const BANK = ['iban' => 'CZ6508000000192000145399','bic' => 'GIBACZPX','account_label' => 'LOCALHOST NEPLATIT','due_days' => 7];
    private array $sessionBackup;

    protected function setUp(): void
    {
        $this->sessionBackup = $_SESSION ?? [];
        $_SESSION = ['trener_id' => 7,'role' => 'admin'];
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->sessionBackup;
    }

    public function testQuickFormSubmitsEveryCurrentVersionAndStaleTermsRollBackBeforeRefresh(): void
    {
        $pdo = ParentJourneyAcceptanceFixture::create();
        $programId = (int)\clubProgramCreate($pdo, 7, 'LOCALHOST-TERMS', 'LOCALHOST NEPLATIT kroužek')['id'];
        foreach (['program_cancellation','program_consent'] as $purpose) {
            \clubProgramTermsConfigure($pdo, 7, 'program', $programId, $purpose, 'Syntetický schválený text pouze pro izolovaný test.', true);
        }
        $offer = \clubProgramCreateOffer($pdo, 7, $programId, 1, 10, 501, 601, 'LOCALHOST-TERMS-OFFER', 'LOCALHOST období', '2026-09-01', '2027-08-31', null, null, 1, 'active');
        $beforeOptional = $this->quickFormVersions(\athleteRegistrationCurrentTerms($pdo));
        self::assertArrayNotHasKey('csc_birth_number_sharing', $beforeOptional);
        $pdo->exec("INSERT INTO club_event_term_versions"
            . "(scope_type,scope_key,consent_purpose,terms_version,consent_text_plain,actor_type,actor_id) "
            . "VALUES('athlete_registration','athlete-registration-v1','csc_birth_number_sharing',"
            . "'csc-v1','Volitelný syntetický souhlas ČSC.','system',0)");
        $terms = \athleteRegistrationCurrentTerms($pdo);
        $submitted = $this->quickFormVersions($terms);
        self::assertSame(array_map(static fn (array $term): string => $term['version'], $terms), $submitted);
        self::assertArrayHasKey('csc_birth_number_sharing', $submitted);

        $programTerms = \clubProgramTermsEffective($pdo, $programId, (int)$offer['id']);
        $input = [
            'parent_first_name' => 'LOCALHOST','parent_last_name' => 'NEPLATIT','email' => 'quick-terms@example.test','contact_phone' => '+420 777 123 456',
            'requested_role' => 'guardian','jmeno' => 'Dítě','prijmeni' => 'Syntetické','narozeni' => '2017-04-03','citizenship_country_code' => 'SK',
            'address_street' => 'LOCALHOST','address_house_number' => '12','address_city' => 'Praha','address_postcode' => '19000',
            'has_czech_birth_number' => '0','birth_number' => '','member_data_notice' => '1','birth_number_legal_notice' => '1',
            'photo_internal' => '0','photo_public' => '0','program_terms_accepted' => '1',
            'program_term_version' => array_map(static fn (array $term): string => $term['terms_version'], $programTerms),
        ];
        $key = bin2hex(random_bytes(16));
        $access = bin2hex(random_bytes(32));
        try {
            \shopProgramQuickCheckoutPlace($pdo, 601, $input, $beforeOptional, $key, $access, self::BANK);
            self::fail('A newly activated optional notice must require a refreshed form.');
        } catch (\AthleteRegistrationException $exception) {
            self::assertStringContainsString('Podmínky registrace se změnily', $exception->getMessage());
        }
        self::assertSame(2, (int)$pdo->query('SELECT COUNT(*) FROM verejni_uzivatele')->fetchColumn());
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM shop_orders')->fetchColumn());
        $pdo->exec("INSERT INTO club_event_term_versions"
            . "(scope_type,scope_key,consent_purpose,terms_version,consent_text_plain,actor_type,actor_id) "
            . "VALUES('athlete_registration','athlete-registration-v1','member_data_notice',"
            . "'member-v2','Nová syntetická informace pro registraci.','system',0)");
        try {
            \shopProgramQuickCheckoutPlace($pdo, 601, $input, $submitted, $key, $access, self::BANK);
            self::fail('A previously rendered version must be rejected.');
        } catch (\AthleteRegistrationException $exception) {
            self::assertStringContainsString('Podmínky registrace se změnily', $exception->getMessage());
        }
        foreach ([
            'verejni_uzivatele' => 2,
            'account_person_claim_requests' => 0,
            'athlete_registration_consent_snapshots' => 0,
            'shop_orders' => 0,
            'shop_order_items' => 0,
            'payments' => 0,
        ] as $table => $expected) {
            self::assertSame($expected, (int)$pdo->query('SELECT COUNT(*) FROM '.$table)->fetchColumn(), $table);
        }
        self::assertFalse($pdo->inTransaction());

        $current = \athleteRegistrationCurrentTerms($pdo);
        $refreshed = $this->quickFormVersions($current);
        self::assertSame('member-v2', $refreshed['member_data_notice']);
        self::assertSame('csc-v1', $refreshed['csc_birth_number_sharing']);
        $order = \shopProgramQuickCheckoutPlace($pdo, 601, $input, $refreshed, $key, $access, self::BANK);
        self::assertSame('quick_program', $order['checkout_mode']);
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM shop_orders')->fetchColumn());
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM payments')->fetchColumn());
        $optionalConsent = $pdo->query("SELECT COUNT(*) FROM athlete_registration_consent_snapshots "
            . "WHERE purpose='csc_birth_number_sharing' AND terms_version='csc-v1' AND accepted=0");
        self::assertSame(1, (int)$optionalConsent->fetchColumn());
    }

    /** Render the actual template fragment and decode its browser-submitted field names. */
    private function quickFormVersions(array $terms): array
    {
        $source = (string)file_get_contents(dirname(__DIR__, 2).'/booking/rychla_prihlaska.php');
        self::assertSame(1, preg_match('~<\?php foreach \(\$terms as \$purpose => \$term\):\?>.*?<\?php endforeach;\?>~s', $source, $match));
        $escape = static fn (mixed $value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        ob_start();
        eval('?>'.str_replace('quickProgramH(', '$escape(', $match[0]));
        $html = (string)ob_get_clean();
        $dom = new \DOMDocument();
        $dom->loadHTML('<form>'.$html.'</form>');
        $pairs = [];
        foreach ($dom->getElementsByTagName('input') as $input) {
            $pairs[] = rawurlencode($input->getAttribute('name')).'='.rawurlencode($input->getAttribute('value'));
        }
        parse_str(implode('&', $pairs), $post);
        return $post['term_version'] ?? [];
    }

    public function testQuickParentJourneyReachesRosterThroughActualServicesExactlyOnce(): void
    {
        [$pdo, $order, $requestId, $input, $versions, $key, $access] = $this->place();
        $accountId = (int)$order['account_id'];
        self::assertSame('pending', $pdo->query('SELECT status FROM account_person_claim_requests')->fetchColumn());
        self::assertSame('email_pending', $this->overviewState($pdo, $accountId));
        self::assertSame(1, \clubProgramOfferForVariant($pdo, 601)['held_order_count']);
        self::assertNull(\quickAccountPasswordSetupIssue($pdo, $accountId, 1));
        try {
            \athleteRegistrationAdminApproveExisting($pdo, $requestId, 101, 7, 'LOCALHOST ověření před e-mailem.');
            self::fail('Unverified parent must not be approved.');
        } catch (\AthleteRegistrationAdminException) {
            self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM club_program_enrollments')->fetchColumn());
        }

        $verified = \one_time_email_verification_consume($pdo, $order['verification_token']);
        self::assertSame($accountId, $verified['id']);
        self::assertNull(\one_time_email_verification_consume($pdo, $order['verification_token']));
        $grant = \quickAccountPasswordSetupIssue($pdo, $accountId, $verified['session_version']);
        self::assertNotNull($grant);
        $password = 'LOCALHOST-Neplatit-Acceptance-2026!';
        $version = \quickAccountPasswordSetupConsume($pdo, $grant, $accountId, $verified['session_version'], $password);
        self::assertSame(2, $version);
        self::assertNull(\quickAccountPasswordSetupConsume($pdo, $grant, $accountId, $version, $password));
        $hash = $pdo->query('SELECT heslo_hash FROM verejni_uzivatele WHERE id='.$accountId)->fetchColumn();
        self::assertTrue(password_verify($password, $hash));
        self::assertSame('payment_pending', $this->overviewState($pdo, $accountId));

        $paid = \shopOrderAdminConfirmBankPayment($pdo, (int)$order['payment_id'], 7, 'LOCALHOST NEPLATIT syntetické potvrzení.', true);
        self::assertSame(1, $paid['pending_identity']);
        self::assertSame('identity_pending', $this->overviewState($pdo, $accountId));
        self::assertSame(1, \clubProgramOfferForVariant($pdo, 601)['held_order_count']);
        self::assertSame(0, \clubProgramOfferForVariant($pdo, 601)['available_count']);
        self::assertSame([], \clubProgramEnrollmentsForAccount($pdo, $accountId));
        self::assertSame([], \clubProgramPendingOrderItems($pdo, 11), 'Another parent cannot see this application.');

        $approved = \athleteRegistrationAdminApproveExisting($pdo, $requestId, 101, 7, 'LOCALHOST ověřená existující osoba.');
        self::assertSame(101, $approved['person_id']);
        self::assertSame('approved', $pdo->query('SELECT status FROM account_person_claim_requests')->fetchColumn());
        $role = $pdo->query('SELECT relation_role FROM account_person_roles WHERE account_id='.$accountId)->fetchColumn();
        self::assertSame('guardian', $role);
        self::assertSame('active', $this->overviewState($pdo, $accountId));
        self::assertSame(1, count(\clubProgramEnrollmentsForAccount($pdo, $accountId)));
        self::assertSame('active', $pdo->query('SELECT status FROM club_roster_members')->fetchColumn());
        $capacity = \clubProgramOfferForVariant($pdo, 601);
        self::assertSame(1, $capacity['active_enrollment_count']);
        self::assertSame(0, $capacity['held_order_count']);
        self::assertSame(0, $capacity['available_count']);

        $replay = \shopProgramQuickCheckoutPlace($pdo, 601, $input, $versions, $key, $access, self::BANK);
        self::assertTrue($replay['replayed']);
        self::assertFalse(\shopOrderAdminConfirmBankPayment($pdo, (int)$order['payment_id'], 7, 'LOCALHOST opakované potvrzení.', true)['changed']);
        $pdo->beginTransaction();
        self::assertSame(['linked' => 0,'activated' => 0], \clubProgramLinkApprovedAthleteRequestInTransaction($pdo, $requestId, 101, 7));
        $pdo->commit();
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM club_program_enrollments')->fetchColumn());
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM club_roster_members')->fetchColumn());
        self::assertSame(1, (int)$pdo->query("SELECT COUNT(*) FROM club_event_notifications WHERE notification_type='identity_approved'")->fetchColumn());
        self::assertSame(1, (int)$pdo->query("SELECT COUNT(*) FROM club_event_notifications WHERE notification_type='program_activated'")->fetchColumn());

        $sent = [];
        $sender = static function (string $email, string $subject, string $body) use (&$sent): bool {
            $sent[] = compact('email', 'subject', 'body');
            return true;
        };
        while (($result = \clubEventNotificationProcessOne($pdo, $sender)) !== null) {
            self::assertTrue($result);
        }
        self::assertCount(4, $sent, 'Receipt, payment, decision and late activation each have one message.');
        self::assertSame(['parent-acceptance@example.test'], array_values(array_unique(array_column($sent, 'email'))));
        self::assertStringContainsString('sportovec byl zařazen do soupisky', implode("\n", array_column($sent, 'body')));
        self::assertSame(4, (int)$pdo->query("SELECT COUNT(*) FROM club_event_notifications WHERE status='sent'")->fetchColumn());
        self::assertNull(\clubEventNotificationProcessOne($pdo, $sender));
        require_once dirname(__DIR__) . '/Support/ParentJourneyCompletion.php';
        \parentJourneyComplete($pdo, $accountId, (int) $order['payment_id']);
    }

    public function testPaidRejectedApplicationReleasesPlaceAndClearlyRequiresManualRefund(): void
    {
        [$pdo, $order, $requestId] = $this->place();
        \one_time_email_verification_consume($pdo, $order['verification_token']);
        \shopOrderAdminConfirmBankPayment($pdo, (int)$order['payment_id'], 7, 'LOCALHOST NEPLATIT syntetické potvrzení.', true);
        \athleteRegistrationAdminReject($pdo, $requestId, 7, 'LOCALHOST údaje se nepodařilo ověřit.');
        self::assertSame('rejected', $pdo->query('SELECT status FROM account_person_claim_requests')->fetchColumn());
        self::assertSame('cancelled', $pdo->query('SELECT status FROM shop_orders')->fetchColumn());
        self::assertSame('refund_required', $this->overviewState($pdo, (int)$order['account_id']));
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM club_program_enrollments')->fetchColumn());
        self::assertSame(1, \clubProgramOfferForVariant($pdo, 601)['available_count']);
        $body = (string)$pdo->query("SELECT body_plain FROM club_event_notifications WHERE notification_type='identity_rejected'")->fetchColumn();
        self::assertStringContainsString('Vrácení peněz musí vyřídit správce klubu', $body);
    }

    public function testQuickCheckoutPinsOfferAndPaymentActivatesAlreadyApprovedPersonOnCurrentSchema(): void
    {
        [$pdo, $order, $requestId] = $this->place(true);
        self::assertSame((int)$pdo->query('SELECT id FROM club_program_offers')->fetchColumn(), (int)$pdo->query('SELECT club_program_offer_id FROM shop_order_items')->fetchColumn());
        \one_time_email_verification_consume($pdo, $order['verification_token']);
        \athleteRegistrationAdminApproveExisting($pdo, $requestId, 101, 7, 'LOCALHOST ověřená osoba před přijetím platby.');
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM club_program_enrollments')->fetchColumn());
        \shopOrderAdminConfirmBankPayment($pdo, (int)$order['payment_id'], 7, 'LOCALHOST NEPLATIT potvrzení po schválení osoby.', true);
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM club_program_enrollments')->fetchColumn());
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM club_roster_members')->fetchColumn());
        self::assertSame('active', $this->overviewState($pdo, (int)$order['account_id']));
    }

    public function testQuickPaymentBeforeIdentityKeepsCapacityHoldOnCurrentSchema(): void
    {
        [$pdo, $order, $requestId] = $this->place(true);
        \one_time_email_verification_consume($pdo, $order['verification_token']);
        $paid = \shopOrderAdminConfirmBankPayment($pdo, (int)$order['payment_id'], 7, 'LOCALHOST NEPLATIT platba před schválením osoby.', true);
        self::assertSame(1, $paid['pending_identity']);
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM club_program_enrollments')->fetchColumn());
        self::assertSame(1, \clubProgramOfferForVariant($pdo, 601)['held_order_count']);
        \athleteRegistrationAdminApproveExisting($pdo, $requestId, 101, 7, 'LOCALHOST ověřená osoba po přijetí platby.');
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM club_program_enrollments')->fetchColumn());
        self::assertSame(0, \clubProgramOfferForVariant($pdo, 601)['held_order_count']);
    }

    private function overviewState(PDO $pdo, int $accountId): string
    {
        $items = \clubProgramPendingOrderItems($pdo, $accountId);
        self::assertCount(1, $items);
        return \clubProgramAccountItemState($items[0])['code'];
    }

    private function place(bool $withOfferSnapshot = false): array
    {
        $pdo = ParentJourneyAcceptanceFixture::create();
        if ($withOfferSnapshot) {
            $pdo->exec('ALTER TABLE shop_order_items ADD COLUMN club_program_offer_id INTEGER NULL');
        }
        $programId = (int)\clubProgramCreate($pdo, 7, 'LOCALHOST-ACCEPTANCE', 'LOCALHOST NEPLATIT kroužek')['id'];
        foreach (['program_cancellation','program_consent'] as $purpose) {
            \clubProgramTermsConfigure($pdo, 7, 'program', $programId, $purpose, 'Syntetický schválený text pouze pro izolovaný test.', true);
        }
        $offer = \clubProgramCreateOffer($pdo, 7, $programId, 1, 10, 501, 601, 'LOCALHOST-TERM', 'LOCALHOST období', '2026-09-01', '2027-08-31', null, null, 1, 'active');
        $terms = \clubProgramTermsEffective($pdo, $programId, (int)$offer['id']);
        $versions = array_map(static fn (array $term): string => $term['version'], \athleteRegistrationCurrentTerms($pdo));
        $input = [
            'parent_first_name' => 'LOCALHOST','parent_last_name' => 'NEPLATIT','email' => 'parent-acceptance@example.test','contact_phone' => '+420 777 123 456',
            'requested_role' => 'guardian','jmeno' => 'Dítě','prijmeni' => 'Jedna','narozeni' => '2017-04-03','citizenship_country_code' => 'SK',
            'address_street' => 'LOCALHOST','address_house_number' => '12','address_orientation_number' => '','address_city' => 'Praha','address_postcode' => '19000',
            'has_czech_birth_number' => '0','birth_number' => '','member_data_notice' => '1','birth_number_legal_notice' => '1','photo_internal' => '0','photo_public' => '0',
            'program_terms_accepted' => '1','program_term_version' => array_map(static fn (array $term): string => $term['terms_version'], $terms),'message' => '',
        ];
        $key = bin2hex(random_bytes(16));
        $access = bin2hex(random_bytes(32));
        $order = \shopProgramQuickCheckoutPlace($pdo, 601, $input, $versions, $key, $access, self::BANK);
        $requestId = (int)$pdo->query('SELECT id FROM account_person_claim_requests')->fetchColumn();
        return [$pdo, $order, $requestId, $input, $versions, $key, $access];
    }
}
