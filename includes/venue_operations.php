<?php
declare(strict_types=1);

function venueOperationAudit(
    PDO $pdo,
    string $targetType,
    int $targetId,
    int $actorTrainerId,
    string $action,
    string $reason,
    array $payload = []
): void {
    $reason = trim($reason);
    if (!in_array($targetType, ['lesson', 'venue_reservation'], true)
        || $targetId < 1 || $actorTrainerId < 1 || $reason === ''
        || mb_strlen($reason, 'UTF-8') > 1000
    ) throw new InvalidArgumentException('Audit provozu sportoviště nemá platné údaje.');
    $pdo->prepare(
        'INSERT INTO venue_operation_events(target_type,target_id,actor_trainer_id,action,reason,payload_json) VALUES(?,?,?,?,?,?)'
    )->execute([$targetType,$targetId,$actorTrainerId,$action,$reason,json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)]);
}

/** @return array{id:int,changed:bool} */
function venueReservationCancel(PDO $pdo,int $reservationId,int $actorTrainerId,bool $manageAll,string $reason,bool $confirmed):array
{
    $reason=trim($reason);
    if($reservationId<1||$actorTrainerId<1||$reason===''||!$confirmed||mb_strlen($reason,'UTF-8')>1000)throw new InvalidArgumentException('Zrušení rezervace vyžaduje důvod a výslovné potvrzení.');
    $pdo->beginTransaction();
    try{
        $sql='SELECT * FROM rezervace_sportovist WHERE id=?';if(!$manageAll)$sql.=' AND trener_id=?';if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$sql.=' FOR UPDATE';
        $st=$pdo->prepare($sql);$st->execute($manageAll?[$reservationId]:[$reservationId,$actorTrainerId]);$row=$st->fetch(PDO::FETCH_ASSOC);
        if(!$row)throw new RuntimeException('Rezervace nebyla nalezena nebo ji nesmíte spravovat.');
        $pdo->prepare('UPDATE planovane_treninky SET rezervace_id=NULL WHERE rezervace_id=?')->execute([$reservationId]);
        venueOperationAudit($pdo,'venue_reservation',$reservationId,$actorTrainerId,'cancel',$reason,['reservation'=>$row]);
        $pdo->prepare('DELETE FROM rezervace_sportovist WHERE id=?')->execute([$reservationId]);
        $pdo->commit();return['id'=>$reservationId,'changed'=>true];
    }catch(Throwable$exception){if($pdo->inTransaction())$pdo->rollBack();if($exception instanceof InvalidArgumentException||$exception instanceof RuntimeException)throw$exception;throw new RuntimeException('Rezervaci se nepodařilo bezpečně zrušit.',0,$exception);}
}
