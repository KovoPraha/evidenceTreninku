<?php

declare(strict_types=1);

return [
    'id' => '20261105210000_bar_recovery',
    'up' => static function (PDO $pdo): void {
        $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $columns = static fn (string $table): array => $mysql
            ? $pdo->query('SHOW COLUMNS FROM ' . $table)->fetchAll(PDO::FETCH_COLUMN)
            : array_column($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC), 'name');
        if (!in_array('closed_by', $columns('kis_bar_cashboxes'), true)) {
            $pdo->exec('ALTER TABLE kis_bar_cashboxes ADD closed_by INT NULL' . ($mysql ? '' : ' REFERENCES treneri(id)'));
        }
        if ($mysql && (int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE()
            AND TABLE_NAME='kis_bar_cashboxes' AND CONSTRAINT_NAME='fk_bar_cashbox_closed_by'")->fetchColumn() === 0) {
            $pdo->exec('ALTER TABLE kis_bar_cashboxes ADD CONSTRAINT fk_bar_cashbox_closed_by FOREIGN KEY(closed_by) REFERENCES treneri(id) ON DELETE RESTRICT');
        }
        if (!in_array('closure_kind', $columns('kis_bar_cashboxes'), true)) {
            $pdo->exec("ALTER TABLE kis_bar_cashboxes ADD closure_kind VARCHAR(15) NOT NULL DEFAULT 'owner'");
        }
        if (!in_array('clock_contract', $columns('kis_bar_state'), true)) {
            $pdo->exec('ALTER TABLE kis_bar_state ADD clock_contract VARCHAR(16) NULL');
        }
        if ($pdo->query('SELECT clock_contract FROM kis_bar_state WHERE id=1')->fetchColumn() === null) {
            // B0-B5 have never been deployed. Never relabel existing local history as UTC.
            $hasHistory = (int)$pdo->query('SELECT COUNT(*) FROM kis_bar_cashboxes')->fetchColumn()
                + (int)$pdo->query('SELECT COUNT(*) FROM kis_bar_sales')->fetchColumn()
                + (int)$pdo->query('SELECT COUNT(*) FROM kis_bar_invoice_receipts')->fetchColumn();
            $pdo->prepare('UPDATE kis_bar_state SET clock_contract=? WHERE id=1')->execute([$hasHistory === 0 ? 'utc-v1' : 'legacy-local']);
        }
    },
    'verify' => static function (PDO $pdo): bool {
        $pdo->query('SELECT closed_by,closure_kind FROM kis_bar_cashboxes WHERE 1=0');
        return in_array($pdo->query('SELECT clock_contract FROM kis_bar_state WHERE id=1')->fetchColumn(), ['utc-v1', 'legacy-local'], true);
    },
];
