<?php
declare(strict_types=1);

/** The PHP development server does not apply Apache .htaccess rules. */
function financeB1PublicPath(string $uri, string $root): ?string
{
    $path = rawurldecode(explode('?', $uri, 2)[0]);
    if ($path === '' || $path[0] !== '/' || preg_match('/[\\\\\x00-\x1f\x7f:%]/', $path)) return null;
    foreach (explode('/', $path) as $part) {
        if ($part !== '' && ($part[0] === '.' || rtrim($part, '. ') !== $part)) return null;
    }
    if (preg_match('~^/(?:bin|database|docs|migrations|output|tests|var|vendor|includes|private|reports|node_modules)(?:/|$)~i', $path)) return null;
    if (preg_match('~^/(?:uploads/(?:uctenky|zatezove_testy|servis|temp)|nahrane_zavody/results)(?:/|$)~i', $path)) return null;
    if (preg_match('~(?:^|/)(?:config|db|csrf_helper|test|analyze_excel|analyze_uci_form)\.php(?:/|$)~i', $path)) return null;
    if (preg_match('~\.(?:sql|log|zip|bak|old|php3|ini|ps1|md|json|lock|yml|yaml|txt)$~i', $path)) return null;
    if (preg_match('~^/(?:uploads|nahrane_obrazky|nahrane_zavody|stories|loga_story)/.*\.(?:php[0-9]?|phtml|pht|phtm|phar|html?|xhtml|shtml|svg|xml|js|mjs|wasm|cgi|pl|py|sh|htaccess)$~i', $path)) return null;
    if ($path === '/') $path = '/index.php';
    $canonicalRoot = realpath($root);
    $canonicalPath = realpath($root . $path);
    if ($canonicalRoot === false || $canonicalPath === false || !is_file($canonicalPath)) return null;
    $prefix = rtrim(str_replace('\\', '/', $canonicalRoot), '/') . '/';
    $normalized = str_replace('\\', '/', $canonicalPath);
    if (strncasecmp($normalized, $prefix, strlen($prefix)) !== 0) return null;
    // Deny aliases/symlinks into forbidden trees, including inside the checkout.
    if (strcasecmp($normalized, $prefix . ltrim($path, '/')) !== 0) return null;
    return $canonicalPath;
}

if (PHP_SAPI === 'cli-server') {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($host !== '127.0.0.1:18086'
        || financeB1PublicPath($_SERVER['REQUEST_URI'] ?? '/', dirname(__DIR__)) === null) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo "Not found.\n";
        return true;
    }
    return false;
}
