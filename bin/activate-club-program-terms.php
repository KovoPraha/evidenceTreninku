<?php
declare(strict_types=1);

// APP_ROOT and APP_HOST select the installation. Without the exact confirmation
// variable this command is SELECT-only; with it the same plan is applied.
function clubProgramTermsActivationMain(): void
{
    if (PHP_SAPI !== 'cli') throw new RuntimeException('Pouze CLI.');
    $root = realpath((string)getenv('APP_ROOT'));
    if ($root === false || !is_file($root . '/config.php')) throw new RuntimeException('APP_ROOT musí ukazovat na instalaci s config.php.');
    $host = strtolower(trim((string)getenv('APP_HOST')));
    if (!in_array($host, ['localhost','127.0.0.1','kis.kovopraha.cz'], true)) {
        throw new RuntimeException('APP_HOST musí označovat lokální nebo produkční KIS instalaci.');
    }
    $apply = (string)getenv('CLUB_PROGRAM_TERMS_CONFIRM') === 'AKTIVOVAT-KROUZKY-2026-27';
    $_SERVER['HTTP_HOST'] = $host;
    $_SERVER['SERVER_NAME'] = $host;
    require $root . '/config.php';
    require_once $root . '/includes/club_program_terms_activation.php';
    foreach (['DB_HOST','DB_NAME','DB_USER','DB_PASS'] as $constant) {
        if (!defined($constant)) throw new RuntimeException('Neúplná konfigurace databáze.');
    }
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
         PDO::ATTR_EMULATE_PREPARES => false]
    );
    $actorId = (int)$pdo->query("SELECT id FROM treneri WHERE aktivni=1 AND role='admin' ORDER BY id LIMIT 1")->fetchColumn();
    if ($actorId < 1) throw new RuntimeException('Chybí aktivní administrátor pro audit změny.');
    $result = clubProgramTermsActivateWorkingVersions($pdo, $actorId, $apply);
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    try { clubProgramTermsActivationMain(); }
    catch (Throwable $e) { fwrite(STDERR, 'Aktivace klubových podmínek selhala: ' . $e->getMessage() . PHP_EOL); exit(1); }
}
