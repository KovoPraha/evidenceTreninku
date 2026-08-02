<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/one_time_token.php';

final class OneTimeTokenTest extends TestCase
{
    public function testIssuedTokenStoresOnlyPurposeBoundHashAndExpiry(): void
    {
        $issued = one_time_token_issue(ONE_TIME_TOKEN_EMAIL_VERIFICATION, 3600, 1_700_000_000);

        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $issued['token']);
        self::assertSame(
            one_time_token_hash(ONE_TIME_TOKEN_EMAIL_VERIFICATION, $issued['token']),
            $issued['hash']
        );
        self::assertNotSame($issued['token'], $issued['hash']);
        self::assertSame('2023-11-14 23:13:20', $issued['expires_at']);
        self::assertNotSame(
            $issued['hash'],
            one_time_token_hash(ONE_TIME_TOKEN_BOOKING_APPROVAL, $issued['token'])
        );
    }

    public function testMalformedTokenIsRejectedWithoutHashing(): void
    {
        self::assertSame('', one_time_token_hash(ONE_TIME_TOKEN_EMAIL_VERIFICATION, 'short'));
        self::assertSame('', one_time_token_hash(ONE_TIME_TOKEN_EMAIL_VERIFICATION, str_repeat('Z', 64)));
    }
}
