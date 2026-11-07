<?php

declare(strict_types=1);

return [
    'id' => '20261107180000_shop_document_automation',
    'up' => static function (PDO $pdo): void {
        $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $id = $mysql ? 'INT NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $tail = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
        $pdo->exec('CREATE TABLE IF NOT EXISTS shop_document_policies (
            id '.$id.', issuer_id INT NOT NULL, rule_id INT NOT NULL, approved_by INT NOT NULL,
            enabled INT NOT NULL, reason VARCHAR(1000) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        )'.$tail);
        $pdo->exec('CREATE TABLE IF NOT EXISTS shop_order_documents (
            order_id INT PRIMARY KEY, policy_id INT NOT NULL, buyer_json TEXT NOT NULL,
            source_json TEXT NOT NULL, source_sha256 VARCHAR(64) NOT NULL,
            paid_at DATETIME NULL, invoice_id INT NULL, correction_id INT NULL,
            last_error VARCHAR(500) NULL, retry_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        )'.$tail);
        foreach (['shop_order_placed','shop_order_cancelled','shop_order_refunded','shop_invoice_issued','shop_correction_issued'
    ,'shop_refund_paid'] as $key) {
            $q = $pdo->prepare('SELECT 1 FROM shop_system_email_templates WHERE template_key=?');
            $q->execute([$key]);
            if (!$q->fetchColumn()) {
                $pdo->prepare('INSERT INTO shop_system_email_templates(template_key,version) VALUES(?,0)')->execute([$key
    ]);
            }
        }
    },
    'verify' => static function (PDO $pdo): bool {
        try {
            $pdo->query('SELECT id,issuer_id,rule_id,approved_by,enabled,reason FROM shop_document_policies LIMIT 0');
            $pdo->query((('SELECT order_id,policy_id,buyer_json,source_json,source_sha256,paid_at,invoice_id,' .
    'correction_id,last_error,retry_at FROM ') .
    'shop_order_documents LIMIT 0'));
            return (int)$pdo->query(
                ("SELECT COUNT(*) FROM shop_system_email_templates WHERE template_key IN " .
    ("('shop_order_placed','shop_order_cancelled','shop_order_refunded','shop_invoice_issued'," .
    "'shop_correction_issued','shop_refund_paid')"))
            )->fetchColumn() === 6;
        } catch (PDOException) {
            return false;
        }
    },
];
