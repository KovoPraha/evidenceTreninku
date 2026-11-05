<?php

declare(strict_types=1);

require_once __DIR__ . '/roster_hierarchy.php';
require_once __DIR__ . '/coach_scope.php';

/**
 * One selector for both training and event targets. A selected group ID targets
 * its complete subtree at the event date; a selected roster ID targets its own
 * branch. The repeated roster rows under multiple parents share the same value.
 *
 * @param list<int> $selectedIds
 */
function rosterTargetSelectorHtml(PDO $pdo, string $date, array $selectedIds = [], string $fieldName = 'team_ids[]', bool $training = false): string
{
    static $instance = 0;
    ++$instance;
    try {
        $rows = kisRosterSelectableTeams($pdo, $date);
    } catch (InvalidArgumentException $exception) {
        return '<div class="alert alert-warning mb-0">Nejprve zadejte platné datum tréninku nebo akce.</div>';
    }
    $selected = array_fill_keys(array_map('intval', $selectedIds), true);
    $scopeAllowed = null;
    if ($training) {
        if (!coachTrainingActorAllowed($pdo)) {
            $rows = [];
            $selected = [];
        }
    } elseif (staffActivePositionIs('coach')) {
        $scopeAllowed = [];
        foreach (array_unique(array_merge(array_column($rows, 'id'), $selectedIds)) as $id) {
            try {
                coachScopeAssertTeams($pdo, [(int)$id], $date);
                $scopeAllowed[(int)$id] = true;
            } catch (RuntimeException) {
            }
        }
        $rows = array_values(array_filter($rows, static fn (array $row): bool => isset($scopeAllowed[(int)$row['id']])));
        $selected = array_intersect_key($selected, $scopeAllowed);
    }
    $nameIds = [];
    foreach ($rows as $row) {
        $nameIds[(string)$row['name']][(int)$row['id']] = true;
    }
    $labelFor = static function (int $id, string $name) use ($nameIds): string {
        return count($nameIds[$name] ?? []) > 1 ? $name . ' (#' . $id . ')' : $name;
    };
    if ($training) {
        return rosterTrainingTargetSelectorHtml($pdo, $date, $rows, $selected, $fieldName, $instance);
    }
    $groups = [];
    $unassigned = [];
    foreach ($rows as $row) {
        $id = (int)$row['id'];
        if (!empty($row['is_group'])) {
            $groups[$id] ??= ['name' => (string)$row['name'], 'children' => []];
            continue;
        }
        $parentId = (int)($row['group_id'] ?? 0);
        if ($parentId > 0) {
            $groups[$parentId] ??= ['name' => (string)($row['group_name'] ?? ''), 'children' => []];
            $groups[$parentId]['children'][$id] = (string)$row['name'];
        } else {
            $unassigned[$id] = (string)$row['name'];
        }
    }
    $visibleIds = [];
    foreach ($rows as $row) {
        $visibleIds[(int)$row['id']] = true;
    }
    $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $name = $escape($fieldName);
    $html = '<div class="roster-target-selector border rounded p-2 bg-white" role="group" aria-label="Skupiny a soupisky">';
    foreach ($groups as $groupId => $group) {
        $inputId = 'roster-target-' . $instance . '-' . $groupId;
        $html .= '<fieldset class="border rounded p-2 mb-2"><legend class="float-none w-auto fs-6 px-1 mb-0">'
            . $escape($labelFor($groupId, $group['name'])) . '</legend>';
        $html .= '<div class="form-check"><input class="form-check-input" type="checkbox" name="' . $name
            . '" value="' . $groupId . '" id="' . $inputId . '"' . (isset($selected[$groupId]) ? ' checked' : '')
            . ($scopeAllowed !== null && !isset($scopeAllowed[$groupId]) ? ' disabled' : '')
            . '><label class="form-check-label fw-semibold" for="' . $inputId . '">Celá skupina</label></div>';
        foreach ($group['children'] as $childId => $childName) {
            $childInputId = 'roster-target-' . $instance . '-' . $groupId . '-' . $childId;
            $html .= '<div class="form-check ms-3"><input class="form-check-input" type="checkbox" name="' . $name
                . '" value="' . $childId . '" id="' . $childInputId . '"' . (isset($selected[$childId]) ? ' checked' : '')
                . '><label class="form-check-label" for="' . $childInputId . '">' . $escape($labelFor($childId, $childName))
    . '</label></div>';
        }
        $html .= '</fieldset>';
    }
    if ($unassigned !== []) {
        $html .= '<fieldset class="border rounded p-2"><legend class="float-none w-auto fs-6 px-1 mb-0">Soupisky bez skupiny</legend>';
        foreach ($unassigned as $id => $label) {
            $inputId = 'roster-target-' . $instance . '-unassigned-' . $id;
            $html .= '<div class="form-check"><input class="form-check-input" type="checkbox" name="' . $name
                . '" value="' . $id . '" id="' . $inputId . '"' . (isset($selected[$id]) ? ' checked' : '')
                . '><label class="form-check-label" for="' . $inputId . '">' . $escape($labelFor($id, $label))
    . '</label></div>';
        }
        $html .= '</fieldset>';
    }
    $historicalIds = array_values(array_diff(array_keys($selected), array_keys($visibleIds)));
    if ($historicalIds !== []) {
        $marks = implode(',', array_fill(0, count($historicalIds), '?'));
        $stmt = $pdo->prepare("SELECT id,name FROM club_teams WHERE id IN ($marks) ORDER BY name,id");
        $stmt->execute($historicalIds);
        $historical = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($historical !== []) {
            $html .= '<fieldset class="border rounded p-2"><legend class="float-none w-auto fs-6 px-1 mb-0">Historické cíle</legend>';
            foreach ($historical as $team) {
                $id = (int)$team['id'];
                $inputId = 'roster-target-' . $instance . '-historical-' . $id;
                $html .= '<div class="form-check"><input class="form-check-input" type="checkbox" name="' . $name
                    . '" value="' . $id . '" id="' . $inputId . '" checked><label class="form-check-label" for="'
                    . $inputId . '">' . $escape((string)$team['name']) . ' (#' . $id . ', již neaktivní)</label></div>';
            }
            $html .= '</fieldset>';
        }
    }
    if ($groups === [] && $unassigned === []) {
        $html .= '<span class="text-muted small">Pro vybrané datum nejsou dostupné skupiny ani soupisky.</span>';
    }
    return $html . '</div>';
}

/** One checkbox per target; assigned teams come first while every active target stays available. */
function rosterTrainingTargetSelectorHtml(PDO $pdo, string $date, array $rows, array $selected, string $fieldName, int $instance): string
{
    $preferred = coachTrainingPreferredTeams($pdo, $date);
    $targets = [];
    foreach ($rows as $row) {
        $id = (int)$row['id'];
        $targets[$id] ??= ['name' => (string)$row['name'], 'group' => !empty($row['is_group']), 'parents' => []];
        if (empty($row['is_group']) && !empty($row['group_name'])) {
            $targets[$id]['parents'][(string)$row['group_name']] = true;
        }
    }
    $labelCounts = [];
    foreach ($targets as &$target) {
        $parent = implode(' / ', array_keys($target['parents']));
        $target['label'] = ($parent !== '' ? $parent . ' → ' : '') . $target['name']
            . ($target['group'] ? ' (celá skupina)' : '');
        $labelCounts[$target['label']] = ($labelCounts[$target['label']] ?? 0) + 1;
    }
    unset($target);
    foreach ($targets as $id => &$target) {
        if ($labelCounts[$target['label']] > 1) {
            $target['label'] .= ' (#' . $id . ')';
        }
    }
    unset($target);
    uasort($targets, static fn (array $a, array $b): int => strnatcasecmp($a['label'], $b['label']));
    $escape = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $html = '<div class="roster-target-selector border rounded p-2 bg-white" role="group" aria-label="Skupiny a soupisky">';
    foreach ([true => 'Moje skupiny a soupisky', false => 'Další skupiny a soupisky'] as $own => $heading) {
        $section = '';
        foreach ($targets as $id => $target) {
            if (isset($preferred[$id]) !== (bool)$own) {
                continue;
            }
            $input = 'training-roster-' . $instance . '-' . $id;
            $section .= '<div class="form-check"><input class="form-check-input" type="checkbox" name="' . $escape($fieldName)
                . '" value="' . $id . '" id="' . $input . '"' . (isset($selected[$id]) ? ' checked' : '') . '>'
                . '<label class="form-check-label" for="' . $input . '">' . $escape($target['label']) . '</label></div>';
        }
        if ($section !== '') {
            $html .= '<fieldset class="mb-2"><legend class="float-none w-auto fs-6 fw-semibold">' . $heading . '</legend>'
    . $section . '</fieldset>';
        }
    }
    if ($targets === []) {
        $html .= '<span class="text-muted small">Pro vybrané datum nejsou dostupné skupiny ani soupisky.</span>';
    }
    return $html . '<div class="form-text">Vlastní přiřazení určuje pořadí. Pro zastupování můžete vybrat i ostatní soupisky.</div></div>';
}

/** HTML options for a single group or roster filter. */
function rosterTargetFilterOptionsHtml(PDO $pdo, string $date, int $selectedId = 0): string
{
    $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $html = '<option value="">— všechny skupiny a soupisky —</option>';
    $seen = [];
    try {
        $rows = kisRosterSelectableTeams($pdo, $date);
    } catch (InvalidArgumentException $exception) {
        return $html;
    }
    $nameIds = [];
    foreach ($rows as $row) {
        $nameIds[(string)$row['name']][(int)$row['id']] = true;
    }
    foreach ($rows as $row) {
        $id = (int)$row['id'];
        if (isset($seen[$id])) {
            continue;
        }
        $seen[$id] = true;
        $label = !empty($row['is_group']) ? 'Skupina: ' . (string)$row['name'] : 'Soupiska: ' . (string)$row['name'];
        if (count($nameIds[(string)$row['name']]) > 1) {
            $label .= ' (#' . $id . ')';
        }
        $html .= '<option value="' . $id . '"' . ($selectedId === $id ? ' selected' : '') . '>' . $escape($label)
    . '</option>';
    }
    if ($selectedId > 0 && !isset($seen[$selectedId])) {
        $stmt = $pdo->prepare('SELECT name FROM club_teams WHERE id=?');
        $stmt->execute([$selectedId]);
        $name = $stmt->fetchColumn();
        if (is_string($name)) {
            $html .= '<option value="' . $selectedId . '" selected>Historický cíl: ' . $escape($name) . ' (#' . $selectedId
    . ')</option>';
        }
    }
    return $html;
}
