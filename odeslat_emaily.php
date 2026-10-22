<?php
require_once __DIR__.'/includes/init.php';
if (!isset($_SESSION['trener_id'])) { header('Location: login.php'); exit; }
if (!canAccess('odeslat_emaily')) { http_response_code(403); exit('Přístup odepřen.'); }
header('Cache-Control: no-store, private');
if (($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
    http_response_code(410);
    exit('Rozesílání veřejných odkazů na profily podle kontaktního e-mailu bylo ukončeno. Použijte zprávy soupisek ověřeným účtům nebo požádejte registráře. Nebyla odeslána žádná zpráva.');
}
?>
<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Komunikace se členy</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
<?php appUiAssets(); ?></head><body class="bg-light">
<?php require_once __DIR__.'/hlavicka.php'; ?>
<main class="container py-4"><h1 class="fw-semibold fs-5 mb-0">Komunikace se členy</h1>
<div class="alert alert-info mt-3">Původní rozesílání veřejných odkazů na profily na kontaktní e-maily bylo ukončeno. Kontaktní e-mail sám nepotvrzuje oprávnění k údajům sportovce.</div>
<p>Zprávy soupisek používají aktivní ověřené účty a schválené vazby rodičů a sportovců. Příjemce znovu ověřují před doručením a chyby zůstávají dohledatelné ve frontě.</p>
<p><a class="btn btn-primary" href="roster_messages_admin.php">Otevřít zprávy soupisek</a> <a class="btn btn-outline-secondary" href="pracovni_pozice.php">Pracovní rozcestník</a></p>
<p class="text-muted">Nemáte-li přístup ke zprávám soupisek, požádejte registráře o vyřízení. Členovi bez účtu předá klub informaci osobně; tímto odkazem se žádná oprávnění nepřidávají.</p>
</main></body></html>
