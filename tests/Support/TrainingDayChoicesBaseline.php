<?php

declare(strict_types=1);

// Frozen from main 22e1b2cb before batched lesson reads.
require_once dirname(__DIR__, 2) . '/includes/training_day.php';
function beforeTrainingDayChoices(PDO $pdo, int $actor, string $search, string $on): array
{
    trainingClosureAssertActor($pdo, $actor);
    $f = staffListFilters(['q' => $search,'from' => $on,'to' => $on], '', []);
    $params = [];
    $where = staffListWhere($f, ['p.nazev','p.id'], 'p.datum', null, $params);
    $q = $pdo->prepare('SELECT p.* FROM planovane_treninky p WHERE '.$where.' ORDER BY CASE WHEN p.trener_id='.$actor
    .' THEN 0 ELSE 1 END,p.datum DESC,p.cas_od,p.id DESC LIMIT 50');
    $q->execute($params);
    $out = [];
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
        try {
            coachScopeAssertPlan($pdo, (int)$row['id'], substitution: true);
            $out[] = $row;
        } catch (RuntimeException) {/* Employment is rechecked before use. */
        }
    }
    return $out;
}
