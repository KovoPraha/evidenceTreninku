<?php
declare(strict_types=1);

require_once __DIR__.'/private_storage.php';

/** This ledger is evidence of an offline act, never an online acceptance or account verification. */
function paperRegistrationRequireRegistrar(): int
{
    $id = (int)($_SESSION['trener_id'] ?? 0);
    if ($id < 1 || !function_exists('staffActivePositionIs') || !staffActivePositionIs('registrar')) {
        throw new DomainException('Přístup má pouze Registrář členů a KIS.');
    }
    return $id;
}

function paperRegistrationText(array $input, string $key, int $max, bool $required = true): string
{
    $value = $input[$key] ?? '';
    if (!is_string($value)) throw new InvalidArgumentException('Neplatné pole: '.$key);
    $value = trim($value);
    if (($required && $value === '') || mb_strlen($value) > $max) throw new InvalidArgumentException('Doplňte platné pole: '.$key);
    return $value;
}

function paperRegistrationOptionalId(array $input, string $key): int
{
    $value = $input[$key] ?? 0;
    if ($value === '') return 0;
    $id = filter_var($value,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
    if ($id === false) throw new InvalidArgumentException('Neplatný identifikátor: '.$key);
    return $id;
}

function paperRegistrationGet(PDO $pdo, int $id, bool $lock = false): array
{
    $suffix = $lock && $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
    $q = $pdo->prepare('SELECT * FROM club_paper_records WHERE id=?'.$suffix);
    $q->execute([$id]);
    $row = $q->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new DomainException('Papírový podklad nebyl nalezen.');
    $q = $pdo->prepare("SELECT event_type FROM club_paper_record_events WHERE record_id=? AND event_type<>'file_viewed' ORDER BY id DESC LIMIT 1".$suffix);
    $q->execute([$id]);
    $row['state'] = (string)$q->fetchColumn();
    return $row;
}

function paperRegistrationEvent(PDO $pdo, int $recordId, int $actor, string $type, string $note): void
{
    $q = $pdo->prepare('INSERT INTO club_paper_record_events(record_id,event_type,actor_id,note) VALUES(?,?,?,?)');
    $q->execute([$recordId, $type, $actor, $note]);
}

/** Owns its transaction so a failed attachment or audit cannot leave partial evidence. */
function paperRegistrationRecord(PDO $pdo, array $input, ?array $upload = null): int
{
    $actor = paperRegistrationRequireRegistrar();
    if ($pdo->inTransaction()) throw new LogicException('Evidence podkladu vyžaduje vlastní transakci.');
    $personId = filter_var($input['sportovec_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    if (!$personId) throw new InvalidArgumentException('Vyberte existující osobu.');
    $kind = paperRegistrationText($input, 'kind', 20);
    $scope = paperRegistrationText($input, 'scope_type', 20);
    $relation = paperRegistrationText($input, 'signer_relation', 20);
    if (!in_array($kind, ['application','consent'], true) || !in_array($scope, ['membership','program','event'], true)
        || !in_array($relation, ['self','guardian','other'], true)) throw new InvalidArgumentException('Neplatný účel nebo vztah podepisujícího.');
    $title = paperRegistrationText($input, 'document_title', 200);
    $signer = paperRegistrationText($input, 'signer_name', 200);
    $location = paperRegistrationText($input, 'original_location', 1000, false);
    $signed = paperRegistrationText($input, 'signed_on', 10);
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $signed);
    if (!$parsed || $parsed->format('Y-m-d') !== $signed || $signed > date('Y-m-d')) throw new InvalidArgumentException('Datum podpisu musí být platné a nesmí být v budoucnu.');
    $reason = paperRegistrationText($input, 'reason', 1000);
    $replacement = paperRegistrationOptionalId($input,'replaces_id');
    $termId = paperRegistrationOptionalId($input,'term_version_id');
    $scopeId = paperRegistrationOptionalId($input,'scope_id');
    if ($scope === 'membership' && $scopeId !== 0) throw new InvalidArgumentException('Členství se neváže na ID kurzu ani akce.');
    if ($scope !== 'membership' && $scopeId < 1) throw new InvalidArgumentException('Vyberte konkrétní program nebo akci.');
    $hasFile = $upload !== null && (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    if ($location === '' && !$hasFile) throw new InvalidArgumentException('Přiložte důkaz nebo uveďte umístění originálu.');
    if ($hasFile) {
        privateStorageValidateUploadBatch([$upload], 1);
        if (!is_uploaded_file((string)($upload['tmp_name'] ?? ''))) throw new InvalidArgumentException('Soubor nebyl bezpečně nahrán.');
    }
    $key = null;
    $pdo->beginTransaction();
    try {
        $q = $pdo->prepare('SELECT id FROM sportovci WHERE id=?'); $q->execute([$personId]);
        if (!$q->fetchColumn()) throw new InvalidArgumentException('Osoba neexistuje; nejprve vyřešte její identitu.');
        if ($scope !== 'membership') {
            $q = $pdo->prepare('SELECT id FROM '.($scope === 'program' ? 'club_programs' : 'club_events').' WHERE id=?');
            $q->execute([$scopeId]);
            if (!$q->fetchColumn()) throw new InvalidArgumentException('Program nebo akce neexistuje.');
        }
        if ($replacement > 0) {
            $old = paperRegistrationGet($pdo, $replacement, true);
            if ((int)$old['sportovec_id'] !== $personId || $old['kind'] !== $kind || $old['scope_type'] !== $scope || (int)$old['scope_id'] !== $scopeId) throw new DomainException('Oprava musí patřit stejné osobě, druhu a účelu.');
            if ($old['state'] === 'superseded') throw new DomainException('Podklad již byl opraven. Otevřete jeho poslední verzi.');
        }
        $version = 'verze neověřena'; $text = '';
        if ($termId > 0) {
            $q = $pdo->prepare('SELECT * FROM club_event_term_versions WHERE id=?'); $q->execute([$termId]);
            $term = $q->fetch(PDO::FETCH_ASSOC);
            if (!$term) throw new InvalidArgumentException('Verze dokumentu neexistuje.');
            $matches = ($scope === 'membership' && $term['scope_type'] === 'athlete_registration')
                || ($scope === 'program' && (($term['scope_type'] === 'club_program' && $term['scope_key'] === 'program:'.$scopeId)
                    || ($term['scope_type'] === 'club_registration' && $term['scope_key'] === 'all-programs')))
                || ($scope === 'event' && (int)($term['event_id'] ?? 0) === $scopeId && $term['scope_type'] === 'club_event');
            if (!$matches) throw new InvalidArgumentException('Verze dokumentu nepatří k vybranému účelu.');
            $version = (string)$term['terms_version']; $text = (string)$term['consent_text_plain'];
            $title = trim((string)($term['title_plain'] ?? '')) ?: $title;
        }
        $hash = null;
        if ($hasFile) {
            $hash = hash_file('sha256', (string)$upload['tmp_name']);
            $key = privateStorageStore((string)$upload['tmp_name'], PRIVATE_STORAGE_PAPER_DOCUMENTS);
        }
        $q = $pdo->prepare('INSERT INTO club_paper_records(sportovec_id,kind,scope_type,scope_id,document_title,term_version_id,version_label,text_snapshot,signer_name,signer_relation,signed_on,original_location,storage_key,file_sha256,recorded_by,replaces_id) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $q->execute([$personId,$kind,$scope,$scopeId ?: null,$title,$termId ?: null,$version,$text,$signer,$relation,$signed,$location,$key,$hash,$actor,$replacement ?: null]);
        $id = (int)$pdo->lastInsertId();
        paperRegistrationEvent($pdo,$id,$actor,'recorded',$reason);
        if ($replacement > 0) paperRegistrationEvent($pdo,$replacement,$actor,'superseded','Nahrazen podkladem #'.$id.'. '.$reason);
        $pdo->commit();
        return $id;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($key !== null) {
            $path = privateStorageResolve($key);
            if ($path !== null && !unlink($path)) error_log('Paper registration: rollback attachment cleanup failed.');
        }
        throw $e;
    }
}

/** Acceptance is evidence review only; no account, membership, roster, payment or online consent is changed. */
function paperRegistrationDecide(PDO $pdo, int $id, string $action, string $note): void
{
    $actor = paperRegistrationRequireRegistrar();
    $note = paperRegistrationText(['note'=>$note], 'note', 1000);
    if (!in_array($action,['accepted','rejected','revoked'],true)) throw new InvalidArgumentException('Neplatné rozhodnutí.');
    if ($pdo->inTransaction()) throw new LogicException('Rozhodnutí vyžaduje vlastní transakci.');
    $pdo->beginTransaction();
    try {
        $row = paperRegistrationGet($pdo,$id,true);
        $allowed = $row['state'] === 'recorded' ? ['accepted','rejected','revoked'] : ($row['state'] === 'accepted' ? ['revoked'] : []);
        if (!in_array($action,$allowed,true)) throw new DomainException('Podklad již byl rozhodnut nebo nahrazen. Pro opravu založte novou verzi.');
        paperRegistrationEvent($pdo,$id,$actor,$action,$note);
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}

function paperRegistrationDownload(PDO $pdo, int $id): string
{
    $actor = paperRegistrationRequireRegistrar();
    $row = paperRegistrationGet($pdo,$id);
    $path = privateStorageResolve((string)$row['storage_key']);
    if ($path === null || !is_string($row['file_sha256']) || !hash_equals($row['file_sha256'], (string)hash_file('sha256',$path))) throw new DomainException('Soukromý důkaz není dostupný nebo nesouhlasí jeho otisk.');
    paperRegistrationEvent($pdo,$id,$actor,'file_viewed','Zobrazení soukromého důkazu.');
    return $path;
}
