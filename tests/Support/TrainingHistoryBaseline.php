<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;

/** Frozen cc805561 read sequence: full grouped result, canonical hierarchy, all labels/participants. */
final class TrainingHistoryBaseline
{
    public static function read(PDO $pdo, string $sql, array $params, int $team): array
    {
        $q = $pdo->prepare($sql . ',t.id DESC');
        $q->execute($params);
        $rows = $q->fetchAll(PDO::FETCH_ASSOC);
        $q->closeCursor();
        if ($team > 0) {
            $rows = \trainingRosterBridgeFilterTrainingRows($pdo, $rows, $team);
        }
        return ['rows' => $rows, 'labels' => \trainingRosterBridgeTrainingLabels($pdo, $rows),
            'participants' => TrainingHistoryFixture::participants($pdo, array_column($rows, 'id'))];
    }
}
