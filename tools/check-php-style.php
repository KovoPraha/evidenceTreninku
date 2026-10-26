<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

chdir(dirname(__DIR__));
$base = getenv('STYLE_BASE_REF') ?: 'origin/main';
$fix = in_array('--fix', $argv, true);

function styleGit(array $arguments): string
{
    $process = proc_open(array_merge(['git'], $arguments), [1 => ['pipe', 'w'], 2 => STDERR], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start git.');
    }
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    if (proc_close($process) !== 0) {
        throw new RuntimeException('Cannot determine changed files; fetch origin/main first.');
    }

    return (string) $output;
}

$mergeBase = trim(styleGit(['merge-base', $base, 'HEAD']));
$paths = explode("\0", styleGit(['diff', '--name-only', '-z', '--no-renames', '--diff-filter=ACMRD', $mergeBase, '--', '*.php']));
$untracked = explode("\0", styleGit(['ls-files', '--others', '--exclude-standard', '-z', '--', '*.php']));
$paths = array_values(array_unique(array_filter(array_merge($paths, $untracked))));
$existing = array_fill_keys(explode("\0", styleGit(['ls-tree', '-r', '--name-only', '-z', $mergeBase, 'migrations'])), true);
$errors = [];
$files = [];
foreach ($paths as $path) {
    if (str_starts_with($path, 'vendor/') || $path === 'config.php') {
        continue;
    }
    if (isset($existing[$path])) {
        $errors[] = $path . ': existing migrations are immutable (checksums).';
        continue;
    }
    if (!is_file($path)) {
        continue;
    }
    $files[] = $path;
}

if ($files !== []) {
    $command = [PHP_BINARY, 'vendor/bin/php-cs-fixer', 'fix', '--config=.php-cs-fixer.dist.php', '--using-cache=no', '--diff'];
    if (!$fix) {
        $command[] = '--dry-run';
    }
    $process = proc_open(array_merge($command, ['--'], $files), [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes);
    if (!is_resource($process) || proc_close($process) !== 0) {
        $errors[] = 'php-cs-fixer: changed PHP files must conform to PSR-12.';
    }
}

foreach ($files as $path) {
    $source = (string) file_get_contents($path);
    foreach (preg_split('/\R/', $source) as $index => $line) {
        if (mb_strlen($line, 'UTF-8') > 200) {
            $errors[] = $path . ':' . ($index + 1) . ': line exceeds 200 characters.';
        }
    }
    $tokens = array_values(array_filter(token_get_all($source), static function ($token): bool {
        return !is_array($token) || !in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);
    }));
    foreach ($tokens as $index => $token) {
        if (!is_array($token) || !in_array($token[0], [T_IF, T_ELSEIF, T_FOREACH, T_FOR], true)) {
            continue;
        }
        $depth = 0;
        for ($next = $index + 1; $next < count($tokens); $next++) {
            if ($tokens[$next] === '(') {
                $depth++;
            } elseif ($tokens[$next] === ')' && --$depth === 0) {
                break;
            }
        }
        if (!in_array($tokens[$next + 1] ?? null, ['{', ':'], true)) {
            $errors[] = $path . ':' . $token[2] . ': control statement requires braces (or template alternative syntax).';
        }
    }
}
foreach ($errors as $error) {
    fwrite(STDERR, $error . PHP_EOL);
}
echo 'Changed PHP files: ' . count($files) . '; errors: ' . count($errors) . PHP_EOL;
exit($errors === [] ? 0 : 1);
