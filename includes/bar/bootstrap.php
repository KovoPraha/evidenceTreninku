<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/session_security.php';
require_once dirname(__DIR__) . '/ui_shell.php';
require_once dirname(__DIR__, 2) . '/csrf_helper.php';
require_once __DIR__ . '/web.php';

/** A retry contains only the caller's own form. It reads no business data and never authorizes a write. */
function barRenderDatabaseUnavailable(array $post): void
{
    if (ob_get_length() !== false && ob_get_length() > 0) {
        ob_clean();
    }
    http_response_code(503);
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store, private');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    $valid = isset($_SESSION['trener_id']) && is_string($post['csrf_token'] ?? null) && csrf_verify($post['csrf_token']);
    ?>
<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Spojení s barem není dostupné | KIS</title><?php appUiAssets(); ?>
<link rel="stylesheet" href="<?= barH(appUiUrl('assets/vendor/bootstrap/bootstrap.min.css')) ?>"></head><body>
<main class="container py-5"><h1>Spojení s barem není dostupné</h1>
<?php if ($post !== [] && $valid): ?>
    <p>Výsledek posledního potvrzení se nepodařilo ověřit. Po obnovení spojení zopakujte stejný požadavek.
        Již uložená operace se neprovede podruhé. Před opakováním neměňte její obsah.</p>
    <form method="post" enctype="multipart/form-data"><?= csrf_field() ?>
        <?php $remaining = 5000;
    barRenderRetryFields($post, '', $remaining); ?>
        <?php foreach ($_FILES as $name => $file): ?>
            <?php if (preg_match('/^[a-zA-Z0-9_]+$/D', $name) && !is_array($file['error'] ?? null)
            && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE): ?>
                <label class="d-block mb-3">Znovu vyberte stejný soubor <input type="file" name="<?= barH($name) ?>" required></label>
            <?php endif; ?>
        <?php endforeach; ?>
        <button class="btn btn-primary">Zopakovat stejný požadavek</button>
    </form>
<?php else: ?>
    <p>Stránku se nepodařilo načíst. Zkuste to po obnovení spojení znovu.</p>
    <?php if ($post !== []): ?><p>Formulář již není platný. Po přihlášení ověřte poslední doklady a aktuální stav.</p><?php endif; ?>
    <a class="btn btn-primary" href="">Zkusit znovu načíst stránku</a>
<?php endif; ?>
</main></body></html>
    <?php
}

function barRenderRetryFields(array $values, string $prefix, int &$remaining, int $depth = 0): void
{
    if ($depth > 5) {
        return;
    }
    foreach ($values as $key => $value) {
        if ($remaining-- < 1 || !preg_match('/^[a-zA-Z0-9_:-]+$/D', (string)$key) || ($prefix === '' && $key === 'csrf_token')) {
            continue;
        }
        $name = $prefix === '' ? (string)$key : $prefix . '[' . $key . ']';
        if (is_array($value)) {
            barRenderRetryFields($value, $name, $remaining, $depth + 1);
        } elseif (is_string($value) && strlen($value) <= 500000) {
            echo '<input type="hidden" name="' . barH($name) . '" value="' . barH($value) . '">';
        }
    }
}

/** Only protected BAR entry points opt in; shared KIS bootstrap behavior remains unchanged. */
function barBootstrap(): PDO
{
    global $pdo;
    app_session_start();
    if (!isset($_SESSION['trener_id'])) {
        header('Cache-Control: no-store, private');
        header('Location: ../login.php', true, 302);
        exit;
    }
    if (!defined('BAR_DATABASE_FAILURE_EXCEPTION')) {
        define('BAR_DATABASE_FAILURE_EXCEPTION', true);
    }
    $previous = null;
    $previous = set_exception_handler(static function (Throwable $error) use (&$previous): void {
        if ($error instanceof PDOException) {
            error_log('BAR database unavailable; original request retained. SQLSTATE=' . (string)$error->getCode());
            barRenderDatabaseUnavailable($_POST);
            return;
        }
        if (is_callable($previous)) {
            $previous($error);
            return;
        }
        throw $error;
    });
    try {
        require_once dirname(__DIR__) . '/init.php';
    } catch (PDOException $error) {
        barRenderDatabaseUnavailable($_POST);
        exit;
    }
    if (!$pdo instanceof PDO) {
        throw new RuntimeException('Databázové připojení není dostupné.');
    }
    return $pdo;
}
