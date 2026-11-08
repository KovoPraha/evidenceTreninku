<?php

declare(strict_types=1);

require_once __DIR__.'/shop_product_image.php';
require_once __DIR__.'/shop_attribute.php';
require_once __DIR__.'/kis_roster.php';
require_once __DIR__.'/club_program_terms.php';
require_once __DIR__.'/shop_inventory.php';

final class ClubProgramWizardException extends RuntimeException
{
}

/**
 * Empty controls choose the documented wizard defaults. Explicit dates remain
 * authoritative, including the source window copied by annual recurrence.
 * @param array<string,mixed> $input
 * @return array{sales_open_at:string,sales_close_at:string}
 */
function clubProgramWizardSalesWindow(array $input, ?DateTimeImmutable $now = null): array
{
    $today = ($now ?? new DateTimeImmutable('now', new DateTimeZone('Europe/Prague')))
        ->setTimezone(new DateTimeZone('Europe/Prague'))->format('Y-m-d');
    $startsOn = trim((string)($input['starts_on'] ?? ''));
    $endsOn = trim((string)($input['ends_on'] ?? ''));
    $open = trim((string)($input['sales_open_at'] ?? '')) ?: $today . 'T00:00';
    // Future courses retain their original default. Admission to a course
    // already in progress can stay open through its last day.
    $close = trim((string)($input['sales_close_at'] ?? ''))
        ?: ($startsOn < $today ? $endsOn : $startsOn) . 'T23:59';
    $normalizedOpen = clubProgramInputDateTime($open);
    $normalizedClose = clubProgramInputDateTime($close);
    if ($normalizedOpen >= $normalizedClose) {
        throw new ClubProgramWizardException('Konec prodeje musí být po jeho otevření. Zkontrolujte prodejní období; ukončený kurz nelze automaticky otevřít k prodeji.');
    }
    return ['sales_open_at' => $open, 'sales_close_at' => $close];
}

/** @param array<string,mixed> $product @return array{name:string,description:string} */
function clubProgramWizardProductText(array $product): array
{
    $name = trim((string)($product['public_name'] ?? ''));
    if ($name === '') {
        $name = trim((string)($product['name'] ?? ''));
    }
    $description = trim((string)($product['public_summary'] ?? ''));
    if ($description === '') {
        $description = trim((string)($product['short_description'] ?? ''));
    }
    return ['name' => $name, 'description' => $description !== '' ? $description : 'Kroužek ' . $name];
}

/** @param array<string,mixed> $input @param array<string,mixed> $reference @return array<string,mixed> */
function cpwSimpleDefaults(array $input, string $requestKey, array $reference): array
{
    $sourceMode = (string)($input['source_mode'] ?? 'new');
    if ($sourceMode === 'existing') {
        $variantId = (int)($input['existing_variant_id'] ?? 0);
        $selected = null;
        foreach ($reference['products'] ?? [] as $product) {
            if ((int)($product['variant_id'] ?? 0) === $variantId && $variantId > 0) {
                $selected = $product;
                break;
            }
        }
        if ($selected === null) {
            throw new ClubProgramWizardException('Vybraný produkt už není dostupný. Obnovte formulář a vyberte variantu znovu.');
        }
        $text = clubProgramWizardProductText($selected);
        $input['name'] = $text['name'];
        $input['description'] = $text['description'];
    }
    $name = trim((string)($input['name'] ?? ''));
    $startsOn = (string)($input['starts_on'] ?? '');
    $endsOn = (string)($input['ends_on'] ?? '');
    $startYear = preg_match('/^(\d{4})-/', $startsOn, $match) === 1 ? (int)$match[1] : (int)date('Y');
    $endYear = preg_match('/^(\d{4})-/', $endsOn, $match) === 1 ? (int)$match[1] : $startYear + 1;
    $suffix = strtoupper(substr($requestKey, 0, 6));

    $input['description'] = trim((string)($input['description'] ?? '')) ?: 'Kroužek ' . $name;
    $input['currency'] = 'CZK';
    $input['category_path'] = 'Kroužky';
    $input['includes_vat'] = '';
    $input['vat_rate_basis_points'] = '';
    $input = array_replace($input, clubProgramWizardSalesWindow($input));
    foreach (CLUB_PROGRAM_TERM_PURPOSES as $purpose) {
        $terms = $reference['terms'][$purpose] ?? [];
        if (is_array($terms) && $terms !== [] && (int)($terms[0]['id'] ?? 0) > 0) {
            $input[$purpose . '_source'] = 'existing';
            $input[$purpose . '_version_id'] = (string)$terms[0]['id'];
            continue;
        }
        $text = trim((string)($input[$purpose . '_text'] ?? ''));
        if ($text === '' || mb_strlen($text, 'UTF-8') > 4000 || str_contains($text, CLUB_PROGRAM_TERM_DRAFT_MARKER)) {
            throw new ClubProgramWizardException('Před prvním zveřejněním vyplňte a zkontrolujte oba texty klubových podmínek. Vzorový text označený jako VZOR nelze zveřejnit.');
        }
        if (($input['terms_confirmed'] ?? '') !== '1') {
            throw new ClubProgramWizardException('Potvrďte, že jste zkontrolovali první znění klubových podmínek.');
        }
        $input[$purpose . '_source'] = 'new';
        $input[$purpose . '_text'] = $text;
    }
    $input['reason'] = $sourceMode === 'existing'
        ? 'Napojení existujícího produktu na kroužkový program.'
        : 'Vypsání nového kroužku.';
    $input['confirmed'] = true;

    if ((int)($input['team_id'] ?? 0) > 0) {
        $input['team_mode'] = 'existing';
        return $input;
    }

    $input['team_mode'] = 'new';
    $input['season_code'] = 'KROUZKY-' . $startYear . '-' . $endYear . '-' . $suffix;
    $input['season_name'] = 'Kroužky ' . $startYear . '/' . $endYear;
    $input['season_type'] = $startYear === $endYear ? 'calendar_year' : 'school_year';
    $input['season_starts_on'] = $startsOn;
    $input['season_ends_on'] = $endsOn;
    $input['team_code'] = 'KROUZEK-' . $suffix;
    $input['team_name'] = $name;
    $input['team_discipline'] = 'Všeobecná cyklistická příprava';
    $from = trim((string)($input['birth_year_from'] ?? ''));
    $to = trim((string)($input['birth_year_to'] ?? ''));
    $input['team_age_label'] = $from !== '' || $to !== '' ? 'Ročníky ' . ($from ?: 'bez omezení') . '–' . ($to ?: 'bez omezení') : 'Děti';
    return $input;
}

/** @return array{seasons:list<array<string,mixed>>,teams:list<array<string,mixed>>,categories:list<string>,terms:array<string,list<array<string,mixed>>>,products:list<array<string,mixed>>} */
function clubProgramWizardReferenceData(PDO $pdo): array
{
    $categories = $pdo->query("SELECT DISTINCT category_path FROM shop_product_categories WHERE TRIM(category_path)<>'' ORDER BY category_path")
        ->fetchAll(PDO::FETCH_COLUMN);
    $terms = ['program_cancellation' => [],'program_consent' => []];
    if (clubProgramTermsRegistryAvailable($pdo)) {
        $rows = $pdo->query(('SELECT id,scope_type,scope_key,consent_purpose,terms_version,consent_text_plain,created_at FROM '
    .
    'club_event_term_versions WHERE status=\'active\' AND consent_purpose IN ' .
    '(\'program_cancellation\',\'program_consent\') ORDER BY created_at DESC,id DESC'))
            ->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $terms[(string)$row['consent_purpose']][] = $row;
        }
    }
    $products = $pdo->query(
        "SELECT p.id AS product_id,v.id AS variant_id,p.name,p.short_description,p.offer_type,p.catalog_status,"
        . "v.sku,v.amount_minor,v.currency,v.stock_quantity_decimal,"
        . "pub.status AS publication_status,pub.public_name,pub.public_summary "
        . "FROM shop_products p JOIN shop_variants v ON v.product_id=p.id "
        . "LEFT JOIN shop_product_publications pub ON pub.product_id=p.id "
        . "WHERE p.catalog_status<>'inactive' AND v.catalog_status<>'inactive' "
        . "AND v.price_mode='fixed' AND v.amount_minor IS NOT NULL AND v.currency='CZK' "
        . "AND NOT EXISTS(SELECT 1 FROM club_program_offers o WHERE o.variant_id=v.id) "
        . 'ORDER BY COALESCE(pub.public_name,p.name),v.sku,v.id'
    )->fetchAll(PDO::FETCH_ASSOC);
    $teams = $pdo->query(('SELECT t.id,t.code,t.name,t.status,t.team_kind,\'\' AS season_name FROM club_teams t WHERE t.status=\'active\' '
    .
    'AND t.team_kind=\'roster\' ORDER BY t.name,t.id'))->fetchAll(PDO::FETCH_ASSOC);
    return['seasons' => [],'teams' => $teams,'categories' => array_values(array_map('strval', $categories)),

    'terms' => $terms,'products' => $products];
}

/** @return array<string,mixed> */
function clubProgramWizardExistingProduct(PDO $pdo, int $variantId, bool $lock = false): array
{
    if ($variantId < 1) {
        throw new InvalidArgumentException('Vyberte existující produkt, který chcete použít.');
    }
    $sql = "SELECT p.*,v.id AS variant_id,v.sku,v.amount_minor,v.currency,v.catalog_status AS variant_status,"
        ."v.stock_quantity_decimal,v.unit_code,v.visible,pub.status AS publication_status,"
        ."pub.public_name,pub.public_summary FROM shop_products p JOIN shop_variants v ON v.product_id=p.id "
        ."LEFT JOIN shop_product_publications pub ON pub.product_id=p.id WHERE v.id=? "
        ."AND p.catalog_status<>'inactive' AND v.catalog_status<>'inactive' AND v.price_mode='fixed' "
        ."AND v.amount_minor IS NOT NULL AND v.currency='CZK' "
        ."AND NOT EXISTS(SELECT 1 FROM club_program_offers o WHERE o.variant_id=v.id)";
    if ($lock && (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $sql .= ' FOR UPDATE';
    }
    $statement = $pdo->prepare($sql);
    $statement->execute([$variantId]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new ClubProgramWizardException('Vybraný produkt už má termín, není aktivní nebo jej nelze bezpečně převést.');
    }
    return$row;
}

/**
 * @param array<string,mixed> $input
 * @return array{product_id:int,variant_id:int,program_id:int,offer_id:int,season_id:?int,team_id:int,image_id:?int,category_path:string}
 */
function clubProgramWizardCreate(
    PDO $pdo,
    int $actorId,
    array $input,
    ?string $imageSource = null,
    bool $uploaded = true,
    ?string $applicationRoot = null
): array {
    $reason = trim((string)($input['reason'] ?? ''));
    $confirmed = ($input['confirmed'] ?? false) === true;
    shopManualCatalogDecision($actorId, $reason, $confirmed);
    $requestKey = strtolower(trim((string)($input['request_key'] ?? '')));
    if (preg_match('/^[a-f0-9]{32}$/D', $requestKey) !== 1) {
        throw new InvalidArgumentException('Průvodce nemá platný jednorázový klíč. Obnovte stránku.');
    }
    $sourceMode = (string)($input['source_mode'] ?? 'new');
    if (!in_array($sourceMode, ['new','existing'], true)) {
        throw new InvalidArgumentException('Vyberte nový nebo existující produkt.');
    }
    $existingProduct = $sourceMode === 'existing' ? clubProgramWizardExistingProduct($pdo, (int)($input['existing_variant_id']
    ?? 0)) : null;
    $existingText = $existingProduct !== null ? clubProgramWizardProductText($existingProduct) : null;
    $name = shopManualCatalogText($existingText['name'] ?? (string)($input['name'] ?? ''), 160, 'Název kroužku');
    $description = shopManualCatalogText($existingText['description'] ?? (string)($input['description'] ?? ''), 4000, 'Veřejný popis');
    if ($description === '') {
        throw new InvalidArgumentException('Veřejný popis je povinný.');
    }
    if (strtoupper(trim((string)($input['currency'] ?? ''))) !== 'CZK') {
        throw new InvalidArgumentException('Průvodce nyní bezpečně podporuje pouze měnu CZK.');
    }
    $category = clubProgramWizardCategory((string)($input['category_path'] ?? ''));
    $suffix = strtoupper(substr($requestKey, 0, 6));
    $slug = clubProgramWizardSlug($name);
    $programCode = clubProgramCode((string)($input['program_code'] ?? '') ?: substr(
        $slug.'-'.$suffix,
        0,
        64
    ));
    $offerCode = clubProgramCode((string)($input['offer_code'] ?? '') ?: substr(
        $slug.'-'.$suffix.'-OFFER',
        0,
        64
    ));
    $sku = strtoupper(trim((string)($input['sku'] ?? '')));
    if ($sku === '') {
        $sku = substr(shopCatalogManualSkuPrefix().$slug.'-'.$suffix, 0, 64);
    }
    $amount = $existingProduct !== null ? (int)$existingProduct['amount_minor'] : ($input['amount_minor'] ?? null);
    if (!is_int($amount)) {
        throw new InvalidArgumentException('Cena musí být zadána přesně v haléřích.');
    }
    $attributes = $existingProduct !== null ? '{}' : shopAttributeFormJson($input, shopAttributeDefinitions(
        $pdo,
        true
    ));
    $stored = null;
    $applicationRoot ??= dirname(__DIR__);
    if ($imageSource !== null && $imageSource !== '') {
        $stored = shopProductImageStoreFile($imageSource, $uploaded, $applicationRoot);
    }
    try {
        $pdo->beginTransaction();
        if ($existingProduct === null) {
            $catalog = shopManualCatalogCreateInTransaction($pdo, $actorId, [
                'name' => $name,'short_description' => $description,'offer_type' => 'program','visibility'
    => 'visible','item_type' => 'service',
            ], [
                'sku' => $sku,'ean' => '','attributes_json' => $attributes,'amount_minor' => $amount,'compare_at_amount_minor'
    => null,
                'includes_vat' => clubProgramWizardNullableInt($input['includes_vat'] ?? null),'vat_rate_basis_points'
    => clubProgramWizardNullableInt($input['vat_rate_basis_points'] ?? null),
                'stock_quantity_decimal' => '','unit_code' => 'person','visible' => 1,
            ], $reason, true);
            $productId = (int)$catalog['product_id'];
            $variantId = (int)$catalog['variant_id'];
        } else {
            $locked = clubProgramWizardExistingProduct($pdo, (int)$existingProduct['variant_id'], true);
            $productId = (int)$locked['id'];
            $variantId = (int)$locked['variant_id'];
            $beforeProduct = $locked;
            $beforeVariant = shopManualCatalogLockVariant($pdo, $variantId);
            $stockVariants = $pdo->prepare('SELECT id FROM shop_variants WHERE product_id=? ORDER BY id'.

    shopInventoryLock($pdo));
            $stockVariants->execute([$productId]);
            foreach ($stockVariants->fetchAll(PDO::FETCH_COLUMN) as $stockVariant) {
                if (shopInventoryManaged($pdo, (int)$stockVariant)) {
                    throw new ClubProgramWizardException('Produkt se skladovou historií nelze převést na službu. Založte samostatný kroužek.');
                }
            }
            $pdo->prepare("UPDATE shop_products SET offer_type='program',item_type='service',visibility='visible',updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$productId]);
            $pdo->prepare("UPDATE shop_variants SET stock_quantity_decimal=NULL,unit_code='person',visible=1,updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$variantId]);
            shopManualCatalogEvent(
                $pdo,
                $productId,
                null,
                $actorId,
                'convert_to_program',
                $beforeProduct,
                shopManualCatalogLockProduct($pdo, $productId),
                $reason
            );
            shopManualCatalogEvent(
                $pdo,
                $productId,
                $variantId,
                $actorId,
                'clear_program_stock',
                $beforeVariant,
                shopManualCatalogLockVariant($pdo, $variantId),
                $reason
            );
        }
        $imageId = null;
        $pdo->prepare('UPDATE shop_product_categories SET is_default=0 WHERE product_id=?')->execute([$productId]);
        $categoryRow = $pdo->prepare('SELECT id FROM shop_product_categories WHERE product_id=? AND category_path=?');
        $categoryRow->execute([$productId,$category]);
        $categoryId = $categoryRow->fetchColumn();
        if ($categoryId === false) {
            $pdo->prepare('INSERT INTO shop_product_categories(product_id,category_path,is_default,sort_order) VALUES(?,?,1,0)')->execute([$productId,

    $category]);
        } else {
            $pdo->prepare('UPDATE shop_product_categories SET is_default=1,sort_order=0 WHERE id=?')->execute([(int)$categoryId]);
        }
        shopManualCatalogEvent($pdo, $productId, null, $actorId, 'assign_category', null, ['category_path'
    => $category,'is_default' => 1,'sort_order' => 0], $reason);
        if ($stored !== null) {
            $imageId = (int)shopProductImageAddStoredInTransaction(
                $pdo,
                $actorId,
                $productId,
                $stored,
                0,
                $reason,
                true
            )['id'];
        }

        [$seasonId,$teamId] = clubProgramWizardTargetInTransaction($pdo, $actorId, $input, $reason);
        $program = clubProgramCreateInTransaction($pdo, $actorId, $programCode, $name, $description);
        $offer = clubProgramCreateOfferInTransaction(
            $pdo,
            $actorId,
            (int)$program['id'],
            $seasonId,
            $teamId,
            $productId,
            $variantId,
            $offerCode,
            $name,
            (string)($input['starts_on'] ?? ''),
            (string)($input['ends_on'] ?? ''),
            clubProgramWizardNullableText($input['sales_open_at'] ?? null),
            clubProgramWizardNullableText($input['sales_close_at'] ?? null),
            clubProgramWizardNullableInt($input['capacity'] ?? null),
            'active',
            clubProgramWizardNullableInt($input['birth_year_from'] ?? null),
            clubProgramWizardNullableInt($input['birth_year_to'] ?? null)
        );
        $purchaseOption = (string)($input['purchase_option'] ?? 'custom');
        if (!in_array($purchaseOption, ['first_half','second_half','full_year','custom'], true)) {
            throw new InvalidArgumentException('Platební varianta není podporována.');
        }
        $featured = !empty($input['is_featured']) ? 1 : 0;
        if (clubProgramColumnExists($pdo, 'club_program_offers', 'purchase_option')) {
            $pdo->prepare('UPDATE club_program_offers SET purchase_option=?,is_featured=? WHERE id=?')->execute([$purchaseOption,

    $featured,(int)$offer['id']]);
            $offer['purchase_option'] = $purchaseOption;
            $offer['is_featured'] = $featured;
        }
        if (clubProgramRuntimeTableExists($pdo, 'club_program_presentations')) {
            $ageFrom = clubProgramWizardNullableInt($input['birth_year_from'] ?? null);
            $ageTo = clubProgramWizardNullableInt($input['birth_year_to'] ?? null);
            $ageLabel = $ageFrom !== null || $ageTo !== null ? 'Ročníky '.($ageFrom ?? 'bez omezení').

    '–'.($ageTo ?? 'bez omezení') : null;
            $pdo->prepare(('INSERT INTO club_program_presentations(program_id,public_name,public_summary,age_label,listing_status,sort_ord'
    .
    'er,interest_enabled) VALUES(?,?,?,?, \'draft\',0,1)'))
                ->execute([(int)$program['id'],$name,$description !== '' ? $description : null,$ageLabel]);
        }
        foreach (CLUB_PROGRAM_TERM_PURPOSES as $purpose) {
            $text = clubProgramWizardTermText($pdo, $purpose, $input);
            clubProgramTermsConfigureInTransaction(
                $pdo,
                $actorId,
                'program',
                (int)$program['id'],
                $purpose,
                $text,
                true
            );
        }
        if (!empty($input['repeat_annually'])) {
            if (!clubProgramRuntimeTableExists($pdo, 'club_program_recurrence_rules')) {
                throw new ClubProgramWizardException('Databáze ještě nepodporuje opakování nabídek.');
            }
            if ($amount < 1 || !clubProgramTermsComplete(clubProgramTermsEffective(
                $pdo,
                (int)$program['id'],
                (int)$offer['id'],
                true
            ))) {
                throw new ClubProgramWizardException('Opakování vyžaduje kladnou cenu a schválené podmínky.');
            }
            $pdo->prepare('INSERT INTO club_program_recurrence_rules(source_offer_id,enabled,horizon_years,updated_by_trainer_id) VALUES (?,1,1,?)')->execute([(int)$offer['id'],

    $actorId]);
            clubProgramEvent(
                $pdo,
                (int)$program['id'],
                (int)$offer['id'],
                'trainer',
                $actorId,
                'configure_recurrence',
                null,
                ['enabled' => true,'horizon_years' => 1,'_audit_reason' => $reason]
            );
        }
        shopManualCatalogEvent($pdo, $productId, $variantId, $actorId, 'create_program_wizard', null, [
            'product_id' => $productId,'variant_id' => $variantId,'program_id' => (int)$program['id'],

    'offer_id' => (int)$offer['id'],
            'season_id' => $seasonId,'team_id' => $teamId,'category_path' => $category,'image_id' => $imageId,


            'purchase_option' => $purchaseOption,'is_featured' => $featured,
        ], $reason);
        $publication = $pdo->prepare('SELECT status FROM shop_product_publications WHERE product_id=?');
        $publication->execute([$productId]);
        $publicationStatus = (string)$publication->fetchColumn();
        if ($publicationStatus === 'active') {
            // Converting a published product creates a new offer even without reactivation.
            $accountingBlockers = shopCatalogPublicationAccountingBlockers($pdo, $productId, 'program');
            if ($accountingBlockers !== []) {
                throw new ShopCatalogPublicationException('Vypsání kroužku je blokované: '.implode(
                    ' ',
                    $accountingBlockers
                ));
            }
        } elseif (empty($input['defer_publication'])) {
            shopCatalogPublicationActivateInTransaction(
                $pdo,
                $productId,
                $actorId,
                $name,
                $description,
                $reason,
                true
            );
        }
        $pdo->commit();
        return['product_id' => $productId,'variant_id' => $variantId,'program_id' => (int)$program['id'],

    'offer_id' => (int)$offer['id'],'season_id' => $seasonId,'team_id' => $teamId,'image_id' => $imageId,

    'category_path' => $category];
    } catch (Throwable$exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($stored !== null) {
            shopProductImageQuarantine(
                shopProductImagePath((string)$stored['image_url'], $applicationRoot),
                $applicationRoot
            );
        }
        if ($exception instanceof InvalidArgumentException || $exception instanceof ShopManualCatalogException
    || $exception instanceof ShopProductImageException || $exception instanceof KisRosterException || $exception instanceof
    ClubProgramException || $exception instanceof ClubProgramTermsException || $exception instanceof ShopCatalogPublicationException
    || $exception instanceof ClubProgramWizardException) {
            throw$exception;
        }
        throw new ClubProgramWizardException(
            'Kroužek se nepodařilo vypsat; žádná jeho část nebyla zveřejněna.',
            0,
            $exception
        );
    }
}

/** @param array<string,mixed> $input @return array{?int,int} */
function clubProgramWizardTargetInTransaction(PDO $pdo, int $actorId, array $input, string $reason): array
{
    if (!$pdo->inTransaction()) {
        throw new LogicException('Výběr soupisky vyžaduje otevřenou transakci.');
    }
    $mode = (string)($input['team_mode'] ?? 'existing');
    if ($mode === 'existing') {
        $teamId = (int)($input['team_id'] ?? 0);
        $sql = "SELECT t.id FROM club_teams t WHERE t.id=? AND t.status='active' AND t.team_kind='roster'";
        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $sql .= ' FOR UPDATE';
        }
        $statement = $pdo->prepare($sql);
        $statement->execute([$teamId]);
        $team = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$team) {
            throw new ClubProgramWizardException('Vybraná aktivní soupiska nebyla nalezena.');
        }
        return[null,(int)$team['id']];
    }
    if ($mode !== 'new') {
        throw new InvalidArgumentException('Vyberte existující nebo novou soupisku.');
    }
    $team = kisRosterCreateStandingTeam($pdo, $actorId, (string)($input['team_code'] ?? ''), (string)($input['team_name']
    ?? ''), 'roster', $reason);
    if (clubProgramColumnExists($pdo, 'club_teams', 'activity_kind')) {
        $pdo->prepare("UPDATE club_teams SET activity_kind='circle' WHERE id=?")->execute([$team['id']]);
    }
    return[null,(int)$team['id']];
}

/** @param array<string,mixed> $input */
function clubProgramWizardTermText(PDO $pdo, string $purpose, array $input): string
{
    if (!in_array($purpose, CLUB_PROGRAM_TERM_PURPOSES, true)) {
        throw new InvalidArgumentException('Typ podmínek není podporován.');
    }
    $source = (string)($input[$purpose.'_source'] ?? 'new');
    if ($source === 'new') {
        return(string)($input[$purpose.'_text'] ?? '');
    }
    if ($source !== 'existing') {
        throw new InvalidArgumentException('Vyberte existující nebo nový text podmínek.');
    }
    $id = (int)($input[$purpose.'_version_id'] ?? 0);
    $sql = "SELECT consent_text_plain FROM club_event_term_versions WHERE id=? AND consent_purpose=? AND status='active'";
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $sql .= ' FOR UPDATE';
    }
    $statement = $pdo->prepare($sql);
    $statement->execute([$id,$purpose]);
    $text = $statement->fetchColumn();
    if (!is_string($text) || trim($text) === '') {
        throw new ClubProgramWizardException('Vybraná verze podmínek už není platná.');
    }
    return$text;
}

function clubProgramWizardSlug(string $value): string
{
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    $ascii = is_string($ascii) ? strtoupper($ascii) : 'PROGRAM';
    $ascii = (string)preg_replace('/[\'`´^~]+/u', '', $ascii);
    $slug = trim((string)preg_replace('/[^A-Z0-9]+/', '-', $ascii), '-');
    return$slug !== '' ? substr($slug, 0, 42) : 'PROGRAM';
}
function clubProgramWizardCategory(string $value): string
{
    $value = trim($value);
    if ($value === '' || mb_strlen($value, 'UTF-8') > 500 || preg_match('/[<\x00-\x1F]/u', $value) === 1) {
        throw new InvalidArgumentException('Kategorie musí být prostý text do 500 znaků.');
    }return$value;
}
function clubProgramWizardNullableInt(mixed $value): ?int
{
    return$value === null || trim((string)$value) === '' ? null : (int)$value;
}
function clubProgramWizardNullableText(mixed $value): ?string
{
    $value = trim((string)$value);
    return$value === '' ? null : $value;
}
