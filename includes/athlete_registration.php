<?php
declare(strict_types=1);

require_once __DIR__ . '/account_person_claim.php';
require_once __DIR__ . '/person_sensitive.php';
require_once __DIR__ . '/private_storage.php';

const ATHLETE_REGISTRATION_CONTRACT = 'athlete-registration-v1';
const ATHLETE_REGISTRATION_SCOPE = 'athlete_registration';
const ATHLETE_REGISTRATION_SCOPE_KEY = 'athlete-registration-v1';
const ATHLETE_REGISTRATION_PENDING_LIMIT = 5;

final class AthleteRegistrationException extends RuntimeException
{
}

/** @return array<string,array{id:int,version:string,text:string}> */
function athleteRegistrationCurrentTerms(PDO $pdo): array
{
    $purposes = [
        'member_data_notice',
        'birth_number_legal_notice',
        'photo_internal',
        'photo_public',
    ];
    $statement = $pdo->prepare(
        'SELECT id,terms_version,consent_text_plain FROM club_event_term_versions '
        . 'WHERE scope_type=? AND scope_key=? AND consent_purpose=? '
        . 'ORDER BY created_at DESC,id DESC LIMIT 1'
    );
    $terms = [];
    foreach ($purposes as $purpose) {
        $statement->execute([ATHLETE_REGISTRATION_SCOPE, ATHLETE_REGISTRATION_SCOPE_KEY, $purpose]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new AthleteRegistrationException('Registrační podmínky nejsou dostupné.');
        $terms[$purpose] = [
            'id' => (int)$row['id'],
            'version' => (string)$row['terms_version'],
            'text' => (string)$row['consent_text_plain'],
        ];
    }
    return $terms;
}

/**
 * @param array<string,mixed> $input
 * @param array<string,string> $submittedVersions
 * @param array{tmp_name?:string,error?:int}|null $photo
 * @param array{keys?:array<string,string>,active_version?:string,index_key?:string}|null $sensitiveConfig
 * @return array{id:int,status:string,created:bool}
 */
function athleteRegistrationSubmit(
    PDO $pdo,
    int $accountId,
    array $input,
    array $submittedVersions,
    ?array $photo = null,
    ?array $sensitiveConfig = null,
    bool $uploadedPhoto = true
): array {
    if ($pdo->inTransaction()) {
        throw new AthleteRegistrationException('Registrační žádost vyžaduje samostatnou transakci.');
    }
    $validated = athleteRegistrationValidate($accountId, $input, $photo);
    $terms = athleteRegistrationCurrentTerms($pdo);
    foreach ($terms as $purpose => $term) {
        if (!isset($submittedVersions[$purpose])
            || !hash_equals($term['version'], (string)$submittedVersions[$purpose])
        ) {
            throw new AthleteRegistrationException('Podmínky registrace se změnily. Obnovte stránku a potvrďte aktuální znění.');
        }
    }
    if (!$validated['member_data_notice'] || !$validated['birth_number_legal_notice']) {
        throw new InvalidArgumentException('Potvrďte obě povinné informace k evidenci člena.');
    }
    if ($validated['has_photo'] && !$validated['photo_internal']) {
        throw new InvalidArgumentException('Pro uložení fotografie potvrďte samostatný interní souhlas.');
    }

    $fingerprint = athleteRegistrationFingerprint(
        $validated['requested_role'],
        $validated['jmeno'],
        $validated['prijmeni'],
        $validated['narozeni']
    );
    $storedPhotoKey = null;
    $pdo->beginTransaction();
    try {
        $accountSql = 'SELECT id,email,aktivni,email_overeno FROM verejni_uzivatele WHERE id=?';
        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') $accountSql .= ' FOR UPDATE';
        $account = $pdo->prepare($accountSql);
        $account->execute([$accountId]);
        $account = $account->fetch(PDO::FETCH_ASSOC);
        if (!$account || !(int)$account['aktivni'] || !(int)$account['email_overeno']) {
            throw new AthleteRegistrationException('Žádost může odeslat pouze aktivní účet s ověřeným e-mailem.');
        }

        $existing = $pdo->prepare(
            "SELECT id FROM account_person_claim_requests WHERE account_id=? AND request_kind='athlete_registration' "
            . "AND status='pending' AND active_fingerprint=? LIMIT 1"
        );
        $existing->execute([$accountId, $fingerprint]);
        $existingId = $existing->fetchColumn();
        if ($existingId !== false) {
            $pdo->commit();
            return ['id' => (int)$existingId, 'status' => 'pending', 'created' => false];
        }

        $pending = $pdo->prepare(
            "SELECT COUNT(*) FROM account_person_claim_requests WHERE account_id=? AND status='pending'"
        );
        $pending->execute([$accountId]);
        if ((int)$pending->fetchColumn() >= ATHLETE_REGISTRATION_PENDING_LIMIT) {
            throw new AthleteRegistrationException('Máte již pět nevyřízených žádostí. Vyčkejte na kontrolu administrátorem.');
        }

        $request = $pdo->prepare(
            'INSERT INTO account_person_claim_requests '
            . '(account_id,requested_role,request_kind,contract_version,claimed_jmeno,claimed_prijmeni,'
            . 'claimed_narozeni,requester_message,status,active_fingerprint) '
            . "VALUES (?,?, 'athlete_registration',?,?,?,?,?,'pending',?)"
        );
        $request->execute([
            $accountId,
            $validated['requested_role'],
            ATHLETE_REGISTRATION_CONTRACT,
            $validated['jmeno'],
            $validated['prijmeni'],
            $validated['narozeni'],
            $validated['message'],
            $fingerprint,
        ]);
        $requestId = (int)$pdo->lastInsertId();

        $detail = $pdo->prepare(
            'INSERT INTO athlete_registration_request_details '
            . '(request_id,has_czech_birth_number,contact_email_snapshot,contact_phone,citizenship_country_code,'
            . 'address_street,address_house_number,address_orientation_number,address_city,address_postcode) '
            . 'VALUES (?,?,?,?,?,?,?,?,?,?)'
        );
        $detail->execute([
            $requestId,
            $validated['has_czech_birth_number'] ? 1 : 0,
            (string)$account['email'],
            $validated['contact_phone'],
            $validated['citizenship_country_code'],
            $validated['address_street'],
            $validated['address_house_number'],
            $validated['address_orientation_number'] !== '' ? $validated['address_orientation_number'] : null,
            $validated['address_city'],
            $validated['address_postcode'],
        ]);

        $consentInsert = $pdo->prepare(
            'INSERT INTO athlete_registration_consent_snapshots '
            . '(request_id,purpose,term_version_id,terms_version,text_snapshot,accepted,accepted_by_account_id,accepted_at) '
            . 'VALUES (?,?,?,?,?,?,?,CURRENT_TIMESTAMP)'
        );
        $consentIds = [];
        $acceptedByPurpose = [
            'member_data_notice' => true,
            'birth_number_legal_notice' => true,
            'photo_public' => $validated['photo_public'],
        ];
        if ($validated['has_photo']) $acceptedByPurpose['photo_internal'] = true;
        foreach ($acceptedByPurpose as $purpose => $accepted) {
            $term = $terms[$purpose];
            $consentInsert->execute([
                $requestId,
                $purpose,
                $term['id'],
                $term['version'],
                $term['text'],
                $accepted ? 1 : 0,
                $accountId,
            ]);
            $consentIds[$purpose] = (int)$pdo->lastInsertId();
        }

        if ($validated['has_czech_birth_number']) {
            personSensitiveStoreBirthNumber(
                $pdo,
                $requestId,
                $validated['birth_number'],
                $validated['narozeni'],
                $sensitiveConfig
            );
        }

        if ($validated['has_photo']) {
            $photoMetadata = privateStorageStoreAthletePhoto((string)$photo['tmp_name'], $uploadedPhoto);
            $storedPhotoKey = $photoMetadata['storage_key'];
            $photoInsert = $pdo->prepare(
                'INSERT INTO athlete_private_files '
                . '(request_id,file_kind,storage_key,sha256,byte_size,mime_type,width_px,height_px,status,consent_snapshot_id) '
                . "VALUES (?,'internal_photo',?,?,?,?,?,?, 'active',?)"
            );
            $photoInsert->execute([
                $requestId,
                $photoMetadata['storage_key'],
                hex2bin($photoMetadata['sha256_hex']),
                $photoMetadata['byte_size'],
                $photoMetadata['mime_type'],
                $photoMetadata['width_px'],
                $photoMetadata['height_px'],
                $consentIds['photo_internal'],
            ]);
        }

        accountPersonClaimEvent(
            $pdo,
            $requestId,
            'account',
            $accountId,
            'submit',
            null,
            'pending',
            'athlete_registration_submit'
        );
        $pdo->commit();
        return ['id' => $requestId, 'status' => 'pending', 'created' => true];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($storedPhotoKey !== null) {
            try {
                privateStorageSoftDelete($storedPhotoKey);
            } catch (Throwable $cleanupException) {
                error_log('athlete registration photo rollback failed: ' . get_class($cleanupException));
            }
        }
        if ($exception instanceof InvalidArgumentException
            || $exception instanceof AthleteRegistrationException
            || $exception instanceof PersonSensitiveException
        ) {
            throw $exception;
        }
        throw new AthleteRegistrationException('Žádost se nepodařilo bezpečně uložit.', 0, $exception);
    }
}

/** @return array{id:int,status:string,changed:bool} */
function athleteRegistrationCancel(PDO $pdo, int $requestId, int $accountId): array
{
    if ($requestId < 1 || $accountId < 1) throw new InvalidArgumentException('Neplatná žádost.');
    $pdo->beginTransaction();
    try {
        $claim = accountPersonClaimLock($pdo, $requestId);
        if (!$claim || (int)$claim['account_id'] !== $accountId
            || (string)($claim['request_kind'] ?? '') !== 'athlete_registration'
        ) {
            throw new AthleteRegistrationException('Tuto žádost nemůžete změnit.');
        }
        if ((string)$claim['status'] === 'cancelled') {
            $pdo->commit();
            return ['id' => $requestId, 'status' => 'cancelled', 'changed' => false];
        }
        if ((string)$claim['status'] !== 'pending') {
            throw new AthleteRegistrationException('Zrušit lze pouze čekající žádost.');
        }
        $retentionUntil = (new DateTimeImmutable('+30 days'))->format('Y-m-d H:i:s');
        $pdo->prepare(
            "UPDATE account_person_claim_requests SET status='cancelled',active_fingerprint=NULL,"
            . "decision_note='Zrušeno žadatelem.',decided_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP WHERE id=?"
        )->execute([$requestId]);
        $pdo->prepare(
            "UPDATE osoba_citlive_udaje SET status='retention_pending',retention_reason='cancelled_request',"
            . 'retention_until=?,updated_at=CURRENT_TIMESTAMP WHERE request_id=? AND status<>\'erased\''
        )->execute([$retentionUntil, $requestId]);
        $pdo->prepare(
            "UPDATE athlete_private_files SET status='retention_pending' WHERE request_id=? AND status='active'"
        )->execute([$requestId]);
        accountPersonClaimEvent(
            $pdo,
            $requestId,
            'account',
            $accountId,
            'cancelled',
            'pending',
            'cancelled',
            'athlete_registration_cancel'
        );
        $pdo->commit();
        return ['id' => $requestId, 'status' => 'cancelled', 'changed' => true];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($exception instanceof InvalidArgumentException || $exception instanceof AthleteRegistrationException) {
            throw $exception;
        }
        throw new AthleteRegistrationException('Žádost se nepodařilo bezpečně zrušit.', 0, $exception);
    }
}

/** @return list<array<string,mixed>> */
function athleteRegistrationListForAccount(PDO $pdo, int $accountId): array
{
    $statement = $pdo->prepare(
        'SELECT c.id,c.requested_role,c.claimed_jmeno,c.claimed_prijmeni,c.claimed_narozeni,'
        . 'c.status,c.created_at,c.decision_note,d.has_czech_birth_number,d.citizenship_country_code '
        . 'FROM account_person_claim_requests c JOIN athlete_registration_request_details d ON d.request_id=c.id '
        . "WHERE c.account_id=? AND c.request_kind='athlete_registration' ORDER BY c.created_at DESC,c.id DESC"
    );
    $statement->execute([$accountId]);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

/** @param array<string,mixed> $input @param array<string,mixed>|null $photo @return array<string,mixed> */
function athleteRegistrationValidate(int $accountId, array $input, ?array $photo): array
{
    if ($accountId < 1) throw new InvalidArgumentException('Neplatný účet.');
    $values = [];
    foreach ([
        'requested_role', 'jmeno', 'prijmeni', 'narozeni', 'contact_phone',
        'citizenship_country_code', 'address_street', 'address_house_number',
        'address_orientation_number', 'address_city', 'address_postcode', 'birth_number', 'message',
    ] as $field) {
        $values[$field] = trim((string)($input[$field] ?? ''));
    }
    $values['citizenship_country_code'] = strtoupper($values['citizenship_country_code']);
    foreach (['jmeno' => 100, 'prijmeni' => 100, 'address_city' => 100] as $field => $max) {
        if ($values[$field] === '' || mb_strlen($values[$field], 'UTF-8') > $max) {
            throw new InvalidArgumentException('Vyplňte všechny povinné osobní a adresní údaje.');
        }
    }
    foreach (['contact_phone' => 50, 'address_street' => 200, 'address_house_number' => 20, 'address_postcode' => 10] as $field => $max) {
        if ($values[$field] === '' || mb_strlen($values[$field], 'UTF-8') > $max) {
            throw new InvalidArgumentException('Vyplňte platný kontakt a úplnou adresu.');
        }
    }
    if (mb_strlen($values['address_orientation_number'], 'UTF-8') > 20
        || mb_strlen($values['message'], 'UTF-8') > 1000
        || preg_match('/^[A-Z]{2}$/D', $values['citizenship_country_code']) !== 1
    ) {
        throw new InvalidArgumentException('Některý údaj nemá platný formát.');
    }
    $birthDate = DateTimeImmutable::createFromFormat('!Y-m-d', $values['narozeni']);
    $today = new DateTimeImmutable('today');
    if (!$birthDate || $birthDate->format('Y-m-d') !== $values['narozeni']
        || $birthDate > $today || $birthDate < new DateTimeImmutable('1900-01-01')
    ) {
        throw new InvalidArgumentException('Zadejte platné datum narození.');
    }
    $adult = $birthDate->modify('+18 years') <= $today;
    if (($adult && $values['requested_role'] !== 'self')
        || (!$adult && $values['requested_role'] !== 'guardian')
    ) {
        throw new InvalidArgumentException($adult
            ? 'Dospělý sportovec podává žádost za sebe.'
            : 'Za nezletilého podává žádost rodič nebo zákonný zástupce.');
    }

    if (!array_key_exists('has_czech_birth_number', $input)) {
        throw new InvalidArgumentException('Uveďte, zda bylo osobě přiděleno české rodné číslo.');
    }
    $hasCzechBirthNumber = athleteRegistrationBoolean($input['has_czech_birth_number']);
    if ($hasCzechBirthNumber === null) throw new InvalidArgumentException('Uveďte, zda bylo osobě přiděleno české rodné číslo.');
    if (!$hasCzechBirthNumber && $values['citizenship_country_code'] === 'CZ') {
        throw new InvalidArgumentException('Výjimka bez rodného čísla je určena jen cizinci, kterému české rodné číslo nebylo přiděleno.');
    }
    personSensitiveValidateBirthNumber($values['birth_number'], $values['narozeni'], $hasCzechBirthNumber);
    $hasPhoto = is_array($photo)
        && (int)($photo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    if ($hasPhoto && ((int)($photo['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || trim((string)($photo['tmp_name'] ?? '')) === '')) {
        throw new InvalidArgumentException('Fotografii se nepodařilo bezpečně nahrát.');
    }
    if (!array_key_exists('photo_public', $input)) {
        throw new InvalidArgumentException('U veřejného použití fotografie zvolte ano nebo ne.');
    }
    foreach (['member_data_notice', 'birth_number_legal_notice', 'photo_internal', 'photo_public'] as $flag) {
        $values[$flag] = athleteRegistrationBoolean($input[$flag] ?? false) ?? false;
    }
    $values['has_czech_birth_number'] = $hasCzechBirthNumber;
    $values['has_photo'] = $hasPhoto;
    return $values;
}

function athleteRegistrationFingerprint(string $role, string $jmeno, string $prijmeni, string $birthDate): string
{
    return hash('sha256', ATHLETE_REGISTRATION_CONTRACT . '|' . accountPersonClaimFingerprint($role, $jmeno, $prijmeni, $birthDate));
}

function athleteRegistrationBoolean(mixed $value): ?bool
{
    if (is_bool($value)) return $value;
    if ($value === 1 || $value === '1' || $value === 'true') return true;
    if ($value === 0 || $value === '0' || $value === 'false') return false;
    return null;
}
