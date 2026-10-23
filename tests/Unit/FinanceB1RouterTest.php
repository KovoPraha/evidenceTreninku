<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__, 2) . '/bin/finance-b1-router.php';
require_once dirname(__DIR__, 2) . '/includes/localhost_acceptance_hub.php';

final class FinanceB1RouterTest extends TestCase
{
    public function testBoundLocalCliServerGetsMissingAddressWithoutChangingHubGuard(): void
    {
        $server = ['SERVER_NAME'=>'127.0.0.1','SERVER_PORT'=>'18086','REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:18086'];
        self::assertFalse(localhostAcceptanceRequestIsAllowed($server, 'localhost'));
        $normalized = financeB1NormalizeServerAddress($server, 'cli-server');
        self::assertSame('127.0.0.1', $normalized['SERVER_ADDR']);
        self::assertTrue(localhostAcceptanceRequestIsAllowed($normalized, 'localhost'));
        self::assertFalse(localhostAcceptanceRequestIsAllowed($normalized, 'kis.kovopraha.cz'));
    }

    public function testServerNormalizationRejectsNonlocalOrIncompleteRuntimeAndNeverReplacesAddress(): void
    {
        $server = ['SERVER_NAME'=>'127.0.0.1','SERVER_PORT'=>'18086','REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:18086'];
        foreach ([['SERVER_NAME'=>'0.0.0.0'],['SERVER_NAME'=>'kis.kovopraha.cz'],['SERVER_NAME'=>'localhost'],['SERVER_PORT'=>'80'],['REMOTE_ADDR'=>'192.0.2.1'],['HTTP_HOST'=>'kis.kovopraha.cz'],['SERVER_ADDR'=>'192.0.2.1'],['SERVER_ADDR'=>'']] as $change) {
            $input = array_replace($server, $change);
            self::assertSame($input, financeB1NormalizeServerAddress($input, 'cli-server'));
            self::assertFalse(localhostAcceptanceRequestIsAllowed(financeB1NormalizeServerAddress($input, 'cli-server'), 'localhost'));
        }
        foreach (array_keys($server) as $missing) {
            $input = $server;
            unset($input[$missing]);
            self::assertSame($input, financeB1NormalizeServerAddress($input, 'cli-server'));
        }
        foreach (['cli','apache2handler','fpm-fcgi'] as $sapi) {
            self::assertSame($server, financeB1NormalizeServerAddress($server, $sapi));
        }
        $input = array_replace($server, ['REMOTE_ADDR'=>'192.0.2.1','HTTP_X_FORWARDED_FOR'=>'127.0.0.1','HTTP_X_FORWARDED_HOST'=>'127.0.0.1:18086']);
        self::assertSame($input, financeB1NormalizeServerAddress($input, 'cli-server'));
    }

    public function testLocalPublicLoginRemainsReachable(): void
    {
        self::assertSame(realpath(dirname(__DIR__, 2) . '/login.php'), financeB1PublicPath('/login.php?next=%2F', dirname(__DIR__, 2)));
    }

    public function testPrivatePathsAndWindowsAliasesNeverReachBuiltInServer(): void
    {
        foreach (['/.git','/.git/config','/%2egit/config','/database/local-demo-schema.sql','/docs/CURRENT_STATE.md','/vendor/autoload.php','/includes/auth.php','/config.php','/db.php','/csrf_helper.php','/config.php/x','/bin/prepare-club-local-review.php','/tests/Support/FinanceB1HttpAcceptance.php','/uploads/uctenky/a.pdf','/nahrane_zavody/results/a.pdf','/stories/a.php','/uploads/a.svg','/login.php::$DATA','/login.php.','/login.php%20','/a/../login.php','/%252e%252e/config.php','/..%5cconfig.php','/login.php%00','/not-present.php','//config.php'] as $path) {
            self::assertNull(financeB1PublicPath($path, dirname(__DIR__, 2)), $path);
        }
    }

    public function testOldReportDirectoryIsAlwaysPrivate(): void
    {
        self::assertNull(financeB1PublicPath('/reports/weekly/2026/report.pdf', dirname(__DIR__, 2)));
    }
}
