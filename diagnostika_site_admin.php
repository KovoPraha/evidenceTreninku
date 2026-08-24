<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/auth_rate_limit.php';

header('Cache-Control: no-store, private, max-age=0');
header('Pragma: no-cache');
if (!isset($_SESSION['trener_id']) || !roleAtLeast('admin')) {
    http_response_code(404);
    exit('Nenalezeno.');
}

function siteDiagnosticsH(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$remoteValue = $_SERVER['REMOTE_ADDR'] ?? '';
$remoteAddress = is_string($remoteValue) ? $remoteValue : '';
$derivedAddress = auth_rate_limit_request_ip();
$headers = [
    'X-Forwarded-For' => 'HTTP_X_FORWARDED_FOR',
    'X-Real-IP' => 'HTTP_X_REAL_IP',
    'CF-Connecting-IP' => 'HTTP_CF_CONNECTING_IP',
    'Forwarded' => 'HTTP_FORWARDED',
];
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Diagnostika síťové adresy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
</head>
<body class="bg-light">
<?php require_once __DIR__ . '/hlavicka.php'; ?>
<main class="container py-4" style="max-width:960px">
    <h1 class="h3 mb-2">Diagnostika síťové adresy</h1>
    <p class="text-muted">Pouze ke čtení. Stránka ukazuje údaje aktuálního požadavku a nic neukládá.</p>

    <div class="alert alert-info">
        Porovnejte odvozenou adresu při otevření z různých sítí. Stejná adresa může znamenat,
        že je hosting za reverzní proxy a je potřeba bezpečně nastavit důvěryhodné proxy.
    </div>

    <div class="table-responsive">
        <table class="table table-bordered align-middle bg-white">
            <thead><tr><th>Údaj</th><th>Přítomno</th><th>Hodnota</th></tr></thead>
            <tbody>
                <tr>
                    <th scope="row">REMOTE_ADDR</th>
                    <td><?= $remoteAddress !== '' ? 'ano' : 'ne' ?></td>
                    <td><code><?= siteDiagnosticsH($remoteAddress !== '' ? $remoteAddress : '—') ?></code></td>
                </tr>
                <?php foreach ($headers as $label => $serverKey):
                    $rawValue = $_SERVER[$serverKey] ?? '';
                    $value = is_string($rawValue) ? $rawValue : '';
                ?>
                    <tr>
                        <th scope="row"><?= siteDiagnosticsH($label) ?></th>
                        <td><?= $value !== '' ? 'ano' : 'ne' ?></td>
                        <td><code><?= siteDiagnosticsH($value !== '' ? $value : '—') ?></code></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="table-primary">
                    <th scope="row">Odvozená adresa pro rate limit</th>
                    <td>ano</td>
                    <td><code><?= siteDiagnosticsH($derivedAddress) ?></code></td>
                </tr>
                <tr>
                    <th scope="row">Odvozená adresa je privátní</th>
                    <td colspan="2"><?= auth_rate_limit_ip_is_private($derivedAddress) ? 'ano' : 'ne' ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</main>
</body>
</html>
