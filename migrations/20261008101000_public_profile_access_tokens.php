<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/public_profile_token.php';

$tableExists = static function (PDO $pdo, string $table): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    } else {
        $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");
    }
    $statement->execute([$table]);
    return (bool)$statement->fetchColumn();
};

$columnExists = static function (PDO $pdo, string $table, string $column): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?'
        );
        $statement->execute([$table, $column]);
        return (bool)$statement->fetchColumn();
    }
    foreach ($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ((string)$row['name'] === $column) return true;
    }
    return false;
};

return [
    'id' => '20261008101000_public_profile_access_tokens',
    'up' => static function (PDO $pdo) use ($tableExists, $columnExists): void {
        $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $marker = $pdo->prepare('SELECT hodnota FROM nastaveni WHERE klic=?');
        $marker->execute(['security_public_profile_access_v1']);
        if ((string)$marker->fetchColumn() === 'complete') return;
        if (!$tableExists($pdo, 'public_profile_access_tokens')) {
            if ($driver === 'mysql') {
                $pdo->exec(
                    'CREATE TABLE public_profile_access_tokens ('
                    . 'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, sportovec_id INT NOT NULL, '
                    . 'token_hash CHAR(64) NOT NULL, token_hint CHAR(8) NOT NULL, scope VARCHAR(32) NOT NULL, '
                    . 'expires_at DATETIME NOT NULL, revoked_at DATETIME NULL, last_used_at DATETIME NULL, '
                    . 'created_by_trainer_id INT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, '
                    . 'PRIMARY KEY(id), UNIQUE KEY uq_public_profile_token_hash(token_hash), '
                    . 'INDEX idx_public_profile_person(sportovec_id,revoked_at,expires_at), '
                    . 'CONSTRAINT fk_public_profile_access_person FOREIGN KEY(sportovec_id) REFERENCES sportovci(id) ON DELETE CASCADE'
                    . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
                );
            } else {
                $pdo->exec(
                    'CREATE TABLE public_profile_access_tokens ('
                    . 'id INTEGER PRIMARY KEY AUTOINCREMENT, sportovec_id INTEGER NOT NULL, token_hash TEXT NOT NULL UNIQUE, '
                    . 'token_hint TEXT NOT NULL, scope TEXT NOT NULL, expires_at TEXT NOT NULL, revoked_at TEXT NULL, '
                    . 'last_used_at TEXT NULL, created_by_trainer_id INTEGER NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, '
                    . 'FOREIGN KEY(sportovec_id) REFERENCES sportovci(id) ON DELETE CASCADE)'
                );
                $pdo->exec('CREATE INDEX idx_public_profile_person ON public_profile_access_tokens(sportovec_id,revoked_at,expires_at)');
            }
        }

        // Preserve old emailed links for a short migration window, but store only
        // their digest. Rotate sportovci.hash so it is no longer a bearer secret.
        if ($tableExists($pdo, 'sportovci') && $columnExists($pdo, 'sportovci', 'hash')) {
            $rows = $pdo->query('SELECT id,hash FROM sportovci ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
            $insert = $pdo->prepare(
                'INSERT INTO public_profile_access_tokens '
                . '(sportovec_id,token_hash,token_hint,scope,expires_at,created_by_trainer_id) VALUES (?,?,?,?,?,NULL)'
            );
            $rotate = $pdo->prepare('UPDATE sportovci SET hash=? WHERE id=?');
            foreach ($rows as $row) {
                $raw = trim((string)$row['hash']);
                if (public_profile_token_is_strong($raw)) {
                    $digest = public_profile_access_token_hash($raw);
                    try {
                        $insert->execute([
                            (int)$row['id'],
                            $digest,
                            substr($raw, -8),
                            'training_read',
                            gmdate('Y-m-d H:i:s', time() + 30 * 86400),
                        ]);
                    } catch (PDOException $exception) {
                        if ((string)$exception->getCode() !== '23000') throw $exception;
                    }
                }
                $rotate->execute([public_profile_token_generate(), (int)$row['id']]);
            }
        }
        if ($driver === 'mysql') {
            $pdo->prepare("INSERT INTO nastaveni(klic,hodnota) VALUES (?,?) ON DUPLICATE KEY UPDATE hodnota=VALUES(hodnota)")
                ->execute(['security_public_profile_access_v1', 'complete']);
        } else {
            $pdo->prepare('INSERT INTO nastaveni(klic,hodnota) VALUES (?,?) ON CONFLICT(klic) DO UPDATE SET hodnota=excluded.hodnota')
                ->execute(['security_public_profile_access_v1', 'complete']);
        }
    },
    'verify' => static function (PDO $pdo) use ($tableExists): bool {
        if (!$tableExists($pdo, 'public_profile_access_tokens')) return false;
        $marker = $pdo->prepare('SELECT hodnota FROM nastaveni WHERE klic=?');
        $marker->execute(['security_public_profile_access_v1']);
        if ((string)$marker->fetchColumn() !== 'complete') return false;
        $invalid = (int)$pdo->query(
            "SELECT COUNT(*) FROM public_profile_access_tokens WHERE LENGTH(token_hash)<>64 OR scope<>'training_read'"
        )->fetchColumn();
        return $invalid === 0;
    },
];
