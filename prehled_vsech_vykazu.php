<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/session_security.php';
app_session_start();
require_once __DIR__ . '/includes/funkce.php';
if (!isset($_SESSION['trener_id']) || !canAccess('vsechny_vykazy')) { header('Location: login.php'); exit; }
$autoload=__DIR__ . '/vendor/autoload.php';
if(!is_file($autoload)){http_response_code(503);exit('Chybí lokální PHP závislosti. Spusťte PRIPRAVIT_LOCALHOST_TESTOVANI.cmd.');}
require_once $autoload;
require_once __DIR__ . '/db.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function allReportsH(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$trainerId = filter_input(INPUT_GET, 'trainer_id', FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]) ?: 0;
$month = (string)($_GET['month'] ?? date('Y-m'));
if (preg_match('/^20\d{2}-(0[1-9]|1[0-2])$/D', $month) !== 1) $month = date('Y-m');
[$year, $mon] = array_map('intval', explode('-', $month));
$trainers = $pdo->query('SELECT id, jmeno FROM treneri ORDER BY jmeno')->fetchAll(PDO::FETCH_ASSOC);
$trData = [];$actData = [];$loadError = null;

if ($trainerId > 0) {
    try {
        $trainerIds = array_map('intval', array_column($trainers, 'id'));
        if (!in_array($trainerId, $trainerIds, true)) throw new InvalidArgumentException('Vybraný trenér nebyl nalezen.');
        $sql = <<<'SQL'
SELECT t.id,t.datum,t.napln,t.poznamka,t.delka,
       (SELECT GROUP_CONCAT(DISTINCT s.nazev SEPARATOR ', ') FROM trenink_skupina tg JOIN skupiny s ON s.id=tg.skupina_id WHERE tg.trenink_id=t.id) AS skupiny,
       (SELECT GROUP_CONCAT(DISTINCT p.nazev SEPARATOR ', ') FROM trenink_podskupina tp JOIN podskupiny p ON p.id=tp.podskupina_id WHERE tp.trenink_id=t.id) AS podskupiny,
       (SELECT COUNT(DISTINCT ts.sportovec_id) FROM trenink_sportovec ts WHERE ts.trenink_id=t.id) AS pocet_sportovcu,
       CASE WHEN EXISTS(SELECT 1 FROM trenink_mereni tm WHERE tm.trenink_id=t.id)
            THEN (SELECT COUNT(DISTINCT tm.mereni_id) FROM trenink_mereni tm WHERE tm.trenink_id=t.id)
            ELSE (SELECT COUNT(DISTINCT m.id) FROM mereni m WHERE m.trenink_id=t.id)
       END AS pocet_mereni
FROM trenink_trener tt
JOIN treninky t ON t.id=tt.trenink_id
WHERE tt.trener_id=:trainer_id AND YEAR(t.datum)=:year AND MONTH(t.datum)=:mon
ORDER BY t.datum,t.id
SQL;
        $statement=$pdo->prepare($sql);$statement->execute([':trainer_id'=>$trainerId,':year'=>$year,':mon'=>$mon]);$trData=$statement->fetchAll(PDO::FETCH_ASSOC);
        $statement=$pdo->prepare('SELECT datum,nazev,delka,poznamka FROM dalsi_cinnosti WHERE trener_id=:trainer_id AND YEAR(datum)=:year AND MONTH(datum)=:mon ORDER BY datum,id');
        $statement->execute([':trainer_id'=>$trainerId,':year'=>$year,':mon'=>$mon]);$actData=$statement->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $exception) {
        $reference = strtoupper(bin2hex(random_bytes(4)));
        error_log('prehled_vsech_vykazu.php ['.$reference.']: '.$exception->getMessage());
        $loadError = $exception instanceof InvalidArgumentException ? $exception->getMessage() : 'Výkazy se nepodařilo načíst. Referenční kód: '.$reference;
    }
}

$totalHours = array_sum(array_map(static fn(array $row): float => (float)$row['delka'], array_merge($trData,$actData)));
$trainerName = '';
foreach ($trainers as $trainer) if ((int)$trainer['id'] === $trainerId) { $trainerName=(string)$trainer['jmeno'];break; }

if (($_GET['export'] ?? '') === 'xls' && $trainerId > 0 && $loadError === null) {
    $spreadsheet=new Spreadsheet();$sheet=$spreadsheet->getActiveSheet();$sheet->setTitle('Výkazy');
    $sheet->setCellValue('A1','Trenér: '.$trainerName);$sheet->setCellValue('A2','Měsíc: '.$month);
    $sheet->fromArray(['Datum','Náplň','Poznámka','Délka','Skupiny','Podskupiny','Počet sportovců','Počet měření'],null,'A4');$row=5;
    foreach($trData as$data){$sheet->fromArray([$data['datum'],$data['napln'],$data['poznamka'],$data['delka'],$data['skupiny'],$data['podskupiny'],$data['pocet_sportovcu'],$data['pocet_mereni']],null,'A'.$row++);}
    $sheet->setCellValue('A'.$row++,'Další činnosti');$sheet->fromArray(['Datum','Název','Délka','Poznámka'],null,'A'.$row++);
    foreach($actData as$data)$sheet->fromArray([$data['datum'],$data['nazev'],$data['delka'],$data['poznamka']],null,'A'.$row++);
    $sheet->setCellValue('A'.$row,'Celkem hodin');$sheet->setCellValue('B'.$row,$totalHours);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');header('Content-Disposition: attachment; filename="vykazy_'.$month.'.xlsx"');(new Xlsx($spreadsheet))->save('php://output');exit;
}
?>
<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Výpis všech výkazů</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous"></head><body class="bg-light"><?php include __DIR__.'/hlavicka.php';?>
<main class="container py-4"><h1 class="h3">Výpis všech výkazů</h1><form method="get" class="row g-2 mb-4 align-items-end"><div class="col-md-4"><label class="form-label" for="report-trainer">Trenér</label><select id="report-trainer" name="trainer_id" class="form-select" required><option value="">Vyberte trenéra</option><?php foreach($trainers as$trainer):?><option value="<?=(int)$trainer['id']?>" <?=$trainerId===(int)$trainer['id']?'selected':''?>><?=allReportsH($trainer['jmeno'])?></option><?php endforeach;?></select></div><div class="col-md-3"><label class="form-label" for="report-month">Měsíc</label><input id="report-month" type="month" name="month" value="<?=allReportsH($month)?>" class="form-control" required></div><div class="col-md-5 d-flex gap-2"><button type="submit" class="btn btn-primary">Zobrazit</button><?php if($trainerId>0&&$loadError===null):?><button name="export" value="xls" class="btn btn-success">Export do Excelu</button><?php endif;?></div></form>
<?php if($loadError!==null):?><div class="alert alert-danger"><?=allReportsH($loadError)?></div><?php elseif($trainerId<1):?><div class="alert alert-info">Nejprve vyberte trenéra a měsíc.</div><?php else:?><section class="card border-0 shadow-sm mb-4"><div class="card-header bg-white"><strong>Tréninky</strong></div><div class="list-group list-group-flush"><?php if($trData===[]):?><div class="list-group-item text-muted">V tomto měsíci nejsou žádné tréninky.</div><?php endif;?><?php foreach($trData as$data):?><div class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2"><span><?=allReportsH($data['datum'])?> – <?=allReportsH($data['napln'])?> (<?=allReportsH($data['delka'])?> h)<span class="small text-muted"> · <?= (int)$data['pocet_sportovcu']?> sportovců · <?= (int)$data['pocet_mereni']?> měření</span></span><a href="edit_trenink.php?id=<?=(int)$data['id']?>" class="btn btn-sm btn-outline-secondary">Upravit</a></div><?php endforeach;?></div></section>
<section class="card border-0 shadow-sm mb-3"><div class="card-header bg-white"><strong>Další činnosti</strong></div><div class="list-group list-group-flush"><?php if($actData===[]):?><div class="list-group-item text-muted">V tomto měsíci nejsou žádné další činnosti.</div><?php endif;?><?php foreach($actData as$data):?><div class="list-group-item"><?=allReportsH($data['datum'])?> – <?=allReportsH($data['nazev'])?> (<?=allReportsH($data['delka'])?> h)<div class="small text-muted"><?=allReportsH($data['poznamka'])?></div></div><?php endforeach;?></div></section><p><strong>Celkem hodin:</strong> <?=allReportsH($totalHours)?> h</p><?php endif;?></main></body></html>
