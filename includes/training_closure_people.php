<?php

declare(strict_types=1);

require_once __DIR__ . '/coach_scope.php';
require_once __DIR__ . '/staff_record_list.php';

/** The same full training-person universe, with roster preference only affecting order. */
function trainingClosurePersonPage(PDO $pdo, array $input): array
{
    coachTrainingRequireActor($pdo);
    $filters = staffListFilters(['picker_q' => staffListText($input, 'q'),
        'picker_page' => staffListText($input, 'page')], 'picker_', []);
    $params = [];
    $where = staffListWhere($filters, ['s.jmeno', 's.prijmeni', 's.id'], 's.id', null, $params);
    $offset = ($filters['page'] - 1) * 50;
    $q = $pdo->prepare('SELECT s.id,s.jmeno,s.prijmeni FROM sportovci s WHERE '
        . coachTrainingPersonPredicate($pdo) . ' AND ' . $where
        . ' ORDER BY CASE WHEN (' . coachTrainingPreferredPersonPredicate($pdo) . ') THEN 0 ELSE 1 END,s.prijmeni,s.jmeno,s.id'
        . ' LIMIT 51 OFFSET ' . $offset);
    $q->execute($params);
    $rows = $q->fetchAll(PDO::FETCH_ASSOC);
    $more = count($rows) > 50;
    return ['rows' => array_slice($rows, 0, 50), 'query' => $filters['q'], 'page' => $filters['page'], 'has_more' => $more];
}

/** Pin all existing choices independently of a search page; never fetch the remaining names. */
function trainingClosureSelectedPeople(PDO $pdo, array $ids): array
{
    coachTrainingRequireActor($pdo);
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
    if ($ids === []) {
        return [];
    }
    $scope = coachTrainingPersonPredicate($pdo);
    $preferred = coachTrainingPreferredPersonPredicate($pdo);
    $rows = [];
    foreach (array_chunk($ids, 500) as $chunk) {
        $marks = implode(',', array_fill(0, count($chunk), '?'));
        $q = $pdo->prepare('SELECT s.id,s.jmeno,s.prijmeni FROM sportovci s WHERE ' . $scope . " AND s.id IN ($marks)"
            . ' ORDER BY CASE WHEN (' . $preferred . ') THEN 0 ELSE 1 END,s.prijmeni,s.jmeno,s.id');
        $q->execute($chunk);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rows[(int)$row['id']] = $row;
        }
    }
    return $rows;
}

function trainingClosurePersonOptions(array $page, array $selected): array
{
    foreach ($page['rows'] as $person) {
        $selected[(int)$person['id']] = $person;
    }
    return $selected;
}

/** Search submits are read-only and retain the entire draft, including an empty selection. */
function trainingClosureSearchAction(array $post): bool
{
    return is_string($post['action'] ?? null)
        && preg_match('/^find_(?:participants|excuse_person)(?:_[1-9][0-9]{0,5})?$/D', $post['action']) === 1;
}

function trainingClosurePickerInput(array $post, string $kind): array
{
    $query = staffListText($post, $kind . '_q');
    $action = staffListText($post, 'action');
    $prefix = 'find_' . ($kind === 'participant' ? 'participants' : 'excuse_person');
    $page = str_starts_with($action, $prefix . '_') ? substr($action, strlen($prefix) + 1) : '1';
    // A changed term always starts at the first page, including native Enter/search submits.
    if ($query !== staffListText($post, $kind . '_last_q')) {
        $page = '1';
    }
    return ['q' => $query, 'page' => $page];
}

/** Native read-only POST keeps draft fields, selection and original concurrency version. */
function trainingClosurePeopleControls(string $kind, array $page): void
{
    $action = $kind === 'participant' ? 'find_participants' : 'find_excuse_person';
    ?>
<label for="<?=staffListH($kind)?>-person-query" class="form-label">Hledat sportovce podle jména nebo čísla</label>
<div class="input-group mb-2">
<input id="<?=staffListH($kind)?>-person-query" class="form-control" type="search" name="<?=staffListH($kind)?>_q" maxlength="100" value="<?=staffListH($page['query'])?>">
<button class="btn btn-outline-secondary" name="action" value="<?=$action?>" formnovalidate>Hledat sportovce</button>
</div>
<input type="hidden" name="<?=staffListH($kind)?>_last_q" value="<?=staffListH($page['query'])?>">
<p class="small" role="status">Strana <?=(int)$page['page']?> · výsledků na stránce: <?=count($page['rows'])?>.
Vybrané osoby zůstávají v nabídce i při hledání. Změna hledání docházku ani omluvu neukládá.</p>
<div class="mb-2">
<?php if ($page['page'] > 1): ?>
<button class="btn btn-outline-secondary btn-sm" name="action" value="<?=$action?>_<?=(int)$page['page'] - 1?>" formnovalidate>Předchozí osoby</button>
<?php endif; ?>
<?php if ($page['has_more']): ?>
<button class="btn btn-outline-secondary btn-sm" name="action" value="<?=$action?>_<?=(int)$page['page'] + 1?>" formnovalidate>Další osoby</button>
<?php endif; ?>
</div>
<?php
}
