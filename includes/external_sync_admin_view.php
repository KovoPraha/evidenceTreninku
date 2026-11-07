<?php

declare(strict_types=1);

function externalSyncAdminH(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function externalSyncAdminUrl(array $query): string
{
    return 'external_sync_admin.php?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
}

function externalSyncAdminTime(mixed $timestamp): string
{
    return $timestamp === null ? '—' : gmdate('d. m. Y H:i:s', (int)$timestamp) . ' UTC';
}

function externalSyncAdminRunStatus(?string $status): string
{
    return match ($status) {
        null => 'Dosud neproběhla', 'complete' => 'Přijato', 'review_required' => 'Čeká na kontrolu',
        'collecting' => 'Probíhá', 'failed' => 'Selhalo', 'auth_required' => 'Vyžaduje přihlášení',
        'expired' => 'Vypršel pronájem běhu', default => $status,
    };
}

function externalSyncAdminCollection(string $collection): string
{
    return match ($collection) {
        'csc_licenses' => 'ČSC · licence', 'sportsoft_members' => 'Sportsoft · členové',
        'sportsoft_events' => 'Sportsoft · závody', 'sportsoft_registrations' => 'Sportsoft · přihlášky',
        default => $collection,
    };
}

function externalSyncAdminFailure(?string $code): string
{
    return match ($code) {
        'auth_required' => 'Stanice potřebuje nové přihlášení k portálu.',
        'layout_changed' => 'Změnila se struktura stránky portálu.',
        'incomplete' => 'Stanice nezískala úplný snímek dat.',
        'network_error' => 'Přenos selhal kvůli síťové chybě.',
        'not_created' => 'Přihláška na portálu dosud není vytvořená.',
        null => '', default => 'Stanice nahlásila neznámou chybu.',
    };
}

function externalSyncAdminValue(mixed $value): string
{
    return $value === null || $value === '' ? 'Neuvedeno' : (is_bool($value) ? ($value ? 'Ano' : 'Ne') : (string)$value);
}

/** Standalone template function also rendered by the synthetic HTTP harness. */
function externalSyncAdminRender(array $overview, ?array $detail, array $input, bool $enabled, string $error = ''): void
{
    $h = 'externalSyncAdminH';
    $url = static fn (array $query): string => externalSyncAdminH(externalSyncAdminUrl($query));
    ?>
<main class="container py-4" id="external-sync-overview">
    <h1>Synchronizace ČSC a Sportsoftu</h1>
    <p>Čtecí přehled pro registráře. Údaje zdroje se porovnávají s aktuální evidencí KIS podle UCI ID.</p>
    <div class="alert <?= $enabled ? 'alert-info' : 'alert-secondary' ?>">
        Příjem dat: <strong><?= $enabled ? 'povolen konfigurací' : 'vypnutý' ?></strong>.
        Tento přehled nezapíná synchronizaci ani nemění osoby, licence či přihlášky.
    </div>
    <?php if ($error !== '') : ?>
        <div class="alert alert-danger" role="alert"><?= $h($error) ?></div>
    <?php else : ?>
    <section aria-labelledby="sync-connections">
        <h2 class="h4" id="sync-connections">Zdroje dat</h2>
        <?php if ($overview['rows'] === []) : ?>
            <p>Na této stránce nejsou žádná připojení. Příprava úložiště ani připojení se zde neprovádí.</p>
        <?php endif; ?>
        <div class="row g-3 mb-3">
        <?php foreach ($overview['rows'] as $connection) : ?>
            <div class="col-lg-6"><article class="card h-100"><div class="card-body">
                <h3 class="h5"><a href="<?= $url(['connection' => $connection['id']]) ?>">
                    <?= $h(externalSyncAdminCollection($connection['collection'])) ?></a></h3>
                <p class="text-break mb-2">Rozsah: <?= $h($connection['scope_id']) ?>
                    <?php if ($connection['event_id'] !== null) : ?> · závod <?= $h($connection['event_id']) ?><?php endif; ?>
                    <br><small>Připojení <?= $h($connection['id']) ?></small>
                </p>
                <p>Připojení: <strong><?= (int)$connection['enabled'] ? 'povoleno' : 'vypnuto' ?></strong></p>
                <p class="mb-1"><strong>Poslední přijatá data:</strong>
                    <?php if ($connection['latest_run_id'] === null) : ?>žádná<?php else : ?>
                        <?= $h(externalSyncAdminTime($connection['accepted_at'])) ?> · <?= (int)$connection['latest_row_count'] ?> řádků
                        <?php if ($connection['accepted_status'] !== 'complete') : ?>
                            <span class="badge text-bg-danger">Nekonzistentní ukazatel snímku</span>
                        <?php elseif ((int)$connection['accepted_at'] < time() - 86400) : ?>
                            <span class="badge text-bg-warning">Starší než 24 hodin</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </p>
                <p class="mb-0"><strong>Poslední pokus:</strong> <?= $h(externalSyncAdminRunStatus($connection['attempt_status'])) ?>
                    · <?= $h(externalSyncAdminTime($connection['attempt_at'])) ?>
                    <?php if ($connection['attempt_id'] !== null) : ?>
                        <a href="<?= $url(['connection' => $connection['id'], 'run' => $connection['attempt_id']]) ?>">Detail pokusu</a>
                    <?php endif; ?>
                </p>
                <?php if ($connection['failure_code'] !== null) : ?>
                    <p class="mt-2 mb-0"><?= $h(externalSyncAdminFailure($connection['failure_code'])) ?></p>
                <?php endif; ?>
            </div></article></div>
        <?php endforeach; ?>
        </div>
        <nav aria-label="Stránky připojení" class="d-flex flex-wrap gap-3 mb-4">
            <?php if ($input['connections_page'] > 1) : ?>
                <a href="<?= $url(['connections_page' => $input['connections_page'] - 1]) ?>">Předchozí připojení</a>
            <?php endif; ?>
            <span>Stránka <?= (int)$input['connections_page'] ?></span>
            <?php if ($overview['has_next']) : ?>
                <a href="<?= $url(['connections_page' => $input['connections_page'] + 1]) ?>">Další připojení</a>
            <?php endif; ?>
        </nav>
    </section>
    <?php if ($detail !== null) :
        $connection = $detail['connection'];
        $run = $detail['run'];
        $projection = $detail['projection'];
        ?>
    <section aria-labelledby="sync-detail">
        <h2 id="sync-detail"><?= $h(externalSyncAdminCollection($connection['collection'])) ?></h2>
        <p class="text-break">Rozsah <?= $h($connection['scope_id']) ?> · připojení <?= $h($connection['id']) ?>
            <?php if ($connection['event_id'] !== null) : ?> · závod <?= $h($connection['event_id']) ?><?php endif; ?>
        </p>
        <h3 class="h5">Historie běhů</h3>
        <?php if ($detail['history'] === []) : ?><p>Žádný běh zatím neproběhl.</p><?php endif; ?>
        <ul class="list-group mb-3">
        <?php foreach ($detail['history'] as $item) : ?>
            <li class="list-group-item">
                <a href="<?= $url(['connection' => $connection['id'], 'run' => $item['id']]) ?>">Běh #<?= (int)$item['sequence_no'] ?></a>
                · <?= $h(externalSyncAdminRunStatus($item['status'])) ?> · začátek <?= $h(externalSyncAdminTime($item['started_at'])) ?>
                <?php if ($item['failure_code'] !== null) : ?> · <?= $h(externalSyncAdminFailure($item['failure_code'])) ?><?php endif; ?>
                <?php if ($item['status'] === 'collecting' && (int)$item['lease_until'] < time()) : ?>
                    <span class="badge text-bg-warning">Bez platného pronájmu běhu</span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
        </ul>
        <nav aria-label="Stránky historie" class="d-flex gap-3 mb-4">
            <?php if ($input['history_page'] > 1) : ?>
                <a href="<?= $url(array_replace($input, ['history_page' => $input['history_page'] - 1])) ?>">Předchozí běhy</a>
            <?php endif; ?>
            <span>Stránka <?= (int)$input['history_page'] ?></span>
            <?php if ($detail['history_has_next']) : ?>
                <a href="<?= $url(array_replace($input, ['history_page' => $input['history_page'] + 1])) ?>">Další běhy</a>
            <?php endif; ?>
        </nav>
        <?php if ($run === null) : ?>
            <p>Dosud nejsou přijata žádná data. Návrh čekající na kontrolu lze otevřít v historii.</p>
        <?php else : ?>
            <h3 class="h4">Běh #<?= (int)$run['sequence_no'] ?> · <?= $h(externalSyncAdminRunStatus($run['status'])) ?></h3>
            <p>Začátek příjmu: <?= $h(externalSyncAdminTime($run['started_at'])) ?>.
                Dokončení příjmu: <?= $h(externalSyncAdminTime($run['finished_at'])) ?>.</p>
            <?php if ($run['status'] === 'review_required') : ?>
                <div class="alert alert-warning">Návrh čekající na kontrolu. Dosud nebyl přijat; zobrazení jej neschvaluje.
                    <?php if ((int)$run['sequence_no'] < (int)$connection['next_sequence']) : ?>Existuje novější pokus.<?php endif; ?>
                </div>
            <?php elseif ($run['id'] !== $connection['latest_run_id']) : ?>
                <div class="alert alert-secondary">Historický pokus. Není posledním přijatým snímkem.</div>
            <?php endif; ?>
            <?php if ($projection === null) : ?>
                <p>Neúplný či neúspěšný běh neposkytuje řádky k párování. Poslední přijatá data zůstávají dostupná v přehledu zdroje.</p>
            <?php else : ?>
                <p>Celkem <?= (int)$projection['total'] ?> řádků · k prověření <?= (int)$projection['needs_review'] ?>.
                    Párování níže odpovídá současné evidenci KIS, nikoli historickému výsledku importu.</p>
                <form method="get" class="d-flex flex-wrap align-items-end gap-2 mb-3">
                    <input type="hidden" name="connection" value="<?= $h($connection['id']) ?>">
                    <input type="hidden" name="run" value="<?= $h($run['id']) ?>">
                    <div><label for="sync-filter" class="form-label">Výsledek párování</label>
                        <select id="sync-filter" name="filter" class="form-select">
                        <?php foreach (['needs_review' => ['K prověření'], 'all' => ['Všechny řádky']] + externalSyncAdminReasons() as $key => $label) : ?>
                            <option value="<?= $h($key) ?>" <?= $input['filter'] === $key ? 'selected' : '' ?>><?= $h($label[0]) ?></option>
                        <?php endforeach; ?>
                        </select>
                    </div><button class="btn btn-primary" type="submit">Zobrazit</button>
                </form>
                <p>Počet řádků odpovídajících filtru: <?= (int)$projection['selected'] ?>. Stránka <?= (int)$input['page'] ?>, nejvýše 50 řádků.</p>
                <?php if ($projection['rows'] === []) : ?><p>Na této stránce nejsou řádky odpovídající filtru.</p><?php endif; ?>
                <?php foreach ($projection['rows'] as $record) :
                    $match = $record['match'];
                    $reason = externalSyncAdminReasons()[$match['status']];
                    ?>
                    <article class="card mb-3 sync-record"><div class="card-body text-break">
                        <h4 class="h5">#<?= (int)$record['ordinal'] ?> · <?= $h($record['source']['display_name'] ?? $record['source']['event_name'] ?? 'Řádek zdroje') ?></h4>
                        <p><strong><?= $h($reason[0]) ?></strong> · <?= $h($reason[1]) ?></p>
                        <?php if ($connection['collection'] !== 'sportsoft_events') : ?>
                            <p>UCI ID: <?= $h($match['uci_id'] ?? 'Neplatné nebo neuvedené; původní hodnota je v polích zdroje.') ?></p>
                        <?php endif; ?>
                        <?php if ($match['sportovec_id'] !== null) : ?>
                            <p><a href="sportovec_karta.php?id=<?= (int)$match['sportovec_id'] ?>">Karta osoby #<?= (int)$match['sportovec_id'] ?></a></p>
                        <?php elseif ($match['candidate_count'] > 0) : ?>
                            <p>Neověření kandidáti podle UCI ID: <?= $h(implode(', ', $match['candidate_ids'])) ?>
                                (celkem <?= (int)$match['candidate_count'] ?>, zobrazeno nejvýše 10). Vazba nebyla potvrzena.</p>
                        <?php endif; ?>
                        <p class="small">Pozorováno ve zdroji: <?= $h($record['observed_at'] ?? 'Čas není dostupný') ?></p>
                        <details><summary>Pole zdroje</summary><dl class="row mt-2">
                        <?php foreach (externalSyncAdminFields($connection['collection']) as $field => $label) : ?>
                            <dt class="col-sm-4"><?= $h($label) ?></dt>
                            <dd class="col-sm-8"><?= $h(externalSyncAdminValue($record['source'][$field] ?? null)) ?></dd>
                        <?php endforeach; ?>
                        </dl></details>
                        <?php if ($connection['collection'] === 'sportsoft_registrations') : ?>
                            <p>Platba dle kontraktu: <?= $record['normalized']['paid'] === 'paid' ? 'zaplaceno' : 'neznámá' ?>. Platbu v KIS to nepotvrzuje.</p>
                        <?php elseif ($connection['collection'] === 'csc_licenses') : ?>
                            <p>Rok ve zdroji sám o sobě nepotvrzuje platnost licence.</p>
                        <?php endif; ?>
                    </div></article>
                <?php endforeach; ?>
                <nav aria-label="Stránky řádků" class="d-flex gap-3 mb-4">
                    <?php if ($input['page'] > 1) : ?>
                        <a href="<?= $url(array_replace($input, ['run' => $run['id'], 'page' => $input['page'] - 1])) ?>">Předchozí řádky</a>
                    <?php endif; ?>
                    <?php if ($projection['has_next']) : ?>
                        <a href="<?= $url(array_replace($input, ['run' => $run['id'], 'page' => $input['page'] + 1])) ?>">Další řádky</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </section>
    <?php endif; ?>
    <?php endif; ?>
</main>
    <?php
}
