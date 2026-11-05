<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/staff_workspaces.php';
require_once __DIR__ . '/time.php';

/** Actor snapshots are never authorization credentials: each service rechecks the live DB. */
function barActor(PDO $pdo, string $capability, bool $lock = false): array
{
    $positions = match ($capability) {
        'catalog_read', 'sell', 'own_consumption' => ['bar_operator', 'bar_admin'],
        'catalog_manage' => ['bar_admin'],
        'manage_permissions', 'transfer' => ['system_admin'],
        'stock_manage' => ['bar_admin'],
        'invoice_read', 'accounting' => ['bar_admin', 'finance_manager'],
        'invoice_manage', 'offers_manage' => ['bar_admin'],
        'cash_recovery' => ['bar_admin'],
        'request_status' => ['bar_operator', 'bar_admin', 'finance_manager'],
        default => throw new DomainException('Neznámé oprávnění baru.'),
    };
    $id = (int)($_SESSION['trener_id'] ?? 0);
    $position = (string)($_SESSION['staff_active_position'] ?? '');
    if ($id < 1 || !in_array($position, $positions, true)) {
        throw new DomainException('Pro tuto operaci nemáte aktivní pracovní pozici.');
    }
    if ($lock && !$pdo->inTransaction()) {
        throw new LogicException('Zamykající autorizace vyžaduje transakci.');
    }
    $suffix = $lock && $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
    $query = $pdo->prepare('SELECT id,aktivni,session_version FROM treneri WHERE id=?' . $suffix);
    $query->execute([$id]);
    $user = $query->fetch(PDO::FETCH_ASSOC);
    if (!$user || (int)$user['aktivni'] !== 1 || (int)$user['session_version'] !== (int)($_SESSION['trener_session_version'] ?? 0)) {
        throw new DomainException('Přihlášení již není platné. Přihlaste se znovu.');
    }
    $query = $pdo->prepare('SELECT p.position_code FROM staff_user_positions p JOIN staff_positions s ON s.code=p.position_code '
        . 'WHERE p.trainer_id=? AND p.position_code=? AND s.active=1' . $suffix);
    $query->execute([$id, $position]);
    $assigned = (bool)$query->fetchColumn();
    $query = $pdo->prepare('SELECT trainer_id FROM staff_superadmins WHERE trainer_id=?' . $suffix);
    $query->execute([$id]);
    $super = (bool)$query->fetchColumn();
    if (!$assigned && $super) {
        $query = $pdo->prepare('SELECT code FROM staff_positions WHERE code=? AND active=1' . $suffix);
        $query->execute([$position]);
        $assigned = (bool)$query->fetchColumn();
    }
    if (!$assigned) {
        throw new DomainException('Pracovní oprávnění bylo odebráno.');
    }
    if ($capability === 'own_consumption') {
        $query = $pdo->prepare("SELECT trainer_id FROM kis_bar_permissions WHERE trainer_id=? AND permission_code='own_consumption'" . $suffix);
        $query->execute([$id]);
        if (!$query->fetchColumn()) {
            throw new DomainException('Vlastní spotřeba není pro váš účet povolená.');
        }
    }
    return ['id' => $id, 'position' => $position];
}

function barEvent(PDO $pdo, int $actorId, string $entity, int $entityId, string $action, array $detail): void
{
    if (!$pdo->inTransaction()) {
        throw new LogicException('Audit musí vzniknout ve stejné transakci jako změna.');
    }
    $pdo->prepare('INSERT INTO kis_bar_events(actor_id,entity_type,entity_id,action,detail_json,created_at) VALUES(?,?,?,?,?,' . barSqlUtcNow($pdo) . ')')
        ->execute([$actorId, $entity, $entityId, $action, json_encode($detail, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]);
}

/** Only a system administrator may grant consumption; bar administration never implies it. */
function barSetConsumptionPermission(PDO $pdo, int $targetId, bool $enabled, string $reason): bool
{
    $reason = trim($reason);
    if ($targetId < 1 || mb_strlen($reason, 'UTF-8') < 5 || mb_strlen($reason, 'UTF-8') > 1000) {
        throw new InvalidArgumentException('Vyberte účet a uveďte důvod o délce 5 až 1000 znaků.');
    }
    $pdo->beginTransaction();
    try {
        // All grant mutations lock the same user rows in a deterministic order, including the actor.
        $ids = array_unique([$targetId, (int)($_SESSION['trener_id'] ?? 0)]);
        sort($ids, SORT_NUMERIC);
        $suffix = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $lock = $pdo->prepare('SELECT id,aktivni FROM treneri WHERE id=?' . $suffix);
        $target = false;
        foreach ($ids as $id) {
            $lock->execute([$id]);
            $row = $lock->fetch(PDO::FETCH_ASSOC);
            if ($id === $targetId) {
                $target = $row;
            }
        }
        $actor = barActor($pdo, 'manage_permissions', true);
        if (!$target || ($enabled && (int)$target['aktivni'] !== 1)) {
            throw new DomainException('Vyberte existující aktivní pracovní účet.');
        }
        $query = $pdo->prepare("SELECT trainer_id FROM kis_bar_permissions WHERE trainer_id=? AND permission_code='own_consumption'" . $suffix);
        $query->execute([$targetId]);
        $before = (bool)$query->fetchColumn();
        if ($before !== $enabled) {
            if ($enabled) {
                $pdo->prepare("INSERT INTO kis_bar_permissions(trainer_id,permission_code,granted_by,reason) VALUES(?,'own_consumption',?,?)")
                    ->execute([$targetId, $actor['id'], $reason]);
            } else {
                $pdo->prepare("DELETE FROM kis_bar_permissions WHERE trainer_id=? AND permission_code='own_consumption'")->execute([$targetId]);
            }
            barEvent($pdo, $actor['id'], 'permission', $targetId, $enabled ? 'consumption_granted' : 'consumption_revoked', ['reason' => $reason]);
        }
        $pdo->commit();
        return $before !== $enabled;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}
