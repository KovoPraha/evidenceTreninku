<?php

declare(strict_types=1);

require_once __DIR__ . '/auth_rate_limit.php';

function mailRequestIdentifierHash(string $identifier): ?string
{
    $identifier = mb_strtolower(trim($identifier), 'UTF-8');
    if ($identifier === '') {
        return null;
    }
    $key = hash_hkdf('sha256', auth_rate_limit_pepper(), 32, 'kis-mail-request-lookup-v1');
    return hash_hmac('sha256', $identifier, $key);
}

function mailRequestAuditStart(PDO $pdo, string $requestId, string $kind, string $identifier): void
{
    if (preg_match('/^[a-f0-9]{32}$/D', $requestId) !== 1 || !in_array($kind, ['registration', 'verify_resend'], true)) {
        throw new InvalidArgumentException('Invalid mail request audit.');
    }
    $statement = $pdo->prepare("INSERT INTO mail_request_audit(request_id,kind,identifier_hash,outcome,reason_code) VALUES(?,?,?,'received','received')");
    $statement->execute([$requestId, $kind, mailRequestIdentifierHash($identifier)]);
}

function mailRequestAuditFinish(PDO $pdo, string $requestId, string $outcome, string $reason, ?int $outboxId = null): void
{
    if (!in_array($outcome, ['rejected', 'failed', 'queued'], true)
        || preg_match('/^[a-z_]{2,40}$/D', $reason) !== 1
        || ($outcome === 'queued') !== ($outboxId !== null && $outboxId > 0)
    ) {
        throw new InvalidArgumentException('Invalid mail request outcome.');
    }
    $statement = $pdo->prepare("UPDATE mail_request_audit SET outcome=?,reason_code=?,outbox_id=?,updated_at=CURRENT_TIMESTAMP WHERE request_id=? AND outcome='received'");
    $statement->execute([$outcome, $reason, $outboxId, $requestId]);
    if ($statement->rowCount() !== 1) {
        throw new RuntimeException('Mail request audit transition failed.');
    }
}
