<?php
declare(strict_types=1);

const PERSON_SENSITIVE_CONTRACT = 'person-sensitive-v1';
const PERSON_SENSITIVE_KEY_BYTES = 32;
const PERSON_SENSITIVE_NONCE_BYTES = 24;

final class PersonSensitiveException extends RuntimeException
{
}

/**
 * @param array{keys?:array<string,string>,active_version?:string,index_key?:string}|null $override
 * @return array{keys:array<string,string>,active_version:string,index_key:string}
 */
function personSensitiveConfig(?array $override = null): array
{
    if ($override !== null) {
        $rawKeys = $override['keys'] ?? [];
        $activeVersion = trim((string)($override['active_version'] ?? ''));
        $rawIndexKey = $override['index_key'] ?? '';
    } else {
        $rawKeys = defined('PERSON_RC_KEYRING') && is_array(PERSON_RC_KEYRING)
            ? PERSON_RC_KEYRING
            : [];
        if ($rawKeys === []) {
            $json = trim((string)getenv('PERSON_RC_KEYRING_JSON'));
            if ($json !== '') {
                try {
                    $decoded = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
                    $rawKeys = is_array($decoded) ? $decoded : [];
                } catch (JsonException) {
                    throw new PersonSensitiveException('Konfigurace citlivých údajů není dostupná.');
                }
            }
        }
        $activeVersion = trim((string)(
            defined('PERSON_RC_ACTIVE_KEY_VERSION')
                ? PERSON_RC_ACTIVE_KEY_VERSION
                : getenv('PERSON_RC_ACTIVE_KEY_VERSION')
        ));
        $rawIndexKey = defined('PERSON_RC_INDEX_KEY')
            ? PERSON_RC_INDEX_KEY
            : (string)getenv('PERSON_RC_INDEX_KEY');
    }

    if (!function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt')) {
        throw new PersonSensitiveException('Šifrování citlivých údajů není dostupné.');
    }
    if (!is_array($rawKeys) || $activeVersion === '' || !array_key_exists($activeVersion, $rawKeys)) {
        throw new PersonSensitiveException('Konfigurace citlivých údajů není dostupná.');
    }

    $keys = [];
    foreach ($rawKeys as $version => $rawKey) {
        $version = trim((string)$version);
        if ($version === '' || preg_match('/^[a-z0-9._-]{1,32}$/Di', $version) !== 1) {
            throw new PersonSensitiveException('Konfigurace citlivých údajů není dostupná.');
        }
        $keys[$version] = personSensitiveDecodeKey($rawKey);
    }
    $indexKey = personSensitiveDecodeKey($rawIndexKey);
    foreach ($keys as $encryptionKey) {
        if (hash_equals($encryptionKey, $indexKey)) {
            throw new PersonSensitiveException('Konfigurace citlivých údajů není dostupná.');
        }
    }

    return ['keys' => $keys, 'active_version' => $activeVersion, 'index_key' => $indexKey];
}

function personSensitiveDecodeKey(mixed $raw): string
{
    if (!is_string($raw)) {
        throw new PersonSensitiveException('Konfigurace citlivých údajů není dostupná.');
    }
    if (strlen($raw) === PERSON_SENSITIVE_KEY_BYTES) return $raw;
    $decoded = base64_decode($raw, true);
    if (!is_string($decoded) || strlen($decoded) !== PERSON_SENSITIVE_KEY_BYTES) {
        throw new PersonSensitiveException('Konfigurace citlivých údajů není dostupná.');
    }
    return $decoded;
}

function personSensitiveNormalizeBirthNumber(string $value): string
{
    $value = trim($value);
    if (preg_match('/^[0-9]{6}\/?[0-9]{3,4}$/D', $value) !== 1) {
        throw new InvalidArgumentException('Rodné číslo nemá platný formát.');
    }
    $digits = str_replace('/', '', $value);
    if (!in_array(strlen($digits), [9, 10], true)) {
        throw new InvalidArgumentException('Rodné číslo nemá platný formát.');
    }
    return $digits;
}

function personSensitiveBirthDate(string $digits): string
{
    if (preg_match('/^[0-9]{9,10}$/D', $digits) !== 1) {
        throw new InvalidArgumentException('Rodné číslo nemá platný formát.');
    }
    $yearPart = (int)substr($digits, 0, 2);
    $monthPart = (int)substr($digits, 2, 2);
    $day = (int)substr($digits, 4, 2);
    $tenDigits = strlen($digits) === 10;
    $year = $tenDigits
        ? ($yearPart >= 54 ? 1900 + $yearPart : 2000 + $yearPart)
        : 1900 + $yearPart;

    $extendedMonth = false;
    if ($monthPart > 70) {
        $monthPart -= 70;
        $extendedMonth = true;
    } elseif ($monthPart > 50) {
        $monthPart -= 50;
    } elseif ($monthPart > 20) {
        $monthPart -= 20;
        $extendedMonth = true;
    }
    if ($extendedMonth && $year < 2004) {
        throw new InvalidArgumentException('Rodné číslo obsahuje neplatné datum.');
    }
    if (!$tenDigits && $year >= 1954) {
        throw new InvalidArgumentException('Rodné číslo nemá platnou délku.');
    }
    if (!checkdate($monthPart, $day, $year)) {
        throw new InvalidArgumentException('Rodné číslo obsahuje neplatné datum.');
    }
    if ($tenDigits) {
        $remainder = 0;
        foreach (str_split($digits) as $digit) {
            $remainder = (($remainder * 10) + (int)$digit) % 11;
        }
        if ($remainder !== 0) {
            throw new InvalidArgumentException('Rodné číslo neprošlo kontrolním součtem.');
        }
    }
    return sprintf('%04d-%02d-%02d', $year, $monthPart, $day);
}

function personSensitiveValidateBirthNumber(
    string $value,
    string $birthDate,
    bool $hasCzechBirthNumber = true
): ?string {
    if (!$hasCzechBirthNumber) {
        if (trim($value) !== '') {
            throw new InvalidArgumentException('Cizinec bez přiděleného českého RČ nesmí mít náhradní číslo.');
        }
        return null;
    }
    $digits = personSensitiveNormalizeBirthNumber($value);
    if (personSensitiveBirthDate($digits) !== $birthDate) {
        throw new InvalidArgumentException('Rodné číslo neodpovídá datu narození.');
    }
    return $digits;
}

function personSensitiveMaskBirthNumber(string $digits): string
{
    if (preg_match('/^[0-9]{9,10}$/D', $digits) !== 1) {
        throw new InvalidArgumentException('Rodné číslo nemá platný formát.');
    }
    return substr($digits, 0, 6) . '/****';
}

/** @return array{ciphertext:string,nonce:string,key_version:string,blind_index:string} */
function personSensitiveEncrypt(
    string $digits,
    string $recordToken,
    int $requestId,
    ?array $configOverride = null
): array {
    $config = personSensitiveConfig($configOverride);
    $version = $config['active_version'];
    $nonce = random_bytes(PERSON_SENSITIVE_NONCE_BYTES);
    $aad = personSensitiveAad($recordToken, $requestId);
    $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
        $digits,
        $aad,
        $nonce,
        $config['keys'][$version]
    );
    return [
        'ciphertext' => $ciphertext,
        'nonce' => $nonce,
        'key_version' => $version,
        'blind_index' => hash_hmac('sha256', $digits, $config['index_key'], true),
    ];
}

function personSensitiveAad(string $recordToken, int $requestId): string
{
    if (preg_match('/^[a-f0-9]{32}$/D', $recordToken) !== 1 || $requestId < 1) {
        throw new PersonSensitiveException('Kontext citlivého údaje není platný.');
    }
    return PERSON_SENSITIVE_CONTRACT . '|' . $recordToken . '|' . $requestId;
}

/** @param array<string,mixed> $row */
function personSensitiveDecryptForAdmin(array $row, ?array $configOverride = null): string
{
    personSensitiveRequireAdmin();
    if ((string)($row['status'] ?? '') === 'erased') {
        throw new PersonSensitiveException('Citlivý údaj byl vymazán.');
    }
    $config = personSensitiveConfig($configOverride);
    $version = (string)($row['rc_key_version'] ?? '');
    if (!isset($config['keys'][$version])) {
        throw new PersonSensitiveException('Šifrovací klíč citlivého údaje není dostupný.');
    }
    $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
        (string)($row['rc_ciphertext'] ?? ''),
        personSensitiveAad((string)($row['record_token'] ?? ''), (int)($row['request_id'] ?? 0)),
        (string)($row['rc_nonce'] ?? ''),
        $config['keys'][$version]
    );
    if (!is_string($plaintext) || preg_match('/^[0-9]{9,10}$/D', $plaintext) !== 1) {
        throw new PersonSensitiveException('Citlivý údaj nelze ověřit.');
    }
    return $plaintext;
}

function personSensitiveRequireAdmin(): int
{
    $trainerId = filter_var($_SESSION['trener_id'] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);
    $role = (string)($_SESSION['role'] ?? '');
    if ($trainerId === false || $role !== 'admin') {
        throw new PersonSensitiveException('Přístup k citlivému údaji je odepřen.');
    }
    return (int)$trainerId;
}

function personSensitiveStoreBirthNumber(
    PDO $pdo,
    int $requestId,
    string $value,
    string $birthDate,
    ?array $configOverride = null
): int {
    $digits = personSensitiveValidateBirthNumber($value, $birthDate, true);
    $recordToken = bin2hex(random_bytes(16));
    $encrypted = personSensitiveEncrypt((string)$digits, $recordToken, $requestId, $configOverride);
    try {
        $statement = $pdo->prepare(
            'INSERT INTO osoba_citlive_udaje '
            . '(record_token,request_id,rc_ciphertext,rc_nonce,rc_key_version,rc_blind_index,contract_version,status) '
            . "VALUES (?,?,?,?,?,?,'person-sensitive-v1','pending')"
        );
        $statement->execute([
            $recordToken,
            $requestId,
            $encrypted['ciphertext'],
            $encrypted['nonce'],
            $encrypted['key_version'],
            $encrypted['blind_index'],
        ]);
        return (int)$pdo->lastInsertId();
    } catch (Throwable $exception) {
        throw new PersonSensitiveException('Rodné číslo nelze bezpečně uložit.', 0, $exception);
    }
}

function personSensitiveAdminMaskedView(
    PDO $pdo,
    int $recordId,
    ?array $configOverride = null,
    string $ipAddress = ''
): string {
    $row = personSensitiveAdminRecord($pdo, $recordId);
    $digits = personSensitiveDecryptForAdmin($row, $configOverride);
    personSensitiveAudit($pdo, $row, 'masked_view', 'admin_masked_view', $ipAddress);
    return personSensitiveMaskBirthNumber($digits);
}

function personSensitiveAdminReveal(
    PDO $pdo,
    int $recordId,
    string $reason,
    ?array $configOverride = null,
    string $ipAddress = ''
): string {
    $reason = trim($reason);
    if (mb_strlen($reason, 'UTF-8') < 10) {
        throw new InvalidArgumentException('Důvod odhalení musí mít alespoň 10 znaků.');
    }
    $row = personSensitiveAdminRecord($pdo, $recordId);
    $digits = personSensitiveDecryptForAdmin($row, $configOverride);
    personSensitiveAudit($pdo, $row, 'reveal', $reason, $ipAddress);
    return substr($digits, 0, 6) . '/' . substr($digits, 6);
}

/** @return array<string,mixed> */
function personSensitiveAdminRecord(PDO $pdo, int $recordId): array
{
    personSensitiveRequireAdmin();
    if ($recordId < 1) throw new InvalidArgumentException('Citlivý záznam není platný.');
    $statement = $pdo->prepare('SELECT * FROM osoba_citlive_udaje WHERE id=? LIMIT 1');
    $statement->execute([$recordId]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new PersonSensitiveException('Citlivý záznam nebyl nalezen.');
    return $row;
}

/** @param array<string,mixed> $row */
function personSensitiveAudit(
    PDO $pdo,
    array $row,
    string $action,
    string $reason,
    string $ipAddress = '',
    ?int $privateFileId = null
): void {
    $trainerId = personSensitiveRequireAdmin();
    $allowed = ['masked_view', 'reveal', 'replace', 'erase', 'key_rotate', 'photo_view'];
    if (!in_array($action, $allowed, true)) {
        throw new InvalidArgumentException('Neplatná auditní operace.');
    }
    $statement = $pdo->prepare(
        'INSERT INTO osoba_citlive_pristupy '
        . '(sensitive_record_id,private_file_id,sportovec_id,request_id,actor_trainer_id,action,reason,ip_address) '
        . 'VALUES (?,?,?,?,?,?,?,?)'
    );
    $statement->execute([
        isset($row['id']) ? (int)$row['id'] : null,
        $privateFileId,
        isset($row['sportovec_id']) && $row['sportovec_id'] !== null ? (int)$row['sportovec_id'] : null,
        isset($row['request_id']) && $row['request_id'] !== null ? (int)$row['request_id'] : null,
        $trainerId,
        $action,
        trim($reason),
        substr(trim($ipAddress), 0, 45),
    ]);
}

function personSensitiveAdminAuditPhotoView(PDO $pdo, array $file, string $ipAddress = ''): void
{
    personSensitiveRequireAdmin();
    $row = [
        'sportovec_id' => $file['sportovec_id'] ?? null,
        'request_id' => $file['request_id'] ?? null,
    ];
    personSensitiveAudit($pdo, $row, 'photo_view', 'admin_photo_view', $ipAddress, (int)($file['id'] ?? 0));
}

function personSensitiveAdminReplace(
    PDO $pdo,
    int $recordId,
    string $value,
    string $birthDate,
    string $reason,
    ?array $configOverride = null,
    string $ipAddress = ''
): void {
    $reason = trim($reason);
    if (mb_strlen($reason, 'UTF-8') < 10) {
        throw new InvalidArgumentException('Důvod změny musí mít alespoň 10 znaků.');
    }
    $row = personSensitiveAdminRecord($pdo, $recordId);
    if ((string)($row['status'] ?? '') === 'erased') {
        throw new PersonSensitiveException('Vymazaný citlivý údaj nelze změnit.');
    }
    $digits = personSensitiveValidateBirthNumber($value, $birthDate, true);
    $encrypted = personSensitiveEncrypt(
        (string)$digits,
        (string)$row['record_token'],
        (int)$row['request_id'],
        $configOverride
    );
    $started = !$pdo->inTransaction();
    if ($started) $pdo->beginTransaction();
    try {
        $update = $pdo->prepare(
            "UPDATE osoba_citlive_udaje SET rc_ciphertext=?,rc_nonce=?,rc_key_version=?,rc_blind_index=?,"
            . 'retention_reason=NULL,retention_until=NULL,erased_at=NULL,updated_at=CURRENT_TIMESTAMP '
            . 'WHERE id=?'
        );
        $update->execute([
            $encrypted['ciphertext'],
            $encrypted['nonce'],
            $encrypted['key_version'],
            $encrypted['blind_index'],
            $recordId,
        ]);
        personSensitiveAudit($pdo, $row, 'replace', $reason, $ipAddress);
        if ($started) $pdo->commit();
    } catch (Throwable $exception) {
        if ($started && $pdo->inTransaction()) $pdo->rollBack();
        throw new PersonSensitiveException('Rodné číslo nelze bezpečně změnit.', 0, $exception);
    }
}

function personSensitiveAdminErase(
    PDO $pdo,
    int $recordId,
    string $reason,
    string $ipAddress = ''
): void {
    $reason = trim($reason);
    if (mb_strlen($reason, 'UTF-8') < 10) {
        throw new InvalidArgumentException('Důvod výmazu musí mít alespoň 10 znaků.');
    }
    $row = personSensitiveAdminRecord($pdo, $recordId);
    $started = !$pdo->inTransaction();
    if ($started) $pdo->beginTransaction();
    try {
        $update = $pdo->prepare(
            "UPDATE osoba_citlive_udaje SET rc_ciphertext=?,rc_nonce=?,rc_key_version='erased',"
            . "rc_blind_index=?,status='erased',retention_reason=?,retention_until=NULL,"
            . 'erased_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP WHERE id=?'
        );
        $update->execute([
            random_bytes(64),
            random_bytes(PERSON_SENSITIVE_NONCE_BYTES),
            random_bytes(PERSON_SENSITIVE_KEY_BYTES),
            $reason,
            $recordId,
        ]);
        personSensitiveAudit($pdo, $row, 'erase', $reason, $ipAddress);
        if ($started) $pdo->commit();
    } catch (Throwable $exception) {
        if ($started && $pdo->inTransaction()) $pdo->rollBack();
        throw new PersonSensitiveException('Citlivý údaj nelze bezpečně vymazat.', 0, $exception);
    }
}

function personSensitiveAdminRotate(
    PDO $pdo,
    int $recordId,
    string $reason,
    ?array $configOverride = null,
    string $ipAddress = ''
): void {
    $reason = trim($reason);
    if (mb_strlen($reason, 'UTF-8') < 10) {
        throw new InvalidArgumentException('Důvod rotace musí mít alespoň 10 znaků.');
    }
    $row = personSensitiveAdminRecord($pdo, $recordId);
    $digits = personSensitiveDecryptForAdmin($row, $configOverride);
    $encrypted = personSensitiveEncrypt(
        $digits,
        (string)$row['record_token'],
        (int)$row['request_id'],
        $configOverride
    );
    $started = !$pdo->inTransaction();
    if ($started) $pdo->beginTransaction();
    try {
        $update = $pdo->prepare(
            'UPDATE osoba_citlive_udaje SET rc_ciphertext=?,rc_nonce=?,rc_key_version=?,rc_blind_index=?,updated_at=CURRENT_TIMESTAMP WHERE id=?'
        );
        $update->execute([
            $encrypted['ciphertext'],
            $encrypted['nonce'],
            $encrypted['key_version'],
            $encrypted['blind_index'],
            $recordId,
        ]);
        personSensitiveAudit($pdo, $row, 'key_rotate', $reason, $ipAddress);
        if ($started) $pdo->commit();
    } catch (Throwable $exception) {
        if ($started && $pdo->inTransaction()) $pdo->rollBack();
        throw new PersonSensitiveException('Klíč citlivého údaje nelze bezpečně otočit.', 0, $exception);
    } finally {
        sodium_memzero($digits);
    }
}
