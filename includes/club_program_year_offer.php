<?php

declare(strict_types=1);

require_once __DIR__ . '/club_program_storefront.php';

final class ClubProgramYearOfferException extends RuntimeException
{
}

/**
 * Read-only proposals for a full-year option of existing programs. Closed full-year
 * offers still suppress a proposal: the operator must inspect that history first.
 *
 * @return list<array<string,mixed>>
 */
function clubProgramYearOfferCandidates(PDO $pdo, ?DateTimeImmutable $now = null): array
{
    $now ??= new DateTimeImmutable('now', new DateTimeZone('Europe/Prague'));
    $rows = $pdo->query(
        "SELECT o.id,o.program_id,o.product_id,o.team_id,o.purchase_option,o.starts_on,o.ends_on,"
        . "o.sales_open_at,o.sales_close_at,o.capacity,o.birth_year_from,o.birth_year_to,o.status,"
        . "p.name AS program_name,p.status AS program_status,t.name AS team_name,"
        . "sp.origin AS product_origin,sp.catalog_status AS product_status,sp.offer_type,"
        . "v.sku,v.amount_minor,v.currency,v.catalog_status AS variant_status "
        . "FROM club_program_offers o JOIN club_programs p ON p.id=o.program_id "
        . "JOIN club_teams t ON t.id=o.team_id "
        . "JOIN shop_products sp ON sp.id=o.product_id "
        . "JOIN shop_variants v ON v.id=o.variant_id "
        . "WHERE o.purchase_option IN ('first_half','second_half','full_year') "
        . "ORDER BY p.name,o.program_id,o.starts_on,o.id"
    )->fetchAll(PDO::FETCH_ASSOC);

    $groups = [];
    foreach ($rows as $row) {
        $year = clubProgramSchoolYearStart((string)$row['starts_on']);
        $key = (int)$row['program_id'] . ':' . $year;
        $groups[$key]['program_id'] = (int)$row['program_id'];
        $groups[$key]['year'] = $year;
        $groups[$key]['program_name'] = (string)$row['program_name'];
        $groups[$key][(string)$row['purchase_option']][] = $row;
    }

    $result = [];
    foreach ($groups as $group) {
        if (!empty($group['full_year']) || empty($group['first_half']) || empty($group['second_half'])
            || (string)$group['second_half'][0]['ends_on'] < $now->format('Y-m-d')) {
            continue;
        }
        $first = $group['first_half'][0];
        $second = $group['second_half'][0];
        $problem = '';
        $terms = [];
        if (count($group['first_half']) !== 1 || count($group['second_half']) !== 1) {
            $problem = 'Více pololetních nabídek pro stejný rok; vyřešte je jednotlivě.';
        } elseif ((string)$first['status'] !== 'active' || (string)$second['status'] !== 'active') {
            $problem = 'Jedna z pololetních nabídek není aktivní.';
        } elseif ((string)$first['program_status'] !== 'active'
            || (string)$first['product_origin'] !== 'manual'
            || (string)$first['product_status'] !== 'active'
            || (string)$first['offer_type'] !== 'program'
            || (string)$first['variant_status'] !== 'active'
            || (string)$second['variant_status'] !== 'active'
            || (string)$first['currency'] !== 'CZK'
            || (string)$second['currency'] !== 'CZK') {
            $problem = 'Program, produkt nebo pololetní varianta není připravená k prodeji.';
        } elseif ((int)$first['team_id'] !== (int)$second['team_id']
            || (int)$first['product_id'] !== (int)$second['product_id']) {
            $problem = 'Pololetí nemají stejný produkt a cílovou soupisku.';
        } elseif ((string)$first['capacity'] !== (string)$second['capacity']
            || (string)$first['birth_year_from'] !== (string)$second['birth_year_from']
            || (string)$first['birth_year_to'] !== (string)$second['birth_year_to']) {
            $problem = 'Kapacita nebo ročníky se mezi pololetími liší.';
        } elseif ((string)$first['starts_on'] >= (string)$first['ends_on']
            || (string)$first['ends_on'] >= (string)$second['starts_on']
            || (string)$second['starts_on'] >= (string)$second['ends_on']
            || clubProgramSchoolYearStart((string)$second['starts_on']) !== $group['year']) {
            $problem = 'Datum pololetí nelze bezpečně spojit do celého roku.';
        } else {
            foreach (CLUB_PROGRAM_TERM_PURPOSES as $purpose) {
                $terms[$purpose] = clubProgramTermsCurrent($pdo, 'program', (int)$group['program_id'], $purpose);
            }
            if (!clubProgramTermsComplete($terms)) {
                $problem = 'Program nemá schválené společné podmínky pro novou nabídku.';
            }
        }
        $year = (int)$group['year'];
        $programId = (int)$group['program_id'];
        $salesOpen = $now->format('Y-m-d') . 'T00:00';
        if (!empty($first['sales_open_at'])) {
            $firstSalesOpen = str_replace(' ', 'T', substr((string)$first['sales_open_at'], 0, 16));
            if ($firstSalesOpen > $salesOpen) {
                $salesOpen = $firstSalesOpen;
            }
        }
        $candidate = [
            'base_offer_id' => (int)$first['id'],
            'program_id' => $programId,
            'program_name' => (string)$group['program_name'],
            'team_name' => (string)$first['team_name'],
            'year' => $year,
            'year_label' => $year . '/' . substr((string)($year + 1), -2),
            'first_price_minor' => (int)$first['amount_minor'],
            'second_price_minor' => (int)$second['amount_minor'],
            'starts_on' => (string)$first['starts_on'],
            'ends_on' => (string)$second['ends_on'],
            'capacity' => $first['capacity'] === null ? null : (int)$first['capacity'],
            'birth_year_from' => $first['birth_year_from'] === null ? null : (int)$first['birth_year_from'],
            'birth_year_to' => $first['birth_year_to'] === null ? null : (int)$first['birth_year_to'],
            'name' => (string)$group['program_name'] . ' · Celý rok ' . $year . '/' . substr((string)($year + 1), -2),
            'code' => 'PROGRAM-YEAR-' . $programId . '-' . $year,
            'sku' => 'KP-PROGRAM-YEAR-' . $programId . '-' . $year,
            'sales_open_at' => $salesOpen,
            'sales_close_at' => (string)$second['ends_on'] . 'T23:59',
            'problem' => $problem,
        ];
        $candidate['fingerprint'] = hash('sha256', json_encode([
            $first, $second, $terms, $problem, $candidate['sales_open_at'], $candidate['sales_close_at']
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $result[] = $candidate;
    }
    return $result;
}

/**
 * The caller previews prices first and stores fingerprints in its session.
 * Re-read under program locks so a stale preview cannot publish changed data.
 *
 * @param array<int,int> $pricesByBaseOfferId price in haléře
 * @param array<int,string> $fingerprintsByBaseOfferId
 * @return list<array{offer_id:int,variant_id:int,product_id:int,program_id:int}>
 */
function clubProgramYearOfferCreateBatch(
    PDO $pdo,
    int $actorId,
    array $pricesByBaseOfferId,
    array $fingerprintsByBaseOfferId,
    bool $confirmed,
    ?DateTimeImmutable $now = null
): array {
    if ($actorId < 1 || !$confirmed || $pricesByBaseOfferId === [] || count($pricesByBaseOfferId) > 50) {
        throw new InvalidArgumentException('Vyberte alespoň jednu skupinu a potvrďte zveřejnění.');
    }
    foreach ($pricesByBaseOfferId as $id => $price) {
        if (!is_int($id) || $id < 1 || !is_int($price) || $price < 1 || $price > 999999999
            || !isset($fingerprintsByBaseOfferId[$id])) {
            throw new InvalidArgumentException('Každá skupina potřebuje schválenou kladnou cenu.');
        }
    }
    ksort($pricesByBaseOfferId, SORT_NUMERIC);
    $now ??= new DateTimeImmutable('now', new DateTimeZone('Europe/Prague'));
    $pdo->beginTransaction();
    try {
        $ids = array_keys($pricesByBaseOfferId);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = $pdo->prepare("SELECT id,program_id FROM club_program_offers WHERE id IN ($placeholders) ORDER BY program_id,id");
        $statement->execute($ids);
        $programRows = $statement->fetchAll(PDO::FETCH_ASSOC);
        if (count($programRows) !== count($ids)) {
            throw new ClubProgramYearOfferException('Výběr skupin se změnil. Obnovte náhled.');
        }
        $programIds = array_unique(array_map(static fn (array $row): int => (int)$row['program_id'], $programRows));
        foreach ($programIds as $programId) {
            $sql = 'SELECT id FROM club_programs WHERE id=?';
            if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
                $sql .= ' FOR UPDATE';
            }
            $lock = $pdo->prepare($sql);
            $lock->execute([$programId]);
            if (!$lock->fetchColumn()) {
                throw new ClubProgramYearOfferException('Program byl mezitím odebrán. Obnovte náhled.');
            }
        }
        $candidates = [];
        foreach (clubProgramYearOfferCandidates($pdo, $now) as $candidate) {
            $candidates[(int)$candidate['base_offer_id']] = $candidate;
        }
        $created = [];
        foreach ($pricesByBaseOfferId as $id => $price) {
            $candidate = $candidates[$id] ?? null;
            if ($candidate === null || $candidate['problem'] !== ''
                || !hash_equals((string)$candidate['fingerprint'], (string)$fingerprintsByBaseOfferId[$id])) {
                throw new ClubProgramYearOfferException('Nabídka se od náhledu změnila. Obnovte stránku a zkontrolujte ji znovu.');
            }
            $created[] = clubProgramCreatePaymentOption($pdo, $actorId, $id, [
                'purchase_option' => 'full_year',
                'is_featured' => $price < $candidate['first_price_minor'] + $candidate['second_price_minor'] ? 1 : 0,
                'sku' => $candidate['sku'],
                'code' => $candidate['code'],
                'name' => $candidate['name'],
                'amount_minor' => $price,
                'compare_at_amount_minor' => null,
                'includes_vat' => null,
                'vat_rate_basis_points' => null,
                'starts_on' => $candidate['starts_on'],
                'ends_on' => $candidate['ends_on'],
                'sales_open_at' => $candidate['sales_open_at'],
                'sales_close_at' => $candidate['sales_close_at'],
                'capacity' => $candidate['capacity'],
                'birth_year_from' => $candidate['birth_year_from'],
                'birth_year_to' => $candidate['birth_year_to'],
                'status' => 'active',
            ], 'Schválená celoroční nabídka ke stávajícímu kroužku.', true, false);
        }
        $pdo->commit();
        return $created;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}
