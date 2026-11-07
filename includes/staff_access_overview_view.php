<?php
// Included only by the system-admin page after authorization.
if (!isset($trainers, $visibleTrainers, $accessGroups, $definitions, $search, $roleFilter, $statusFilter, $selectedId)
    || !function_exists('staffAdminH')) {
    http_response_code(404);
    exit;
}
?>
<div class="d-flex flex-wrap gap-2 mb-3">
    <a class="btn btn-outline-primary" href="sprava_treneru.php"><i class="bi bi-person-plus me-1"></i>Pracovní účty a nový účet</a>
    <a class="btn btn-outline-secondary" href="bar/permissions.php">Vlastní spotřeba baru</a>
</div>
<form method="get" class="card card-body border-0 shadow-sm mb-3" role="search" aria-label="Hledání pracovních účtů">
    <div class="row g-3 align-items-end">
        <div class="col-lg-4"><label class="form-label" for="access-search">Jméno, e-mail nebo #ID</label>
            <input class="form-control" type="search" name="q" id="access-search" value="<?= staffAdminH($search) ?>" placeholder="Najít člověka"></div>
        <div class="col-lg-4"><label class="form-label" for="access-role">Oblast nebo role</label>
            <select class="form-select" name="access" id="access-role">
                <option value="">Všechny přístupy</option>
                <optgroup label="Oblasti"><?php foreach ($accessGroups as $key => $group): ?>
                    <option value="<?= staffAdminH($key) ?>" <?= $roleFilter === $key ? 'selected' : '' ?>><?= staffAdminH($group['label']) ?></option>
                <?php endforeach; ?></optgroup>
                <optgroup label="Jednotlivé role"><?php foreach ($definitions as $code => $position): ?>
                    <option value="<?= staffAdminH($code) ?>" <?= $roleFilter === $code ? 'selected' : '' ?>><?= staffAdminH($position['label']) ?></option>
                <?php endforeach; ?></optgroup>
                <optgroup label="Zvláštní přístupy">
                    <?php foreach (['superadmin' => 'Superadmin', 'own_consumption' => 'Vlastní spotřeba baru', 'unassigned' => 'Bez pracovní pozice'] as $code => $label): ?>
                    <option value="<?= $code ?>" <?= $roleFilter === $code ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </optgroup>
            </select></div>
        <div class="col-lg-2"><label class="form-label" for="access-status">Stav účtu</label>
            <select class="form-select" name="status" id="access-status">
                <option value="">Všechny účty</option>
                <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Aktivní</option>
                <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Deaktivované</option>
            </select></div>
        <div class="col-lg-2 d-flex gap-2"><button class="btn btn-primary">Filtrovat</button><a class="btn btn-outline-secondary" href="sprava_pracovnich_pozic.php">Zrušit</a></div>
    </div>
</form>
<p class="small text-muted" id="access-legend">Zobrazeno <?= count($visibleTrainers) ?> z <?= count($trainers) ?> pracovních účtů.
    Hvězdička ★ označuje výchozí pozici. Superadmin má dostupné všechny role; vlastní spotřeba baru vyžaduje zvláštní povolení.
    Deaktivovaný účet se nemůže přihlásit, přidělené role zůstávají evidované.
    Soukromé profily svěřenců se řídí také trenérským rozsahem.</p>
<div class="d-md-none mb-4" id="access-cards" aria-describedby="access-legend">
    <?php foreach ($visibleTrainers as $account): ?>
        <article class="card shadow-sm mb-3 <?= (int)$account['id'] === $selectedId ? 'border-primary' : '' ?>"
            aria-labelledby="access-person-<?= (int)$account['id'] ?>">
            <div class="card-body">
                <h2 class="h5 text-break mb-1" id="access-person-<?= (int)$account['id'] ?>"><?= staffAdminH($account['jmeno']) ?></h2>
                <div class="small text-muted text-break mb-2"><?= staffAdminH($account['email']) ?></div>
                <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                    <span class="badge <?= (int)$account['aktivni'] === 1 ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= (int)$account['aktivni'] === 1 ? 'Aktivní' : 'Deaktivovaný' ?></span>
                    <span class="small text-muted">#<?= (int)$account['id'] ?></span>
                    <?php if ($account['superadmin']): ?><span class="badge text-bg-warning">Superadmin</span><?php endif; ?>
                </div>
                <a class="btn btn-outline-primary w-100 mb-3"
                    href="?<?= staffAdminH(http_build_query([
                        'q' => $search, 'access' => $roleFilter, 'status' => $statusFilter, 'trainer_id' => (int)$account['id'],
                    ])) ?>#uprava-pristupu"
                    aria-label="Upravit přístupy: <?= staffAdminH($account['jmeno']) ?>">Upravit přístupy</a>
                <dl class="mb-0">
                    <?php foreach ($accessGroups as $group): ?>
                        <dt class="small text-muted mb-1"><?= staffAdminH($group['label']) ?></dt>
                        <dd class="mb-3">
                            <?php $hasRole = false;
                        foreach ($group['codes'] as $code): ?>
                                <?php
                            if (!in_array($code, $account['positions'], true) && !$account['superadmin']) {
                                continue;
                            }
                            $hasRole = true;
                            ?>
                                <div class="mb-1 text-break">
                                    <?= staffAdminH($definitions[$code]['label']) ?>
                                    <?php if ($account['default_position'] === $code): ?><span class="small text-primary">★ výchozí</span><?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            <?php if (!$hasRole): ?><span class="small text-muted">Žádná role</span><?php endif; ?>
                        </dd>
                    <?php endforeach; ?>
                    <dt class="small text-muted mb-1">Zvláštní oprávnění</dt>
                    <dd class="mb-0">
                        <?php if ($account['own_consumption']): ?><span class="badge text-bg-info text-wrap">Vlastní spotřeba baru povolena</span>
                        <?php else: ?><span class="small text-muted">Vlastní spotřeba nepovolena</span><?php endif; ?>
                    </dd>
                </dl>
            </div>
        </article>
    <?php endforeach; ?>
    <?php if ($visibleTrainers === []): ?><div class="card card-body text-center text-muted">Žádný pracovní účet neodpovídá filtru.</div><?php endif; ?>
</div>
<div class="card border-0 shadow-sm mb-4 d-none d-md-block"><div class="table-responsive">
<table class="table table-hover align-middle mb-0" aria-describedby="access-legend">
    <caption class="visually-hidden">Pracovní účty a dostupné pracovní role</caption>
    <thead class="table-light"><tr><th scope="col">Člověk / stav</th>
        <?php foreach ($accessGroups as $group): ?><th scope="col"><?= staffAdminH($group['label']) ?></th><?php endforeach; ?>
        <th scope="col">Zvláštní oprávnění</th><th scope="col">Úprava</th></tr></thead>
    <tbody>
    <?php foreach ($visibleTrainers as $account): ?>
        <tr <?= (int)$account['id'] === $selectedId ? 'class="table-primary"' : '' ?>>
            <th scope="row"><div><?= staffAdminH($account['jmeno']) ?></div>
                <div class="small fw-normal text-muted text-break"><?= staffAdminH($account['email']) ?></div>
                <span class="badge <?= (int)$account['aktivni'] === 1 ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= (int)$account['aktivni'] === 1 ? 'Aktivní' : 'Deaktivovaný' ?></span>
                <span class="small fw-normal text-muted">#<?= (int)$account['id'] ?></span></th>
            <?php foreach ($accessGroups as $group): ?>
                <td><?php $hasRole = false;
                foreach ($group['codes'] as $code): ?>
                    <?php
                    if (!in_array($code, $account['positions'], true) && !$account['superadmin']) {
                        continue;
                    }
                    $hasRole = true;
                    ?>
                    <span class="badge text-wrap text-start mb-1 <?= $account['superadmin'] ? 'text-bg-warning' : 'text-bg-light border' ?>"
                        title="<?= staffAdminH($definitions[$code]['description']) ?>">
                        <?= staffAdminH($definitions[$code]['label']) ?><?= $account['default_position'] === $code ? ' ★' : '' ?></span>
                <?php endforeach; ?><?php if (!$hasRole): ?><span class="text-muted" aria-label="Žádná role">—</span><?php endif; ?></td>
            <?php endforeach; ?>
            <td><?php if ($account['superadmin']): ?><span class="badge text-bg-warning mb-1">Superadmin</span><?php endif; ?>
                <?php if ($account['own_consumption']): ?><span class="badge text-bg-info text-wrap">Vlastní spotřeba baru povolena</span>
                <?php else: ?><span class="small text-muted d-block">Vlastní spotřeba nepovolena</span><?php endif; ?></td>
            <td><a class="btn btn-sm btn-outline-primary"
                href="?<?= staffAdminH(http_build_query([
                    'q' => $search, 'access' => $roleFilter, 'status' => $statusFilter, 'trainer_id' => (int)$account['id'],
                ])) ?>#uprava-pristupu"
                aria-label="Upravit přístupy: <?= staffAdminH($account['jmeno']) ?>">Upravit přístupy</a></td>
        </tr>
    <?php endforeach; ?>
    <?php if ($visibleTrainers === []): ?><tr><td colspan="7" class="text-center text-muted py-4">Žádný pracovní účet neodpovídá filtru.</td></tr><?php endif; ?>
    </tbody>
</table></div></div>
