<?php

declare(strict_types=1);

/** New organizational parents only. Never merge memberships or reuse an existing scoped parent. */
function memberEvidenceGroupsPreview(PDO $pdo, array $config, array $teams, string $on): array
{
    if (!isset($config['groups'])) {
        return [];
    }
    $groups = [];
    $assigned = [];
    $codes = array_fill_keys(array_column($teams, 'standing_code'), true);
    $dates = [$on, (new DateTimeImmutable($on))->modify('+1 day')->format('Y-m-d'),
        (new DateTimeImmutable($on))->modify('+1 year')->format('Y-m-d')];
    foreach ($config['groups'] as $item) {
        $code = kisRosterCode((string) ($item['standing_code'] ?? ''));
        $name = kisRosterText((string) ($item['name'] ?? ''), 160, 'Název skupiny');
        $activity = kisRosterEnum((string) ($item['activity_kind'] ?? ''), ['circle', 'competitive', 'other'], 'Zaměření');
        $query = $pdo->prepare('SELECT id FROM club_teams WHERE code=? OR standing_code=?');
        $query->execute([$code, $code]);
        if (isset($codes[$code]) || $query->fetchColumn()) {
            throw new RuntimeException('Kód nové skupiny již existuje nebo koliduje s mapou.');
        }
        $codes[$code] = true;
        $children = array_map('intval', (array) ($item['child_ids'] ?? []));
        sort($children, SORT_NUMERIC);
        if (!$children || count($children) !== count(array_unique($children))) {
            throw new InvalidArgumentException('Skupina vyžaduje neprázdný seznam jedinečných soupisek.');
        }
        foreach ($children as $id) {
            if (!isset($teams[$id]) || isset($assigned[$id])) {
                throw new RuntimeException('Každá převáděná soupiska musí patřit právě do jedné nové skupiny.');
            }
            $assigned[$id] = true;
            $query = $pdo->prepare('SELECT id FROM club_team_edges WHERE parent_team_id=? OR child_team_id=?');
            $query->execute([$id, $id]);
            if ($query->fetchColumn()) {
                throw new RuntimeException('Existující organizační vazby vyžadují samostatné vypořádání.');
            }
        }
        $membership = [];
        foreach ($dates as $date) {
            $ids = [];
            foreach ($children as $id) {
                foreach (kisRosterEffectiveMemberIds($pdo, $id, $date) as $person) {
                    $ids[$person] = true;
                }
            }
            $ids = array_keys($ids);
            sort($ids, SORT_NUMERIC);
            $membership[$date] = ['count' => count($ids), 'sha256' => hash('sha256', json_encode($ids, JSON_THROW_ON_ERROR))];
        }
        $groups[$code] = ['standing_code' => $code, 'name' => $name, 'activity_kind' => $activity,
            'child_ids' => $children, 'membership' => $membership];
    }
    if (count($assigned) !== count($teams)) {
        throw new RuntimeException('V nové hierarchii chybí některá převáděná soupiska.');
    }
    ksort($groups);
    return array_values($groups);
}

function memberEvidenceGroupsApply(PDO $pdo, array $groups, string $on, int $actor, string $reason): array
{
    $created = [];
    foreach ($groups as $group) {
        $parent = kisRosterCreateStandingTeam($pdo, $actor, $group['standing_code'], $group['name'], 'group', $reason);
        $pdo->prepare('UPDATE club_teams SET activity_kind=? WHERE id=?')->execute([$group['activity_kind'], $parent['id']]);
        $preview = kisRosterPreviewHierarchy($pdo, $parent['id'], $group['child_ids'], $on);
        kisRosterAttachChildren($pdo, $parent['id'], $group['child_ids'], $on, $actor, $reason, $preview['fingerprint']);
        foreach ($group['membership'] as $date => $expected) {
            $members = kisRosterEffectiveMemberIds($pdo, $parent['id'], $date);
            $actual = ['count' => count($members), 'sha256' => hash('sha256', json_encode($members, JSON_THROW_ON_ERROR))];
            if ($expected !== $actual) {
                throw new RuntimeException('Parita členství nové skupiny selhala.');
            }
        }
        $created[$group['standing_code']] = $parent['id'];
    }
    return $created;
}
