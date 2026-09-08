<?php
declare(strict_types=1);

function publicListingHasTestPrefix(string $title): bool
{
    return str_starts_with(mb_strtoupper(trim($title), 'UTF-8'), 'TEST -');
}

/** @return array{email:string,name:string}|null */
function publicListingTrainer(PDO $pdo, int $trainerId): ?array
{
    if ($trainerId < 1) return null;
    $statement = $pdo->prepare('SELECT email,jmeno FROM treneri WHERE id=?');
    $statement->execute([$trainerId]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    return $row === false ? null : ['email' => (string)$row['email'], 'name' => (string)$row['jmeno']];
}

function publicListingIsTechnicalTrainer(array $trainer): bool
{
    $email = mb_strtolower(trim((string)($trainer['email'] ?? '')), 'UTF-8');
    $name = mb_strtolower(trim((string)($trainer['name'] ?? '')), 'UTF-8');
    return $email === 'kis-superadmin-test@velocota.com'
        || $email === 'tester.spravce@velocota.com'
        || str_starts_with($email, 'kis-e2e-')
        || str_contains($name, 'testovací administrátor')
        || str_contains($name, 'testovací superadministrátor');
}

function publicListingValidateTechnicalOwner(PDO $pdo, int $trainerId, string $title): void
{
    $trainer = publicListingTrainer($pdo, $trainerId);
    if ($trainer !== null && publicListingIsTechnicalTrainer($trainer) && !publicListingHasTestPrefix($title)) {
        throw new InvalidArgumentException(
            'Technický testovací účet smí zveřejnit pouze položku s prefixem TEST -. Reálnou nabídku musí převzít skutečný provozní vlastník.'
        );
    }
}
