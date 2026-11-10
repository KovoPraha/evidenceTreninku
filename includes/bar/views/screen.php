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
<meta name="theme-color" content="#050505">
<title><?= barH($nadpis) ?> – obrazovka</title>
<link rel="stylesheet" href="../assets/bar-screen.css?v=20261110">
</head>
<body class="<?= barH($vzhled) ?>" data-refresh-seconds="<?= $obnova ?>">
<main class="stranka">
    <header class="hlava">
        <h1><?= barH($nadpis) ?></h1>
        <?php if ($podtitul !== ''): ?><div class="podtitul"><?= barH($podtitul) ?></div><?php endif; ?>
    </header>

    <div class="sloupce" id="obsah">
        <?php if (!$kategorie): ?>
            <div class="prazdne">Nabídka se právě připravuje.</div>
        <?php else: ?>
            <?php foreach ($sloupce as $sloupec): ?>
                <div class="sloupec">
                <?php foreach ($sloupec as $kat): ?>
                    <section class="kategorie k<?= (int)$kat['barva'] ?>">
                        <h2><?= barH($kat['nazev']) ?></h2>
                        <?php foreach ($kat['produkty'] as $produkt): ?>
                            <div class="polozka<?= $produkt['vyprodano'] ? ' vyprodano' : '' ?>">
                                <span class="nazev"><?= barH($produkt['nazev']) ?><?php if ($produkt['vyprodano']): ?><span class="stav">vyprodáno</span><?php endif; ?></span>
                                <span class="tecky" aria-hidden="true"></span>
                                <span class="cena"><?= barDisplayPrice($produkt['cena']) ?></span>
                                <?php if (trim((string)$produkt['popis']) !== ''): ?><span class="popis"><?= barH($produkt['popis']) ?></span><?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </section>
                <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <footer class="pata"><span>Ceny včetně DPH</span><span id="hodiny"></span></footer>
</main>
<script src="../assets/bar-screen.js?v=20261110" nonce="<?= barH(app_csp_nonce()) ?>" defer></script>
</body>
</html>
