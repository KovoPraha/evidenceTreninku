<?php

declare(strict_types=1);

function externalSyncReviewRender(array $context): void
{
    $h = 'externalSyncAdminH';
    $states = externalSyncReviewStates();
    $match = $context['match'];
    $review = $context['review'];
    $query = ['run' => $context['run']['id'], 'ordinal' => $context['record']['ordinal_no']];
    ?>
    <section class="text-break">
        <h2 class="h4"><?= $h(externalSyncAdminCollection($context['connection']['collection'])) ?> · řádek #<?= (int)$query['ordinal'] ?></h2>
        <p>Rozsah <?= $h($context['connection']['scope_id']) ?> · běh <?= $h($query['run']) ?>
            · <?= $h(externalSyncAdminRunStatus($context['run']['status'])) ?></p>
        <p><strong><?= $h(externalSyncAdminReasons()[$match['status']][0]) ?></strong>
            · <?= $h(externalSyncAdminReasons()[$match['status']][1]) ?> · UCI ID <?= $h($match['uci_id'] ?? 'neuvedeno / neplatné') ?></p>
        <?php if ($match['sportovec_id'] !== null) : ?>
            <p><a href="sportovec_karta.php?id=<?= (int)$match['sportovec_id'] ?>&amp;tab=kis">Karta osoby #<?= (int)$match['sportovec_id'] ?></a></p>
        <?php endif; ?>
        <details><summary>Pole zdroje</summary><dl class="row mt-2">
            <?php foreach (externalSyncAdminFields($context['connection']['collection']) as $field => $label) : ?>
                <dt class="col-sm-4"><?= $h($label) ?></dt><dd class="col-sm-8"><?= $h(externalSyncAdminValue($context['source'][$field] ?? null)) ?></dd>
            <?php endforeach; ?>
        </dl></details>
        <p class="mt-3">Stav řešení: <strong><?= $h($states[$review['state']]) ?></strong> · revize <?= (int)$review['revision'] ?></p>
        <?php if ($review['stale']) : ?><p class="alert alert-warning">Podklady se změnily. Předchozí rozhodnutí je v historii, záznam vyžaduje nové posouzení.</p><?php endif; ?>
        <?php if (!$context['current']) : ?>
            <p class="alert alert-secondary">Historický snímek je dostupný pouze ke čtení.</p>
        <?php else : ?>
            <form method="post" action="external_sync_review.php" class="card card-body my-3">
                <?= csrf_field() ?>
                <input type="hidden" name="run" value="<?= $h($query['run']) ?>">
                <input type="hidden" name="ordinal" value="<?= (int)$query['ordinal'] ?>">
                <input type="hidden" name="revision" value="<?= (int)$review['revision'] ?>">
                <input type="hidden" name="basis" value="<?= $h($review['basis_hash']) ?>">
                <label for="review-state" class="form-label">Nové řešení</label>
                <select id="review-state" name="state" required class="form-select mb-3">
                    <option value="">Vyberte řešení</option>
                    <?php foreach ($states as $key => $label) : ?>
                        <?php if (($key === 'resolved' && $match['status'] !== 'matched')
                            || (in_array($key, ['deferred', 'excluded'], true) && $match['status'] === 'matched')) {
                            continue;
                        } ?>
                        <option value="<?= $h($key) ?>"><?= $h($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <label for="review-reason" class="form-label">Důvod rozhodnutí (10–1000 znaků)</label>
                <textarea id="review-reason" name="reason" minlength="10" maxlength="1000" rows="3" required class="form-control mb-3"></textarea>
                <p class="small">Zápis uloží rozhodnutí a jeho autora. Ověření po opravě je možné pouze při jednoznačné shodě s aktuální evidencí.
                    Změny licence či přihlášky se na portály neodesílají.</p>
                <button class="btn btn-primary align-self-start" type="submit">Uložit rozhodnutí</button>
            </form>
        <?php endif; ?>
        <h3 class="h5">Historie rozhodnutí</h3>
        <?php if ($context['history'] === []) : ?><p>Dosud bez rozhodnutí.</p><?php endif; ?>
        <ol class="list-group mb-3">
        <?php foreach ($context['history'] as $event) : ?>
            <li class="list-group-item text-break"><strong>Revize <?= (int)$event['revision'] ?> · <?= $h($states[$event['state']]) ?></strong>
                <br><?= $h(externalSyncAdminTime($event['recorded_at'])) ?> · pracovník #<?= (int)$event['actor_id'] ?>
                <p class="mb-0" style="white-space:pre-wrap"><?= $h($event['reason']) ?></p></li>
        <?php endforeach; ?>
        </ol>
        <nav class="d-flex gap-3" aria-label="Historie rozhodnutí">
            <a href="external_sync_review.php?<?= $h(http_build_query($query)) ?>">Aktuální detail</a>
            <?php if ($context['history_has_next']) : ?>
                <a href="external_sync_review.php?<?= $h(http_build_query($query + ['before' => end($context['history'])['revision']])) ?>">Starší rozhodnutí</a>
            <?php endif; ?>
        </nav>
    </section>
    <?php
}
