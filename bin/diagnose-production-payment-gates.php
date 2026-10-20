<?php
declare(strict_types=1);

// The workflow copies this read-only probe to the production host. It never
// opens a database connection or contacts a payment provider.
if (PHP_SAPI !== 'cli' || getenv('APP_HOST') !== 'kis.kovopraha.cz') {
    fwrite(STDERR, "Invalid diagnostic target.\n");
    exit(2);
}

$root = realpath((string) getenv('KIS_PAYMENT_DIAGNOSTIC_ROOT'));
if ($root === false || !is_file($root . '/config.php')) {
    fwrite(STDERR, "Production configuration unavailable.\n");
    exit(2);
}

// A production config can contain credentials. Never print its output or an
// exception message, including on a failed probe.
ob_start();
try {
    require $root . '/config.php';
} catch (Throwable $exception) {
    ob_end_clean();
    fwrite(STDERR, "Production configuration could not be loaded.\n");
    exit(2);
}
ob_end_clean();

if (!defined('JE_LOKALNE') || JE_LOKALNE !== false
    || !defined('APP_BASE_URL') || APP_BASE_URL !== 'https://kis.kovopraha.cz') {
    fwrite(STDERR, "Production identity mismatch.\n");
    exit(2);
}

// Fio's worker explicitly gives environment variables precedence over
// constants. Stripe and SumUp gateways read their enabled constants.
$fioImportOverride = getenv('FIO_IMPORT_ENABLED');
$fioAutoOverride = getenv('FIO_AUTO_CONFIRM_ENABLED');
$flags = [
    'fio_import_enabled' => $fioImportOverride !== false
        ? $fioImportOverride === '1'
        : (defined('FIO_IMPORT_ENABLED') && FIO_IMPORT_ENABLED === true),
    'fio_auto_confirm_enabled' => $fioAutoOverride !== false
        ? $fioAutoOverride === '1'
        : (defined('FIO_AUTO_CONFIRM_ENABLED') && FIO_AUTO_CONFIRM_ENABLED === true),
    'stripe_enabled' => defined('STRIPE_ENABLED') && STRIPE_ENABLED === true,
    'sumup_enabled' => defined('SUMUP_ENABLED') && SUMUP_ENABLED === true,
];

echo json_encode([
    'ok' => true,
    'target' => 'kis.kovopraha.cz',
    ...$flags,
    'all_disabled' => !in_array(true, $flags, true),
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), "\n";

if (in_array(true, $flags, true)) exit(3);
