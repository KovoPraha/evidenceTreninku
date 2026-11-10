<?php

declare(strict_types=1);

// Presentation adapted from the original BAR; data is supplied exclusively by KIS.
/** @var string $nadpis */
/** @var string $podtitul */
/** @var string $vzhled */
/** @var int $obnova */
/** @var array $sloupce */
/** @var array $kategorie */
/** @var array $produkty */
/** @var string $json */
?>
<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#000000">
<title>KOVO BAR – obrazová nabídka</title>
<link rel="stylesheet" href="../assets/bar-photos.css?v=20261110">
</head>
<body data-refresh-seconds="<?= $obnova ?>">
<main class="plocha">
    <?php if (!$produkty): ?>
        <div class="prazdne">Nabídka se právě připravuje.</div>
    <?php else: ?>
        <section class="produkty" id="produkty" aria-live="polite" aria-label="Aktuální nabídka"></section>
    <?php endif; ?>
</main>
<?php if ($produkty): ?>
<script id="bar-screen-products" type="application/json" nonce="<?= barH(app_csp_nonce()) ?>"><?= $json ?></script>
<script src="../assets/bar-photos.js?v=20261110" defer></script>
<?php endif; ?>
</body>
</html>
