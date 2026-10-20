<?php
declare(strict_types=1);

/**
 * Fixed, non-personal catalog inventory for the Pumptrack price guard.
 * The transaction is read-only even though the application DB user can write.
 */
final class PumptrackCatalogDiagnosticFailure extends RuntimeException
{
    public function __construct(
        public readonly string $stage,
        public readonly string $exceptionClass,
        public readonly ?string $sqlState,
        Throwable $previous
    ) {
        parent::__construct(match ($stage) {
            'target' => 'Produkční cíl nebo způsob spuštění neodpovídá diagnostice.',
            'config' => 'Produkční konfiguraci nelze bezpečně načíst nebo ověřit.',
            'connection' => 'Připojení k databázi selhalo.',
            'read_only_transaction' => 'Zahájení transakce pouze pro čtení selhalo.',
            'variants_query' => 'Pevný dotaz na shop_variants a shop_products selhal; zkontrolujte schéma.',
            'canonical_query' => 'Pevný dotaz na kanonické nabídky Pumptracku selhal; zkontrolujte schéma.',
            'rollback' => 'Ukončení transakce pouze pro čtení selhalo.',
            default => 'Serializace výsledku diagnostiky selhala.',
        }, 0, $previous);
    }
}

function diagnosePumptrackCatalogMain(): void
{
    $stage = 'target';
    $pdo = null;
    $readOnlyStarted = false;
    try {
        if (PHP_SAPI !== 'cli') {
            throw new RuntimeException('Pouze CLI.');
        }
        $root = realpath((string) getenv('APP_ROOT'));
        $host = strtolower(trim((string) getenv('APP_HOST')));
        if ($root === false || $host !== 'kis.kovopraha.cz' || !is_file($root . '/config.php')) {
            throw new RuntimeException('Cíl musí být produkční instalace kis.kovopraha.cz.');
        }

        $stage = 'config';
        $_SERVER['HTTP_HOST'] = $host;
        $_SERVER['SERVER_NAME'] = $host;
        require $root . '/config.php';
        if (!defined('JE_LOKALNE') || JE_LOKALNE !== false) {
            throw new RuntimeException('Diagnostika je určena pouze pro produkci.');
        }
        foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $constant) {
            if (!defined($constant)) {
                throw new RuntimeException('Neúplná konfigurace databáze.');
            }
        }

        $stage = 'connection';
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
        $stage = 'read_only_transaction';
        $pdo->exec('START TRANSACTION READ ONLY');
        $readOnlyStarted = true;
        $stage = 'variants_query';
        $variants = $pdo->query(
            'SELECT v.id AS variant_id,v.product_id,v.sku,v.origin AS variant_origin,'
            . 'v.catalog_status AS variant_status,v.price_mode,v.amount_minor,'
            . 'v.compare_at_amount_minor,v.currency,v.unit_code,v.visible,'
            . 'v.stock_quantity_decimal,p.name AS product_name,p.origin AS product_origin,'
            . 'p.catalog_status AS product_status,p.offer_type '
            . 'FROM shop_variants v JOIN shop_products p ON p.id=v.product_id '
            . 'WHERE v.id IN (893,941) ORDER BY v.id'
        )->fetchAll();
        $stage = 'canonical_query';
        $canonical = $pdo->query(
            'SELECT o.id AS offer_id,o.purchase_option,o.status AS offer_status,'
            . 'v.id AS variant_id,v.product_id,v.sku,v.origin AS variant_origin,'
            . 'v.catalog_status AS variant_status,v.price_mode,v.amount_minor,'
            . 'v.compare_at_amount_minor,v.currency,v.unit_code,v.visible,'
            . 'v.stock_quantity_decimal,sp.name AS product_name,sp.origin AS product_origin,'
            . 'sp.catalog_status AS product_status,sp.offer_type '
            . 'FROM club_program_offers o '
            . 'JOIN club_programs p ON p.id=o.program_id '
            . 'JOIN shop_variants v ON v.id=o.variant_id '
            . 'JOIN shop_products sp ON sp.id=o.product_id '
            . "WHERE p.code='KROUZKY-2627-PUMPTRACK' AND sp.origin='manual' "
            . "AND o.purchase_option IN ('first_half','full_year') "
            . 'ORDER BY o.purchase_option,o.id'
        )->fetchAll();
        $stage = 'rollback';
        $pdo->exec('ROLLBACK');
        $readOnlyStarted = false;
        $stage = 'serialization';
        echo json_encode(
            ['variants' => $variants, 'canonical_offers' => $canonical],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ) . PHP_EOL;
    } catch (Throwable $error) {
        if ($readOnlyStarted && $pdo !== null) {
            try {
                $pdo->exec('ROLLBACK');
            } catch (Throwable) {
                // Keep the original, categorized diagnostic failure.
            }
        }
        $sqlState = $error instanceof PDOException && preg_match('/^[A-Z0-9]{5}$/', (string) $error->getCode())
            ? (string) $error->getCode() : null;
        throw new PumptrackCatalogDiagnosticFailure($stage, get_class($error), $sqlState, $error);
    }
}

function diagnosePumptrackCatalogCli(): void
{
    try {
        diagnosePumptrackCatalogMain();
    } catch (PumptrackCatalogDiagnosticFailure $error) {
        fwrite(STDERR, json_encode([
            'error' => 'pumptrack_diagnostic_failed',
            'stage' => $error->stage,
            'exception_class' => $error->exceptionClass,
            'sqlstate' => $error->sqlState,
            'message' => $error->getMessage(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
        exit(1);
    }
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    diagnosePumptrackCatalogCli();
}
