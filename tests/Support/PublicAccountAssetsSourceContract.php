<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$root = dirname(__DIR__, 2);
$base = '01b2ca386a221f34ed6300148192c9af2cf4e7d9';
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

$report = [];
foreach (['prihlaseni.php', 'registrace.php', 'sportovec_prihlaseni.php', 'zapomenute_heslo.php'] as $name) {
    $path = 'booking/' . $name;
    $process = proc_open(['git', 'show', $base . ':' . $path], [1 => ['pipe', 'w'], 2 => STDERR], $pipes, $root);
    if (!is_resource($process)) {
        throw new RuntimeException('Canonical source unavailable.');
    }
    $before = str_replace("\r\n", "\n", (string) stream_get_contents($pipes[1]));
    fclose($pipes[1]);
    if (proc_close($process) !== 0) {
        throw new RuntimeException('Cannot read exact baseline.');
    }
    $after = str_replace("\r\n", "\n", (string) file_get_contents($root . '/' . $path));
    $tag = '~^[ \t]*<link href="https://cdn\.jsdelivr\.net/npm/bootstrap@5\.3\.3/dist/css/bootstrap\.min\.css" rel="stylesheet"\s+'
        . 'integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW\+ALEwIH"\s+'
        . 'crossorigin="anonymous">[ \t]*\n~m';
    $expected = preg_replace($tag, '', $before, -1, $count);
    if ($count !== 1) {
        throw new RuntimeException('Exactly one known duplicate required.');
    }
    $guards = $name === 'registrace.php' ? [
        "if (!\$jmeno)    \$errors[] = 'Zadejte jméno.';",
        "if (!\$prijmeni) \$errors[] = 'Zadejte příjmení.';",
        "if (!filter_var(\$email, FILTER_VALIDATE_EMAIL)) \$errors[] = 'Neplatná emailová adresa.';",
        "if (\$heslo !== \$heslo2) \$errors[] = 'Hesla se neshodují.';",
    ] : [];
    foreach ($guards as $guard) {
        if (substr_count($expected, $guard) !== 1) {
            throw new RuntimeException('Unexpected pre-existing guard.');
        }
        $offset = strpos($guard, '$errors');
        $replacement = substr($guard, 0, $offset) . '{ ' . substr($guard, $offset) . ' }';
        $expected = str_replace($guard, $replacement, $expected);
    }
    if ($signature($expected) !== $signature($after)) {
        throw new RuntimeException('PHP tokens or literal HTML changed beyond exact CSS deletion/guard braces: ' . $path);
    }
    $report[$path] = ['source_sha256' => hash('sha256', $after), 'literal_html_except_one_tag_equal' => true,
        'php_tokens_except_listed_guard_braces_equal' => true, 'guard_braces_count' => count($guards)];
}
echo json_encode(['baseline_sha' => $base, 'complete' => true, 'routes' => $report], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT), "\n";
