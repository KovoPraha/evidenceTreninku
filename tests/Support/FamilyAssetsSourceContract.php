<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$root = dirname(__DIR__, 2);
$base = '4d1e99412d83c7bb70da77694f32e31b93ff8284';
$signature = static function (string $source): array {
    $result = [];
    foreach (token_get_all($source) as $token) {
        if (is_array($token)) {
            if (in_array($token[0], [T_OPEN_TAG, T_CLOSE_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $type = token_name($token[0]);
            $last = array_key_last($result);
            if ($type === 'T_INLINE_HTML' && $last !== null && is_array($result[$last]) && $result[$last][0] === 'T_INLINE_HTML') {
                $result[$last][1] .= $token[1];
                continue;
            }
            $result[] = [$type, $token[1]];
        } else {
            $result[] = $token;
        }
    }
    return $result;
};

// Pin every permitted brace insertion against the immutable baseline.
$insertions = json_decode('{
    "booking/moje_osoby.php": [],
    "booking/moje_objednavky.php": [],
    "booking/moje_programy.php": [
        [
            125,
            [
                "{"
            ]
        ],
        [
            131,
            [
                "}"
            ]
        ],
        [
            132,
            [
                "{"
            ]
        ],
        [
            223,
            [
                "}"
            ]
        ]
    ],
    "booking/moje_rezervace.php": []
}', true, 512, JSON_THROW_ON_ERROR);
$tag = '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"'
        . ' integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">';
$routes = [];
foreach ($insertions as $path => $changes) {
    $process = proc_open(['git', 'show', $base . ':' . $path], [1 => ['pipe', 'w'], 2 => STDERR], $pipes, $root);
    if (!is_resource($process)) {
        throw new RuntimeException('Canonical source unavailable.');
    }
    $before = str_replace("\r\n", "\n", (string) stream_get_contents($pipes[1]));
    fclose($pipes[1]);
    if (proc_close($process) !== 0 || substr_count($before, $tag) !== 1) {
        throw new RuntimeException('Exact baseline duplicate required.');
    }
    $tokens = $signature(str_replace($tag, '', $before));
    foreach (array_reverse($changes) as [$position, $braces]) {
        array_splice($tokens, $position, 0, $braces);
    }
    $after = str_replace("\r\n", "\n", (string) file_get_contents($root . '/' . $path));
    if ($tokens !== $signature($after)) {
        throw new RuntimeException('Unexpected PHP token or literal HTML change: ' . $path);
    }
    $routes[$path] = ['source_sha256' => hash('sha256', $after), 'whole_literal_html_except_exact_duplicate_equal' => true,
        'php_tokens_except_exact_listed_guard_braces_equal' => true, 'brace_insertions' => $changes];
}
echo json_encode(['complete' => true, 'baseline_sha' => $base, 'routes' => $routes], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), "\n";
