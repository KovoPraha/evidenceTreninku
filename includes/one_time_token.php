<?php
declare(strict_types=1);

const ONE_TIME_TOKEN_EMAIL_VERIFICATION = 'email-verification-v1';
const ONE_TIME_TOKEN_BOOKING_APPROVAL = 'booking-approval-v1';

/** @return array{token:string,hash:string,expires_at:string} */
function one_time_token_issue(string $purpose, int $ttlSeconds, ?int $now = null): array
{
    if ($purpose === '' || $ttlSeconds < 60) {
        throw new InvalidArgumentException('Neplatné parametry jednorázového tokenu.');
    }

    $token = bin2hex(random_bytes(32));
    $issuedAt = $now ?? time();

    return [
        'token' => $token,
        'hash' => one_time_token_hash($purpose, $token),
        'expires_at' => gmdate('Y-m-d H:i:s', $issuedAt + $ttlSeconds),
    ];
}

function one_time_token_hash(string $purpose, string $token): string
{
    // Nové tokeny mají 64 hex znaků. Rozsah zachovává jednorázově i starší
    // 48znakové booking odkazy při bezpečné migraci jejich plaintextu na hash.
    if ($purpose === '' || preg_match('/^[a-f0-9]{48,128}$/D', $token) !== 1) {
        return '';
    }

    return hash('sha256', "evidence-one-time-token\0" . $purpose . "\0" . $token);
}

/** @return array{id:int,session_version:int}|null */
function one_time_email_verification_consume(PDO $pdo, string $token, ?int $now = null): ?array
{
    $hash = one_time_token_hash(ONE_TIME_TOKEN_EMAIL_VERIFICATION, $token);
    if ($hash === '') {
        return null;
    }

    $nowSql = gmdate('Y-m-d H:i:s', $now ?? time());
    $pdo->beginTransaction();
    try {
        $select = $pdo->prepare(
            'SELECT id, session_version FROM verejni_uzivatele '
            . 'WHERE verifikacni_token = ? AND verifikacni_token_expires_at >= ? '
            . 'AND email_overeno = 0 AND aktivni = 1'
        );
        $select->execute([$hash, $nowSql]);
        $user = $select->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            $pdo->rollBack();
            return null;
        }

        $update = $pdo->prepare(
            'UPDATE verejni_uzivatele SET email_overeno = 1, '
            . 'verifikacni_token = NULL, verifikacni_token_expires_at = NULL '
            . 'WHERE id = ? AND verifikacni_token = ? '
            . 'AND verifikacni_token_expires_at >= ? AND email_overeno = 0 AND aktivni = 1'
        );
        $update->execute([(int)$user['id'], $hash, $nowSql]);
        if ($update->rowCount() !== 1) {
            $pdo->rollBack();
            return null;
        }

        $pdo->commit();
        return [
            'id' => (int)$user['id'],
            'session_version' => (int)$user['session_version'],
        ];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

/** @return array<string,mixed>|null */
function one_time_booking_approval_lookup(PDO $pdo, string $token, ?int $now = null): ?array
{
    $hash = one_time_token_hash(ONE_TIME_TOKEN_BOOKING_APPROVAL, $token);
    if ($hash === '') {
        return null;
    }

    $statement = $pdo->prepare(
        'SELECT vr.*, il.nazev, il.datum, il.cas_od, il.cas_do, il.trener_id, '
        . 'vu.email, vu.jmeno FROM verejne_rezervace vr '
        . 'JOIN individualni_lekce il ON il.id = vr.lekce_id '
        . 'JOIN verejni_uzivatele vu ON vu.id = vr.uzivatel_id '
        . 'WHERE vr.potvrzovaci_token = ? AND vr.potvrzovaci_token_expires_at >= ? '
        . "AND vr.stav = 'ceka'"
    );
    $statement->execute([$hash, gmdate('Y-m-d H:i:s', $now ?? time())]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

/** @return array<string,mixed>|null */
function one_time_booking_approval_consume(
    PDO $pdo,
    string $token,
    string $action,
    ?int $now = null
): ?array {
    if (!in_array($action, ['potvrdit', 'zamit'], true)) {
        return null;
    }

    $hash = one_time_token_hash(ONE_TIME_TOKEN_BOOKING_APPROVAL, $token);
    if ($hash === '') {
        return null;
    }

    $nowSql = gmdate('Y-m-d H:i:s', $now ?? time());
    $pdo->beginTransaction();
    try {
        $reservation = one_time_booking_approval_lookup($pdo, $token, $now);
        if (!$reservation) {
            $pdo->rollBack();
            return null;
        }

        $newState = $action === 'potvrdit' ? 'potvrzena' : 'zamitnuta';
        $confirmedAt = $action === 'potvrdit' ? $nowSql : null;
        $update = $pdo->prepare(
            'UPDATE verejne_rezervace SET stav = ?, cas_potvrzeni = ?, '
            . 'potvrzovaci_token = NULL, potvrzovaci_token_expires_at = NULL '
            . 'WHERE id = ? AND potvrzovaci_token = ? '
            . "AND potvrzovaci_token_expires_at >= ? AND stav = 'ceka'"
        );
        $update->execute([$newState, $confirmedAt, (int)$reservation['id'], $hash, $nowSql]);
        if ($update->rowCount() !== 1) {
            $pdo->rollBack();
            return null;
        }

        $pdo->commit();
        $reservation['stav'] = $newState;
        $reservation['cas_potvrzeni'] = $confirmedAt;
        return $reservation;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}
