<?php

declare(strict_types=1);
return [
    'id' => '20261105200000_bar_documents',
    'up' => static function (PDO $pdo): void {
        $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $id = $mysql ? 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $big = $mysql ? 'BIGINT UNSIGNED' : 'INTEGER';
        $text = $mysql ? 'MEDIUMTEXT' : 'TEXT';
        $suffix = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
        $columns = $mysql ? $pdo->query('SHOW COLUMNS FROM kis_bar_products')->fetchAll(PDO::FETCH_COLUMN)
            : array_column(
                $pdo->query('PRAGMA table_info(kis_bar_products)')->fetchAll(PDO::FETCH_ASSOC),
                'name'
            );
        if (!in_array('purchase_version', $columns, true)) {
            $pdo->exec('ALTER TABLE kis_bar_products ADD purchase_version INT NOT NULL DEFAULT 1');
        }
        $columns = $mysql ? $pdo->query('SHOW COLUMNS FROM kis_bar_state')->fetchAll(PDO::FETCH_COLUMN)
            : array_column(
                $pdo->query('PRAGMA table_info(kis_bar_state)')->fetchAll(PDO::FETCH_ASSOC),
                'name'
            );
        if (!in_array('active_offer_publication_id', $columns, true)) {
            $pdo->exec("ALTER TABLE kis_bar_state ADD active_offer_publication_id $big NULL");
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS kis_bar_invoices (
            id $id, pdf_sha256 VARCHAR(64) NOT NULL UNIQUE, storage_key VARCHAR(255) NOT NULL, pdf_bytes INT NOT NULL,
            raw_text $text NOT NULL, parse_warning TEXT NOT NULL, identity_key VARCHAR(64) NULL UNIQUE,
            status VARCHAR(15) NOT NULL DEFAULT 'draft', lock_version INT NOT NULL DEFAULT 1,
            draft_json $text NOT NULL, current_receipt_id $big NULL,
            uploaded_by INT NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(uploaded_by) REFERENCES treneri(id) ON DELETE RESTRICT
        )$suffix");
        $pdo->exec("CREATE TABLE IF NOT EXISTS kis_bar_invoice_receipts (
            id $id, invoice_id $big NOT NULL, actor_id INT NOT NULL, received_on VARCHAR(10) NOT NULL,
            snapshot_json $text NOT NULL, status VARCHAR(15) NOT NULL DEFAULT 'received',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, reversed_at DATETIME NULL, reversed_by INT NULL, reversal_reason TEXT NULL,
            FOREIGN KEY(invoice_id) REFERENCES kis_bar_invoices(id) ON DELETE RESTRICT,
            FOREIGN KEY(actor_id) REFERENCES treneri(id) ON DELETE RESTRICT,
            FOREIGN KEY(reversed_by) REFERENCES treneri(id) ON DELETE RESTRICT
        )$suffix");
        $pdo->exec("CREATE TABLE IF NOT EXISTS kis_bar_invoice_receipt_items (
            receipt_id $big NOT NULL, product_id $big NOT NULL, quantity INT NOT NULL, unit_cost_minor BIGINT NOT NULL,
            stock_operation_id $big NOT NULL, PRIMARY KEY(receipt_id,product_id),
            FOREIGN KEY(receipt_id) REFERENCES kis_bar_invoice_receipts(id) ON DELETE RESTRICT,
            FOREIGN KEY(product_id) REFERENCES kis_bar_products(id) ON DELETE RESTRICT,
            FOREIGN KEY(stock_operation_id) REFERENCES kis_bar_stock_operations(id) ON DELETE RESTRICT
        )$suffix");
        $pdo->exec("CREATE TABLE IF NOT EXISTS kis_bar_supplier_mappings (
            mapping_key VARCHAR(64) NOT NULL PRIMARY KEY, supplier_id VARCHAR(50) NOT NULL, supplier_code VARCHAR(100) NOT NULL,
            invoice_unit VARCHAR(20) NOT NULL, product_id $big NOT NULL, multiplier INT NOT NULL,
            FOREIGN KEY(product_id) REFERENCES kis_bar_products(id) ON DELETE RESTRICT
        )$suffix");
        $pdo->exec("CREATE TABLE IF NOT EXISTS kis_bar_offers (
            id $id, title VARCHAR(150) NOT NULL, subtitle VARCHAR(500) NOT NULL, layout VARCHAR(10) NOT NULL,
            selection_json TEXT NOT NULL, lock_version INT NOT NULL DEFAULT 1,
            updated_by INT NOT NULL, FOREIGN KEY(updated_by) REFERENCES treneri(id) ON DELETE RESTRICT
        )$suffix");
        $pdo->exec("CREATE TABLE IF NOT EXISTS kis_bar_offer_publications (
            id $id, offer_id $big NOT NULL, config_json TEXT NOT NULL, published_by INT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(offer_id) REFERENCES kis_bar_offers(id) ON DELETE RESTRICT,
            FOREIGN KEY(published_by) REFERENCES treneri(id) ON DELETE RESTRICT
        )$suffix");
        $pdo->exec("CREATE TABLE IF NOT EXISTS kis_bar_accounting_exports (
            id $id, actor_id INT NOT NULL, manifest_json $text NOT NULL, sha256 VARCHAR(64) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(actor_id) REFERENCES treneri(id) ON DELETE RESTRICT
        )$suffix");
        $pdo->exec("CREATE TABLE IF NOT EXISTS kis_bar_accounting_export_items (
            source_key VARCHAR(100) NOT NULL PRIMARY KEY, export_id $big NOT NULL, event_sha256 VARCHAR(64) NOT NULL,
            FOREIGN KEY(export_id) REFERENCES kis_bar_accounting_exports(id) ON DELETE RESTRICT
        )$suffix");
    },
    'verify' => static function (PDO $pdo): bool {
        foreach (['invoices', 'invoice_receipts', 'invoice_receipt_items', 'supplier_mappings', 'offers', 'offer_publications',
     'accounting_exports', 'accounting_export_items'] as $table) {
            $pdo->query('SELECT * FROM kis_bar_' . $table . ' WHERE 1=0');
        }
        $pdo->query('SELECT purchase_version FROM kis_bar_products WHERE 1=0');
        $pdo->query('SELECT active_offer_publication_id FROM kis_bar_state WHERE 1=0');
        return true;
    },
];
