<?php
declare(strict_types=1);
require_once __DIR__.'/includes/init.php';
require_once __DIR__.'/includes/sports_activity.php';
if(!isset($_SESSION['trener_id'])){http_response_code(403);exit('Přihlaste se jako pracovník.');}
try{
    $actor=(int)$_SESSION['trener_id'];$statementId=(int)($_GET['statement_id']??0);
    if($statementId>0){
        $q=$pdo->prepare('SELECT * FROM sports_activity_statements WHERE id=?');$q->execute([$statementId]);$row=$q->fetch(PDO::FETCH_ASSOC);if(!$row){http_response_code(404);exit('Podklad neexistuje.');}
        sportsActivityAssertActor($pdo,$actor,(int)$row['sportovec_id']);$summary=json_decode($row['snapshot_json'],true,512,JSON_THROW_ON_ERROR);
        if(!hash_equals($row['snapshot_hash'],sportsActivityHash($summary)))throw new RuntimeException('Podklad neprošel kontrolou integrity.');
    }else{
        $person=(int)($_GET['person_id']??0);if($person<1)throw new InvalidArgumentException('Vyberte osobu.');
        sportsActivityAssertActor($pdo,$actor,$person);$summary=sportsActivitySummary($pdo,$person,(string)($_GET['from']??''),(string)($_GET['to']??''));
    }
    header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="sportovni-cinnost.csv"');header('Cache-Control: no-store');echo ($_GET['kind']??'')==='measurements'?sportsActivityMeasurementsCsv($summary):sportsActivityCsv($summary);
}catch(InvalidArgumentException $e){http_response_code(422);echo $e->getMessage();}catch(RuntimeException $e){http_response_code(403);echo $e->getMessage();}
