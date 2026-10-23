<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/uat_readiness.php';
require_once __DIR__ . '/includes/private_storage_readiness.php';

header('Cache-Control: no-store, private');
if (!isset($_SESSION['trener_id']) || !roleAtLeast('admin')) {
    header('Location: login.php');
    exit;
}

$privateStorageReadiness = privateStorageReadiness();
$privateStorageReady = $privateStorageReadiness['configured'] && $privateStorageReadiness['outside_webroot']
    && $privateStorageReadiness['directory_exists'] && $privateStorageReadiness['writable'];

function uatPageH(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

try {
    $readiness = uatReadiness($pdo, __DIR__);
    $loadError = '';
} catch (Throwable $exception) {
    error_log('uat_readiness_admin.php: ' . $exception->getMessage());
    $readiness = ['ready' => false, 'generated_at' => '', 'checks' => [], 'release' => []];
    $loadError = 'Před-UAT kontrolu se nyní nepodařilo bezpečně načíst.';
}
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Připravenost produkčního UAT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
</head>
<body class="bg-light">
<?php require __DIR__ . '/hlavicka.php'; ?>
<main class="container py-4" style="max-width:1050px">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div><h1 class="h3 mb-1"><i class="bi bi-clipboard2-check me-2 text-primary"></i>Připravenost produkčního UAT</h1><p class="text-muted mb-0">Kontrola pouze čte stav. Nic nevytváří, neodesílá, neplatí ani nemaže.</p></div>
        <div class="text-end"><span class="badge text-bg-<?= $readiness['ready'] ? 'success' : 'danger' ?> fs-6"><?= $readiness['ready'] ? 'PŘIPRAVENO' : 'BLOKOVÁNO' ?></span><?php if($readiness['generated_at']!==''):?><div class="small text-muted mt-1">Stav k <?=uatPageH($readiness['generated_at'])?></div><?php endif;?></div>
    </div>
    <?php if ($loadError !== ''): ?><div class="alert alert-danger"><?=uatPageH($loadError)?></div><?php endif; ?>
    <div class="alert alert-info"><strong>Zápisové scénáře spusťte jen při zeleném výsledku.</strong> Červená položka uvádí konkrétní scénáře, které blokuje. Hesla, Stripe klíče ani bankovní údaje tato stránka nikdy nezobrazuje.</div>
    <section class="card mb-3 border-<?= $privateStorageReady ? 'success' : 'danger' ?>">
        <div class="card-body">
            <h2 class="h5">Soukromé úložiště dokumentů <span class="badge text-bg-<?= $privateStorageReady ? 'success' : 'danger' ?>"><?= $privateStorageReady ? 'PASS' : 'BLOCKED' ?></span></h2>
            <p class="mb-1">Ověřeno aktuálním webovým procesem, bez vytváření souborů.</p>
            <ul class="mb-0">
            <?php foreach (['configured'=>'Nastavení úložiště', 'outside_webroot'=>'Umístění mimo veřejný web', 'directory_exists'=>'Dostupný adresář', 'writable'=>'Oprávnění k zápisu'] as $key=>$label): ?>
                <li><?=uatPageH($label)?>: <?= $privateStorageReadiness[$key] ? 'ANO' : 'NE' ?></li>
            <?php endforeach; ?>
            </ul>
            <details class="mt-2"><summary>Technická kontrola přístupu</summary><ul>
            <?php foreach (['configured_from_environment'=>'Nastavení z prostředí', 'configured_from_constant'=>'Nastavení z konfigurace', 'environment_constant_mismatch'=>'Prostředí přebíjí jinou cestu v konfiguraci', 'open_basedir_enabled'=>'Hosting omezuje přístup k adresářům', 'open_basedir_allows_configured_root'=>'Cesta je v povoleném rozsahu hostingu', 'root_realpath_available'=>'Lze ověřit skutečnou cestu', 'directory_stat_available'=>'Lze přečíst údaje adresáře', 'effective_uid_available'=>'Lze zjistit vlastníka webového procesu', 'directory_owner_uid_available'=>'Lze zjistit vlastníka adresáře', 'directory_owner_matches_effective_uid'=>'Vlastník adresáře odpovídá webovému procesu'] as $key=>$label): ?>
                <li><?=uatPageH($label)?>: <?= $privateStorageReadiness[$key] ? 'ANO' : 'NE' ?></li>
            <?php endforeach; ?>
            </ul><p class="small text-muted mb-0">Nedostupné údaje neprokazují rozdílného vlastníka. Rozhodující je dostupný adresář a oprávnění k zápisu.</p></details>
        </div>
    </section>
    <div class="alert alert-warning">Produkční účetnictví a sklad nemají oddělenou testovací evidenci. Fiktivní doklady, platby, výdeje a vratky zkoušejte pouze v izolovaném prostředí. Tento přehled nepotvrzuje dokončení uživatelské přejímky.</div>
    <div class="vstack gap-3">
        <?php foreach ($readiness['checks'] as $check): ?>
            <section class="card border-0 shadow-sm border-start border-5 border-<?= $check['ok'] ? 'success' : 'danger' ?>">
                <div class="card-body d-flex flex-column flex-md-row justify-content-between gap-3">
                    <div><h2 class="h5 mb-1"><?=uatPageH($check['label'])?></h2><div class="text-muted"><?=uatPageH($check['detail'])?></div></div>
                    <div class="text-md-end"><span class="badge text-bg-<?= $check['ok'] ? 'success' : 'danger' ?>"><?= $check['ok'] ? 'PASS' : 'BLOCKED' ?></span><?php if(!$check['ok']):?><div class="small text-muted mt-1">Scénáře <?=uatPageH(implode(', ', array_map(static fn(int $scenario): string => sprintf('%02d',$scenario), $check['scenarios'])))?></div><?php endif;?></div>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
</main>
</body>
</html>
