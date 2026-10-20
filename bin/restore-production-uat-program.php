<?php
declare(strict_types=1);

/** Reopens only the dedicated 100 CZK fixture deactivated by production-test-cleanup.php. */
final class KisUatFixtureRestoreFailure extends RuntimeException
{
    public function __construct(public readonly string $category)
    {
        parent::__construct($category);
    }
}

/** @return array{product_id:int,variant_id:int,status:string} */
function kisUatFixtureRestoreIdentity(PDO $pdo): array
{
    $query = $pdo->prepare("SELECT p.id AS product_id,p.name,p.origin,p.external_product_key,p.offer_type,p.visibility,p.catalog_status AS product_status,"
        . "v.id AS variant_id,v.origin AS variant_origin,v.visible,v.price_mode,v.amount_minor,v.currency,v.catalog_status AS variant_status,"
        . "pub.status AS publication_status,pub.public_name,pub.public_summary,"
        . "o.code AS offer_code,o.status AS offer_status,cp.code AS program_code,t.code AS team_code "
        . "FROM shop_variants v JOIN shop_products p ON p.id=v.product_id "
        . "LEFT JOIN shop_product_publications pub ON pub.product_id=p.id "
        . "LEFT JOIN club_program_offers o ON o.product_id=p.id AND o.variant_id=v.id "
        . "LEFT JOIN club_programs cp ON cp.id=o.program_id LEFT JOIN club_teams t ON t.id=o.team_id "
        . "WHERE v.sku='KP-TEST-UAT-KROUZEK'");
    $query->execute();
    $rows = $query->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) !== 1) throw new KisUatFixtureRestoreFailure('fixture_not_unique');
    $row = $rows[0];
    $variantCount = $pdo->prepare('SELECT COUNT(*) FROM shop_variants WHERE product_id=?');
    $variantCount->execute([(int)$row['product_id']]);
    if ((int)$variantCount->fetchColumn() !== 1) throw new KisUatFixtureRestoreFailure('fixture_extra_variant');
    if ((string)$row['name'] !== 'TEST - Cyklistický kroužek'
        || (string)$row['origin'] !== 'manual'
        || preg_match('/^manual:[a-f0-9]{32}$/D',(string)$row['external_product_key']) !== 1
        || (string)$row['offer_type'] !== 'program'
        || (string)$row['visibility'] !== 'visible'
        || (string)$row['public_name'] !== 'TEST - Cyklistický kroužek'
        || (string)$row['public_summary'] !== 'Dočasná produkční UAT nabídka. Po skončení testu bude deaktivována.'
    ) throw new KisUatFixtureRestoreFailure('fixture_product_identity');
    if ((string)$row['variant_origin'] !== 'manual' || (int)$row['visible'] !== 1 || (string)$row['price_mode'] !== 'fixed'
        || (int)$row['amount_minor'] !== 10000 || (string)$row['currency'] !== 'CZK') {
        throw new KisUatFixtureRestoreFailure('fixture_variant_identity');
    }
    if ((string)$row['offer_code'] !== 'TEST-UAT-KROUZEK-2026'
        || (string)$row['offer_status'] !== 'active'
        || (string)$row['program_code'] !== 'TEST-UAT-KROUZEK'
        || (string)$row['team_code'] !== 'TEST-UAT-DETI') {
        throw new KisUatFixtureRestoreFailure('fixture_offer_identity');
    }
    $statuses = [(string)$row['product_status'],(string)$row['publication_status'],(string)$row['variant_status']];
    if ($statuses !== ['inactive','inactive','inactive'] && $statuses !== ['active','active','active']) {
        throw new KisUatFixtureRestoreFailure('fixture_status_mixed');
    }
    return ['product_id'=>(int)$row['product_id'],'variant_id'=>(int)$row['variant_id'],'status'=>$statuses[0]];
}

/** @return array{ok:bool,mode:string,product_id:int,variant_id:int,before:string,after:string,changed:bool} */
function kisUatFixtureRestore(PDO $pdo, bool $apply): array
{
    $pdo->beginTransaction();
    try {
        $identity = kisUatFixtureRestoreIdentity($pdo);
        $before = $identity['status'];
        if ($apply && $before === 'inactive') {
            $actor = $pdo->prepare("SELECT id FROM treneri WHERE LOWER(TRIM(email))='tester.spravce@velocota.com' AND jmeno='Tester Správce' AND role='admin' AND aktivni=1");
            $actor->execute();
            $actors = $actor->fetchAll(PDO::FETCH_COLUMN);
            if (count($actors) !== 1) throw new KisUatFixtureRestoreFailure('fixture_actor_identity');
            shopCatalogPublicationActivateInTransaction(
                $pdo,$identity['product_id'],(int)$actors[0],
                'TEST - Cyklistický kroužek',
                'Dočasná produkční UAT nabídka. Po skončení testu bude deaktivována.',
                'Obnova výhradně 100 Kč TEST programu pro ověření rodičovské objednávky.',true
            );
            $after = kisUatFixtureRestoreIdentity($pdo);
            if ($after['status'] !== 'active' || $after['product_id'] !== $identity['product_id']
                || $after['variant_id'] !== $identity['variant_id']) {
                throw new KisUatFixtureRestoreFailure('fixture_postcondition');
            }
        }
        $apply ? $pdo->commit() : $pdo->rollBack();
        return [
            'ok'=>true,'mode'=>$apply?'apply':'dry-run',
            'product_id'=>$identity['product_id'],'variant_id'=>$identity['variant_id'],
            'before'=>$before,'after'=>$apply?'active':$before,'changed'=>$apply && $before==='inactive',
        ];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
}

function kisUatFixtureRestoreMain(): void
{
    if (PHP_SAPI !== 'cli') throw new KisUatFixtureRestoreFailure('cli_only');
    $root = realpath((string)getenv('APP_ROOT'));
    if ($root === false || strtolower(trim((string)getenv('APP_HOST'))) !== 'kis.kovopraha.cz'
        || !is_file($root . '/config.php')) throw new KisUatFixtureRestoreFailure('production_target');
    $mode = (string)getenv('KIS_UAT_FIXTURE_RESTORE_MODE');
    if (!in_array($mode,['dry-run','apply'],true)) throw new KisUatFixtureRestoreFailure('mode');
    if ($mode === 'apply' && (string)getenv('KIS_UAT_FIXTURE_RESTORE_CONFIRM') !== 'OBNOVIT-TEST-KROUZEK-100') {
        throw new KisUatFixtureRestoreFailure('confirmation');
    }
    $_SERVER['HTTP_HOST'] = 'kis.kovopraha.cz';
    $_SERVER['SERVER_NAME'] = 'kis.kovopraha.cz';
    require_once $root . '/config.php';
    if (!defined('JE_LOKALNE') || JE_LOKALNE !== false) throw new KisUatFixtureRestoreFailure('production_mode');
    require_once $root . '/includes/shop_catalog_publication.php';
    foreach (['DB_HOST','DB_NAME','DB_USER','DB_PASS'] as $constant) {
        if (!defined($constant)) throw new KisUatFixtureRestoreFailure('database_configuration');
    }
    $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES=>false,
    ]);
    echo json_encode(kisUatFixtureRestore($pdo,$mode==='apply'),JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

function kisUatFixtureRestoreEntry(): void
{
    try { kisUatFixtureRestoreMain(); }
    catch (KisUatFixtureRestoreFailure $exception) {
        fwrite(STDERR,'uat_fixture_restore_failed category='.$exception->category.PHP_EOL);
        exit(1);
    } catch (Throwable $exception) {
        fwrite(STDERR,'uat_fixture_restore_failed category=unexpected_error'.PHP_EOL);
        exit(1);
    }
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) kisUatFixtureRestoreEntry();
