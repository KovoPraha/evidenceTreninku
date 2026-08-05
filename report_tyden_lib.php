<?php
// report_tyden_lib.php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/sports_measurement_contract.php';

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/**
 * Vrati rozsah tydne Po-Ne jako DATE (Y-m-d).
 * start = pondeli (inclusive), end_exclusive = pondeli+7 (exclusive)
 * Vstup: libovolne datum v tydnu (Y-m-d)
 */
function week_range_from_any_date(string $anyDate): array {
    $dt = new DateTime($anyDate);
    $dow = (int)$dt->format('N');          // 1=Po..7=Ne
    $dt->modify('-' . ($dow - 1) . ' days'); // na pondeli
    $start = $dt->format('Y-m-d');
    $endEx = (clone $dt)->modify('+7 days')->format('Y-m-d');
    return ['start' => $start, 'end_exclusive' => $endEx];
}

function load_groups(PDO $pdo): array {
    $st = $pdo->query("SELECT id, nazev FROM skupiny ORDER BY poradi, nazev");
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function load_subgroups(PDO $pdo, int $skupina_id): array {
    $st = $pdo->prepare("SELECT id, nazev FROM podskupiny WHERE skupina_id = ? ORDER BY poradi, nazev");
    $st->execute([$skupina_id]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function resolve_podskupiny_ids(PDO $pdo, int $skupina_id, ?int $podskupina_id): array {
    if ($podskupina_id && $podskupina_id > 0) return [$podskupina_id];

    $st = $pdo->prepare("SELECT id FROM podskupiny WHERE skupina_id = ? ORDER BY id");
    $st->execute([$skupina_id]);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Treninky v tydnu podle:
 * - trenink_skupina = vybrana skupina
 * - nebo trenink_podskupina IN (podskupiny vybrane skupiny / konkretni podskupina)
 *
 * Pozor: treninky.datum je DATE (Y-m-d)
 */
function load_week_trainings(PDO $pdo, int $skupina_id, ?int $podskupina_id, string $start, string $end_exclusive): array {
    $podsk_ids = resolve_podskupiny_ids($pdo, $skupina_id, $podskupina_id);

    $paramsIn = [];
    $in = '';
    if (!empty($podsk_ids)) {
        $in = implode(',', array_fill(0, count($podsk_ids), '?'));
        $paramsIn = $podsk_ids;
    }

    $sql = "
        SELECT DISTINCT
            t.id, t.datum, t.delka, t.napln, t.poznamka
        FROM treninky t
        LEFT JOIN trenink_skupina ts ON ts.trenink_id = t.id
        LEFT JOIN trenink_podskupina tp ON tp.trenink_id = t.id
        WHERE t.datum >= ? AND t.datum < ?
          AND (
                ts.skupina_id = ?
                " . (!empty($podsk_ids) ? " OR tp.podskupina_id IN ($in) " : "") . "
              )
        ORDER BY t.datum ASC, t.id ASC
    ";

    $final = [$start, $end_exclusive, $skupina_id];
    if (!empty($podsk_ids)) $final = array_merge($final, $paramsIn);

    $st = $pdo->prepare($sql);
    $st->execute($final);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function load_trainers_for_trainings(PDO $pdo, array $treninkIds): array {
    if (empty($treninkIds)) return [];
    $in = implode(',', array_fill(0, count($treninkIds), '?'));

    $sql = "
        SELECT tt.trenink_id, tr.jmeno
        FROM trenink_trener tt
        JOIN treneri tr ON tr.id = tt.trener_id
        WHERE tt.trenink_id IN ($in)
        ORDER BY tr.jmeno
    ";
    $st = $pdo->prepare($sql);
    $st->execute($treninkIds);

    $out = [];
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
        $tid = (int)$r['trenink_id'];
        if (!isset($out[$tid])) $out[$tid] = [];
        $out[$tid][] = $r['jmeno'];
    }
    return $out;
}

function load_participants_for_trainings(PDO $pdo, array $treninkIds): array {
    if (empty($treninkIds)) return [];
    $in = implode(',', array_fill(0, count($treninkIds), '?'));

    $sql = "
        SELECT ts.trenink_id, s.jmeno, s.prijmeni
        FROM trenink_sportovec ts
        JOIN sportovci s ON s.id = ts.sportovec_id
        WHERE ts.trenink_id IN ($in)
        ORDER BY s.prijmeni, s.jmeno
    ";
    $st = $pdo->prepare($sql);
    $st->execute($treninkIds);

    $out = [];
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
        $tid = (int)$r['trenink_id'];
        if (!isset($out[$tid])) $out[$tid] = [];
        $out[$tid][] = trim(($r['jmeno'] ?? '') . ' ' . ($r['prijmeni'] ?? ''));
    }
    return $out;
}

/**
 * Mereni jen pro pritomne (trenink_sportovec)
 * Tabulky dle exportu:
 * - trenink_mereni(trenink_id, mereni_id, poradi)
 * - mereni_zaznamy(...)
 */
function load_measurements_for_trainings_present(PDO $pdo, array $treninkIds): array {
    if (empty($treninkIds)) return [];
    $in = implode(',', array_fill(0, count($treninkIds), '?'));

    $sql = "
        SELECT
            tm.trenink_id,
            tm.poradi,
            mz.id AS mereni_id,
            mz.typ,
            mz.sportovec_id,
            s.jmeno, s.prijmeni,
            mz.vzdalenost, mz.distance_unit, mz.cas, mz.prevod,
            mz.cvik_id, c.nazev AS cvik_nazev,
            mz.segment_id, seg.nazev AS segment_nazev,
            mz.vaha, mz.opakovani, mz.rpe,
            mz.poznamka
        FROM trenink_mereni tm
        JOIN mereni_zaznamy mz ON mz.id = tm.mereni_id
        JOIN trenink_sportovec ts ON ts.trenink_id = tm.trenink_id AND ts.sportovec_id = mz.sportovec_id
        JOIN sportovci s ON s.id = mz.sportovec_id
        LEFT JOIN cviky c ON c.id = mz.cvik_id
        LEFT JOIN segmenty seg ON seg.id = mz.segment_id
        WHERE tm.trenink_id IN ($in)
        ORDER BY tm.trenink_id ASC, tm.poradi ASC, s.prijmeni ASC, s.jmeno ASC, mz.id ASC
    ";
    $st = $pdo->prepare($sql);
    $st->execute($treninkIds);

    $out = [];
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
        $tid = (int)$r['trenink_id'];
        if (!isset($out[$tid])) $out[$tid] = [];
        $out[$tid][] = $r;
    }
    return $out;
}

function build_week_report_html(array $ctx): string {
    $skupinaNazev = $ctx['skupina_nazev'] ?? ('Skupina #' . ($ctx['skupina_id'] ?? ''));
    $podskNazev   = $ctx['podskupina_nazev'] ?? null;

    $weekStart = $ctx['week_start'] ?? '';
    $weekEnd   = $ctx['week_end'] ?? '';

    $trainings = $ctx['trainings'] ?? [];
    $trainersByT = $ctx['trainersByT'] ?? [];
    $participantsByT = $ctx['participantsByT'] ?? [];
    $measurementsByT = $ctx['measurementsByT'] ?? [];

    ob_start();
    ?>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        .muted { color:#666; }
        .box { border:1px solid #ddd; border-radius:10px; padding:12px; margin-bottom:12px; }
        .h { font-size:16px; font-weight:700; margin:0 0 6px; }
        .subh { font-size:13px; font-weight:700; margin:12px 0 6px; }
        .row { margin:2px 0; }
        .badge { display:inline-block; padding:2px 8px; border:1px solid #ddd; border-radius:999px; font-size:11px; }
        table { width:100%; border-collapse:collapse; margin-top:6px; }
        th, td { border:1px solid #ddd; padding:6px; vertical-align:top; }
        th { background:#f5f5f5; }
        .mb0 { margin-bottom:0; }
        .mt8 { margin-top:8px; }
        .small { font-size: 11px; }
    </style>

    <div class="box">
        <div class="h">Týdenní report</div>
        <div class="row"><strong>Skupina:</strong> <?= h($skupinaNazev) ?>
            <?php if ($podskNazev): ?>
                <span class="badge">Podskupina: <?= h($podskNazev) ?></span>
            <?php else: ?>
                <span class="badge">včetně všech podskupin</span>
            <?php endif; ?>
        </div>
        <div class="row muted"><strong>Období:</strong> <?= h($weekStart) ?> (Po) – <?= h($weekEnd) ?> (Ne)</div>
        <div class="row muted small">Vygenerováno: <?= h(date('Y-m-d H:i:s')) ?></div>
    </div>

    <?php if (empty($trainings)): ?>
        <div class="box"><strong>Bez tréninků.</strong></div>
    <?php else: ?>
        <?php foreach ($trainings as $t):
            $tid = (int)$t['id'];
            $datum = $t['datum'] ?? '';
            $delka = $t['delka'] ?? '';
            $napln = $t['napln'] ?? '';
            $poznamka = $t['poznamka'] ?? '';

            $treners = $trainersByT[$tid] ?? [];
            $parts   = $participantsByT[$tid] ?? [];
            $mers    = $measurementsByT[$tid] ?? [];
        ?>
        <div class="box">
            <div class="h mb0"><?= h($datum) ?> <span class="muted">(#<?= $tid ?>)</span></div>
            <div class="row"><strong>Délka:</strong> <?= h($delka) ?> h</div>
            <?php if (!empty($treners)): ?>
                <div class="row"><strong>Trenéři:</strong> <?= h(implode(', ', $treners)) ?></div>
            <?php endif; ?>

            <div class="subh">Účast (<?= count($parts) ?>)</div>
            <?php if (empty($parts)): ?>
                <div class="muted">Bez účastníků.</div>
            <?php else: ?>
                <div><?= h(implode(', ', $parts)) ?></div>
            <?php endif; ?>

            <?php if (trim($napln) !== ''): ?>
                <div class="subh">Náplň</div>
                <div><?= nl2br(h($napln)) ?></div>
            <?php endif; ?>

            <?php if (trim((string)$poznamka) !== ''): ?>
                <div class="subh">Poznámka</div>
                <div><?= nl2br(h($poznamka)) ?></div>
            <?php endif; ?>

            <?php if (!empty($mers)): ?>
                <div class="subh mt8">Měření (přítomní)</div>
                <table>
                    <thead>
                        <tr>
                            <th style="width:18%;">Sportovec</th>
                            <th style="width:12%;">Typ</th>
                            <th>Data</th>
                            <th style="width:26%;">Poznámka</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($mers as $m):
                        $sport = trim(($m['jmeno'] ?? '') . ' ' . ($m['prijmeni'] ?? ''));
                        $typ = $m['typ'] ?? '';

                        $dataParts = [];
                        if ($typ === 'kolo' || $typ === 'beh') {
                            if (($m['vzdalenost'] ?? '') !== '' && $m['vzdalenost'] !== null) $dataParts[] = 'vzdálenost: ' . $m['vzdalenost'] . ' ' . sportsMeasurementDisplayUnit($m['distance_unit'] ?? null);
                            if (($m['cas'] ?? '') !== '') $dataParts[] = 'čas: ' . $m['cas'];
                            if ($typ === 'kolo' && ($m['prevod'] ?? '') !== '') $dataParts[] = 'převod: ' . $m['prevod'];
                        } elseif ($typ === 'posilovna') {
                            $cvik = $m['cvik_nazev'] ?? '';
                            if ($cvik !== '') $dataParts[] = 'cvik: ' . $cvik;
                            if (($m['vaha'] ?? '') !== '' && $m['vaha'] !== null) $dataParts[] = 'váha: ' . $m['vaha'] . ' kg';
                            if (($m['opakovani'] ?? '') !== '' && $m['opakovani'] !== null) $dataParts[] = 'opak.: ' . $m['opakovani'];
                            if (($m['rpe'] ?? '') !== '') $dataParts[] = 'RPE: ' . $m['rpe'];
                        } elseif ($typ === 'kolo_krouzek' || $typ === 'kolo_silnice' || $typ === 'kolo_mtb') {
                            $seg = $m['segment_nazev'] ?? '';
                            if ($seg !== '') $dataParts[] = 'segment: ' . $seg;
                            if (($m['cas'] ?? '') !== '') $dataParts[] = 'čas: ' . $m['cas'];
                        }

                        $dataTxt = implode(' • ', array_map('h', $dataParts));
                        $poz = $m['poznamka'] ?? '';
                    ?>
                        <tr>
                            <td><?= h($sport) ?></td>
                            <td><?= h($typ) ?></td>
                            <td><?= $dataTxt !== '' ? $dataTxt : '<span class="muted">—</span>' ?></td>
                            <td><?= nl2br(h($poz)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php
    return ob_get_clean();
}

function save_week_report(PDO $pdo, array $ctx, string $baseDirAbs): array {
    if (!is_dir($baseDirAbs)) @mkdir($baseDirAbs, 0775, true);

    $weekStart  = $ctx['week_start'] ?? '';
    $weekEnd    = $ctx['week_end'] ?? '';
    $skupina_id = (int)($ctx['skupina_id'] ?? 0);
    $podsk_id   = (int)($ctx['podskupina_id'] ?? 0);

    $stamp = date('Ymd_His');
    $key = "week_{$weekStart}_{$weekEnd}_g{$skupina_id}" . ($podsk_id ? "_p{$podsk_id}" : "") . "_{$stamp}";

    $dir = rtrim($baseDirAbs, '/\\') . DIRECTORY_SEPARATOR . substr($weekStart, 0, 4) . DIRECTORY_SEPARATOR . $key;
    @mkdir($dir, 0775, true);

    $html = build_week_report_html($ctx);

    $htmlPath = $dir . DIRECTORY_SEPARATOR . $key . '.html';
    file_put_contents($htmlPath, $html);

    $pdfPath = $dir . DIRECTORY_SEPARATOR . $key . '.pdf';

    $vendor = __DIR__ . '/vendor/autoload.php';
    if (!file_exists($vendor)) {
        throw new RuntimeException("Chybi vendor/autoload.php. Spust composer require mpdf/mpdf");
    }
    require_once $vendor;

    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'margin_left' => 10,
        'margin_right' => 10,
        'margin_top' => 10,
        'margin_bottom' => 10,
        'default_font' => 'dejavusans'
    ]);

    $mpdf->WriteHTML($html);
    $mpdf->Output($pdfPath, \Mpdf\Output\Destination::FILE);

    return ['dir' => $dir, 'html' => $htmlPath, 'pdf' => $pdfPath];
}

function build_report_context(PDO $pdo, int $skupina_id, ?int $podskupina_id, string $anyDate): array {
    $rng = week_range_from_any_date($anyDate);
    $start = $rng['start'];
    $endEx = $rng['end_exclusive'];

    $weekStart = $start;
    $weekEnd = (new DateTime($start))->modify('+6 days')->format('Y-m-d');

    $st = $pdo->prepare("SELECT nazev FROM skupiny WHERE id = ?");
    $st->execute([$skupina_id]);
    $skNazev = (string)$st->fetchColumn();

    $podNazev = null;
    if ($podskupina_id && $podskupina_id > 0) {
        $st = $pdo->prepare("SELECT nazev FROM podskupiny WHERE id = ?");
        $st->execute([$podskupina_id]);
        $podNazev = (string)$st->fetchColumn();
    }

    $trainings = load_week_trainings($pdo, $skupina_id, $podskupina_id, $start, $endEx);
    $ids = array_map(fn($r) => (int)$r['id'], $trainings);

    $trainersByT = load_trainers_for_trainings($pdo, $ids);
    $participantsByT = load_participants_for_trainings($pdo, $ids);
    $measurementsByT = load_measurements_for_trainings_present($pdo, $ids);

    return [
        'skupina_id' => $skupina_id,
        'podskupina_id' => $podskupina_id ? (int)$podskupina_id : 0,
        'skupina_nazev' => $skNazev,
        'podskupina_nazev' => $podNazev,
        'week_start' => $weekStart,
        'week_end' => $weekEnd,
        'trainings' => $trainings,
        'trainersByT' => $trainersByT,
        'participantsByT' => $participantsByT,
        'measurementsByT' => $measurementsByT,
    ];
}
