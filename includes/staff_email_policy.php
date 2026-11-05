<?php

declare(strict_types=1);

/** Legacy fixtures can omit the new column; only the migration/import can create a restricted account. */
function staffAccountHasTechnicalEmail(PDO $pdo, int $trainerId, bool $lock = false): bool
{
    $suffix = $lock && $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
    $query = $pdo->prepare('SELECT * FROM treneri WHERE id=?' . $suffix);
    $query->execute([$trainerId]);
    $row = $query->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new DomainException('Pracovní účet nebyl nalezen.');
    }
    return (int)($row['technical_email'] ?? 0) === 1;
}
