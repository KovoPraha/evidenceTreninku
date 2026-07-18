<?php
declare(strict_types=1);

function sportovecLogEvent(
    PDO $pdo,
    int $sportovecId,
    string $eventType,
    string $title,
    array $old = [],
    array $new = [],
    string $source = 'manual',
    ?int $userId = null,
    ?string $detail = null,
    ?string $refTable = null,
    ?int $refId = null
): void {
    if ($sportovecId <= 0) {
        return;
    }
    $oldJson = $old ? json_encode($old, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
    $newJson = $new ? json_encode($new, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
    $stmt = $pdo->prepare("
        INSERT INTO sportovec_history
            (sportovec_id, created_by, source, event_type, title, detail, old_json, new_json, ref_table, ref_id)
        VALUES
            (:sportovec_id, :created_by, :source, :event_type, :title, :detail, :old_json, :new_json, :ref_table, :ref_id)
    ");
    $stmt->execute([
        ':sportovec_id' => $sportovecId,
        ':created_by' => $userId,
        ':source' => in_array($source, ['manual','kis_import','bulk_action','system'], true) ? $source : 'manual',
        ':event_type' => substr($eventType, 0, 80),
        ':title' => substr($title, 0, 180),
        ':detail' => $detail,
        ':old_json' => $oldJson,
        ':new_json' => $newJson,
        ':ref_table' => $refTable,
        ':ref_id' => $refId,
    ]);

    if (function_exists('zapisAuditLog')) {
        try {
            zapisAuditLog(
                $pdo,
                $userId ?? 0,
                $title,
                'sportovci',
                $sportovecId,
                json_encode([
                    'event_type' => $eventType,
                    'source' => $source,
                    'old' => $old,
                    'new' => $new,
                    'detail' => $detail,
                    'ref_table' => $refTable,
                    'ref_id' => $refId,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );
        } catch (Throwable $e) {
            error_log('sportovecLogEvent audit failed: ' . $e->getMessage());
        }
    }
}

function sportovecHistoryFetch(PDO $pdo, int $sportovecId, int $limit = 80): array
{
    $stmt = $pdo->prepare("
        SELECT h.*, t.jmeno AS trener_jmeno
        FROM sportovec_history h
        LEFT JOIN treneri t ON t.id = h.created_by
        WHERE h.sportovec_id = ?
        ORDER BY h.created_at DESC, h.id DESC
        LIMIT " . max(1, min(300, $limit))
    );
    $stmt->execute([$sportovecId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
