<?php

declare(strict_types=1);
require_once __DIR__.'/includes/init.php';
require_once __DIR__.'/csrf_helper.php';
require_once __DIR__.'/includes/medical_clearance.php';
header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');
try {
    medicalClearanceRegistrar($pdo);
} catch (DomainException) {
    http_response_code(403);
    exit('Přístup má pověřený registrář.');
}
function medicalH(mixed $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
$error = '';
$id = (int)($_GET['id'] ?? $_POST['watch_id'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) {
            http_response_code(403);
            throw new DomainException('Formulář vypršel. Obnovte stránku.');
        }
        switch ((string)($_POST['action'] ?? '')) {
            case 'policy':medicalClearancePolicyCreate($pdo, $_POST);
                break;
            case 'watch':$id = medicalClearanceWatchCreate($pdo, $_POST);
                break;
            case 'record':medicalClearanceRecord($pdo, $_POST, $_FILES['evidence'] ?? null);
                break;
            case 'decide':medicalClearanceDecide($pdo, (int)($_POST['record_id'] ?? 0), (int)($_POST['revision'] ?? 0), (string)($_POST['decision'] ?? ''), (string)($_POST['reason'] ?? ''));
                break;
            case 'contact':medicalClearanceContact($pdo, $id, (string)($_POST['reason'] ?? ''));
                break;
            default:throw new InvalidArgumentException('Neplatná operace.');
        }
        header('Location: medical_clearance_admin.php'.($id ? '?id='.$id : ''), true, 303);
        exit;
    } catch (DomainException|InvalidArgumentException $e) {
        if (http_response_code() !== 403) {
            http_response_code(422);
        }$error = $e->getMessage();
    } catch (Throwable $e) {
        http_response_code(500);
        error_log('Medical clearance: '.get_class($e));
        $error = 'Podklad se nepodařilo uložit.';
    }
}
$policies = $pdo->query('SELECT * FROM medical_clearance_policies ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
$medicalList = medicalClearanceList($pdo, $_GET);
$watches = $medicalList['rows'];
$contextPerson = max(0, (int)($_GET['person_id'] ?? 0));
$chosenPerson = ($_POST['action'] ?? '') === 'watch' ? max(0, (int)($_POST['sportovec_id'] ?? 0))
    : max(0, (int)($_GET['medical_selected_person'] ?? $contextPerson));
$personChoices = medicalClearancePersonChoices($pdo, $_GET, $chosenPerson);
$people = $personChoices['rows'];
$personLookupContext = [];
foreach (['q','state','from','to'] as $key) {
    $personLookupContext['medical_'.$key] = $medicalList['filters'][$key];
}
$personLookupContext['medical_page'] = $medicalList['page'];
$personLookupContext['medical_selected_person'] = $chosenPerson;
if ($contextPerson) {
    $personLookupContext['person_id'] = $contextPerson;
}
if ($id > 0) {
    $personLookupContext['id'] = $id;
}
$selected = null;
$records = [];
$events = [];
if ($id > 0) {
    try {
        $selected = medicalClearanceWatch($pdo, $id);
        $q = $pdo->prepare('SELECT * FROM medical_clearance_records WHERE watch_id=? ORDER BY id DESC');
        $q->execute([$id]);
        $records = $q->fetchAll(PDO::FETCH_ASSOC);
        $q = $pdo->prepare('SELECT e.*,t.jmeno FROM medical_clearance_events e JOIN treneri t ON t.id=e.actor_id WHERE watch_id=? ORDER BY e.id DESC');
        $q->execute([$id]);
        $events = $q->fetchAll(PDO::FETCH_ASSOC);
    } catch (DomainException $e) {
        $error = $e->getMessage();
    }
}
$labels = ['missing' => 'Nedoloženo','pending' => 'Čeká na ověření','valid' => 'Platné','expired' => 'Platnost skončila',
    'not_yet_valid' => 'Dosud neplatné','verified' => 'Ověřeno','superseded' => 'Nahrazeno','revoked' => 'Odvoláno','rejected' => 'Odmítnuto'];
$medicalViewData = compact(
    'error',
    'selected',
    'id',
    'records',
    'events',
    'contextPerson',
    'medicalList',
    'watches',
    'labels',
    'personChoices',
    'personLookupContext',
    'people',
    'chosenPerson',
    'policies'
);
require __DIR__ . '/includes/medical_clearance_admin_view.php';
