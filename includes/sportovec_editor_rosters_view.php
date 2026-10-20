<?php $rosters = $editContext['rosters']; $rosterActionUrl = $editorActionUrl ?? (spUrl(['edit_id' => $editId]) . '#rosters-title'); ?>
<section class="mt-4" aria-labelledby="rosters-title">
    <h2 class="h5 border-bottom pb-2" id="rosters-title">Soupisky sportovce</h2>
    <?php if (!empty($editError) && ($editorLastAction ?? '') === 'add_roster'): ?><div class="alert alert-danger" role="alert"><?= h($editError) ?></div><?php endif; ?>
    <form method="post" action="<?= h($rosterActionUrl) ?>" class="border rounded p-3 mb-4">
        <?= csrf_field() ?><input type="hidden" name="akce" value="add_roster"><input type="hidden" name="id" value="<?= (int)$editId ?>">
        <div class="row g-3 align-items-end">
            <div class="col-lg-7"><label class="form-label" for="add-roster-team">Zařadit do soupisky</label><select class="form-select" id="add-roster-team" name="team_id" required><option value="">Vyberte soupisku a sezonu</option>
                <?php foreach ($rosters['choices'] as $team): ?><option value="<?= (int)$team['id'] ?>"><?= h($team['season_name'] . ' · ' . $team['name']) ?></option><?php endforeach; ?>
            </select></div>
            <div class="col-lg-3"><label class="form-label" for="add-roster-date">Zařadit od (volitelné)</label><input class="form-control" type="date" id="add-roster-date" name="valid_from"></div>
            <div class="col-lg-2"><button class="btn btn-primary w-100" type="submit" <?= !$rosters['choices'] ? 'disabled' : '' ?>>Uložit zařazení</button></div>
            <div class="col-12"><label class="form-label" for="add-roster-reason">Poznámka k zařazení (volitelné)</label><input class="form-control" id="add-roster-reason" name="reason" maxlength="1000"></div>
        </div>
        <p class="small text-muted mb-0 mt-2">Bez zadaného data se sportovec zařadí ode dneška, u budoucí sezony od jejího začátku. Opakované uložení stejného aktivního členství nevytvoří duplicitu.</p>
        <?php if (!$rosters['choices']): ?><p class="text-muted mb-0">Není dostupná aktivní soupiska pro probíhající nebo budoucí sezonu.</p><?php endif; ?>
    </form>
    <?php foreach (['current' => 'Aktuální soupisky', 'planned' => 'Budoucí zařazení', 'history' => 'Historické soupisky'] as $bucket => $label): ?>
        <h3 class="h6 mt-3"><?= h($label) ?></h3>
        <?php if (!$rosters[$bucket]): ?><p class="text-muted small">Žádné evidované zařazení.</p><?php else: ?>
        <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Soupiska a sezona</th><th>Platnost členství</th><th>Stav a původ</th></tr></thead><tbody>
        <?php foreach ($rosters[$bucket] as $membership): ?><tr>
            <td><a href="kis_rosters_admin.php?team_id=<?= (int)$membership['team_id'] ?>"><?= h($membership['team_name']) ?></a><br><small><?= h($membership['season_name']) ?></small></td>
            <td><?= h($membership['valid_from']) ?> – <?= h($membership['valid_to'] ?: 'bez konce') ?></td>
            <td><?= h(sportovecEditorStatusLabel($membership['status'])) ?> · <?= h($membership['source'] === 'kis_shadow' ? 'Import KIS' : $membership['source']) ?><?php if ($membership['source'] === 'kis_shadow' && $bucket === 'history'): ?><br><small class="text-muted">U historického importu mohou data vymezovat pouze sezonu.</small><?php endif; ?></td>
        </tr><?php endforeach; ?></tbody></table></div>
        <?php endif; ?>
    <?php endforeach; ?>
    <?php if ($rosters['claims']): ?><h3 class="h6 mt-3">Původní zařazení ze starého KIS bez potvrzeného členství</h3>
        <ul><?php foreach ($rosters['claims'] as $claim): ?><li><?= h($claim['season_name'] . ' · ' . $claim['team_name']) ?> · <?= h(sportovecEditorStatusLabel($claim['status'])) ?><?= $claim['decision_note'] ? ' · ' . h($claim['decision_note']) : '' ?></li><?php endforeach; ?></ul>
    <?php endif; ?>
    <?php if ($rosters['current_claims']): ?><h3 class="h6 mt-3">Návrhy převodu do aktuálních soupisek bez potvrzené vazby</h3>
        <ul><?php foreach ($rosters['current_claims'] as $claim): ?><li><?= h($claim['season_name'] . ' · ' . $claim['team_name']) ?> · <?= h(sportovecEditorStatusLabel($claim['status'])) ?><?= $claim['decision_note'] ? ' · ' . h($claim['decision_note']) : '' ?></li><?php endforeach; ?></ul>
    <?php endif; ?>
    <?php if ($rosters['events']): ?><details class="border rounded p-3 mt-3"><summary class="fw-semibold">Všechny zaznamenané změny členství (<?= count($rosters['events']) ?>)</summary>
        <div class="table-responsive mt-2"><table class="table table-sm"><thead><tr><th>Datum</th><th>Soupiska a změna</th><th>Předchozí → nové období</th><th>Poznámka</th></tr></thead><tbody>
        <?php foreach ($rosters['events'] as $event): $before = json_decode($event['before_json'] ?? 'null', true) ?: []; $after = json_decode($event['after_json'] ?? '{}', true) ?: []; ?><tr>
            <td><?= h($event['created_at']) ?></td><td><?= h($event['team_name']) ?><br><?= h(sportovecEditorStatusLabel($event['action'])) ?></td>
            <td><?= h(($before['valid_from'] ?? '—') . ' / ' . ($before['valid_to'] ?? '—')) ?> → <?= h(($after['valid_from'] ?? '—') . ' / ' . ($after['valid_to'] ?? 'bez konce')) ?></td><td><?= h($event['note']) ?></td>
        </tr><?php endforeach; ?></tbody></table></div>
    </details><?php endif; ?>
</section>
