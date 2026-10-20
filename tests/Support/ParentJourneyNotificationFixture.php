<?php
declare(strict_types=1);

namespace Tests\Support;

use PDO;

/** Minimal shared outbox for isolated identity/program SQLite service fixtures. */
final class ParentJourneyNotificationFixture
{
    public static function install(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS club_event_notifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                registration_id INTEGER NULL,
                registration_event_id INTEGER NULL,
                order_id INTEGER NULL,
                notification_type TEXT NOT NULL,
                recipient_email TEXT NOT NULL,
                recipient_name TEXT NOT NULL,
                subject_plain TEXT NOT NULL,
                body_plain TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'pending',
                attempts INTEGER NOT NULL DEFAULT 0,
                available_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                claimed_at TEXT NULL,
                claim_token TEXT NULL,
                sent_at TEXT NULL,
                last_error TEXT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(order_id,notification_type)
            )
            SQL);
        $migration = require dirname(__DIR__, 2) . '/migrations/20261020120000_parent_journey_notifications.php';
        $migration['up']($pdo);
        $migration['up']($pdo);
        if (!$migration['verify']($pdo)) throw new \RuntimeException('Parent notification fixture migration failed.');
    }
}
