<?php
declare(strict_types=1);

/** A no-store browser Back reload must return to the same submission, not issue a new one. */
function trainingSubmissionFormUrl(string $key, array $query): string
{
    $parameters = ['submission_key' => $key];
    foreach (['plan_id', 'duplikat'] as $name) {
        if (isset($query[$name]) && is_scalar($query[$name]) && ctype_digit((string)$query[$name]) && (int)$query[$name] > 0) {
            $parameters[$name] = (int)$query[$name];
        }
    }
    return 'formular.php?' . http_build_query($parameters);
}

/** Session-scoped duplicate-submit guard. Unknown outcomes fail closed, never retry automatically. */
function trainingSubmissionIssue(array &$session, int $actor, int $plan, ?string $retry = null, ?int $now = null): string
{
    $now ??= time();
    $session['training_submissions'] ??= [];
    foreach ($session['training_submissions'] as $key => $entry) {
        if ((int)($entry['issued_at'] ?? 0) < $now - 7200) unset($session['training_submissions'][$key]);
    }
    if ($retry !== null) {
        $entry = trainingSubmissionEntry($session, $retry, $actor, $plan, $now);
        if ($entry['state'] !== 'issued') throw new RuntimeException('Tento formulář již byl odeslán. Nejprve ověřte výsledek v přehledu tréninků.');
        return $retry;
    }
    while (count($session['training_submissions']) >= 50) {
        unset($session['training_submissions'][array_key_first($session['training_submissions'])]);
    }
    $key = bin2hex(random_bytes(24));
    $session['training_submissions'][$key] = ['actor' => $actor, 'plan' => $plan, 'issued_at' => $now, 'state' => 'issued'];
    return $key;
}

function trainingSubmissionEntry(array $session, string $key, int $actor, int $plan, ?int $now = null): array
{
    $entry = $session['training_submissions'][$key] ?? null;
    if (!preg_match('/^[a-f0-9]{48}$/D', $key) || !is_array($entry)
        || $entry['actor'] !== $actor || $entry['plan'] !== $plan || $entry['issued_at'] < ($now ?? time()) - 7200) {
        throw new RuntimeException('Formulář vypršel nebo již není dostupný. Před novým zadáním ověřte přehled tréninků.');
    }
    return $entry;
}

function trainingSubmissionBegin(array &$session, string $key, int $actor, int $plan): void
{
    $entry = trainingSubmissionEntry($session, $key, $actor, $plan);
    if ($entry['state'] !== 'issued') throw new RuntimeException('Výsledek předchozího odeslání je nejistý nebo se právě ukládá. Zápis se neopakuje; ověřte přehled tréninků.');
    $session['training_submissions'][$key]['state'] = 'pending';
}

/** Persist pending before any DB write. Keep the session lock during the transaction. */
function trainingSubmissionPersistPending(string $key, int $actor, int $plan): void
{
    if (session_status() !== PHP_SESSION_ACTIVE || !session_write_close()) throw new RuntimeException('Ochranu odeslání se nepodařilo uložit. Trénink nebyl zapsán.');
    if (!session_start()) throw new RuntimeException('Přihlášení se nepodařilo obnovit. Trénink nebyl zapsán.');
    $entry = trainingSubmissionEntry($_SESSION, $key, $actor, $plan);
    if ((int)($_SESSION['trener_id'] ?? 0) !== $actor || $entry['state'] !== 'pending') throw new RuntimeException('Přihlášení nebo formulář se mezitím změnil. Trénink nebyl zapsán.');
}

function trainingSubmissionComplete(array &$session, string $key, int $trainingId, string $redirect): void
{
    if (($session['training_submissions'][$key]['state'] ?? null) !== 'pending') throw new LogicException('Chybí rozpracované odeslání.');
    $session['training_submissions'][$key]['state'] = 'completed';
    $session['training_submissions'][$key]['training_id'] = $trainingId;
    $session['training_submissions'][$key]['redirect'] = $redirect;
}

/** Call only after a known rollback before attempting commit. */
function trainingSubmissionRetryAfterRollback(array &$session, string $key): void
{
    if (($session['training_submissions'][$key]['state'] ?? null) === 'pending') $session['training_submissions'][$key]['state'] = 'issued';
}
