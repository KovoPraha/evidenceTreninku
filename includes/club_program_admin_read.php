<?php

declare(strict_types=1);

require_once __DIR__ . '/club_program.php';
require_once __DIR__ . '/club_program_capacity_read.php';

/** One fresh administrative GET projection; never a checkout or locking read.
 * @return list<array<string,mixed>>
 */
function clubProgramAdminOffersRead(PDO $pdo): array
{
    $rows = $pdo->query('SELECT o.*,p.code AS program_code,p.name AS program_name,NULL AS season_name,t.name AS team_name,v.sku,sp.name AS '
        . ('product_name FROM club_program_offers o JOIN club_programs p ON p.id=o.program_id JOIN club_teams t ON ' .

    't.id=o.team_id JOIN shop_variants v ON v.id=o.variant_id JOIN shop_products sp ON ')
        . 'sp.id=o.product_id ORDER BY o.starts_on DESC,o.id DESC')->fetchAll(PDO::FETCH_ASSOC);
    $context = new ClubProgramReadContext($pdo);
    $context->warmAdminOffers($rows);
    $capacities = (new ClubProgramCapacityRead($pdo, $context))->states($rows);
    foreach ($rows as &$row) {
        $row = array_merge($row, $capacities[(int) $row['id']]);
        $row['effective_terms'] = clubProgramTermsEffective($pdo, (int) $row['program_id'], (int) $row['id'], false, $context);
        $row['terms_complete'] = clubProgramTermsComplete($row['effective_terms']);
        $saleState = clubProgramOfferSaleState($row);
        if ($saleState['saleable'] && !$row['terms_complete']) {
            $saleState = ['saleable' => false, 'reason' => 'Nabídka nemá zveřejněné platné storno podmínky a souhlas.'];
        }
        $row['saleable'] = $saleState['saleable'];
        $row['sale_reason'] = $saleState['reason'];
    }
    unset($row);
    return $rows;
}
