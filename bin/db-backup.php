<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * Escapes a value for a MySQL client option file.
 */
function mysqlOptionValue(string $value): string
{
    return '"' . str_replace(
        ["\\", "\"", "\r", "\n"],
        ["\\\\", "\\\"", "", ""],
        $value
    ) . '"';
}

$options = getopt('', ['config:', 'output:']);
$configFile = isset($options['config']) ? (string) $options['config'] : '';
$outputFile = isset($options['output']) ? (string) $options['output'] : '';

if ($configFile === '' || $outputFile === '') {
    fwrite(
        STDERR,
        "Použití: php db-backup.php --config=/cesta/config.php --output=/cesta/backup.sql.gz\n"
    );
    exit(2);
}

if (!is_file($configFile)) {
    fwrite(STDERR, "CHYBA: Konfigurační soubor neexistuje.\n");
    exit(2);
}

require_once $configFile;

foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $constant) {
    if (!defined($constant)) {
        fwrite(STDERR, "CHYBA: V config.php chybí konstanta {$constant}.\n");
        exit(2);
    }
}

if (!function_exists('proc_open')) {
    fwrite(STDERR, "CHYBA: Hosting nepovoluje PHP funkci proc_open.\n");
    exit(1);
}

if (!function_exists('gzopen')) {
    fwrite(STDERR, "CHYBA: PHP nemá rozšíření zlib pro komprimovanou zálohu.\n");
    exit(1);
}

$outputDirectory = dirname($outputFile);
if (!is_dir($outputDirectory) || !is_writable($outputDirectory)) {
    fwrite(STDERR, "CHYBA: Adresář pro zálohu není zapisovatelný.\n");
    exit(1);
}

$temporaryOptionFile = tempnam(sys_get_temp_dir(), 'evidence-db-');
$temporarySqlFile = $outputFile . '.tmp.sql';
$temporaryErrorFile = $outputFile . '.tmp.err';

if ($temporaryOptionFile === false) {
    fwrite(STDERR, "CHYBA: Nelze vytvořit dočasný konfigurační soubor.\n");
    exit(1);
}

$cleanup = static function () use (
    $temporaryOptionFile,
    $temporarySqlFile,
    $temporaryErrorFile
): void {
    foreach ([$temporaryOptionFile, $temporarySqlFile, $temporaryErrorFile] as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
};

register_shutdown_function($cleanup);

$clientConfig = "[client]\n"
    . 'host=' . mysqlOptionValue((string) DB_HOST) . "\n"
    . 'user=' . mysqlOptionValue((string) DB_USER) . "\n"
    . 'password=' . mysqlOptionValue((string) DB_PASS) . "\n";

if (defined('DB_PORT') && (int) DB_PORT > 0) {
    $clientConfig .= 'port=' . (int) DB_PORT . "\n";
}

if (file_put_contents($temporaryOptionFile, $clientConfig, LOCK_EX) === false) {
    fwrite(STDERR, "CHYBA: Nelze připravit přístup pro mysqldump.\n");
    exit(1);
}
@chmod($temporaryOptionFile, 0600);

$command = [
    'mysqldump',
    '--defaults-extra-file=' . $temporaryOptionFile,
    '--single-transaction',
    '--quick',
    '--skip-lock-tables',
    '--default-character-set=utf8mb4',
    '--result-file=' . $temporarySqlFile,
    (string) DB_NAME,
];

$process = proc_open(
    $command,
    [
        0 => ['file', '/dev/null', 'r'],
        1 => ['file', '/dev/null', 'w'],
        2 => ['file', $temporaryErrorFile, 'w'],
    ],
    $pipes
);

if (!is_resource($process)) {
    fwrite(STDERR, "CHYBA: Nepodařilo se spustit mysqldump.\n");
    exit(1);
}

$exitCode = proc_close($process);
if ($exitCode !== 0 || !is_file($temporarySqlFile) || filesize($temporarySqlFile) === 0) {
    $detail = is_file($temporaryErrorFile)
        ? trim((string) file_get_contents($temporaryErrorFile))
        : '';
    fwrite(STDERR, "CHYBA: Záloha databáze selhala"
        . ($detail !== '' ? ": {$detail}" : '.')
        . "\n");
    exit(1);
}

$source = fopen($temporarySqlFile, 'rb');
$target = gzopen($outputFile, 'wb9');

if ($source === false || $target === false) {
    if (is_resource($source)) {
        fclose($source);
    }
    if (is_resource($target)) {
        gzclose($target);
    }
    fwrite(STDERR, "CHYBA: Zálohu nelze zkomprimovat.\n");
    exit(1);
}

while (!feof($source)) {
    $chunk = fread($source, 1024 * 1024);
    if ($chunk === false || gzwrite($target, $chunk) === false) {
        fclose($source);
        gzclose($target);
        @unlink($outputFile);
        fwrite(STDERR, "CHYBA: Zápis komprimované zálohy selhal.\n");
        exit(1);
    }
}

fclose($source);
gzclose($target);
@chmod($outputFile, 0600);

if (!is_file($outputFile) || filesize($outputFile) === 0) {
    fwrite(STDERR, "CHYBA: Výsledná záloha je prázdná.\n");
    exit(1);
}

fwrite(STDOUT, "DB záloha OK: {$outputFile}\n");
