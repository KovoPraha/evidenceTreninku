<section class="mt-4" aria-labelledby="parents-title">
    <h2 class="h5 border-bottom pb-2" id="parents-title">Rodiče a zákonní zástupci</h2>
    <?php if ($editError !== '' && in_array($editorLastAction ?? '', ['save_parent','unlink_parent'], true)): ?><div class="alert alert-danger" role="alert"><?= h($editError) ?></div><?php endif; ?>
    <?php if (!$editContext['parents']): ?><p class="text-muted">Ke sportovci není připojený žádný rodičovský kontakt.</p><?php endif; ?>
    <div class="row g-3">
    <?php foreach ($editContext['parents'] as $parent): $parentFieldId = 'parent-' . substr(hash('sha256', $parent['source_key']), 0, 12); ?>
        <div class="col-lg-6"><div class="border rounded p-3 h-100">
            <form method="post" action="<?= h(spUrl(['edit_id' => $editId])) ?>#parents-title">
                <?= csrf_field() ?><input type="hidden" name="akce" value="save_parent"><input type="hidden" name="id" value="<?= (int)$editId ?>"><input type="hidden" name="link_key" value="<?= h($parent['source_key']) ?>">
                <label class="form-label" for="<?= h($parentFieldId) ?>-name">Jméno a příjmení rodiče</label>
                <input class="form-control mb-2" id="<?= h($parentFieldId) ?>-name" name="parent_name" value="<?= h($parent['display_name']) ?>" maxlength="255" required>
                <label class="form-label" for="<?= h($parentFieldId) ?>-relation">Vztah ke sportovci</label>
                <input class="form-control mb-2" id="<?= h($parentFieldId) ?>-relation" name="relationship" value="<?= h($parent['relationship_label']) ?>" maxlength="255" placeholder="Např. matka, otec, zákonný zástupce">
                <label class="form-label" for="<?= h($parentFieldId) ?>-emails">E-mail / e-maily rodiče</label>
                <input class="form-control mb-2" id="<?= h($parentFieldId) ?>-emails" name="parent_emails" value="<?= h(sportovecEditorContactValues($parent['emails_json'])) ?>" maxlength="2000">
                <label class="form-label" for="<?= h($parentFieldId) ?>-phones">Telefon / telefony rodiče</label>
                <input class="form-control mb-2" id="<?= h($parentFieldId) ?>-phones" name="parent_phones" value="<?= h(sportovecEditorContactValues($parent['phones_json'])) ?>" maxlength="2000">
                <?php if ((int)$parent['shared_people'] > 1): ?><p class="small text-warning-emphasis">Společný kontakt pro <?= (int)$parent['shared_people'] ?> sportovce. Úprava jména, e-mailu nebo telefonu se projeví u všech jeho vazeb.</p><?php endif; ?>
                <button class="btn btn-outline-primary" type="submit">Uložit údaje rodiče</button>
            </form>
            <div class="small mt-3">
                <?php if ($parent['account_id']): ?>
                    Účet #<?= (int)$parent['account_id'] ?> · <?= $parent['account_active'] ? 'aktivní' : 'neaktivní' ?> · <?= $parent['email_overeno'] ? 'e-mail ověřen' : 'e-mail neověřen' ?><br>
                    Přihlašovací e-mail účtu: <?= h($parent['login_email']) ?><br>
                    Vazba účtu: <?= h(sportovecEditorStatusLabel($parent['relation_status'] ?? 'contact_only')) ?>
                    <p class="text-muted mt-1 mb-2">Úprava kontaktu rodiče nemění přihlašovací e-mail. Přístup k portálu spravujete v části Napojené uživatelské účty.</p>
                <?php else: ?>Bez uživatelského účtu.<?php endif; ?>
            </div>
            <form method="post" action="<?= h(spUrl(['edit_id' => $editId])) ?>#parents-title" class="mt-2">
                <?= csrf_field() ?><input type="hidden" name="akce" value="unlink_parent"><input type="hidden" name="id" value="<?= (int)$editId ?>"><input type="hidden" name="link_key" value="<?= h($parent['source_key']) ?>">
                <button class="btn btn-outline-danger btn-sm" type="submit">Odpojit rodiče od tohoto sportovce</button>
                <div class="small text-muted mt-1">Ukončí jeho kontakt a související rodičovský přístup k tomuto sportovci. Vazby k ostatním dětem zůstávají zachované.</div>
            </form>
        </div></div>
    <?php endforeach; ?>
    </div>
    <details class="border rounded p-3 mt-3"><summary class="fw-semibold">Přidat nebo připojit rodiče</summary>
        <form method="post" action="<?= h(spUrl(['edit_id' => $editId])) ?>#parents-title" class="mt-3">
            <?= csrf_field() ?><input type="hidden" name="akce" value="save_parent"><input type="hidden" name="id" value="<?= (int)$editId ?>">
            <label class="form-label" for="existing-parent">Vybrat existující kontakt rodiče</label>
            <select class="form-select mb-3" id="existing-parent" name="existing_contact_key"><option value="">Nový rodič – vyplnit údaje níže</option>
                <?php foreach ($editContext['contact_choices'] as $contact): ?><option value="<?= h($contact['contact_key']) ?>"><?= h($contact['display_name'] . ' · ' . sportovecEditorContactValues($contact['emails_json'])) ?></option><?php endforeach; ?>
            </select>
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label" for="new-parent-name">Jméno a příjmení nového rodiče</label><input class="form-control" id="new-parent-name" name="parent_name" maxlength="255"></div>
                <div class="col-md-6"><label class="form-label" for="new-parent-relation">Vztah ke sportovci</label><input class="form-control" id="new-parent-relation" name="relationship" maxlength="255"></div>
                <div class="col-md-6"><label class="form-label" for="new-parent-email">E-mail / e-maily nového rodiče</label><input class="form-control" id="new-parent-email" name="parent_emails" maxlength="2000"></div>
                <div class="col-md-6"><label class="form-label" for="new-parent-phone">Telefon / telefony nového rodiče</label><input class="form-control" id="new-parent-phone" name="parent_phones" maxlength="2000"></div>
            </div>
            <p class="small text-muted mt-2">Při výběru existujícího rodiče se použijí jeho uložené kontakty. Připojení kontaktu samo o sobě neschvaluje přístup uživatelského účtu.</p>
            <button class="btn btn-primary" type="submit">Připojit rodiče</button>
        </form>
    </details>
</section>

<section class="mt-4" aria-labelledby="accounts-title">
    <h2 class="h5 border-bottom pb-2" id="accounts-title">Napojené uživatelské účty</h2>
    <?php if ($editError !== '' && in_array($editorLastAction ?? '', ['attach_account','revoke_account'], true)): ?><div class="alert alert-danger" role="alert"><?= h($editError) ?></div><?php endif; ?>
    <?php if (!$editContext['roles']): ?><p class="text-muted">Na sportovce není napojený žádný uživatelský účet.</p><?php else: ?>
    <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Účet a přihlašovací e-mail</th><th>Vztah</th><th>Stav vazby</th><th>Stav účtu</th><th>Úprava přístupu</th></tr></thead><tbody>
    <?php foreach ($editContext['roles'] as $relation): ?><tr>
        <td><?= h(trim($relation['prijmeni'] . ' ' . $relation['jmeno'])) ?> · #<?= (int)$relation['account_id'] ?><br><?= h($relation['email']) ?></td>
        <td><?= $relation['relation_role'] === 'guardian' ? 'Rodič / zákonný zástupce' : 'Vlastní účet sportovce' ?></td>
        <td><?= h($relation['valid_to'] && $relation['status'] === 'approved' ? 'Ukončená' : sportovecEditorStatusLabel($relation['status'])) ?></td>
        <td><?= $relation['aktivni'] ? 'Aktivní' : 'Neaktivní' ?> · <?= $relation['email_overeno'] ? 'e-mail ověřen' : 'e-mail neověřen' ?></td>
        <td><?php if ($relation['relation_role'] === 'guardian' && $relation['status'] !== 'revoked'): ?>
            <form method="post" action="<?= h(spUrl(['edit_id' => $editId])) ?>#accounts-title">
                <?= csrf_field() ?><input type="hidden" name="akce" value="revoke_account"><input type="hidden" name="id" value="<?= (int)$editId ?>"><input type="hidden" name="relation_id" value="<?= (int)$relation['id'] ?>">
                <button class="btn btn-outline-danger btn-sm" type="submit">Odpojit přístup rodiče</button>
            </form>
        <?php endif; ?></td>
    </tr><?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
    <details class="border rounded p-3"><summary class="fw-semibold">Připojit účet rodiče nebo schválit čekající vazbu</summary>
        <form method="post" action="<?= h(spUrl(['edit_id' => $editId])) ?>#accounts-title" class="mt-3">
            <?= csrf_field() ?><input type="hidden" name="akce" value="attach_account"><input type="hidden" name="id" value="<?= (int)$editId ?>">
            <label class="form-label" for="parent-account">Účet rodiče</label><select class="form-select mb-2" id="parent-account" name="account_id" required><option value="">Vyberte účet</option>
                <?php foreach ($editContext['account_choices'] as $account): ?><option value="<?= (int)$account['id'] ?>"><?= h(trim($account['prijmeni'] . ' ' . $account['jmeno']) . ' · ' . $account['email']) ?><?= !$account['aktivni'] ? ' · neaktivní' : '' ?></option><?php endforeach; ?>
            </select>
            <label class="form-label" for="parent-account-contact">Spojit také s kontaktem rodiče</label><select class="form-select mb-2" id="parent-account-contact" name="contact_key"><option value="">Samostatná vazba účtu bez spojení s kontaktem</option>
                <?php foreach ($editContext['parents'] as $parent): ?><option value="<?= h($parent['contact_key']) ?>"><?= h($parent['display_name']) ?></option><?php endforeach; ?>
            </select>
            <label class="form-label" for="parent-account-reason">Důvod propojení a schválení přístupu</label><input class="form-control mb-2" id="parent-account-reason" name="reason" maxlength="1000" required>
            <p class="small text-muted">Schválená rodičovská vazba umožní aktivnímu účtu s ověřeným e-mailem spravovat tohoto sportovce v portálu.</p>
            <button class="btn btn-primary" type="submit">Připojit účet rodiče a schválit přístup</button>
        </form>
    </details>
</section>
