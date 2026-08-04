<?php
declare(strict_types=1);

require_once __DIR__ . '/member_charge_read.php';

final class ChildAccessException extends RuntimeException
{
}

function childAccessTableExists(PDO $pdo, string $table): bool
{
    $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'mysql') {
        $statement = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES '
            . 'WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1'
        );
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    if ($driver === 'sqlite') {
        $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=? LIMIT 1");
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    return false;
}

function childAccessColumnExists(PDO $pdo, string $table, string $column): bool
{
    $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'mysql') {
        $statement = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1'
        );
        $statement->execute([$table, $column]);
        return (bool)$statement->fetchColumn();
    }
    if ($driver === 'sqlite') {
        foreach ($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC) as $definition) {
            if ((string)$definition['name'] === $column) {
                return true;
            }
        }
    }
    return false;
}

function childAccessNormalizeLogin(string $login): string
{
    $normalized = strtolower(trim($login));
    if (strlen($normalized) < 3 || strlen($normalized) > 120
        || preg_match('/\A[a-z0-9][a-z0-9._@+\-]*\z/D', $normalized) !== 1
    ) {
        throw new InvalidArgumentException(
            'Přihlašovací jméno musí mít 3–120 znaků a smí obsahovat jen písmena bez diakritiky, čísla, tečku, zavináč, plus, pomlčku a podtržítko.'
        );
    }
    return $normalized;
}

function childAccessValidatePassword(string $password): void
{
    if (strlen($password) < 12 || strlen($password) > 200) {
        throw new InvalidArgumentException('Heslo musí mít 12–200 znaků.');
    }
}

function childAccessValidateReason(string $reason): string
{
    $reason = trim($reason);
    if ($reason === '' || mb_strlen($reason, 'UTF-8') > 1000) {
        throw new InvalidArgumentException('Je vyžadován důvod o délce nejvýše 1000 znaků.');
    }
    return $reason;
}

function childAccessEvent(
    PDO $pdo,
    int $accessAccountId,
    string $actorType,
    ?int $actorId,
    string $action,
    string $note
): void {
    $allowedActors = ['trainer', 'athlete', 'system'];
    $allowedActions = ['create', 'password_reset', 'activate', 'deactivate', 'login', 'logout'];
    if ($accessAccountId < 1
        || !in_array($actorType, $allowedActors, true)
        || !in_array($action, $allowedActions, true)
    ) {
        throw new InvalidArgumentException('Neplatná auditní událost přístupu sportovce.');
    }
    $statement = $pdo->prepare(
        'INSERT INTO child_access_events '
        . '(access_account_id,actor_type,actor_id,action,note) VALUES (?,?,?,?,?)'
    );
    $statement->execute([$accessAccountId, $actorType, $actorId, $action, trim($note)]);
}

/** @return array{access_account_id:int,created:bool} */
function childAccessCreate(
    PDO $pdo,
    int $sportovecId,
    string $login,
    string $password,
    int $actorTrainerId,
    string $reason
): array {
    $loginKey = childAccessNormalizeLogin($login);
    childAccessValidatePassword($password);
    $reason = childAccessValidateReason($reason);
    if ($sportovecId < 1 || $actorTrainerId < 1) {
        throw new InvalidArgumentException('Chybí sportovec nebo administrátor.');
    }

    $pdo->beginTransaction();
    try {
        $person = $pdo->prepare('SELECT id FROM sportovci WHERE id=?');
        $person->execute([$sportovecId]);
        $trainer = $pdo->prepare('SELECT id FROM treneri WHERE id=? AND aktivni=1');
        $trainer->execute([$actorTrainerId]);
        if (!$person->fetchColumn() || !$trainer->fetchColumn()) {
            throw new ChildAccessException('Sportovec nebo aktivní administrátor nebyli nalezeni.');
        }
        $insert = $pdo->prepare(
            'INSERT INTO child_access_accounts '
            . '(sportovec_id,login_name,login_key,password_hash,active,session_version,'
            . 'password_changed_at,created_by_trainer_id) '
            . 'VALUES (?,?,?,?,1,1,CURRENT_TIMESTAMP,?)'
        );
        $insert->execute([
            $sportovecId,
            trim($login),
            $loginKey,
            password_hash($password, PASSWORD_DEFAULT),
            $actorTrainerId,
        ]);
        $accessAccountId = (int)$pdo->lastInsertId();
        childAccessEvent($pdo, $accessAccountId, 'trainer', $actorTrainerId, 'create', $reason);
        $pdo->commit();
        return ['access_account_id' => $accessAccountId, 'created' => true];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($exception instanceof InvalidArgumentException || $exception instanceof ChildAccessException) {
            throw $exception;
        }
        throw new ChildAccessException('Přístup sportovce se nepodařilo vytvořit.', 0, $exception);
    }
}

function childAccessResetPassword(
    PDO $pdo,
    int $accessAccountId,
    string $password,
    int $actorTrainerId,
    string $reason
): void {
    childAccessValidatePassword($password);
    childAccessChangeState($pdo, $accessAccountId, $actorTrainerId, 'password_reset', $reason, $password);
}

function childAccessSetActive(
    PDO $pdo,
    int $accessAccountId,
    bool $active,
    int $actorTrainerId,
    string $reason
): void {
    childAccessChangeState($pdo, $accessAccountId, $actorTrainerId, $active ? 'activate' : 'deactivate', $reason);
}

function childAccessChangeState(
    PDO $pdo,
    int $accessAccountId,
    int $actorTrainerId,
    string $action,
    string $reason,
    ?string $password = null
): void {
    $reason = childAccessValidateReason($reason);
    if ($accessAccountId < 1 || $actorTrainerId < 1) {
        throw new InvalidArgumentException('Chybí přístupový účet nebo administrátor.');
    }
    $pdo->beginTransaction();
    try {
        $sql = 'SELECT id,active FROM child_access_accounts WHERE id=?';
        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $sql .= ' FOR UPDATE';
        }
        $rowStatement = $pdo->prepare($sql);
        $rowStatement->execute([$accessAccountId]);
        $row = $rowStatement->fetch(PDO::FETCH_ASSOC);
        $trainer = $pdo->prepare('SELECT id FROM treneri WHERE id=? AND aktivni=1');
        $trainer->execute([$actorTrainerId]);
        if (!$row || !$trainer->fetchColumn()) {
            throw new ChildAccessException('Přístupový účet nebo aktivní administrátor nebyli nalezeni.');
        }

        if ($action === 'password_reset') {
            $update = $pdo->prepare(
                'UPDATE child_access_accounts SET password_hash=?, password_changed_at=CURRENT_TIMESTAMP, '
                . 'session_version=session_version+1, updated_at=CURRENT_TIMESTAMP WHERE id=?'
            );
            $update->execute([password_hash((string)$password, PASSWORD_DEFAULT), $accessAccountId]);
        } elseif ($action === 'activate') {
            if ((int)$row['active'] === 1) {
                $pdo->commit();
                return;
            }
            $update = $pdo->prepare(
                'UPDATE child_access_accounts SET active=1, session_version=session_version+1, '
                . 'updated_at=CURRENT_TIMESTAMP WHERE id=?'
            );
            $update->execute([$accessAccountId]);
        } elseif ($action === 'deactivate') {
            if ((int)$row['active'] === 0) {
                $pdo->commit();
                return;
            }
            $update = $pdo->prepare(
                'UPDATE child_access_accounts SET active=0, session_version=session_version+1, '
                . 'updated_at=CURRENT_TIMESTAMP WHERE id=?'
            );
            $update->execute([$accessAccountId]);
        } else {
            throw new InvalidArgumentException('Neplatná změna přístupu sportovce.');
        }
        childAccessEvent($pdo, $accessAccountId, 'trainer', $actorTrainerId, $action, $reason);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($exception instanceof InvalidArgumentException || $exception instanceof ChildAccessException) {
            throw $exception;
        }
        throw new ChildAccessException('Přístup sportovce se nepodařilo změnit.', 0, $exception);
    }
}

/** @return array<string,mixed>|null */
function childAccessAuthenticate(PDO $pdo, string $login, string $password): ?array
{
    try {
        $loginKey = childAccessNormalizeLogin($login);
    } catch (InvalidArgumentException) {
        return null;
    }
    $statement = $pdo->prepare(
        'SELECT a.id,a.sportovec_id,a.login_name,a.password_hash,a.session_version,'
        . 's.jmeno,s.prijmeni FROM child_access_accounts a '
        . 'JOIN sportovci s ON s.id=a.sportovec_id '
        . 'WHERE a.login_key=? AND a.active=1 LIMIT 1'
    );
    $statement->execute([$loginKey]);
    $account = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$account || !password_verify($password, (string)$account['password_hash'])) {
        return null;
    }
    unset($account['password_hash']);
    return $account;
}

function childAccessRecordLogin(PDO $pdo, int $accessAccountId): void
{
    $pdo->beginTransaction();
    try {
        $update = $pdo->prepare(
            'UPDATE child_access_accounts SET last_login_at=CURRENT_TIMESTAMP '
            . 'WHERE id=? AND active=1'
        );
        $update->execute([$accessAccountId]);
        if ($update->rowCount() !== 1) {
            throw new ChildAccessException('Přístup sportovce již není aktivní.');
        }
        childAccessEvent($pdo, $accessAccountId, 'athlete', $accessAccountId, 'login', 'Přihlášení sportovce.');
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

/** @return array<string,mixed>|null */
function childAccessIdentity(PDO $pdo, int $accessAccountId): ?array
{
    if ($accessAccountId < 1) {
        return null;
    }
    $statement = $pdo->prepare(
        'SELECT a.id AS access_account_id,a.sportovec_id,a.login_name,a.session_version,'
        . 's.jmeno,s.prijmeni,s.narozeni,s.stav_clenstvi '
        . 'FROM child_access_accounts a JOIN sportovci s ON s.id=a.sportovec_id '
        . 'WHERE a.id=? AND a.active=1 LIMIT 1'
    );
    $statement->execute([$accessAccountId]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

/** @return array{person:array<string,mixed>,rosters:list<array<string,mixed>>,events:list<array<string,mixed>>,trainings:list<array<string,mixed>>,payments:list<array<string,mixed>>,member_charges:list<array<string,mixed>>} */
function childAccessOverview(PDO $pdo, int $accessAccountId): array
{
    $person = childAccessIdentity($pdo, $accessAccountId);
    if ($person === null) {
        throw new ChildAccessException('Přístup sportovce není aktivní.');
    }

    $rosters = childAccessScopedRows($pdo, $accessAccountId, 'rosters');
    $events = childAccessScopedRows($pdo, $accessAccountId, 'events');
    $trainings = childAccessScopedRows($pdo, $accessAccountId, 'trainings');
    $payments = childAccessScopedRows($pdo, $accessAccountId, 'payments');
    $memberCharges = memberChargeRowsForSportovec($pdo, (int)$person['sportovec_id']);

    return compact('person', 'rosters', 'events', 'trainings', 'payments') + ['member_charges' => $memberCharges];
}

/** @return list<array<string,mixed>> */
function childAccessScopedRows(PDO $pdo, int $accessAccountId, string $section): array
{
    if ($accessAccountId < 1) {
        return [];
    }
    if ($section === 'rosters') {
        if (!childAccessHasTables($pdo, ['club_roster_members', 'club_teams', 'club_seasons'])) {
            return [];
        }
        $sql = 'SELECT m.status,m.valid_from,m.valid_to,t.name AS team_name,t.discipline,t.age_label,'
            . 's.name AS season_name FROM child_access_accounts a '
            . 'JOIN club_roster_members m ON m.sportovec_id=a.sportovec_id '
            . 'JOIN club_teams t ON t.id=m.team_id JOIN club_seasons s ON s.id=t.season_id '
            . 'WHERE a.id=? AND a.active=1 ORDER BY s.starts_on DESC,t.name,m.id';
    } elseif ($section === 'events') {
        if (!childAccessHasTables($pdo, ['club_event_registrations', 'club_events'])) {
            return [];
        }
        $sql = 'SELECT e.name AS event_name,e.event_type,e.status AS event_status,'
            . 'r.status,r.registered_at,r.cancelled_at FROM child_access_accounts a '
            . 'JOIN club_event_registrations r ON r.sportovec_id=a.sportovec_id '
            . 'JOIN club_events e ON e.id=r.event_id '
            . 'WHERE a.id=? AND a.active=1 ORDER BY r.registered_at DESC,r.id DESC';
    } elseif ($section === 'trainings') {
        if (!childAccessHasTables($pdo, ['trenink_sportovec', 'treninky'])) {
            return [];
        }
        $sql = 'SELECT t.datum,t.napln,t.delka,t.kategorie FROM child_access_accounts a '
            . 'JOIN trenink_sportovec ts ON ts.sportovec_id=a.sportovec_id '
            . 'JOIN treninky t ON t.id=ts.trenink_id '
            . 'WHERE a.id=? AND a.active=1 ORDER BY t.datum DESC,t.id DESC';
    } elseif ($section === 'payments') {
        if (!childAccessHasTables($pdo, ['shop_order_items', 'shop_orders'])
            || !childAccessColumnExists($pdo, 'shop_order_items', 'beneficiary_sportovec_id')
        ) {
            return [];
        }
        $sql = 'SELECT o.public_code,o.status AS order_status,o.payment_status,o.placed_at,'
            . 'i.product_name_snapshot,i.quantity,i.line_amount_minor,i.currency '
            . 'FROM child_access_accounts a '
            . 'JOIN shop_order_items i ON i.beneficiary_sportovec_id=a.sportovec_id '
            . 'JOIN shop_orders o ON o.id=i.order_id '
            . 'WHERE a.id=? AND a.active=1 ORDER BY o.placed_at DESC,o.id DESC,i.id';
    } else {
        throw new InvalidArgumentException('Neplatná část přehledu sportovce.');
    }
    $statement = $pdo->prepare($sql);
    $statement->execute([$accessAccountId]);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

/** @param list<string> $tables */
function childAccessHasTables(PDO $pdo, array $tables): bool
{
    foreach ($tables as $table) {
        if (!childAccessTableExists($pdo, $table)) {
            return false;
        }
    }
    return true;
}

/** @return list<array<string,mixed>> */
function childAccessAdminList(PDO $pdo): array
{
    return $pdo->query(
        'SELECT a.id,a.sportovec_id,a.login_name,a.active,a.session_version,a.last_login_at,'
        . 'a.password_changed_at,s.jmeno,s.prijmeni FROM child_access_accounts a '
        . 'JOIN sportovci s ON s.id=a.sportovec_id ORDER BY s.prijmeni,s.jmeno,a.id'
    )->fetchAll(PDO::FETCH_ASSOC);
}
