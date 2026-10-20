<?php /** @var ?array $editPerson */ ?>
<section class="card section-card mb-4" id="edit-card" aria-labelledby="form-title">
    <div class="card-header bg-warning text-dark">
        <i class="bi bi-pencil-square me-1"></i><span id="form-title"><?= $editPerson ? 'Upravit: ' . h($editPerson['prijmeni'] . ' ' . $editPerson['jmeno']) . ' · #' . (int)$editId : 'Vyberte sportovce k úpravě' ?></span>
    </div>
    <div class="card-body">
        <?php if ($editError !== ''): ?><div class="alert alert-danger" role="alert"><?= h($editError) ?></div><?php endif; ?>
        <?php if (!$editPerson): ?>
            <p class="text-muted mb-0">Klikněte na tužku v řádku sportovce. Pro jednotlivou úpravu není potřeba zaškrtávat člena.</p>
        <?php else: $editValues = array_replace($editPerson, $editContext['profile'], $editInput); ?>
        <div class="d-flex flex-wrap gap-2 mb-3">
            <?= sportovecStatusBadge($editPerson) ?>
            <a class="btn btn-outline-secondary btn-sm" href="sportovec_karta.php?sportovec_id=<?= (int)$editId ?>">Karta člena, skupiny a historie</a>
            <a class="btn btn-outline-secondary btn-sm" href="eshop_identity_admin.php">Správa vazeb na rodiče a účty</a>
        </div>
        <form method="post" action="<?= h(spUrl(['edit_id' => $editId])) ?>#edit-card" id="sportovec-form">
            <?= csrf_field() ?>
            <input type="hidden" name="akce" value="save">
            <input type="hidden" name="id" id="sp-id" value="<?= (int)$editId ?>">
            <?php foreach ([
                'Osobní a sportovní údaje' => ['prijmeni','jmeno','narozeni','category','uciid','oddil'],
                'Kontakt sportovce' => ['email','telefon'],
                'Trvalá adresa' => ['adresa_ulice','adresa_cp','adresa_co','adresa_obec','adresa_psc'],
            ] as $section => $fields): ?>
            <fieldset class="mb-4">
                <legend class="fs-6 fw-semibold border-bottom pb-2"><?= h($section) ?></legend>
                <div class="row g-3">
                    <?php foreach ($fields as $field): [$label, $type, $limit] = sportovecEditorFields()[$field]; ?>
                    <div class="<?= $field === 'email' ? 'col-md-8' : 'col-md-4' ?>">
                        <label for="sp-<?= h($field) ?>" class="form-label"><?= h($label) ?></label>
                        <input type="<?= h($type) ?>" name="<?= h($field) ?>" id="sp-<?= h($field) ?>" class="form-control"
                               value="<?= h($editValues[$field] ?? '') ?>" maxlength="<?= $limit ?>"
                               <?= in_array($field, ['jmeno','prijmeni'], true) ? 'required' : '' ?>>
                    </div>
                    <?php endforeach; ?>
                </div>
            </fieldset>
            <?php endforeach; ?>
            <?php if ($editContext['profile']): ?>
            <fieldset class="mb-4"><legend class="fs-6 fw-semibold border-bottom pb-2">Další kontaktní údaje</legend>
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label" for="sp-country">Země</label><input class="form-control" id="sp-country" name="country" maxlength="100" value="<?= h($editValues['country'] ?? '') ?>"></div>
                    <div class="col-md-8"><label class="form-label" for="sp-contact_address">Kontaktní adresa</label><textarea class="form-control" id="sp-contact_address" name="contact_address" maxlength="2000" rows="3"><?= h($editValues['contact_address'] ?? '') ?></textarea></div>
                </div>
            </fieldset>
            <?php endif; ?>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary" id="btn-save"><i class="bi bi-check-lg me-1"></i>Uložit změny</button>
                <a class="btn btn-outline-secondary" href="<?= h(spUrl(['edit_id' => null])) ?>">Zavřít editaci</a>
            </div>
        </form>

        <section class="mt-4" aria-labelledby="parents-title">
            <h2 class="h6 border-bottom pb-2" id="parents-title">Rodiče a zákonní zástupci ze starého KIS</h2>
            <?php if (!$editContext['parents']): ?><p class="text-muted">Ve zdrojovém KIS nejsou u tohoto člena uložené vazby na rodiče.</p><?php endif; ?>
            <div class="row g-3">
            <?php foreach ($editContext['parents'] as $parent): ?>
                <div class="col-lg-6"><div class="border rounded p-3 h-100">
                    <strong><?= h(sportovecEditorContactValues($parent['names_json']) ?: $parent['display_name']) ?></strong>
                    <div><?= h($parent['relationship_label'] ?: 'Zákonný zástupce') ?> · kontakt <?= (int)$parent['source_slot'] ?></div>
                    <dl class="row small mb-0 mt-2">
                        <dt class="col-sm-3">E-mail</dt><dd class="col-sm-9 text-break"><?= h(sportovecEditorContactValues($parent['emails_json']) ?: 'Neuveden') ?></dd>
                        <dt class="col-sm-3">Telefon</dt><dd class="col-sm-9"><?= h(sportovecEditorContactValues($parent['phones_json']) ?: 'Neuveden') ?></dd>
                        <dt class="col-sm-3">Účet</dt><dd class="col-sm-9"><?= $parent['account_id'] ? '#' . (int)$parent['account_id'] . ' · ' . ($parent['account_active'] ? 'aktivní' : 'neaktivní') . ' · ' . ($parent['email_overeno'] ? 'e-mail ověřen' : 'e-mail neověřen') : 'Bez uživatelského účtu' ?></dd>
                        <dt class="col-sm-3">Vazba</dt><dd class="col-sm-9"><?= h(['approved' => $parent['valid_to'] ? 'Ukončená' : 'Schválená', 'pending' => 'Čeká na ověření', 'revoked' => 'Zrušená'][$parent['relation_status'] ?? ''] ?? 'Pouze kontakt v původní evidenci') ?></dd>
                    </dl>
                    <div class="small text-muted">Zdroj: <?= h($parent['source_key']) ?></div>
                </div></div>
            <?php endforeach; ?>
            </div>
        </section>

        <section class="mt-4" aria-labelledby="accounts-title">
            <h2 class="h6 border-bottom pb-2" id="accounts-title">Napojené uživatelské účty</h2>
            <?php if (!$editContext['roles']): ?><p class="text-muted">Na sportovce není napojený žádný uživatelský účet.</p><?php else: ?>
            <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Účet a kontakt</th><th>Vztah</th><th>Stav vazby</th><th>Stav účtu</th></tr></thead><tbody>
            <?php foreach ($editContext['roles'] as $relation): ?><tr>
                <td><?= h(trim($relation['prijmeni'] . ' ' . $relation['jmeno'])) ?> · #<?= (int)$relation['account_id'] ?><br><?= h($relation['email']) ?></td>
                <td><?= $relation['relation_role'] === 'guardian' ? 'Rodič / zákonný zástupce' : 'Vlastní účet sportovce' ?></td>
                <td><?= h(['approved' => $relation['valid_to'] ? 'Ukončená' : 'Schválená', 'pending' => 'Čeká na ověření', 'revoked' => 'Zrušená'][$relation['status']] ?? $relation['status']) ?></td>
                <td><?= $relation['aktivni'] ? 'Aktivní' : 'Neaktivní' ?> · <?= $relation['email_overeno'] ? 'e-mail ověřen' : 'e-mail neověřen' ?></td>
            </tr><?php endforeach; ?></tbody></table></div>
            <?php endif; ?>
        </section>

        <section class="mt-4" aria-labelledby="source-title">
            <h2 class="h6 border-bottom pb-2" id="source-title">Rodné číslo a úplná původní evidence</h2>
            <p class="small text-muted">Zde zobrazíte RČ z registrace a všechny původní údaje člena ze starého KIS. Původní import je zachovaný pro kontrolu; aktuální osobní a kontaktní údaje se upravují výše.</p>
            <?php if ($revealed !== null): ?>
                <?php foreach ($revealed['birth_numbers'] as $number): ?><div class="alert alert-info">Rodné číslo: <strong><?= h($number) ?></strong></div><?php endforeach; ?>
                <?php foreach ($revealed['source_rows'] as $index => $sourceRow): ?>
                    <h3 class="h6 mt-3">Původní záznam <?= $index + 1 ?></h3>
                    <div class="table-responsive"><table class="table table-sm table-striped"><tbody>
                    <?php foreach ($sourceRow as $key => $value): ?>
                        <tr><th scope="row" style="width:35%" class="text-break"><?= h(sportovecEditorSourceLabel((string)$key)) ?></th><td class="text-break" style="white-space:pre-wrap"><?= h(is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ($value === null || $value === '' ? '—' : (string)$value)) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody></table></div>
                <?php endforeach; ?>
                <?php if (!$revealed['birth_numbers'] && !$revealed['source_rows']): ?><p class="text-muted">U člena není uložené RČ ani původní importovaný záznam.</p><?php endif; ?>
            <?php elseif ($editContext['sensitive'] || $editContext['profile'] || !empty($editPerson['rc'])): ?>
                <form method="post" action="<?= h(spUrl(['edit_id' => $editId])) ?>#source-title" class="row g-2 align-items-end">
                    <?= csrf_field() ?><input type="hidden" name="akce" value="reveal_person"><input type="hidden" name="id" value="<?= (int)$editId ?>">
                    <div class="col-md-7"><label for="reveal-reason" class="form-label">Důvod přístupu k úplným údajům</label><input class="form-control" id="reveal-reason" name="reason" required minlength="10" placeholder="Např. kontrola údajů člena v evidenci"></div>
                    <div class="col-md-5"><button type="submit" class="btn btn-outline-primary">Zobrazit úplné údaje včetně RČ</button></div>
                </form>
            <?php else: ?><p class="text-muted">U člena není uložené chráněné RČ ani původní importovaný záznam.</p><?php endif; ?>
        </section>
        <?php endif; ?>
    </div>
</section>
