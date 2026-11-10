<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$root = dirname(__DIR__, 2);
$base = '50ce79b2e290b926a70e46f8a729682f12a7b864';
$read = static function (string $path) use ($root, $base): string {
    $process = proc_open(['git', 'show', $base . ':' . $path], [1 => ['pipe', 'w'], 2 => STDERR], $pipes, $root);
    if (!is_resource($process)) {
        throw new RuntimeException('Canonical source unavailable.');
    }
    $source = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    if (proc_close($process) !== 0) {
        throw new RuntimeException('Exact baseline unavailable.');
    }
    return str_replace("\r\n", "\n", (string) $source);
};
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

$reports = [];
foreach (['includes/ui_shell.php', 'booking/eshop.php'] as $path) {
    $before = $read($path);
    $expected = $before;
    if ($path === 'includes/ui_shell.php') {
        $guard = 'if ($rendered) return;';
        if (substr_count($expected, $guard) !== 1) {
            throw new RuntimeException('Unexpected legacy guard.');
        }
        $expected = str_replace($guard, 'if ($rendered) { return; }', $expected);
        $paths = ['bootstrapCssVersion' => 'assets/vendor/bootstrap/bootstrap.min.css',
            'bootstrapJsVersion' => 'assets/vendor/bootstrap/bootstrap.bundle.min.js',
            'iconsVersion' => 'assets/vendor/bootstrap-icons/font/bootstrap-icons.css',
            'cssVersion' => 'assets/app-ui.css', 'jsVersion' => 'assets/app-ui.js'];
        $old = '';
        $new = "    \$versions = require \$root . '/includes/ui_asset_versions.php';\n";
        foreach ($paths as $variable => $asset) {
            $old .= "    \$" . $variable . " = (string)(filemtime(\$root . '" . '/' . $asset . "') ?: '1');\n";
            $new .= "    \$" . $variable . " = \$versions['" . $asset . "'];\n";
        }
        if (substr_count($expected, $old) !== 1) {
            throw new RuntimeException('Exactly the five known metadata versions required.');
        }
        $expected = str_replace($old, $new, $expected);
    } else {
        $marker = "data-app-csp-handler=\"\n    <?=";
        if (substr_count($expected, $marker) !== 1) {
            throw new RuntimeException('Exactly the known product image marker required.');
        }
        $expected = str_replace($marker, 'data-app-csp-handler="<?=', $expected);
    }
    $after = str_replace("\r\n", "\n", (string) file_get_contents($root . '/' . $path));
    if ($signature($expected) !== $signature($after)) {
        throw new RuntimeException('Unrelated PHP or emitted HTML changed: ' . $path);
    }
    $reports[$path] = ['source_sha256' => hash('sha256', $after), 'all_other_php_tokens_and_literal_html_equal' => true];
}
$assets = [];
foreach (['assets/vendor/bootstrap/bootstrap.min.css', 'assets/vendor/bootstrap/bootstrap.bundle.min.js',
    'assets/vendor/bootstrap-icons/font/bootstrap-icons.css', 'assets/app-ui.css', 'assets/app-ui.js'] as $path) {
    $canonical = $read($path);
    if ($canonical !== file_get_contents($root . '/' . $path)) {
        throw new RuntimeException('UI payload changed: ' . $path);
    }
    $assets[$path] = hash('sha256', $canonical);
}
$versions = require $root . '/includes/ui_asset_versions.php';
if ($versions !== $assets) {
    throw new RuntimeException('Manifest does not bind the exact five baseline payloads.');
}
echo json_encode(['complete' => true, 'baseline_sha' => $base,
    'only_runtime_changes' => 'five offline content versions and trusted image marker padding; one guard brace pair',
    'routes' => $reports, 'unchanged_asset_sha256' => $assets], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT), "\n";
