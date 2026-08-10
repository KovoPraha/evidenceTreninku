<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/password_security.php';

final class PasswordSecurityTest extends TestCase
{
    public function testDetectsModernBcryptHash(): void
    {
        $hash = password_hash('correct horse battery staple', PASSWORD_BCRYPT);

        self::assertTrue(\trainer_password_is_modern_hash($hash));
        self::assertTrue(\trainer_password_verify('correct horse battery staple', $hash));
        self::assertFalse(\trainer_password_verify('wrong password', $hash));
    }

    public function testDetectsAndVerifiesArgonHashWhenAvailable(): void
    {
        if (!defined('PASSWORD_ARGON2ID')) {
            self::markTestSkipped('Argon2id is not available in this PHP build.');
        }

        $hash = password_hash('argon secret', PASSWORD_ARGON2ID);

        self::assertTrue(\trainer_password_is_modern_hash($hash));
        self::assertTrue(\trainer_password_verify('argon secret', $hash));
        self::assertFalse(\trainer_password_verify('wrong password', $hash));
    }

    public function testLegacyPlaintextPasswordIsNeverAccepted(): void
    {
        self::assertFalse(\trainer_password_is_modern_hash('Legacy-Secret-123'));
        self::assertFalse(\trainer_password_verify('Legacy-Secret-123', 'Legacy-Secret-123'));
        self::assertFalse(\trainer_password_verify('legacy-secret-123', 'Legacy-Secret-123'));
        self::assertFalse(\trainer_password_verify('', ''));
    }

    public function testMalformedHashPrefixIsNotTreatedAsModernHash(): void
    {
        self::assertFalse(\trainer_password_is_modern_hash('$2y$not-a-valid-hash'));
    }

    public function testFreshDefaultHashDoesNotNeedRehash(): void
    {
        $hash = \trainer_password_hash('fresh password');

        self::assertFalse(\trainer_password_needs_rehash($hash));
    }

    public function testLegacyAndOutdatedHashesNeedRehash(): void
    {
        $lowCostBcrypt = password_hash('old password', PASSWORD_BCRYPT, ['cost' => 4]);

        self::assertTrue(\trainer_password_needs_rehash('legacy plaintext'));
        self::assertTrue(\trainer_password_needs_rehash($lowCostBcrypt));
    }

    public function testUniqueCandidateSelectionDoesNotDependOnDatabaseRowOrder(): void
    {
        $first = ['id' => 10, 'heslo' => password_hash('first secret', PASSWORD_BCRYPT)];
        $second = ['id' => 20, 'heslo' => password_hash('second secret', PASSWORD_BCRYPT)];

        self::assertSame(20, \trainer_password_unique_match([$first, $second], 'second secret')['id']);
        self::assertSame(10, \trainer_password_unique_match([$second, $first], 'first secret')['id']);
        self::assertNull(\trainer_password_unique_match([$first, $second], 'wrong secret'));
    }

    public function testMultipleMatchingCandidatesFailClosed(): void
    {
        $sharedPassword = 'same secret';
        $candidates = [
            ['id' => 10, 'heslo' => password_hash($sharedPassword, PASSWORD_BCRYPT)],
            ['id' => 20, 'heslo' => password_hash($sharedPassword, PASSWORD_BCRYPT)],
        ];

        self::assertNull(\trainer_password_unique_match($candidates, $sharedPassword));
    }
}
