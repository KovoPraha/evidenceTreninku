<?php
declare(strict_types=1);

require_once __DIR__ . '/password_security.php';

final class UnifiedAccountException extends RuntimeException {}

/** @return array<string,mixed> */
function unifiedAccountEnsureTrainerCustomer(PDO $pdo, array $trainer): array
{
    $trainerId = (int)($trainer['id'] ?? 0);
    $email = strtolower(trim((string)($trainer['email'] ?? '')));
    $name = trim((string)($trainer['jmeno'] ?? ''));
    $passwordHash = (string)($trainer['heslo'] ?? '');
    if ($trainerId < 1 || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || $name === '' || !trainer_password_is_modern_hash($passwordHash)
    ) {
        throw new UnifiedAccountException('Trenérský účet nemá údaje potřebné pro společný účet.');
    }

    $ownTransaction = !$pdo->inTransaction();
    if ($ownTransaction) $pdo->beginTransaction();
    try {
        $byTrainer = $pdo->prepare('SELECT * FROM verejni_uzivatele WHERE trener_id=? LIMIT 1');
        $byTrainer->execute([$trainerId]);
        $account = $byTrainer->fetch(PDO::FETCH_ASSOC);
        if (!$account) {
            $byEmail = $pdo->prepare('SELECT * FROM verejni_uzivatele WHERE LOWER(email)=? LIMIT 1');
            $byEmail->execute([$email]);
            $account = $byEmail->fetch(PDO::FETCH_ASSOC);
            if ($account && $account['trener_id'] !== null && (int)$account['trener_id'] !== $trainerId) {
                throw new UnifiedAccountException('E-mail je už propojen s jiným trenérským účtem.');
            }
            if ($account) {
                $pdo->prepare(
                    'UPDATE verejni_uzivatele SET trener_id=?,heslo_hash=?,email_overeno=1,aktivni=1 '
                    . 'WHERE id=? AND (trener_id IS NULL OR trener_id=?)'
                )->execute([$trainerId, $passwordHash, (int)$account['id'], $trainerId]);
            } else {
                $pdo->prepare(
                    'INSERT INTO verejni_uzivatele '
                    . '(jmeno,prijmeni,email,heslo_hash,email_overeno,aktivni,trener_id) '
                    . 'VALUES (?,?,?,?,1,1,?)'
                )->execute([$name, '', $email, $passwordHash, $trainerId]);
            }
        } else {
            $pdo->prepare(
                'UPDATE verejni_uzivatele SET email=?,heslo_hash=?,email_overeno=1,aktivni=1 WHERE id=?'
            )->execute([$email, $passwordHash, (int)$account['id']]);
        }
        $byTrainer->execute([$trainerId]);
        $account = $byTrainer->fetch(PDO::FETCH_ASSOC);
        if (!$account) throw new UnifiedAccountException('Zákaznickou část společného účtu se nepodařilo připravit.');
        if ($ownTransaction) $pdo->commit();
        return $account;
    } catch (Throwable $exception) {
        if ($ownTransaction && $pdo->inTransaction()) $pdo->rollBack();
        if ($exception instanceof UnifiedAccountException) throw $exception;
        throw new UnifiedAccountException('Společný účet se nepodařilo připravit bez částečného zápisu.', 0, $exception);
    }
}

/** @return array{public:array<string,mixed>,trainer:?array<string,mixed>}|null */
function unifiedAccountAuthenticate(PDO $pdo, string $email, string $password): ?array
{
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') return null;

    $publicStatement = $pdo->prepare('SELECT * FROM verejni_uzivatele WHERE LOWER(email)=? AND aktivni=1 LIMIT 1');
    $publicStatement->execute([$email]);
    $public = $publicStatement->fetch(PDO::FETCH_ASSOC);
    if ($public && $public['trener_id'] === null) {
        if (password_verify($password, (string)$public['heslo_hash'])) {
            return ['public' => $public, 'trainer' => null];
        }
        // Stejný e-mail mohl historicky vzniknout zvlášť u trenéra a zákazníka.
        // Při shodě s trenérským heslem účty bezpečně spojíme místo vytvoření duplicity.
    }

    $trainerStatement = $pdo->prepare(
        'SELECT id,jmeno,email,heslo,role,session_version FROM treneri '
        . 'WHERE aktivni=1 AND LOWER(email)=? LIMIT 1'
    );
    $trainerStatement->execute([$email]);
    $trainer = $trainerStatement->fetch(PDO::FETCH_ASSOC);
    if (!$trainer || !trainer_password_verify($password, (string)$trainer['heslo'])) return null;
    if (trainer_password_needs_rehash((string)$trainer['heslo'])) {
        $newHash = trainer_password_hash($password);
        $pdo->prepare('UPDATE treneri SET heslo=? WHERE id=? AND heslo=?')
            ->execute([$newHash, (int)$trainer['id'], (string)$trainer['heslo']]);
        $trainer['heslo'] = $newHash;
    }
    $public = unifiedAccountEnsureTrainerCustomer($pdo, $trainer);
    return ['public' => $public, 'trainer' => $trainer];
}
