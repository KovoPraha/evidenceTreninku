<?php

declare(strict_types=1);

if (!function_exists('barActor')) {
    require_once __DIR__ . '/access.php';
}

/** The owner's supplied full name must identify exactly one active, existing system administrator. */
function barCutoverResolveAdministrator(PDO $pdo, string $requestedName): array
{
    $requestedName = mb_strtolower(trim($requestedName), 'UTF-8');
    if (!mb_check_encoding($requestedName, 'UTF-8') || mb_strlen($requestedName, 'UTF-8') < 3 || mb_strlen($requestedName, 'UTF-8') > 100) {
        throw new InvalidArgumentException('Zadejte přesné jméno schváleného správce.');
    }
    $query = $pdo->prepare('SELECT id,jmeno,session_version FROM treneri WHERE aktivni=1 AND LOWER(TRIM(jmeno))=?');
    $query->execute([$requestedName]);
    $rows = $query->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) !== 1) {
        throw new DomainException('Určeného správce nelze jednoznačně přiřadit k aktivnímu pracovnímu účtu.');
    }
    $id = (int)$rows[0]['id'];
    $query = $pdo->prepare("SELECT trainer_id FROM staff_superadmins WHERE trainer_id=? UNION
        SELECT p.trainer_id FROM staff_user_positions p JOIN staff_positions s ON s.code=p.position_code
        WHERE p.trainer_id=? AND p.position_code='system_admin' AND s.active=1");
    $query->execute([$id, $id]);
    if (!$query->fetchColumn()) {
        throw new DomainException('Určený účet nemá oprávnění provést systémový převod.');
    }
    return ['id' => $id, 'session_version' => (int)$rows[0]['session_version']];
}

/** Narrow audited addition. Existing positions, defaults and grants are never removed or rewritten. */
function barCutoverPermissionState(PDO $pdo, int $targetId): array
{
    $state = [];
    foreach (['bar_admin' => "SELECT trainer_id FROM staff_user_positions WHERE position_code='bar_admin'",
        'own_consumption' => "SELECT trainer_id FROM kis_bar_permissions WHERE permission_code='own_consumption'"] as $key => $sql) {
        $ids = array_map('intval', $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN));
        $state[$key] = in_array($targetId, $ids, true);
        $state[$key . '_only_requested'] = $ids === [$targetId];
    }
    return $state;
}

/** Narrow audited addition. Existing positions, defaults and grants are never removed or rewritten. */
function barCutoverGrantAdministrator(PDO $pdo, int $targetId): array
{
    barActor($pdo, 'manage_permissions');
    $pdo->beginTransaction();
    try {
        $suffix = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $ids = array_unique([$targetId, (int)($_SESSION['trener_id'] ?? 0)]);
        sort($ids, SORT_NUMERIC);
        $query = $pdo->prepare('SELECT id,aktivni FROM treneri WHERE id=?' . $suffix);
        foreach ($ids as $id) {
            $query->execute([$id]);
            $row = $query->fetch(PDO::FETCH_ASSOC);
            if (!$row || (int)$row['aktivni'] !== 1) {
                throw new DomainException('Pracovní účet již není aktivní.');
            }
        }
        $actor = barActor($pdo, 'manage_permissions', true);
        if (!$pdo->query("SELECT code FROM staff_positions WHERE code='bar_admin' AND active=1" . $suffix)->fetchColumn()) {
            throw new DomainException('Pracovní pozice správce baru není aktivní.');
        }
        $query = $pdo->prepare("SELECT COUNT(*) FROM staff_user_positions WHERE position_code='bar_admin' AND trainer_id<>?");
        $query->execute([$targetId]);
        if ((int)$query->fetchColumn() !== 0) {
            throw new DomainException('Správu baru již má jiný účet; existující práva se automaticky nemažou.');
        }
        $query = $pdo->prepare("SELECT COUNT(*) FROM kis_bar_permissions WHERE permission_code='own_consumption' AND trainer_id<>?");
        $query->execute([$targetId]);
        if ((int)$query->fetchColumn() !== 0) {
            throw new DomainException('Spotřebu již má jiný účet; existující práva se automaticky nemažou.');
        }
        $query = $pdo->prepare('SELECT position_code,is_default FROM staff_user_positions WHERE trainer_id=? ORDER BY position_code' . $suffix);
        $query->execute([$targetId]);
        $before = $query->fetchAll(PDO::FETCH_ASSOC);
        $added = !in_array('bar_admin', array_column($before, 'position_code'), true);
        $reason = 'Výslovný pokyn vlastníka 5. 11. 2026: prozatím pouze určený správce, správa baru a vlastní spotřeba.';
        if ($added) {
            $pdo->prepare("INSERT INTO staff_user_positions(trainer_id,position_code,is_default,assigned_by_trainer_id) VALUES(?,'bar_admin',?,?)")
                ->execute([$targetId, $before === [] ? 1 : 0, $actor['id']]);
            $query->execute([$targetId]);
            $after = $query->fetchAll(PDO::FETCH_ASSOC);
            $pdo->prepare('INSERT INTO staff_position_assignment_events(trainer_id,actor_trainer_id,action,before_json,after_json,reason) VALUES(?,?,?,?,?,?)')
                ->execute([$targetId, $actor['id'], 'bar_cutover_admin_granted', json_encode($before, JSON_THROW_ON_ERROR), json_encode($after, JSON_THROW_ON_ERROR), $reason]);
        }
        $query = $pdo->prepare("SELECT trainer_id FROM kis_bar_permissions WHERE trainer_id=? AND permission_code='own_consumption'" . $suffix);
        $query->execute([$targetId]);
        $consumptionAdded = !$query->fetchColumn();
        if ($consumptionAdded) {
            $pdo->prepare("INSERT INTO kis_bar_permissions(trainer_id,permission_code,granted_by,reason) VALUES(?,'own_consumption',?,?)")
                ->execute([$targetId, $actor['id'], $reason]);
            barEvent($pdo, $actor['id'], 'permission', $targetId, 'consumption_granted', ['reason' => $reason]);
        }
        $pdo->commit();
        return ['trainer_id' => $targetId, 'bar_admin' => true, 'own_consumption' => true,
            'admin_added' => $added, 'consumption_added' => $consumptionAdded];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}
