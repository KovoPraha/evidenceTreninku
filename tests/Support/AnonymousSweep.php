<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/AnonymousRouteInventory.php';
require __DIR__ . '/AnonymousHttpPolicy.php';
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
foreach (array_merge(anonymousCliRoutes(), anonymousHiddenRoutes()) as $route => $reason) {
    if (!in_array($route, $routes, true)) {
        $errors[] = 'Stale CLI/hidden route: ' . $route;
    }
}

// Copy source into a disposable tree. No real config, database, uploads or sessions.
$sandbox = sys_get_temp_dir() . '/evidence-anonymous-' . bin2hex(random_bytes(8));
mkdir($sandbox, 0700);
$server = null;
try {
    foreach (anonymousTrackedPhpFiles($root) as $relative) {
        if (!preg_match('~^(?:includes/.*|[^/]+|(?:auth|booking|auditlog|jizdy|servis|uctenky|udalosti|vozidla|zatezove_testy)/.*)\.php$~', $relative)
            || $relative === 'config.php') {
            continue;
        }
        $target = $sandbox . '/' . $relative;
        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0700, true);
        }
        copy($root . '/' . $relative, $target);
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
        . '$pdo->exec("CREATE TABLE sportovci (id INTEGER PRIMARY KEY); INSERT INTO sportovci VALUES (1)");'
        . '$pdo->exec("CREATE TABLE zatezove_testy (id INTEGER PRIMARY KEY, sportovec_id INTEGER); INSERT INTO zatezove_testy VALUES (1, 1)");'
        . '$pdo->exec("CREATE TABLE zatezove_testy_soubory (id INTEGER PRIMARY KEY, test_id INTEGER, cesta TEXT, nazev TEXT, typ TEXT);'
        . 'INSERT INTO zatezove_testy_soubory VALUES (1, 1, \'private://synthetic\', \'SYNTHETIC\', \'public_img\')");'
        . "\n" . substr($db, $end);
    file_put_contents($sandbox . '/db.php', $db);
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    if ($socket === false) {
        throw new RuntimeException($error);
    }
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    // PHP's built-in server omits SERVER_ADDR. Supply only its actual loopback
    // address, so localhost-only pages reach their real identity guard too.
    file_put_contents($sandbox . '/sweep-prepend.php', '<?php $_SERVER["SERVER_ADDR"] = "127.0.0.1";');
    $server = proc_open(
        [
        PHP_BINARY, '-d', 'session.save_path=' . $sandbox . '/sessions',
        '-d', 'display_errors=0', '-d', 'log_errors=1', '-d', 'disable_functions=mail',
        '-d', 'auto_prepend_file=' . $sandbox . '/sweep-prepend.php',
        '-S', $address, '-t', $sandbox,
    ],
        [0 => ['pipe', 'r'], 1 => ['file', $sandbox . '/server.log', 'a'], 2 => ['file', $sandbox . '/server.log', 'a']],
        $pipes,
        $sandbox,
        array_merge(getenv(), ['APP_HOST' => 'localhost'])
    );
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
        $cli = isset(anonymousCliRoutes()[$route]);
        $counts[$cli ? 'cli' : 'protected']++;
        foreach (anonymousRouteQueries($route) as $query) {
            $provedDenial = false;
            foreach (['GET', 'POST'] as $method) {
                $result = anonymousProbeResponse('http://' . $address, $route . $query, $method);
                $status = $result['status'];
                $denied = $result['denied'];
                $provedDenial = $provedDenial || $denied;
                if (!$denied && $status !== 405) {
                    $errors[] = $method . ' ' . $route . $query . ': HTTP ' . $status
                        . ($result['redirectBody'] ? ' (non-empty redirect body)' : '');
                }
            }
            // Method rejection alone proves no authentication. The other method
            // must reach a login/401/403 (or an explicitly reviewed concealment).
            if (!$provedDenial) {
                $errors[] = $route . $query . ': neither GET nor POST proved access denial';
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
