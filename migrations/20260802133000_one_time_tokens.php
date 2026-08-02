<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/one_time_token.php';

$columnExists = static function (PDO $pdo, string $table, string $column): bool {
    $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'mysql') {
        $statement = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $statement->execute([$table, $column]);
        return (bool)$statement->fetchColumn();
    }
    if ($driver === 'sqlite') {
        if (preg_match('/^[a-z0-9_]+$/D', $table) !== 1) {
            throw new RuntimeException('Neplatný název tabulky.');
        }
        foreach ($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC) as $definition) {
            if (($definition['name'] ?? null) === $column) {
                return true;
            }
        }
        return false;
    }
    throw new RuntimeException('Nepodporovaný databázový driver.');
};

return [
    'id' => '20260802133000_one_time_tokens',
    'up' => static function (PDO $pdo) use ($columnExists): void {
        $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $definitions = [
            ['verejni_uzivatele', 'verifikacni_token_expires_at'],
            ['verejne_rezervace', 'potvrzovaci_token_expires_at'],
        ];
        foreach ($definitions as [$table, $column]) {
            if ($columnExists($pdo, $table, $column)) {
                continue;
            }
            $type = $driver === 'mysql' ? 'DATETIME NULL' : 'TEXT NULL';
            $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $type);
        }

        $users = $pdo->query(
            'SELECT id, verifikacni_token, registrovan FROM verejni_uzivatele '
            . 'WHERE verifikacni_token IS NOT NULL AND verifikacni_token_expires_at IS NULL'
        )->fetchAll(PDO::FETCH_ASSOC);
        $updateUser = $pdo->prepare(
            'UPDATE verejni_uzivatele SET verifikacni_token = ?, '
            . 'verifikacni_token_expires_at = ? WHERE id = ? AND verifikacni_token = ?'
        );
        foreach ($users as $user) {
            $raw = (string)$user['verifikacni_token'];
            $hash = one_time_token_hash(ONE_TIME_TOKEN_EMAIL_VERIFICATION, $raw);
            $registeredAt = strtotime((string)$user['registrovan'] . ' UTC') ?: 0;
            $updateUser->execute([
                $hash !== '' ? $hash : null,
                $hash !== '' ? gmdate('Y-m-d H:i:s', $registeredAt + 86400) : null,
                (int)$user['id'],
                $raw,
            ]);
        }

        $reservations = $pdo->query(
            'SELECT id, potvrzovaci_token, cas_rezervace FROM verejne_rezervace '
            . 'WHERE potvrzovaci_token IS NOT NULL AND potvrzovaci_token_expires_at IS NULL'
        )->fetchAll(PDO::FETCH_ASSOC);
        $updateReservation = $pdo->prepare(
            'UPDATE verejne_rezervace SET potvrzovaci_token = ?, '
            . 'potvrzovaci_token_expires_at = ? WHERE id = ? AND potvrzovaci_token = ?'
        );
        foreach ($reservations as $reservation) {
            $raw = (string)$reservation['potvrzovaci_token'];
            $hash = one_time_token_hash(ONE_TIME_TOKEN_BOOKING_APPROVAL, $raw);
            $reservedAt = strtotime((string)$reservation['cas_rezervace'] . ' UTC') ?: 0;
            $updateReservation->execute([
                $hash !== '' ? $hash : null,
                $hash !== '' ? gmdate('Y-m-d H:i:s', $reservedAt + 172800) : null,
                (int)$reservation['id'],
                $raw,
            ]);
        }
    },
    'verify' => static function (PDO $pdo) use ($columnExists): bool {
        if (!$columnExists($pdo, 'verejni_uzivatele', 'verifikacni_token_expires_at')
            || !$columnExists($pdo, 'verejne_rezervace', 'potvrzovaci_token_expires_at')
        ) {
            return false;
        }

        $invalidUsers = (int)$pdo->query(
            'SELECT COUNT(*) FROM verejni_uzivatele '
            . 'WHERE verifikacni_token IS NOT NULL AND verifikacni_token_expires_at IS NULL'
        )->fetchColumn();
        $invalidReservations = (int)$pdo->query(
            'SELECT COUNT(*) FROM verejne_rezervace '
            . 'WHERE potvrzovaci_token IS NOT NULL AND potvrzovaci_token_expires_at IS NULL'
        )->fetchColumn();

        return $invalidUsers === 0 && $invalidReservations === 0;
    },
];
