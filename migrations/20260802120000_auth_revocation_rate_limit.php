<?php
declare(strict_types=1);

$tableExists = static function (PDO $pdo, string $table): bool {
    $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'mysql') {
        $statement = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table LIMIT 1'
        );
        $statement->execute(['table' => $table]);
        return (bool)$statement->fetchColumn();
    }

    if ($driver === 'sqlite') {
        $statement = $pdo->prepare(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = :table LIMIT 1"
        );
        $statement->execute(['table' => $table]);
        return (bool)$statement->fetchColumn();
    }

    throw new RuntimeException('Unsupported database driver for auth migration.');
};

$columnExists = static function (PDO $pdo, string $table, string $column): bool {
    $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'mysql') {
        $statement = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table '
            . 'AND COLUMN_NAME = :column LIMIT 1'
        );
        $statement->execute(['table' => $table, 'column' => $column]);
        return (bool)$statement->fetchColumn();
    }

    if ($driver === 'sqlite') {
        if (!preg_match('/^[a-z0-9_]+$/D', $table)) {
            throw new RuntimeException('Invalid table identifier in auth migration.');
        }
        $columns = $pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $definition) {
            if (($definition['name'] ?? null) === $column) {
                return true;
            }
        }
        return false;
    }

    throw new RuntimeException('Unsupported database driver for auth migration.');
};

$indexExists = static function (PDO $pdo, string $table, string $index): bool {
    $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'mysql') {
        $statement = $pdo->prepare(
            'SELECT 1 FROM information_schema.STATISTICS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table '
            . 'AND INDEX_NAME = :index_name LIMIT 1'
        );
        $statement->execute(['table' => $table, 'index_name' => $index]);
        return (bool)$statement->fetchColumn();
    }

    if ($driver === 'sqlite') {
        $statement = $pdo->prepare(
            "SELECT 1 FROM sqlite_master WHERE type = 'index' AND name = :index_name LIMIT 1"
        );
        $statement->execute(['index_name' => $index]);
        return (bool)$statement->fetchColumn();
    }

    throw new RuntimeException('Unsupported database driver for auth migration.');
};

return [
    'id' => '20260802120000_auth_revocation_rate_limit',
    'up' => static function (PDO $pdo) use ($tableExists, $columnExists): void {
        foreach (['treneri', 'verejni_uzivatele'] as $requiredTable) {
            if (!$tableExists($pdo, $requiredTable)) {
                throw new RuntimeException('Required identity table is missing: ' . $requiredTable);
            }
        }

        $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        foreach (['treneri', 'verejni_uzivatele'] as $identityTable) {
            if ($columnExists($pdo, $identityTable, 'session_version')) {
                continue;
            }

            if ($driver === 'mysql') {
                $pdo->exec(
                    'ALTER TABLE `' . $identityTable . '` '
                    . 'ADD COLUMN `session_version` INT UNSIGNED NOT NULL DEFAULT 1'
                );
            } elseif ($driver === 'sqlite') {
                $pdo->exec(
                    'ALTER TABLE ' . $identityTable
                    . ' ADD COLUMN session_version INTEGER NOT NULL DEFAULT 1'
                );
            } else {
                throw new RuntimeException('Unsupported database driver for auth migration.');
            }
        }

        if (!$tableExists($pdo, 'auth_login_limits')) {
            if ($driver === 'mysql') {
                $pdo->exec(
                    'CREATE TABLE auth_login_limits ('
                    . 'scope VARCHAR(64) NOT NULL, '
                    . 'key_hash CHAR(64) NOT NULL, '
                    . 'window_started_at BIGINT UNSIGNED NOT NULL, '
                    . 'attempts INT UNSIGNED NOT NULL DEFAULT 0, '
                    . 'blocked_until BIGINT UNSIGNED NOT NULL DEFAULT 0, '
                    . 'updated_at BIGINT UNSIGNED NOT NULL, '
                    . 'PRIMARY KEY (scope, key_hash), '
                    . 'INDEX idx_auth_login_limits_blocked (blocked_until)'
                    . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
                );
            } elseif ($driver === 'sqlite') {
                $pdo->exec(
                    'CREATE TABLE auth_login_limits ('
                    . 'scope TEXT NOT NULL, '
                    . 'key_hash TEXT NOT NULL, '
                    . 'window_started_at INTEGER NOT NULL, '
                    . 'attempts INTEGER NOT NULL DEFAULT 0, '
                    . 'blocked_until INTEGER NOT NULL DEFAULT 0, '
                    . 'updated_at INTEGER NOT NULL, '
                    . 'PRIMARY KEY (scope, key_hash)'
                    . ')'
                );
                $pdo->exec(
                    'CREATE INDEX idx_auth_login_limits_blocked '
                    . 'ON auth_login_limits (blocked_until)'
                );
            } else {
                throw new RuntimeException('Unsupported database driver for auth migration.');
            }
        }
    },
    'verify' => static function (PDO $pdo) use ($tableExists, $columnExists, $indexExists): bool {
        if (!$columnExists($pdo, 'treneri', 'session_version')
            || !$columnExists($pdo, 'verejni_uzivatele', 'session_version')
            || !$tableExists($pdo, 'auth_login_limits')
            || !$indexExists($pdo, 'auth_login_limits', 'idx_auth_login_limits_blocked')
        ) {
            return false;
        }

        foreach (['treneri', 'verejni_uzivatele'] as $identityTable) {
            $invalid = (int)$pdo->query(
                'SELECT COUNT(*) FROM ' . $identityTable
                . ' WHERE session_version IS NULL OR session_version < 1'
            )->fetchColumn();
            if ($invalid !== 0) {
                return false;
            }
        }

        foreach (['scope', 'key_hash', 'window_started_at', 'attempts', 'blocked_until', 'updated_at'] as $column) {
            if (!$columnExists($pdo, 'auth_login_limits', $column)) {
                return false;
            }
        }

        return true;
    },
];
