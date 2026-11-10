<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$root = dirname(__DIR__, 2);
$base = getenv('WORKSPACE_ASSETS_BASE_SHA') ?: 'faed76c0ee94d210c99f67a20a92604da8f61206';
if (!preg_match('/^[a-f0-9]{40}$/D', $base)) {
    throw new RuntimeException('An exact source baseline is required.');
}
$process = proc_open(['git', 'show', $base . ':pracovni_pozice.php'], [1 => ['pipe', 'w'], 2 => STDERR], $pipes, $root);
if (!is_resource($process)) {
    throw new RuntimeException('Cannot read canonical source.');
}
$before = (string) stream_get_contents($pipes[1]);
fclose($pipes[1]);
if (proc_close($process) !== 0) {
    throw new RuntimeException('Cannot read canonical baseline.');
}
$before = str_replace("\r\n", "\n", $before);
$after = str_replace("\r\n", "\n", (string) file_get_contents($root . '/pracovni_pozice.php'));
$duplicate = "    <link href=\"<?=staffDashboardH(appUiUrl('assets/vendor/bootstrap/bootstrap.min.css'))?>\" rel=\"stylesheet\">\n";
if (substr_count($before, $duplicate) !== 1) {
    throw new RuntimeException('Exactly one known duplicate is required.');
}
$expected = str_replace($duplicate, '', $before);
// Required repository style: add braces to this literal pre-existing single-statement guard.
$guard = 'if ($code === $activeCode) continue;';
if (substr_count($expected, $guard) !== 1) {
    throw new RuntimeException('Unexpected legacy guard.');
}
$expected = str_replace($guard, 'if ($code === $activeCode) { continue; }', $expected);
$signature = static function (string $source): array {
    $result = [];
    foreach (token_get_all($source) as $token) {
        if (is_array($token)) {
            if (in_array($token[0], [T_OPEN_TAG, T_CLOSE_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            // Empty PHP template boundaries emit nothing. Join their adjacent literal HTML.
            $last = array_key_last($result);
            if ($token[0] === T_INLINE_HTML && $last !== null && is_array($result[$last]) && $result[$last][0] === T_INLINE_HTML) {
                $result[$last][1] .= $token[1];
                continue;
            }
            $result[] = [$token[0], $token[1]];
        } else {
            $result[] = $token;
        }
    }
    return $result;
};
if ($signature($expected) !== $signature($after)) {
    throw new RuntimeException('PHP tokens or literal emitted HTML changed beyond the CSS deletion and literal guard braces.');
}
echo json_encode([
    'baseline_sha' => $base,
    'sole_html_change' => 'known duplicate CSS link deleted',
    'php_tokens_except_literal_guard_braces_identical' => true,
    'other_literal_html_unchanged' => true,
    'formatting_has_no_output' => true,
    'source_sha256' => hash('sha256', $after),
], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT), "\n";
