<?php
declare(strict_types=1);

require_once __DIR__ . '/person_match.php';
require_once __DIR__ . '/person_sensitive.php';
require_once __DIR__ . '/sportovec_history_lib.php';
require_once __DIR__ . '/kis_person_import.php';
require_once __DIR__ . '/sportovec_editor_details.php';

function sportovecEditorFields(): array
{
    return [
        'prijmeni' => ['Příjmení', 'text', 255], 'jmeno' => ['Jméno', 'text', 100],
        'narozeni' => ['Datum narození', 'date', 10], 'category' => ['Kategorie', 'text', 100],
        'uciid' => ['UCI ID', 'text', 80], 'oddil' => ['Oddíl', 'text', 160],
        'email' => ['E-mail / e-maily', 'text', 1000], 'telefon' => ['Telefon', 'tel', 50],
        'adresa_ulice' => ['Ulice', 'text', 200], 'adresa_cp' => ['Číslo popisné', 'text', 20],
        'adresa_co' => ['Číslo orientační', 'text', 20], 'adresa_obec' => ['Obec', 'text', 100],
        'adresa_psc' => ['PSČ', 'text', 10],
    ];
}

function sportovecEditorPerson(PDO $pdo, int $id, bool $lock = false): array
{
    $sql = 'SELECT * FROM sportovci WHERE id=?';
    if ($lock && $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') $sql .= ' FOR UPDATE';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $person = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$person) throw new InvalidArgumentException('Vybraný sportovec nebyl nalezen.');
    return $person;
}

function sportovecEditorContext(PDO $pdo, int $id): array
{
    $activeLink = clubFinanceColumnExists($pdo, 'kis_person_links', 'ended_at') ? ' AND l.ended_at IS NULL' : '';
    $sharedLink = $activeLink !== '' ? ' AND shared_link.ended_at IS NULL' : '';
    $profile = $pdo->prepare('SELECT * FROM kis_person_profiles WHERE sportovec_id=?');
    $profile->execute([$id]);
    $parents = $pdo->prepare('SELECT l.*,c.display_name,c.names_json,c.emails_json,c.phones_json,c.account_id,c.account_state, '
        . 'r.status AS relation_status,r.valid_to,u.aktivni AS account_active,u.email_overeno,u.email AS login_email, '
        . '(SELECT COUNT(DISTINCT shared_link.sportovec_id) FROM kis_person_links shared_link WHERE shared_link.contact_key=c.contact_key' . $sharedLink . ') AS shared_people '
        . 'FROM kis_person_links l JOIN kis_person_contacts c ON c.contact_key=l.contact_key '
        . 'LEFT JOIN account_person_roles r ON r.account_id=c.account_id AND r.sportovec_id=l.sportovec_id '
        . 'LEFT JOIN verejni_uzivatele u ON u.id=c.account_id WHERE l.sportovec_id=?' . $activeLink . ' ORDER BY l.source_slot,l.source_key');
    $parents->execute([$id]);
    $parentRows = [];
    foreach ($parents->fetchAll(PDO::FETCH_ASSOC) as $parent) {
        if (!isset($parentRows[$parent['contact_key']])) $parentRows[$parent['contact_key']] = $parent;
    }
    $roles = $pdo->prepare('SELECT r.*,u.jmeno,u.prijmeni,u.email,u.aktivni,u.email_overeno '
        . 'FROM account_person_roles r JOIN verejni_uzivatele u ON u.id=r.account_id '
        . 'WHERE r.sportovec_id=? ORDER BY r.relation_role,r.id');
    $roles->execute([$id]);
    $sensitive = $pdo->prepare("SELECT id,status FROM osoba_citlive_udaje WHERE sportovec_id=? AND status<>'erased' ORDER BY id DESC");
    $sensitive->execute([$id]);
    return ['profile' => $profile->fetch(PDO::FETCH_ASSOC) ?: [], 'parents' => array_values($parentRows),
        'roles' => $roles->fetchAll(PDO::FETCH_ASSOC), 'sensitive' => $sensitive->fetchAll(PDO::FETCH_ASSOC),
        'contact_choices' => $pdo->query('SELECT contact_key,display_name,emails_json FROM kis_person_contacts ORDER BY display_name,contact_key')->fetchAll(PDO::FETCH_ASSOC),
        'account_choices' => $pdo->query('SELECT id,jmeno,prijmeni,email,aktivni,email_overeno FROM verejni_uzivatele ORDER BY prijmeni,jmeno,id')->fetchAll(PDO::FETCH_ASSOC),
        'rosters' => sportovecEditorRosters($pdo, $id), 'payments' => sportovecEditorPayments($pdo, $id)];
}

function sportovecEditorSave(PDO $pdo, int $id, array $input, int $actor): void
{
    if ($id < 1 || $actor < 1) throw new InvalidArgumentException('Nejprve vyberte sportovce k úpravě.');
    $pdo->beginTransaction();
    try {
        $old = sportovecEditorPerson($pdo, $id, true);
        $new = [];
        foreach (sportovecEditorFields() as $field => [$label, $type, $limit]) {
            // Missing controls must not erase data (also supports an older open form).
            if (!array_key_exists($field, $input)) continue;
            if (!is_string($input[$field])) throw new InvalidArgumentException('Neplatná hodnota: ' . $label . '.');
            $value = trim($input[$field]);
            if (mb_strlen($value, 'UTF-8') > $limit) throw new InvalidArgumentException($label . ': příliš dlouhá hodnota.');
            if (in_array($field, ['jmeno', 'prijmeni'], true) && $value === '') {
                throw new InvalidArgumentException('Jméno a příjmení jsou povinné.');
            }
            if ($field === 'narozeni') {
                if ($value !== '' && (personMatchV1Date($value) !== $value || $value > date('Y-m-d'))) {
                    throw new InvalidArgumentException('Datum narození není platné nebo je v budoucnosti.');
                }
                $value = $value === '' ? null : $value;
                if ($value !== ($old[$field] ?? null)) {
                    $sensitive = $pdo->prepare("SELECT * FROM osoba_citlive_udaje WHERE sportovec_id=? AND status<>'erased'");
                    $sensitive->execute([$id]);
                    foreach ($sensitive->fetchAll(PDO::FETCH_ASSOC) as $row) {
                        $digits = personSensitiveDecryptForAdmin($row);
                        try { personSensitiveValidateBirthNumber($digits, (string)$value); }
                        finally { sodium_memzero($digits); }
                    }
                }
            }
            $new[$field] = $value;
        }
        if ($new) {
            $assignments = implode(',', array_map(static fn($field) => $field . '=?', array_keys($new)));
            $pdo->prepare('UPDATE sportovci SET ' . $assignments . ' WHERE id=?')->execute([...array_values($new), $id]);
        }
        $profile = $pdo->prepare('SELECT country,contact_address FROM kis_person_profiles WHERE sportovec_id=?');
        $profile->execute([$id]);
        $oldProfile = $profile->fetch(PDO::FETCH_ASSOC);
        $newProfile = [];
        foreach (['country' => 100, 'contact_address' => 2000] as $field => $limit) {
            if (!$oldProfile || !array_key_exists($field, $input)) continue;
            if (!is_string($input[$field]) || mb_strlen(trim($input[$field]), 'UTF-8') > $limit) {
                throw new InvalidArgumentException('Země nebo kontaktní adresa mají neplatnou hodnotu.');
            }
            $newProfile[$field] = trim($input[$field]);
        }
        if ($newProfile) {
            $assignments = implode(',', array_map(static fn($field) => $field . '=?', array_keys($newProfile)));
            $pdo->prepare('UPDATE kis_person_profiles SET ' . $assignments . ' WHERE sportovec_id=?')->execute([...array_values($newProfile), $id]);
        }
        sportovecLogEvent($pdo, $id, 'profile_edit', 'Úprava osobních a kontaktních údajů',
            array_intersect_key($old, $new) + ($oldProfile ?: []), $new + $newProfile, 'manual', $actor);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
}

/** Explicit, audited access to the selected person's original records only. */
function sportovecEditorReveal(PDO $pdo, int $id, string $reason, ?array $keys = null, string $ip = ''): array
{
    personSensitiveRequireAdmin();
    if (mb_strlen(trim($reason), 'UTF-8') < 10) throw new InvalidArgumentException('Uveďte důvod zobrazení (alespoň 10 znaků).');
    $person = sportovecEditorPerson($pdo, $id);
    $pdo->beginTransaction();
    try {
        $legacyBirthNumber = trim((string)($person['rc'] ?? ''));
        $birthNumbers = $legacyBirthNumber !== '' ? [$legacyBirthNumber . ' (původní evidence)'] : [];
        $currentBirthNumber = $legacyBirthNumber;
        $stmt = $pdo->prepare("SELECT id FROM osoba_citlive_udaje WHERE sportovec_id=? AND status<>'erased'");
        $stmt->execute([$id]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $recordId) {
            $currentBirthNumber = personSensitiveAdminReveal($pdo, (int)$recordId, $reason, $keys, $ip);
            $birthNumbers[] = $currentBirthNumber;
        }
        $stmt = $pdo->prepare('SELECT p.canonical_key,b.* FROM kis_person_profiles p JOIN kis_person_batches b '
            . 'ON b.fingerprint=p.batch_fingerprint WHERE p.sportovec_id=?');
        $stmt->execute([$id]);
        $batch = $stmt->fetch(PDO::FETCH_ASSOC);
        $sourceRows = [];
        if ($batch) {
            $plain = kisPersonDecryptArchive($batch, $keys);
            try { $data = json_decode($plain, true, 64, JSON_THROW_ON_ERROR); }
            finally { sodium_memzero($plain); }
            $aliases = $pdo->prepare('SELECT source_key FROM kis_person_aliases WHERE sportovec_id=?');
            $aliases->execute([$id]);
            $sourceKeys = $aliases->fetchAll(PDO::FETCH_COLUMN);
            foreach ($data['source_archive']['people'] ?? [] as $row) {
                if (in_array($row['id_hash'] ?? null, $sourceKeys, true)) $sourceRows[] = sportovecEditorSourceFields($row);
            }
            // Do not place the decrypted archive, values, or reason in general history.
        }
        if ($batch || $legacyBirthNumber !== '') personSensitiveAudit($pdo, ['sportovec_id' => $id], 'reveal', $reason, $ip);
        $pdo->commit();
        if ($currentBirthNumber === '') {
            $candidates = [];
            foreach ($sourceRows as $row) foreach ($row as $key => $value) {
                if (is_string($value) && preg_match('/^(rc|rodne_?cislo|rodné číslo|rodne cislo)$/iu', (string)$key)) {
                    try { $digits = personSensitiveNormalizeBirthNumber($value); $candidates[$digits] = substr($digits, 0, 6) . '/' . substr($digits, 6); }
                    catch (InvalidArgumentException) { /* Keep malformed original data in the source view only. */ }
                }
            }
            if (count($candidates) === 1) $currentBirthNumber = (string)reset($candidates);
        }
        return ['birth_numbers' => $birthNumbers, 'source_rows' => $sourceRows, 'current_birth_number' => $currentBirthNumber];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
}

/** Source profiles can contain arbitrary legacy columns; credentials are never displayed. */
function sportovecEditorSourceFields(array $row): array
{
    $clean = [];
    foreach ($row as $key => $value) {
        if (preg_match('/heslo|password|token|secret|cipher|nonce|blind|keyring|^hash$/i', (string)$key)) continue;
        $clean[$key] = is_array($value) ? sportovecEditorSourceFields($value) : $value;
    }
    return $clean;
}

function sportovecEditorSourceLabel(string $key): string
{
    return ['rc' => 'Rodné číslo', 'rodnecislo' => 'Rodné číslo', 'rodne_cislo' => 'Rodné číslo',
        'jmeno' => 'Jméno', 'prijmeni' => 'Příjmení', 'telefon' => 'Telefon',
        'trvalaadresaulice' => 'Trvalá adresa – ulice', 'trvalaadresacp' => 'Číslo popisné',
        'trvalaadresaco' => 'Číslo orientační', 'trvalaadresaobec' => 'Obec',
        'trvalaadresapsc' => 'PSČ', 'datum_narozeni' => 'Datum narození',
    ][strtolower($key)] ?? $key;
}

function sportovecEditorContactValues(string $json): string
{
    $values = json_decode($json, true);
    return is_array($values) ? implode(', ', array_filter($values, 'is_string')) : '';
}
