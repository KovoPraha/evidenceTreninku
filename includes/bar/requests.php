<?php

declare(strict_types=1);

require_once __DIR__ . '/management.php';

const BAR_CHECKOUT_MAX_MINOR = 999999999;

function barCheckMinor(int $minor): int
{
    if ($minor < 0 || $minor > BAR_CHECKOUT_MAX_MINOR) {
        throw new InvalidArgumentException('Částka je mimo povolený rozsah.');
    }
    return $minor;
}

/** The module lock precedes all actor locks, including while waiting for a competing writer. */
function barRunRequest(PDO $pdo, string $operation, string $token, array $request, callable $write, string $capability = 'sell'): array
{
    if (!preg_match('/^[a-f0-9]{64}$/D', $token)) {
        throw new InvalidArgumentException('Formulář nemá platný identifikátor. Načtěte stránku znovu.');
    }
    if ($pdo->inTransaction()) {
        throw new LogicException('Pokladní operace musí řídit vlastní transakci.');
    }
    $hash = hash('sha256', json_encode($request, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    $pdo->beginTransaction();
    try {
        $suffix = barLockState($pdo);
        $actor = barActor($pdo, $capability, true);
        $query = $pdo->prepare('SELECT * FROM kis_bar_requests WHERE token=?' . $suffix);
        $query->execute([$token]);
        $old = $query->fetch(PDO::FETCH_ASSOC);
        if ($old) {
            if ((int)$old['actor_id'] !== $actor['id'] || $old['operation'] !== $operation || !hash_equals($old['request_sha256'], $hash)) {
                throw new BarConflictException('Tento formulář již patří jiné operaci nebo uživateli.');
            }
            $result = json_decode($old['result_json'], true, 16, JSON_THROW_ON_ERROR);
            $pdo->commit();
            return $result + ['repeated' => true];
        }
        $result = $write($actor, $suffix);
        $pdo->prepare('INSERT INTO kis_bar_requests(token,actor_id,operation,request_sha256,result_json) VALUES(?,?,?,?,?)')
            ->execute([$token, $actor['id'], $operation, $hash, json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]);
        $pdo->commit();
        return $result + ['repeated' => false];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}
