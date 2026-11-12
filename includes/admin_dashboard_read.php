<?php

declare(strict_types=1);

/** Fresh attention counts for the administrative overview. */
function adminDashboardMemberCounts(PDO $pdo): array
{
    $row = $pdo->query("SELECT
        COUNT(CASE WHEN NOT EXISTS (
            SELECT 1 FROM sportovec_skupina ss WHERE ss.sportovec_id=s.id
        ) THEN 1 END) AS without_group,
        COUNT(CASE WHEN COALESCE(s.kis_neuhrazeno,0)>0 THEN 1 END) AS debt,
        COUNT(CASE WHEN s.kis_last_seen_at IS NULL
            OR s.kis_last_seen_at < DATE_SUB(NOW(), INTERVAL 90 DAY) THEN 1 END) AS stale,
        COUNT(CASE WHEN COALESCE(s.stav_manualni,0)=1
            AND COALESCE(s.kis_aktivni,0)=1 THEN 1 END) AS manual_active
        FROM sportovci s")->fetch(PDO::FETCH_ASSOC);
    return [
        'without_group' => (int)$row['without_group'],
        'debt' => (int)$row['debt'],
        'stale' => (int)$row['stale'],
        'manual_active' => (int)$row['manual_active'],
    ];
}
