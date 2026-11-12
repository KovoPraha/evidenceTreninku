<?php

declare(strict_types=1);

/** Frozen four attention queries from admin_dashboard.php at 5cd6ad07. */
function adminDashboardBaseline(PDO $pdo): array
{
    return [
        'without_group' => (int)$pdo->query('SELECT COUNT(*) FROM sportovci s WHERE NOT EXISTS '
            . '(SELECT 1 FROM sportovec_skupina ss WHERE ss.sportovec_id=s.id)')->fetchColumn(),
        'debt' => (int)$pdo->query('SELECT COUNT(*) FROM sportovci WHERE COALESCE(kis_neuhrazeno,0)>0')->fetchColumn(),
        'stale' => (int)$pdo->query('SELECT COUNT(*) FROM sportovci WHERE kis_last_seen_at IS NULL '
            . 'OR kis_last_seen_at < DATE_SUB(NOW(), INTERVAL 90 DAY)')->fetchColumn(),
        'manual_active' => (int)$pdo->query('SELECT COUNT(*) FROM sportovci WHERE '
            . 'COALESCE(stav_manualni,0)=1 AND COALESCE(kis_aktivni,0)=1')->fetchColumn(),
    ];
}

function adminDashboardSeed(PDO $pdo, int $size, string $layout): void
{
    $pdo->exec('CREATE TABLE sportovci(id INT PRIMARY KEY,kis_neuhrazeno DECIMAL(10,2) NULL,
        kis_last_seen_at TIMESTAMP NULL,stav_manualni TINYINT NULL,kis_aktivni TINYINT NULL);
        CREATE TABLE sportovec_skupina(sportovec_id INT NOT NULL,skupina_id INT NOT NULL,
            PRIMARY KEY(sportovec_id,skupina_id))');
    $now = new DateTimeImmutable('2026-11-12 12:00:00', new DateTimeZone('UTC'));
    $cutoff = $now->modify('-90 days');
    $dates = [null, $cutoff->modify('-1 second')->format('Y-m-d H:i:s'), $cutoff->format('Y-m-d H:i:s'),
        $cutoff->modify('+1 second')->format('Y-m-d H:i:s'), $now->format('Y-m-d H:i:s'),
        $now->modify('+1 day')->format('Y-m-d H:i:s')];
    $person = $pdo->prepare('INSERT INTO sportovci VALUES(?,?,?,?,?)');
    $group = $pdo->prepare('INSERT INTO sportovec_skupina VALUES(?,?)');
    $pdo->beginTransaction();
    for ($id = 1; $id <= $size; $id++) {
        $person->execute([$id, [null, '-1.00', '0.00', '0.01', '129.99'][$id % 5],
            $dates[$id % count($dates)], [null, 0, 1, 2][$id % 4], [0, null, 1, 2, 1][$id % 5]]);
        if ($layout === 'grouped' || ($layout === 'mixed' && $id % 2 === 0)) {
            $group->execute([$id, 1]);
            $group->execute([$id, 2]);
        }
    }
    // Historical orphan assignments do not remove a different person from "without group".
    $group->execute([$size + 100, 99]);
    $pdo->commit();
}
