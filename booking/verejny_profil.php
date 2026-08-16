<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/session_security.php';
app_session_start();
if (!isset($_SESSION['verejny_uzivatel_id'])) {
    header('Location: prihlaseni.php?redirect=verejny_profil.php');
    exit;
}
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/csrf_helper.php';
require_once dirname(__DIR__) . '/includes/public_profile.php';

function publicProfileH(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$accountId = (int)$_SESSION['verejny_uzivatel_id'];
$errors = [];
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Formulář vypršel. Obnovte stránku.';
    } else {
        try {
            $result = publicProfileSave(
                $pdo,
                $accountId,
                (string)($_POST['jmeno'] ?? ''),
                (string)($_POST['prijmeni'] ?? ''),
                (string)($_POST['narozeni'] ?? ''),
                (string)($_POST['telefon'] ?? '')
            );
            $success = $result['created'] ? 'Profil účastníka byl vytvořen.' : 'Profil účastníka byl uložen.';
        } catch (InvalidArgumentException | PublicProfileException $exception) {
            $errors[] = $exception->getMessage();
        }
    }
}
$profile = publicProfileForAccount($pdo, $accountId);
$accountStatement = $pdo->prepare('SELECT jmeno,prijmeni,email,telefon FROM verejni_uzivatele WHERE id=?');
$accountStatement->execute([$accountId]);
$account = $accountStatement->fetch(PDO::FETCH_ASSOC) ?: [];
?>
<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Můj veřejný profil</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><?php appUiAssets(); ?></head><body class="bg-light">
<?php publicShellNav(); ?>
<main class="container py-4" style="max-width:720px"><div class="d-flex justify-content-between mb-3"><div><h1 class="h4 mb-1">Můj profil účastníka</h1><p class="text-muted mb-0">Tento profil se používá pro rezervace velodromu a budoucí klubové služby.</p></div><a class="btn btn-outline-primary btn-sm align-self-start" href="velodrom.php">Velodrom</a></div>
<?php foreach($errors as $error):?><div class="alert alert-danger"><?=publicProfileH($error)?></div><?php endforeach;?><?php if($success!==''):?><div class="alert alert-success"><?=publicProfileH($success)?></div><?php endif;?>
<div class="card shadow-sm border-0"><div class="card-body"><form method="post" class="row g-3"><?=csrf_field()?><div class="col-md-6"><label class="form-label">Jméno</label><input class="form-control" name="jmeno" maxlength="100" required value="<?=publicProfileH($profile['jmeno']??$account['jmeno']??'')?>"></div><div class="col-md-6"><label class="form-label">Příjmení</label><input class="form-control" name="prijmeni" maxlength="100" required value="<?=publicProfileH($profile['prijmeni']??$account['prijmeni']??'')?>"></div><div class="col-md-6"><label class="form-label">Datum narození</label><input class="form-control" type="date" name="narozeni" required value="<?=publicProfileH($profile['narozeni']??'')?>"></div><div class="col-md-6"><label class="form-label">Telefon</label><input class="form-control" name="telefon" maxlength="50" value="<?=publicProfileH($profile['telefon']??$account['telefon']??'')?>"></div><div class="col-12"><label class="form-label">Kontaktní e-mail</label><input class="form-control" value="<?=publicProfileH($account['email']??'')?>" readonly><div class="form-text">E-mail je převzat z přihlášeného účtu.</div></div><div class="col-12 d-grid"><button class="btn btn-primary">Uložit profil</button></div></form></div></div>
</main><?php publicShellFooter(); ?></body></html>
