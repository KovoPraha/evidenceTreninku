<?php
declare(strict_types=1);

require_once __DIR__.'/roster_hierarchy.php';

function kisRosterMappingName(string $name): string
{
    return mb_strtolower(trim((string)preg_replace('/\s+/u',' ', $name)), 'UTF-8');
}

/** Read-only suggestions. Legacy and KIS IDs belong to different tables and are never equated. */
function kisRosterLegacyMappingPreview(PDO $pdo, string $today): array
{
    $today = kisRosterDate($today);
    if (!kisRosterHierarchyHasTable($pdo,'skupiny') || !kisRosterHierarchyHasTable($pdo,'podskupiny')
        || !kisRosterHierarchyHasTable($pdo,'club_teams')) return ['rows'=>[],'counts'=>['exact'=>0,'ambiguous'=>0,'none'=>0]];
    $sources = [];
    foreach ($pdo->query('SELECT id,nazev FROM skupiny ORDER BY nazev,id')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sources[] = ['kind'=>'group','id'=>(int)$row['id'],'name'=>(string)$row['nazev'],'parent_name'=>null];
    }
    foreach ($pdo->query('SELECT p.id,p.nazev,s.nazev parent_name FROM podskupiny p JOIN skupiny s ON s.id=p.skupina_id ORDER BY s.nazev,p.nazev,p.id')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sources[] = ['kind'=>'roster','id'=>(int)$row['id'],'name'=>(string)$row['nazev'],'parent_name'=>(string)$row['parent_name']];
    }
    $sourceNameCounts = [];
    foreach ($sources as $source) $sourceNameCounts[$source['kind'].':'.kisRosterMappingName($source['name'])] = ($sourceNameCounts[$source['kind'].':'.kisRosterMappingName($source['name'])] ?? 0) + 1;
    $teams = $pdo->query("SELECT id,name,team_kind FROM club_teams WHERE status='active' ORDER BY name,id")->fetchAll(PDO::FETCH_ASSOC);
    $candidateIndex = [];
    foreach ($teams as $team) $candidateIndex[$team['team_kind'].':'.kisRosterMappingName((string)$team['name'])][] = ['id'=>(int)$team['id'],'name'=>(string)$team['name']];
    $futurePlans = ['group'=>[],'roster'=>[]];
    $historicalPlans = ['group'=>[],'roster'=>[]];
    if (kisRosterHierarchyHasTable($pdo,'planovane_treninky')) {
        if (kisRosterHierarchyHasColumn($pdo,'planovane_treninky','skupina_id')) {
            $stmt = $pdo->prepare("SELECT skupina_id,COUNT(DISTINCT id) amount FROM planovane_treninky WHERE datum>=? AND stav='planovany' AND skupina_id IS NOT NULL GROUP BY skupina_id");
            $stmt->execute([$today]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $futurePlans['group'][(int)$row['skupina_id']] = (int)$row['amount'];
            $stmt = $pdo->prepare("SELECT skupina_id,COUNT(DISTINCT id) amount FROM planovane_treninky WHERE datum<? AND stav<>'zruseny' AND skupina_id IS NOT NULL GROUP BY skupina_id");
            $stmt->execute([$today]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $historicalPlans['group'][(int)$row['skupina_id']] = (int)$row['amount'];
        }
        $subPlanIds = [];
        if (kisRosterHierarchyHasColumn($pdo,'planovane_treninky','podskupina_id')) {
            $stmt = $pdo->prepare("SELECT id,podskupina_id FROM planovane_treninky WHERE datum>=? AND stav='planovany' AND podskupina_id IS NOT NULL");
            $stmt->execute([$today]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $subPlanIds[(int)$row['podskupina_id']][(int)$row['id']] = true;
        }
        if (kisRosterHierarchyHasTable($pdo,'planovane_treninky_podskupiny')) {
            $stmt = $pdo->prepare("SELECT p.id,l.podskupina_id FROM planovane_treninky p JOIN planovane_treninky_podskupiny l ON l.plan_id=p.id WHERE p.datum>=? AND p.stav='planovany'");
            $stmt->execute([$today]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $subPlanIds[(int)$row['podskupina_id']][(int)$row['id']] = true;
        }
        foreach ($subPlanIds as $subgroupId => $ids) $futurePlans['roster'][$subgroupId] = count($ids);
        $subPlanIds = [];
        if (kisRosterHierarchyHasColumn($pdo,'planovane_treninky','podskupina_id')) {
            $stmt = $pdo->prepare("SELECT id,podskupina_id FROM planovane_treninky WHERE datum<? AND stav<>'zruseny' AND podskupina_id IS NOT NULL");
            $stmt->execute([$today]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $subPlanIds[(int)$row['podskupina_id']][(int)$row['id']] = true;
        }
        if (kisRosterHierarchyHasTable($pdo,'planovane_treninky_podskupiny')) {
            $stmt = $pdo->prepare("SELECT p.id,l.podskupina_id FROM planovane_treninky p JOIN planovane_treninky_podskupiny l ON l.plan_id=p.id WHERE p.datum<? AND p.stav<>'zruseny'");
            $stmt->execute([$today]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $subPlanIds[(int)$row['podskupina_id']][(int)$row['id']] = true;
        }
        foreach ($subPlanIds as $subgroupId => $ids) $historicalPlans['roster'][$subgroupId] = count($ids);
    }
    $attendance = ['group'=>[],'roster'=>[]];
    if (kisRosterHierarchyHasTable($pdo,'treninky') && kisRosterHierarchyHasTable($pdo,'trenink_sportovec')) {
        foreach (['group'=>['trenink_skupina','skupina_id'],'roster'=>['trenink_podskupina','podskupina_id']] as $kind => [$linkTable,$key]) {
            if (!kisRosterHierarchyHasTable($pdo,$linkTable)) continue;
            $stmt = $pdo->prepare("SELECT l.$key AS source_id,COUNT(DISTINCT ts.trenink_id || ':' || ts.sportovec_id) amount FROM $linkTable l JOIN treninky t ON t.id=l.trenink_id JOIN trenink_sportovec ts ON ts.trenink_id=t.id WHERE t.datum<? GROUP BY l.$key");
            if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql') {
                $stmt = $pdo->prepare("SELECT l.$key AS source_id,COUNT(DISTINCT ts.trenink_id,ts.sportovec_id) amount FROM $linkTable l JOIN treninky t ON t.id=l.trenink_id JOIN trenink_sportovec ts ON ts.trenink_id=t.id WHERE t.datum<? GROUP BY l.$key");
            }
            $stmt->execute([$today]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $attendance[$kind][(int)$row['source_id']] = (int)$row['amount'];
        }
    }
    $counts = ['exact'=>0,'ambiguous'=>0,'none'=>0];
    $rows = [];
    foreach ($sources as $source) {
        $key = $source['kind'].':'.kisRosterMappingName($source['name']);
        $candidates = $candidateIndex[$key] ?? [];
        $status = !$candidates ? 'none' : (count($candidates)===1 && $sourceNameCounts[$key]===1 ? 'exact' : 'ambiguous');
        $counts[$status]++;
        $source['status'] = $status;
        $source['candidates'] = $candidates;
        $source['future_plan_count'] = $futurePlans[$source['kind']][$source['id']] ?? 0;
        $source['historical_plan_count'] = $historicalPlans[$source['kind']][$source['id']] ?? 0;
        $source['historical_attendance_count'] = $attendance[$source['kind']][$source['id']] ?? 0;
        $rows[] = $source;
    }
    return ['rows'=>$rows,'counts'=>$counts];
}
