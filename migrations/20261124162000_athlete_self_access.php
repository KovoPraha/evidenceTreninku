<?php

declare(strict_types=1);

return [
    'id' => '20261124162000_athlete_self_access',
    'up' => static function (PDO $pdo): void {
        $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        if ($mysql) {
            $pdo->exec('ALTER TABLE child_access_accounts MODIFY created_by_trainer_id INT NULL');
            $pdo->exec('ALTER TABLE password_reset_tokens MODIFY delivery_account_id INT NULL');
        } else {
            if ($pdo->inTransaction()) {
                throw new RuntimeException('SQLite schema rebuild requires no active transaction.');
            }
            $pdo->exec('PRAGMA foreign_keys=OFF');
            try {
                $pdo->beginTransaction();
                foreach ([
                    'child_access_accounts' => ['created_by_trainer_id INTEGER NOT NULL', 'created_by_trainer_id INTEGER NULL'],
                    'password_reset_tokens' => ['delivery_account_id INTEGER NOT NULL', 'delivery_account_id INTEGER NULL'],
                ] as $table => [$old, $new]) {
                    $sql = (string)$pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name=" . $pdo->quote($table))->fetchColumn();
                    if (!str_contains($sql, $old)) {
                        continue;
                    }
                    $create = str_replace('CREATE TABLE ' . $table, 'CREATE TABLE ' . $table . '_new', $sql);
                    $create = str_replace($old, $new, $create);
                    $pdo->exec($create);
                    $pdo->exec("INSERT INTO {$table}_new SELECT * FROM {$table}");
                    $pdo->exec("DROP TABLE {$table}");
                    $pdo->exec("ALTER TABLE {$table}_new RENAME TO {$table}");
                }
                $pdo->exec('CREATE INDEX IF NOT EXISTS idx_password_reset_target ON password_reset_tokens(target_type,target_id,consumed_at,expires_at)');
                $pdo->commit();
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $error;
            } finally {
                $pdo->exec('PRAGMA foreign_keys=ON');
            }
            if ($pdo->query('PRAGMA foreign_key_check')->fetchColumn() !== false) {
                throw new RuntimeException('Athlete access migration broke a foreign key.');
            }
        }
        $pdo->exec($mysql ? <<<'SQL'
            CREATE TABLE IF NOT EXISTS athlete_email_trust (
                sportovec_id INT NOT NULL PRIMARY KEY,
                access_account_id BIGINT UNSIGNED NULL,
                email_hash CHAR(64) NOT NULL,
                state VARCHAR(20) NOT NULL,
                auto_created TINYINT(1) NOT NULL DEFAULT 0,
                source VARCHAR(40) NOT NULL,
                trusted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_athlete_trust_person FOREIGN KEY (sportovec_id) REFERENCES sportovci(id) ON DELETE RESTRICT,
                CONSTRAINT fk_athlete_trust_access FOREIGN KEY (access_account_id) REFERENCES child_access_accounts(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL : <<<'SQL'
            CREATE TABLE IF NOT EXISTS athlete_email_trust (
                sportovec_id INTEGER NOT NULL PRIMARY KEY,
                access_account_id INTEGER NULL,
                email_hash TEXT NOT NULL,
                state TEXT NOT NULL,
                auto_created INTEGER NOT NULL DEFAULT 0,
                source TEXT NOT NULL,
                trusted_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (sportovec_id) REFERENCES sportovci(id) ON DELETE RESTRICT,
                FOREIGN KEY (access_account_id) REFERENCES child_access_accounts(id) ON DELETE RESTRICT
            )
            SQL);
        $pdo->beginTransaction();
        try {
            $rows = $pdo->query('SELECT id,email FROM sportovci ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
            $existing = $pdo->prepare('SELECT id,active FROM child_access_accounts WHERE sportovec_id=?');
            $login = $pdo->prepare('SELECT 1 FROM child_access_accounts WHERE login_key=?');
            $trust = $pdo->prepare('SELECT 1 FROM athlete_email_trust WHERE sportovec_id=?');
            $insert = $pdo->prepare('INSERT INTO child_access_accounts'
                . '(sportovec_id,login_name,login_key,password_hash,active,session_version,password_changed_at,created_by_trainer_id) '
                . 'VALUES(?,?,?,?,0,1,CURRENT_TIMESTAMP,NULL)');
            $audit = $pdo->prepare('INSERT INTO child_access_events(access_account_id,actor_type,actor_id,action,note) VALUES(?,\'system\',NULL,\'create\',?)');
            $record = $pdo->prepare('INSERT INTO athlete_email_trust(sportovec_id,access_account_id,email_hash,state,auto_created,source) VALUES(?,?,?,?,?,?)');
            foreach ($rows as $person) {
                $id = (int)$person['id'];
                $trust->execute([$id]);
                if ($trust->fetchColumn()) {
                    continue;
                }
                $email = mb_strtolower(trim((string)$person['email']), 'UTF-8');
                $valid = filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
                $existing->execute([$id]);
                $access = $existing->fetch(PDO::FETCH_ASSOC);
                $state = $valid ? 'trusted' : 'undeliverable';
                $auto = 0;
                if ($access && (int)$access['active'] !== 1) {
                    $state = 'disabled';
                }
                if (!$access) {
                    $loginKey = 'sportovec.' . $id;
                    for ($attempt = 0; $attempt < 5; $attempt++) {
                        $login->execute([$loginKey]);
                        if (!$login->fetchColumn()) {
                            break;
                        }
                        $loginKey = 'sportovec.' . $id . '.' . bin2hex(random_bytes(4));
                    }
                    $login->execute([$loginKey]);
                    if ($login->fetchColumn()) {
                        $state = 'login_conflict';
                    } else {
                        $insert->execute([$id, $loginKey, $loginKey, password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT)]);
                        $access = ['id' => (int)$pdo->lastInsertId(), 'active' => 0];
                        $auto = 1;
                        $audit->execute([(int)$access['id'], 'Automaticky vytvořený vypnutý přístup; první heslo pouze na adresu uznanou vlastníkem.']);
                    }
                }
                $record->execute([$id, $access ? (int)$access['id'] : null, hash('sha256', $email), $state, $auto, 'owner_2026_11_24']);
            }
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
    },
    'verify' => static function (PDO $pdo): bool {
        try {
            $pdo->query('SELECT sportovec_id,access_account_id,email_hash,state,auto_created FROM athlete_email_trust LIMIT 0');
            return true;
        } catch (PDOException) {
            return false;
        }
    },
];
