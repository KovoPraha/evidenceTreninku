<?php
/**
 * cron_upominky.php
 * Zasílá upomínky trenérům na plánované tréninky, které proběhly,
 * ale dosud nebyly zaevidovány v evidenci.
 *
 * Spuštění:
 *   - CLI:  php cron_upominky.php
 *   - Web:  /cron_upominky.php?secret=TAJNY_TOKEN  (vyzaduje UPOMINKA_SECRET)
 *
 * Doporučené nastavení cronu (každý den v 7:00):
 *   0 7 * * * php /cesta/k/evidencePavel/cron_upominky.php >> /var/log/upominky.log 2>&1
 */

// ── Bezpečnost ────────────────────────────────────────────────────────────────
define('UPOMINKA_SECRET', getenv('UPOMINKA_SECRET') ?: '');
define('UPOMINKA_OD_DATU', 14);                  // upomínat max. 14 dní zpětně
define('UPOMINKA_PO_DNECH', 1);                  // upomínat 1+ dní po datu tréninku
define('UPOMINKA_EMAIL_FROM', 'evidence@kovopraha.cz');
define('UPOMINKA_BASE_URL',   'https://data.kovopraha.cz/evidence');

$isCli = PHP_SAPI === 'cli';

if (!$isCli) {
    // Webový přístup — povoleno pouze z localhost nebo se správným tokenem
    $token   = trim($_GET['secret'] ?? '');
    if (UPOMINKA_SECRET === '' || !hash_equals(UPOMINKA_SECRET, $token)) {
        http_response_code(403);
        exit('Přístup odepřen.');
    }
    header('Content-Type: text/plain; charset=utf-8');
}

require_once __DIR__ . '/db.php';

$dnes    = date('Y-m-d');
$odDatum = date('Y-m-d', strtotime("-" . UPOMINKA_OD_DATU . " days"));
$doPlan  = date('Y-m-d', strtotime("-" . UPOMINKA_PO_DNECH . " days"));

// ── Načíst nezaevidované plány skupin dle trenéra ─────────────────────────────
$stmt = $pdo->prepare("
    SELECT pt.id, pt.nazev, pt.datum, pt.cas_od, pt.kategorie, pt.trener_id,
           sk.nazev  AS skupina_nazev,
           t.jmeno   AS trener_jmeno,
           t.email   AS trener_email
    FROM planovane_treninky pt
    LEFT JOIN skupiny  sk ON sk.id = pt.skupina_id
    LEFT JOIN treneri  t  ON t.id  = pt.trener_id
    WHERE pt.stav    = 'planovany'
      AND pt.datum  >= ?
      AND pt.datum  <= ?
      AND pt.upominka_cas IS NULL
      AND t.email IS NOT NULL AND t.email != ''
    ORDER BY pt.trener_id, pt.datum, pt.cas_od
");
$stmt->execute([$odDatum, $doPlan]);
$plany = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($plany)) {
    echo "[" . date('Y-m-d H:i:s') . "] Žádné nezaevidované tréninky k upomínání.\n";
    exit;
}

// ── Seskupit dle trenéra ──────────────────────────────────────────────────────
$poDle = [];
foreach ($plany as $p) {
    $tid = (int)$p['trener_id'];
    if (!isset($poDle[$tid])) {
        $poDle[$tid] = [
            'jmeno' => $p['trener_jmeno'],
            'email' => $p['trener_email'],
            'plany' => [],
        ];
    }
    $poDle[$tid]['plany'][] = $p;
}

$denNazvy = [1=>'Po',2=>'Út',3=>'St',4=>'Čt',5=>'Pá',6=>'So',7=>'Ne'];
$odeslano = 0;
$chyby    = 0;

foreach ($poDle as $tid => $data) {
    $pocet = count($data['plany']);

    // ── Sestavit seznam tréninků pro email ────────────────────────────────
    $radky = [];
    $ids   = [];
    foreach ($data['plany'] as $p) {
        $ids[] = (int)$p['id'];
        $dt  = new DateTime($p['datum']);
        $den = $denNazvy[(int)$dt->format('N')] ?? '';
        $cas = $p['cas_od'] ? ' ' . substr($p['cas_od'], 0, 5) : '';
        $kat = $p['kategorie'] ? " [{$p['kategorie']}]" : '';
        $sk  = $p['skupina_nazev'] ? " — {$p['skupina_nazev']}" : '';
        $radky[] = "  • {$den} {$dt->format('j. n. Y')}{$cas}{$kat}  {$p['nazev']}{$sk}";
    }

    $seznam = implode("\n", $radky);
    $tydnu  = $pocet === 1 ? 'trénink' : ($pocet < 5 ? 'tréninky' : 'tréninků');
    $odkaz  = UPOMINKA_BASE_URL . '/planovac.php?jen_moje=1';

    $subject = "Upomínka: {$pocet} nezaevidovan" . ($pocet === 1 ? 'ý' : 'é') . " {$tydnu}";
    $body    = "Dobrý den {$data['jmeno']},\n\n"
             . "evidenční systém Kovopraha zjistil, že máte {$pocet} plánovan"
             . ($pocet === 1 ? 'ý trénink' : 'é tréninky')
             . ", které již proběhl" . ($pocet === 1 ? '' : 'y')
             . ", ale dosud nejso" . ($pocet === 1 ? 'u' : 'u') . " zaevidován" . ($pocet === 1 ? '' : 'y') . ":\n\n"
             . $seznam . "\n\n"
             . "Prosím, doplňte evidenci co nejdříve:\n"
             . $odkaz . "\n\n"
             . "---\nTento email byl odeslán automaticky systémem evidence tréninků.\n"
             . "Pokud jste trénink zrušili, označte ho v plánovači jako zrušený.";

    $headers = "From: " . UPOMINKA_EMAIL_FROM . "\r\n"
             . "Content-Type: text/plain; charset=utf-8\r\n"
             . "X-Mailer: EvidenceKovopraha";

    $ok = @mail($data['email'], $subject, $body, $headers);

    if ($ok) {
        // Označit jako odeslané
        $inPl = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("UPDATE planovane_treninky SET upominka_cas = NOW() WHERE id IN ({$inPl})")
            ->execute($ids);
        echo "[" . date('Y-m-d H:i:s') . "] ✓ Email odeslán: {$data['jmeno']} <{$data['email']}> — {$pocet} {$tydnu}\n";
        $odeslano++;
    } else {
        echo "[" . date('Y-m-d H:i:s') . "] ✗ Chyba odesílání: {$data['jmeno']} <{$data['email']}>\n";
        $chyby++;
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Hotovo. Odesláno: {$odeslano}, chyby: {$chyby}.\n";
