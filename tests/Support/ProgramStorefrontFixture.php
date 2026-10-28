<?php

declare(strict_types=1);

namespace Tests\Support;

require_once __DIR__ . '/StorefrontReadFixture.php';

function programStorefrontFixture(int $count, ?StorefrontCountingPdo $connection = null): StorefrontCountingPdo
{
    $pdo = storefrontReadFixture($count, false, $connection);
    $pdo->exec("UPDATE shop_products SET offer_type='program'");
    $pdo->exec('ALTER TABLE club_programs ADD COLUMN description TEXT');
    foreach (['team_id INTEGER', 'season_id INTEGER', 'starts_on TEXT', 'ends_on TEXT',
        'sales_open_at TEXT', 'sales_close_at TEXT', 'capacity INTEGER'] as $definition) {
        $pdo->exec('ALTER TABLE club_program_offers ADD COLUMN ' . $definition);
    }
    $pdo->exec('CREATE TABLE club_teams(id INTEGER PRIMARY KEY,name TEXT)');
    $pdo->exec('CREATE TABLE club_seasons(id INTEGER PRIMARY KEY,name TEXT)');
    $pdo->exec('CREATE TABLE club_program_physical_teams(program_id INTEGER,team_id INTEGER)');
    $pdo->exec('CREATE TABLE club_program_enrollments(id INTEGER PRIMARY KEY,offer_id INTEGER,sportovec_id INTEGER,source_order_item_id INTEGER,status TEXT)');
    $pdo->exec('CREATE TABLE shop_orders(id INTEGER PRIMARY KEY,status TEXT,payment_status TEXT,payment_expires_at TEXT)');
    $pdo
        ->exec('CREATE TABLE shop_order_items(id INTEGER PRIMARY KEY,order_id INTEGER,product_id INTEGER,'
        . 'variant_id INTEGER,club_program_offer_id INTEGER,beneficiary_sportovec_id INTEGER,'
        . 'athlete_registration_request_id INTEGER,quantity INTEGER)');
    $pdo->exec('CREATE TABLE account_person_claim_requests(id INTEGER PRIMARY KEY,status TEXT)');
    $pdo->exec('CREATE TABLE club_event_term_versions(id INTEGER PRIMARY KEY,scope_type TEXT,scope_key TEXT,consent_purpose TEXT,consent_text_plain TEXT,status TEXT)');
    $pdo->exec("INSERT INTO club_seasons VALUES(1,'LOCALHOST NEPLATIT')");
    $program = $pdo->prepare("INSERT INTO club_programs VALUES(?,'active',?,'LOCALHOST NEPLATIT')");
    $team = $pdo->prepare("INSERT INTO club_teams VALUES(?,'LOCALHOST team')");
    $offer = $pdo->prepare("INSERT INTO club_program_offers VALUES(?,?,?,?,'active',?,1,'2026-01-01','2099-12-31',NULL,NULL,10)");
    $term = $pdo->prepare("INSERT INTO club_event_term_versions VALUES(?,'club_program',?,?,'Published synthetic consent','active')");
    for ($id = 1; $id <= $count; $id++) {
        $program->execute([$id, 'LOCALHOST program ' . $id]);
        $team->execute([$id]);
        $offer->execute([$id, $id, $id, $id, $id]);
        $term->execute([$id * 2, 'program:' . $id, 'program_consent']);
        $term->execute([$id * 2 + 1, 'program:' . $id, 'program_cancellation']);
    }
    $pdo->queries = [];
    return $pdo;
}
