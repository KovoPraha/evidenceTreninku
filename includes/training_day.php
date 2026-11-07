<?php

declare(strict_types=1);
require_once __DIR__.'/training_closure.php';
require_once __DIR__.'/staff_record_list.php';

function trainingDayPlan(PDO $pdo, int $actor, int $planId): array
{
    trainingClosureAssertActor($pdo, $actor);
    $q = $pdo->prepare('SELECT * FROM planovane_treninky WHERE id=?');
    $q->execute([$planId]);
    $plan = $q->fetch(PDO::FETCH_ASSOC);
    if (!$plan) {
        throw new TrainingClosureException('Lekce nebyla nalezena.');
    }
    coachScopeAssertPlan($pdo, $planId, substitution: true);
    return $plan;
}

function trainingDayChoices(PDO $pdo, int $actor, string $search, string $on): array
{
    trainingClosureAssertActor($pdo, $actor);
    $f = staffListFilters(['q' => $search,'from' => $on,'to' => $on], '', []);
    $params = [];
    $where = staffListWhere($f, ['p.nazev','p.id'], 'p.datum', null, $params);
    $q = $pdo->prepare('SELECT p.* FROM planovane_treninky p WHERE '.$where.' ORDER BY CASE WHEN p.trener_id='.$actor
    .' THEN 0 ELSE 1 END,p.datum DESC,p.cas_od,p.id DESC LIMIT 50');
    $q->execute($params);
    $rows = $q->fetchAll(PDO::FETCH_ASSOC);
    if (!$pdo->inTransaction()) {
        return trainingDayReadCurrentChoices($pdo, $rows);
    }
    $out = [];
    foreach ($rows as $row) {
        try {
            coachScopeAssertPlan($pdo, (int)$row['id'], substitution: true);
            $out[] = $row;
        } catch (RuntimeException) {/* Employment is rechecked before use. */
        }
    }
    return $out;
}

/** Current GET projection only. Transactional callers retain the original loop.
 * @param list<array<string,mixed>> $rows @return list<array<string,mixed>>
 */
function trainingDayReadCurrentChoices(PDO $pdo, array $rows): array
{
    if ($rows === []) {
        return [];
    }
    try {
        $ids = array_map('intval', array_column($rows, 'id'));
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $q = $pdo->prepare("SELECT id FROM planovane_treninky WHERE id IN ($marks)");
        $q->execute($ids);
        $existing = array_fill_keys(array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN)), true);
        // Fresh employment check after the batch. Nothing is cached across calls;
        // trainingDayPlan and every writer still perform canonical current checks.
        coachTrainingRequireActor($pdo);
        return array_values(array_filter($rows, static fn (array $row): bool => isset($existing[(int) $row['id']])));
    } catch (RuntimeException) {
        // The original per-row loop also omits rows on revoked employment or a
        // failed current plan read. Initial actor/filter/query errors still escape.
        return [];
    }
}
