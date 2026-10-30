<?php

declare(strict_types=1);

require_once __DIR__ . '/TrainingRosterFilterBaseline.php';

function w82CopyCountBaseline(PDO $pdo, string $from, string $to, bool $filterJenMoje, int $trenerId, int $filterTrener, int $filterSkupina, int $filterTeam): int
{
    $monday = new DateTimeImmutable($from);
    $sunday = new DateTimeImmutable($to);
    // Počet plánů k okopírování (stejné filtry jako aktuální zobrazení)
    $kStatsWhere  = ["datum BETWEEN ? AND ?", "stav = 'planovany'"];
    $kStatsParams = [$monday->format('Y-m-d'), $sunday->format('Y-m-d')];
    if ($filterJenMoje) {
        $kStatsWhere[] = 'trener_id = ?';
        $kStatsParams[] = $trenerId;
    } elseif ($filterTrener) {
        $kStatsWhere[] = 'trener_id = ?';
        $kStatsParams[] = $filterTrener;
    }
    if ($filterSkupina) {
        $kStatsWhere[] = 'skupina_id = ?';
        $kStatsParams[] = $filterSkupina;
    }
    $kCount = (int)$pdo->prepare("SELECT COUNT(*) FROM planovane_treninky WHERE " . implode(' AND ', $kStatsWhere))
                        ->execute($kStatsParams) ? $pdo->query("SELECT FOUND_ROWS()")->fetchColumn() : 0;
    // Přímý count
    $stKC = $pdo->prepare("SELECT COUNT(*) FROM planovane_treninky WHERE " . implode(' AND ', $kStatsWhere));
    $stKC->execute($kStatsParams);
    $kCount = (int)$stKC->fetchColumn();
    if ($filterTeam > 0) {
        $countQuery = $pdo->prepare("SELECT id,datum FROM planovane_treninky WHERE " . implode(' AND ', $kStatsWhere));
        $countQuery->execute($kStatsParams);
        $kCount = count(w82FilterPlanBaseline($pdo, $countQuery->fetchAll(PDO::FETCH_ASSOC), $filterTeam));
    }
    return $kCount;
}
