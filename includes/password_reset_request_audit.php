<?php

declare(strict_types=1);

require_once __DIR__ . '/auth_rate_limit.php';
require_once __DIR__ . '/password_reset.php';
require_once __DIR__ . '/trainer_password_reset.php';
require_once __DIR__ . '/mail_request_audit.php';

/** @return array{request_id:string,csrf_valid:bool,issued:bool,token:?string} */
function passwordResetRequestHandle(
    PDO $pdo,
    bool $trainerReset,
    string $identifier,
    bool $csrfValid,
    string $ipAddress,
    string $resetUrl,
    ?array $keys = null
): array {
    $requestId = bin2hex(random_bytes(16));
    $result = ['request_id' => $requestId, 'csrf_valid' => $csrfValid, 'issued' => false, 'token' => null];
    $auditStarted = false;
    $stage = 'audit_start';

    try {
        passwordResetAuditStart($pdo, $requestId, $trainerReset ? 'trainer' : 'public', $identifier);
        $auditStarted = true;
        if (!$csrfValid) {
            passwordResetAuditFinish($pdo, $requestId, 'rejected', 'csrf_invalid');
            return $result;
        }

        $identifier = trim($identifier);
        if ($identifier === '' || strlen($identifier) > ($trainerReset ? 254 : 160)) {
            passwordResetAuditFinish($pdo, $requestId, 'rejected', 'invalid_identifier');
            return $result;
        }

        $stage = 'rate_limit';
        if (!auth_rate_limit_reserve_attempt($pdo, 'password_reset', $identifier, $ipAddress)) {
            passwordResetAuditFinish($pdo, $requestId, 'rejected', 'rate_limited');
            return $result;
        }

        $stage = 'queue';
        $queued = $trainerReset
            ? trainerPasswordResetQueueRequest($pdo, $identifier, $resetUrl, $keys, null, $requestId)
            : passwordResetQueueRequest($pdo, $identifier, $resetUrl, $keys, null, $requestId);
        if (!$queued['issued']) {
            passwordResetAuditFinish($pdo, $requestId, 'rejected', 'not_eligible');
            return $result;
        }

        $result['issued'] = true;
        $result['token'] = $queued['token'];
        return $result;
    } catch (Throwable $exception) {
        if ($auditStarted && !$pdo->inTransaction()) {
            try {
                $reason = $stage === 'rate_limit' ? 'rate_limit_error' : 'queue_error';
                if ($exception instanceof PasswordResetException) {
                    $reason = in_array($exception->getMessage(), ['lookup_error', 'token_insert_error', 'mail_enqueue_error', 'audit_update_error', 'commit_error'], true)
                        ? $exception->getMessage() : $reason;
                }
                passwordResetAuditFinish(
                    $pdo,
                    $requestId,
                    'failed',
                    $reason
                );
            } catch (Throwable $auditError) {
                error_log('Password reset audit completion failed: request_id=' . $requestId . ' class=' . get_class($auditError));
            }
        }
        error_log('Password reset request failed: request_id=' . $requestId . ' stage=' . $stage . ' class=' . get_class($exception));
        return $result;
    }
}

function passwordResetAuditStart(PDO $pdo, string $requestId, string $audience, string $identifier = ''): void
{
    if (preg_match('/^[a-f0-9]{32}$/D', $requestId) !== 1 || !in_array($audience, ['public', 'trainer'], true)) {
        throw new InvalidArgumentException('Invalid password reset audit start.');
    }
    $pdo->prepare(
        "INSERT INTO password_reset_request_audit(request_id,audience,identifier_hash,outcome,reason_code) VALUES(?,?,?,'received','received')"
    )->execute([$requestId, $audience, mailRequestIdentifierHash($identifier)]);
}

function passwordResetAuditFinish(PDO $pdo, string $requestId, string $outcome, string $reason, ?int $outboxId = null): void
{
    $reasons = ['csrf_invalid', 'invalid_identifier', 'rate_limited', 'not_eligible', 'rate_limit_error',
        'queue_error', 'lookup_error', 'token_insert_error', 'mail_enqueue_error', 'audit_update_error', 'commit_error', 'queued'];
    if (!in_array($outcome, ['rejected', 'failed', 'queued'], true)
        || !in_array($reason, $reasons, true)
        || ($outcome === 'queued') !== ($reason === 'queued')
        || ($outcome === 'queued') !== ($outboxId !== null && $outboxId > 0)
        || ($outcome !== 'queued' && $outboxId !== null)
    ) {
        throw new InvalidArgumentException('Invalid password reset audit outcome.');
    }
    $update = $pdo->prepare(
        "UPDATE password_reset_request_audit SET outcome=?,reason_code=?,outbox_id=?,updated_at=CURRENT_TIMESTAMP "
        . "WHERE request_id=? AND outcome='received'"
    );
    $update->execute([$outcome, $reason, $outboxId, $requestId]);
    if ($update->rowCount() !== 1) {
        throw new RuntimeException('Password reset audit transition failed.');
    }
}

/** @return list<array<string,mixed>> */
function passwordResetAuditAdminList(PDO $pdo, string $requestId = ''): array
{
    $requestId = strtolower(trim($requestId));
    if ($requestId !== '' && preg_match('/^[a-f0-9]{32}$/D', $requestId) !== 1) {
        throw new InvalidArgumentException('Reference požadavku musí mít 32 hexadecimálních znaků.');
    }
    $sql = 'SELECT a.request_id,a.audience,a.outcome,a.reason_code,a.outbox_id,a.created_at,o.status AS outbox_status '
        . 'FROM password_reset_request_audit a LEFT JOIN auth_mail_outbox o ON o.id=a.outbox_id';
    if ($requestId !== '') {
        $statement = $pdo->prepare($sql . ' WHERE a.request_id=? LIMIT 1');
        $statement->execute([$requestId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
    return $pdo->query($sql . ' ORDER BY a.created_at DESC,a.request_id DESC LIMIT 50')->fetchAll(PDO::FETCH_ASSOC);
}

function passwordResetAuditReasonLabel(string $reason): string
{
    return match ($reason) {
        'received' => 'Požadavek přijat, výsledek zatím není známý',
        'queued' => 'Zařazeno do fronty',
        'csrf_invalid' => 'Neplatný bezpečnostní token formuláře',
        'invalid_identifier' => 'Neplatný vstup',
        'rate_limited' => 'Omezení počtu pokusů',
        'not_eligible' => 'Účet nenalezen nebo není způsobilý',
        'rate_limit_error' => 'Selhala kontrola počtu pokusů',
        'lookup_error' => 'Selhalo vyhledání účtu',
        'token_insert_error' => 'Selhalo uložení tokenu',
        'mail_enqueue_error' => 'Selhalo zařazení e-mailu',
        'audit_update_error' => 'Selhal audit atomického zařazení',
        'commit_error' => 'Selhalo potvrzení transakce',
        default => 'Neznámý důvod',
    };
}
