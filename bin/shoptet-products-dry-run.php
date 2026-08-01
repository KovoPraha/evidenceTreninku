<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/shoptet_product_csv.php';
require_once dirname(__DIR__) . '/includes/shop_catalog_contract.php';

const SHOP_DRY_RUN_USAGE = 64;
const SHOP_DRY_RUN_VALIDATION = 2;

/** @return never */
function shopDryRunUsage(string $message = ''): void
{
    if ($message !== '') {
        fwrite(STDERR, $message . PHP_EOL . PHP_EOL);
    }
    fwrite(
        STDERR,
        "Pouziti: php bin/shoptet-products-dry-run.php --input=<lokalni-soubor.csv> [--json]\n"
        . "Nastroj provadi pouze read-only dry-run. Neobsahuje zapis do DB ani apply rezim.\n"
    );
    exit(SHOP_DRY_RUN_USAGE);
}

/** @return array{input:string,json:bool} */
function shopDryRunArguments(array $arguments): array
{
    $input = '';
    $json = false;
    for ($index = 1, $count = count($arguments); $index < $count; $index++) {
        $argument = (string)$arguments[$index];
        if ($argument === '--help' || $argument === '-h') {
            shopDryRunUsage();
        }
        if ($argument === '--json') {
            $json = true;
            continue;
        }
        if ($argument === '--input') {
            if (!isset($arguments[$index + 1]) || str_starts_with((string)$arguments[$index + 1], '--')) {
                shopDryRunUsage('Za --input chybi cesta.');
            }
            $input = (string)$arguments[++$index];
            continue;
        }
        if (str_starts_with($argument, '--input=')) {
            $input = substr($argument, strlen('--input='));
            continue;
        }
        shopDryRunUsage('Neznamy parametr: ' . $argument);
    }
    if ($input === '') {
        shopDryRunUsage('Parametr --input je povinny.');
    }
    return ['input' => $input, 'json' => $json];
}

/** @param array<string,mixed> $result */
function shopDryRunJson(array $result): string
{
    $json = json_encode(
        $result,
        JSON_PRETTY_PRINT
        | JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_HEX_QUOT
        | JSON_THROW_ON_ERROR
    );
    return $json . PHP_EOL;
}

/** @param array<string,mixed> $result */
function shopDryRunSummary(array $result): string
{
    $summary = $result['summary'];
    $source = $result['source'];
    $lines = [
        'Shoptet katalogovy dry-run (bez zapisu)',
        'Soubor: ' . $source['filename'] . ' | SHA-256: ' . $source['sha256'],
        'Kodovani: ' . $source['encoding'] . ' | oddelovac: ' . $source['delimiter'],
        'Produkty: ' . $summary['products'] . ' | varianty: ' . $summary['variants'],
        'Blokatory: ' . $summary['errors'] . ' | varovani: ' . $summary['warnings'],
        'Stav kontraktu: ' . ($summary['contract_ready'] ? 'pripraven pro kontrolu' : 'vyzaduje opravu vstupu'),
        'Kontrakt je provisionalni do overeni realneho anonymizovaneho exportu.',
    ];
    foreach ($result['issues'] as $issue) {
        $location = $issue['row'] === null ? '' : ' radek ' . $issue['row'];
        if ($issue['field'] !== null) {
            $location .= ' [' . $issue['field'] . ']';
        }
        $lines[] = strtoupper($issue['severity']) . ' ' . $issue['code'] . $location . ': ' . $issue['message'];
    }
    return implode(PHP_EOL, $lines) . PHP_EOL;
}

$options = shopDryRunArguments($argv);
try {
    $parsed = ShoptetProductCsv::read($options['input']);
    $result = ShopCatalogContract::build($parsed);
} catch (ShoptetProductCsvException $exception) {
    $result = [
        'contract_version' => ShopCatalogContract::VERSION,
        'provisional' => true,
        'source' => [
            'filename' => basename($options['input']),
            'sha256' => '',
            'encoding' => 'unknown',
            'delimiter' => 'unknown',
            'rows' => 0,
            'columns' => 0,
        ],
        'summary' => [
            'products' => 0,
            'variants' => 0,
            'errors' => 1,
            'warnings' => 0,
            'contract_ready' => false,
            'database_writes' => 0,
        ],
        'normalizations' => [],
        'issues' => [[
            'severity' => 'error',
            'code' => 'invalid_csv_input',
            'message' => $exception->getMessage(),
            'row' => null,
            'field' => null,
        ]],
        'products' => [],
    ];
}

fwrite(STDOUT, $options['json'] ? shopDryRunJson($result) : shopDryRunSummary($result));
exit($result['summary']['errors'] > 0 ? SHOP_DRY_RUN_VALIDATION : 0);
