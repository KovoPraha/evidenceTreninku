<?php

declare(strict_types=1);

require_once __DIR__.'/staff_record_list.php';

/** Keep an explicit order selection distinct from the unfiltered workspace entry. */
function shopReturnAdminOrder(PDO $pdo, array $query, array $post): ?array
{
    $selected = null;
    foreach ([$query, $post] as $input) {
        if (!array_key_exists('order_id', $input)) {
            continue;
        }
        $value = is_scalar($input['order_id']) ? filter_var($input['order_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
        if ($value === false || ($selected !== null && $selected !== $value)) {
            throw new InvalidArgumentException('Neplatné nebo rozdílné číslo objednávky.');
        }
        $selected = $value;
    }
    if ($selected === null) {
        return null;
    }
    $q = $pdo->prepare('SELECT id,public_code FROM shop_orders WHERE id=?');
    $q->execute([$selected]);
    $order = $q->fetch(PDO::FETCH_ASSOC);
    if (!$order) {
        throw new OutOfBoundsException('Objednávka nebyla nalezena.');
    }
    return $order;
}

function shopReturnAdminList(PDO $pdo, ?array $order, int $page): array
{
    $where = $order === null ? '1=1' : 'c.order_id=?';
    return staffListPage(
        $pdo,
        'c.*,t.jmeno AS owner_name,o.public_code AS order_public_code',
        'FROM shop_return_cases c LEFT JOIN treneri t ON t.id=c.owner_trainer_id LEFT JOIN shop_orders o ON o.id=c.order_id WHERE '.$where,
        "CASE WHEN c.state='open' THEN 0 ELSE 1 END,c.next_due_on,c.id DESC",
        $order === null ? [] : [(int)$order['id']],
        $page
    );
}

/** A selected order must retain its older issues even beyond the global recent-issue list. */
function shopReturnAdminIssueIds(PDO $pdo, ?array $order): array
{
    $q = $pdo->prepare("SELECT id FROM shop_stock_movements WHERE kind='issue'"
        .($order === null ? '' : ' AND order_id=?').' ORDER BY id DESC'.($order === null ? ' LIMIT 200' : ''));
    $q->execute($order === null ? [] : [(int)$order['id']]);
    return $q->fetchAll(PDO::FETCH_COLUMN);
}

/** Reject a mismatched form before calling the existing domain mutation service. */
function shopReturnAdminAssertOrder(PDO $pdo, array $order, string $action, int $sourceId): void
{
    $sql = $action === 'open'
        ? "SELECT order_id FROM shop_stock_movements WHERE id=? AND kind='issue'"
        : 'SELECT order_id FROM shop_return_cases WHERE id=?';
    $q = $pdo->prepare($sql);
    $q->execute([$sourceId]);
    $sourceOrder = $q->fetchColumn();
    if ($sourceOrder === false || (int)$sourceOrder !== (int)$order['id']) {
        throw new DomainException('Výdej nebo případ nepatří do vybrané objednávky.');
    }
}

function shopReturnAdminPagination(array $listing, ?array $order): void
{
    echo '<p role="status">Nalezeno: '.(int)$listing['total'].' · strana '.(int)$listing['page'].' z '.(int)$listing['pages'].'</p>';
    foreach (['Předchozí' => $listing['page'] - 1,'Další' => $listing['page'] + 1] as $label => $page) {
        if ($page < 1 || $page > $listing['pages']) {
            continue;
        }
        $query = $order === null ? [] : ['order_id' => (int)$order['id']];
        $query['return_page'] = $page;
        echo '<a class="btn btn-outline-secondary btn-sm mb-3 me-2" href="?'.staffListH(http_build_query($query)).'">'.$label.'</a>';
    }
}
