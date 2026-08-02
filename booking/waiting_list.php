<?php
/**
 * Po uvolnění slotu atomicky povýší prvního čekajícího a po uvolnění DB zámku
 * odešle oznámení zákazníkovi a případně approval odkazy trenérovi.
 */

require_once dirname(__DIR__) . '/includes/one_time_token.php';

function notifyWaitingList(PDO $pdo, int $lekceId, string $slotOd): void
{
    $lockName = 'rez_' . $lekceId . '_' . preg_replace('/[^0-9]/', '', $slotOd);
    $lockStatement = $pdo->prepare('SELECT GET_LOCK(?, 5)');
    $lockStatement->execute([$lockName]);
    if ((int)$lockStatement->fetchColumn() !== 1) {
        error_log('Čekací listinu se nepodařilo bezpečně uzamknout: ' . $lockName);
        return;
    }

    $customerEmail = null;
    $customerSubject = null;
    $customerBody = null;
    $trainerEmail = null;
    $trainerSubject = null;
    $trainerBody = null;

    try {
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

        $from = substr((string)$waiting['slot_cas_od'], 0, 5);
        $to = substr((string)$waiting['slot_cas_do'], 0, 5);
        $name = trim($waiting['jmeno'] . ' ' . $waiting['prijmeni']);
        $base = 'https://data.kovopraha.cz/evidence/booking';
        $customerEmail = (string)$waiting['uzivatel_email'];

        if ($waiting['typ'] === 'zelena') {
            $customerSubject = "✓ Váš slot je potvrzen: {$waiting['lekce_nazev']}";
            $customerBody = "Dobrý den {$name},\n\n"
                . "Slot, na který jste čekali, se uvolnil a byl vám automaticky přiřazen.\n\n"
                . "Lekce: {$waiting['lekce_nazev']}\n"
                . "Sportoviště: {$waiting['sport_nazev']}\n"
                . "Datum: {$waiting['datum']}\nČas: {$from}–{$to}\n\n"
                . "Vaše rezervace je POTVRZENA.\n\nPřehled: {$base}/moje_rezervace.php";
        } else {
            $approval = one_time_token_issue(ONE_TIME_TOKEN_BOOKING_APPROVAL, 172800);
            $storeApproval = $pdo->prepare(
                "UPDATE verejne_rezervace SET potvrzovaci_token=?, "
                . "potvrzovaci_token_expires_at=? WHERE id=? AND stav='ceka'"
            );
            $storeApproval->execute([$approval['hash'], $approval['expires_at'], $waiting['id']]);
            if ($storeApproval->rowCount() !== 1) {
                return;
            }

            $customerSubject = "Slot k dispozici: {$waiting['lekce_nazev']}";
            $customerBody = "Dobrý den {$name},\n\nSlot, na který jste čekali, se uvolnil.\n"
                . "Vaše žádost byla automaticky předána trenérovi ke schválení.\n\n"
                . "Lekce: {$waiting['lekce_nazev']}\nDatum: {$waiting['datum']}, {$from}–{$to}\n\n"
                . "Přehled: {$base}/moje_rezervace.php";

            if ($waiting['trener_email']) {
                $host = $base . '/potvrdit.php';
                $trainerEmail = (string)$waiting['trener_email'];
                $trainerSubject = "Žádost z čekací listiny: {$waiting['lekce_nazev']}";
                $trainerBody = "Zákazník {$name} ze čekací listiny žádá o potvrzení lekce:\n"
                    . "{$waiting['lekce_nazev']} — {$waiting['datum']}, {$from}–{$to}\n\n"
                    . "✓ POTVRDIT: {$host}#token=" . rawurlencode($approval['token']) . "&akce=potvrdit\n"
                    . "✗ ZAMÍTNOUT: {$host}#token=" . rawurlencode($approval['token']) . "&akce=zamit";
            }
        }
    } finally {
        $pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$lockName]);
    }

    $headers = "From: evidence@kovopraha.cz\r\nContent-Type: text/plain; charset=utf-8";
    if ($customerEmail !== null && $customerSubject !== null && $customerBody !== null) {
        @mail($customerEmail, $customerSubject, $customerBody, $headers);
    }
    if ($trainerEmail !== null && $trainerSubject !== null && $trainerBody !== null) {
        @mail($trainerEmail, $trainerSubject, $trainerBody, $headers);
    }
}
