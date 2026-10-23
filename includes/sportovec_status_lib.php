<?php
declare(strict_types=1);

require_once __DIR__.'/sportovec_history_lib.php';

/** Return only membership fields, including on an older test/read schema. */
function sportovecMembershipFields(PDO $pdo,int $personId): array
{
    $stmt=$pdo->prepare('SELECT * FROM sportovci WHERE id=?');$stmt->execute([$personId]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC)?:[];
    return array_intersect_key($row,array_flip(['stav_clenstvi','stav_manualni','stav_duvod','stav_aktualizovan']));
}

function sportovecMembershipRevision(array $person): string
{
    return hash('sha256',json_encode([(int)$person['id'],(string)($person['stav_clenstvi']??''),(int)($person['stav_manualni']??0),$person['stav_duvod']??null,$person['stav_aktualizovan']??null],JSON_THROW_ON_ERROR));
}

/** Explicit current membership decision. Does not touch accounts, rosters or money. */
function sportovecMembershipDecide(PDO $pdo,int $personId,string $status,string $reason,int $actor,string $revision): void
{
    $reason=trim($reason);
    if ($actor<1 || !in_array($status,['aktivni','cekajici','neaktivni','archiv','review'],true) || mb_strlen($reason)<10 || mb_strlen($reason)>1000) {
        throw new InvalidArgumentException('Vyberte stav a uveďte důvod rozhodnutí (10–1000 znaků).');
    }
    $pdo->beginTransaction();
    try {
        $stmt=$pdo->prepare('SELECT * FROM sportovci WHERE id=?'.($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''));
        $stmt->execute([$personId]);$old=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!$old || !hash_equals(sportovecMembershipRevision($old),$revision)) throw new RuntimeException('Stav osoby se změnil nebo osoba neexistuje. Obnovte stránku.');
        $newStatus=$status==='review'?$old['stav_clenstvi']:$status;
        $manual=$status==='review'?0:1;
        $pdo->prepare('UPDATE sportovci SET stav_clenstvi=?,stav_manualni=?,stav_duvod=?,stav_aktualizovan=CURRENT_TIMESTAMP WHERE id=?')->execute([$newStatus,$manual,$reason,$personId]);
        sportovecLogEvent($pdo,$personId,'status_manual',$status==='review'?'Členství předáno k ověření':'Výslovné rozhodnutí o členství',
            array_intersect_key($old,array_flip(['stav_clenstvi','stav_manualni','stav_duvod'])),
            ['stav_clenstvi'=>$newStatus,'stav_manualni'=>$manual,'stav_duvod'=>$reason],'manual',$actor);
        $pdo->commit();
    } catch (Throwable $e) { if($pdo->inTransaction())$pdo->rollBack(); throw $e; }
}

function sportovecStatusCompute(array $sportovec): array
{
    $manual = (int)($sportovec['stav_manualni'] ?? 0) === 1;
    $stored = (string)($sportovec['stav_clenstvi'] ?? 'cekajici');
    // Membership is a registrar decision. Payment, account access and roster
    // participation have their own lifecycle and cannot silently change it.
    $meta = sportovecStatusMeta($stored, $manual
        ? 'Evidovaný stav členství; účet, účast a úhrady se posuzují samostatně.'
        : 'Historicky evidovaný stav bez doloženého ručního rozhodnutí; ověřte u registráře.', $manual);
    $meta['verification'] = $manual ? 'recorded' : 'review';
    return $meta;
}

function sportovecStatusMeta(string $status, string $reason, bool $manual): array
{
    $map = [
        'aktivni' => ['label' => 'Aktivní', 'class' => 'bg-success'],
        'cekajici' => ['label' => 'Čekající', 'class' => 'bg-warning text-dark'],
        'neaktivni' => ['label' => 'Neaktivní', 'class' => 'bg-secondary'],
        'archiv' => ['label' => 'Archiv', 'class' => 'bg-dark'],
    ];
    $meta = $map[$status] ?? $map['cekajici'];
    $meta['status'] = isset($map[$status]) ? $status : 'cekajici';
    $meta['reason'] = $reason;
    $meta['manual'] = $manual;
    return $meta;
}

function sportovecStatusUpdate(PDO $pdo, int $sportovecId, array $sportovec): void
{
    // Compatibility entry point: never persist a status derived from payments.
    // Explicit audited membership changes remain in the registrar workflow.
}

function sportovecStatusBadge(array $sportovec): string
{
    $meta = sportovecStatusCompute($sportovec);
    $title = htmlspecialchars($meta['reason'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $label = htmlspecialchars($meta['label'] . ($meta['manual'] ? '' : ' · k ověření'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return '<span class="badge ' . $meta['class'] . '" title="' . $title . '">' . $label . '</span>';
}
