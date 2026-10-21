<?php
declare(strict_types=1);

/** Compatibility for isolated pre-feature test databases; deployed releases migrate before activation. */
function clubConsentRegistryAvailable(PDO $pdo): bool
{
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql') {
        $q=$pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='club_registration_consents'");
        return (bool)$q->fetchColumn();
    }
    return (bool)$pdo->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='club_registration_consents'")->fetchColumn();
}

/** @return list<array<string,mixed>> */
function clubConsentCurrent(PDO $pdo, bool $includeHidden=false, bool $lock=false): array
{
    if (!clubConsentRegistryAvailable($pdo)) return [];
    $suffix=$lock && $pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
    // Lock stable definitions before reading the latest version, shared with
    // the publishing transaction. A checkout never mixes two policy versions.
    if ($suffix!=='') $pdo->query('SELECT id FROM club_registration_consents ORDER BY id'.$suffix)->fetchAll();
    $rows=$pdo->query("SELECT t.*,c.id AS custom_consent_id FROM club_registration_consents c JOIN club_event_term_versions t ON t.id=c.current_term_id ORDER BY c.id".$suffix)->fetchAll(PDO::FETCH_ASSOC);
    return array_values(array_filter($rows,static fn(array $r):bool=>$includeHidden||(int)$r['custom_visible']===1));
}

function clubConsentManifest(array $terms): string
{
    return hash('sha256',json_encode(array_map(static fn(array $r):array=>[(int)$r['id'],(int)$r['custom_required']],$terms),JSON_THROW_ON_ERROR));
}

/** @return array{manifest:string,accepted:array} */
function clubConsentInput(array $input): array
{
    return ['manifest'=>is_string($input['club_consent_manifest']??null)?$input['club_consent_manifest']:'',
        'accepted'=>is_array($input['club_consent_accepted']??null)?$input['club_consent_accepted']:[]];
}

/** Must run inside the order transaction, after replay detection and before any account/order writes. */
function clubConsentValidate(PDO $pdo, array $input): array
{
    if (!$pdo->inTransaction()) throw new LogicException('Kontrola souhlasů vyžaduje transakci objednávky.');
    $terms=clubConsentCurrent($pdo,false,true);
    $posted=clubConsentInput($input);
    if ($terms===[] && $posted['manifest']==='') return [];
    if (!hash_equals(clubConsentManifest($terms),$posted['manifest'])) throw new InvalidArgumentException('Souhlasy k přihlášce se změnily. Obnovte stránku a zkontrolujte aktuální znění.');
    $result=[];
    foreach($terms as $term) {
        $accepted=($posted['accepted'][(int)$term['id']]??'')==='1';
        if ((int)$term['custom_required']===1 && !$accepted) throw new InvalidArgumentException('Potvrďte povinný souhlas: '.$term['title_plain']);
        $term['accepted']=$accepted; $result[]=$term;
    }
    return $result;
}

function clubConsentStore(PDO $pdo,int $orderItemId,int $accountId,array $terms):void
{
    if ($terms===[]) return;
    if (!$pdo->inTransaction()) throw new LogicException('Uložení souhlasů vyžaduje transakci objednávky.');
    $q=$pdo->prepare('INSERT INTO club_registration_consent_acceptances(order_item_id,account_id,term_version_id,title_snapshot,text_snapshot,required_snapshot,accepted) VALUES(?,?,?,?,?,?,?)');
    foreach($terms as $t) $q->execute([$orderItemId,$accountId,(int)$t['id'],$t['title_plain'],$t['consent_text_plain'],(int)$t['custom_required'],!empty($t['accepted'])?1:0]);
}

/** One shared field set for the anonymous application and the existing-athlete checkout. */
function clubConsentFields(PDO $pdo,array $posted=[]):void
{
    $terms=clubConsentCurrent($pdo);
    if ($terms===[]) return;
    $h=static fn(mixed $v):string=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    // A redisplayed form after a version change must never carry acceptance
    // forward silently. Checkboxes belong to a particular rendered manifest.
    $prior=clubConsentInput($posted);$same=hash_equals(clubConsentManifest($terms),$prior['manifest']);
    echo '<fieldset class="my-3"><legend class="h5">Další souhlasy k přihlášce</legend><input type="hidden" name="club_consent_manifest" value="'.$h(clubConsentManifest($terms)).'">';
    foreach($terms as $t) {
        $id=(int)$t['id'];$required=(int)$t['custom_required']===1;
        echo '<div class="border rounded p-3 mb-2 small"><strong class="text-break">'.$h($t['title_plain']).'</strong> <span class="badge text-bg-secondary">'.($required?'Povinný':'Volitelný').'</span><div class="my-2 text-break">'.nl2br($h($t['consent_text_plain'])).'</div>';
        echo '<div class="form-check"><input type="checkbox" class="form-check-input" name="club_consent_accepted['.$id.']" id="club-consent-'.$id.'" value="1"'.($required?' required':'').($same&&($prior['accepted'][$id]??'')==='1'?' checked':'').'><label class="form-check-label" for="club-consent-'.$id.'">Souhlasím: '.$h($t['title_plain']).'</label></div></div>';
    }
    echo '</fieldset>';
}
