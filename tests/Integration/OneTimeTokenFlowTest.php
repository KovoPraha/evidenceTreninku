<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/one_time_token.php';

final class OneTimeTokenFlowTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->pdo->exec(
            'CREATE TABLE verejni_uzivatele ('
            . 'id INTEGER PRIMARY KEY, session_version INTEGER NOT NULL, aktivni INTEGER NOT NULL, '
            . 'email_overeno INTEGER NOT NULL, verifikacni_token TEXT NULL, '
            . 'verifikacni_token_expires_at TEXT NULL, email TEXT, jmeno TEXT)'
        );
        $this->pdo->exec(
            'CREATE TABLE individualni_lekce ('
            . 'id INTEGER PRIMARY KEY, nazev TEXT, datum TEXT, cas_od TEXT, cas_do TEXT, trener_id INTEGER)'
        );
        $this->pdo->exec(
            'CREATE TABLE verejne_rezervace ('
            . 'id INTEGER PRIMARY KEY, lekce_id INTEGER, uzivatel_id INTEGER, stav TEXT, '
            . 'potvrzovaci_token TEXT NULL, potvrzovaci_token_expires_at TEXT NULL, '
            . 'cas_potvrzeni TEXT NULL, slot_cas_od TEXT NULL, slot_cas_do TEXT NULL)'
        );
    }

    public function testEmailVerificationIsAtomicSingleUseAndExpires(): void
    {
        $valid = one_time_token_issue(ONE_TIME_TOKEN_EMAIL_VERIFICATION, 3600, 1_700_000_000);
        $insert = $this->pdo->prepare(
            'INSERT INTO verejni_uzivatele VALUES (?, 3, 1, 0, ?, ?, ?, ?)'
        );
        $insert->execute([1, $valid['hash'], $valid['expires_at'], 'user@example.test', 'Eva']);

        self::assertSame(
            ['id' => 1, 'session_version' => 3],
            one_time_email_verification_consume($this->pdo, $valid['token'], 1_700_000_100)
        );
        self::assertNull(one_time_email_verification_consume($this->pdo, $valid['token'], 1_700_000_100));
        self::assertSame(1, (int)$this->pdo->query('SELECT email_overeno FROM verejni_uzivatele')->fetchColumn());
        self::assertNull($this->pdo->query('SELECT verifikacni_token FROM verejni_uzivatele')->fetchColumn());

        $expired = one_time_token_issue(ONE_TIME_TOKEN_EMAIL_VERIFICATION, 3600, 1_700_000_000);
        $insert->execute([2, $expired['hash'], $expired['expires_at'], 'old@example.test', 'Olda']);
        self::assertNull(one_time_email_verification_consume($this->pdo, $expired['token'], 1_700_004_000));
    }

    public function testBookingGetLookupDoesNotConsumeAndPostConsumeIsSingleUse(): void
    {
        $issued = one_time_token_issue(ONE_TIME_TOKEN_BOOKING_APPROVAL, 3600, 1_700_000_000);
        $this->pdo->exec(
            "INSERT INTO verejni_uzivatele VALUES (1, 1, 1, 1, NULL, NULL, 'r@example.test', 'Roman')"
        );
        $this->pdo->exec(
            "INSERT INTO individualni_lekce VALUES (1, 'Dráha', '2026-08-03', '10:00', '11:00', 9)"
        );
        $insert = $this->pdo->prepare(
            "INSERT INTO verejne_rezervace VALUES (1, 1, 1, 'ceka', ?, ?, NULL, '10:00', '11:00')"
        );
        $insert->execute([$issued['hash'], $issued['expires_at']]);

        self::assertNotNull(one_time_booking_approval_lookup($this->pdo, $issued['token'], 1_700_000_100));
        self::assertSame('ceka', $this->pdo->query('SELECT stav FROM verejne_rezervace')->fetchColumn());

        $consumed = one_time_booking_approval_consume(
            $this->pdo,
            $issued['token'],
            'potvrdit',
            1_700_000_100
        );
        self::assertSame('potvrzena', $consumed['stav'] ?? null);
        self::assertNull(one_time_booking_approval_consume($this->pdo, $issued['token'], 'zamit', 1_700_000_100));
        self::assertNull($this->pdo->query('SELECT potvrzovaci_token FROM verejne_rezervace')->fetchColumn());
    }

    public function testExpiredBookingTokenCannotBeLookedUpOrConsumed(): void
    {
        $issued = one_time_token_issue(ONE_TIME_TOKEN_BOOKING_APPROVAL, 3600, 1_700_000_000);
        $this->pdo->exec(
            "INSERT INTO verejni_uzivatele VALUES (1, 1, 1, 1, NULL, NULL, 'r@example.test', 'Roman')"
        );
        $this->pdo->exec(
            "INSERT INTO individualni_lekce VALUES (1, 'Dráha', '2026-08-03', '10:00', '11:00', 9)"
        );
        $insert = $this->pdo->prepare(
            "INSERT INTO verejne_rezervace VALUES (1, 1, 1, 'ceka', ?, ?, NULL, '10:00', '11:00')"
        );
        $insert->execute([$issued['hash'], $issued['expires_at']]);

        self::assertNull(one_time_booking_approval_lookup($this->pdo, $issued['token'], 1_700_004_000));
        self::assertNull(one_time_booking_approval_consume($this->pdo, $issued['token'], 'potvrdit', 1_700_004_000));
        self::assertSame('ceka', $this->pdo->query('SELECT stav FROM verejne_rezervace')->fetchColumn());
    }
}
