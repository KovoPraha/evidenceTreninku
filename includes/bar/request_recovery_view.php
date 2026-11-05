<?php

declare(strict_types=1);

function barRenderRequestRecoveryMount(): void
{
    $actorId = (int)($_SESSION['trener_id'] ?? 0);
    if ($actorId < 1) {
        return;
    }
    $rejected = in_array(http_response_code(), [409, 422], true) && is_string($_POST['token'] ?? null) ? $_POST['token'] : '';
    $version = (string)(filemtime(dirname(__DIR__, 2) . '/assets/bar-requests.js') ?: '1');
    ?>
    <div id="bar-request-recovery" data-user="<?= $actorId ?>:<?= (int)($_SESSION['trener_session_version'] ?? 0) ?>"
         data-csrf="<?= barH(csrf_token()) ?>" data-prefix="<?= barH(appUiUrl('bar/')) ?>"
         data-rejected="<?= barH($rejected) ?>" aria-live="polite"></div>
    <script nonce="<?= barH(app_csp_nonce()) ?>" defer src="<?= barH(appUiUrl('assets/bar-requests.js') . '?v=' . $version) ?>"></script>
    <?php
}
