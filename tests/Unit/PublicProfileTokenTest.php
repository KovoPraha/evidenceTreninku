<?php
declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2).'/includes/public_profile_token.php';

final class PublicProfileTokenTest extends TestCase
{
    public function testTokensAreStrongAndUnique():void
    {
        $tokens=[];for($i=0;$i<100;$i++){$token=\public_profile_token_generate();self::assertTrue(\public_profile_token_is_strong($token));$tokens[]=$token;}
        self::assertCount(100,array_unique($tokens));
    }

    public function testMalformedAndShortValuesAreRejected():void
    {
        self::assertFalse(\public_profile_token_is_strong('ABCDEF123456'));
        self::assertFalse(\public_profile_token_is_strong(''));
        self::assertFalse(\public_profile_token_is_strong(str_repeat('g',64)));
    }

    public function testAccessTokenIsDomainSeparatedBeforeStorage(): void
    {
        $token = str_repeat('a', 64);
        self::assertSame(64, strlen(\public_profile_access_token_hash($token)));
        self::assertNotSame($token, \public_profile_access_token_hash($token));
        self::assertSame('', \public_profile_access_token_hash('short'));
    }

    public function testProfileSessionExpiresAndIsScopedToOneAthlete(): void
    {
        $before = $_SESSION ?? [];
        try {
            $_SESSION = [];
            $pdo = new PDO('sqlite::memory:');
            $pdo->exec('CREATE TABLE public_profile_access_tokens '
                . '(sportovec_id INTEGER, token_hash TEXT, scope TEXT, revoked_at TEXT, expires_at TEXT)');
            $token = str_repeat('a', 64);
            $statement = $pdo->prepare('INSERT INTO public_profile_access_tokens VALUES (?,?,?,?,?)');
            $statement->execute([42, \public_profile_access_token_hash($token), 'training_read', null, '2099-01-01 00:00:00']);
            \public_profile_access_grant_session(42, $token, 300);
            self::assertSame(42, \public_profile_access_session_athlete_id($pdo));
            $_SESSION['public_profile_access']['expires_at'] = time() - 1;
            self::assertNull(\public_profile_access_session_athlete_id($pdo));
        } finally {
            $_SESSION = $before;
        }
    }
}
