<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/bar/transfer_package.php';
require_once dirname(__DIR__, 2) . '/bin/bar-cutover-prepare.php';

final class BarSourceAccountTest extends TestCase
{
    private string $root;
    private array $user;
    private string|false $oldReplacement;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/bar-source-account-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0700);
        $this->oldReplacement = getenv('BAR_LUCKA_REPLACE_PASSWORD');
        putenv('BAR_LUCKA_REPLACE_PASSWORD');
        $this->user = ['id' => 77, 'jmeno' => 'LuCkA', 'role' => 'admin', 'schvalen' => 1,
            'heslo_hash' => password_hash('LOCALHOST-only-test', PASSWORD_DEFAULT)];
    }

    protected function tearDown(): void
    {
        foreach (new \FilesystemIterator($this->root) as $file) {
            unlink($file->getPathname());
        }
        rmdir($this->root);
        putenv($this->oldReplacement === false ? 'BAR_LUCKA_REPLACE_PASSWORD' : 'BAR_LUCKA_REPLACE_PASSWORD=' . $this->oldReplacement);
        unset($GLOBALS['bar_source_account_meta']);
    }

    public function testOnlyNamedApprovedIdentityIsCanonicalizedAndOldAdminRightsAreDropped(): void
    {
        $original = $this->user;
        $value = \barCutoverPrepareSourceLucka($this->user, $this->root, '123');
        self::assertSame('lucka', $value['jmeno']);
        self::assertSame('prodejce', $value['role']);
        self::assertSame($original['heslo_hash'], $value['heslo_hash']);
        self::assertSame($original, $this->user);
        self::assertTrue($GLOBALS['bar_source_account_meta']['source_password_preserved']);
        self::assertFalse($GLOBALS['bar_source_account_meta']['password_replaced']);
        self::assertCount(0, glob($this->root . '/*'));
    }

    public static function invalidAccounts(): array
    {
        return [['jmeno', 'another'], ['jmeno', 'lucka2'], ['schvalen', 0], ['role', 'unknown']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidAccounts')]
    public function testAnotherIdentityOrUnapprovedAccountIsNotActivated(string $key, mixed $value): void
    {
        $this->user[$key] = $value;
        $this->expectException(\InvalidArgumentException::class);
        \barCutoverPrepareSourceLucka($this->user, $this->root, '123');
    }

    public function testUnsupportedCredentialFailsWithoutExplicitReplacementAndWithoutLeakingIt(): void
    {
        $this->user['heslo_hash'] = 'LOCALHOST-private-legacy-value';
        try {
            \barCutoverPrepareSourceLucka($this->user, $this->root, '123');
            self::fail('Unsupported credential accepted.');
        } catch (\InvalidArgumentException $error) {
            self::assertStringNotContainsString($this->user['heslo_hash'], $error->getMessage());
            self::assertStringNotContainsString($this->user['heslo_hash'], json_encode($GLOBALS['bar_source_account_meta'], JSON_THROW_ON_ERROR));
            self::assertCount(0, glob($this->root . '/*'));
        }
    }

    public function testReplacementGeneratesOnceAndLaterChecksRequireNoPlaintext(): void
    {
        $this->user['heslo_hash'] = 'LOCALHOST-private-legacy-value';
        putenv('BAR_LUCKA_REPLACE_PASSWORD=1');
        $value = \barCutoverPrepareSourceLucka($this->user, $this->root, '123');
        $delivery = json_decode((string)file_get_contents($this->root . '/bar-transfer-credential-123.delivery.json'), true, 8, JSON_THROW_ON_ERROR);
        self::assertTrue(password_verify($delivery['password'], $value['heslo_hash']));
        self::assertTrue($GLOBALS['bar_source_account_meta']['password_replaced']);
        self::assertFalse($GLOBALS['bar_source_account_meta']['source_password_preserved']);
        self::assertStringNotContainsString($delivery['password'], json_encode($GLOBALS['bar_source_account_meta'], JSON_THROW_ON_ERROR));
        unlink($this->root . '/bar-transfer-credential-123.delivery.json');
        putenv('BAR_LUCKA_REPLACE_PASSWORD');
        self::assertSame($value, \barCutoverPrepareSourceLucka($this->user, $this->root, '123'));
        $this->user['heslo_hash'] = 'LOCALHOST-changed-source';
        $this->expectException(\RuntimeException::class);
        \barCutoverPrepareSourceLucka($this->user, $this->root, '123');
    }
}
