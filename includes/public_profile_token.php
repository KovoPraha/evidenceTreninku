<?php
declare(strict_types=1);

/** Public profile URLs are bearer credentials and require cryptographic entropy. */
function public_profile_token_generate(): string
{
    return bin2hex(random_bytes(32));
}

function public_profile_token_is_strong(string $token): bool
{
    return preg_match('/\A[a-f0-9]{64}\z/D', $token) === 1;
}

function public_profile_access_token_hash(string $token): string
{
    if (!public_profile_token_is_strong($token)) return '';
    return hash('sha256', "evidence-public-profile-access-v1\0" . $token);
}

/** @return array{token:string,expires_at:string} */
function public_profile_access_issue(PDO $pdo, int $sportovecId, ?int $actorId, int $ttlSeconds = 7776000): array
{
    if ($sportovecId < 1 || $ttlSeconds < 300 || $ttlSeconds > 31536000) {
        throw new InvalidArgumentException('Neplatné parametry přístupového odkazu.');
    }
    $person = $pdo->prepare('SELECT id FROM sportovci WHERE id=?');
    $person->execute([$sportovecId]);
    if (!$person->fetchColumn()) throw new RuntimeException('Sportovec nebyl nalezen.');
    public_profile_access_revoke_for_person($pdo, $sportovecId);
    $token = public_profile_token_generate();
    $expiresAt = gmdate('Y-m-d H:i:s', time() + $ttlSeconds);
    $pdo->prepare(
        'INSERT INTO public_profile_access_tokens '
        . '(sportovec_id,token_hash,token_hint,scope,expires_at,created_by_trainer_id) VALUES (?,?,?,?,?,?)'
    )->execute([
        $sportovecId,
        public_profile_access_token_hash($token),
        substr($token, -8),
        'training_read',
        $expiresAt,
        $actorId,
    ]);
    return ['token' => $token, 'expires_at' => $expiresAt];
}

function public_profile_access_resolve(PDO $pdo, string $token): ?int
{
    $digest = public_profile_access_token_hash(trim($token));
    if ($digest === '') return null;
    $statement = $pdo->prepare(
        'SELECT id,sportovec_id FROM public_profile_access_tokens '
        . "WHERE token_hash=? AND scope='training_read' AND revoked_at IS NULL AND expires_at>=CURRENT_TIMESTAMP LIMIT 1"
    );
    $statement->execute([$digest]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    $pdo->prepare('UPDATE public_profile_access_tokens SET last_used_at=CURRENT_TIMESTAMP WHERE id=?')->execute([(int)$row['id']]);
    return (int)$row['sportovec_id'];
}

function public_profile_access_grant_session(int $sportovecId, int $ttlSeconds = 7200): void
{
    if ($sportovecId < 1) throw new InvalidArgumentException('Neplatný profil sportovce.');
    $_SESSION['public_profile_access'] = [
        'sportovec_id' => $sportovecId,
        'expires_at' => time() + max(300, min($ttlSeconds, 43200)),
    ];
}

function public_profile_access_session_athlete_id(): ?int
{
    $access = $_SESSION['public_profile_access'] ?? null;
    if (!is_array($access) || (int)($access['expires_at'] ?? 0) < time() || (int)($access['sportovec_id'] ?? 0) < 1) {
        unset($_SESSION['public_profile_access']);
        return null;
    }
    return (int)$access['sportovec_id'];
}

function public_profile_access_revoke_for_person(PDO $pdo, int $sportovecId): int
{
    $statement = $pdo->prepare(
        'UPDATE public_profile_access_tokens SET revoked_at=CURRENT_TIMESTAMP '
        . 'WHERE sportovec_id=? AND revoked_at IS NULL'
    );
    $statement->execute([$sportovecId]);
    return $statement->rowCount();
}
