<?php
declare(strict_types=1);

namespace Tests\Unit;

use FilesystemIterator;
use RecursiveCallbackFilterIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class GlobalFunctionAvailabilityTest extends TestCase
{
    private const EXCLUDED_DIRECTORIES = [
        '.agents',
        '.git',
        '.phpunit.cache',
        'docs',
        'node_modules',
        'var',
        'vendor',
    ];

    public function testEveryStaticallyCalledGlobalFunctionIsAvailableOrDeclaredByTheApplication(): void
    {
        $files = $this->firstPartyPhpFiles();
        $declared = [];
        $calls = [];

        foreach ($files as $file) {
            $tokens = token_get_all((string) file_get_contents($file), TOKEN_PARSE);
            foreach ($this->declaredFunctions($tokens) as $function) {
                $declared[strtolower($function)] = true;
            }
            foreach ($this->globalFunctionCalls($tokens) as $function => $line) {
                $calls[strtolower($function)][] = $this->relativePath($file) . ':' . $line;
            }
        }

        $missing = [];
        foreach ($calls as $function => $locations) {
            if (!isset($declared[$function]) && !function_exists($function)) {
                $missing[] = $function . ' called at ' . implode(', ', array_unique($locations));
            }
        }

        sort($missing);
        self::assertSame([], $missing, "Unavailable global function calls:\n" . implode("\n", $missing));
    }

    public function testScannerFindsGlobalCallsWithoutMistakingMethodsConstructorsOrAttributesForFunctions(): void
    {
        $source = <<<'PHP'
<?php
#[DataProvider('rows')]
function declaredHelper(): void {}
declaredHelper();
definitely_missing_f1_guard();
$object->method();
ExampleClass::method();
new ExampleClass();
PHP;

        $calls = $this->globalFunctionCalls(token_get_all($source, TOKEN_PARSE));

        self::assertArrayHasKey('declaredHelper', $calls);
        self::assertArrayHasKey('definitely_missing_f1_guard', $calls);
        self::assertArrayNotHasKey('DataProvider', $calls);
        self::assertArrayNotHasKey('method', $calls);
        self::assertArrayNotHasKey('ExampleClass', $calls);
    }

    /** @return list<string> */
    private function firstPartyPhpFiles(): array
    {
        $root = dirname(__DIR__, 2);
        $directory = new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS);
        $filter = new RecursiveCallbackFilterIterator(
            $directory,
            static fn (SplFileInfo $entry): bool => !$entry->isDir()
                || !in_array($entry->getFilename(), self::EXCLUDED_DIRECTORIES, true)
        );
        $iterator = new RecursiveIteratorIterator($filter, RecursiveIteratorIterator::LEAVES_ONLY);
        $files = [];

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
                continue;
            }
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            $topDirectory = explode('/', $relative, 2)[0];
            if (in_array($topDirectory, self::EXCLUDED_DIRECTORIES, true)) {
                continue;
            }
            $files[] = $file->getPathname();
        }

        sort($files);
        self::assertNotEmpty($files);
        return $files;
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens
     *  @return list<string>
     */
    private function declaredFunctions(array $tokens): array
    {
        $declared = [];
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || $token[0] !== T_FUNCTION) {
                continue;
            }
            for ($cursor = $index + 1, $count = count($tokens); $cursor < $count; $cursor++) {
                $candidate = $tokens[$cursor];
                if (is_array($candidate) && in_array($candidate[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }
                if ($candidate === '&') {
                    continue;
                }
                if (is_array($candidate) && $candidate[0] === T_STRING) {
                    $declared[] = $candidate[1];
                }
                break;
            }
        }
        return $declared;
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens
     *  @return array<string,int>
     */
    private function globalFunctionCalls(array $tokens): array
    {
        $calls = [];
        $attributeDepth = 0;
        foreach ($tokens as $index => $token) {
            if (is_array($token) && $token[0] === T_ATTRIBUTE) {
                $attributeDepth = 1;
                continue;
            }
            if ($attributeDepth > 0) {
                if ($token === '[') {
                    $attributeDepth++;
                } elseif ($token === ']') {
                    $attributeDepth--;
                }
                continue;
            }
            if (!is_array($token) || $token[0] !== T_STRING || $this->nextSignificantToken($tokens, $index) !== '(') {
                continue;
            }

            $previous = $this->previousSignificantToken($tokens, $index);
            $previousId = is_array($previous) ? $previous[0] : null;
            if (in_array($previousId, [T_FUNCTION, T_NEW, T_OBJECT_OPERATOR, T_DOUBLE_COLON], true)
                || (defined('T_NULLSAFE_OBJECT_OPERATOR') && $previousId === T_NULLSAFE_OBJECT_OPERATOR)
            ) {
                continue;
            }

            $calls[$token[1]] = $token[2];
        }
        return $calls;
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function previousSignificantToken(array $tokens, int $index): array|string|null
    {
        for ($cursor = $index - 1; $cursor >= 0; $cursor--) {
            if (!$this->isIgnorable($tokens[$cursor])) {
                return $tokens[$cursor];
            }
        }
        return null;
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function nextSignificantToken(array $tokens, int $index): array|string|null
    {
        for ($cursor = $index + 1, $count = count($tokens); $cursor < $count; $cursor++) {
            if (!$this->isIgnorable($tokens[$cursor])) {
                return $tokens[$cursor];
            }
        }
        return null;
    }

    private function isIgnorable(array|string $token): bool
    {
        return is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);
    }

    private function relativePath(string $path): string
    {
        return str_replace('\\', '/', substr($path, strlen(dirname(__DIR__, 2)) + 1));
    }
}
