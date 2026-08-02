<?php
declare(strict_types=1);

// Browser backups and URL tokens are intentionally disabled. bin/.htaccess is
// the first barrier; this check keeps the endpoint closed even when Apache
// overrides are unavailable or ignored by the hosting provider.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/db-backup.php';
