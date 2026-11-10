<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/Support/SharedHeaderScriptComparison.php';

final class SharedHeaderScriptComparisonTest extends TestCase
{
    public function testOnlyTheExactPackagingChangeNormalizesToTheSameContext(): void
    {
        $reference = str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__) . '/Support/SharedHeaderScriptReference.php.txt'));
        $values = ['key', '/base/sw.js', '/base/', '/base/push_subscribe.php', str_repeat('a', 64),
            '/base/push_subscribe.php', str_repeat('a', 64), '/base/ajax_global_search.php', '/base/'];
        $index = -1;
        $before = preg_replace_callback('/<\?=.*?\?>/s', static function () use (&$index, $values): string {
            return $index++ < 0 ? 'same-nonce' : json_encode($values[$index - 1], JSON_THROW_ON_ERROR);
        }, $reference);
        $context = ['pushPublicKey' => 'key', 'serviceWorkerUrl' => '/base/sw.js', 'appBaseUrl' => '/base/',
            'pushEndpoint' => '/base/push_subscribe.php', 'csrfToken' => str_repeat('b', 64), 'searchUrl' => '/base/ajax_global_search.php'];
        $after = '<script type="application/json" id="appStaffHeaderConfig" nonce="same-nonce">'
            . json_encode($context, JSON_THROW_ON_ERROR) . '</script>' . "\n"
            . '<script nonce="same-nonce" src="/base/assets/staff-header.js?v=123"></script>';
        self::assertSame(sharedHeaderScriptComparisonHtml($before), sharedHeaderScriptComparisonHtml($after));
        self::assertNotSame($before, sharedHeaderScriptComparisonHtml($before));
        foreach ([
            str_replace('push_subscribe.php', 'different.php', $after),
            str_replace('nonce="same-nonce" src', 'nonce="other" src', $after),
            str_replace('src="/base/', 'src="//foreign.example/base/', $after),
            $after . $after,
            str_replace('const input    =', 'const changed =', $before),
        ] as $invalid) {
            self::assertNotSame(sharedHeaderScriptComparisonHtml($before), sharedHeaderScriptComparisonHtml($invalid));
        }
        self::assertSame('<div>unchanged</div>', sharedHeaderScriptComparisonHtml('<div>unchanged</div>'));
    }
}
