<?php

declare(strict_types=1);

/** Fresh attention counts for the administrative overview. */
function adminDashboardMemberCounts(PDO $pdo): array
{
    $withoutGroup = (int)$pdo->query('SELECT COUNT(*) FROM sportovci s WHERE NOT EXISTS '
        . '(SELECT 1 FROM sportovec_skupina ss WHERE ss.sportovec_id=s.id)')->fetchColumn();
    $row = $pdo->query("SELECT
        COUNT(CASE WHEN COALESCE(s.kis_neuhrazeno,0)>0 THEN 1 END) AS debt,
        COUNT(CASE WHEN s.kis_last_seen_at IS NULL
            OR s.kis_last_seen_at < DATE_SUB(NOW(), INTERVAL 90 DAY) THEN 1 END) AS stale,
        COUNT(CASE WHEN COALESCE(s.stav_manualni,0)=1
            AND COALESCE(s.kis_aktivni,0)=1 THEN 1 END) AS manual_active
        FROM sportovci s")->fetch(PDO::FETCH_ASSOC);
    return [
        'without_group' => $withoutGroup,
        'debt' => (int)$row['debt'],
        'stale' => (int)$row['stale'],
        'manual_active' => (int)$row['manual_active'],
    ];
}
