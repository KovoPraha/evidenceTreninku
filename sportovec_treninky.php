<?php
require_once __DIR__ . '/includes/session_security.php';
/**
 * sportovec_treninky.php
 * Veřejná karta sportovce – přístup přes hash (bez přihlášení)
 */
app_session_start();
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, private');
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/db.php';

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function safePublicHtml($html): string {
    $html = strip_tags((string)$html, '<p><br><b><strong><i><em><ul><ol><li><a>');
    return preg_replace_callback('~<\s*(/)?\s*([a-z0-9]+)([^>]*)>~i', function ($m) {
        $closing = $m[1] === '/';
        $tag = strtolower($m[2]);
        $allowed = ['p', 'br', 'b', 'strong', 'i', 'em', 'ul', 'ol', 'li', 'a'];
        if (!in_array($tag, $allowed, true)) return '';
        if ($closing) return $tag === 'br' ? '' : "</{$tag}>";
        if ($tag !== 'a') return "<{$tag}>";
        $attrs = $m[3] ?? '';
        if (!preg_match('~href\s*=\s*(["\'])(.*?)\1~is', $attrs, $hm)) return '<a>';
        $href = trim(html_entity_decode($hm[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $compact = preg_replace('/[\x00-\x20]+/', '', $href);
        if (preg_match('/^([a-z][a-z0-9+.-]*):/i', $compact, $sm)
            && !in_array(strtolower($sm[1]), ['http', 'https', 'mailto'], true)) {
            return '<a>';
        }
        return '<a href="' . h($href) . '" rel="noopener noreferrer">';
    }, $html);
}

function fmtDate(?string $d): string {
    if (!$d || $d === '0000-00-00') return '—';
    $ts = strtotime($d);
    return $ts ? date('d.m.Y', $ts) : h($d);
}

function renderMereniData(array $mz): string {
    $typ   = $mz['typ'] ?? '';
    $parts = [];
    if ($typ === 'kolo' || $typ === 'beh') {
        if (!empty($mz['vzdalenost']) && $mz['vzdalenost'] !== '0')
            $parts[] = '<strong>' . h($mz['vzdalenost']) . '</strong> km';
        if (!empty($mz['cas']))
            $parts[] = '⏱ ' . h($mz['cas']);
        if (!empty($mz['prevod']) && $typ === 'kolo')
            $parts[] = 'převod ' . h($mz['prevod']);
    } elseif ($typ === 'posilovna') {
        $cvik = $mz['cvik_nazev'] ?: ($mz['cvik_id'] ? '#' . (int)$mz['cvik_id'] : '—');
        $parts[] = '<strong>' . h($cvik) . '</strong>';
        if (!empty($mz['vaha']) && $mz['vaha'] !== '0') $parts[] = h($mz['vaha']) . ' kg';
        if (!empty($mz['opakovani']))                    $parts[] = (int)$mz['opakovani'] . '×';
        if (!empty($mz['rpe']))                          $parts[] = 'RPE ' . h($mz['rpe']);
    } elseif ($typ === 'kolo_krouzek' || $typ === 'kolo_silnice' || $typ === 'kolo_mtb') {
        $segment = $mz['segment_nazev'] ?? ($mz['segment_id'] ? '#' . (int)$mz['segment_id'] : '—');
        $parts[] = '<strong>' . h($segment) . '</strong>';
        if (!empty($mz['cas'])) $parts[] = '⏱ ' . h($mz['cas']);
    } else {
        return '<span class="text-muted">—</span>';
    }
    return implode(' · ', $parts) ?: '<span class="text-muted">—</span>';
}

// 1) Autentizace – hash
$hash = trim($_GET['hash'] ?? '');
if (!$hash) { http_response_code(404); exit('Profil není dostupný.'); }

$stmt = $pdo->prepare('SELECT * FROM sportovci WHERE hash = ?');
$stmt->execute([$hash]);
$s = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$s) { http_response_code(404); exit('Profil není dostupný.'); }

$sportovec_id   = (int)$s['id'];
$sportovec_name = trim(($s['jmeno'] ?? '') . ' ' . ($s['prijmeni'] ?? ''));

$odmena_default = isset($s['odmena_za_trenink']) ? (float)$s['odmena_za_trenink'] : 0.0;

// 2) Dostupné roky tréninků (pro dropdown)
$rokyAvailable = [];
try {
    $stmtY = $pdo->prepare("
        SELECT DISTINCT YEAR(t.datum) AS rok
        FROM treninky t
        LEFT JOIN trenink_sportovec ts ON ts.trenink_id = t.id AND ts.sportovec_id = :sid
        LEFT JOIN trenink_mereni tm   ON tm.trenink_id  = t.id
        LEFT JOIN mereni_zaznamy mz   ON mz.id = tm.mereni_id AND mz.sportovec_id = :sid
        WHERE ts.sportovec_id IS NOT NULL OR mz.sportovec_id IS NOT NULL
        ORDER BY rok DESC
    ");
    $stmtY->execute([':sid' => $sportovec_id]);
    $rokyAvailable = array_column($stmtY->fetchAll(PDO::FETCH_ASSOC), 'rok');
} catch (PDOException $e) {
    $rokyAvailable = [];
}

$defaultRok = !empty($rokyAvailable) ? (int)$rokyAvailable[0] : (int)date('Y');

// 3) Všechna měření sportovce (pro záložku Měření)
$mojeMereni = [];
try {
    $sqlAll = "
        SELECT
            t.id AS trenink_id,
            t.datum AS trenink_datum,
            tm.poradi,
            mz.id AS mereni_id, mz.typ,
            mz.vzdalenost, mz.cas, mz.prevod,
            mz.cvik_id, mz.segment_id, mz.vaha, mz.opakovani, mz.rpe,
            mz.poznamka AS mereni_poznamka,
            c.nazev AS cvik_nazev,
            seg.nazev AS segment_nazev
        FROM trenink_mereni tm
        JOIN mereni_zaznamy mz ON mz.id = tm.mereni_id
        JOIN treninky t ON t.id = tm.trenink_id
        LEFT JOIN cviky c ON c.id = mz.cvik_id
        LEFT JOIN segmenty seg ON seg.id = mz.segment_id
        WHERE mz.sportovec_id = ?
        ORDER BY t.datum DESC, t.id DESC, tm.poradi ASC, mz.id ASC
    ";
    $stAll = $pdo->prepare($sqlAll);
    $stAll->execute([$sportovec_id]);
    $mojeMereni = $stAll->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mojeMereni = [];
}

// 4) Statistiky celkové
$statCelkem = count($rokyAvailable) > 0 ? null : 0;
try {
    $stStat = $pdo->prepare("
        SELECT COUNT(DISTINCT t.id) AS pocet
        FROM treninky t
        LEFT JOIN trenink_sportovec ts ON ts.trenink_id = t.id AND ts.sportovec_id = :sid
        LEFT JOIN trenink_mereni tm   ON tm.trenink_id  = t.id
        LEFT JOIN mereni_zaznamy mz   ON mz.id = tm.mereni_id AND mz.sportovec_id = :sid
        WHERE ts.sportovec_id IS NOT NULL OR mz.sportovec_id IS NOT NULL
    ");
    $stStat->execute([':sid' => $sportovec_id]);
    $statCelkem = (int)$stStat->fetchColumn();
} catch (PDOException $e) {}

// 5) Kredit a aktuální období
$aktualniObdobi = null;
$pocetTrObdobi  = 0;
$sazba_v_obdobi = $odmena_default;
$castkaObdobi   = 0.0;

try {
    $stmtOpen = $pdo->prepare("
        SELECT * FROM sportovec_obdobi
        WHERE sportovec_id = ?
          AND (datum_do IS NULL OR datum_do = '0000-00-00')
        ORDER BY datum_od DESC LIMIT 1
    ");
    $stmtOpen->execute([$sportovec_id]);
    $aktualniObdobi = $stmtOpen->fetch(PDO::FETCH_ASSOC);

    if ($aktualniObdobi) {
        $od = $aktualniObdobi['datum_od'];
        $sazba_v_obdobi = isset($aktualniObdobi['sazba_kc'])
            ? (float)$aktualniObdobi['sazba_kc'] : $odmena_default;

        $stmtCount = $pdo->prepare("
            SELECT COUNT(DISTINCT ts.trenink_id) AS pocet
            FROM trenink_sportovec ts
            JOIN treninky t ON t.id = ts.trenink_id
            WHERE ts.sportovec_id = :sid
              AND t.datum >= :od AND t.datum <= :do
        ");
        $stmtCount->execute([':sid' => $sportovec_id, ':od' => $od, ':do' => date('Y-m-d')]);
        $pocetTrObdobi = (int)($stmtCount->fetchColumn() ?? 0);
        $castkaObdobi  = $pocetTrObdobi * $sazba_v_obdobi;
    }
} catch (PDOException $e) { $aktualniObdobi = null; }

// 6) Zátěžové testy
$testy         = [];
$souboryByTest = [];
try {
    $stmtTests = $pdo->prepare("SELECT * FROM zatezove_testy WHERE sportovec_id = ? ORDER BY datum DESC, id DESC");
    $stmtTests->execute([$sportovec_id]);
    $testy = $stmtTests->fetchAll(PDO::FETCH_ASSOC);

    $testIds = array_column($testy, 'id');
    if (!empty($testIds)) {
        $in = implode(',', array_fill(0, count($testIds), '?'));
        $stmtFiles = $pdo->prepare("
            SELECT * FROM zatezove_testy_soubory
            WHERE test_id IN ($in) AND typ = 'public_img'
            ORDER BY id ASC
        ");
        $stmtFiles->execute($testIds);
        while ($row = $stmtFiles->fetch(PDO::FETCH_ASSOC)) {
            $souboryByTest[(int)$row['test_id']][] = $row;
        }
    }
} catch (PDOException $e) {}

// 7) Oznámení
$oznameni = [];
try {
    $stmtPS = $pdo->prepare("SELECT DISTINCT podskupina_id FROM sportovec_podskupina WHERE sportovec_id = ?");
    $stmtPS->execute([$sportovec_id]);
    $podskupinyIds = array_map('intval', $stmtPS->fetchAll(PDO::FETCH_COLUMN));

    $skupinyIds = [];
    if (!empty($podskupinyIds)) {
        $in = implode(',', array_fill(0, count($podskupinyIds), '?'));
        $stmtG = $pdo->prepare("SELECT DISTINCT skupina_id FROM podskupiny WHERE id IN ($in)");
        $stmtG->execute($podskupinyIds);
        $skupinyIds = array_map('intval', $stmtG->fetchAll(PDO::FETCH_COLUMN));
    }

    $whereParts = ["(ot.target_type = 'sportovec' AND ot.target_id = ?)"];
    $params     = [$sportovec_id];

    if (!empty($podskupinyIds)) {
        $whereParts[] = "(ot.target_type = 'podskupina' AND ot.target_id IN (" . implode(',', array_fill(0, count($podskupinyIds), '?')) . "))";
        $params = array_merge($params, $podskupinyIds);
    }
    if (!empty($skupinyIds)) {
        $whereParts[] = "(ot.target_type = 'skupina' AND ot.target_id IN (" . implode(',', array_fill(0, count($skupinyIds), '?')) . "))";
        $params = array_merge($params, $skupinyIds);
    }

    $stO = $pdo->prepare("
        SELECT DISTINCT o.*
        FROM oznameni o
        JOIN oznameni_targets ot ON ot.oznameni_id = o.id
        WHERE (" . implode(' OR ', $whereParts) . ")
        ORDER BY o.datum DESC, o.id DESC LIMIT 50
    ");
    $stO->execute($params);
    $oznameni = $stO->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// 8) Kalendář
function buildCalendarUrl(array $extra = []): string {
    $params = $_GET;
    foreach ($extra as $k => $v) $params[$k] = $v;
    return 'sportovec_treninky.php?' . http_build_query($params);
}

function ymToDate(string $ym, bool $start): DateTime {
    $d = DateTime::createFromFormat('Y-m', $ym) ?: new DateTime('first day of this month');
    $d->setTime(0, 0, 0);
    $d->modify($start ? 'first day of this month' : 'last day of this month');
    return $d;
}

$ym         = $_GET['ym'] ?? date('Y-m');
$monthStart = ymToDate($ym, true);
$monthEnd   = ymToDate($ym, false);
$ymPrev     = (clone $monthStart)->modify('-1 month')->format('Y-m');
$ymNext     = (clone $monthStart)->modify('+1 month')->format('Y-m');
$rangeFrom  = $monthStart->format('Y-m-d');
$rangeTo    = $monthEnd->format('Y-m-d');

// Podskupiny sportovce
$podskupinySportovce = [];
$skupinySportovce    = [];
try {
    $stmtPS = $pdo->prepare("
        SELECT sp.podskupina_id, p.skupina_id
        FROM sportovec_podskupina sp
        JOIN podskupiny p ON p.id = sp.podskupina_id
        WHERE sp.sportovec_id = ?
    ");
    $stmtPS->execute([$sportovec_id]);
    foreach ($stmtPS->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if ($r['podskupina_id'] > 0) $podskupinySportovce[(int)$r['podskupina_id']] = 1;
        if ($r['skupina_id']    > 0) $skupinySportovce[(int)$r['skupina_id']]    = 1;
    }
    $podskupinySportovce = array_keys($podskupinySportovce);
    $skupinySportovce    = array_keys($skupinySportovce);
} catch (PDOException $e) {}

// Kalendář - tréninky skupin sportovce
$calTreninkyByDate = [];
$calTreninkMeta    = [];
$totalCal          = 0;

if (!empty($podskupinySportovce)) {
    $inPs = implode(',', array_fill(0, count($podskupinySportovce), '?'));
    try {
        $stmtCal = $pdo->prepare("
            SELECT DISTINCT t.id, DATE(t.datum) AS datum, t.delka, t.napln
            FROM treninky t
            JOIN trenink_podskupina tp ON tp.trenink_id = t.id
            WHERE tp.podskupina_id IN ($inPs)
              AND DATE(t.datum) BETWEEN ? AND ?
            ORDER BY t.datum DESC, t.id DESC
        ");
        $stmtCal->execute(array_merge($podskupinySportovce, [$rangeFrom, $rangeTo]));
        $calTreninky = $stmtCal->fetchAll(PDO::FETCH_ASSOC);

        $ids = [];
        foreach ($calTreninky as $t) {
            $ids[] = (int)$t['id'];
            $calTreninkyByDate[$t['datum']][] = $t;
            $totalCal++;
        }

        if (!empty($ids)) {
            $inIds = implode(',', array_fill(0, count($ids), '?'));
            $stmtTr = $pdo->prepare("
                SELECT tt.trenink_id, tr.jmeno
                FROM trenink_trener tt JOIN treneri tr ON tr.id = tt.trener_id
                WHERE tt.trenink_id IN ($inIds) ORDER BY tr.jmeno
            ");
            $stmtTr->execute($ids);
            while ($r = $stmtTr->fetch(PDO::FETCH_ASSOC)) {
                $calTreninkMeta[(int)$r['trenink_id']]['treneri'][] = $r['jmeno'];
            }

            $stmtTag = $pdo->prepare("
                SELECT tt.trenink_id, tg.nazev
                FROM trenink_tag tt JOIN tagy tg ON tg.id = tt.tag_id
                WHERE tt.trenink_id IN ($inIds) ORDER BY tg.nazev
            ");
            $stmtTag->execute($ids);
            while ($r = $stmtTag->fetch(PDO::FETCH_ASSOC)) {
                $calTreninkMeta[(int)$r['trenink_id']]['tagy'][] = $r['nazev'];
            }
        }
    } catch (PDOException $e) {}
}

// 8b) Plánované tréninky skupin sportovce (nadcházející)
// Stejná logika jako sportovec_detail.php — skupiny přes sportovec_skupina, bez filtru podskupin
$maSkupiny = false;
try {
    $stmtMaSkup = $pdo->prepare("SELECT COUNT(*) FROM sportovec_skupina WHERE sportovec_id = ?");
    $stmtMaSkup->execute([$sportovec_id]);
    $maSkupiny = (int)$stmtMaSkup->fetchColumn() > 0;
} catch (PDOException $e) {}

$planovane   = [];
$planKatMeta = [
    'silnice'   => ['label'=>'Silnice',   'bgcls'=>'bg-success',          'icon'=>'bi-bicycle'],
    'mtb'       => ['label'=>'MTB',       'bgcls'=>'bg-warning text-dark', 'icon'=>'bi-tree'],
    'draha'     => ['label'=>'Dráha',     'bgcls'=>'bg-primary',           'icon'=>'bi-stopwatch'],
    'cyklokros' => ['label'=>'Cyklokros', 'bgcls'=>'badge-orange',         'icon'=>'bi-tornado'],
    'posilovna' => ['label'=>'Posilovna', 'bgcls'=>'bg-danger',            'icon'=>'bi-trophy'],
    'atletika'  => ['label'=>'Atletika',  'bgcls'=>'bg-info text-dark',    'icon'=>'bi-person-walking'],
    'cviceni'   => ['label'=>'Cvičení',   'bgcls'=>'bg-secondary',         'icon'=>'bi-heart-pulse'],
    'plavani'   => ['label'=>'Plavání',   'bgcls'=>'badge-teal',           'icon'=>'bi-water'],
];
try {
    $stmtPl = $pdo->prepare("
        SELECT pt.*, sk.nazev AS skupina_nazev,
               COALESCE(
                   (SELECT GROUP_CONCAT(ps2.nazev ORDER BY ps2.poradi SEPARATOR ', ')
                    FROM planovane_treninky_podskupiny ptp2
                    JOIN podskupiny ps2 ON ps2.id = ptp2.podskupina_id
                    WHERE ptp2.plan_id = pt.id),
                   (SELECT ps3.nazev FROM podskupiny ps3 WHERE ps3.id = pt.podskupina_id)
               ) AS podskupiny_nazvy,
               t.jmeno AS trener_jmeno
        FROM planovane_treninky pt
        LEFT JOIN skupiny sk ON sk.id = pt.skupina_id
        LEFT JOIN treneri t  ON t.id  = pt.trener_id
        WHERE pt.stav = 'planovany'
          AND pt.datum >= CURDATE()
          AND pt.skupina_id IN (
              SELECT skupina_id FROM sportovec_skupina WHERE sportovec_id = ?
          )
        ORDER BY pt.datum, pt.cas_od
        LIMIT 40
    ");
    $stmtPl->execute([$sportovec_id]);
    $planovane = $stmtPl->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $planovane = [];
}

// 9) Google Sheets linky
$gsLinksActive = false;
$gsCatsOrdered = [];
$gsLinksByCat  = [];
try {
    $where  = ["(l.viditelnost = 'verejny')"];
    $params = [];

    $cileneParts  = ["(gt.target_type = 'sportovec' AND gt.target_id = ?)"];
    $cileneParams = [$sportovec_id];

    if (!empty($podskupinySportovce)) {
        $in = implode(',', array_fill(0, count($podskupinySportovce), '?'));
        $cileneParts[] = "(gt.target_type = 'podskupina' AND gt.target_id IN ($in))";
        foreach ($podskupinySportovce as $pid) $cileneParams[] = $pid;
    }
    if (!empty($skupinySportovce)) {
        $in = implode(',', array_fill(0, count($skupinySportovce), '?'));
        $cileneParts[] = "(gt.target_type = 'skupina' AND gt.target_id IN ($in))";
        foreach ($skupinySportovce as $gid) $cileneParams[] = $gid;
    }

    $where[] = "(l.viditelnost = 'cilene' AND (" . implode(' OR ', $cileneParts) . "))";
    $params  = array_merge($params, $cileneParams);

    $st = $pdo->prepare("
        SELECT DISTINCT l.*, k.nazev AS kategorie_nazev, tr.jmeno AS trener_jmeno
        FROM gs_linky l
        JOIN gs_kategorie k ON k.id = l.kategorie_id
        LEFT JOIN treneri tr ON tr.id = l.vlozil_trener_id
        LEFT JOIN gs_link_targets gt ON gt.link_id = l.id
        WHERE (" . implode(' OR ', $where) . ")
        ORDER BY l.created_at DESC, l.id DESC
    ");
    $st->execute($params);
    $allLinks = $st->fetchAll(PDO::FETCH_ASSOC);

    $lastByCat = [];
    foreach ($allLinks as $l) {
        $cid = (int)$l['kategorie_id'];
        $gsLinksByCat[$cid][] = $l;
        $ts = $l['created_at'] ?? '';
        if (!isset($lastByCat[$cid]) || $ts > $lastByCat[$cid]) $lastByCat[$cid] = $ts;
    }

    $cats = [];
    foreach ($gsLinksByCat as $cid => $items) {
        $cats[] = ['id' => $cid, 'nazev' => $items[0]['kategorie_nazev'] ?? 'Kategorie', 'last_created' => $lastByCat[$cid] ?? '', 'count' => count($items)];
    }
    usort($cats, fn($a, $b) => $b['last_created'] <=> $a['last_created']);
    $gsCatsOrdered = $cats;
    $gsLinksActive = !empty($allLinks);
} catch (PDOException $e) {}

// Funkce pro vykreslení kalendáře
function renderMonthTable(DateTime $monthStart, array $treninkyByDate, array $metaByTrenink, string $parentId): string {
    $year  = (int)$monthStart->format('Y');
    $month = (int)$monthStart->format('m');
    $firstDay    = new DateTime(sprintf('%04d-%02d-01', $year, $month));
    $lastDay     = (clone $firstDay)->modify('last day of this month');
    $startDow    = (int)$firstDay->format('N');
    $daysInMonth = (int)$lastDay->format('j');
    $weeks       = (int)ceil(($startDow - 1 + $daysInMonth) / 7);

    $html = '<table class="table table-bordered calendar-mini mb-0"><thead class="table-light"><tr>';
    foreach (['Po', 'Út', 'St', 'Čt', 'Pá', 'So', 'Ne'] as $d) $html .= "<th>{$d}</th>";
    $html .= '</tr></thead><tbody>';

    $day = 1;
    $d   = clone $firstDay;
    for ($w = 0; $w < $weeks; $w++) {
        $html .= '<tr>';
        for ($col = 1; $col <= 7; $col++) {
            if ($w === 0 && $col < $startDow || $day > $daysInMonth) {
                $html .= '<td class="calendar-cell empty"></td>';
                continue;
            }
            $dateKey = $d->format('Y-m-d');
            $html .= '<td class="calendar-cell">';
            $html .= '<div class="d-flex justify-content-between align-items-center mb-1"><span class="badge text-bg-light border">' . $day . '</span></div>';

            if (!empty($treninkyByDate[$dateKey])) {
                $html .= '<div class="d-grid gap-1">';
                foreach ($treninkyByDate[$dateKey] as $t) {
                    $tid = (int)$t['id'];
                    $meta  = $metaByTrenink[$tid] ?? ['treneri' => [], 'tagy' => []];
                    $title = !empty($t['napln']) ? mb_substr(trim($t['napln']), 0, 35) . (mb_strlen(trim($t['napln'])) > 35 ? '…' : '') : 'Trénink #' . $tid;
                    $cId   = 'cal_' . $parentId . '_' . $tid;

                    $html .= '<button class="btn btn-outline-primary btn-sm text-start" type="button" data-bs-toggle="collapse" data-bs-target="#' . $cId . '">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</button>';
                    $html .= '<div class="collapse" id="' . $cId . '"><div class="card card-body p-2 small">';
                    $html .= '<div><strong>Datum:</strong> ' . date('d.m.Y', strtotime($dateKey)) . '</div>';
                    if (!empty($t['delka'])) $html .= '<div><strong>Délka:</strong> ' . htmlspecialchars($t['delka'], ENT_QUOTES, 'UTF-8') . ' h</div>';
                    if (!empty($meta['treneri'])) $html .= '<div><strong>Trenér:</strong> ' . htmlspecialchars(implode(', ', $meta['treneri']), ENT_QUOTES, 'UTF-8') . '</div>';
                    if (!empty($meta['tagy']))    $html .= '<div><strong>Tagy:</strong> ' . htmlspecialchars(implode(', ', $meta['tagy']), ENT_QUOTES, 'UTF-8') . '</div>';
                    if (!empty($t['napln']))      $html .= '<div class="mt-1">' . nl2br(htmlspecialchars($t['napln'], ENT_QUOTES, 'UTF-8')) . '</div>';
                    $html .= '</div></div>';
                }
                $html .= '</div>';
            }
            $html .= '</td>';
            $d->modify('+1 day');
            $day++;
        }
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';
    return $html;
}
?>
<!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($sportovec_name) ?> – karta sportovce</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    body { background: #f5f7fa; }
    .hero-card {
        background: linear-gradient(135deg, #1a56db 0%, #0e9f6e 100%);
        color: #fff; border-radius: 1rem; padding: 1.5rem 2rem;
    }
    .stat-pill {
        background: rgba(255,255,255,.18); border-radius: 2rem;
        padding: .35rem .9rem; font-size: .88rem; display: inline-block;
    }

    .thumb-tr { max-width: 100px; max-height: 100px; object-fit: cover;
                border-radius: .5rem; border: 1px solid #dee2e6; }

    .calendar-mini th { width: calc(100%/7); text-align: center; }
    .calendar-cell { vertical-align: top; min-width: 120px; }
    .calendar-cell.empty { background: #fafafa; height: 56px; }
    .calendar-mini .btn { font-size: .73rem; padding: 2px 5px; }
    @media (max-width: 768px) { .calendar-cell { min-width: 0; } }

    .mereni-typ-kolo           { background: #1a56db !important; }
    .mereni-typ-kolo_krouzek   { background: #7c3aed !important; }
    .mereni-typ-kolo_silnice   { background: #dc2626 !important; }
    .mereni-typ-kolo_mtb       { background: #f59e0b !important; }
    .mereni-typ-beh       { background: #0e9f6e !important; }
    .mereni-typ-posilovna { background: #f59e0b !important; color: #1f2937 !important; }

    .nav-tabs .nav-link { color: #374151; }
    .nav-tabs .nav-link.active { font-weight: 600; }

    .note-saved-anim { animation: fadeOut 2.5s forwards; }
    @keyframes fadeOut { 0%{ opacity:1 } 70%{ opacity:1 } 100%{ opacity:0 } }
    .badge-orange { background-color: #fd7e14 !important; color: #fff; }
    .badge-teal   { background-color: #20c997 !important; color: #fff; }
    .plan-card { border-left: 3px solid #3b82f6; }
  </style>
</head>
<body>

<div class="container py-4">

  <!-- HERO -->
  <div class="hero-card mb-4 d-flex align-items-center justify-content-between flex-wrap gap-3">
    <div>
      <h1 class="mb-1 fw-bold fs-2"><?= h($sportovec_name) ?></h1>
      <div class="stat-pill">
        <i class="bi bi-bicycle me-1"></i>Veřejná karta sportovce
      </div>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <?php if ($statCelkem !== null): ?>
        <div class="stat-pill">
          <i class="bi bi-calendar-check me-1"></i><?= (int)$statCelkem ?> tréninků celkem
        </div>
      <?php endif; ?>
      <?php if (!empty($mojeMereni)): ?>
        <div class="stat-pill">
          <i class="bi bi-speedometer2 me-1"></i><?= count($mojeMereni) ?> měření
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- KREDIT -->
  <?php if ($aktualniObdobi || $odmena_default > 0): ?>
  <div class="card mb-4 border-0 shadow-sm">
    <div class="card-body">
      <h5 class="card-title mb-3"><i class="bi bi-coin me-2 text-warning"></i>Aktuální kredit</h5>
      <?php if ($aktualniObdobi): ?>
        <div class="row g-3">
          <div class="col-sm-3">
            <div class="text-muted small">Období od</div>
            <div class="fw-semibold"><?= fmtDate($aktualniObdobi['datum_od']) ?></div>
          </div>
          <div class="col-sm-3">
            <div class="text-muted small">Tréninků v období</div>
            <div class="fw-semibold"><?= (int)$pocetTrObdobi ?></div>
          </div>
          <div class="col-sm-3">
            <div class="text-muted small">Sazba / trénink</div>
            <div class="fw-semibold"><?= number_format($sazba_v_obdobi, 2, ',', ' ') ?> Kč</div>
          </div>
          <div class="col-sm-3">
            <div class="text-muted small">Aktuální kredit</div>
            <div class="fw-bold fs-5 text-success"><?= number_format($castkaObdobi, 2, ',', ' ') ?> Kč</div>
          </div>
        </div>
      <?php else: ?>
        <p class="mb-0 text-muted">
          Nastavena odměna <strong><?= number_format($odmena_default, 2, ',', ' ') ?> Kč / trénink</strong>.
          Kredit se začne počítat po vytvoření období trenérem.
        </p>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- TABS -->
  <ul class="nav nav-tabs" id="mainTabs" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#pane-treninky" type="button">
        <i class="bi bi-journal-text me-1"></i>Tréninky
        <?php if ($statCelkem): ?>
          <span class="badge bg-secondary ms-1"><?= (int)$statCelkem ?></span>
        <?php endif; ?>
      </button>
    </li>
    <?php if ($maSkupiny): ?>
    <li class="nav-item" role="presentation">
      <button class="nav-link" data-bs-toggle="tab" data-bs-target="#pane-plan" type="button">
        <i class="bi bi-calendar-plus me-1"></i>Plán
        <?php if (!empty($planovane)): ?>
          <span class="badge bg-primary ms-1"><?= count($planovane) ?></span>
        <?php endif; ?>
      </button>
    </li>
    <?php endif; ?>
    <li class="nav-item" role="presentation">
      <button class="nav-link" data-bs-toggle="tab" data-bs-target="#pane-kalendar" type="button">
        <i class="bi bi-calendar3 me-1"></i>Kalendář
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" data-bs-toggle="tab" data-bs-target="#pane-mereni" type="button">
        <i class="bi bi-speedometer2 me-1"></i>Měření
        <?php if (!empty($mojeMereni)): ?>
          <span class="badge bg-secondary ms-1"><?= count($mojeMereni) ?></span>
        <?php endif; ?>
      </button>
    </li>
    <?php if (!empty($testy)): ?>
    <li class="nav-item" role="presentation">
      <button class="nav-link" data-bs-toggle="tab" data-bs-target="#pane-testy" type="button">
        <i class="bi bi-clipboard2-pulse me-1"></i>Zátěžové testy
        <span class="badge bg-secondary ms-1"><?= count($testy) ?></span>
      </button>
    </li>
    <?php endif; ?>
    <?php
    $zavodyPublicTab = [];
    try {
        $stmtZPT = $pdo->prepare("
            SELECT z.id, z.datum, z.kategorie, z.popis, zsp.poradi, zsp.cas, zsp.body
            FROM zavod_sportovec zsp
            JOIN zavody z ON zsp.zavod_id = z.id
            WHERE zsp.sportovec_id = :sid
            ORDER BY z.datum DESC
            LIMIT 50
        ");
        $stmtZPT->execute([':sid' => $sportovec_id]);
        $zavodyPublicTab = $stmtZPT->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}
    if (!empty($zavodyPublicTab)): ?>
    <li class="nav-item" role="presentation">
      <button class="nav-link" data-bs-toggle="tab" data-bs-target="#pane-zavody" type="button">
        <i class="bi bi-trophy me-1"></i>Závody
        <span class="badge bg-secondary ms-1"><?= count($zavodyPublicTab) ?></span>
      </button>
    </li>
    <?php endif; ?>
    <?php if ($gsLinksActive): ?>
    <li class="nav-item" role="presentation">
      <button class="nav-link" data-bs-toggle="tab" data-bs-target="#pane-gs" type="button">
        <i class="bi bi-link-45deg me-1"></i>Odkazy
      </button>
    </li>
    <?php endif; ?>
    <?php if (!empty($oznameni)): ?>
    <li class="nav-item" role="presentation">
      <button class="nav-link" data-bs-toggle="tab" data-bs-target="#pane-zpravy" type="button">
        <i class="bi bi-bell me-1"></i>Zprávy
        <span class="badge bg-danger ms-1"><?= count($oznameni) ?></span>
      </button>
    </li>
    <?php endif; ?>
  </ul>

  <div class="tab-content bg-white border border-top-0 rounded-bottom p-3 shadow-sm mb-5" id="mainTabsContent">

    <!-- ===== TRÉNINKY ===== -->
    <div class="tab-pane fade show active" id="pane-treninky" role="tabpanel">

      <!-- Rok filter -->
      <div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
        <?php if (!empty($rokyAvailable)): ?>
        <div class="d-flex align-items-center gap-2">
          <label class="fw-semibold mb-0 small text-muted">Rok:</label>
          <select id="rokFilter" class="form-select form-select-sm" style="width:auto;">
            <?php foreach ($rokyAvailable as $r): ?>
              <option value="<?= (int)$r ?>" <?= ((int)$r === $defaultRok) ? 'selected' : '' ?>><?= (int)$r ?></option>
            <?php endforeach; ?>
            <option value="0">Vše</option>
          </select>
        </div>
        <?php endif; ?>
        <div id="trainingStats" class="text-muted small"></div>
      </div>

      <div id="trainingListWrap">
        <div class="text-center py-5">
          <div class="spinner-border text-primary" role="status"></div>
          <div class="mt-2 text-muted small">Načítám tréninky…</div>
        </div>
      </div>
    </div>

    <!-- ===== PLÁN ===== -->
    <?php if ($maSkupiny): ?>
    <div class="tab-pane fade" id="pane-plan" role="tabpanel">
      <?php if (empty($planovane)): ?>
        <div class="alert alert-info mb-0">Zatím žádné naplánované tréninky.</div>
      <?php else:
        $planovaneByDate = [];
        foreach ($planovane as $pl) $planovaneByDate[$pl['datum']][] = $pl;
        $denNazvy = [1=>'Pondělí',2=>'Úterý',3=>'Středa',4=>'Čtvrtek',5=>'Pátek',6=>'Sobota',7=>'Neděle'];
      ?>
        <?php foreach ($planovaneByDate as $datum => $plans): ?>
          <?php
            $dt  = new DateTime($datum);
            $dow = (int)$dt->format('N');
            $den = $denNazvy[$dow] ?? '';
          ?>
          <div class="mb-3">
            <div class="text-muted small fw-semibold mb-2 text-uppercase border-bottom pb-1">
              <?= h($den) ?>, <?= h(date('d.m.Y', strtotime($datum))) ?>
            </div>
            <?php foreach ($plans as $pl):
              $kat = $pl['kategorie'] ?? '';
              $km  = $planKatMeta[$kat] ?? ['label'=>h($kat),'bgcls'=>'bg-secondary','icon'=>'bi-calendar'];
            ?>
            <div class="card plan-card mb-2 shadow-sm">
              <div class="card-body py-2 px-3">
                <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
                  <span class="badge <?= $km['bgcls'] ?>">
                    <i class="bi <?= $km['icon'] ?> me-1"></i><?= $km['label'] ?>
                  </span>
                  <?php if (!empty($pl['cas_od'])): ?>
                    <span class="text-muted small">
                      <i class="bi bi-clock me-1"></i><?= h(substr($pl['cas_od'],0,5)) ?><?= !empty($pl['cas_do']) ? '–'.h(substr($pl['cas_do'],0,5)) : '' ?>
                    </span>
                  <?php endif; ?>
                  <?php if (!empty($pl['skupina_nazev'])): ?>
                    <span class="badge bg-light text-dark border"><?= h($pl['skupina_nazev']) ?></span>
                  <?php endif; ?>
                  <?php if (!empty($pl['podskupiny_nazvy'])): ?>
                    <span class="text-muted small"><?= h($pl['podskupiny_nazvy']) ?></span>
                  <?php endif; ?>
                </div>
                <?php if (!empty($pl['nazev'])): ?>
                  <div class="fw-semibold"><?= h($pl['nazev']) ?></div>
                <?php endif; ?>
                <?php if (!empty($pl['popis'])): ?>
                  <div class="text-muted small mt-1"><?= nl2br(h($pl['popis'])) ?></div>
                <?php endif; ?>
                <?php if (!empty($pl['misto'])): ?>
                  <div class="text-muted small mt-1"><i class="bi bi-geo-alt me-1"></i><?= h($pl['misto']) ?></div>
                <?php endif; ?>
                <?php if (!empty($pl['trener_jmeno'])): ?>
                  <div class="text-muted small"><i class="bi bi-person me-1"></i><?= h($pl['trener_jmeno']) ?></div>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ===== KALENDÁŘ ===== -->
    <div class="tab-pane fade" id="pane-kalendar" role="tabpanel">

      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
          <h5 class="mb-0">Kalendář tréninkových skupin</h5>
          <div class="text-muted small">
            Tréninky ze všech podskupin sportovce (<?= count($podskupinySportovce) ?>).
          </div>
        </div>
        <div class="d-flex align-items-center gap-2">
          <a class="btn btn-outline-secondary btn-sm" href="<?= h(buildCalendarUrl(['ym' => $ymPrev])) ?>">
            <i class="bi bi-chevron-left"></i> <?= h($ymPrev) ?>
          </a>
          <span class="fw-semibold"><?= h($monthStart->format('m / Y')) ?></span>
          <a class="btn btn-outline-secondary btn-sm" href="<?= h(buildCalendarUrl(['ym' => $ymNext])) ?>">
            <?= h($ymNext) ?> <i class="bi bi-chevron-right"></i>
          </a>
        </div>
      </div>

      <?php if (empty($podskupinySportovce)): ?>
        <div class="alert alert-info">
          Sportovec není zařazen v žádné podskupině. Kalendář nemá zdroj tréninků.
        </div>
      <?php else: ?>
        <div class="card">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <div class="fw-semibold"><?= h($monthStart->format('F Y')) ?></div>
              <span class="badge bg-light text-dark border">Tréninků: <?= (int)$totalCal ?></span>
            </div>
            <?= renderMonthTable($monthStart, $calTreninkyByDate, $calTreninkMeta, 'cal') ?>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <!-- ===== MĚŘENÍ ===== -->
    <div class="tab-pane fade" id="pane-mereni" role="tabpanel">

      <?php if (empty($mojeMereni)): ?>
        <div class="alert alert-info mb-0">Zatím nemáš žádná měření.</div>
      <?php else: ?>

        <!-- Type filter pills -->
        <div class="d-flex gap-2 flex-wrap mb-3">
          <button class="btn btn-sm btn-outline-secondary active mereni-filter-btn" data-typ="">Vše (<?= count($mojeMereni) ?>)</button>
          <?php
            $typCounts = array_count_values(array_column($mojeMereni, 'typ'));
            $typLabels = ['kolo' => '🚴 Kolo', 'kolo_krouzek' => '🔄 Kroužek', 'kolo_silnice' => '🛣️ Silnice', 'kolo_mtb' => '🌲 MTB', 'beh' => '🏃 Běh', 'posilovna' => '💪 Posilovna'];
            foreach ($typCounts as $typ => $cnt):
          ?>
          <button class="btn btn-sm btn-outline-secondary mereni-filter-btn" data-typ="<?= h($typ) ?>">
            <?= $typLabels[$typ] ?? h($typ) ?> (<?= (int)$cnt ?>)
          </button>
          <?php endforeach; ?>
        </div>

        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle" id="mereniTable">
            <thead class="table-dark">
              <tr>
                <th style="width:105px;">Datum</th>
                <th style="width:90px;">Typ</th>
                <th>Hodnoty</th>
                <th style="width:200px;">Poznámka</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($mojeMereni as $mz): ?>
                <tr data-typ="<?= h($mz['typ'] ?? '') ?>">
                  <td class="text-muted small"><?= fmtDate($mz['trenink_datum'] ?? '') ?></td>
                  <td>
                    <?php
                      $typBadge = ['kolo' => 'mereni-typ-kolo', 'kolo_krouzek' => 'mereni-typ-kolo_krouzek', 'kolo_silnice' => 'mereni-typ-kolo_silnice', 'kolo_mtb' => 'mereni-typ-kolo_mtb', 'beh' => 'mereni-typ-beh', 'posilovna' => 'mereni-typ-posilovna'];
                      $cls = $typBadge[$mz['typ'] ?? ''] ?? 'bg-secondary';
                    ?>
                    <span class="badge <?= $cls ?>"><?= h($mz['typ'] ?? '') ?></span>
                  </td>
                  <td><?= renderMereniData($mz) ?></td>
                  <td class="text-muted small"><?= nl2br(h($mz['mereni_poznamka'] ?? '')) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <!-- ===== ZÁTĚŽOVÉ TESTY ===== -->
    <?php if (!empty($testy)): ?>
    <div class="tab-pane fade" id="pane-testy" role="tabpanel">
      <?php foreach ($testy as $test):
        $tid   = (int)$test['id'];
        $files = $souboryByTest[$tid] ?? [];
      ?>
        <div class="card mb-3">
          <div class="card-header d-flex justify-content-between align-items-center">
            <strong><?= fmtDate($test['datum']) ?></strong>
          </div>
          <div class="card-body">
            <?php if (!empty($test['popis_sportovec'])): ?>
              <div class="mb-3">
                <div class="fw-semibold text-muted small mb-1">VÝSLEDKY A POPIS</div>
                <div><?= nl2br(h($test['popis_sportovec'])) ?></div>
              </div>
            <?php endif; ?>

            <?php if (!empty($files)): ?>
              <div class="d-flex flex-wrap gap-2">
                <?php foreach ($files as $f): ?>
                  <?php $downloadUrl = 'private_download.php?kind=stress&amp;id=' . (int)$f['id'] . '&amp;hash=' . rawurlencode($hash); ?>
                  <a href="<?= $downloadUrl ?>" target="_blank" rel="noopener">
                    <img src="<?= $downloadUrl ?>" alt="<?= h($f['nazev'] ?? '') ?>" class="thumb-tr">
                  </a>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ===== ODKAZY ===== -->
    <?php if ($gsLinksActive): ?>
    <div class="tab-pane fade" id="pane-gs" role="tabpanel">
      <?php foreach ($gsCatsOrdered as $cat):
        $cid   = (int)$cat['id'];
        $items = $gsLinksByCat[$cid] ?? [];
      ?>
        <div class="card mb-3">
          <div class="card-header d-flex justify-content-between">
            <strong><?= h($cat['nazev']) ?></strong>
            <span class="badge bg-secondary"><?= (int)$cat['count'] ?></span>
          </div>
          <div class="card-body p-2">
            <?php foreach ($items as $l): ?>
              <div class="border rounded p-2 mb-2">
                <div class="fw-semibold"><?= h($l['nazev'] ?? '') ?></div>
                <?php if (!empty($l['popis'])): ?>
                  <div class="text-muted small mb-1"><?= nl2br(h($l['popis'])) ?></div>
                <?php endif; ?>
                <?php if (!empty($l['url'])): ?>
                  <a href="<?= h($l['url']) ?>" target="_blank" class="small" style="word-break:break-all;"><?= h($l['url']) ?></a>
                <?php endif; ?>
                <?php if (!empty($l['trener_jmeno'])): ?>
                  <div class="text-muted small mt-1">vložil: <?= h($l['trener_jmeno']) ?> · <?= h($l['created_at'] ?? '') ?></div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ===== ZPRÁVY ===== -->
    <?php if (!empty($oznameni)): ?>
    <div class="tab-pane fade" id="pane-zpravy" role="tabpanel">
      <?php foreach ($oznameni as $o): ?>
        <div class="border rounded p-3 mb-3">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="fw-semibold"><?= h($o['nazev'] ?? 'Oznámení') ?></div>
            <span class="badge bg-light text-dark border"><?= fmtDate($o['datum'] ?? '') ?></span>
          </div>
          <div><?= safePublicHtml($o['obsah_html']) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ===== ZÁVODY ===== -->
    <?php if (!empty($zavodyPublicTab)):
    $zavKatMetaPublic = [
        'silnice' => ['label'=>'Silnice','color'=>'success','icon'=>'bi-bicycle'],
        'draha'   => ['label'=>'Dráha','color'=>'primary','icon'=>'bi-stopwatch'],
        'mtb'     => ['label'=>'MTB','color'=>'warning','icon'=>'bi-tree'],
    ];
    ?>
    <div class="tab-pane fade" id="pane-zavody" role="tabpanel">
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Datum</th>
              <th>Kategorie</th>
              <th>Závod</th>
              <th>Pořadí</th>
              <th>Čas</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($zavodyPublicTab as $zp):
              $zpkat = $zp['kategorie'] ?? 'silnice';
              $zpmeta = $zavKatMetaPublic[$zpkat] ?? $zavKatMetaPublic['silnice'];
          ?>
            <tr>
              <td><?= h(date('d.m.Y', strtotime($zp['datum']))) ?></td>
              <td><span class="badge bg-<?= $zpmeta['color'] ?>"><i class="bi <?= $zpmeta['icon'] ?> me-1"></i><?= $zpmeta['label'] ?></span></td>
              <td><?= h($zp['popis']) ?></td>
              <td><?= $zp['poradi'] !== null ? h($zp['poradi']).'.' : '—' ?></td>
              <td><?= $zp['cas'] ? h($zp['cas']) : '—' ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

  </div><!-- /tab-content -->

</div><!-- /container -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    const HASH        = <?= json_encode($hash,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
    const CSRF_TOKEN  = <?= json_encode(csrf_token(),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
    const DEFAULT_ROK = <?= json_encode($defaultRok,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;

    // ── Tab persistence ──────────────────────────────────────────────
    const TAB_KEY = 'sp_treninky_tab';
    const tabEls  = document.querySelectorAll('#mainTabs button[data-bs-toggle="tab"]');
    const saved   = sessionStorage.getItem(TAB_KEY);
    if (saved) {
        const el = document.querySelector('#mainTabs button[data-bs-target="' + saved + '"]');
        if (el) bootstrap.Tab.getOrCreateInstance(el).show();
    }
    tabEls.forEach(btn => btn.addEventListener('shown.bs.tab', e => {
        sessionStorage.setItem(TAB_KEY, e.target.getAttribute('data-bs-target'));
    }));

    // ── Rok filter + training list AJAX ─────────────────────────────
    const wrap    = document.getElementById('trainingListWrap');
    const rokSel  = document.getElementById('rokFilter');

    function loadTrainings(rok) {
        wrap.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div><div class="mt-2 text-muted small">Načítám…</div></div>';

        const url = 'ajax_sportovec_treninky.php?hash=' + encodeURIComponent(HASH) + '&rok=' + rok;
        fetch(url)
            .then(r => r.text())
            .then(html => {
                wrap.innerHTML = html;
                bindNoteHandlers();
            })
            .catch(() => {
                wrap.innerHTML = '<div class="alert alert-danger">Chyba při načítání tréninků.</div>';
            });
    }

    if (rokSel) {
        rokSel.addEventListener('change', () => loadTrainings(rokSel.value));
    }

    // Initial load
    loadTrainings(DEFAULT_ROK);

    // ── AJAX note save (delegated to wrap) ──────────────────────────
    function bindNoteHandlers() {
        wrap.querySelectorAll('.save-note-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const tid    = btn.dataset.tid;
                const ta     = wrap.querySelector('.athlete-note-input[data-tid="' + tid + '"]');
                const status = wrap.querySelector('.note-status[data-tid="' + tid + '"]');
                if (!ta) return;

                btn.disabled = true;
                btn.textContent = 'Ukládám…';

                const fd = new FormData();
                fd.append('hash', HASH);
                fd.append('csrf_token', CSRF_TOKEN);
                fd.append('trenink_id', tid);
                fd.append('poznamka', ta.value);

                fetch('ajax_sportovec_poznamka.php', { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(data => {
                        btn.disabled = false;
                        btn.textContent = 'Uložit';
                        if (data.ok) {
                            if (status) {
                                status.style.display = 'inline';
                                status.className = 'note-status text-success small note-saved-anim';
                                setTimeout(() => { status.style.display = 'none'; }, 2800);
                            }
                        } else {
                            alert('Chyba: ' + (data.msg || 'Neznámá chyba'));
                        }
                    })
                    .catch(() => {
                        btn.disabled = false;
                        btn.textContent = 'Uložit';
                        alert('Chyba sítě.');
                    });
            });
        });
    }

    // ── Měření type filter ──────────────────────────────────────────
    document.querySelectorAll('.mereni-filter-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.mereni-filter-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            const typ  = btn.dataset.typ;
            const rows = document.querySelectorAll('#mereniTable tbody tr');
            rows.forEach(row => {
                row.style.display = (!typ || row.dataset.typ === typ) ? '' : 'none';
            });
        });
    });

})();
</script>

</body>
</html>
