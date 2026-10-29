<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/ProgramCapacityBaseline.php';
require __DIR__ . '/ProgramCapacityProfile.php';
