<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/Support/CheckoutAssetsComparison.php';

final class CheckoutAssetsComparisonTest extends TestCase
{
    public function testOnlyTheExactReviewedTagIsIgnored(): void
    {
        $tag = '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"'
            . ' integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"'
            . "\ncrossorigin=\"anonymous\">";
        $local = '<link href="/assets/vendor/bootstrap/bootstrap.min.css?v=' . str_repeat('a', 64) . '" rel="stylesheet">';
        $data = '<main>420 CZK, stock=3</main><input name="term_version[member_data_notice]" value="v1">'
            . '<input name="program_term_version[program_consent]" value="p1"><input name="registration_acknowledged" value="1">'
            . '<input name="csc_birth_number_sharing" value="0"><script>const amount=420;</script>';
        self::assertSame($local . $data, \checkoutAssetsComparisonHtml($tag . $local . $data));
        self::assertSame('prefix\n' . $local . $data, \checkoutAssetsComparisonHtml('prefix\n' . $tag . $local . $data));
        foreach ([$tag . $tag . $local, $tag, $tag . $local . $local,
            str_replace('sha384-QWT', 'sha384-BAD', $tag) . $local,
            str_replace('5.3.3', '5.3.4', $tag) . $local,
            str_replace("\ncrossorigin", ' crossorigin', $tag) . $local,
            $tag . str_replace(str_repeat('a', 64), str_repeat('a', 63), $local)] as $negative) {
            self::assertSame($negative, \checkoutAssetsComparisonHtml($negative));
        }
        foreach ([['420', '421'], ['CZK', 'EUR'], ['stock=3', 'stock=0'], ['value="v1"', 'value="v2"'],
            ['value="p1"', 'value="p2"'], ['value="1"', 'value="0"'], ['sharing" value="0"', 'sharing" value="1"'],
            ['const amount=420', 'const amount=0']] as [$from, $to]) {
            self::assertNotSame($local . $data, \checkoutAssetsComparisonHtml($tag . $local . str_replace($from, $to, $data)));
        }
    }
}
