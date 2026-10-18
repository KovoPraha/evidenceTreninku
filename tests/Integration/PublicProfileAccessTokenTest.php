<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/public_profile_token.php';
require_once dirname(__DIR__, 2) . '/includes/auth_session.php';

final class PublicProfileAccessTokenTest extends TestCase
{
    public function testLegacyTokenIsHashedRotatedAndStillExchangeable(): void
    {
        $pdo = $this->database();
        $legacy = str_repeat('a', 64);
        $pdo->prepare('INSERT INTO sportovci(id,hash) VALUES (?,?)')->execute([7, $legacy]);
        $migration = require dirname(__DIR__, 2) . '/migrations/20261008101000_public_profile_access_tokens.php';
        $migration['up']($pdo);
        self::assertTrue($migration['verify']($pdo));
        self::assertSame(7, \public_profile_access_resolve($pdo, $legacy));
        self::assertNotSame($legacy, (string)$pdo->query('SELECT hash FROM sportovci WHERE id=7')->fetchColumn());
        self::assertSame(
            \public_profile_access_token_hash($legacy),
            (string)$pdo->query('SELECT token_hash FROM public_profile_access_tokens WHERE sportovec_id=7')->fetchColumn()
        );

        $snapshot = (string)$pdo->query('SELECT hash FROM sportovci WHERE id=7')->fetchColumn();
        $migration['up']($pdo);
        self::assertSame($snapshot, (string)$pdo->query('SELECT hash FROM sportovci WHERE id=7')->fetchColumn());
    }

    public function testIssueResolveAndRevokeLifecycle(): void
    {
        $pdo = $this->database();
        $pdo->prepare('INSERT INTO sportovci(id,hash) VALUES (?,?)')->execute([9, str_repeat('b', 64)]);
        $migration = require dirname(__DIR__, 2) . '/migrations/20261008101000_public_profile_access_tokens.php';
        $migration['up']($pdo);
        $issued = \public_profile_access_issue($pdo, 9, 5, 3600);
        self::assertSame(9, \public_profile_access_resolve($pdo, $issued['token']));
        self::assertSame(1, \public_profile_access_revoke_for_person($pdo, 9));
        self::assertNull(\public_profile_access_resolve($pdo, $issued['token']));
    }

    public function testRevocationImmediatelyInvalidatesExistingProfileSession(): void
    {
        $before = $_SESSION ?? [];
        try {
            $_SESSION = [];
            $pdo = $this->database();
            $pdo->prepare('INSERT INTO sportovci(id,hash) VALUES (?,?)')->execute([42, str_repeat('c', 64)]);
            $migration = require dirname(__DIR__, 2) . '/migrations/20261008101000_public_profile_access_tokens.php';
            $migration['up']($pdo);
            $issued = \public_profile_access_issue($pdo, 42, 5, 3600);
            self::assertSame(42, \public_profile_access_resolve($pdo, $issued['token']));
            \public_profile_access_grant_session(42, $issued['token']);
            self::assertSame(42, \public_profile_access_session_athlete_id($pdo));
            \public_profile_access_revoke_for_person($pdo, 42);
            self::assertNull(\public_profile_access_session_athlete_id($pdo));
            self::assertArrayNotHasKey('public_profile_access', $_SESSION);
        } finally {
            $_SESSION = $before;
        }
    }

    public function testTokenRotationInvalidatesPreviouslyOpenedProfile(): void
    {
        $before = $_SESSION ?? [];
        try {
            $_SESSION = [];
            $pdo = $this->database();
            $pdo->prepare('INSERT INTO sportovci(id,hash) VALUES (?,?)')->execute([42, str_repeat('d', 64)]);
            $migration = require dirname(__DIR__, 2) . '/migrations/20261008101000_public_profile_access_tokens.php';
            $migration['up']($pdo);
            $first = \public_profile_access_issue($pdo, 42, 5, 3600);
            \public_profile_access_grant_session(42, $first['token']);
            self::assertSame(42, \public_profile_access_session_athlete_id($pdo));
            $second = \public_profile_access_issue($pdo, 42, 5, 3600);
            self::assertNotSame($first['token'], $second['token']);
            self::assertNull(\public_profile_access_session_athlete_id($pdo));
            self::assertSame(42, \public_profile_access_resolve($pdo, $second['token']));
        } finally {
            $_SESSION = $before;
        }
    }

    public function testUnboundLegacySessionGrantIsRejected(): void
    {
        $before = $_SESSION ?? [];
        try {
            $_SESSION = ['public_profile_access' => ['sportovec_id' => 42, 'expires_at' => time() + 300]];
            self::assertNull(\public_profile_access_session_athlete_id($this->database()));
            self::assertArrayNotHasKey('public_profile_access', $_SESSION);
        } finally {
            $_SESSION = $before;
        }
    }

    public function testIdentityChangeCannotInheritAnotherAthletesProfile(): void
    {
        $before = $_SESSION ?? [];
        try {
            $_SESSION = [];
            $pdo = $this->database();
            $pdo->prepare('INSERT INTO sportovci(id,hash) VALUES (?,?)')->execute([42, str_repeat('c', 64)]);
            $migration = require dirname(__DIR__, 2) . '/migrations/20261008101000_public_profile_access_tokens.php';
            $migration['up']($pdo);
            $issued = \public_profile_access_issue($pdo, 42, 5, 3600);
            \public_profile_access_grant_session(42, $issued['token']);
            \auth_session_bind_child(99, 1);
            self::assertNull(\public_profile_access_session_athlete_id($pdo));
            self::assertArrayNotHasKey('public_profile_access', $_SESSION);
        } finally {
            $_SESSION = $before;
        }
    }

    public function testStaffProfileGrantCannotSurviveTrainerIdentityChange(): void
    {
        $before = $_SESSION ?? [];
        try {
            $_SESSION = ['trener_id' => 5, 'trener_session_version' => 2];
            $pdo = $this->database();
            \public_profile_access_grant_staff_session(42);
            self::assertSame(42, \public_profile_access_session_athlete_id($pdo));
            \auth_session_clear_identity('trainer');
            self::assertNull(\public_profile_access_session_athlete_id($pdo));
        } finally {
            $_SESSION = $before;
        }
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE nastaveni(klic TEXT PRIMARY KEY,hodnota TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE sportovci(id INTEGER PRIMARY KEY,hash TEXT NOT NULL)');
        return $pdo;
    }
}
