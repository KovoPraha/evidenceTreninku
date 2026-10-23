<?php
declare(strict_types=1);

/** Monitoring configuration describes expected operation; it never starts a scheduler or enables a transport. */
function operationalJobsCatalog(): array
{
    return [
        'transactional_notifications'=>['label'=>'Transakční oznámení','owner'=>'Správce systému','interval_seconds'=>300,'command'=>'bin/club-event-notifications.php'],
        'training_reminders'=>['label'=>'Nezaevidované tréninky','owner'=>'Vedoucí trenér','interval_seconds'=>86400,'command'=>'cron_upominky.php'],
        'family_weekly'=>['label'=>'Rodinný týdenní přehled','owner'=>'Registrář','interval_seconds'=>604800,'command'=>'bin/family-weekly-summaries.php'],
        'member_reminders'=>['label'=>'Připomínky příspěvků','owner'=>'Hospodář','interval_seconds'=>86400,'command'=>'bin/member-charge-reminders.php'],
        'roster_messages'=>['label'=>'Zprávy soupisek','owner'=>'Registrář','interval_seconds'=>300,'command'=>'bin/roster-message-worker.php'],
        'order_expiration'=>['label'=>'Expirace objednávek','owner'=>'Správce obchodu','interval_seconds'=>300,'command'=>'bin/expire-shop-orders.php'],
        'fio_import'=>['label'=>'Bankovní import','owner'=>'Hospodář','interval_seconds'=>3600,'command'=>'bin/fio-import.php'],
        'program_offers'=>['label'=>'Obnova nabídek programů','owner'=>'Registrář','interval_seconds'=>86400,'command'=>'bin/refresh-club-program-offers.php'],
        'backup'=>['label'=>'Záloha a ověření obnovy','owner'=>'Správce systému','interval_seconds'=>86400,'command'=>'bin/db-backup.php'],
    ];
}

function operationalJobStart(PDO $pdo, string $job): int
{
    if (!isset(operationalJobsCatalog()[$job])) throw new InvalidArgumentException('Neznámá úloha.');
    $pdo->prepare("INSERT INTO operational_job_runs(job_key,status,started_at) VALUES(?,'running',?)")->execute([$job,gmdate('Y-m-d H:i:s')]);
    return (int)$pdo->lastInsertId();
}

function operationalJobFinish(PDO $pdo, int $run, bool $success, string $code = 'completed'): void
{
    if (!preg_match('/^[a-z0-9_]{1,64}$/D', $code)) throw new InvalidArgumentException('Výsledek musí být bezpečný kód bez osobních údajů.');
    $q=$pdo->prepare("UPDATE operational_job_runs SET status=?,summary_code=?,finished_at=? WHERE id=? AND status='running'");
    $q->execute([$success?'success':'failed',$code,gmdate('Y-m-d H:i:s'),$run]);
    if ($q->rowCount()!==1) throw new RuntimeException('Běh již byl uzavřen.');
}

/** Read-only aggregate view; an absent heartbeat is not evidence of successful execution. */
function operationalJobsStatus(PDO $pdo, ?array $configuration = null, ?int $now = null): array
{
    $now ??= time(); $rows=[];
    foreach (operationalJobsCatalog() as $key=>$definition) {
        $mode=$configuration[$key] ?? getenv('JOB_MONITOR_'.strtoupper($key));
        $mode=is_string($mode)?$mode:'';
        $last=null;$success=null;$schema=true;
        try {
            $q=$pdo->prepare('SELECT started_at,finished_at,status,summary_code FROM operational_job_runs WHERE job_key=? ORDER BY id DESC LIMIT 1');
            $q->execute([$key]);$last=$q->fetch(PDO::FETCH_ASSOC)?:null;
            $q=$pdo->prepare("SELECT MAX(finished_at) FROM operational_job_runs WHERE job_key=? AND status='success'");
            $q->execute([$key]);$success=$q->fetchColumn()?:null;
        } catch (PDOException) { $schema=false; }
        $status=match(true) {
            !$schema=>'configuration_missing',
            $mode==='disabled'=>'disabled',
            $mode!=='expected'=>'configuration_missing',
            $last===null=>'never_run',
            $last['status']==='failed'=>'failed',
            $last['status']==='running' && $now-strtotime($last['started_at'].' UTC')>max(900,$definition['interval_seconds'])=>'stalled',
            $last['status']==='running'=>'running',
            !$success || $now-strtotime($success.' UTC')>2*$definition['interval_seconds']=>'late',
            default=>'healthy',
        };
        $action=match($status) {
            'disabled'=>'Úloha je podle provozní konfigurace záměrně vypnutá. Ověřit, že ji nenahrazuje ruční postup.',
            'configuration_missing'=>'Doplnit schválený provozní režim a ověřit konfiguraci; tento přehled úlohu nezapíná.',
            'never_run','late'=>'Ověřit plánovač, závislosti a poslední skutečný běh; po nápravě spustit schválený postup.',
            'failed','stalled'=>'Ověřit běh a frontu, nejprve vyřešit nejisté doručení; neopakovat automaticky obchodní operaci.',
            'running'=>'Vyčkat na výsledek; při překročení intervalu ověřit proces.',
            default=>'Bez zásahu; sledovat další běh.',
        };
        $duration=$last && $last['finished_at'] ? max(0,strtotime($last['finished_at'].' UTC')-strtotime($last['started_at'].' UTC')) : null;
        $labels=['configuration_missing'=>'Chybí provozní nastavení','disabled'=>'Vypnuto','never_run'=>'Běh dosud nedoložen','failed'=>'Selhalo','stalled'=>'Běh se neukončil','running'=>'Probíhá','late'=>'Po termínu','healthy'=>'V pořádku'];
        $rows[]=$definition+['job_key'=>$key,'status'=>$status,'status_label'=>$labels[$status],'last_success_at'=>$success,'last_run'=>$last,'last_duration_seconds'=>$duration,'next_action'=>$action,'scheduler_verified'=>false];
    }
    return $rows;
}
