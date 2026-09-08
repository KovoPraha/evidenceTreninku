<?php
declare(strict_types=1);

require_once __DIR__ . '/deployment_release.php';
require_once __DIR__ . '/shop_bank_settings.php';

function uatReadinessTableExists(PDO $pdo, string $table): bool
{
    if (preg_match('/^[a-z0-9_]+$/D', $table) !== 1) return false;
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=? LIMIT 1");
    $statement->execute([$table]);
    return (bool)$statement->fetchColumn();
}

/** @param list<mixed> $parameters */
function uatReadinessCount(PDO $pdo, string $sql, array $parameters = []): int
{
    try {
        $statement = $pdo->prepare($sql);
        $statement->execute($parameters);
        return (int)$statement->fetchColumn();
    } catch (Throwable) {
        return -1;
    }
}

/** @return array{key:string,label:string,ok:bool,detail:string,scenarios:list<int>} */
function uatReadinessCheck(string $key, string $label, bool $ok, string $detail, array $scenarios): array
{
    return ['key' => $key, 'label' => $label, 'ok' => $ok, 'detail' => $detail, 'scenarios' => $scenarios];
}

/** @return array<string,mixed> */
function uatReadinessSnapshot(PDO $pdo, ?string $applicationRoot = null): array
{
    $release = deploymentReleaseInfo($applicationRoot);
    $parents = -1;
    if (uatReadinessTableExists($pdo, 'verejni_uzivatele')) {
        $parents = uatReadinessCount(
            $pdo,
            'SELECT COUNT(*) FROM verejni_uzivatele WHERE aktivni=1 AND email IN (?,?)',
            ['tester.karel@velocota.com', 'tester.petra@velocota.com']
        );
    }

    $children = -1;
    if (uatReadinessTableExists($pdo, 'child_access_accounts') && uatReadinessTableExists($pdo, 'sportovci')) {
        $children = uatReadinessCount(
            $pdo,
            "SELECT COUNT(DISTINCT a.id) FROM child_access_accounts a JOIN sportovci s ON s.id=a.sportovec_id "
            . "WHERE a.active=1 AND ((LOWER(s.jmeno) IN ('ema','adam') AND LOWER(s.prijmeni)='tester') "
            . "OR (LOWER(s.prijmeni) IN ('ema','adam') AND LOWER(s.jmeno)='tester'))"
        );
    }

    $staffId = 0;
    if (uatReadinessTableExists($pdo, 'treneri')) {
        try {
            $statement = $pdo->prepare(
                "SELECT id FROM treneri WHERE aktivni=1 AND (LOWER(email) IN (?,?) OR LOWER(jmeno) IN (?,?)) ORDER BY id LIMIT 1"
            );
            $statement->execute([
                'kis-superadmin-test@velocota.com', 'tester.spravce@velocota.com',
                'tester správce', 'kis testovací superadministrátor',
            ]);
            $staffId = (int)$statement->fetchColumn();
        } catch (Throwable) {
            $staffId = 0;
        }
    }
    $positions = $staffId > 0 && uatReadinessTableExists($pdo, 'staff_user_positions')
        ? uatReadinessCount($pdo, 'SELECT COUNT(*) FROM staff_user_positions WHERE trainer_id=?', [$staffId]) : -1;
    $superadmin = $staffId > 0 && uatReadinessTableExists($pdo, 'staff_superadmins')
        ? uatReadinessCount($pdo, 'SELECT COUNT(*) FROM staff_superadmins WHERE trainer_id=?', [$staffId]) === 1 : false;

    $fixtureCounts = [
        'goods' => -1, 'program' => -1, 'free_event' => -1, 'paid_event' => -1,
        'velodrome' => -1, 'lesson' => -1, 'training' => -1, 'calendar_event' => -1,
    ];
    if (uatReadinessTableExists($pdo, 'shop_products') && uatReadinessTableExists($pdo, 'shop_product_publications')) {
        $base = " FROM shop_products p JOIN shop_product_publications pub ON pub.product_id=p.id WHERE p.catalog_status='active' AND pub.status='active' AND pub.public_name LIKE 'TEST -%'";
        $fixtureCounts['goods'] = uatReadinessCount($pdo, "SELECT COUNT(*)$base AND p.offer_type='goods'");
        $fixtureCounts['program'] = uatReadinessCount($pdo, "SELECT COUNT(*)$base AND p.offer_type='program'");
    }
    if (uatReadinessTableExists($pdo, 'club_events') && uatReadinessTableExists($pdo, 'club_event_sessions')) {
        $eventBase = " FROM club_events e JOIN club_event_sessions s ON s.event_id=e.id WHERE e.name LIKE 'TEST -%' AND e.status='open' AND e.visibility='public' AND s.status='scheduled' AND s.ends_at>=CURRENT_TIMESTAMP";
        $fixtureCounts['free_event'] = uatReadinessCount($pdo, "SELECT COUNT(DISTINCT e.id)$eventBase AND e.participant_fee_minor=0");
        $fixtureCounts['paid_event'] = uatReadinessCount($pdo, "SELECT COUNT(DISTINCT e.id)$eventBase AND e.participant_fee_minor>0");
        $fixtureCounts['calendar_event'] = uatReadinessCount($pdo, "SELECT COUNT(DISTINCT e.id)$eventBase");
    }
    if (uatReadinessTableExists($pdo, 'individualni_lekce') && uatReadinessTableExists($pdo, 'sportovist')) {
        $lessonBase = " FROM individualni_lekce il JOIN sportovist s ON s.id=il.sportoviste_id WHERE il.nazev LIKE 'TEST -%' AND il.stav='aktivni' AND il.datum>=CURRENT_DATE AND s.je_verejne=1 AND s.aktivni=1";
        $fixtureCounts['velodrome'] = uatReadinessCount($pdo, "SELECT COUNT(*)$lessonBase AND s.kod='velodrom'");
        $fixtureCounts['lesson'] = uatReadinessCount($pdo, "SELECT COUNT(*)$lessonBase AND s.kod<>'velodrom'");
    }
    if (uatReadinessTableExists($pdo, 'planovane_treninky')) {
        $fixtureCounts['training'] = uatReadinessCount($pdo, "SELECT COUNT(*) FROM planovane_treninky WHERE nazev LIKE 'TEST -%' AND je_verejny=1 AND stav='planovany' AND datum>=CURRENT_DATE");
    }

    $technicalPublic = -1;
    if (uatReadinessTableExists($pdo, 'individualni_lekce') && uatReadinessTableExists($pdo, 'sportovist') && uatReadinessTableExists($pdo, 'treneri')) {
        $technicalPublic = uatReadinessCount(
            $pdo,
            "SELECT COUNT(*) FROM individualni_lekce il JOIN sportovist s ON s.id=il.sportoviste_id JOIN treneri t ON t.id=il.trener_id "
            . "WHERE il.stav='aktivni' AND il.datum>=CURRENT_DATE AND s.je_verejne=1 AND il.nazev NOT LIKE 'TEST -%' "
            . "AND (LOWER(t.email) IN ('kis-superadmin-test@velocota.com','tester.spravce@velocota.com') OR LOWER(t.email) LIKE 'kis-e2e-%' OR LOWER(t.jmeno) LIKE '%testovací administrátor%')"
        );
    }

    $secret = defined('STRIPE_SECRET_KEY') ? (string)STRIPE_SECRET_KEY : '';
    $publishable = defined('STRIPE_PUBLISHABLE_KEY') ? (string)STRIPE_PUBLISHABLE_KEY : '';
    $webhook = defined('STRIPE_WEBHOOK_SECRET') ? (string)STRIPE_WEBHOOK_SECRET : '';
    $stripe = [
        'enabled' => defined('STRIPE_ENABLED') && STRIPE_ENABLED === true,
        'test_pair' => str_starts_with($secret, 'sk_test_') && str_starts_with($publishable, 'pk_test_'),
        'live_pair' => str_starts_with($secret, 'sk_live_') || str_starts_with($publishable, 'pk_live_'),
        'webhook' => str_starts_with($webhook, 'whsec_'),
    ];
    try {
        $bank = shopBankSettingsResolve($pdo);
        $bankReady = $bank['settings'] !== null && $bank['database_error'] === '' && !$bank['conflict'];
    } catch (Throwable) {
        $bankReady = false;
    }
    $queueAvailable = uatReadinessTableExists($pdo, 'club_event_notifications');
    $queueFailed = $queueAvailable
        ? uatReadinessCount($pdo, "SELECT COUNT(*) FROM club_event_notifications WHERE status='failed'") : -1;

    return [
        'release' => $release,
        'parents' => $parents,
        'children' => $children,
        'staff_id' => $staffId,
        'positions' => $positions,
        'superadmin' => $superadmin,
        'fixtures' => $fixtureCounts,
        'technical_public_without_prefix' => $technicalPublic,
        'stripe' => $stripe,
        'bank_ready' => $bankReady,
        'inbox_ready' => defined('KIS_UAT_INBOX_READY') && KIS_UAT_INBOX_READY === true,
        'bank_reconciliation_ready' => defined('KIS_UAT_BANK_RECONCILIATION_READY') && KIS_UAT_BANK_RECONCILIATION_READY === true,
        'queue_available' => $queueAvailable,
        'queue_failed' => $queueFailed,
        'owner' => defined('KIS_UAT_OWNER') ? trim((string)KIS_UAT_OWNER) : '',
        'window_end' => defined('KIS_UAT_WINDOW_END') ? trim((string)KIS_UAT_WINDOW_END) : '',
    ];
}

/** @param array<string,mixed> $snapshot @return array{ready:bool,generated_at:string,checks:list<array<string,mixed>>,release:array<string,mixed>} */
function uatReadinessEvaluate(array $snapshot, ?DateTimeImmutable $now = null): array
{
    $now ??= new DateTimeImmutable('now', new DateTimeZone('Europe/Prague'));
    $release = (array)($snapshot['release'] ?? []);
    $fixtures = (array)($snapshot['fixtures'] ?? []);
    $stripe = (array)($snapshot['stripe'] ?? []);
    $windowEnd = trim((string)($snapshot['window_end'] ?? ''));
    try {
        $window = $windowEnd === '' ? null : new DateTimeImmutable($windowEnd, new DateTimeZone('Europe/Prague'));
    } catch (Throwable) {
        $window = null;
    }
    $fixturesReady = $fixtures !== [];
    foreach (['goods','program','free_event','paid_event','velodrome','lesson','training','calendar_event'] as $key) {
        if ((int)($fixtures[$key] ?? -1) < 1) $fixturesReady = false;
    }
    $checks = [
        uatReadinessCheck('release', 'Schválený nasazený release', (bool)($release['uat_approved'] ?? false) && preg_match('/^[a-f0-9]{40}$/D', (string)($release['sha'] ?? '')) === 1, 'SHA ' . ((string)($release['sha'] ?? '') ?: 'nezjištěno') . '; deploy ' . ((string)($release['deployed_at'] ?? '') ?: 'nezjištěn'), range(1,20)),
        uatReadinessCheck('parents', 'Tester Karel a Tester Petra', (int)($snapshot['parents'] ?? -1) === 2, 'Aktivní rodičovské účty: ' . (int)($snapshot['parents'] ?? -1) . ' / 2.', [1,2,3,5,6,7,8,9,11,13,14,15,19,20]),
        uatReadinessCheck('children', 'Tester Ema a Tester Adam', (int)($snapshot['children'] ?? -1) === 2, 'Aktivní dětské přístupy: ' . (int)($snapshot['children'] ?? -1) . ' / 2.', [2,3,5,6,9,10,11,12,15,19,20]),
        uatReadinessCheck('staff', 'Tester Správce a osm pozic', (int)($snapshot['staff_id'] ?? 0) > 0 && (int)($snapshot['positions'] ?? -1) === 8 && ($snapshot['superadmin'] ?? false) === true, 'Účet: ' . ((int)($snapshot['staff_id'] ?? 0) > 0 ? 'aktivní' : 'chybí') . '; pozice ' . (int)($snapshot['positions'] ?? -1) . ' / 8; superadmin ' . (($snapshot['superadmin'] ?? false) ? 'ano' : 'ne') . '.', [3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,20]),
        uatReadinessCheck('fixtures', 'Časově omezená TEST data', $fixturesReady, 'Počty: zboží ' . (int)($fixtures['goods'] ?? -1) . ', kroužek ' . (int)($fixtures['program'] ?? -1) . ', akce zdarma/placená ' . (int)($fixtures['free_event'] ?? -1) . '/' . (int)($fixtures['paid_event'] ?? -1) . ', velodrom ' . (int)($fixtures['velodrome'] ?? -1) . ', lekce ' . (int)($fixtures['lesson'] ?? -1) . ', trénink ' . (int)($fixtures['training'] ?? -1) . ', kalendář ' . (int)($fixtures['calendar_event'] ?? -1) . '.', [3,5,6,7,8,9,10,11,19,20]),
        uatReadinessCheck('technical_owner', 'Žádná anonymní technická nabídka', (int)($snapshot['technical_public_without_prefix'] ?? -1) === 0, 'Veřejné položky technických účtů bez prefixu TEST -: ' . (int)($snapshot['technical_public_without_prefix'] ?? -1) . '.', [9,19,20]),
        uatReadinessCheck('stripe', 'Stripe pouze v sandboxu', ($stripe['enabled'] ?? false) && ($stripe['test_pair'] ?? false) && !($stripe['live_pair'] ?? true) && ($stripe['webhook'] ?? false), 'Zapnuto ' . (($stripe['enabled'] ?? false) ? 'ano' : 'ne') . '; testovací pár ' . (($stripe['test_pair'] ?? false) ? 'ano' : 'ne') . '; live klíč ' . (($stripe['live_pair'] ?? false) ? 'ANO — STOP' : 'ne') . '; webhook ' . (($stripe['webhook'] ?? false) ? 'ano' : 'ne') . '.', [4]),
        uatReadinessCheck('bank', 'Banka a kontrola skutečné úhrady', ($snapshot['bank_ready'] ?? false) === true && ($snapshot['bank_reconciliation_ready'] ?? false) === true, 'Checkout účet ' . (($snapshot['bank_ready'] ?? false) ? 'platný' : 'nepřipravený') . '; určený kontrolor banky ' . (($snapshot['bank_reconciliation_ready'] ?? false) ? 'ano' : 'ne') . '.', [3,6,7,8,13,20]),
        uatReadinessCheck('email', 'Inbox a fronta zpráv', ($snapshot['inbox_ready'] ?? false) === true && ($snapshot['queue_available'] ?? false) === true && (int)($snapshot['queue_failed'] ?? -1) === 0, 'Testovací inbox ' . (($snapshot['inbox_ready'] ?? false) ? 'potvrzen' : 'nepotvrzen') . '; selhané zprávy ' . (int)($snapshot['queue_failed'] ?? -1) . '.', [1,2,3,5,6,7,8,9,11,13,14,20]),
        uatReadinessCheck('cleanup', 'Vlastník a konec testovacího okna', trim((string)($snapshot['owner'] ?? '')) !== '' && $window instanceof DateTimeImmutable && $window > $now, 'Vlastník ' . (trim((string)($snapshot['owner'] ?? '')) ?: 'neurčen') . '; konec ' . ($window?->format('Y-m-d H:i') ?? 'neurčen/neplatný') . '.', [20]),
    ];
    return [
        'ready' => !in_array(false, array_column($checks, 'ok'), true),
        'generated_at' => $now->format('Y-m-d H:i:s'),
        'checks' => $checks,
        'release' => $release,
    ];
}

/** @return array{ready:bool,generated_at:string,checks:list<array<string,mixed>>,release:array<string,mixed>} */
function uatReadiness(PDO $pdo, ?string $applicationRoot = null): array
{
    return uatReadinessEvaluate(uatReadinessSnapshot($pdo, $applicationRoot));
}
