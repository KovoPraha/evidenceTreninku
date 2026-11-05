<?php

declare(strict_types=1);

namespace Tests\Unit;

use DOMDocument;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/bar/bootstrap.php';

final class BarUnavailableViewTest extends TestCase
{
    protected function tearDown(): void
    {
        $_SESSION = [];
        $_FILES = [];
        http_response_code(200);
    }

    private function render(array $post): string
    {
        ob_start();
        echo 'PREVIOUS PARTIAL PRIVATE PAGE';
        \barRenderDatabaseUnavailable($post);
        return (string)ob_get_clean();
    }

    public function testUnavailablePagePreservesExactRequestTokenNestedCartAndJsonWithoutReadingBusinessData(): void
    {
        $_SESSION = ['trener_id' => 2];
        $_FILES = [];
        $json = '{"rows":[{"description":"Čaj & <script>test</script>"}]}';
        $html = $this->render(['csrf_token' => \csrf_token(), 'action' => 'commit', 'token' => str_repeat('a', 64),
            'quantities' => [11 => '3'], 'document_json' => $json]);
        self::assertSame(503, http_response_code());
        self::assertStringNotContainsString('PREVIOUS PARTIAL PRIVATE PAGE', $html);
        self::assertStringNotContainsString('<script>test</script>', $html);
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $fields = [];
        foreach ($dom->getElementsByTagName('input') as $input) {
            $fields[$input->getAttribute('name')] = $input->getAttribute('value');
        }
        self::assertSame(str_repeat('a', 64), $fields['token']);
        self::assertSame('3', $fields['quantities[11]']);
        self::assertSame($json, $fields['document_json']);
        self::assertSame($_SESSION['csrf_token'], $fields['csrf_token']);
    }

    public function testExpiredSessionOrCsrfDoesNotEchoFinancialFormBackToCaller(): void
    {
        $_SESSION = [];
        $html = $this->render(['csrf_token' => 'invalid', 'token' => 'SHOULD-NOT-BE-ECHOED', 'reference' => 'PRIVATE PAYMENT PROOF']);
        self::assertStringNotContainsString('SHOULD-NOT-BE-ECHOED', $html);
        self::assertStringNotContainsString('PRIVATE PAYMENT PROOF', $html);
        self::assertStringNotContainsString('<form', $html);
    }

    public function testUploadedFileMustBeSelectedAgainButItsRequestTokenSurvives(): void
    {
        $_SESSION = ['trener_id' => 3];
        $_FILES = ['pdf' => ['error' => UPLOAD_ERR_OK]];
        $html = $this->render(['csrf_token' => \csrf_token(), 'action' => 'upload', 'token' => str_repeat('b', 64)]);
        self::assertStringContainsString('enctype="multipart/form-data"', $html);
        self::assertStringContainsString('type="file" name="pdf"', $html);
        self::assertStringContainsString(str_repeat('b', 64), $html);
    }
}
