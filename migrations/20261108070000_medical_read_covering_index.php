<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/medical_clearance_read_index.php';

return [
    'id' => '20261108070000_medical_read_covering_index',
    'up' => static function (PDO $pdo): void {
        MedicalClearanceReadIndex::ensure($pdo);
    },
    'verify' => static fn (PDO $pdo): bool => MedicalClearanceReadIndex::verify($pdo),
];
