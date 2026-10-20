<?php
declare(strict_types=1);
require_once __DIR__.'/person_sensitive.php';
require_once __DIR__.'/person_match.php';
require_once __DIR__.'/kis_person_cutover.php';

const KIS_PERSON_IMPORT_CONTRACT = 'kis-full-person-v1';
const KIS_PERSON_IMPORT_LIMIT = 12000000;

function kisPersonJson(mixed $value): string
{
    return json_encode($value, JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}

function kisPersonReadPayload(string $bytes): array
{
    if (strlen($bytes)>KIS_PERSON_IMPORT_LIMIT) throw new InvalidArgumentException('Soubor je příliš velký.');
    if (str_starts_with($bytes,"\x1f\x8b")) {
        $decoded=@gzdecode($bytes,KIS_PERSON_IMPORT_LIMIT+1);
        if (!is_string($decoded)) throw new InvalidArgumentException('Neplatný komprimovaný soubor.');
        $bytes=$decoded;
    }
    if (strlen($bytes)>KIS_PERSON_IMPORT_LIMIT) throw new InvalidArgumentException('Rozbalený soubor je příliš velký.');
    $data=json_decode($bytes,true,64,JSON_THROW_ON_ERROR);
    if (!is_array($data) || ($data['contract']??'')!==KIS_PERSON_IMPORT_CONTRACT
        || ($data['source_namespace']??'')!=='servis.kovopraha.cz/2026-10-20') {
        throw new InvalidArgumentException('Nepodporovaný zdroj importu.');
    }
    $keys=[]; $aliases=[]; $contacts=[]; $links=[];
    foreach (['people','contacts','links'] as $kind) {
        if (!is_array($data[$kind]??null) || !array_is_list($data[$kind]) || count($data[$kind])<1 || count($data[$kind])>10000) {
            throw new InvalidArgumentException('Neplatná kolekce importu.');
        }
    }
    foreach($data['people'] as $p) {
        $key=$p['key']??'';
        if (!is_string($key) || !preg_match('/^O[0-9]{4,8}$/D',$key) || isset($keys[$key])) throw new InvalidArgumentException('Duplicitní identita osoby.');
        $keys[$key]=true;
        foreach(['first_name'=>100,'last_name'=>255,'email'=>1000,'phone'=>50,'street'=>200,'house_no'=>20,'orientation_no'=>20,'city'=>100,'postcode'=>10,'country'=>100,'contact_address'=>2000] as $field=>$limit) {
            if (!is_string($p[$field]??null) || mb_strlen($p[$field])>$limit) throw new InvalidArgumentException('Neplatné pole osoby: '.$field);
        }
        if ($p['first_name']==='' && $p['last_name']==='') throw new InvalidArgumentException('Osoba nemá jméno.');
        if (($p['birth_date']??null)!==null && personMatchV1Date($p['birth_date'])===null) throw new InvalidArgumentException('Neplatné datum osoby.');
        if (!is_bool($p['active']??null) || !is_array($p['aliases']??null) || !$p['aliases']) throw new InvalidArgumentException('Chybí původ osoby.');
        foreach($p['aliases'] as $a) {
            $source=$a['source_key']??'';
            if (!is_string($source) || !preg_match('/^[a-zA-Z0-9_-]{2,80}$/D',$source) || isset($aliases[$source])
                || !is_int($a['row']??null) || !is_string($a['username']??null) || mb_strlen($a['username'])>190) throw new InvalidArgumentException('Neplatný alias osoby.');
            $aliases[$source]=$key;
        }
    }
    foreach($data['contacts'] as $c) {
        $key=$c['key']??'';
        if (!is_string($key) || !preg_match('/^(O[0-9]{4,8}|Z[0-9]{4,8}\.[12])$/D',$key) || isset($contacts[$key])) throw new InvalidArgumentException('Duplicitní kontakt.');
        if (($c['member_key']??null)!==null && !isset($keys[$c['member_key']])) throw new InvalidArgumentException('Neplatná vazba kontaktu na člena.');
        foreach(['names','emails','phones','source_slots'] as $field) {
            if (!is_array($c[$field]??null) || !array_is_list($c[$field])) throw new InvalidArgumentException('Neplatné varianty kontaktu.');
            foreach($c[$field] as $v) if (!is_string($v) || mb_strlen($v)>1000) throw new InvalidArgumentException('Neplatná hodnota kontaktu.');
        }
        $contacts[$key]=$c;
    }
    foreach($data['links'] as $l) {
        $source=$l['source_key']??'';
        if (!is_string($source) || isset($links[$source]) || !isset($keys[$l['person_key']??''],$contacts[$l['contact_key']??''])
            || !isset($aliases[$l['source_person_key']??'']) || $aliases[$l['source_person_key']]!==$l['person_key']
            || !in_array($l['slot']??null,[1,2],true)
            || $source!==$l['source_person_key'].':ZZ'.$l['slot']
            || !is_string($l['relationship']??null) || mb_strlen($l['relationship'])>255) throw new InvalidArgumentException('Neplatná nebo ztracená zdrojová vazba.');
        if (($contacts[$l['contact_key']]['member_key']??null)===$l['person_key']) throw new InvalidArgumentException('Vazba osoby na sebe.');
        $links[$source]=true;
    }
    $archive=$data['source_archive']??[];
    if (!is_array($archive['people']??null) || !is_array($archive['guardians']??null)
        || count($archive['people'])!==count($aliases) || count($archive['guardians'])!==count($links)) throw new InvalidArgumentException('Archiv zdrojů není úplný.');
    $sourceIds=array_column($archive['people'],'id_hash'); $guardianIds=array_column($archive['guardians'],'id_vyskytu');
    if (count(array_unique($sourceIds))!==count($aliases) || array_diff(array_keys($aliases),$sourceIds)
        || count(array_unique($guardianIds))!==count($links) || array_diff(array_keys($links),$guardianIds)) throw new InvalidArgumentException('Archiv neodpovídá mapám.');
    return ['data'=>$data,'bytes'=>$bytes,'fingerprint'=>hash('sha256',$bytes)];
}

function kisPersonEncryptArchive(string $payload, string $fingerprint, ?array $configOverride=null): array
{
    $config=personSensitiveConfig($configOverride); $version=$config['active_version'];
    $key=hash_hkdf('sha256',$config['keys'][$version],32,'evidence-kis-full-person-v1');
    $nonce=random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
    return ['key_version'=>$version,'ciphertext'=>base64_encode($nonce.sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
        $payload,KIS_PERSON_IMPORT_CONTRACT.'|'.$fingerprint,$nonce,$key))];
}

function kisPersonDecryptArchive(array $row, ?array $configOverride=null): string
{
    $config=personSensitiveConfig($configOverride);$version=$row['key_version'];
    if (!isset($config['keys'][$version])) throw new RuntimeException('Klíč archivu není dostupný.');
    $raw=base64_decode($row['payload_ciphertext'],true);$n=SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
    if (!is_string($raw) || strlen($raw)<=$n) throw new RuntimeException('Archiv je poškozen.');
    $result=sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(substr($raw,$n),KIS_PERSON_IMPORT_CONTRACT.'|'.$row['fingerprint'],
        substr($raw,0,$n),hash_hkdf('sha256',$config['keys'][$version],32,'evidence-kis-full-person-v1'));
    if (!is_string($result) || !hash_equals($row['fingerprint'],hash('sha256',$result))) throw new RuntimeException('Archiv nelze ověřit.');
    return $result;
}

/** Accounts remain disabled/unverified and roles pending; email never grants access. */
function kisPersonAccountPlan(PDO $pdo, array $data): array
{
    $identities=[];$owners=[];
    foreach($data['people'] as $p) {
        if ($p['birth_date']===null || $p['birth_date']>(new DateTimeImmutable('today'))->modify('-18 years')->format('Y-m-d')) continue;
        $identities[$p['key']]=['name'=>trim($p['first_name'].' '.$p['last_name']),'emails'=>[$p['email']],'member_key'=>$p['key']];
    }
    foreach($data['contacts'] as $c) {
        $id=$c['member_key']??$c['key'];
        $old=$identities[$id]??['name'=>'','emails'=>[],'member_key'=>$c['member_key']];
        $identities[$id]=['name'=>$old['name'] ?: ($c['names'][0]??''),'emails'=>array_merge($old['emails'],$c['emails']),'member_key'=>$c['member_key']];
    }
    foreach($identities as $id=>&$identity) {
        $emails=[];
        foreach($identity['emails'] as $raw) {
            $email=mb_strtolower(trim($raw),'UTF-8');
            if ($email!=='' && strlen($email)<=160 && filter_var($email,FILTER_VALIDATE_EMAIL)) $emails[$email]=true;
        }
        $identity['emails']=array_keys($emails);
        foreach($identity['emails'] as $email) $owners[$email][$id]=true;
    }
    unset($identity);
    $existing=[];
    foreach($pdo->query('SELECT id,email FROM verejni_uzivatele ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) as $a) $existing[mb_strtolower($a['email'],'UTF-8')]=(int)$a['id'];
    foreach($identities as $id=>&$identity) {
        $identity['account_id']=null;
        $identity['state']='review_contact';
        if ($identity['name']==='' || count($identity['emails'])!==1) continue;
        $email=$identity['emails'][0];
        if (count($owners[$email])!==1) {$identity['state']='review_shared_email';continue;}
        $identity['email']=$email;
        if(isset($existing[$email])) {
            // Do not attach existing authenticated users by email alone.
            $identity['state']='review_existing_account';
        } else $identity['state']='create_disabled';
    }
    unset($identity);
    return $identities;
}

function kisPersonImportSummary(array $data): array
{
    return ['people'=>count($data['people']),'contacts'=>count($data['contacts']),'links'=>count($data['links']),
        'aliases'=>array_sum(array_map(static fn($p)=>count($p['aliases']),$data['people'])),
        'unknown_birth_dates'=>count(array_filter($data['people'],static fn($p)=>$p['birth_date']===null))];
}

function kisPersonImportApply(PDO $pdo, array $payload, int $actor, string $expectedCutover, ?array $keyConfig=null): array
{
    if($actor<1 || $pdo->inTransaction()) throw new InvalidArgumentException('Import vyžaduje samostatnou transakci a administrátora.');
    // Re-validate even for CLI callers; never trust a preconstructed array.
    $payload=kisPersonReadPayload($payload['bytes']);
    $data=$payload['data'];$fp=$payload['fingerprint'];
    $actorQuery=$pdo->prepare("SELECT id FROM treneri WHERE id=? AND role='admin'");
    $actorQuery->execute([$actor]);
    if(!$actorQuery->fetchColumn()) throw new RuntimeException('Import vyžaduje administrátora.');
    $lock=$pdo->query("SELECT GET_LOCK('kis_full_person_import_v1',0)")->fetchColumn();
    if((int)$lock!==1) throw new RuntimeException('Jiný import právě běží.');
    try {
        $pdo->beginTransaction();
        $done=$pdo->prepare('SELECT fingerprint,summary_json FROM kis_person_batches WHERE source_namespace=? FOR UPDATE');
        $done->execute([$data['source_namespace']]);$existing=$done->fetch(PDO::FETCH_ASSOC);
        if($existing) {
            if(!hash_equals($existing['fingerprint'],$fp)) throw new RuntimeException('Pro tento zdroj již existuje jiný import.');
            $pdo->rollBack();
            return json_decode($existing['summary_json'],true,32,JSON_THROW_ON_ERROR)+['already_applied'=>true];
        }
        $plan=kisPersonCutoverPlan($pdo,true);
        if(!hash_equals($expectedCutover,$plan['fingerprint'])) throw new RuntimeException('Databáze se od náhledu změnila. Spusťte nový náhled.');
        $accounts=kisPersonAccountPlan($pdo,$data);
        // Encrypt before deleting anything, so unavailable keys fail safely.
        $archive=kisPersonEncryptArchive($payload['bytes'],$fp,$keyConfig);
        kisPersonCutoverDelete($pdo,$plan);
        $summary=kisPersonImportSummary($data)+['removed_people'=>$plan['people'],'removed_history_rows'=>$plan['rows']-$plan['people'],
            'removed_by_table'=>array_column($plan['tables'],'count','table'),'accounts_created'=>0,'pending_roles'=>0];
        $pdo->prepare('INSERT INTO kis_person_batches(fingerprint,source_namespace,actor_trainer_id,payload_ciphertext,key_version,summary_json) VALUES(?,?,?,?,?,?)')
            ->execute([$fp,$data['source_namespace'],$actor,$archive['ciphertext'],$archive['key_version'],'{}']);
        $personInsert=$pdo->prepare('INSERT INTO sportovci(jmeno,prijmeni,narozeni,email,telefon,hash,adresa_ulice,adresa_cp,adresa_co,adresa_obec,adresa_psc,stav_clenstvi,stav_manualni,kis_aktivni) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,1,?)');
        $profileInsert=$pdo->prepare('INSERT INTO kis_person_profiles(sportovec_id,canonical_key,batch_fingerprint,contact_address,country,source_active) VALUES(?,?,?,?,?,?)');
        $aliasInsert=$pdo->prepare('INSERT INTO kis_person_aliases(source_key,sportovec_id,source_row,legacy_username) VALUES(?,?,?,?)');
        $personIds=[];
        foreach($data['people'] as $p) {
            $personInsert->execute([$p['first_name'],$p['last_name'],$p['birth_date'],$p['email'],$p['phone'],bin2hex(random_bytes(32)),
                $p['street'],$p['house_no'],$p['orientation_no'],$p['city'],$p['postcode'],$p['active']?'aktivni':'neaktivni',(int)$p['active']]);
            $id=(int)$pdo->lastInsertId();$personIds[$p['key']]=$id;
            $profileInsert->execute([$id,$p['key'],$fp,$p['contact_address'],$p['country'],(int)$p['active']]);
            foreach($p['aliases'] as $a) $aliasInsert->execute([$a['source_key'],$id,$a['row'],$a['username']]);
        }
        $accountInsert=$pdo->prepare('INSERT INTO verejni_uzivatele(jmeno,prijmeni,email,heslo_hash,email_overeno,aktivni) VALUES(?,?,?,?,0,0)');
        foreach($accounts as &$account) {
            if($account['state']!=='create_disabled') continue;
            $accountInsert->execute([mb_substr($account['name'],0,80),'',$account['email'],password_hash(bin2hex(random_bytes(32)),PASSWORD_BCRYPT,['cost'=>10])]);
            $account['account_id']=(int)$pdo->lastInsertId();$account['state']='disabled_reset_required';$summary['accounts_created']++;
        }
        unset($account);
        $contactInsert=$pdo->prepare('INSERT INTO kis_person_contacts(contact_key,batch_fingerprint,display_name,names_json,emails_json,phones_json,linked_sportovec_id,account_id,account_state) VALUES(?,?,?,?,?,?,?,?,?)');
        $contactAccounts=[];
        foreach($data['contacts'] as $c) {
            $account=$accounts[$c['member_key']??$c['key']];
            $contactInsert->execute([$c['key'],$fp,mb_substr($c['names'][0]??'',0,255),kisPersonJson($c['names']),kisPersonJson($c['emails']),kisPersonJson($c['phones']),
                $c['member_key']===null?null:$personIds[$c['member_key']],$account['account_id'],$account['state']]);
            $contactAccounts[$c['key']]=$account['account_id'];
        }
        $linkInsert=$pdo->prepare('INSERT INTO kis_person_links(source_key,sportovec_id,contact_key,relationship_label,source_person_key,source_slot) VALUES(?,?,?,?,?,?)');
        $roles=[];
        foreach($data['links'] as $l) {
            $id=$personIds[$l['person_key']];
            $linkInsert->execute([$l['source_key'],$id,$l['contact_key'],$l['relationship'],$l['source_person_key'],$l['slot']]);
            $accountId=$contactAccounts[$l['contact_key']];
            if($accountId!==null) $roles[$accountId.':'.$id]=[$accountId,$id,'guardian'];
        }
        foreach($accounts as $a) if($a['account_id']!==null && $a['member_key']!==null) {
            $id=$personIds[$a['member_key']];$roles[$a['account_id'].':'.$id]=[$a['account_id'],$id,'self'];
        }
        $roleInsert=$pdo->prepare("INSERT INTO account_person_roles(account_id,sportovec_id,relation_role,status,source,valid_from,created_by_trainer_id,approved_by_trainer_id,decision_note) VALUES(?,?,?,'pending','kis_import',CURRENT_TIMESTAMP,?,NULL,?)");
        $roleEvent=$pdo->prepare("INSERT INTO account_person_role_events(relation_id,actor_trainer_id,action,from_status,to_status,relation_role,note) VALUES(?,?,'import',NULL,'pending',?,?)");
        $note='Import starého KIS. Čeká na ověření a aktivaci; nevytváří oprávnění.';
        foreach($roles as [$aid,$pid,$role]) {
            $roleInsert->execute([$aid,$pid,$role,$actor,$note]);
            $roleEvent->execute([(int)$pdo->lastInsertId(),$actor,$role,$note]);$summary['pending_roles']++;
        }
        $summary['account_states']=array_count_values(array_column($accounts,'state'));
        foreach(['sportovci'=>'people','kis_person_aliases'=>'aliases','kis_person_contacts'=>'contacts','kis_person_links'=>'links'] as $table=>$key) {
            if((int)$pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn()!==$summary[$key]) throw new RuntimeException('Počet importovaných řádků neodpovídá podkladům.');
        }
        $summary['fingerprint']=$fp;
        $pdo->prepare('UPDATE kis_person_batches SET summary_json=? WHERE fingerprint=?')->execute([kisPersonJson($summary),$fp]);
        $pdo->commit();
        return $summary;
    } catch(Throwable $e) {
        if($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('kis_full_person_import_v1')");
    }
}
