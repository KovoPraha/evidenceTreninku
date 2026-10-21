<?php
declare(strict_types=1);

final class ShopSystemEmailException extends RuntimeException {}

/** Existing transactional events only. No template can create a new business event. */
function shopSystemEmailCatalog(): array
{
    $common = ['order_code'=>'Kód objednávky','customer_name'=>'Jméno zákazníka'];
    return [
        'shop_payment_received'=>[
            'label'=>'Přijatá platba','trigger'=>'Po ověřeném přijetí platby, jednou pro objednávku.',
            'subject'=>'Platba přijata – objednávka {order_code}',
            'body'=>"Dobrý den,\n\npotvrzujeme přijetí platby za objednávku {order_code}.\n\nDatum přijetí platby: {paid_at}\nPřijatá částka: {amount}\n\nPoložky objednávky:\n{items}\n\nCo se děje dál:\n{next_steps}\n\n{status_instruction}\n\nKlub KOVO Praha",
            'variables'=>$common+['paid_at'=>'Datum úhrady','amount'=>'Přijatá částka včetně měny','items'=>'Položky','next_steps'=>'Další postup podle obsahu objednávky','status_instruction'=>'Pokyny k otevření stavu objednávky'],
            'required'=>['order_code','amount','items','next_steps','status_instruction'],
        ],
        'shop_order_ready'=>[
            'label'=>'Připraveno k osobnímu odběru','trigger'=>'Po potvrzení přípravy osobního odběru. PPL ani Z-BOX tento e-mail neposílají.',
            'subject'=>'Objednávka {order_code} je připravena',
            'body'=>"Dobrý den,\n\nobjednávka {order_code} je připravena k osobnímu odběru.\n\nPoložky:\n{items}\n\nKlub KOVO Praha",
            'variables'=>$common+['items'=>'Položky'], 'required'=>['order_code','items'],
        ],
        'shop_ppl_dispatched'=>[
            'label'=>'Předání PPL','trigger'=>'Po zaznamenání skutečného předání zásilky PPL.',
            'subject'=>'Objednávka {order_code} předána PPL',
            'body'=>"Dobrý den,\n\nobjednávka {order_code} byla předána dopravci PPL.\nČíslo zásilky: {tracking_number}\nCílový box: {point_name} ({point_code})\nAdresa boxu: {point_address}\n\nDoručení do boxu a pokyny k vyzvednutí vám oznámí PPL. Aktuální stav objednávky uvidíte také přes odkaz z původního potvrzení.\n\nKlub KOVO Praha",
            'variables'=>$common+['tracking_number'=>'Číslo zásilky','point_name'=>'Název boxu','point_code'=>'Kód boxu','point_address'=>'Adresa boxu'],
            'required'=>['order_code','tracking_number'],
        ],
        'shop_packeta_dispatched'=>[
            'label'=>'Podání Zásilkovnou','trigger'=>'Až po potvrzení fyzického podání do Z-BOXu. Podací kód je interní a do zprávy nepatří.',
            'subject'=>'Objednávka {order_code} podána do Z-BOXu',
            'body'=>"Dobrý den,\n\nobjednávka {order_code} byla podána do sítě Zásilkovny.\nČíslo zásilky: {tracking_number}\nCílový Z-BOX: {point_name} ({point_code})\nAdresa Z-BOXu: {point_address}\n\nZásilkovna vám pošle pokyny k vyzvednutí. Aktuální stav objednávky najdete přes odkaz z původního potvrzení.\n\nKlub KOVO Praha",
            'variables'=>$common+['tracking_number'=>'Číslo zásilky','point_name'=>'Název Z-BOXu','point_code'=>'Kód cílového Z-BOXu','point_address'=>'Adresa Z-BOXu'],
            'required'=>['order_code','tracking_number'],
        ],
        'shop_guest_order_placed'=>[
            'label'=>'Nákup bez účtu – platební údaje','trigger'=>'Po vytvoření objednávky bez účtu. Bezpečný odkaz zákazník potřebuje pro přístup k objednávce.',
            'subject'=>'Objednávka {order_code} – platební údaje',
            'body'=>"Dobrý den,\n\nobjednávka {order_code} byla přijata.\nPoložka: {items}\n{delivery_instruction}\nČástka: {amount}\nVariabilní symbol: {variable_symbol}\nSplatnost: {due_at}\n\nBezpečný odkaz na objednávku a QR platbu:\n{order_url}\n\nKlub KOVO Praha",
            'variables'=>$common+['items'=>'Položka','delivery_instruction'=>'Způsob předání a cena dopravy','amount'=>'Částka včetně měny','variable_symbol'=>'Variabilní symbol','due_at'=>'Splatnost','order_url'=>'Bezpečný odkaz na objednávku'],
            'required'=>['order_code','amount','variable_symbol','due_at','order_url'],
        ],
        'shop_quick_program_order_placed'=>[
            'label'=>'Rychlá přihláška – platební údaje','trigger'=>'Po rychlé přihlášce. Odkazy k objednávce a ověření e-mailu musí zůstat v textu.',
            'subject'=>'Přihláška {order_code} – platební údaje',
            'body'=>"Dobrý den,\n\npřihláška {order_code} byla přijata a místo je dočasně rezervované.\n{payment_instruction}\n\nObjednávka a QR platba:\n{order_url}\n\nOvěření e-mailu pro správu přihlášky:\n{verification_url}\n\nKlub KOVO Praha",
            'variables'=>$common+['payment_instruction'=>'Souhrn přihlášky a platební údaje','order_url'=>'Bezpečný odkaz na objednávku','verification_url'=>'Ověřovací odkaz'],
            'required'=>['order_code','payment_instruction','order_url','verification_url'],
        ],
    ];
}

function shopSystemEmailTableExists(PDO $pdo, string $table): bool
{
    if (!in_array($table,['shop_system_email_templates','shop_system_email_versions'],true)) throw new InvalidArgumentException('Neplatná tabulka šablon.');
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql') {
        $q=$pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    } else $q=$pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");
    $q->execute([$table]); return (bool)$q->fetchColumn();
}

function shopSystemEmailCurrent(PDO $pdo, string $key): array
{
    $definition=shopSystemEmailCatalog()[$key]??throw new InvalidArgumentException('Neznámý systémový e-mail.');
    $result=['template_key'=>$key,'version'=>0,'subject_plain'=>$definition['subject'],'body_plain'=>$definition['body']];
    // Legacy test fixtures and pre-migration releases retain their existing texts.
    if (!shopSystemEmailTableExists($pdo,'shop_system_email_templates')) return $result;
    $q=$pdo->prepare('SELECT template_key,version,subject_plain,body_plain FROM shop_system_email_templates WHERE template_key=?');$q->execute([$key]);
    $row=$q->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new ShopSystemEmailException('Chybí systémová šablona; zkontrolujte migrace.');
    return (int)$row['version']===0?$result:$row;
}

function shopSystemEmailValidate(string $key, string $subject, string $body): void
{
    $definition=shopSystemEmailCatalog()[$key]??throw new InvalidArgumentException('Neznámý systémový e-mail.');
    if (trim($subject)===''||mb_strlen($subject,'UTF-8')>180||preg_match('/[\x00-\x1f\x7f]/u',$subject)) throw new InvalidArgumentException('Předmět vyplňte na jednom řádku, nejvýše 180 znaků.');
    if (trim($body)===''||mb_strlen($body,'UTF-8')>8000||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/u',$body)) throw new InvalidArgumentException('Text vyplňte, nejvýše 8000 znaků bez řídicích znaků.');
    $text=$subject."\n".$body;
    preg_match_all('/\{([a-z_]+)\}/',$text,$matches);
    foreach ($matches[1] as $name) if (!isset($definition['variables'][$name])) throw new InvalidArgumentException('Nepovolená proměnná {'.$name.'}.');
    if (strpbrk((string)preg_replace('/\{[a-z_]+\}/','',$text),'{}')!==false) throw new InvalidArgumentException('Proměnné zapisujte přesně ve tvaru {order_code}.');
    foreach ($definition['required'] as $name) if (!str_contains($body,'{'.$name.'}')) throw new InvalidArgumentException('V textu musí zůstat proměnná {'.$name.'}.');
}

function shopSystemEmailRender(array $template, array $values): array
{
    $key=(string)$template['template_key'];
    shopSystemEmailValidate($key,(string)$template['subject_plain'],(string)$template['body_plain']);
    $replacements=[];
    foreach (shopSystemEmailCatalog()[$key]['variables'] as $name=>$description) {
        if (str_contains($template['subject_plain'].' '.$template['body_plain'],'{'.$name.'}')&&!array_key_exists($name,$values)) throw new ShopSystemEmailException('Chybí podklad pro proměnnou {'.$name.'}.');
        $replacements['{'.$name.'}']=(string)($values[$name]??'');
    }
    $subject=strtr($template['subject_plain'],$replacements);$body=strtr($template['body_plain'],$replacements);
    if (mb_strlen($subject,'UTF-8')>255||preg_match('/[\x00-\x1f\x7f]/u',$subject)||str_contains($body,"\0")||mb_strlen($body,'UTF-8')>100000) throw new ShopSystemEmailException('Údaje nelze bezpečně vložit do systémového e-mailu.');
    return $template+['subject'=>$subject,'body'=>$body];
}

function shopSystemEmailMessage(PDO $pdo, string $key, array $values): array
{
    return shopSystemEmailRender(shopSystemEmailCurrent($pdo,$key),$values);
}

function shopSystemEmailSave(PDO $pdo, string $key, int $actorId, int $expectedVersion, string $subject, string $body, string $reason): array
{
    $subject=trim($subject);$body=trim(str_replace("\r\n","\n",$body));$reason=trim($reason);
    shopSystemEmailValidate($key,$subject,$body);
    if ($actorId<1||$expectedVersion<0||$reason===''||mb_strlen($reason,'UTF-8')>1000) throw new InvalidArgumentException('Uveďte autora, verzi a důvod změny do 1000 znaků.');
    $pdo->beginTransaction();
    try {
        $q=$pdo->prepare('SELECT * FROM shop_system_email_templates WHERE template_key=?'.($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''));$q->execute([$key]);$row=$q->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new ShopSystemEmailException('Chybí šablona. Zkontrolujte migrace.');
        if ((int)$row['version']!==$expectedVersion) throw new ShopSystemEmailException('Šablonu mezitím upravil někdo jiný. Obnovte stránku a porovnejte text.');
        $current=shopSystemEmailCurrent($pdo,$key);
        if ($subject===$current['subject_plain']&&$body===$current['body_plain']) {$pdo->commit();return ['version'=>$expectedVersion,'changed'=>false];}
        $version=$expectedVersion+1;
        $pdo->prepare('INSERT INTO shop_system_email_versions(template_key,version,subject_plain,body_plain,created_by_trainer_id,reason) VALUES(?,?,?,?,?,?)')->execute([$key,$version,$subject,$body,$actorId,$reason]);
        $pdo->prepare('UPDATE shop_system_email_templates SET version=?,subject_plain=?,body_plain=? WHERE template_key=?')->execute([$version,$subject,$body,$key]);
        $pdo->commit();return ['version'=>$version,'changed'=>true];
    } catch (Throwable $e) {if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

function shopSystemEmailSampleValues(): array
{
    return ['order_code'=>'LOCALHOST-NEPLATIT','customer_name'=>'Testovací zákazník','paid_at'=>'21. 10. 2026 10:00','amount'=>'250,00 CZK','items'=>'- LOCALHOST tričko × 1','next_steps'=>'- Zboží klub připraví k osobnímu odběru.','status_instruction'=>'Ukázka: stav najdete ve svém účtu.','tracking_number'=>'Z1234567890','point_name'=>'LOCALHOST ukázkový box','point_code'=>'TEST-BOX','point_address'=>'Ukázková adresa 1','delivery_instruction'=>'Předání: osobní odběr','variable_symbol'=>'99900001','due_at'=>'28. 10. 2026','order_url'=>'https://example.invalid/objednavka','verification_url'=>'https://example.invalid/overeni','payment_instruction'=>'LOCALHOST kroužek. Částka 250,00 CZK, VS 99900001, NEPLATIT.'];
}

/** Attach provenance to an already inserted outbox row without changing queued text. */
function shopSystemEmailRecord(PDO $pdo, int $notificationId, array $message): void
{
    if (!$pdo->inTransaction()) throw new LogicException('Verze e-mailu vyžaduje transakci oznámení.');
    if (!shopSystemEmailTableExists($pdo,'shop_system_email_templates')) return;
    $pdo->prepare('UPDATE club_event_notifications SET system_template_key=?,system_template_version=? WHERE id=? AND system_template_key IS NULL')->execute([$message['template_key'],(int)$message['version'],$notificationId]);
}
