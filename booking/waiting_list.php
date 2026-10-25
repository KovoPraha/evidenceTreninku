<?php
/**
 * Po uvolnění slotu atomicky povýší prvního čekajícího a po uvolnění DB zámku
 * odešle oznámení zákazníkovi a případně approval odkazy trenérovi.
 */

require_once dirname(__DIR__) . '/includes/one_time_token.php';
require_once dirname(__DIR__) . '/includes/app_url.php';
require_once dirname(__DIR__) . '/includes/booking_notification.php';

function notifyWaitingList(PDO $pdo, int $lekceId, string $slotOd): void
{
    $lockName = 'rez_' . $lekceId . '_' . preg_replace('/[^0-9]/', '', $slotOd);
    $lockStatement = $pdo->prepare('SELECT GET_LOCK(?, 5)');
    $lockStatement->execute([$lockName]);
    if ((int)$lockStatement->fetchColumn() !== 1) {
        error_log('Čekací listinu se nepodařilo bezpečně uzamknout: ' . $lockName);
        return;
    }

    try {
        $pdo->beginTransaction();
        $capacity = $pdo->prepare("
            SELECT il.max_osob,
                   (SELECT COUNT(*) FROM verejne_rezervace active
                    WHERE active.lekce_id = il.id AND active.slot_cas_od = ?
                      AND active.stav IN ('ceka','potvrzena')) AS obsazeno
            FROM individualni_lekce il
            WHERE il.id = ?
        ");
        $capacity->execute([$slotOd, $lekceId]);
        $slot = $capacity->fetch(PDO::FETCH_ASSOC);
        if (!$slot || (int)$slot['obsazeno'] >= (int)$slot['max_osob']) {
            return;
        }

        $candidate = $pdo->prepare("
            SELECT vr.id, vr.slot_cas_od, vr.slot_cas_do,
                   u.jmeno, u.prijmeni, u.email AS uzivatel_email,
                   il.nazev AS lekce_nazev, il.datum, il.typ, il.cas_od, il.cas_do,
                   s.nazev AS sport_nazev,
                   t.email AS trener_email
            FROM verejne_rezervace vr
            JOIN verejni_uzivatele u ON u.id = vr.uzivatel_id
            JOIN individualni_lekce il ON il.id = vr.lekce_id
            JOIN sportovist s ON s.id = il.sportoviste_id
            JOIN treneri t ON t.id = il.trener_id
            WHERE vr.lekce_id = ? AND vr.slot_cas_od = ? AND vr.stav = 'cekaci_listina'
            ORDER BY vr.cas_rezervace ASC
            LIMIT 1
        ");
        $candidate->execute([$lekceId, $slotOd]);
        $waiting = $candidate->fetch(PDO::FETCH_ASSOC);
        if (!$waiting) {
            return;
        }

        $newState = $waiting['typ'] === 'zelena' ? 'potvrzena' : 'ceka';
        $promote = $pdo->prepare(
            "UPDATE verejne_rezervace SET stav=?, cas_potvrzeni=NOW() "
            . "WHERE id=? AND stav='cekaci_listina'"
        );
        $promote->execute([$newState, $waiting['id']]);
        if ($promote->rowCount() !== 1) {
            return;
        }

        bookingNotificationEnqueue($pdo, (int)$waiting['id'], 'promoted');
        if ($newState === 'ceka') bookingNotificationEnqueue($pdo, (int)$waiting['id'], 'promoted', 'trainer');
        $pdo->commit();
    } finally {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$lockName]);
    }
}
