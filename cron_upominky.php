<?php
/**
 * cron_upominky.php
 * Zasílá upomínky trenérům na plánované tréninky, které proběhly,
 * ale dosud nebyly zaevidovány v evidenci.
 *
 * Spuštění:
 *   - CLI:  php cron_upominky.php
 *
 * Doporučené nastavení cronu (každý den v 7:00):
 *   0 7 * * * php /cesta/k/evidencePavel/cron_upominky.php >> /var/log/upominky.log 2>&1
 */

// ── Bezpečnost ────────────────────────────────────────────────────────────────
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__.'/db.php';
require_once __DIR__.'/includes/app_url.php';
require_once __DIR__.'/includes/operational_jobs.php';
$run=operationalJobStart($pdo,'training_reminders');
try {
    $pdo->beginTransaction();
    $q=$pdo->prepare("SELECT p.id,p.trener_id,p.nazev,p.datum,t.email FROM planovane_treninky p JOIN treneri t ON t.id=p.trener_id WHERE p.stav='planovany' AND p.datum>=? AND p.datum<=? AND p.upominka_cas IS NULL".($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''));
    $q->execute([date('Y-m-d',strtotime('-14 days')),date('Y-m-d',strtotime('-1 day'))]);
    $queued=0;
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $key='training_reminder:'.(int)$row['id'];
        $existing=$pdo->prepare('SELECT id FROM club_event_notifications WHERE dedupe_key=?');$existing->execute([$key]);
        if($existing->fetchColumn())continue;
        $valid=filter_var($row['email'],FILTER_VALIDATE_EMAIL);
        $subject='Doplňte evidenci tréninku';
        $body='Plán #'.$row['id'].' dne '.$row['datum']." dosud nemá záznam.\nPřehled: ".appUrl('planovac.php?jen_moje=1');
        $pdo->prepare('INSERT INTO club_event_notifications(dedupe_key,notification_type,recipient_email,recipient_name,subject_plain,body_plain,status,last_error) VALUES(?,?,?,?,?,?,?,?)')
            ->execute([$key,'training_reminder',$valid?$row['email']:'','',$subject,$body,$valid?'pending':'failed',$valid?null:'manual_contact_required: vedoucí trenér ověří kontakt.']);
        $pdo->prepare('UPDATE planovane_treninky SET upominka_cas=CURRENT_TIMESTAMP WHERE id=? AND upominka_cas IS NULL')->execute([(int)$row['id']]);
        $queued++;
    }
    $pdo->commit();operationalJobFinish($pdo,$run,true);
    echo json_encode(['queued'=>$queued,'transport'=>'queue_only'],JSON_THROW_ON_ERROR).PHP_EOL;
} catch(Throwable $error) {
    if($pdo->inTransaction())$pdo->rollBack();
    operationalJobFinish($pdo,$run,false,'enqueue_failed');
    fwrite(STDERR,"Zařazení připomínek selhalo; žádné zprávy nebyly odeslány.\n");exit(1);
}
