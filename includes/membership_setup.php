<?php

declare(strict_types=1);

require_once __DIR__.'/monthly_membership.php';
require_once __DIR__.'/shop_manual_catalog.php';

function membershipRacingTerms(): string
{
    return 'Závodní tým: 2 200 Kč za kalendářní měsíc. První měsíc se hradí v plné výši. '
        . 'Splatnost je 15. den měsíce, první úhrada nejdříve sedm dní po sjednání. '
        . 'Ukončení je možné ke konci kalendářního měsíce. Platba bankovním trvalým příkazem pod stálým VS plátce. '
        . 'Výběr jedné nebo více disciplín MTB, Silnice a Dráha nemění cenu a pouze zařazuje do příslušných soupisek. '
        . 'Doklady a upozornění na nezaplacené předpisy jsou zasílány elektronicky.';
}

/** Explicit administrative action; never called by a migration or a GET request. */
function membershipPrepareRacingTeam(PDO $pdo): array
{
    return monthlyMembershipTransaction($pdo, static function () use ($pdo): array {
        $actor = accountingDocumentRequireFinance($pdo);
        $reason = 'Založení Závodního týmu 2 200 Kč měsíčně podle zadání vlastníka; více disciplín bez příplatku.';
        $teams = [];
        foreach (['CLEN-ZAVODNI' => ['Závodní tým', 'group'], 'CLEN-ZAVODNI-MTB' => ['MTB', 'roster'],
            'CLEN-ZAVODNI-SILNICE' => ['Silnice', 'roster'], 'CLEN-ZAVODNI-DRAHA' => ['Dráha', 'roster']] as $code => [$name, $kind]) {
            $q = $pdo->prepare('SELECT * FROM club_teams WHERE code=? ORDER BY id' . accountingDocumentLock($pdo));
            $q->execute([$code]);
            $rows = $q->fetchAll(PDO::FETCH_ASSOC);
            if ($rows === []) {
                $team = kisRosterCreateStandingTeam($pdo, $actor, $code, $name, $kind, $reason);
            } elseif (count($rows) === 1 && $rows[0]['standing_code'] === $code && $rows[0]['team_kind'] === $kind
                && $rows[0]['status'] === 'active' && $rows[0]['name'] === $name) {
                $team = $rows[0];
            } else {
                throw new DomainException('Kód skupiny nebo soupisky již používá jiná konfigurace. Zkontrolujte ji ručně.');
            }
            $teams[] = (int)$team['id'];
        }
        $parent = array_shift($teams);
        $preview = kisRosterPreviewHierarchy($pdo, $parent, $teams, monthlyMembershipToday());
        kisRosterAttachChildren($pdo, $parent, $teams, monthlyMembershipToday(), $actor, $reason, $preview['fingerprint']);
        $sku = shopCatalogManualSkuPrefix().'CLEN-ZAVODNI-MESIC';
        $q = $pdo->prepare('SELECT v.id,v.product_id,v.amount_minor,p.name,p.offer_type,p.origin FROM shop_variants v '
            . 'JOIN shop_products p ON p.id=v.product_id WHERE v.sku=?' . accountingDocumentLock($pdo));
        $q->execute([$sku]);
        $variant = $q->fetch(PDO::FETCH_ASSOC);
        if ($variant) {
            if ((int)$variant['amount_minor'] !== 220000 || $variant['name'] !== 'Závodní tým'
                || $variant['offer_type'] !== 'program' || $variant['origin'] !== 'manual') {
                throw new DomainException('Kód produktu už má jiné nastavení; příprava jej nepřepíše.');
            }
            return ['product_id' => (int)$variant['product_id'], 'variant_id' => (int)$variant['id'], 'team_id' => $parent, 'team_ids' => $teams];
        }
        $product = shopManualCatalogCreateInTransaction($pdo, $actor, [
            'name' => 'Závodní tým', 'short_description' => membershipRacingTerms(),
            'offer_type' => 'program', 'item_type' => 'service', 'visibility' => 'visible',
        ], ['sku' => $sku, 'amount_minor' => 220000, 'visible' => 1,
            'attributes_json' => '{"Tarif":"Měsíční členství"}'], $reason, true);
        $pdo->prepare('INSERT INTO shop_product_categories(product_id,category_path,is_default,sort_order) VALUES(?,?,1,0)')
            ->execute([$product['product_id'], 'Členství v klubu']);
        shopManualCatalogEvent(
            $pdo,
            $product['product_id'],
            $product['variant_id'],
            $actor,
            'membership_category',
            null,
            ['category_path' => 'Členství v klubu', 'team_id' => $parent, 'team_ids' => $teams],
            $reason
        );
        monthlyMembershipEvent($pdo, null, 'trainer', $actor, 'racing_team_prepared', $product + ['team_id' => $parent, 'team_ids' => $teams]);
        return $product + ['team_id' => $parent, 'team_ids' => $teams];
    });
}

/** Versioned issuer and member document defaults, committed together after explicit confirmation. */
function membershipSaveIssuer(PDO $pdo, array $input): int
{
    return monthlyMembershipTransaction($pdo, static function () use ($pdo, $input): int {
        accountingDocumentRequireFinance($pdo);
        $id = accountingDocumentConfigureIssuer($pdo, $input);
        $q = $pdo->prepare("SELECT id FROM accounting_rules WHERE source_type='member_charge' "
            . "AND document_kind='contribution_confirmation' AND category_code='CLEN' AND activity='KLUB' AND valid_from<=?");
        $q->execute([monthlyMembershipToday()]);
        if (!$q->fetchColumn()) {
            accountingDocumentConfigureRule($pdo, [
                'source_type' => 'member_charge', 'document_kind' => 'contribution_confirmation', 'category_code' => 'CLEN',
                'activity' => 'KLUB', 'title' => 'Členství v klubu', 'valid_from' => monthlyMembershipToday(),
                'confirmed' => '1', 'approval_evidence' => $input['approval_evidence'] ?? '',
            ]);
        }
        $year = (int)substr(monthlyMembershipToday(), 0, 4);
        foreach ([$year, $year + 1] as $seriesYear) {
            $q = $pdo->prepare("SELECT id FROM accounting_document_series WHERE kind='contribution_confirmation' AND series_year=?");
            $q->execute([$seriesYear]);
            if (!$q->fetchColumn()) {
                accountingDocumentConfigureSeries($pdo, [
                    'kind' => 'contribution_confirmation', 'series_year' => $seriesYear, 'prefix' => 'CLEN', 'next_number' => 1,
                    'confirmed' => '1', 'reason' => 'Výchozí řada členství schválená s údaji vystavovatele.',
                ]);
            }
        }
        return $id;
    });
}
