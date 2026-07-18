<?php
/**
 * booking/waiting_list.php
 * Helper: po uvolnění slotu automaticky přiřadí rezervaci prvnímu čekajícímu
 * a zašle mu emailové oznámení.
 */

function notifyWaitingList(PDO $pdo, int $lekceId, string $slotOd): void
{
    // Najít prvního v pořadí na čekací listině
    $stmt = $pdo->prepare("
        SELECT vr.id, vr.slot_cas_od, vr.slot_cas_do, vr.potvrzovaci_token,
               u.jmeno, u.prijmeni, u.email AS uzivatel_email,
               il.nazev AS lekce_nazev, il.datum, il.typ, il.cas_od, il.cas_do,
               s.nazev AS sport_nazev,
               t.email AS trener_email
        FROM verejne_rezervace vr
        JOIN verejni_uzivatele u  ON u.id  = vr.uzivatel_id
        JOIN individualni_lekce il ON il.id = vr.lekce_id
        JOIN sportovist s         ON s.id  = il.sportoviste_id
        JOIN treneri t            ON t.id  = il.trener_id
        WHERE vr.lekce_id = ? AND vr.slot_cas_od = ? AND vr.stav = 'cekaci_listina'
        ORDER BY vr.cas_rezervace ASC
        LIMIT 1
    ");
    $stmt->execute([$lekceId, $slotOd]);
    $cek = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$cek) return;

    $stavNovy = $cek['typ'] === 'zelena' ? 'potvrzena' : 'ceka';
    $pdo->prepare("UPDATE verejne_rezervace SET stav=?, cas_potvrzeni=NOW() WHERE id=?")
        ->execute([$stavNovy, $cek['id']]);

    $od  = substr($cek['slot_cas_od'], 0, 5);
    $do  = substr($cek['slot_cas_do'], 0, 5);
    $kdo = trim($cek['jmeno'] . ' ' . $cek['prijmeni']);
    $base = 'https://data.kovopraha.cz/evidence/booking';
    $hdrs = "From: evidence@kovopraha.cz\r\nContent-Type: text/plain; charset=utf-8";

    // — Email zákazníkovi —
    if ($cek['typ'] === 'zelena') {
        $subj = "✓ Váš slot je potvrzen: {$cek['lekce_nazev']}";
        $body = "Dobrý den {$kdo},\n\n"
              . "Slot, na který jste čekali, se uvolnil a byl vám automaticky přiřazen.\n\n"
              . "Lekce:       {$cek['lekce_nazev']}\n"
              . "Sportoviště: {$cek['sport_nazev']}\n"
              . "Datum:       {$cek['datum']}\n"
              . "Čas:         {$od}–{$do}\n\n"
              . "Vaše rezervace je POTVRZENA.\n\n"
              . "Přehled: {$base}/moje_rezervace.php";
    } else {
        $subj = "Slot k dispozici: {$cek['lekce_nazev']}";
        $body = "Dobrý den {$kdo},\n\n"
              . "Slot, na který jste čekali, se uvolnil.\n"
              . "Vaše žádost byla automaticky předána trenérovi ke schválení.\n\n"
              . "Lekce:  {$cek['lekce_nazev']}\n"
              . "Datum:  {$cek['datum']}, {$od}–{$do}\n\n"
              . "Přehled: {$base}/moje_rezervace.php";
    }
    @mail($cek['uzivatel_email'], $subj, $body, $hdrs);

    // — Email trenérovi (jen žlutá lekce — potřebuje potvrzení) —
    if ($cek['typ'] === 'zluta' && $cek['trener_email']) {
        $token = bin2hex(random_bytes(24));
        $pdo->prepare("UPDATE verejne_rezervace SET potvrzovaci_token=? WHERE id=?")
            ->execute([$token, $cek['id']]);
        $host = 'https://data.kovopraha.cz/evidence/booking/potvrdit.php';
        $trBody = "Zákazník {$kdo} ze čekací listiny žádá o potvrzení lekce:\n"
                . "{$cek['lekce_nazev']} — {$cek['datum']}, {$od}–{$do}\n\n"
                . "✓ POTVRDIT: {$host}?token={$token}&akce=potvrdit\n"
                . "✗ ZAMÍTNOUT: {$host}?token={$token}&akce=zamit";
        @mail($cek['trener_email'], "Žádost z čekací listiny: {$cek['lekce_nazev']}", $trBody, $hdrs);
    }
}
