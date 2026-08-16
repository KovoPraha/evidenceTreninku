<?php
declare(strict_types=1);

require_once __DIR__ . '/public_profile_token.php';

const PERSON_MATCH_V1 = 'person-match-v1';
const PERSON_MATCH_EXACT = 'exact';
const PERSON_MATCH_SIMILARITY = 'similarity';
const PERSON_MATCH_NONE = 'none';

/**
 * Jediná sdílená implementace kontraktu person-match-v1.
 *
 * @param array{jmeno?:mixed,prijmeni?:mixed,narozeni?:mixed} $input
 * @return array{contract:string,level:string,candidates:list<array<string,mixed>>,exact_count:int,similarity_count:int}
 */
function personMatchV1(PDO $pdo, array $input): array
{
    $firstName = personMatchV1NormalizeName((string)($input['jmeno'] ?? ''));
    $lastName = personMatchV1NormalizeName((string)($input['prijmeni'] ?? ''));
    $birthDate = personMatchV1Date($input['narozeni'] ?? null);
    if ($firstName === '' || $lastName === '') {
        return personMatchV1Result([]);
    }

    $rows = $pdo->query(
        'SELECT id,jmeno,prijmeni,narozeni,kis_external_id FROM sportovci ORDER BY id'
    )->fetchAll(PDO::FETCH_ASSOC);
    $candidates = [];
    foreach ($rows as $row) {
        $rowFirstName = personMatchV1NormalizeName((string)($row['jmeno'] ?? ''));
        $rowLastName = personMatchV1NormalizeName((string)($row['prijmeni'] ?? ''));
        $rowBirthDate = personMatchV1Date($row['narozeni'] ?? null);
        $sameFirstName = hash_equals($rowFirstName, $firstName);
        $sameLastName = hash_equals($rowLastName, $lastName);

        if ($sameFirstName && $sameLastName && $birthDate !== null && $rowBirthDate === $birthDate) {
            $candidates[] = personMatchV1Candidate($row, PERSON_MATCH_EXACT, ['SHODA']);
            continue;
        }

        $rules = [];
        if ($sameLastName && !$sameFirstName && $birthDate !== null && $rowBirthDate === $birthDate) {
            $rules[] = 'P1';
        }
        if ($sameFirstName && $sameLastName && $birthDate !== null && $rowBirthDate === null) {
            $rules[] = 'P2';
        }
        if ($sameFirstName && $sameLastName && $birthDate !== null && $rowBirthDate !== null) {
            $inputDate = new DateTimeImmutable($birthDate);
            $rowDate = new DateTimeImmutable($rowBirthDate);
            $days = (int)$inputDate->diff($rowDate)->format('%a');
            $swappedDayMonth = $inputDate->format('Y') === $rowDate->format('Y')
                && $inputDate->format('m') === $rowDate->format('d')
                && $inputDate->format('d') === $rowDate->format('m');
            if (($days > 0 && $days < 32) || $swappedDayMonth) {
                $rules[] = 'P3';
            }
            if ($inputDate->format('Y') === $rowDate->format('Y')) {
                $rules[] = 'P4';
            }
        }
        if ($rules !== []) {
            $candidates[] = personMatchV1Candidate($row, PERSON_MATCH_SIMILARITY, array_values(array_unique($rules)));
        }
    }

    usort($candidates, static function (array $a, array $b): int {
        $levelOrder = [PERSON_MATCH_EXACT => 0, PERSON_MATCH_SIMILARITY => 1];
        return [$levelOrder[$a['level']], (int)$a['id']] <=> [$levelOrder[$b['level']], (int)$b['id']];
    });
    return personMatchV1Result($candidates);
}

function personMatchV1NormalizeName(string $value): string
{
    $value = str_replace("\xC2\xA0", ' ', trim($value));
    $value = (string)preg_replace('/\s+/u', ' ', $value);
    $map = [
        'á'=>'a','ä'=>'a','č'=>'c','ď'=>'d','é'=>'e','ě'=>'e','ë'=>'e','í'=>'i','ĺ'=>'l','ľ'=>'l',
        'ň'=>'n','ó'=>'o','ô'=>'o','ö'=>'o','ř'=>'r','ŕ'=>'r','š'=>'s','ť'=>'t','ú'=>'u','ů'=>'u',
        'ü'=>'u','ý'=>'y','ž'=>'z','Á'=>'A','Ä'=>'A','Č'=>'C','Ď'=>'D','É'=>'E','Ě'=>'E','Ë'=>'E',
        'Í'=>'I','Ĺ'=>'L','Ľ'=>'L','Ň'=>'N','Ó'=>'O','Ô'=>'O','Ö'=>'O','Ř'=>'R','Ŕ'=>'R','Š'=>'S',
        'Ť'=>'T','Ú'=>'U','Ů'=>'U','Ü'=>'U','Ý'=>'Y','Ž'=>'Z',
    ];
    $value = mb_strtolower(strtr($value, $map), 'UTF-8');
    // T9 kontraktu považuje mezeru i spojovník uvnitř složeného jména za stejný oddělovač.
    $value = (string)preg_replace('/[\s\p{Pd}\'’ʼ]+/u', '', $value);
    return trim($value);
}

function personMatchV1Date(mixed $value): ?string
{
    $value = trim((string)$value);
    if ($value === '' || $value === '0000-00-00') {
        return null;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value ? $value : null;
}

/** @return array<string,mixed> */
function personMatchV1Candidate(array $row, string $level, array $rules): array
{
    return [
        'id' => (int)$row['id'],
        'jmeno' => (string)($row['jmeno'] ?? ''),
        'prijmeni' => (string)($row['prijmeni'] ?? ''),
        'narozeni' => personMatchV1Date($row['narozeni'] ?? null),
        'kis_external_id' => trim((string)($row['kis_external_id'] ?? '')) ?: null,
        'level' => $level,
        'rules' => $rules,
    ];
}

/** @param list<array<string,mixed>> $candidates */
function personMatchV1Result(array $candidates): array
{
    $exactCount = count(array_filter($candidates, static fn(array $c): bool => $c['level'] === PERSON_MATCH_EXACT));
    $similarityCount = count($candidates) - $exactCount;
    return [
        'contract' => PERSON_MATCH_V1,
        'level' => $exactCount > 0 ? PERSON_MATCH_EXACT : ($similarityCount > 0 ? PERSON_MATCH_SIMILARITY : PERSON_MATCH_NONE),
        'candidates' => $candidates,
        'exact_count' => $exactCount,
        'similarity_count' => $similarityCount,
    ];
}

/**
 * Audit vyhodnocení/override do existujícího obecného auditního logu (bez migrace).
 *
 * @param array<string,mixed> $result
 * @param array<string,mixed> $context
 */
function personMatchV1Audit(
    PDO $pdo,
    int $actorTrainerId,
    string $action,
    array $result,
    ?int $createdPersonId,
    string $reason,
    array $context = []
): void {
    if ($actorTrainerId < 1) {
        throw new InvalidArgumentException('Audit shody vyžaduje identifikátor administrátora.');
    }
    $candidateIds = array_map(static fn(array $candidate): int => (int)$candidate['id'], $result['candidates'] ?? []);
    $detail = json_encode([
        'contract' => PERSON_MATCH_V1,
        'action' => $action,
        'actor_trainer_id' => $actorTrainerId,
        'candidate_ids' => $candidateIds,
        'created_person_id' => $createdPersonId,
        'reason' => trim($reason),
        'context' => $context,
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $statement = $pdo->prepare(
        'INSERT INTO ucto_audit_log '
        . '(uzivatel_id,akce,tabulka,zaznam_id,detail,ip_adresa,user_agent) VALUES (?,?,?,?,?,?,?)'
    );
    $statement->execute([
        $actorTrainerId,
        'person_match_v1_' . $action,
        'sportovci',
        $createdPersonId ?? ($candidateIds[0] ?? null),
        $detail,
        (string)($_SERVER['REMOTE_ADDR'] ?? ''),
        (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
    ]);
}

/** @param array{jmeno:mixed,prijmeni:mixed,narozeni:mixed,email?:mixed} $input */
function personMatchV1CreateManual(PDO $pdo, array $input): int
{
    $firstName = trim((string)$input['jmeno']);
    $lastName = trim((string)$input['prijmeni']);
    $birthDate = personMatchV1Date($input['narozeni']);
    $email = trim((string)($input['email'] ?? ''));
    if ($firstName === '' || $lastName === '' || $birthDate === null) {
        throw new InvalidArgumentException('Jméno, příjmení a platné datum narození jsou povinné.');
    }
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        throw new InvalidArgumentException('Zadejte platný e-mail nebo pole ponechte prázdné.');
    }
    for ($attempt = 0; $attempt < 3; $attempt++) {
        $hash = public_profile_token_generate();
        $collision = $pdo->prepare('SELECT id FROM sportovci WHERE hash=?');
        $collision->execute([$hash]);
        if (!$collision->fetchColumn()) {
            $insert = $pdo->prepare(
                'INSERT INTO sportovci '
                . '(jmeno,prijmeni,narozeni,email,hash,uci,stav_clenstvi,stav_manualni,kis_external_id) '
                . "VALUES (?,?,?,?,?,0,'cekajici',1,NULL)"
            );
            $insert->execute([$firstName, $lastName, $birthDate, $email, $hash]);
            return (int)$pdo->lastInsertId();
        }
    }
    throw new RuntimeException('Nepodařilo se bezpečně vygenerovat veřejný identifikátor osoby.');
}
