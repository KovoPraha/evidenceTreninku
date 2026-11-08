<?php
/** Authorized profile fragment; included only after the endpoint session checks. */
if (!isset($measurementPage, $measurementType, $mojeMereni)) {
    http_response_code(404);
    exit;
}
?>
      <?php if ($measurementPage['all'] === 0): ?>
        <div class="alert alert-info mb-0">Zatím nemáš žádná měření.</div>
      <?php else: ?>

        <!-- Type filter pills -->
        <div class="d-flex gap-2 flex-wrap mb-3">
          <button class="btn btn-sm btn-outline-secondary <?= $measurementType === '' ? 'active' : '' ?> mereni-filter-btn" data-typ="">Vše (<?= $measurementPage['all'] ?>)</button>
          <?php
            $typCounts = $measurementPage['types'];
          $typLabels = ['kolo' => '🚴 Kolo', 'kolo_krouzek' => '🔄 Kroužek', 'kolo_silnice' => '🛣️ Silnice', 'kolo_mtb' => '🌲 MTB', 'beh' => '🏃 Běh', 'posilovna' => '💪 Posilovna'];
          foreach ($typCounts as $typ => $cnt):
              ?>
          <button class="btn btn-sm btn-outline-secondary <?= (string)$typ === $measurementType ? 'active' : '' ?> mereni-filter-btn" data-typ="<?= h($typ) ?>">
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
                          $typBadge = ['kolo' => 'mereni-typ-kolo',
                              'kolo_krouzek' => 'mereni-typ-kolo_krouzek',
                              'kolo_silnice' => 'mereni-typ-kolo_silnice',
                              'kolo_mtb' => 'mereni-typ-kolo_mtb',
                              'beh' => 'mereni-typ-beh',
                              'posilovna' => 'mereni-typ-posilovna'];
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
<?php AthleteProfileRead::navigation($measurementPage, ['view' => 'measurements', 'typ' => $measurementType]); ?>
