<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/includes/kis_sync_lib.php';

// Deterministic test-only key. Production must provide an unrelated secret in config.php.
defined('AUTH_RATE_LIMIT_PEPPER') || define(
    'AUTH_RATE_LIMIT_PEPPER',
    'test-only-auth-rate-limit-pepper-0000000000000000'
);
