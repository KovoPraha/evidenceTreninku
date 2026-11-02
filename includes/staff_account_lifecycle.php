<?php

declare(strict_types=1);

final class StaffAccountLifecycleException extends RuntimeException
{
}
function staffAccountReason(string$reason): string
{
    $reason = trim((string)preg_replace('/\s+/u', ' ', $reason));
    if (mb_strlen($reason, 'UTF-8') < 5 || mb_strlen($reason, 'UTF-8') > 1000) {
        throw new InvalidArgumentException('Důvod musí mít 5 až 1000 znaků.');
    }return$reason;
}
function staffAccountSnapshot(array$row): array
{
    return array_intersect_key($row, array_flip(['id','jmeno','email','role','aktivni','session_version']));
}
function staffAccountEvent(PDO$pdo, int$trainerId, int$actorId, string$action, string$reason, ?array$before, array$after): void
{
    $pdo->prepare('INSERT INTO staff_account_events(trainer_id,actor_trainer_id,action,reason,before_json,after_json) VALUES (?,?,?,?,?,?)')->execute([
        $trainerId, $actorId, $action, $reason,
        $before === null ? null : json_encode(staffAccountSnapshot($before), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        json_encode(staffAccountSnapshot($after), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
    ]);
}
/** @return array{id:int,changed:bool,active:bool} */
function staffAccountSetActive(PDO$pdo, int$trainerId, int$actorId, bool$active, string$reason, bool$confirmed): array
{
    if (min($trainerId, $actorId) < 1 || !$confirmed) {
        throw new InvalidArgumentException('Změna stavu účtu vyžaduje důvod a potvrzení.');
    }$reason = staffAccountReason($reason);
    if (!$active && $trainerId === $actorId) {
        throw new StaffAccountLifecycleException('Vlastní účet nelze deaktivovat.');
    }$pdo->beginTransaction();
    try {
        $sql = 'SELECT * FROM treneri WHERE id=?';
        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $sql .= ' FOR UPDATE';
        }$s = $pdo->prepare($sql);
        $s->execute([$trainerId]);
        $before = $s->fetch(PDO::FETCH_ASSOC);
        if (!$before) {
            throw new StaffAccountLifecycleException('Pracovní účet nebyl nalezen.');
        }$target = $active ? 1 : 0;
        if ((int)$before['aktivni'] === $target) {
            $pdo->commit();
            return['id' => $trainerId,'changed' => false,'active' => $active];
        }if ($active) {
            $duplicate = $pdo->prepare('SELECT COUNT(*) FROM treneri WHERE aktivni=1 AND LOWER(TRIM(email))=LOWER(TRIM(?)) AND id<>?');
            $duplicate->execute([$before['email'],$trainerId]);
            if ((int)$duplicate->fetchColumn() > 0) {
                throw new StaffAccountLifecycleException('Účet nelze aktivovat, protože e-mail používá jiný aktivní účet.');
            }
        }$pdo->prepare('UPDATE treneri SET aktivni=?,session_version=session_version+1 WHERE id=?')->execute([$target,$trainerId]);
        $s = $pdo->prepare('SELECT * FROM treneri WHERE id=?');
        $s->execute([$trainerId]);
        $after = $s->fetch(PDO::FETCH_ASSOC);
        staffAccountEvent($pdo, $trainerId, $actorId, $active ? 'activate' : 'deactivate', $reason, $before, $after);
        $pdo->commit();
        return['id' => $trainerId,'changed' => true,'active' => $active];
    } catch (Throwable$e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }if ($e instanceof InvalidArgumentException || $e instanceof StaffAccountLifecycleException) {
            throw$e;
        }throw new StaffAccountLifecycleException('Stav účtu se nepodařilo změnit.', 0, $e);
    }
}

/** @return array{id:int,changed:bool,name:string} */
function staffAccountRename(PDO $pdo, int $trainerId, int $actorId, string $name, string $reason): array
{
    if (min($trainerId, $actorId) < 1) {
        throw new InvalidArgumentException('Vyberte platný pracovní účet.');
    }
    if (!mb_check_encoding($name, 'UTF-8')) {
        throw new InvalidArgumentException('Zadejte platné jméno.');
    }
    $name = trim((string)preg_replace('/\s+/u', ' ', $name));
    if ($name === '' || mb_strlen($name, 'UTF-8') > 100) {
        throw new InvalidArgumentException('Jméno musí mít 1 až 100 znaků.');
    }
    $reason = staffAccountReason($reason);

    $pdo->beginTransaction();
    try {
        $sql = 'SELECT * FROM treneri WHERE id=?';
        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $sql .= ' FOR UPDATE';
        }
        $statement = $pdo->prepare($sql);
        $statement->execute([$trainerId]);
        $before = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$before) {
            throw new StaffAccountLifecycleException('Pracovní účet nebyl nalezen.');
        }
        $changed = (string)$before['jmeno'] !== $name;
        if ($changed) {
            $pdo->prepare('UPDATE treneri SET jmeno=? WHERE id=?')->execute([$name, $trainerId]);
            $after = $before;
            $after['jmeno'] = $name;
            staffAccountEvent($pdo, $trainerId, $actorId, 'rename', $reason, $before, $after);
        }
        $pdo->commit();
        return ['id' => $trainerId, 'changed' => $changed, 'name' => $name];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($exception instanceof InvalidArgumentException || $exception instanceof StaffAccountLifecycleException) {
            throw $exception;
        }
        throw new StaffAccountLifecycleException('Jméno se nepodařilo změnit.', 0, $exception);
    }
}
