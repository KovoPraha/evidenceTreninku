<?php
declare(strict_types=1);

require_once __DIR__ . '/web.php';
require_once __DIR__ . '/offers.php';
require_once dirname(__DIR__) . '/session_security.php';

function barMenuHtml(array $menu, bool $pdf = false): string
{
    ob_start();
    ?>
<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= barH($menu['offer']['title'] ?? 'Bar KOVO') ?></title>
<?php if (!$pdf): ?><meta http-equiv="refresh" content="30"><?php endif; ?>
<style nonce="<?= barH(app_csp_nonce()) ?>">
@page{margin:18mm}
*{box-sizing:border-box}
body{font-family:<?= $pdf ? 'DejaVu Sans' : 'system-ui,sans-serif' ?>;margin:0;background:#102222;color:#f6f4eb;padding:32px}
main{max-width:1400px;margin:auto}
h1{font-size:36px;margin:0
0 8px}
header{border-bottom:3px solid #66d8b2;padding-bottom:22px;margin-bottom:24px}
header p{margin:0;font-size:20px}
.items{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:20px}
.item{padding:18px;border:1px
solid #46625b;border-radius:12px;break-inside:avoid;page-break-inside:avoid}
.item h2{font-size:24px;margin:0 0 8px}
.category{color:#a1efd5;font-size:14px;margin:0
0 8px}
.description{font-size:17px;white-space:pre-line}
.price{font-size:32px;font-weight:700;margin:12px 0 0}
.sold{color:#ffd0a0}
.photo{width:100%;height:220px;object-fit:contain;border-radius:8px}
footer{margin-top:24px;font-size:12px;color:#c5d5cf}
@media(max-width:650px){body{padding:18px}
.items{grid-template-columns:1fr}
h1{font-size:28px}
}
@media
print{body{background:white;color:#132e25;padding:0}
.category{color:#28634d}
.items{display:block}
.item{margin-bottom:12px}
footer{color:#333}
}
<?php if ($pdf): ?>body{background:#fff;color:#132e25;padding:0}
.items{display:block}
.item{padding:12px;margin-bottom:12px;border-color:#b8cbc3}
.category{color:#28634d}
.description{font-size:13px}
.price{font-size:22px}
.item
h2{font-size:20px}
footer{color:#333}
<?php endif; ?>
</style></head><body><main><header><h1><?= barH($menu['offer']['title'] ?? 'Bar KOVO') ?></h1><p><?= barH($menu['offer']['subtitle']
    ?? '') ?></p></header>
<?php if ($menu['products'] === []): ?><p>Nabídka se připravuje.</p><?php else: ?><div class="items">
<?php foreach ($menu['products'] as $product): ?><article class="item">
    <?php if (!$pdf && ($menu['offer']['layout'] ?? '') === 'photos' && $product['image_id'] !== null): ?><img class="photo" src="menu-image.php?id=<?=
    (int)$product['image_id'] ?>" alt=""><?php endif; ?>
    <p class="category"><?= barH($product['category']) ?></p><h2><?= barH($product['name']) ?></h2>
    <div class="description"><?= nl2br(barH($product['description'])) ?></div><p class="price"><?= barH(barFormatMoney((int)$product['price_minor']))
    ?></p>
    <?php if ((int)$product['sold_out'] === 1): ?><p class="sold">Vyprodáno</p><?php endif; ?>
</article><?php endforeach; ?></div><?php endif; ?>
<?php if ($pdf): ?><footer>Ceny v Kč. Vygenerováno <?= barH((new DateTimeImmutable('now', new DateTimeZone('Europe/Prague')))->format('d. m. Y H:i'))
    ?> · Aktuální nabídka se může změnit.</footer><?php endif; ?>
</main></body></html>
    <?php
    return (string)ob_get_clean();
}
