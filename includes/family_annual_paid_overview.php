<?php
declare(strict_types=1);

require_once __DIR__ . '/family_portal.php';

final class FamilyAnnualPaidOverviewException extends RuntimeException
{
}

function familyAnnualPaidOverviewYear(mixed $value, ?DateTimeImmutable $today = null): int
{
    $today ??= new DateTimeImmutable('today', new DateTimeZone('Europe/Prague'));
    $currentYear = (int)$today->format('Y');
    if ($value === null || $value === '') {
        return $currentYear;
    }
    if (!is_scalar($value) || preg_match('/^\d{4}$/D', (string)$value) !== 1) {
        throw new InvalidArgumentException('Vybraný rok není platný.');
    }
    $year = (int)$value;
    if ($year < 2000 || $year > $currentYear) {
        throw new InvalidArgumentException('Lze zobrazit pouze uzavřený nebo právě probíhající rok od roku 2000.');
    }
    return $year;
}

/** @param list<array<string,mixed>> $rows @return array<string,int> */
function familyAnnualPaidOverviewTotals(array $rows): array
{
    $totals = [];
    foreach ($rows as $row) {
        $currency = strtoupper((string)($row['currency'] ?? ''));
        if (preg_match('/^[A-Z]{3}$/D', $currency) !== 1) {
            continue;
        }
        $totals[$currency] = ($totals[$currency] ?? 0) + (int)($row['amount_minor'] ?? 0);
    }
    ksort($totals);
    return $totals;
}

/** @param array<string,int> $totals */
function familyAnnualPaidOverviewTotalsLabel(array $totals): string
{
    if ($totals === []) {
        return '0,00 CZK';
    }
    $labels = [];
    foreach ($totals as $currency => $amountMinor) {
        $labels[] = number_format($amountMinor / 100, 2, ',', ' ') . ' ' . $currency;
    }
    return implode(' + ', $labels);
}

/**
 * Read-only annual overview for every person currently visible to the account.
 * The two sources intentionally remain separate because a service can exist in
 * both systems during migration and must not be silently counted twice.
 *
 * @return array{
 *   year:int,
 *   member_charges:list<array<string,mixed>>,
 *   shop_items:list<array<string,mixed>>,
 *   totals:array{member_charges:array<string,int>,shop_items:array<string,int>}
 * }
 */
function familyAnnualPaidOverview(PDO $pdo, int $accountId, int $year): array
{
    if ($accountId < 1) {
        throw new FamilyAnnualPaidOverviewException('Roční přehled vyžaduje přihlášený účet.');
    }
    if ($year < 2000 || $year > 9999) {
        throw new InvalidArgumentException('Vybraný rok není platný.');
    }

    $people = familyPortalAuthorizedPeople($pdo, $accountId);
    $personIds = array_values(array_unique(array_map(
        static fn (array $person): int => (int)$person['sportovec_id'],
        $people
    )));
    $memberCharges = [];
    $shopItems = [];

    if ($personIds !== []) {
        $placeholders = implode(',', array_fill(0, count($personIds), '?'));
        $from = sprintf('%04d-01-01 00:00:00', $year);
        $until = sprintf('%04d-01-01 00:00:00', $year + 1);

        if (familyPortalHasTables($pdo, ['club_member_charges', 'payments', 'sportovci'])) {
            $statement = $pdo->prepare(
                'SELECT c.id,c.public_code,c.title_snapshot,c.amount_minor,c.currency,'
                . 'p.paid_at,s.jmeno AS beneficiary_first_name,s.prijmeni AS beneficiary_last_name '
                . 'FROM club_member_charges c '
                . "JOIN payments p ON p.payable_type='member_charge' AND p.payable_id=c.id AND p.status='paid' "
                . 'JOIN sportovci s ON s.id=c.sportovec_id '
                . "WHERE c.status='paid' AND c.sportovec_id IN ({$placeholders}) "
                . 'AND p.paid_at>=? AND p.paid_at<? '
                . 'ORDER BY p.paid_at DESC,c.id DESC'
            );
            $statement->execute([...$personIds, $from, $until]);
            $memberCharges = $statement->fetchAll(PDO::FETCH_ASSOC);
            foreach ($memberCharges as &$row) {
                $row['amount_minor'] = (int)$row['amount_minor'];
            }
            unset($row);
        }

        if (familyPortalHasTables($pdo, ['shop_orders', 'shop_order_items', 'payments', 'sportovci'])
            && familyAnnualPaidOverviewColumnExists($pdo, 'shop_order_items', 'beneficiary_sportovec_id')
        ) {
            $statement = $pdo->prepare(
                'SELECT oi.id,o.public_code,oi.product_name_snapshot,oi.quantity,'
                . 'oi.line_amount_minor AS amount_minor,oi.currency,p.paid_at,'
                . 's.jmeno AS beneficiary_first_name,s.prijmeni AS beneficiary_last_name '
                . 'FROM shop_order_items oi '
                . 'JOIN shop_orders o ON o.id=oi.order_id '
                . "JOIN payments p ON p.payable_type='shop_order' AND p.payable_id=o.id AND p.status='paid' "
                . 'JOIN sportovci s ON s.id=oi.beneficiary_sportovec_id '
                . "WHERE o.payment_status='paid' AND oi.beneficiary_sportovec_id IN ({$placeholders}) "
                . 'AND p.paid_at>=? AND p.paid_at<? '
                . 'ORDER BY p.paid_at DESC,o.id DESC,oi.id ASC'
            );
            $statement->execute([...$personIds, $from, $until]);
            $shopItems = $statement->fetchAll(PDO::FETCH_ASSOC);
            foreach ($shopItems as &$row) {
                $row['quantity'] = (int)$row['quantity'];
                $row['amount_minor'] = (int)$row['amount_minor'];
            }
            unset($row);
        }
    }

    return [
        'year' => $year,
        'member_charges' => $memberCharges,
        'shop_items' => $shopItems,
        'totals' => [
            'member_charges' => familyAnnualPaidOverviewTotals($memberCharges),
            'shop_items' => familyAnnualPaidOverviewTotals($shopItems),
        ],
    ];
}

function familyAnnualPaidOverviewColumnExists(PDO $pdo, string $table, string $column): bool
{
    if (preg_match('/^[a-z0-9_]+$/D', $table) !== 1 || preg_match('/^[a-z0-9_]+$/D', $column) !== 1) {
        return false;
    }
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1'
        );
        $statement->execute([$table, $column]);
        return (bool)$statement->fetchColumn();
    }
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        foreach ($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ((string)$row['name'] === $column) {
                return true;
            }
        }
    }
    return false;
}
