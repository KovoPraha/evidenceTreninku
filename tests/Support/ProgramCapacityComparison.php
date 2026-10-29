<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__, 2) . '/docs/verification/refactoring-wave6-20261029/capacity-baseline.php';
require __DIR__ . '/ProgramCapacityProfile.php';
