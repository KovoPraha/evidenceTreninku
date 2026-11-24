<?php

declare(strict_types=1);

// Owner's one-time decision on 24 Nov 2026, 15:21:06 UTC. Newer accounts keep normal verification.
$existingEmailTrustCutoff = 1795533666;

return [
    'id' => '20261124160000_existing_email_trust',
    'up' => static function (PDO $pdo) use ($existingEmailTrustCutoff): void {
        $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $pdo->exec($mysql ? <<<'SQL'
            CREATE TABLE IF NOT EXISTS existing_email_trust (
                account_id INT NOT NULL PRIMARY KEY,
                email_hash CHAR(64) NOT NULL,
                state VARCHAR(16) NOT NULL,
                trusted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                source VARCHAR(40) NOT NULL,
                CONSTRAINT fk_existing_email_trust_account FOREIGN KEY (account_id)
                    REFERENCES verejni_uzivatele(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL : <<<'SQL'
            CREATE TABLE IF NOT EXISTS existing_email_trust (
                account_id INTEGER NOT NULL PRIMARY KEY,
                email_hash TEXT NOT NULL,
                state TEXT NOT NULL,
                trusted_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                source TEXT NOT NULL,
                FOREIGN KEY (account_id) REFERENCES verejni_uzivatele(id) ON DELETE RESTRICT
            )
            SQL);

        $pdo->beginTransaction();
        try {
            $sql = $mysql
                ? 'SELECT id,email FROM verejni_uzivatele WHERE UNIX_TIMESTAMP(registrovan)<=? ORDER BY id FOR UPDATE'
                : "SELECT id,email FROM verejni_uzivatele WHERE CAST(strftime('%s',registrovan) AS INTEGER)<=? ORDER BY id";
            $accounts = $pdo->prepare($sql);
            $accounts->execute([$existingEmailTrustCutoff]);
            $insert = $pdo->prepare(($mysql ? 'INSERT IGNORE' : 'INSERT OR IGNORE')
                . ' INTO existing_email_trust(account_id,email_hash,state,source) VALUES(?,?,?,?)');
            $recorded = $pdo->prepare('SELECT email_hash,state FROM existing_email_trust WHERE account_id=?');
            $update = $pdo->prepare('UPDATE verejni_uzivatele SET email_overeno=1,verifikacni_token=NULL,verifikacni_token_expires_at=NULL WHERE id=? AND email=?');
            foreach ($accounts->fetchAll(PDO::FETCH_ASSOC) as $account) {
                $email = mb_strtolower(trim((string)$account['email']), 'UTF-8');
                $valid = filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
                $insert->execute([(int)$account['id'], hash('sha256', $email), $valid ? 'trusted' : 'undeliverable', 'owner_2026_11_24']);
                $recorded->execute([(int)$account['id']]);
                $trust = $recorded->fetch(PDO::FETCH_ASSOC);
                if ($valid && $trust && $trust['state'] === 'trusted' && hash_equals((string)$trust['email_hash'], hash('sha256', $email))) {
                    $update->execute([(int)$account['id'], (string)$account['email']]);
                }
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
            $pdo->query('SELECT account_id,email_hash,state,source FROM existing_email_trust LIMIT 0');
            return true;
        } catch (PDOException) {
            return false;
        }
    },
];
