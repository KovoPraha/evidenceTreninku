<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/shoptet_product_input.php';
require_once dirname(__DIR__) . '/includes/shop_catalog_stage.php';

const SHOP_STAGE_USAGE = 64;
const SHOP_STAGE_VALIDATION = 2;

/** @return never */
function shopStageUsage(string $message = ''): void
{
    if ($message !== '') {
        fwrite(STDERR, $message . PHP_EOL . PHP_EOL);
    }
    fwrite(
        STDERR,
        "Pouziti: APP_HOST=<host> php bin/shoptet-products-stage.php "
        . "--input=<lokalni-soubor.csv|xml> --apply [--json]\n"
        . "Nejprve spustte bin/shoptet-products-dry-run.php bez --apply.\n"
    );
    exit(SHOP_STAGE_USAGE);
}

/** @return array{input:string,json:bool} */
function shopStageArguments(array $arguments): array
{
    $input = '';
    $inputSeen = false;
    $apply = false;
    $json = false;
    for ($index = 1, $count = count($arguments); $index < $count; $index++) {
        $argument = (string)$arguments[$index];
        if ($argument === '--help' || $argument === '-h') {
            shopStageUsage();
        }
        if ($argument === '--apply') {
            if ($apply) {
                shopStageUsage('Parametr --apply smi byt uveden pouze jednou.');
            }
            $apply = true;
            continue;
        }
        if ($argument === '--json') {
            $json = true;
            continue;
        }
        if ($argument === '--input') {
            if ($inputSeen || !isset($arguments[$index + 1])) {
                shopStageUsage('Parametr --input chybi nebo je uveden vicekrat.');
            }
            $input = (string)$arguments[++$index];
            $inputSeen = true;
            continue;
        }
        if (str_starts_with($argument, '--input=')) {
            if ($inputSeen) {
                shopStageUsage('Parametr --input smi byt uveden pouze jednou.');
            }
            $input = substr($argument, 8);
            $inputSeen = true;
            continue;
        }
        shopStageUsage('Neznamy parametr: ' . $argument);
    }

    if (!$apply) {
        shopStageUsage('Zapis do stagingu vyzaduje explicitni --apply.');
    }
    if ($input === ''
        || str_contains($input, '://')
        || !in_array(strtolower(pathinfo($input, PATHINFO_EXTENSION)), ['csv', 'xml'], true)
        || is_link($input)
        || !is_file($input)
        || !is_readable($input)
    ) {
        shopStageUsage('Vstup musi byt citelny lokalni regularni .csv nebo .xml soubor.');
    }
    return ['input' => $input, 'json' => $json];
}

/** @param array<string,mixed> $payload */
function shopStageOutput(array $payload, bool $json): string
{
    if ($json) {
        return json_encode(
            $payload,
            JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ) . PHP_EOL;
    }
    return sprintf(
        "%s staging run #%d: produkty %d, varianty %d, rucni kontrola %d.\n",
        $payload['created'] ? 'Vytvoren' : 'Jiz existuje',
        $payload['run_id'],
        $payload['products'],
        $payload['variants'],
        $payload['manual_review_products']
    );
}

// Omezené hostingové shelly blokují argumenty za jménem skriptu; bez argumentů
// lze proto vstupy zadat proměnnými prostředí SHOPTET_INPUT, SHOPTET_APPLY=1
// a SHOPTET_JSON=1 (typicky přes putenv bootstrap, viz
// docs/thinline-deploy-runbook.md). CLI argumenty mají přednost.
if (count($argv) === 1) {
    $environmentInput = (string)getenv('SHOPTET_INPUT');
    if ($environmentInput !== '') {
        $argv[] = '--input=' . $environmentInput;
        if ((string)getenv('SHOPTET_APPLY') === '1') {
            $argv[] = '--apply';
        }
        if ((string)getenv('SHOPTET_JSON') === '1') {
            $argv[] = '--json';
        }
    }
}

$options = shopStageArguments($argv);
$appHost = getenv('APP_HOST');
if (!is_string($appHost) || preg_match('/^[a-z0-9.-]+(?::\d+)?$/Di', $appHost) !== 1) {
    shopStageUsage('APP_HOST musi byt explicitne nastaveny na hostname aplikace.');
}
$_SERVER['HTTP_HOST'] = $appHost;
$_SERVER['SERVER_NAME'] = (string)preg_replace('/:\d+$/', '', $appHost);

try {
    $parsed = ShoptetProductInput::read($options['input']);
    $catalog = ShopCatalogContract::build($parsed);
    if (!$catalog['summary']['contract_ready']) {
        fwrite(STDERR, 'Import obsahuje blokatory; nejprve opravte dry-run.' . PHP_EOL);
        exit(SHOP_STAGE_VALIDATION);
    }

    require dirname(__DIR__) . '/config.php';
    foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $constant) {
        if (!defined($constant)) {
            throw new RuntimeException('Chybi databazova konfigurace.');
        }
    }
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    $result = shopCatalogStage($pdo, $catalog);
    fwrite(STDOUT, shopStageOutput($result, $options['json']));
    exit(0);
} catch (ShoptetProductCsvException | ShoptetProductXmlException $exception) {
    fwrite(STDERR, 'Neplatny vstup katalogu: ' . $exception->getMessage() . PHP_EOL);
    exit(SHOP_STAGE_VALIDATION);
} catch (Throwable $exception) {
    error_log('shoptet-products-stage.php: ' . $exception->getMessage());
    fwrite(
        STDERR,
        "Staging katalogu selhal. Overte, ze probehla migrace bin/migrate.php --apply.\n"
    );
    exit(1);
}
