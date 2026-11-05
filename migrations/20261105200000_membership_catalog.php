<?php

declare(strict_types=1);

return [
    'id' => '20261105200000_membership_catalog',
    'up' => static function (PDO $pdo): void {
        $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $columns = $mysql
            ? $pdo->query('SHOW COLUMNS FROM monthly_membership_tariffs')->fetchAll(PDO::FETCH_COLUMN)
            : array_column($pdo->query('PRAGMA table_info(monthly_membership_tariffs)')->fetchAll(PDO::FETCH_ASSOC), 'name');
        if (!in_array('billing_interval', $columns, true)) {
            $pdo->exec("ALTER TABLE monthly_membership_tariffs ADD COLUMN billing_interval VARCHAR(10) NOT NULL DEFAULT 'month'");
        }
        $tail = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
        $pdo->exec('CREATE TABLE IF NOT EXISTS monthly_membership_tariff_teams ('
            . 'tariff_id BIGINT UNSIGNED NOT NULL, team_id BIGINT UNSIGNED NOT NULL, PRIMARY KEY(tariff_id,team_id), '
            . 'FOREIGN KEY(tariff_id) REFERENCES monthly_membership_tariffs(id))' . $tail);
        $pdo->exec('CREATE TABLE IF NOT EXISTS monthly_membership_team_grants ('
            . 'membership_id BIGINT UNSIGNED NOT NULL, team_id BIGINT UNSIGNED NOT NULL, roster_member_id BIGINT UNSIGNED NOT NULL UNIQUE, '
            . 'PRIMARY KEY(membership_id,team_id), FOREIGN KEY(membership_id) REFERENCES monthly_memberships(id))' . $tail);
    },
    'verify' => static function (PDO $pdo): bool {
        $pdo->query('SELECT billing_interval FROM monthly_membership_tariffs WHERE 1=0');
        $pdo->query('SELECT tariff_id,team_id FROM monthly_membership_tariff_teams WHERE 1=0');
        $pdo->query('SELECT membership_id,team_id,roster_member_id FROM monthly_membership_team_grants WHERE 1=0');
        return true;
    },
];
