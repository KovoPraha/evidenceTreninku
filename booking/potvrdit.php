<?php
require_once dirname(__DIR__) . '/includes/session_security.php';
app_session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/one_time_token.php';
require_once __DIR__ . '/waiting_list.php';
require_once __DIR__ . '/../csrf_helper.php';

header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$source = $method === 'POST' ? $_POST : [];
$token = trim((string)($source['token'] ?? ''));
$akce = in_array($source['akce'] ?? '', ['potvrdit', 'zamit'], true)
    ? (string)$source['akce']
    : '';
$legacyToken = $method === 'GET' ? trim((string)($_GET['token'] ?? '')) : '';
$legacyAction = $method === 'GET' && in_array($_GET['akce'] ?? '', ['potvrdit', 'zamit'], true)
    ? (string)$_GET['akce']
    : '';
if (one_time_token_hash(ONE_TIME_TOKEN_BOOKING_APPROVAL, $legacyToken) === '') {
    $legacyToken = '';
    $legacyAction = '';
}

$reservation = null;
$ok = false;
$msg = '';

if ($method === 'POST' && !csrf_verify((string)($_POST['csrf_token'] ?? ''))) {
    $msg = 'Požadavek není platný. Otevřete původní odkaz znovu.';
} elseif ($method === 'GET') {
    $msg = 'Načítáme bezpečný náhled rezervace…';
} elseif ($token !== '' && $akce !== '') {
    if ($method === 'POST' && ($_POST['faze'] ?? '') === 'potvrdit') {
        $reservation = one_time_booking_approval_consume($pdo, $token, $akce);
        if ($reservation) {
            $ok = true;
            $msg = $akce === 'potvrdit' ? 'Rezervace potvrzena.' : 'Rezervace zamítnuta.';

            $terminInfo = !empty($reservation['slot_cas_od']) && !empty($reservation['slot_cas_do'])
                ? 'Datum: ' . $reservation['datum'] . ', Slot: '
                    . substr((string)$reservation['slot_cas_od'], 0, 5) . '–'
                    . substr((string)$reservation['slot_cas_do'], 0, 5)
                : 'Datum: ' . $reservation['datum'] . ', '
                    . substr((string)$reservation['cas_od'], 0, 5) . '–'
                    . substr((string)$reservation['cas_do'], 0, 5);

            if ($akce === 'potvrdit') {
                $subject = 'Rezervace potvrzena — ' . $reservation['nazev'];
                $body = "Dobrý den {$reservation['jmeno']},\n\nVaše rezervace byla potvrzena trenérem:\n"
                    . "Lekce: {$reservation['nazev']}\n{$terminInfo}\n\nTěšíme se na Vás!";
            } else {
                notifyWaitingList(
                    $pdo,
                    (int)$reservation['lekce_id'],
                    (string)($reservation['slot_cas_od'] ?? '')
                );
                $subject = 'Rezervace zamítnuta — ' . $reservation['nazev'];
                $body = "Dobrý den {$reservation['jmeno']},\n\nBohužel Vaše žádost o rezervaci byla zamítnuta.\n"
                    . "{$terminInfo}\nV případě dotazů nás kontaktujte.";
            }

            @mail((string)$reservation['email'], $subject, $body,
                "From: evidence@kovopraha.cz\r\nContent-Type: text/plain; charset=utf-8");
        } else {
            $msg = 'Odkaz není platný, expiroval nebo byla rezervace již zpracována.';
        }
    } elseif ($method === 'POST' && ($_POST['faze'] ?? '') === 'nahled') {
        $reservation = one_time_booking_approval_lookup($pdo, $token);
        if (!$reservation) {
            $msg = 'Odkaz není platný, expiroval nebo byla rezervace již zpracována.';
        }
    }
} else {
    $msg = 'Neplatný požadavek.';
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>Potvrzení rezervace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php appUiAssets(); ?>
</head>
<body class="bg-light">
<div class="container mt-5" style="max-width:520px">
    <div class="card shadow text-center p-4">
        <?php if ($ok): ?>
            <i class="bi bi-check-circle-fill text-success fs-1 mb-3"></i>
            <h5><?= h($msg) ?></h5>
            <p class="text-muted">Zákazník byl informován e-mailem.</p>
        <?php elseif ($reservation): ?>
            <i class="bi bi-question-circle-fill text-primary fs-1 mb-3"></i>
            <h5><?= $akce === 'potvrdit' ? 'Potvrdit rezervaci?' : 'Zamítnout rezervaci?' ?></h5>
            <p class="mb-1"><strong><?= h((string)$reservation['nazev']) ?></strong></p>
            <p class="text-muted"><?= h((string)$reservation['datum']) ?> · <?= h((string)$reservation['jmeno']) ?></p>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= h($token) ?>">
                <input type="hidden" name="akce" value="<?= h($akce) ?>">
                <input type="hidden" name="faze" value="potvrdit">
                <button type="submit" class="btn <?= $akce === 'potvrdit' ? 'btn-success' : 'btn-danger' ?>">
                    <?= $akce === 'potvrdit' ? 'Ano, potvrdit' : 'Ano, zamítnout' ?>
                </button>
            </form>
        <?php else: ?>
            <i class="bi bi-exclamation-triangle-fill text-warning fs-1 mb-3"></i>
            <h5 id="approval-message"><?= h($msg) ?></h5>
            <?php if ($method === 'GET'): ?>
                <form method="post" id="approval-preview-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="token" id="approval-token" value="<?= h($legacyToken) ?>">
                    <input type="hidden" name="akce" id="approval-action" value="<?= h($legacyAction) ?>">
                    <input type="hidden" name="faze" value="nahled">
                    <button type="submit" id="approval-preview-submit" class="btn btn-primary" <?= $legacyToken === '' ? 'disabled' : '' ?>>
                        Zobrazit náhled rozhodnutí
                    </button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
        <a href="../individualni_lekce_sprava.php" class="btn btn-outline-secondary mt-3">
            Přejít na správu lekcí
        </a>
    </div>
</div>
<?php if ($method === 'GET'): ?>
<script>
(() => {
    const params = new URLSearchParams(window.location.hash.slice(1));
    const token = params.get('token') || '';
    const action = params.get('akce') || '';
    history.replaceState(null, '', window.location.pathname);
    const submit = document.getElementById('approval-preview-submit');
    if (!/^[a-f0-9]{48,128}$/.test(token) || !['potvrdit', 'zamit'].includes(action)) {
        if (!document.getElementById('approval-token').value) {
            document.getElementById('approval-message').textContent = 'Odkaz není platný nebo je neúplný.';
        }
        return;
    }
    document.getElementById('approval-token').value = token;
    document.getElementById('approval-action').value = action;
    submit.disabled = false;
})();
</script>
<?php endif; ?>
</body>
</html>
