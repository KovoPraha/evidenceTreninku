<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/shoptet_product_input.php';
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
        "Pouziti: php bin/shoptet-products-dry-run.php --input=<lokalni-soubor.csv|xml> [--json]\n"
        . "Nastroj provadi pouze read-only dry-run. Neobsahuje zapis do DB ani apply rezim.\n"
    );
    exit(SHOP_DRY_RUN_USAGE);
}

/** @return array{input:string,json:bool} */
function shopDryRunArguments(array $arguments): array
{
    $input = '';
    $inputSeen = false;
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
            if ($inputSeen) {
                shopDryRunUsage('Parametr --input smi byt uveden pouze jednou.');
            }
            if (!isset($arguments[$index + 1]) || str_starts_with((string)$arguments[$index + 1], '--')) {
                shopDryRunUsage('Za --input chybi cesta.');
            }
            $input = (string)$arguments[++$index];
            $inputSeen = true;
            continue;
        }
        if (str_starts_with($argument, '--input=')) {
            if ($inputSeen) {
                shopDryRunUsage('Parametr --input smi byt uveden pouze jednou.');
            }
            $input = substr($argument, strlen('--input='));
            $inputSeen = true;
            continue;
        }
        shopDryRunUsage('Neznamy parametr: ' . $argument);
    }
    if ($input === '') {
        shopDryRunUsage('Parametr --input je povinny.');
    }
    if (str_contains($input, '://')
        || !in_array(strtolower(pathinfo($input, PATHINFO_EXTENSION)), ['csv', 'xml'], true)
        || is_link($input)
        || !is_file($input)
        || !is_readable($input)
    ) {
        shopDryRunUsage('Vstup musi byt citelny lokalni regularni .csv nebo .xml soubor.');
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
        'Format: ' . ($source['delimiter'] === 'xml' ? 'XML' : 'CSV (' . $source['delimiter'] . ')')
            . ' | kodovani: ' . $source['encoding'],
        'Produkty: ' . $summary['products'] . ' | varianty: ' . $summary['variants'],
        'Typy nabidky: ' . implode(', ', array_map(
            static fn (string $type, int $count): string => $type . '=' . $count,
            array_keys($summary['offer_type_counts']),
            array_values($summary['offer_type_counts'])
        )),
        'Rucni kontrola klasifikace: ' . $summary['manual_review_products'],
        'Blokatory: ' . $summary['errors'] . ' | varovani: ' . $summary['warnings'],
        'Stav kontraktu: ' . ($summary['contract_ready'] ? 'pripraven pro kontrolu' : 'vyzaduje opravu vstupu'),
        'Rezim: read-only staging | DB zapisy: ' . $summary['database_writes'],
    ];
    $displayedIssues = array_slice($result['issues'], 0, 50);
    foreach ($displayedIssues as $issue) {
        $location = $issue['row'] === null ? '' : ' radek ' . $issue['row'];
        if ($issue['field'] !== null) {
            $location .= ' [' . $issue['field'] . ']';
        }
        $lines[] = strtoupper($issue['severity']) . ' ' . $issue['code'] . $location . ': ' . $issue['message'];
    }
    $hiddenIssues = count($result['issues']) - count($displayedIssues);
    if ($hiddenIssues > 0) {
        $lines[] = '... dalsich ' . $hiddenIssues . ' problemu je dostupnych ve vystupu --json.';
    }
    return implode(PHP_EOL, $lines) . PHP_EOL;
}

// Omezené hostingové shelly blokují argumenty za jménem skriptu; bez argumentů
// lze proto vstup zadat proměnnými prostředí SHOPTET_INPUT a SHOPTET_JSON=1
// (typicky přes putenv bootstrap). CLI argumenty mají přednost.
if (count($argv) === 1) {
    $environmentInput = (string)getenv('SHOPTET_INPUT');
    if ($environmentInput !== '') {
        $argv[] = '--input=' . $environmentInput;
        if ((string)getenv('SHOPTET_JSON') === '1') {
            $argv[] = '--json';
        }
    }
}

$options = shopDryRunArguments($argv);
try {
    $parsed = ShoptetProductInput::read($options['input']);
    $result = ShopCatalogContract::build($parsed);
} catch (ShoptetProductCsvException | ShoptetProductXmlException $exception) {
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
            'offer_type_counts' => array_fill_keys(ShopOfferClassifier::TYPES, 0),
            'manual_review_products' => 0,
        ],
        'normalizations' => [],
        'issues' => [[
            'severity' => 'error',
            'code' => 'invalid_catalog_input',
            'message' => $exception->getMessage(),
            'row' => null,
            'field' => null,
        ]],
        'products' => [],
    ];
}

fwrite(STDOUT, $options['json'] ? shopDryRunJson($result) : shopDryRunSummary($result));
exit($result['summary']['errors'] > 0 ? SHOP_DRY_RUN_VALIDATION : 0);
