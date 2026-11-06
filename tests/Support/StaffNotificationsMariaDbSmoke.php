<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/includes/staff_notifications.php';
require_once dirname(__DIR__, 2) . '/includes/club_event_notification.php';
defined('APP_BASE_URL') || define('APP_BASE_URL', 'http://localhost');
$server = new PDO('mysql:host=127.0.0.1;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$database = 'evidence_staff_notice_test_' . bin2hex(random_bytes(5));
$server->exec('CREATE DATABASE `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=' . $database . ';charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec('CREATE TABLE treneri(id INT PRIMARY KEY,jmeno VARCHAR(100),email VARCHAR(180),aktivni INT,technical_email INT DEFAULT 0) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE staff_user_positions(trainer_id INT,position_code VARCHAR(40)) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE staff_superadmins(trainer_id INT) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE account_person_claim_requests(id BIGINT UNSIGNED PRIMARY KEY,status VARCHAR(24)) ENGINE=InnoDB');
    $pdo->exec("CREATE TABLE club_event_notifications(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,dedupe_key VARCHAR(128) UNIQUE,
        notification_type VARCHAR(64),recipient_email VARCHAR(180),recipient_name VARCHAR(180),subject_plain TEXT,body_plain TEXT,
        status ENUM('pending','processing','sent','failed') NOT NULL DEFAULT 'pending',attempts INT DEFAULT 0,
        available_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,claimed_at DATETIME NULL,claim_token VARCHAR(32) NULL,sent_at DATETIME NULL,
        last_error TEXT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
    $pdo->exec("INSERT INTO treneri(id,jmeno,email,aktivni) VALUES(1,'LOCALHOST NEPLATIT','registrar@example.test',1)");
    $pdo->exec("INSERT INTO staff_user_positions VALUES(1,'registrar'); INSERT INTO account_person_claim_requests VALUES(7,'pending')");
    $migration = require dirname(__DIR__, 2) . '/migrations/20261106090000_staff_notifications.php';
    $migration['up']($pdo);
    $migration['up']($pdo);
    if (!$migration['verify']($pdo)) {
        throw new RuntimeException('Migration verification failed.');
    }
    $first = staffNotificationSync($pdo);
    $second = staffNotificationSync($pdo);
    if ($first['emails'] !== 1 || $second['emails'] !== 0 || staffNotificationInbox($pdo, 1)['unread'] !== 1) {
        throw new RuntimeException('Native prepared statements or deduplication failed.');
    }
    $pdo->exec("UPDATE account_person_claim_requests SET status='approved' WHERE id=7");
    $pdo->exec("INSERT INTO club_event_notifications(notification_type,status,claimed_at) VALUES('unrelated','processing','2020-01-01 00:00:00')");
    $result = clubEventNotificationProcessOne($pdo, static function (): bool {
        throw new RuntimeException('Transport must not be called.');
    }, true);
    if ($result !== 'cancelled' || $pdo->query('SELECT status FROM club_event_notifications')->fetchColumn() !== 'cancelled') {
        throw new RuntimeException('Obsolete notification must be cancelled without mail.');
    }
    if ($pdo->query("SELECT status FROM club_event_notifications WHERE notification_type='unrelated'")->fetchColumn() !== 'processing') {
        throw new RuntimeException('Staff worker must preserve unrelated claims.');
    }
    echo "MariaDB staff notifications PASS: migration twice, native queries, dedupe, inbox, obsolete mail cancellation.\n";
} finally {
    $pdo = null;
    $server->exec('DROP DATABASE `' . $database . '`');
}
