<?php
declare(strict_types=1);

final class ShopOrderCustomerStatusException extends RuntimeException {}

/** @return list<array<string,mixed>> */
function shopOrderCustomerStatusDefinitions(PDO $pdo, bool $activeOnly = false): array
{
    $sql = 'SELECT * FROM shop_order_status_definitions' . ($activeOnly ? ' WHERE active=1' : '') . ' ORDER BY active DESC,label,id';
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

function shopOrderCustomerStatusLabel(array $order): string
{
    $canonical = (string)($order['status'] ?? '');
    if (in_array($canonical, ['ready','completed','cancelled'], true)) {
        if (($order['fulfillment_method']??'')==='ppl_box') return ['ready'=>'Připraveno k odeslání','completed'=>'Předáno PPL','cancelled'=>'Stornováno'][$canonical];
        if (($order['fulfillment_method']??'')==='packeta_zbox') return ['ready'=>'Připraveno k odeslání','completed'=>'Podáno do Z-BOXu','cancelled'=>'Stornováno'][$canonical];
        return ['ready'=>'Připraveno k odběru','completed'=>'Vydáno','cancelled'=>'Stornováno'][$canonical];
    }
    $custom = trim((string)($order['customer_status_label'] ?? ''));
    if ($custom !== '') return $custom;
    return $canonical === 'processing' ? 'Připravuje se' : 'Vytvořená objednávka';
}

/** Actual lifecycle label, never overridden by a customer-facing note. */
function shopOrderSystemStatusLabel(array $order): string
{
    unset($order['customer_status_label']);
    return shopOrderCustomerStatusLabel($order);
}

function shopOrderSystemStatusDefinitions(): array
{
    return [
        ['label'=>'Vytvořená objednávka','effect'=>'Vznikne při nákupu. Čeká na ověřenou úhradu.','email'=>'Platební údaje pro nákup bez účtu a rychlou přihlášku.'],
        ['label'=>'Připravuje se','effect'=>'Vznikne po potvrzení platby. Kroužek se aktivuje, nebo čeká na schválení údajů sportovce.','email'=>'Potvrzení přijaté platby.'],
        ['label'=>'Připraveno k odběru / odeslání','effect'=>'Obsluha potvrdí přípravu zaplaceného zboží. Kroužek nemá výdejový stav.','email'=>'Osobní odběr ano; příprava PPL a Z-BOXu bez e-mailu.'],
        ['label'=>'Vydáno / Předáno PPL / Podáno do Z-BOXu','effect'=>'Obsluha potvrdí skutečný výdej nebo expedici. Předání dopravci nepotvrzuje doručení zákazníkovi.','email'=>'PPL a Zásilkovna posílají sledovací údaje; osobní výdej bez další zprávy.'],
        ['label'=>'Stornováno','effect'=>'Storno vrátí sklad. U zaplacené objednávky vznikne požadavek na samostatnou vratku.','email'=>'Storno v tomto toku nemá samostatný e-mail.'],
    ];
}

/** Select one real fulfillment action; carriers must never fall through to pickup. */
function shopOrderFulfillmentAction(array $order, bool $hasProgram): ?string
{
    if ($hasProgram) return null;
    if (($order['status']??'')==='processing') return 'ready';
    if (($order['status']??'')!=='ready') return null;
    return match($order['fulfillment_method']??'personal_pickup') {
        'personal_pickup'=>'complete',
        'ppl_box'=>'dispatch_ppl',
        'packeta_zbox'=>trim((string)($order['packeta_consignment_code']??''))===''?'prepare_packeta':'deposit_packeta',
        default=>null,
    };
}

/** @return array<string,mixed> */
function shopOrderCustomerStatusAttach(PDO $pdo, array $order): array
{
    $order['customer_status_label'] = null;
    if ((int)($order['customer_status_id'] ?? 0) > 0) {
        $statement = $pdo->prepare('SELECT label FROM shop_order_status_definitions WHERE id=?');
        $statement->execute([(int)$order['customer_status_id']]);
        $order['customer_status_label'] = $statement->fetchColumn() ?: null;
    }
    return $order;
}

/** @return array<string,mixed> */
function shopOrderCustomerStatusInput(array $input): array
{
    $label = trim((string)($input['label'] ?? ''));
    if ($label === '' || mb_strlen($label,'UTF-8') > 80 || preg_match('/[\x00-\x1F\x7F]/u',$label)) {
        throw new InvalidArgumentException('Název stavu musí mít nejvýše 80 znaků a nesmí obsahovat řídicí znaky.');
    }
    if (mb_strtolower($label,'UTF-8') === mb_strtolower('Vytvořená objednávka','UTF-8')) {
        throw new InvalidArgumentException('Výchozí stav Vytvořená objednávka již existuje.');
    }
    $enabled = !empty($input['email_enabled']) ? 1 : 0;
    $subject = trim((string)($input['email_subject'] ?? ''));
    $body = trim((string)($input['email_body'] ?? ''));
    if ($enabled && ($subject === '' || $body === '')) throw new InvalidArgumentException('Pro automatický e-mail vyplňte předmět i text.');
    if (mb_strlen($subject,'UTF-8') > 180 || preg_match('/[\r\n\x00-\x1F\x7F]/u',$subject)) {
        throw new InvalidArgumentException('Předmět e-mailu smí mít nejvýše 180 znaků a jeden řádek.');
    }
    if (mb_strlen($body,'UTF-8') > 5000 || str_contains($body,"\0")) {
        throw new InvalidArgumentException('Text e-mailu smí mít nejvýše 5000 znaků.');
    }
    return ['label'=>$label,'active'=>!empty($input['active']) ? 1 : 0,'email_enabled'=>$enabled,'email_subject'=>$subject !== '' ? $subject : null,'email_body'=>$body !== '' ? $body : null];
}

/** @return array{id:int,changed:bool} */
function shopOrderCustomerStatusSave(PDO $pdo, int $actorId, int $definitionId, array $input, string $reason): array
{
    $reason = trim($reason);
    if ($actorId < 1 || $definitionId < 0 || $reason === '' || mb_strlen($reason,'UTF-8') > 1000) {
        throw new InvalidArgumentException('Uveďte administrátora a stručný důvod změny stavu.');
    }
    $values = shopOrderCustomerStatusInput($input);
    $pdo->beginTransaction();
    try {
        $before = null;
        if ($definitionId > 0) {
            $sql = 'SELECT * FROM shop_order_status_definitions WHERE id=?';
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') $sql .= ' FOR UPDATE';
            $statement = $pdo->prepare($sql);$statement->execute([$definitionId]);$before = $statement->fetch(PDO::FETCH_ASSOC);
            if (!$before) throw new ShopOrderCustomerStatusException('Stav nebyl nalezen.');
            if ($values['label'] !== $before['label']) throw new InvalidArgumentException('Název již použitého stavu se nemění. Založte nový stav a původní deaktivujte.');
            $changed = false;
            foreach (['active','email_enabled','email_subject','email_body'] as $key) {
                if ((string)($before[$key] ?? '') !== (string)($values[$key] ?? '')) $changed = true;
            }
            if ($changed) {
                $pdo->prepare('UPDATE shop_order_status_definitions SET active=?,email_enabled=?,email_subject=?,email_body=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')
                    ->execute([$values['active'],$values['email_enabled'],$values['email_subject'],$values['email_body'],$definitionId]);
            }
        } else {
            $values['active'] = 1;
            $pdo->prepare('INSERT INTO shop_order_status_definitions(label,active,email_enabled,email_subject,email_body,created_by_trainer_id) VALUES(?,?,?,?,?,?)')
                ->execute([$values['label'],1,$values['email_enabled'],$values['email_subject'],$values['email_body'],$actorId]);
            $definitionId = (int)$pdo->lastInsertId();
            $changed = true;
        }
        if ($changed) {
            $afterStatement = $pdo->prepare('SELECT * FROM shop_order_status_definitions WHERE id=?');$afterStatement->execute([$definitionId]);
            $pdo->prepare('INSERT INTO shop_order_status_definition_events(definition_id,actor_trainer_id,action,before_json,after_json,reason) VALUES(?,?,?,?,?,?)')
                ->execute([$definitionId,$actorId,$before === null ? 'create' : 'update', $before === null ? null : json_encode($before,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),json_encode($afterStatement->fetch(PDO::FETCH_ASSOC),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$reason]);
        }
        $pdo->commit();
        return ['id'=>$definitionId,'changed'=>$changed];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($exception instanceof InvalidArgumentException || $exception instanceof ShopOrderCustomerStatusException || $exception instanceof PDOException) throw $exception;
        throw new ShopOrderCustomerStatusException('Stav nebylo možné bezpečně uložit.',0,$exception);
    }
}

function shopOrderCustomerStatusRenderTemplate(string $template, array $order, string $label): string
{
    return strtr($template,[
        '{order_code}'=>(string)$order['public_code'],
        '{customer_name}'=>(string)$order['customer_name_snapshot'],
        '{status}'=>$label,
    ]);
}

/** @return array{order_id:int,changed:bool,notification_id:int|null} */
function shopOrderCustomerStatusAssign(PDO $pdo, int $orderId, int $definitionId, int $actorId, string $reason): array
{
    $reason = trim($reason);
    if ($orderId < 1 || $definitionId < 0 || $actorId < 1 || $reason === '' || mb_strlen($reason,'UTF-8') > 1000) {
        throw new InvalidArgumentException('Vyberte objednávku a stav a napište důvod změny.');
    }
    $pdo->beginTransaction();
    try {
        $sql = 'SELECT * FROM shop_orders WHERE id=?';
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') $sql .= ' FOR UPDATE';
        $statement = $pdo->prepare($sql);$statement->execute([$orderId]);$order = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$order) throw new ShopOrderCustomerStatusException('Objednávka nebyla nalezena.');
        if (!in_array($order['status'],['placed','processing'],true)) throw new ShopOrderCustomerStatusException('U dokončené, připravené nebo stornované objednávky již nelze měnit zákaznický stav.');
        if ((int)($order['customer_status_id'] ?? 0) === $definitionId) {
            $pdo->commit();return ['order_id'=>$orderId,'changed'=>false,'notification_id'=>null];
        }
        $definition = null;
        if ($definitionId > 0) {
            $sql = 'SELECT * FROM shop_order_status_definitions WHERE id=?';
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') $sql .= ' FOR UPDATE';
            $statement = $pdo->prepare($sql);$statement->execute([$definitionId]);$definition = $statement->fetch(PDO::FETCH_ASSOC);
            if (!$definition || (int)$definition['active'] !== 1) throw new ShopOrderCustomerStatusException('Vybraný stav už není aktivní. Obnovte stránku.');
        }
        $pdo->prepare('UPDATE shop_orders SET customer_status_id=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')
            ->execute([$definitionId > 0 ? $definitionId : null,$orderId]);
        $pdo->prepare('INSERT INTO shop_order_events(order_id,actor_type,actor_id,action,from_status,to_status,note) VALUES(?,\'trainer\',?,\'set_customer_status\',?,?,?)')
            ->execute([$orderId,$actorId,'customer:'.(int)($order['customer_status_id'] ?? 0),'customer:'.$definitionId,$reason]);
        $eventId = (int)$pdo->lastInsertId();
        $notificationId = null;
        if ($definition !== null && (int)$definition['email_enabled'] === 1) {
            if (!filter_var((string)$order['customer_email_snapshot'],FILTER_VALIDATE_EMAIL)) {
                throw new ShopOrderCustomerStatusException('Zákazník nemá platný e-mail; stav nebyl změněn.');
            }
            $subject = shopOrderCustomerStatusRenderTemplate((string)$definition['email_subject'],$order,(string)$definition['label']);
            $body = shopOrderCustomerStatusRenderTemplate((string)$definition['email_body'],$order,(string)$definition['label']);
            if (mb_strlen($subject,'UTF-8') > 255 || preg_match('/[\r\n\x00-\x1F\x7F]/u',$subject)) {
                throw new ShopOrderCustomerStatusException('Údaje zákazníka nelze bezpečně vložit do předmětu e-mailu; stav nebyl změněn.');
            }
            $pdo->prepare('INSERT INTO club_event_notifications(registration_id,registration_event_id,order_id,notification_type,recipient_email,recipient_name,subject_plain,body_plain) VALUES(NULL,NULL,?,?,?,?,?,?)')
                ->execute([$orderId,'shop_status_'.$eventId,(string)$order['customer_email_snapshot'],trim((string)$order['customer_name_snapshot']),$subject,$body]);
            $notificationId = (int)$pdo->lastInsertId();
        }
        $pdo->commit();return ['order_id'=>$orderId,'changed'=>true,'notification_id'=>$notificationId];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($exception instanceof InvalidArgumentException || $exception instanceof ShopOrderCustomerStatusException || $exception instanceof PDOException) throw $exception;
        throw new ShopOrderCustomerStatusException('Změna stavu selhala bez částečného zápisu.',0,$exception);
    }
}
