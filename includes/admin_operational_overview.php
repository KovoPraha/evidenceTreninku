<?php
declare(strict_types=1);

/**
 * Read-only M3.4 overview. It deliberately links to existing operational pages
 * instead of duplicating their write, confirmation, CSRF or audit logic.
 */

function adminOperationalTableExists(PDO $pdo, string $table): bool
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

function adminOperationalColumnExists(PDO $pdo, string $table, string $column): bool
{
    if (preg_match('/^[a-z0-9_]+$/D', $table) !== 1 || preg_match('/^[a-z0-9_]+$/D', $column) !== 1) return false;
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1');
        $statement->execute([$table, $column]);
        return (bool)$statement->fetchColumn();
    }
    foreach ($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ((string)$row['name'] === $column) return true;
    }
    return false;
}

/** @param list<string> $tables */
function adminOperationalHasTables(PDO $pdo, array $tables): bool
{
    foreach ($tables as $table) if (!adminOperationalTableExists($pdo, $table)) return false;
    return true;
}

/** @return array{key:string,label:string,description:string,icon:string,items:list<array<string,mixed>>} */
function adminOperationalSection(string $key, string $label, string $description, string $icon): array
{
    return compact('key', 'label', 'description', 'icon') + ['items' => []];
}

/** @param array<string,mixed> $section */
function adminOperationalAdd(array &$section, string $key, string $title, int $count, string $detail, string $href, string $severity = 'warning'): void
{
    if ($count < 1) return;
    $section['items'][] = compact('key', 'title', 'count', 'detail', 'href', 'severity');
}

/**
 * @return array{
 *   generated_at:string,
 *   sections:array<string,array<string,mixed>>,
 *   unavailable:list<string>,
 *   signal_count:int
 * }
 */
function adminOperationalOverview(PDO $pdo, ?DateTimeImmutable $now = null): array
{
    $now ??= new DateTimeImmutable('now', new DateTimeZone('Europe/Prague'));
    $sections = [
        'payments' => adminOperationalSection('payments', 'Platby a vratky', 'Peníze, které čekají na ověření nebo zásah.', 'cash-coin'),
        'capacity' => adminOperationalSection('capacity', 'Kapacity', 'Naplněné klubové akce a období kroužků.', 'people'),
        'registrations' => adminOperationalSection('registrations', 'Přihlášky a schválení', 'Čekací listiny, platbou držená místa a žádosti o propojení.', 'person-check'),
        'exceptions' => adminOperationalSection('exceptions', 'Provozní výjimky', 'Fronty a importy, které nelze bezpečně dokončit automaticky.', 'exclamation-triangle'),
    ];
    $unavailable = [];

    if (adminOperationalHasTables($pdo, ['club_member_charges'])) {
        $statement = $pdo->prepare("SELECT COUNT(*) FROM club_member_charges WHERE status='pending' AND due_on IS NOT NULL AND due_on<?");
        $statement->execute([$now->format('Y-m-d')]);
        adminOperationalAdd($sections['payments'], 'overdue_member_charges', 'Členské předpisy po splatnosti', (int)$statement->fetchColumn(), 'Ověřte platbu nebo navazující připomínku.', 'member_charges_admin.php?status=pending', 'danger');
    } else $unavailable[] = 'členské předpisy';

    if (adminOperationalHasTables($pdo, ['shop_orders', 'payments'])) {
        $refunds = (int)$pdo->query("SELECT COUNT(*) FROM shop_orders o JOIN payments p ON p.payable_type='shop_order' AND p.payable_id=o.id WHERE o.status='cancelled' AND o.payment_status='refund_required' AND p.status='refund_required'")->fetchColumn();
        adminOperationalAdd($sections['payments'], 'refunds_required', 'Vratky čekající na odeslání', $refunds, 'Peníze musí být vráceny a následně auditovaně potvrzeny.', 'eshop_orders_admin.php', 'danger');

        if (adminOperationalColumnExists($pdo, 'shop_orders', 'payment_expires_at')) {
            $statement = $pdo->prepare("SELECT COUNT(*) FROM shop_orders o JOIN payments p ON p.payable_type='shop_order' AND p.payable_id=o.id WHERE o.status='placed' AND o.payment_status='pending' AND p.status='pending' AND COALESCE(o.payment_expires_at,p.due_at)<=?");
            $statement->execute([$now->format('Y-m-d H:i:s')]);
            adminOperationalAdd($sections['payments'], 'expired_shop_orders', 'Nezaplacené objednávky po limitu', (int)$statement->fetchColumn(), 'Spusťte bezpečný náhled expirace a teprve potom potvrzené zpracování.', 'eshop_order_expiry_admin.php', 'warning');
        } else $unavailable[] = 'expirace objednávek';
    } else $unavailable[] = 'objednávky a platby';

    if (adminOperationalHasTables($pdo, ['fio_account_movements'])) {
        $proposals = (int)$pdo->query("SELECT COUNT(*) FROM fio_account_movements WHERE match_status='proposed_exact'")->fetchColumn();
        adminOperationalAdd($sections['payments'], 'fio_proposals', 'Přesné návrhy Fio k ručnímu ověření', $proposals, 'Návrh sám platbu nemění; potvrzení zůstává ruční.', 'eshop_fio_admin.php', 'info');
    } else $unavailable[] = 'Fio pohyby';

    if (adminOperationalHasTables($pdo, ['club_events', 'club_event_sessions', 'club_event_registrations'])) {
        $capacitySql = "SELECT COUNT(*) FROM (SELECT e.id,COALESCE((SELECT MIN(s.capacity_override) FROM club_event_sessions s WHERE s.event_id=e.id AND s.status='scheduled' AND s.capacity_override IS NOT NULL),e.capacity) AS effective_capacity,(SELECT COUNT(*) FROM club_event_registrations r WHERE r.event_id=e.id AND r.status IN ('confirmed','payment_pending')) AS occupied,(SELECT COUNT(*) FROM club_event_registrations r WHERE r.event_id=e.id AND r.status='waitlisted') AS waitlisted FROM club_events e WHERE e.status='open') q WHERE q.occupied>=q.effective_capacity OR q.waitlisted>0";
        adminOperationalAdd($sections['capacity'], 'full_events', 'Naplněné klubové akce', (int)$pdo->query($capacitySql)->fetchColumn(), 'Zkontrolujte kapacitu a pořadí čekací listiny u konkrétní akce.', 'eshop_events_admin.php', 'warning');

        $waitlisted = (int)$pdo->query("SELECT COUNT(*) FROM club_event_registrations WHERE status='waitlisted'")->fetchColumn();
        adminOperationalAdd($sections['registrations'], 'waitlisted_registrations', 'Lidé na čekacích listinách', $waitlisted, 'Po uvolnění místa se používá existující transakční pořadí.', 'eshop_events_admin.php', 'warning');
        $paymentPending = (int)$pdo->query("SELECT COUNT(*) FROM club_event_registrations WHERE status='payment_pending'")->fetchColumn();
        adminOperationalAdd($sections['registrations'], 'payment_pending_registrations', 'Místa držená čekající platbou', $paymentPending, 'Přihláška drží kapacitu do úhrady nebo bezpečné expirace objednávky.', 'eshop_orders_admin.php', 'info');
    } else $unavailable[] = 'klubové akce a přihlášky';

    if (adminOperationalHasTables($pdo, ['club_program_offers', 'club_program_enrollments'])) {
        $fullOffers = (int)$pdo->query("SELECT COUNT(*) FROM club_program_offers o WHERE o.status='active' AND o.capacity IS NOT NULL AND (SELECT COUNT(*) FROM club_program_enrollments e WHERE e.offer_id=o.id AND e.status='active')>=o.capacity")->fetchColumn();
        adminOperationalAdd($sections['capacity'], 'full_program_offers', 'Naplněná období kroužků', $fullOffers, 'Další nákup musí zůstat zablokovaný, dokud se kapacita neuvolní.', 'club_programs_admin.php', 'warning');
    } else $unavailable[] = 'období kroužků';

    if (adminOperationalHasTables($pdo, ['account_person_claim_requests'])) {
        $claims = (int)$pdo->query("SELECT COUNT(*) FROM account_person_claim_requests WHERE status='pending'")->fetchColumn();
        adminOperationalAdd($sections['registrations'], 'pending_person_claims', 'Žádosti o propojení osoby', $claims, 'Schválení nebo zamítnutí proveďte v existující správě identit.', 'eshop_identity_admin.php', 'warning');
    } else $unavailable[] = 'žádosti o propojení osoby';

    if (adminOperationalHasTables($pdo, ['member_charge_reminders'])) {
        $failed = (int)$pdo->query("SELECT COUNT(*) FROM member_charge_reminders WHERE status='failed'")->fetchColumn();
        adminOperationalAdd($sections['exceptions'], 'failed_charge_reminders', 'Selhané připomínky členských plateb', $failed, 'Ruční opakování pouze vrátí zprávu do auditované fronty.', 'member_charge_reminders_admin.php?status=failed', 'danger');
    } else $unavailable[] = 'připomínky členských plateb';

    if (adminOperationalHasTables($pdo, ['club_event_notifications'])) {
        $failed = (int)$pdo->query("SELECT COUNT(*) FROM club_event_notifications WHERE status='failed'")->fetchColumn();
        adminOperationalAdd($sections['exceptions'], 'failed_event_notifications', 'Selhaná oznámení z čekací listiny', $failed, 'Zkontrolujte chybu a případně proveďte auditované ruční opakování.', 'eshop_notifications_admin.php?status=failed', 'danger');
    } else $unavailable[] = 'oznámení klubových akcí';

    if (adminOperationalHasTables($pdo, ['fio_account_movements'])) {
        $review = (int)$pdo->query("SELECT COUNT(*) FROM fio_account_movements WHERE match_status LIKE 'review_%'")->fetchColumn();
        adminOperationalAdd($sections['exceptions'], 'fio_review', 'Fio pohyby vyžadující kontrolu', $review, 'Chybí jednoznačná shoda VS, částky, měny nebo stavu.', 'eshop_fio_admin.php', 'warning');
    }

    if (adminOperationalHasTables($pdo, ['kis_import_runs', 'kis_import_matches'])) {
        $kis = (int)$pdo->query("SELECT COUNT(*) FROM kis_import_matches WHERE run_id=(SELECT MAX(id) FROM kis_import_runs) AND match_status IN ('ambiguous','conflict')")->fetchColumn();
        adminOperationalAdd($sections['exceptions'], 'kis_conflicts', 'Konflikty posledního KIS importu', $kis, 'Nejednoznačné osoby musí být vyřešeny před bezpečným přenosem.', 'kis_sync_center.php', 'warning');
    } else $unavailable[] = 'KIS importní kontrola';

    $signalCount = 0;
    foreach ($sections as &$section) {
        usort($section['items'], static fn (array $a, array $b): int => ['danger' => 0, 'warning' => 1, 'info' => 2][$a['severity']] <=> ['danger' => 0, 'warning' => 1, 'info' => 2][$b['severity']]);
        foreach ($section['items'] as $item) $signalCount += (int)$item['count'];
    }
    unset($section);

    return [
        'generated_at' => $now->format('Y-m-d H:i:s'),
        'sections' => $sections,
        'unavailable' => array_values(array_unique($unavailable)),
        'signal_count' => $signalCount,
    ];
}
