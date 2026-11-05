<?php

declare(strict_types=1);

return [
    'id' => '20261105150000_monthly_memberships',
    'up' => static function (PDO $pdo): void {
        $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $id = $mysql ? 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $tail = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
        $tables = [
            'monthly_membership_mutex' => 'id INT PRIMARY KEY, revision INT NOT NULL DEFAULT 0',
            'monthly_membership_tariffs' => 'id ' . $id . (', variant_id BIGINT UNSIGNED NOT NULL UNIQUE, team_id BIGINT UNSIGNED NOT NULL, amount_minor INT NOT NULL, due_day INT NOT '
    .
    'NULL, terms_text TEXT NOT NULL, terms_sha256 CHAR(64) NOT NULL, issuer_id BIGINT UNSIGNED NOT NULL, rule_id BIGINT UNSIGNED '
    .
    'NOT NULL, document_kind VARCHAR(40) NOT NULL, approved_by INT NOT NULL, status VARCHAR(16) NOT NULL DEFAULT \'active\', '
    .
    'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP'),
            'monthly_memberships' => 'id ' . $id . (', tariff_id BIGINT UNSIGNED NOT NULL, payer_account_id INT NOT NULL, sportovec_id INT NOT NULL, starts_on DATE NOT NULL, '
    .
    'ends_on DATE NULL, variable_symbol VARCHAR(10) NOT NULL, iban_snapshot VARCHAR(40) NOT NULL, buyer_json TEXT NOT NULL, '
    .
    'consent_sha256 CHAR(64) NOT NULL, request_key CHAR(64) NOT NULL UNIQUE, roster_member_id BIGINT UNSIGNED NULL, created_at '
    .
    'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(tariff_id) REFERENCES monthly_membership_tariffs(id)')
    ,
            'monthly_membership_periods' => 'id ' . $id . (', membership_id BIGINT UNSIGNED NOT NULL, period_key CHAR(7) NOT NULL, charge_id BIGINT UNSIGNED NOT NULL UNIQUE, '
    .
    'document_id BIGINT UNSIGNED NULL, last_error VARCHAR(500) NULL, UNIQUE(membership_id,period_key), FOREIGN KEY(membership_id) '
    .
    'REFERENCES monthly_memberships(id)'),
            'monthly_membership_receipts' => 'id ' . $id . (', movement_id BIGINT UNSIGNED NOT NULL UNIQUE, reference VARCHAR(100) NOT NULL UNIQUE, payer_account_id INT NOT NULL, '
    .
    ('amount_minor BIGINT NOT NULL, currency CHAR(3) NOT NULL DEFAULT \'CZK\', paid_on DATE NOT NULL, iban_snapshot VARCHAR(40) NOT ' .
    'NULL, created_at DATETIME NOT NULL ')
    .
    'DEFAULT CURRENT_TIMESTAMP'),
            'monthly_membership_allocations' => 'id ' . $id . (
                ', receipt_id BIGINT UNSIGNED NOT NULL, charge_id BIGINT UNSIGNED NOT NULL, amount_minor BIGINT NOT NULL, '
    .
    'UNIQUE(receipt_id,charge_id), FOREIGN KEY(receipt_id) REFERENCES monthly_membership_receipts(id)'
            ),
            'monthly_membership_messages' => 'id ' . $id . (', charge_id BIGINT UNSIGNED NOT NULL, account_id INT NOT NULL, kind VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL '
    .
    'DEFAULT \'pending\', claim_token CHAR(32) NULL, attempts INT NOT NULL DEFAULT 0, claimed_at DATETIME NULL, sent_at DATETIME '
    .
    'NULL, last_error VARCHAR(500) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE(charge_id,kind)')
    ,
            'monthly_membership_events' => 'id ' . $id . (', membership_id BIGINT UNSIGNED NULL, actor_type VARCHAR(16) NOT NULL, actor_id INT NULL, action VARCHAR(60) NOT NULL, '
    .
    'detail_json TEXT NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP'),
        ];
        foreach ($tables as $name => $columns) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS $name ($columns)$tail");
        }
        $pdo->exec($mysql ? 'INSERT IGNORE INTO monthly_membership_mutex(id) VALUES(1)' : 'INSERT OR IGNORE INTO monthly_membership_mutex(id) VALUES(1)');
    },
    'verify' => static function (PDO $pdo): bool {
        foreach (['monthly_membership_mutex','monthly_membership_tariffs','monthly_memberships','monthly_membership_periods'
    ,'monthly_membership_receipts','monthly_membership_allocations','monthly_membership_messages','monthly_membership_events'] as $table) {
            $pdo->query("SELECT * FROM $table WHERE 1=0");
        }
        return (int)$pdo->query('SELECT COUNT(*) FROM monthly_membership_mutex WHERE id=1')->fetchColumn() === 1;
    },
];
