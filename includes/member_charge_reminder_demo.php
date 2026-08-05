<?php
declare(strict_types=1);

require_once __DIR__ . '/member_charge_reminder.php';

/** @return array{charge_id:int,reminder_id:int,account_id:int,sportovec_id:int} */
function memberChargeReminderSeedLocalDemo(PDO $pdo, int $actorTrainerId, bool $confirmed, bool $isLocal): array
{
    if (!$isLocal) throw new MemberChargeReminderException('Testovací ukázku lze připravit pouze na localhostu.');
    if ($actorTrainerId < 1 || !$confirmed) throw new InvalidArgumentException('Přípravu testovací ukázky musíte výslovně potvrdit.');
    foreach (['verejni_uzivatele', 'sportovci', 'account_person_roles', 'club_member_charges',
        'club_member_charge_events', 'member_charge_reminder_preferences', 'member_charge_reminders',
        'member_charge_reminder_events'] as $table) {
        if (!memberChargeReminderTableExists($pdo, $table)) {
            throw new MemberChargeReminderException('Testovací databáze nemá potřebnou strukturu.');
        }
    }

    $pdo->beginTransaction();
    try {
        $person = $pdo->query(
            "SELECT a.id AS account_id,a.email,a.jmeno AS account_first_name,a.prijmeni AS account_last_name,"
            . "s.id AS sportovec_id,s.jmeno AS child_first_name,s.prijmeni AS child_last_name "
            . "FROM verejni_uzivatele a JOIN account_person_roles r ON r.account_id=a.id "
            . "JOIN sportovci s ON s.id=r.sportovec_id WHERE a.email='rodic@localhost.test' "
            . "AND a.aktivni=1 AND a.email_overeno=1 AND r.status='approved' "
            . "AND r.relation_role IN ('self','guardian') AND r.valid_from<=CURRENT_TIMESTAMP "
            . "AND (r.valid_to IS NULL OR r.valid_to>CURRENT_TIMESTAMP) ORDER BY s.id LIMIT 1"
        )->fetch(PDO::FETCH_ASSOC);
        if (!$person) throw new MemberChargeReminderException('Nejdříve obnovte standardní localhost demo data.');

        $accountId = (int)$person['account_id'];
        $sportovecId = (int)$person['sportovec_id'];
        $dueOn = (new DateTimeImmutable('today', new DateTimeZone('Europe/Prague')))->modify('+7 days')->format('Y-m-d');
        $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            $pdo->prepare(
                'INSERT INTO member_charge_reminder_preferences(account_id,enabled,days_before) VALUES(?,1,7) '
                . 'ON DUPLICATE KEY UPDATE enabled=1,days_before=7,updated_at=CURRENT_TIMESTAMP'
            )->execute([$accountId]);
        } else {
            $pdo->prepare(
                'INSERT INTO member_charge_reminder_preferences(account_id,enabled,days_before) VALUES(?,1,7) '
                . 'ON CONFLICT(account_id) DO UPDATE SET enabled=1,days_before=7,updated_at=CURRENT_TIMESTAMP'
            )->execute([$accountId]);
        }
        memberChargeReminderAudit($pdo, null, $accountId, 'localhost_demo_opt_in', null, 'enabled', 'Administrátor zapnul připomínky pouze syntetickému localhost účtu.', 'trainer', $actorTrainerId);

        $chargeStatement = $pdo->prepare("SELECT id,status FROM club_member_charges WHERE source_system='localhost_demo' AND source_external_id='reminder-v1'");
        $chargeStatement->execute();
        $charge = $chargeStatement->fetch(PDO::FETCH_ASSOC);
        if (!$charge) {
            $pdo->prepare(
                'INSERT INTO club_member_charges(sportovec_id,payer_account_id,public_code,charge_type,title_snapshot,period_from,period_to,'
                . "amount_minor,currency,due_on,status,source_system,source_external_id,source_import_run_id) "
                . "VALUES(?,?,'LOCAL-REMINDER-001','membership','LOCALHOST – testovací členský příspěvek',NULL,NULL,125000,'CZK',?,'pending','localhost_demo','reminder-v1',NULL)"
            )->execute([$sportovecId, $accountId, $dueOn]);
            $chargeId = (int)$pdo->lastInsertId();
            $fromStatus = null;
        } else {
            $chargeId = (int)$charge['id'];
            $fromStatus = (string)$charge['status'];
            $pdo->prepare(
                "UPDATE club_member_charges SET sportovec_id=?,payer_account_id=?,title_snapshot='LOCALHOST – testovací členský příspěvek',"
                . "amount_minor=125000,currency='CZK',due_on=?,status='pending',updated_at=CURRENT_TIMESTAMP WHERE id=?"
            )->execute([$sportovecId, $accountId, $dueOn, $chargeId]);
        }
        $snapshot = json_encode(['schema' => 'localhost-member-charge-reminder-v1', 'due_on' => $dueOn, 'amount_minor' => 125000, 'currency' => 'CZK'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $pdo->prepare(
            "INSERT INTO club_member_charge_events(charge_id,action,from_status,to_status,actor_type,actor_id,reason,snapshot_json) "
            . "VALUES(?,'localhost_demo_reset',?,'pending','trainer',?,'Příprava testovací ukázky připomínky.',?)"
        )->execute([$chargeId, $fromStatus, $actorTrainerId, $snapshot]);

        $message = memberChargeReminderComposeMessage([
            ...$person,
            'title_snapshot' => 'LOCALHOST – testovací členský příspěvek',
            'amount_minor' => 125000,
            'currency' => 'CZK',
            'due_on' => $dueOn,
        ]);
        $reminderStatement = $pdo->prepare("SELECT id,status FROM member_charge_reminders WHERE charge_id=? AND account_id=? AND reminder_type='due_soon'");
        $reminderStatement->execute([$chargeId, $accountId]);
        $reminder = $reminderStatement->fetch(PDO::FETCH_ASSOC);
        if (!$reminder) {
            $pdo->prepare(
                "INSERT INTO member_charge_reminders(charge_id,account_id,reminder_type,recipient_email,recipient_name,subject_plain,body_plain,status,attempts,available_at) "
                . "VALUES(?,?,'due_soon',?,?,?,?, 'pending',0,CURRENT_TIMESTAMP)"
            )->execute([$chargeId, $accountId, (string)$person['email'], $message['recipient_name'], $message['subject'], $message['body']]);
            $reminderId = (int)$pdo->lastInsertId();
            $reminderFrom = null;
        } else {
            $reminderId = (int)$reminder['id'];
            $reminderFrom = (string)$reminder['status'];
            $pdo->prepare(
                "UPDATE member_charge_reminders SET recipient_email=?,recipient_name=?,subject_plain=?,body_plain=?,status='pending',attempts=0,"
                . 'available_at=CURRENT_TIMESTAMP,claimed_at=NULL,claim_token=NULL,sent_at=NULL,cancelled_at=NULL,last_error=NULL,updated_at=CURRENT_TIMESTAMP WHERE id=?'
            )->execute([(string)$person['email'], $message['recipient_name'], $message['subject'], $message['body'], $reminderId]);
        }
        memberChargeReminderAudit($pdo, $reminderId, $accountId, 'localhost_demo_reset', $reminderFrom, 'pending', 'Administrátor připravil syntetickou ukázku bez odeslání.', 'trainer', $actorTrainerId);
        $pdo->commit();
        return ['charge_id' => $chargeId, 'reminder_id' => $reminderId, 'account_id' => $accountId, 'sportovec_id' => $sportovecId];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
}
