<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;

require_once dirname(__DIR__, 2) . '/includes/club_program.php';

/** Reduced synthetic schema for semantic characterization, not a production query-plan model. */
function programCapacityFixture(PDO $pdo): void
{
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $definitions = [
        'club_program_offers' => 'id INTEGER PRIMARY KEY,program_id INTEGER,team_id INTEGER,product_id INTEGER,variant_id INTEGER,starts_on VARCHAR(10),ends_on VARCHAR(10)',
        'club_program_physical_teams' => 'program_id INTEGER,team_id INTEGER,PRIMARY KEY(program_id,team_id)',
        'club_program_enrollments' => 'id INTEGER PRIMARY KEY,offer_id INTEGER,sportovec_id INTEGER,status VARCHAR(30),source_order_item_id INTEGER',
        'shop_orders' => 'id INTEGER PRIMARY KEY,status VARCHAR(30),payment_status VARCHAR(30),payment_expires_at VARCHAR(19)',
        'shop_order_items' => 'id INTEGER PRIMARY KEY,order_id INTEGER,product_id INTEGER,variant_id INTEGER,quantity INTEGER,'
            . 'beneficiary_sportovec_id INTEGER,athlete_registration_request_id INTEGER,club_program_offer_id INTEGER',
        'account_person_claim_requests' => 'id INTEGER PRIMARY KEY,status VARCHAR(30)',
    ];
    foreach ($definitions as $table => $columns) {
        $pdo->exec('CREATE TABLE ' . $table . '(' . $columns . ')');
    }
    $pdo->exec("INSERT INTO club_program_offers VALUES
        (1,1,10,101,1001,'2026-09-01','2027-06-30'),
        (2,2,10,102,1002,'2026-09-01','2027-01-31'),
        (3,3,10,103,1003,'2027-02-01','2027-06-30'),
        (4,4,20,104,1004,'2026-09-01','2027-06-30')");
}

function programCapacityHold(PDO $pdo, int $id, int $offerId, ?int $person, ?int $request, int $quantity = 1): void
{
    $pdo->prepare("INSERT INTO shop_orders VALUES(?,'placed','pending','2026-10-29 12:00:00')")->execute([$id]);
    $pdo->prepare('INSERT INTO shop_order_items VALUES(?,?,?,?,?,?,?,?)')
        ->execute([$id, $id, 100 + $offerId, 1000 + $offerId, $quantity, $person, $request, $offerId]);
}

function programCapacityOffer(PDO $pdo, int $id = 1, ?int $capacity = 10): array
{
    $q = $pdo->prepare('SELECT * FROM club_program_offers WHERE id=?');
    $q->execute([$id]);
    return $q->fetch(PDO::FETCH_ASSOC) + ['capacity' => $capacity];
}
