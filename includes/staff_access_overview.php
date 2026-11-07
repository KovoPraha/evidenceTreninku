<?php

declare(strict_types=1);

require_once __DIR__ . '/staff_workspaces.php';

/** All working accounts, including inactive accounts and accounts without positions. */
function staffAccessOverview(PDO $pdo): array
{
    $accounts = [];
    $sql = 'SELECT t.id,t.jmeno,t.email,t.role,t.aktivni,s.trainer_id AS superadmin '
        . 'FROM treneri t LEFT JOIN staff_superadmins s ON s.trainer_id=t.id '
        . 'ORDER BY t.aktivni DESC,t.jmeno,t.id';
    foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $row['superadmin'] = $row['superadmin'] !== null;
        $row['positions'] = [];
        $row['default_position'] = '';
        $row['own_consumption'] = false;
        $accounts[(int)$row['id']] = $row;
    }
    foreach ($pdo->query('SELECT trainer_id,position_code,is_default FROM staff_user_positions ORDER BY position_code')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $id = (int)$row['trainer_id'];
        if (!isset($accounts[$id])) {
            continue;
        }
        $accounts[$id]['positions'][] = (string)$row['position_code'];
        if ((int)$row['is_default'] === 1) {
            $accounts[$id]['default_position'] = (string)$row['position_code'];
        }
    }
    foreach ($pdo->query("SELECT trainer_id FROM kis_bar_permissions WHERE permission_code='own_consumption'")->fetchAll(PDO::FETCH_COLUMN) as $id) {
        if (isset($accounts[(int)$id])) {
            $accounts[(int)$id]['own_consumption'] = true;
        }
    }
    return array_values($accounts);
}

/** Display groups only; authorization continues to use the canonical workspace registry. */
function staffAccessOverviewGroups(): array
{
    return [
        'sport' => ['label' => 'Trenéři a sport', 'codes' => ['coach', 'sports_lead']],
        'shop' => ['label' => 'E-shop', 'codes' => ['catalog_manager', 'order_operator']],
        'bar' => ['label' => 'Bar', 'codes' => ['bar_operator', 'bar_admin']],
        'other' => ['label' => 'Další přístupy', 'codes' => array_values(array_diff(staffPositionCodes(), [
            'coach', 'sports_lead', 'catalog_manager', 'order_operator', 'bar_operator', 'bar_admin',
        ]))],
    ];
}

function staffAccessOverviewFilter(array $accounts, string $query, string $role, string $status): array
{
    $query = mb_strtolower(trim($query), 'UTF-8');
    $groups = staffAccessOverviewGroups();
    return array_values(array_filter($accounts, static function (array $account) use ($query, $role, $status, $groups): bool {
        if ($status === 'active' && (int)$account['aktivni'] !== 1) {
            return false;
        }
        if ($status === 'inactive' && (int)$account['aktivni'] !== 0) {
            return false;
        }
        if ($query !== '' && !str_contains(mb_strtolower($account['jmeno'] . ' ' . $account['email'] . ' #' . $account['id'], 'UTF-8'), $query)) {
            return false;
        }
        if ($role === 'superadmin') {
            return $account['superadmin'];
        }
        if ($role === 'own_consumption') {
            return $account['own_consumption'];
        }
        if ($role === 'unassigned') {
            return !$account['superadmin'] && $account['positions'] === [];
        }
        if ($role === '') {
            return true;
        }
        $codes = $groups[$role]['codes'] ?? [$role];
        return array_intersect($codes, $account['positions']) !== []
            || ($account['superadmin'] && array_intersect($codes, staffPositionCodes()) !== []);
    }));
}
