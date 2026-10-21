<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/includes/athlete_registration_texts.php';
if (!isset($_SESSION['trener_id']) || !staffActivePositionIs('registrar')) {
    http_response_code(403);
    exit('Správa textů je dostupná v pracovní pozici Registrář členů a KIS.');
}
header('Cache-Control: no-store, private');
function registrationTextH(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
$purpose = (string)($_POST['purpose'] ?? $_GET['purpose'] ?? 'member_data_notice');
if (!isset(ATHLETE_REGISTRATION_TEXT_PURPOSES[$purpose])) {
    http_response_code(400);
    exit('Neznámý typ registračního textu.');
}
$error = '';
$postedText = null;
$postedTitle = null;
$postedReason = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedText = isset($_POST['text']) && is_string($_POST['text']) ? $_POST['text'] : null;
    $postedTitle = is_string($_POST['title'] ?? null) ? $_POST['title'] : '';
    $postedReason = is_string($_POST['reason'] ?? null) ? $_POST['reason'] : '';
    try {
        if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) throw new InvalidArgumentException('Formulář vypršel. Obnovte stránku.');
        if (($_POST['action'] ?? '') === 'save_draft') {
            $id = registrationTextsSaveDraft($pdo, $purpose, $postedText ?? '', $postedReason, (int)($_POST['base_term_id'] ?? 0), $postedTitle);
            $_SESSION['registration_text_flash'] = 'Koncept byl uložen. Veřejná přihláška stále používá dosavadní znění. Zkontrolujte náhled a případně koncept zveřejněte.';
        } elseif (($_POST['action'] ?? '') === 'publish') {
            $id = (int)($_POST['draft_id'] ?? 0);
            $check = $pdo->prepare('SELECT purpose FROM athlete_registration_text_drafts WHERE id=?');
            $check->execute([$id]);
            if ($check->fetchColumn() !== $purpose) throw new InvalidArgumentException('Koncept nepatří k vybranému textu.');
            registrationTextsPublish($pdo, $id, ($_POST['confirmed'] ?? '') === '1');
            $_SESSION['registration_text_flash'] = 'Nové znění je zveřejněno pro nové přihlášky. Dřívější potvrzení zůstávají beze změny.';
        } else throw new InvalidArgumentException('Neznámá operace.');
        header('Location: registration_texts_admin.php?purpose='.rawurlencode($purpose).'&draft_id='.$id, true, 303);
        exit;
    } catch (InvalidArgumentException $exception) {
        $error = $exception->getMessage();
    } catch (Throwable $exception) {
        error_log('Registration text administration failed: '.get_class($exception));
        $error = 'Operaci se nepodařilo dokončit. Obnovte stránku a ověřte stav. Rozepsaný text je zachován níže.';
    }
}
$current = registrationTextsCurrent($pdo, $purpose);
$draftQuery = $pdo->prepare('SELECT d.*,c.jmeno AS creator_name,p.jmeno AS publisher_name FROM athlete_registration_text_drafts d JOIN treneri c ON c.id=d.created_by LEFT JOIN treneri p ON p.id=d.published_by WHERE d.purpose=? ORDER BY d.id DESC');
$draftQuery->execute([$purpose]);
$drafts = $draftQuery->fetchAll(PDO::FETCH_ASSOC);
$selectedDraft = null;
$selectedId = (int)($_POST['draft_id'] ?? $_GET['draft_id'] ?? 0);
foreach ($drafts as $draft) if ((int)$draft['id'] === $selectedId) $selectedDraft = $draft;
$history = registrationTextsHistory($pdo, $purpose);
$names = $pdo->query('SELECT id,jmeno FROM treneri')->fetchAll(PDO::FETCH_KEY_PAIR);
$flash = (string)($_SESSION['registration_text_flash'] ?? '');
unset($_SESSION['registration_text_flash']);
$editorText = $postedText ?? $selectedDraft['text_plain'] ?? $current['consent_text_plain'];
$editorTitle = $postedTitle ?? registrationTextTitle($selectedDraft ?? $current, $purpose);
$editorReason = $postedText !== null ? $postedReason : ($selectedDraft['reason'] ?? '');
$currentTitles = [];
foreach (array_keys(ATHLETE_REGISTRATION_TEXT_PURPOSES) as $key) $currentTitles[$key] = registrationTextTitle(registrationTextsCurrent($pdo, $key), $key);
?>
<!doctype html>
<html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Registrační texty a souhlasy</title><?php appUiAssets(); ?></head>
<body><?php include __DIR__.'/hlavicka.php'; ?>
<main class="container py-4">
    <h1 class="h3">Registrační texty a souhlasy</h1>
    <p class="text-body-secondary">Upravte název i text, připravte koncept a samostatně ho zveřejněte. Nová verze platí pro nové přihlášky; historie dřívějších potvrzení se nepřepisuje.</p>
    <?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?=registrationTextH($error)?></div><?php endif; ?>
    <?php if ($flash !== ''): ?><div class="alert alert-success" role="status"><?=registrationTextH($flash)?></div><?php endif; ?>
    <nav class="nav nav-pills gap-2 mb-4" aria-label="Typ registračního textu">
        <?php foreach ($currentTitles as $key => $label): ?><a class="nav-link text-break <?=$purpose === $key ? 'active' : ''?>" <?=$purpose === $key ? 'aria-current="page"' : ''?> href="?purpose=<?=registrationTextH($key)?>"><?=registrationTextH($label)?></a><?php endforeach; ?>
    </nav>
    <section class="card mb-4"><div class="card-header"><h2 class="h5 mb-0">Aktuálně zveřejněné znění</h2></div><div class="card-body">
        <p class="small text-body-secondary">Verze <?=registrationTextH($current['terms_version'])?> · <?=registrationTextH($current['created_at'])?></p>
        <h3 class="h5 text-break"><?=registrationTextH(registrationTextTitle($current, $purpose))?></h3>
        <div class="text-break"><?=nl2br(registrationTextH($current['consent_text_plain']))?></div>
    </div></section>
    <?php if ($selectedDraft): $isPublished = $selectedDraft['published_term_id'] !== null; $stale = (int)$selectedDraft['base_term_id'] !== (int)$current['id']; ?>
    <section class="card mb-4 border-primary" id="draft-preview"><div class="card-header"><h2 class="h5 mb-0">Náhled konceptu č. <?=(int)$selectedDraft['id']?></h2></div><div class="card-body">
        <p class="small">Připravil/a <?=registrationTextH($selectedDraft['creator_name'])?> · <?=registrationTextH($selectedDraft['created_at'])?></p>
        <p><strong>Důvod změny:</strong> <?=registrationTextH($selectedDraft['reason'])?></p>
        <h3 class="h5 text-break"><?=registrationTextH(registrationTextTitle($selectedDraft, $purpose))?></h3>
        <div class="text-break mb-3"><?=nl2br(registrationTextH($selectedDraft['text_plain']))?></div>
        <?php if ($isPublished): ?><div class="alert alert-success mb-0">Zveřejněno <?=registrationTextH($selectedDraft['published_at'])?> uživatelem <?=registrationTextH($selectedDraft['publisher_name'])?>. Znění je součástí historie verzí.</div>
        <?php elseif ($stale): ?><div class="alert alert-warning mb-0">Mezitím se změnilo zveřejněné znění. Porovnejte ho s konceptem a formulářem níže uložte nový koncept.</div>
        <?php else: ?><form method="post">
            <?=csrf_field()?><input type="hidden" name="action" value="publish"><input type="hidden" name="purpose" value="<?=registrationTextH($purpose)?>"><input type="hidden" name="draft_id" value="<?=(int)$selectedDraft['id']?>">
            <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="publish-confirmed" name="confirmed" value="1" required><label class="form-check-label" for="publish-confirmed">Potvrzuji, že klub schválil název i přesné znění tohoto konceptu a chci ho nyní zveřejnit pro nové přihlášky.</label></div>
            <button class="btn btn-success" type="submit">Zveřejnit toto znění</button>
        </form><?php endif; ?>
    </div></section><?php endif; ?>
    <section class="card mb-4"><div class="card-header"><h2 class="h5 mb-0">Připravit nový koncept</h2></div><div class="card-body"><form method="post">
        <?=csrf_field()?><input type="hidden" name="action" value="save_draft"><input type="hidden" name="purpose" value="<?=registrationTextH($purpose)?>"><input type="hidden" name="base_term_id" value="<?=(int)$current['id']?>">
        <label class="form-label" for="registration-title">Název souhlasu / informace</label>
        <input class="form-control mb-3" type="text" name="title" id="registration-title" maxlength="200" value="<?=registrationTextH($editorTitle)?>" required>
        <label class="form-label" for="registration-text">Znění textu</label>
        <textarea class="form-control mb-3" name="text" id="registration-text" rows="12" maxlength="12000" required><?=registrationTextH($editorText)?></textarea>
        <label class="form-label" for="change-reason">Důvod změny</label><textarea class="form-control mb-3" name="reason" id="change-reason" rows="2" maxlength="1000" required><?=registrationTextH($editorReason)?></textarea>
        <button class="btn btn-primary" type="submit">Uložit koncept a zobrazit náhled</button>
    </form></div></section>
    <section class="card mb-4"><div class="card-header"><h2 class="h5 mb-0">Uložené koncepty</h2></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Koncept</th><th>Vytvořen</th><th>Autor</th><th>Důvod změny</th><th>Stav</th></tr></thead><tbody>
        <?php foreach ($drafts as $draft): ?><tr><td><a href="?purpose=<?=registrationTextH($purpose)?>&amp;draft_id=<?=(int)$draft['id']?>#draft-preview">Koncept č. <?=(int)$draft['id']?></a></td><td><?=registrationTextH($draft['created_at'])?></td><td><?=registrationTextH($draft['creator_name'])?></td><td><?=registrationTextH($draft['reason'])?></td><td><?=$draft['published_term_id'] !== null ? 'Zveřejněný' : ((int)$draft['base_term_id'] === (int)$current['id'] ? 'Koncept' : 'Starší podklad')?></td></tr><?php endforeach; ?>
        <?php if (!$drafts): ?><tr><td colspan="5">Zatím nejsou uložené žádné koncepty.</td></tr><?php endif; ?>
    </tbody></table></div></section>
    <section class="card"><div class="card-header"><h2 class="h5 mb-0">Historie zveřejněných verzí</h2></div><div class="card-body">
        <?php foreach ($history as $term): ?><details class="border rounded p-3 mb-2"><summary class="text-break"><?=registrationTextH(registrationTextTitle($term, $purpose))?> · <?=registrationTextH($term['terms_version'])?> · <?=registrationTextH($term['created_at'])?><?=(int)$term['id'] === (int)$current['id'] ? ' · Aktuální' : ''?></summary><div class="mt-3">
            <p class="small">Zveřejnil/a: <?=registrationTextH($term['actor_type'] === 'trainer' ? ($names[(int)$term['actor_id']] ?? 'Pracovník č. '.(int)$term['actor_id']) : 'Systém při nasazení')?><?php if ($term['reason']): ?> · Důvod: <?=registrationTextH($term['reason'])?><?php endif; ?></p>
            <div class="text-break"><?=nl2br(registrationTextH($term['consent_text_plain']))?></div>
        </div></details><?php endforeach; ?>
    </div></section>
</main></body></html>
