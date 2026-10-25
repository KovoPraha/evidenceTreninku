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
            <a class="btn btn-outline-secondary btn-sm" href="#parents-title">Rodiče a účty</a>
            <a class="btn btn-outline-secondary btn-sm" href="#rosters-title">Soupisky a zařazení</a>
            <a class="btn btn-outline-secondary btn-sm" href="#payments-title">Všechny platby</a>
            <a class="btn btn-outline-secondary btn-sm" href="#birth-number-title">Upravit RČ</a>
            <?php if ($editError !== '' && ($editorLastAction ?? '') === 'save'): ?>
                <?php // A distinct GET URL must reload the record, not only scroll the current POST response. ?>
                <a class="btn btn-outline-primary btn-sm" href="<?= h(spUrl(['edit_id' => $editId, 'reload' => bin2hex(random_bytes(8))])) ?>#edit-card">Načíst aktuální údaje</a>
            <?php endif; ?>
        </div>
        <?php require __DIR__.'/person_workflow_view.php'; ?>
        <form method="post" action="<?= h(spUrl(['edit_id' => $editId])) ?>#edit-card" id="sportovec-form">
            <?= csrf_field() ?>
            <input type="hidden" name="akce" value="save">
            <input type="hidden" name="id" id="sp-id" value="<?= (int)$editId ?>">
            <input type="hidden" name="profile_revision" value="<?= h($editInput['profile_revision'] ?? sportovecEditorRevision($editPerson, $editContext['profile'])) ?>">
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

        <section class="mt-4" aria-labelledby="birth-number-title">
            <h2 class="h5 border-bottom pb-2" id="birth-number-title">Upravit rodné číslo</h2>
            <p class="small text-muted"><?= $editContext['sensitive'] ? 'Aktuální RČ je uložené v chráněné evidenci osoby.' : 'RČ můžete nově uložit do chráněné evidence osoby.' ?> Pro načtení uloženého RČ i pro změnu vyplňte důvod. Prázdné datum narození se doplní z nového RČ.</p>
            <?php if ($editError !== '' && in_array($editorLastAction ?? '', ['load_birth_number','save_birth_number'], true)): ?><div class="alert alert-danger" role="alert"><?= h($editError) ?></div><?php endif; ?>
            <form method="post" action="<?= h(spUrl(['edit_id' => $editId])) ?>#birth-number-title" class="border rounded p-3">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$editId ?>">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label" for="sp-birth-number">Rodné číslo</label><input class="form-control" type="text" id="sp-birth-number" name="birth_number" value="<?= h($birthEditValue ?? '') ?>" maxlength="11" autocomplete="off" required></div>
                    <div class="col-md-6"><label class="form-label" for="sp-birth-date">Datum narození k rodnému číslu</label><input class="form-control" type="date" id="sp-birth-date" name="birth_date" value="<?= h(in_array($editorLastAction ?? '', ['load_birth_number','save_birth_number'], true) && is_string($_POST['birth_date'] ?? null) ? $_POST['birth_date'] : ($editPerson['narozeni'] ?? '')) ?>"></div>
                    <div class="col-12"><label class="form-label" for="sp-birth-reason">Důvod načtení, doplnění nebo opravy</label><input class="form-control" id="sp-birth-reason" name="reason" value="<?= h(in_array($editorLastAction ?? '', ['load_birth_number','save_birth_number'], true) && is_string($_POST['reason'] ?? null) ? $_POST['reason'] : '') ?>" minlength="10" maxlength="1000" required></div>
                </div>
                <button class="btn btn-primary mt-3" type="submit" name="akce" value="save_birth_number">Uložit rodné číslo</button>
                <button class="btn btn-outline-primary mt-3" type="submit" name="akce" value="load_birth_number" formnovalidate>Načíst uložené rodné číslo</button>
            </form>
        </section>
        <?php require __DIR__ . '/sportovec_editor_parents_view.php'; ?>
        <?php require __DIR__ . '/sportovec_editor_rosters_view.php'; ?>
        <?php require __DIR__ . '/sportovec_editor_payments_view.php'; ?>

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
