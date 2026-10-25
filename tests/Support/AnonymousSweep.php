<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/AnonymousRouteInventory.php';
$root = dirname(__DIR__, 2);
$routes = anonymousEntryPoints($root);
$public = anonymousPublicRoutes();
$errors = [];
$counts = ['public' => 0, 'cli' => 0, 'protected' => 0, 'errors' => 0];
foreach ($public as $route => $reason) {
    if (!in_array($route, $routes, true) || trim($reason) === '') {
        $errors[] = 'Stale/unexplained public exception: ' . $route;
    }
}

// Copy source into a disposable tree. No real config, database, uploads or sessions.
$sandbox = sys_get_temp_dir() . '/evidence-anonymous-' . bin2hex(random_bytes(8));
mkdir($sandbox, 0700);
$server = null;
try {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        if (!preg_match('~^(?:includes/.*|[^/]+|(?:auth|booking|auditlog|jizdy|servis|uctenky|udalosti|vozidla|zatezove_testy)/.*)\.php$~', $relative)
            || $relative === 'config.php') {
            continue;
        }
        $target = $sandbox . '/' . $relative;
        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0700, true);
        }
        copy($file->getPathname(), $target);
    }
    // Include every inventoried future entry point, including new subdirectories.
    foreach ($routes as $route) {
        if (!is_dir(dirname($sandbox . '/' . $route))) {
            mkdir(dirname($sandbox . '/' . $route), 0700, true);
        }
        copy($root . '/' . $route, $sandbox . '/' . $route);
    }
    mkdir($sandbox . '/vendor');
    file_put_contents($sandbox . '/vendor/autoload.php', '<?php return require ' . var_export($root . '/vendor/autoload.php', true) . ';');
    mkdir($sandbox . '/sessions');
    file_put_contents($sandbox . '/config.php', "<?php\ndefine('DB_HOST', 'isolated'); define('DB_NAME', 'isolated');"
        . "define('DB_USER', ''); define('DB_PASS', ''); define('JE_LOKALNE', false); define('VELOCOTA_INTEGRATION', false);\n");
    $db = (string) file_get_contents($sandbox . '/db.php');
    $start = strpos($db, 'try {');
    $end = strpos($db, '// Webovy request');
    if ($start === false || $end === false || $end <= $start) {
        throw new RuntimeException('Database bootstrap changed: review isolated connection seam.');
    }
    // Keep session validation and workspace enforcement from the real bootstrap.
    $db = substr($db, 0, $start) . '$pdo = new PDO("sqlite::memory:");'
        . '$pdo->exec("CREATE TABLE treneri (id INTEGER PRIMARY KEY, aktivni INTEGER)");'
        . "\n" . substr($db, $end);
    file_put_contents($sandbox . '/db.php', $db);
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    if ($socket === false) {
        throw new RuntimeException($error);
    }
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $server = proc_open([
        PHP_BINARY, '-d', 'session.save_path=' . $sandbox . '/sessions',
        '-d', 'display_errors=0', '-d', 'log_errors=1', '-d', 'disable_functions=mail',
        '-S', $address, '-t', $sandbox,
    ], [0 => ['pipe', 'r'], 1 => ['file', $sandbox . '/server.log', 'a'], 2 => ['file', $sandbox . '/server.log', 'a']], $pipes, $sandbox);
    if (!is_resource($server)) {
        throw new RuntimeException('Cannot start isolated PHP HTTP server.');
    }
    fclose($pipes[0]);
    for ($attempt = 0; $attempt < 100; $attempt++) {
        $ready = @stream_socket_client('tcp://' . $address, $errno, $error, 0.1);
        if ($ready !== false) {
            fclose($ready);
            break;
        }
        usleep(50000);
    }
    foreach ($routes as $route) {
        if (isset($public[$route])) {
            $counts['public']++;
            continue;
        }
        $source = (string) file_get_contents($root . '/' . $route);
        $cli = preg_match('/PHP_SAPI\s*!==?\s*[\'"]cli[\'"]/', $source) === 1;
        $counts[$cli ? 'cli' : 'protected']++;
        foreach (['GET', 'POST'] as $method) {
            $requestRoute = $route;
            $requestMethod = $method;
            for ($redirect = 0; $redirect < 4; $redirect++) {
            $curl = curl_init('http://' . $address . '/' . $requestRoute);
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 15, CURLOPT_PROXY => '',
                CURLOPT_CUSTOMREQUEST => $requestMethod,
            ]);
            $response = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            $headerSize = (int) curl_getinfo($curl, CURLINFO_HEADER_SIZE);
            $headers = substr((string) $response, 0, $headerSize);
            curl_close($curl);
            $denied = in_array($status, [401, 403, 404, 405], true);
            $login = in_array($status, [302, 303], true)
                && preg_match('~^Location:\s*(?:https?://[^/]+)?[^\r\n]*\b(?:login|prihlaseni|sportovec_prihlaseni)\.php(?:[?\s]|$)~mi', $headers);
            if ($denied || $login || !in_array($status, [301, 302, 303, 307, 308], true)) {
                break;
            }
            // Legacy aliases are checked through to their canonical route, never exempted.
            if (!preg_match('~^Location:\s*([^\r\n]+)~mi', $headers, $location)
                || str_contains($location[1], ':') || str_starts_with($location[1], '//')) {
                break;
            }
            $target = parse_url($location[1], PHP_URL_PATH);
            $parts = [];
            foreach (explode('/', str_starts_with($target, '/') ? $target : dirname($requestRoute) . '/' . $target) as $part) {
                if ($part === '..') {
                    array_pop($parts);
                } elseif ($part !== '' && $part !== '.') {
                    $parts[] = $part;
                }
            }
            $requestRoute = implode('/', $parts);
            $requestMethod = 'GET';
            }
            if (!$denied && !$login) {
                $errors[] = $method . ' ' . $route . ': HTTP ' . $status;
            }
        }
    }
} finally {
    if (is_resource($server)) {
        proc_terminate($server);
        proc_close($server);
    }
    // Only the random directory created above is removed; never the checkout.
    $children = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sandbox, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($children as $child) {
        $child->isDir() ? rmdir($child->getPathname()) : unlink($child->getPathname());
    }
    rmdir($sandbox);
}
$counts['errors'] = count($errors);
echo json_encode(['counts' => $counts, 'failures' => $errors], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($errors === [] ? 0 : 1);
