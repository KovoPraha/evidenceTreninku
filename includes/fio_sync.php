<?php
declare(strict_types=1);

require_once __DIR__.'/fio_readonly_import.php';

/**
 * One bounded HTTP request per invocation. The caller holds the import lock.
 * Progress belongs to an account, not a token; rotating tokens preserves it.
 * Fetch injection is used by offline integration tests.
 */
function fioSynchronize(PDO $pdo, string $iban, callable $fetch, DateTimeImmutable $today, bool $autoConfirm = false, int $overlapDays = 3, ?string $startDate = null, ?int $now = null): array
{
    $iban = fioNormalizeIban($iban);
    if ($iban === '' || $overlapDays < 1 || $overlapDays > 30) {
        throw new InvalidArgumentException('fio_invalid_sync_settings');
    }
    $today = $today->setTime(0, 0);
    $now ??= time();
    $select = $pdo->prepare('SELECT * FROM fio_sync_state WHERE source_account_iban=?');
    $select->execute([$iban]);
    $state = $select->fetch(PDO::FETCH_ASSOC);
    if (!$state) {
        // Include existing history when upgrading, even if its newest run skipped a gap.
        $history = $pdo->prepare("SELECT MIN(period_from) FROM fio_import_runs WHERE source_account_iban=? AND status='completed'");
        $history->execute([$iban]);
        $earliest = $history->fetchColumn();
        $startDate ??= $today->modify('-29 days')->format('Y-m-d');
        if (is_string($earliest) && $earliest < $startDate) {
            $startDate = $earliest;
        }
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $startDate, $today->getTimezone());
        if (!$parsed || $parsed->format('Y-m-d') !== $startDate || $parsed > $today) {
            throw new InvalidArgumentException('fio_invalid_start_date');
        }
        $pdo->prepare('INSERT INTO fio_sync_state(source_account_iban,start_date) VALUES (?,?)')->execute([$iban, $startDate]);
        $state = ['start_date'=>$startDate, 'covered_through'=>null, 'last_attempt_at'=>null];
    }
    if ($state['last_attempt_at'] !== null && $now - (int)$state['last_attempt_at'] < 30) {
        throw new FioImportException('fio_import_rate_limited');
    }
    $from = new DateTimeImmutable((string)$state['start_date'], $today->getTimezone());
    if ($state['covered_through'] !== null) {
        $overlap = (new DateTimeImmutable((string)$state['covered_through'], $today->getTimezone()))->modify('-'.($overlapDays - 1).' days');
        if ($overlap > $from) {
            $from = $overlap;
        }
    }
    // At least one new day even for a 30-day overlap; normally seven-day windows.
    $to = min($today, $from->modify('+'.max(6, $overlapDays).' days'));
    $periodFrom = $from->format('Y-m-d');
    $periodTo = $to->format('Y-m-d');
    $pdo->prepare('UPDATE fio_sync_state SET last_attempt_at=?,last_error=NULL WHERE source_account_iban=?')->execute([$now, $iban]);
    try {
        $json = $fetch($periodFrom, $periodTo);
        $import = fioImportJson($pdo, $json, $periodFrom, $periodTo, $iban);
        // A crash before this write only causes an idempotent re-import.
        $pdo->prepare('UPDATE fio_sync_state SET covered_through=?,last_success_at=?,last_error=NULL WHERE source_account_iban=?')->execute([$periodTo, $now, $iban]);
        $caughtUp = $periodTo === $today->format('Y-m-d');
        // Finish backfill first, so competing movements in later windows are visible.
        $automatic = $autoConfirm && $caughtUp
            ? fioAutoConfirmPending($pdo, $iban)
            : ['confirmed'=>0, 'review'=>0];
        return $import + ['period_from'=>$periodFrom, 'period_to'=>$periodTo, 'caught_up'=>$caughtUp, 'automatic'=>$automatic];
    } catch (Throwable $exception) {
        $code = $exception instanceof FioImportException && preg_match('/^fio_[a-z0-9_]+$/D', $exception->getMessage())
            ? $exception->getMessage() : 'fio_sync_failed';
        $pdo->prepare('UPDATE fio_sync_state SET last_error=? WHERE source_account_iban=?')->execute([$code, $iban]);
        throw new FioImportException($code, 0, $exception);
    }
}

/** Reuses canonical payment transitions, with a system actor and no fabricated trainer. */
function fioAutoConfirmPending(PDO $pdo, string $iban): array
{
    $query = $pdo->prepare("SELECT m.id FROM fio_account_movements m JOIN fio_import_runs r ON r.id=m.import_run_id
        WHERE r.source_account_iban=? AND m.match_status='proposed_exact' ORDER BY m.booked_on,m.id");
    $query->execute([fioNormalizeIban($iban)]);
    $result = ['confirmed'=>0, 'review'=>0];
    foreach ($query->fetchAll(PDO::FETCH_COLUMN) as $id) {
        try {
            $confirmation = fioConfirmExactMovement($pdo, (int)$id, null, 'Automatická přesná shoda Fio.', true);
            if ($confirmation['changed']) {
                $result['confirmed']++;
            }
        } catch (FioAutomaticReviewException|ShopCheckoutException|MemberChargeAdminException $exception) {
            $pdo->prepare("UPDATE fio_account_movements SET match_status='review_auto_conflict',match_reason=? WHERE id=? AND match_status<>'confirmed'")
                ->execute(['Automatické potvrzení vyžaduje kontrolu: '.mb_substr($exception->getMessage(), 0, 190, 'UTF-8'), (int)$id]);
            $result['review']++;
        }
    }
    return $result;
}
