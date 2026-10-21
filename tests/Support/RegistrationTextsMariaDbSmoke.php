<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2).'/includes/athlete_registration_texts.php';
require_once dirname(__DIR__, 2).'/includes/club_registration_consent_admin.php';
$database = getenv('EVIDENCE_REGISTRATION_TEXTS_SMOKE_DB') ?: 'evidence_registration_texts_test';
if (preg_match('/\Aevidence_registration_texts_test(?:_[a-z0-9_]+)?\z/', $database) !== 1) {
    throw new RuntimeException('Refusing to use a non-test database.');
}
$port = (int)(getenv('EVIDENCE_REGISTRATION_TEXTS_SMOKE_PORT') ?: 3306);
$connect = static fn(string $db = ''): PDO => new PDO('mysql:host=127.0.0.1;port='.$port.($db === '' ? '' : ';dbname='.$db).';charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false]);
$_SESSION = ['trener_id'=>7,'staff_positions'=>['registrar'],'staff_active_position'=>'registrar'];
if (($argv[1] ?? '') === 'publish') {
    try { registrationTextsPublish($connect($database), (int)($argv[2] ?? 0), true); echo 'published'; }
    catch (InvalidArgumentException|PDOException) { echo 'rejected'; }
    exit;
}
if (($argv[1] ?? '') === 'custom-publish') {
    try { clubConsentPublish($connect($database),(int)$argv[2],(int)$argv[3],true); echo 'published'; }
    catch(InvalidArgumentException|PDOException){echo 'rejected';}
    exit;
}
$server = $connect();
$quoted = '`'.$database.'`';
$server->exec('CREATE DATABASE '.$quoted.' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
try {
    $pdo = $connect($database);
    $pdo->exec('CREATE TABLE treneri(id INT PRIMARY KEY) ENGINE=InnoDB');
    $pdo->exec('INSERT INTO treneri VALUES(7)');
    $terms = require dirname(__DIR__,2).'/migrations/20260803150000_club_event_terms.php';
    $pdo->exec('CREATE TABLE club_events(id BIGINT UNSIGNED PRIMARY KEY) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE club_event_registrations(id BIGINT UNSIGNED PRIMARY KEY) ENGINE=InnoDB');
    ($terms['up'])($pdo);
    $scope = require dirname(__DIR__,2).'/migrations/20260816180000_registration_terms_scope.php';
    ($scope['up'])($pdo);
    // This migration also evolves unrelated order/enrollment tables; only the
    // terms lifecycle columns are needed in this focused database fixture.
    $pdo->exec("ALTER TABLE club_event_term_versions ADD status VARCHAR(16) NOT NULL DEFAULT 'active', ADD archived_at DATETIME NULL, ADD archived_by_trainer_id INT NULL");
    $migration = require dirname(__DIR__,2).'/migrations/20261021150000_athlete_registration_text_drafts.php';
    ($migration['up'])($pdo);
    ($migration['up'])($pdo);
    if (!(($migration['verify'])($pdo))) throw new RuntimeException('Migration did not verify.');
    $titles = require dirname(__DIR__,2).'/migrations/20261021170000_registration_text_titles.php';
    ($titles['up'])($pdo);
    ($titles['up'])($pdo);
    if (!(($titles['verify'])($pdo))) throw new RuntimeException('Title migration did not verify.');
    $pdo->exec('CREATE TABLE verejni_uzivatele(id INT PRIMARY KEY) ENGINE=InnoDB');
    $pdo->exec('INSERT INTO verejni_uzivatele VALUES(10)');
    $pdo->exec('CREATE TABLE shop_order_items(id BIGINT UNSIGNED PRIMARY KEY) ENGINE=InnoDB');
    $pdo->exec('INSERT INTO shop_order_items VALUES(100)');
    $customMigration=require dirname(__DIR__,2).'/migrations/20261021190000_custom_club_registration_consents.php';
    ($customMigration['up'])($pdo);($customMigration['up'])($pdo);
    if(!(($customMigration['verify'])($pdo)))throw new RuntimeException('Custom consent migration did not verify.');
    $purpose = 'birth_number_legal_notice';
    $base = registrationTextsCurrent($pdo, $purpose);
    $first = registrationTextsSaveDraft($pdo,$purpose,'LOCALHOST První konečné znění.','LOCALHOST test A',(int)$base['id']);
    $second = registrationTextsSaveDraft($pdo,$purpose,'LOCALHOST Druhé konečné znění.','LOCALHOST test B',(int)$base['id']);
    if (registrationTextsCurrent($pdo,$purpose)['terms_version'] !== $base['terms_version']) throw new RuntimeException('Draft changed public version.');
    // Both workers compete for the same previous public version. Exactly one
    // may publish; neither may silently overwrite the other.
    $workers = [];
    foreach ([$first,$second] as $draftId) {
        $pipes = [];
        $process = proc_open([PHP_BINARY,__FILE__,'publish',(string)$draftId], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
        if (!is_resource($process)) throw new RuntimeException('Worker did not start.');
        fclose($pipes[0]);
        $workers[] = [$process,$pipes];
    }
    $outcomes = [];
    foreach ($workers as [$process,$pipes]) {
        $outcomes[] = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        if (proc_close($process) !== 0 || $error !== '') throw new RuntimeException('Publication worker failed.');
    }
    sort($outcomes);
    if ($outcomes !== ['published','rejected']) throw new RuntimeException('Competing publications were not serialized.');
    if (count(registrationTextsHistory($pdo,$purpose)) !== 2) throw new RuntimeException('History was overwritten or duplicated.');
    $published = (int)$pdo->query('SELECT id FROM athlete_registration_text_drafts WHERE published_term_id IS NOT NULL')->fetchColumn();
    $termId = registrationTextsPublish($pdo,$published,true);
    if (registrationTextsPublish($pdo,$published,true) !== $termId) throw new RuntimeException('Retry is not idempotent.');
    $base = registrationTextsCurrent($pdo,$purpose);
    $draft = registrationTextsSaveDraft($pdo,$purpose,$base['consent_text_plain'],'LOCALHOST Pouze název',(int)$base['id'],'LOCALHOST Nový název souhlasu');
    registrationTextsPublish($pdo,$draft,true);
    $renamed = registrationTextsCurrent($pdo,$purpose);
    if ($renamed['title_plain'] !== 'LOCALHOST Nový název souhlasu' || $renamed['consent_text_plain'] !== $base['consent_text_plain']) throw new RuntimeException('Title-only publication did not preserve body.');
    $history = registrationTextsHistory($pdo,$purpose);
    if ($history[1]['title_plain'] !== 'Informace k rodnému číslu') throw new RuntimeException('Original title was not preserved in history.');
    $custom=clubConsentCreate($pdo,'LOCALHOST Vlastní souhlas','LOCALHOST Platné znění.','LOCALHOST Vytvoření',true,true);
    if(clubConsentCurrent($pdo)!==[])throw new RuntimeException('An unpublished custom consent became visible.');
    $base=clubConsentTerm($pdo,$custom['id']);
    $other=clubConsentSaveDraft($pdo,$custom['id'],(int)$base['id'],'LOCALHOST Konkurenční','LOCALHOST Jiné znění.','LOCALHOST Jiný koncept',true,true);
    $workers=[];
    foreach([$custom['draft_id'],$other] as $draftId){
        $pipes=[];$process=proc_open([PHP_BINARY,__FILE__,'custom-publish',(string)$custom['id'],(string)$draftId],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if(!is_resource($process))throw new RuntimeException('Custom publication worker failed to start.');
        fclose($pipes[0]);$workers[]=[$process,$pipes];
    }
    $outcomes=[];
    foreach($workers as [$process,$pipes]){$outcomes[]=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($process)!==0||$error!=='')throw new RuntimeException('Custom publication worker failed.');}
    sort($outcomes);if($outcomes!==['published','rejected'])throw new RuntimeException('Custom consent publication was not serialized.');
    $terms=clubConsentCurrent($pdo);$input=['club_consent_manifest'=>clubConsentManifest($terms),'club_consent_accepted'=>[(int)$terms[0]['id']=>'1']];
    $pdo->beginTransaction();$snapshots=clubConsentValidate($pdo,$input);clubConsentStore($pdo,100,10,$snapshots);$pdo->commit();
    clubConsentHide($pdo,$custom['id'],(int)$terms[0]['id'],true);
    if(clubConsentCurrent($pdo)!==[]||(int)$pdo->query('SELECT COUNT(*) FROM club_registration_consent_acceptances')->fetchColumn()!==1)throw new RuntimeException('Hiding lost history or left the consent visible.');
    echo 'MariaDB registration texts and custom consents smoke OK ('.$pdo->query('SELECT VERSION()')->fetchColumn().")\n";
} finally {
    $server->exec('DROP DATABASE '.$quoted);
}
