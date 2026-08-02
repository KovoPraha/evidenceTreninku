<?php
declare(strict_types=1);

/**
 * Zpetne kompatibilni nazev pro read-only CLI kontrolu migraci.
 * Webovy endpoint je zamerne vypnuty: deploy pouziva pouze SSH a zadny token
 * se tak nedostane do URL, access logu ani historie prohlizece.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$argv = [__FILE__, '--check', '--json'];
require __DIR__ . '/migrate.php';
