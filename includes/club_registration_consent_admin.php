<?php
declare(strict_types=1);

require_once __DIR__.'/athlete_registration_texts.php';
require_once __DIR__.'/club_registration_consents.php';

function clubConsentValidateDocument(string $title,string $text,string $reason):array
{
    $title=trim($title);$text=trim($text);$reason=trim($reason);
    if($title===''||mb_strlen($title)>200||preg_match('/[\x00-\x1f\x7f]/u',$title)===1) throw new InvalidArgumentException('Vyplňte název do 200 znaků na jednom řádku.');
    if($text===''||mb_strlen($text)>12000||$reason===''||mb_strlen($reason)>1000) throw new InvalidArgumentException('Vyplňte text do 12 000 znaků a důvod změny do 1 000 znaků.');
    return [$title,$text,$reason];
}

function clubConsentDefinition(PDO $pdo,int $id,bool $lock=false):array
{
    $q=$pdo->prepare('SELECT * FROM club_registration_consents WHERE id=?'.($lock&&$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''));
    $q->execute([$id]);$row=$q->fetch(PDO::FETCH_ASSOC);
    if(!$row) throw new InvalidArgumentException('Vlastní souhlas nebyl nalezen.');
    return $row;
}

function clubConsentTerm(PDO $pdo,int $id):array
{
    $q=$pdo->prepare('SELECT t.* FROM club_registration_consents c JOIN club_event_term_versions t ON t.id=c.current_term_id WHERE c.id=?');
    $q->execute([$id]);$row=$q->fetch(PDO::FETCH_ASSOC);
    if(!$row) throw new InvalidArgumentException('Znění vlastního souhlasu není dostupné.');
    return $row;
}

function clubConsentInsertTerm(PDO $pdo,array $definition,string $version,string $title,string $text,bool $required,bool $visible,int $actor):int
{
    $q=$pdo->prepare("INSERT INTO club_event_term_versions(scope_type,scope_key,consent_purpose,event_id,terms_version,consent_text_plain,actor_trainer_id,actor_type,actor_id,status,title_plain,custom_required,custom_visible) VALUES('club_registration','all-programs',?,NULL,?,?,?,'trainer',?,'active',?,?,?)");
    $q->execute([$definition['purpose'],$version,$text,$actor,$actor,$title,$required?1:0,$visible?1:0]);
    return (int)$pdo->lastInsertId();
}

function clubConsentDraftInsert(PDO $pdo,array $definition,string $title,string $text,string $reason,bool $required,bool $visible,int $actor):int
{
    $q=$pdo->prepare('INSERT INTO athlete_registration_text_drafts(purpose,text_plain,reason,base_term_id,created_by,title_plain,custom_consent_id,custom_required,custom_visible) VALUES(?,?,?,?,?,?,?,?,?)');
    $q->execute([$definition['purpose'],$text,$reason,$definition['current_term_id'],$actor,$title,$definition['id'],$required?1:0,$visible?1:0]);
    return (int)$pdo->lastInsertId();
}

/** New documents are hidden until the registrar explicitly publishes the saved draft. */
function clubConsentCreate(PDO $pdo,string $title,string $text,string $reason,bool $required,bool $visible):array
{
    $actor=registrationTextsRequireRegistrar();[$title,$text,$reason]=clubConsentValidateDocument($title,$text,$reason);
    if($pdo->inTransaction()) throw new LogicException('Vytvoření souhlasu vyžaduje samostatnou transakci.');
    $pdo->beginTransaction();
    try {
        $purpose='custom_'.bin2hex(random_bytes(16));
        $pdo->prepare('INSERT INTO club_registration_consents(purpose,created_by) VALUES(?,?)')->execute([$purpose,$actor]);
        $id=(int)$pdo->lastInsertId();$definition=['id'=>$id,'purpose'=>$purpose];
        $term=clubConsentInsertTerm($pdo,$definition,'custom-created-'.$id,$title,$text,$required,false,$actor);
        $pdo->prepare('UPDATE club_registration_consents SET current_term_id=? WHERE id=?')->execute([$term,$id]);
        $definition['current_term_id']=$term;
        $draft=clubConsentDraftInsert($pdo,$definition,$title,$text,$reason,$required,$visible,$actor);
        $pdo->commit();return ['id'=>$id,'draft_id'=>$draft];
    } catch(Throwable $e) { if($pdo->inTransaction())$pdo->rollBack();throw $e; }
}

function clubConsentSaveDraft(PDO $pdo,int $id,int $baseTermId,string $title,string $text,string $reason,bool $required,bool $visible):int
{
    $actor=registrationTextsRequireRegistrar();[$title,$text,$reason]=clubConsentValidateDocument($title,$text,$reason);
    $definition=clubConsentDefinition($pdo,$id);
    if((int)$definition['current_term_id']!==$baseTermId) throw new InvalidArgumentException('Mezitím se souhlas změnil. Porovnejte aktuální verzi a uložte nový koncept.');
    return clubConsentDraftInsert($pdo,$definition,$title,$text,$reason,$required,$visible,$actor);
}

function clubConsentPublish(PDO $pdo,int $id,int $draftId,bool $confirmed):int
{
    $actor=registrationTextsRequireRegistrar();
    if(!$confirmed)throw new InvalidArgumentException('Výslovně potvrďte zveřejnění nebo skrytí uloženého konceptu.');
    if($pdo->inTransaction())throw new LogicException('Zveřejnění souhlasu vyžaduje samostatnou transakci.');
    $pdo->beginTransaction();
    try {
        $definition=clubConsentDefinition($pdo,$id,true);
        $q=$pdo->prepare('SELECT * FROM athlete_registration_text_drafts WHERE id=? AND custom_consent_id=?'.($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''));
        $q->execute([$draftId,$id]);$draft=$q->fetch(PDO::FETCH_ASSOC);
        if(!$draft)throw new InvalidArgumentException('Koncept nepatří k tomuto souhlasu.');
        if($draft['published_term_id']!==null){$pdo->commit();return (int)$draft['published_term_id'];}
        if((int)$draft['base_term_id']!==(int)$definition['current_term_id'])throw new InvalidArgumentException('Koncept vychází ze starší verze. Zkontrolujte aktuální souhlas a uložte nový koncept.');
        [$title,$text]=clubConsentValidateDocument($draft['title_plain'],$draft['text_plain'],$draft['reason']);
        if((int)$draft['custom_visible']===1&&preg_match('/^\s*vzor(?:ový)?\s+text\b|před produkční aktivací/iu',$text)===1)throw new InvalidArgumentException('Vzorový text nelze zveřejnit.');
        $term=clubConsentInsertTerm($pdo,$definition,'custom-draft-'.$draftId,$title,$text,(int)$draft['custom_required']===1,(int)$draft['custom_visible']===1,$actor);
        $pdo->prepare('UPDATE club_registration_consents SET current_term_id=? WHERE id=?')->execute([$term,$id]);
        $pdo->prepare('UPDATE athlete_registration_text_drafts SET published_term_id=?,published_by=?,published_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$term,$actor,$draftId]);
        $pdo->commit();return $term;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

function clubConsentHide(PDO $pdo,int $id,int $baseTermId,bool $confirmed):int
{
    registrationTextsRequireRegistrar();
    if(!$confirmed)throw new InvalidArgumentException('Potvrďte skrytí souhlasu.');
    $current=clubConsentTerm($pdo,$id);
    if((int)$current['custom_visible']===0)return (int)$current['id'];
    $draft=clubConsentSaveDraft($pdo,$id,$baseTermId,$current['title_plain'],$current['consent_text_plain'],'Skrytí souhlasu registrářem.',(int)$current['custom_required']===1,false);
    return clubConsentPublish($pdo,$id,$draft,true);
}
