<?php
declare(strict_types=1);

require_once __DIR__ . '/account_person_role.php';
require_once __DIR__ . '/public_profile_token.php';

final class PublicProfileException extends RuntimeException
{
}

/** @return array<string,mixed>|null */
function publicProfileForAccount(PDO $pdo, int $accountId): ?array
{
    if ($accountId < 1) {
        return null;
    }
    $statement = $pdo->prepare(
        'SELECT p.account_id,p.sportovec_id,p.created_at,p.updated_at, '
        . 's.jmeno,s.prijmeni,s.narozeni,s.email,s.telefon,vu.email AS account_email,vu.telefon AS account_phone '
        . 'FROM public_self_profiles p JOIN sportovci s ON s.id=p.sportovec_id '
        . 'JOIN verejni_uzivatele vu ON vu.id=p.account_id WHERE p.account_id=?'
    );
    $statement->execute([$accountId]);
    return $statement->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Creates or updates exactly one self-owned sportovec record for an account.
 * @return array{account_id:int,sportovec_id:int,created:bool}
 */
function publicProfileSave(
    PDO $pdo,
    int $accountId,
    string $firstName,
    string $lastName,
    string $birthDate,
    string $phone
): array {
    $firstName = trim($firstName);
    $lastName = trim($lastName);
    $birthDate = trim($birthDate);
    $phone = trim($phone);
    if ($accountId < 1 || $firstName === '' || $lastName === '') {
        throw new InvalidArgumentException('Profil vyžaduje účet, jméno a příjmení.');
    }
    if (mb_strlen($firstName, 'UTF-8') > 100 || mb_strlen($lastName, 'UTF-8') > 100
        || preg_match('/[<>]/u', $firstName . $lastName) === 1
    ) {
        throw new InvalidArgumentException('Jméno není v povoleném formátu.');
    }
    $birth = DateTimeImmutable::createFromFormat('!Y-m-d', $birthDate);
    if (!$birth || $birth->format('Y-m-d') !== $birthDate
        || $birth > new DateTimeImmutable('today') || $birth < new DateTimeImmutable('1900-01-01')
    ) {
        throw new InvalidArgumentException('Zadejte platné datum narození.');
    }
    if ($phone !== '' && (mb_strlen($phone, 'UTF-8') > 50 || preg_match('/^[0-9+ ()-]+$/D', $phone) !== 1)) {
        throw new InvalidArgumentException('Telefon není v povoleném formátu.');
    }

    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->beginTransaction();
    }
    try {
        $accountSql = 'SELECT * FROM verejni_uzivatele WHERE id=?';
        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $accountSql .= ' FOR UPDATE';
        }
        $accountStatement = $pdo->prepare($accountSql);
        $accountStatement->execute([$accountId]);
        $account = $accountStatement->fetch(PDO::FETCH_ASSOC);
        if (!$account || (int)$account['aktivni'] !== 1 || !filter_var($account['email'], FILTER_VALIDATE_EMAIL)) {
            throw new PublicProfileException('Aktivní účet s platným kontaktním e-mailem nebyl nalezen.');
        }

        $profileStatement = $pdo->prepare('SELECT * FROM public_self_profiles WHERE account_id=?');
        $profileStatement->execute([$accountId]);
        $profile = $profileStatement->fetch(PDO::FETCH_ASSOC);
        $created = false;
        if ($profile) {
            $sportovecId = (int)$profile['sportovec_id'];
            $update = $pdo->prepare(
                'UPDATE sportovci SET jmeno=?,prijmeni=?,narozeni=?,email=?,telefon=? WHERE id=?'
            );
            $update->execute([
                $firstName, $lastName, $birthDate, (string)$account['email'], $phone ?: null, $sportovecId,
            ]);
            $pdo->prepare('UPDATE public_self_profiles SET updated_at=CURRENT_TIMESTAMP WHERE account_id=?')
                ->execute([$accountId]);
            $action = 'update';
        } else {
            $existingSelf = publicProfileExistingApprovedSelf($pdo, $accountId);
            if ($existingSelf !== null) {
                $sportovecId = $existingSelf;
                $pdo->prepare(
                    'UPDATE sportovci SET jmeno=?,prijmeni=?,narozeni=?,email=?,telefon=? WHERE id=?'
                )->execute([
                    $firstName,
                    $lastName,
                    $birthDate,
                    (string)$account['email'],
                    $phone ?: null,
                    $sportovecId,
                ]);
                $action = 'adopt_approved_self';
            } else {
                $hash = public_profile_token_generate();
                $collision = $pdo->prepare('SELECT id FROM sportovci WHERE hash=?');
                $collision->execute([$hash]);
                if ($collision->fetchColumn()) {
                    throw new PublicProfileException('Identita veřejného profilu koliduje s existující osobou.');
                }
                $insert = $pdo->prepare(
                    'INSERT INTO sportovci '
                    . '(jmeno,prijmeni,narozeni,email,telefon,hash,uci,stav_clenstvi) '
                    . "VALUES (?,?,?,?,?, ?,0,'cekajici')"
                );
                $insert->execute([
                    $firstName, $lastName, $birthDate, (string)$account['email'], $phone ?: null, $hash,
                ]);
                $sportovecId = (int)$pdo->lastInsertId();
                $action = 'create';
                $created = true;
            }
            $pdo->prepare('INSERT INTO public_self_profiles(account_id,sportovec_id) VALUES (?,?)')
                ->execute([$accountId, $sportovecId]);
        }
        $pdo->prepare('UPDATE verejni_uzivatele SET telefon=? WHERE id=?')
            ->execute([$phone ?: null, $accountId]);
        publicProfileEnsureCanonicalSelf($pdo, $accountId, $sportovecId);
        $payload = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'birth_date' => $birthDate,
            'contact_email' => (string)$account['email'],
            'contact_phone' => $phone !== '' ? $phone : null,
        ];
        $pdo->prepare(
            'INSERT INTO public_profile_events(account_id,sportovec_id,action,payload_json) VALUES (?,?,?,?)'
        )->execute([
            $accountId,
            $sportovecId,
            $action,
            json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        if ($ownsTransaction) {
            $pdo->commit();
        }
        return ['account_id' => $accountId, 'sportovec_id' => $sportovecId, 'created' => $created];
    } catch (Throwable $exception) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($exception instanceof InvalidArgumentException || $exception instanceof PublicProfileException) {
            throw $exception;
        }
        throw new PublicProfileException('Profil se nepodařilo uložit bez částečné změny.', 0, $exception);
    }
}

function publicProfileEnsureCanonicalSelf(PDO $pdo, int $accountId, int $sportovecId): void
{
    $active = $pdo->prepare(
        "SELECT sportovec_id FROM account_person_roles WHERE account_id=? AND relation_role='self' "
        . "AND status='approved' AND valid_from<=CURRENT_TIMESTAMP "
        . 'AND (valid_to IS NULL OR valid_to>CURRENT_TIMESTAMP) ORDER BY sportovec_id'
    );
    $active->execute([$accountId]);
    $activeIds = array_map('intval', $active->fetchAll(PDO::FETCH_COLUMN));
    if (count($activeIds) > 1 || ($activeIds !== [] && $activeIds[0] !== $sportovecId)) {
        throw new PublicProfileException('Kanonická self vazba účtu je v konfliktu s veřejným profilem.');
    }
    $actorId = (int)$pdo->query(
        'SELECT system_trainer_id FROM public_profile_settings WHERE singleton_id=1'
    )->fetchColumn();
    if ($actorId < 1) {
        throw new PublicProfileException('Chybí auditní identita automatu veřejných profilů.');
    }
    $relationSql = 'SELECT * FROM account_person_roles WHERE account_id=? AND sportovec_id=?';
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $relationSql .= ' FOR UPDATE';
    }
    $relationStatement = $pdo->prepare($relationSql);
    $relationStatement->execute([$accountId, $sportovecId]);
    $relation = $relationStatement->fetch(PDO::FETCH_ASSOC);
    if ($relation && $relation['relation_role'] !== 'self') {
        throw new PublicProfileException('Osoba je k účtu připojena jinou kanonickou rolí.');
    }
    if ($relation && $relation['status'] === 'approved' && $relation['valid_to'] === null) {
        return;
    }
    $fromStatus = $relation ? (string)$relation['status'] : null;
    if ($relation) {
        $pdo->prepare(
            "UPDATE account_person_roles SET relation_role='self',status='approved',source='self_registration', "
            . 'valid_from=CURRENT_TIMESTAMP,valid_to=NULL,created_by_trainer_id=?,approved_by_trainer_id=?, '
            . "decision_note='Automaticky ověřená self vazba veřejného účtu.',updated_at=CURRENT_TIMESTAMP WHERE id=?"
        )->execute([$actorId, $actorId, (int)$relation['id']]);
        $relationId = (int)$relation['id'];
        $action = 'self_reactivate';
    } else {
        $pdo->prepare(
            'INSERT INTO account_person_roles '
            . '(account_id,sportovec_id,relation_role,status,source,valid_from,created_by_trainer_id, '
            . "approved_by_trainer_id,decision_note) VALUES (?,?,'self','approved','self_registration', "
            . "CURRENT_TIMESTAMP,?,?, 'Automaticky ověřená self vazba veřejného účtu.')"
        )->execute([$accountId, $sportovecId, $actorId, $actorId]);
        $relationId = (int)$pdo->lastInsertId();
        $action = 'self_create';
    }
    accountPersonRoleEvent(
        $pdo,
        $relationId,
        $actorId,
        $action,
        $fromStatus,
        'approved',
        'self',
        'Automaticky ověřená self vazba veřejného účtu.'
    );
}

function publicProfileExistingApprovedSelf(PDO $pdo, int $accountId): ?int
{
    $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'mysql') {
        $table = $pdo->query(
            "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() "
            . "AND TABLE_NAME='account_person_roles' LIMIT 1"
        )->fetchColumn();
    } else {
        $table = $pdo->query(
            "SELECT 1 FROM sqlite_master WHERE type='table' AND name='account_person_roles' LIMIT 1"
        )->fetchColumn();
    }
    if (!$table) {
        return null;
    }
    $statement = $pdo->prepare(
        "SELECT sportovec_id FROM account_person_roles WHERE account_id=? AND relation_role='self' "
        . "AND status='approved' AND valid_from<=CURRENT_TIMESTAMP "
        . 'AND (valid_to IS NULL OR valid_to>CURRENT_TIMESTAMP) ORDER BY sportovec_id'
    );
    $statement->execute([$accountId]);
    $ids = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    if (count($ids) > 1) {
        throw new PublicProfileException('Účet má více aktivních self vazeb; před pokračováním je musí opravit správce.');
    }
    return $ids[0] ?? null;
}
