<?php
declare(strict_types=1);

namespace Tests\Unit;

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
}
