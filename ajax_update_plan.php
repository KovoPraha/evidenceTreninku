<?php
require_once __DIR__ . '/includes/session_security.php';
/**
 * ajax_update_plan.php
 * AJAX endpoint pro inline úpravy plánovaných tréninků z plánovače.
 * Akce: rename (změna názvu), move (změna datumu přes D&D)
 */
app_session_start();
if (!isset($_SESSION['trener_id'])) { http_response_code(403); echo json_encode(['ok'=>false,'msg'=>'Nepřihlášen']); exit; }
require_once 'includes/funkce.php';
if (!canAccess('planovac')) { http_response_code(403); echo json_encode(['ok'=>false,'msg'=>'Přístup odepřen']); exit; }
require_once 'db.php';
require_once 'csrf_helper.php';
require_once __DIR__.'/includes/training_closure.php';

header('Content-Type: application/json; charset=utf-8');

$trenerId = (int)$_SESSION['trener_id'];
$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;

if (!csrf_verify($data['csrf_token'] ?? '')) {
    echo json_encode(['ok'=>false,'msg'=>'Neplatný CSRF token']); exit;
}

$akce  = $data['akce'] ?? '';
$planId = (int)($data['plan_id'] ?? 0);

if (!$planId) { echo json_encode(['ok'=>false,'msg'=>'Chybí plan_id']); exit; }

// Ověřit vlastnictví (trenér může upravovat své; hlavní může vše)
$stCheck = $pdo->prepare("SELECT id, trener_id FROM planovane_treninky WHERE id=? AND stav='planovany'");
$stCheck->execute([$planId]);
$plan = $stCheck->fetch(PDO::FETCH_ASSOC);

if (!$plan) { echo json_encode(['ok'=>false,'msg'=>'Plán nenalezen']); exit; }
if ((int)$plan['trener_id'] !== $trenerId && !roleAtLeast('hlavni')) {
    echo json_encode(['ok'=>false,'msg'=>'Nemáte oprávnění']); exit;
}

try {
$pdo->beginTransaction();
trainingClosureAssertActor($pdo,$trenerId);coachScopeAssertPlan($pdo,$planId);
$stCheck=$pdo->prepare("SELECT * FROM planovane_treninky WHERE id=? AND stav='planovany'".trainingClosureLockSuffix($pdo));$stCheck->execute([$planId]);$plan=$stCheck->fetch(PDO::FETCH_ASSOC);
if(!$plan)throw new TrainingClosureException('Plán již není dostupný.');
switch ($akce) {
    case 'rename':
        $nazev = trim($data['nazev'] ?? '');
        if ($nazev === '') { echo json_encode(['ok'=>false,'msg'=>'Název nesmí být prázdný']); exit; }
        $nazev = mb_substr($nazev, 0, 200);   // ochrana proti přetečení VARCHAR
        $pdo->prepare("UPDATE planovane_treninky SET nazev=? WHERE id=?")
            ->execute([$nazev, $planId]);
        $pdo->commit();
        echo json_encode(['ok'=>true,'nazev'=>htmlspecialchars($nazev, ENT_QUOTES, 'UTF-8')]);
        break;

    case 'move':
        $noveDatum = trim($data['datum'] ?? '');
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $noveDatum, $m) || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
            echo json_encode(['ok'=>false,'msg'=>'Neplatné datum']); exit;
        }
        foreach(['training_rsvps'=>'plan_id','club_makeup_slots'=>'plan_id','club_makeup_credits'=>'source_plan_id','club_makeup_reservations'=>'target_plan_id'] as $table=>$column){
            if(memberChargeReadTableExists($pdo,$table)){$check=$pdo->prepare("SELECT 1 FROM $table WHERE $column=? LIMIT 1");$check->execute([$planId]);if($check->fetchColumn())throw new TrainingClosureException('Lekci s reakcemi nebo náhradami přesuňte zrušením a propojením nového termínu.');}
        }
        if((int)($plan['rezervace_id']??0)>0)throw new TrainingClosureException('Lekce má rezervované sportoviště. Přesun řešte spolu s rezervací.');
        $teams=trainingRosterBridgePlanTeamIds($pdo,$planId);
        trainingScheduleAssertAvailable($pdo,$trenerId,(int)$plan['trener_id'],$noveDatum,$plan['cas_od'],$plan['cas_do'],(int)$plan['sportoviste_id']?:null,$teams,$planId);
        $pdo->prepare("UPDATE planovane_treninky SET datum=? WHERE id=?")
            ->execute([$noveDatum, $planId]);
        trainingRosterBridgeReplacePlanTeams($pdo,$planId,$teams,$trenerId);
        $pdo->prepare('INSERT INTO training_schedule_events(plan_id,action,reason,before_json,actor_id) VALUES(?,?,?,?,?)')->execute([$planId,'date_changed','Přesun dosud nepotvrzené lekce v plánovači.',json_encode($plan,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$trenerId]);
        $pdo->commit();
        echo json_encode(['ok'=>true,'datum'=>$noveDatum]);
        break;

    default:
        $pdo->rollBack();
        echo json_encode(['ok'=>false,'msg'=>'Neznámá akce']);
}
} catch(Throwable $e) {if($pdo->inTransaction())$pdo->rollBack();http_response_code(422);echo json_encode(['ok'=>false,'msg'=>$e instanceof RuntimeException?$e->getMessage():'Změnu se nepodařilo bezpečně uložit.'],JSON_UNESCAPED_UNICODE);}
