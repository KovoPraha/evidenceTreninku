<?php
declare(strict_types=1);

require_once __DIR__ . '/athlete_registration.php';
require_once __DIR__ . '/person_match.php';
require_once __DIR__ . '/kis_roster.php';

final class AthleteRegistrationAdminException extends RuntimeException
{
}

/** @return array<string,mixed> */
function athleteRegistrationAdminClaim(PDO $pdo, int $requestId, bool $lock = false): array
{
    if ($requestId < 1) throw new InvalidArgumentException('Neplatná registrační žádost.');
    $sql = 'SELECT c.*,d.has_czech_birth_number,d.contact_email_snapshot,d.contact_phone,'
        . 'd.citizenship_country_code,d.address_street,d.address_house_number,d.address_orientation_number,'
        . 'd.address_city,d.address_postcode,vu.email AS account_email,vu.aktivni AS account_active,'
        . 'vu.email_overeno AS account_email_verified '
        . 'FROM account_person_claim_requests c '
        . 'JOIN athlete_registration_request_details d ON d.request_id=c.id '
        . 'JOIN verejni_uzivatele vu ON vu.id=c.account_id '
        . "WHERE c.id=? AND c.request_kind='athlete_registration'";
    if ($lock && (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') $sql .= ' FOR UPDATE';
    $statement = $pdo->prepare($sql);
    $statement->execute([$requestId]);
    $claim = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$claim) throw new AthleteRegistrationAdminException('Registrační žádost nebyla nalezena.');
    return $claim;
}

/**
 * @param array{keys?:array<string,string>,active_version?:string,index_key?:string}|null $sensitiveConfig
 * @return array<string,mixed>
 */
function athleteRegistrationAdminReview(
    PDO $pdo,
    int $requestId,
    ?array $sensitiveConfig = null,
    string $ipAddress = ''
): array {
    $claim = athleteRegistrationAdminClaim($pdo, $requestId);
    $consents = $pdo->prepare(
        'SELECT purpose,terms_version,accepted,accepted_at,withdrawn_at '
        . 'FROM athlete_registration_consent_snapshots WHERE request_id=? ORDER BY purpose'
    );
    $consents->execute([$requestId]);
    $claim['consents'] = $consents->fetchAll(PDO::FETCH_ASSOC);

    $sensitive = $pdo->prepare(
        'SELECT id,status,sportovec_id,rc_blind_index FROM osoba_citlive_udaje WHERE request_id=? LIMIT 1'
    );
    $sensitive->execute([$requestId]);
    $sensitive = $sensitive->fetch(PDO::FETCH_ASSOC) ?: null;
    $claim['sensitive_record_id'] = $sensitive !== null ? (int)$sensitive['id'] : null;
    $claim['birth_number_masked'] = $sensitive !== null
        ? personSensitiveAdminMaskedView($pdo, (int)$sensitive['id'], $sensitiveConfig, $ipAddress)
        : null;

    $file = $pdo->prepare(
        "SELECT id,status FROM athlete_private_files WHERE request_id=? "
        . "AND file_kind='profile_photo' AND status='active' ORDER BY id DESC LIMIT 1"
    );
    $file->execute([$requestId]);
    $file = $file->fetch(PDO::FETCH_ASSOC) ?: null;
    $claim['private_photo_id'] = $file !== null ? (int)$file['id'] : null;
    $claim['match'] = athleteRegistrationAdminMatch($pdo, $claim, $sensitive);
    return $claim;
}

/** @param array<string,mixed> $claim @param array<string,mixed>|null $requestSensitive @return array<string,mixed> */
function athleteRegistrationAdminMatch(PDO $pdo, array $claim, ?array $requestSensitive = null): array
{
    $result = personMatchV1($pdo, [
        'jmeno' => $claim['claimed_jmeno'] ?? '',
        'prijmeni' => $claim['claimed_prijmeni'] ?? '',
        'narozeni' => $claim['claimed_narozeni'] ?? '',
    ]);
    if ($requestSensitive === null && isset($claim['id'])) {
        $statement = $pdo->prepare(
            'SELECT id,status,sportovec_id,rc_blind_index FROM osoba_citlive_udaje WHERE request_id=? LIMIT 1'
        );
        $statement->execute([(int)$claim['id']]);
        $requestSensitive = $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    $candidateSensitive = $pdo->prepare(
        "SELECT rc_blind_index FROM osoba_citlive_udaje WHERE sportovec_id=? AND status<>'erased' LIMIT 1"
    );
    foreach ($result['candidates'] as &$candidate) {
        $candidate['birth_number_match'] = false;
        $candidate['birth_number_conflict'] = false;
        $candidateSensitive->execute([(int)$candidate['id']]);
        $blindIndex = $candidateSensitive->fetchColumn();
        if ($blindIndex === false) continue;
        if ($requestSensitive === null) {
            $candidate['birth_number_conflict'] = (int)($claim['has_czech_birth_number'] ?? 0) === 0;
            continue;
        }
        $candidate['birth_number_match'] = hash_equals(
            (string)$requestSensitive['rc_blind_index'],
            (string)$blindIndex
        );
        $candidate['birth_number_conflict'] = !$candidate['birth_number_match'];
    }
    unset($candidate);
    return $result;
}

/** @return array{id:int,status:string,relation_id:int,person_id:int} */
function athleteRegistrationAdminApproveExisting(
    PDO $pdo,
    int $requestId,
    int $sportovecId,
    int $trainerId,
    string $note
): array {
    $note = athleteRegistrationAdminDecisionNote($requestId, $trainerId, $note);
    if ($sportovecId < 1) throw new InvalidArgumentException('Vyberte ověřenou existující osobu.');
    $pdo->beginTransaction();
    try {
        $claim = athleteRegistrationAdminClaim($pdo, $requestId, true);
        athleteRegistrationAdminAssertApprovable($claim);
        $match = athleteRegistrationAdminMatch($pdo, $claim);
        athleteRegistrationAdminApplyToPerson($pdo, $claim, $sportovecId);
        $approval = accountPersonClaimApprove($pdo, $requestId, $sportovecId, $trainerId, $note);
        personMatchV1Audit($pdo, $trainerId, 'athlete_registration_link', $match, null, $note, [
            'source' => 'eshop_identity_admin',
            'request_id' => $requestId,
            'selected_person_id' => $sportovecId,
        ]);
        $pdo->commit();
        return [
            'id' => $requestId,
            'status' => 'approved',
            'relation_id' => (int)$approval['relation_id'],
            'person_id' => $sportovecId,
        ];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        athleteRegistrationAdminRethrow($exception, 'Registrační žádost se nepodařilo bezpečně připojit.');
    }
}

/** @return array{id:int,status:string,relation_id:int,person_id:int} */
function athleteRegistrationAdminCreatePerson(
    PDO $pdo,
    int $requestId,
    int $trainerId,
    string $note,
    string $confirmation,
    string $overrideReason
): array {
    $note = athleteRegistrationAdminDecisionNote($requestId, $trainerId, $note);
    $overrideReason = trim($overrideReason);
    $claim = athleteRegistrationAdminClaim($pdo, $requestId);
    athleteRegistrationAdminAssertApprovable($claim);
    $preview = athleteRegistrationAdminMatch($pdo, $claim);
    athleteRegistrationAdminAssertCreateConfirmation($preview, $confirmation, $overrideReason);

    $pdo->beginTransaction();
    try {
        $claim = athleteRegistrationAdminClaim($pdo, $requestId, true);
        athleteRegistrationAdminAssertApprovable($claim);
        $freshMatch = athleteRegistrationAdminMatch($pdo, $claim);
        athleteRegistrationAdminAssertCreateConfirmation($freshMatch, $confirmation, $overrideReason);
        $personId = personMatchV1CreateManual($pdo, [
            'jmeno' => $claim['claimed_jmeno'],
            'prijmeni' => $claim['claimed_prijmeni'],
            'narozeni' => $claim['claimed_narozeni'],
            'email' => $claim['contact_email_snapshot'],
        ]);
        athleteRegistrationAdminApplyToPerson($pdo, $claim, $personId);
        $approval = accountPersonClaimApprove($pdo, $requestId, $personId, $trainerId, $note);
        $action = $freshMatch['level'] === PERSON_MATCH_EXACT
            ? 'athlete_registration_override_create'
            : ($freshMatch['level'] === PERSON_MATCH_SIMILARITY
                ? 'athlete_registration_similarity_create'
                : 'athlete_registration_create');
        personMatchV1Audit(
            $pdo,
            $trainerId,
            $action,
            $freshMatch,
            $personId,
            $freshMatch['level'] === PERSON_MATCH_EXACT ? $overrideReason : $note,
            ['source' => 'eshop_identity_admin', 'request_id' => $requestId]
        );
        $pdo->commit();
        return [
            'id' => $requestId,
            'status' => 'approved',
            'relation_id' => (int)$approval['relation_id'],
            'person_id' => $personId,
        ];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        athleteRegistrationAdminRethrow($exception, 'Registrační žádost se nepodařilo bezpečně schválit.');
    }
}

/** @return array{id:int,status:string} */
function athleteRegistrationAdminReject(
    PDO $pdo,
    int $requestId,
    int $trainerId,
    string $note
): array {
    $note = athleteRegistrationAdminDecisionNote($requestId, $trainerId, $note);
    $pdo->beginTransaction();
    try {
        $claim = athleteRegistrationAdminClaim($pdo, $requestId, true);
        athleteRegistrationAdminAssertPending($claim);
        $retentionUntil = (new DateTimeImmutable('+30 days'))->format('Y-m-d H:i:s');
        $pdo->prepare(
            "UPDATE account_person_claim_requests SET status='rejected',active_fingerprint=NULL,"
            . 'decided_by_trainer_id=?,decision_note=?,decided_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP WHERE id=?'
        )->execute([$trainerId, $note, $requestId]);
        $pdo->prepare(
            "UPDATE osoba_citlive_udaje SET status='retention_pending',retention_reason='rejected_request',"
            . "retention_until=?,updated_at=CURRENT_TIMESTAMP WHERE request_id=? AND status<>'erased'"
        )->execute([$retentionUntil, $requestId]);
        $pdo->prepare(
            "UPDATE athlete_private_files SET status='retention_pending' WHERE request_id=? AND status='active'"
        )->execute([$requestId]);
        accountPersonClaimEvent($pdo, $requestId, 'trainer', $trainerId, 'rejected', 'pending', 'rejected', $note);
        $pdo->commit();
        return ['id' => $requestId, 'status' => 'rejected'];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        athleteRegistrationAdminRethrow($exception, 'Registrační žádost se nepodařilo bezpečně zamítnout.');
    }
}

/** @return array{groups:list<array<string,mixed>>,subgroups:list<array<string,mixed>>,teams:list<array<string,mixed>>} */
function athleteRegistrationAdminAssignmentOptions(PDO $pdo): array
{
    return [
        'groups' => $pdo->query('SELECT id,nazev FROM skupiny ORDER BY poradi,nazev,id')->fetchAll(PDO::FETCH_ASSOC),
        'subgroups' => $pdo->query(
            'SELECT p.id,p.skupina_id,p.nazev,s.nazev AS skupina_nazev FROM podskupiny p '
            . 'JOIN skupiny s ON s.id=p.skupina_id ORDER BY s.poradi,p.poradi,p.nazev,p.id'
        )->fetchAll(PDO::FETCH_ASSOC),
        'teams' => $pdo->query(
            "SELECT t.id,t.name,t.season_id,s.code AS season_code,s.name AS season_name,s.starts_on,s.ends_on "
            . "FROM club_teams t JOIN club_seasons s ON s.id=t.season_id "
            . "WHERE t.status='active' AND s.status='active' AND s.ends_on>=CURRENT_DATE "
            . 'ORDER BY s.starts_on DESC,t.name,t.id'
        )->fetchAll(PDO::FETCH_ASSOC),
    ];
}

/** @return array<string,mixed> */
function athleteRegistrationAdminAssignmentContext(PDO $pdo, int $requestId): array
{
    $claim = athleteRegistrationAdminClaim($pdo, $requestId);
    if ((string)$claim['status'] !== 'approved' || (int)($claim['matched_sportovec_id'] ?? 0) < 1) {
        throw new AthleteRegistrationAdminException('Zařadit lze pouze schválenou registraci s přiřazenou osobou.');
    }
    $person = $pdo->prepare('SELECT id,jmeno,prijmeni,narozeni FROM sportovci WHERE id=?');
    $person->execute([(int)$claim['matched_sportovec_id']]);
    $claim['person'] = $person->fetch(PDO::FETCH_ASSOC);
    if (!$claim['person']) throw new AthleteRegistrationAdminException('Schválená osoba nebyla nalezena.');
    $claim['options'] = athleteRegistrationAdminAssignmentOptions($pdo);
    $state = $pdo->prepare(
        'SELECT m.id AS roster_member_id,m.status AS roster_status,t.id AS team_id,t.name AS team_name,'
        . 's.id AS season_id,s.name AS season_name FROM club_roster_members m '
        . 'JOIN club_teams t ON t.id=m.team_id JOIN club_seasons s ON s.id=t.season_id '
        . "WHERE m.sportovec_id=? AND m.status='active' AND m.valid_to IS NULL ORDER BY s.starts_on DESC,t.name"
    );
    $state->execute([(int)$claim['matched_sportovec_id']]);
    $claim['active_rosters'] = $state->fetchAll(PDO::FETCH_ASSOC);
    return $claim;
}

/** @return array{request_id:int,person_id:int,roster_member_id:int,changed:bool} */
function athleteRegistrationAdminAssign(
    PDO $pdo,
    int $requestId,
    int $groupId,
    int $subgroupId,
    int $teamId,
    int $trainerId,
    string $reason
): array {
    $reason = athleteRegistrationAdminDecisionNote($requestId, $trainerId, $reason);
    if (min($groupId, $subgroupId, $teamId) < 1) {
        throw new InvalidArgumentException('Vyberte skupinu, její podskupinu a sezonní tým.');
    }
    $pdo->beginTransaction();
    try {
        $claim = athleteRegistrationAdminClaim($pdo, $requestId, true);
        if ((string)$claim['status'] !== 'approved' || (int)($claim['matched_sportovec_id'] ?? 0) < 1) {
            throw new AthleteRegistrationAdminException('Zařadit lze pouze schválenou registraci s přiřazenou osobou.');
        }
        $personId = (int)$claim['matched_sportovec_id'];
        $subgroupSql = 'SELECT p.id,p.nazev,s.id AS skupina_id,s.nazev AS skupina_nazev '
            . 'FROM podskupiny p JOIN skupiny s ON s.id=p.skupina_id WHERE p.id=? AND s.id=?';
        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') $subgroupSql .= ' FOR UPDATE';
        $subgroup = $pdo->prepare($subgroupSql);
        $subgroup->execute([$subgroupId, $groupId]);
        $subgroup = $subgroup->fetch(PDO::FETCH_ASSOC);
        if (!$subgroup) throw new AthleteRegistrationAdminException('Vybraná podskupina nepatří do vybrané skupiny.');

        $teamSql = 'SELECT t.id,t.name,t.season_id,s.name AS season_name,s.starts_on,s.ends_on '
            . 'FROM club_teams t JOIN club_seasons s ON s.id=t.season_id '
            . "WHERE t.id=? AND t.status='active' AND s.status='active' AND s.ends_on>=CURRENT_DATE";
        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') $teamSql .= ' FOR UPDATE';
        $team = $pdo->prepare($teamSql);
        $team->execute([$teamId]);
        $team = $team->fetch(PDO::FETCH_ASSOC);
        if (!$team) throw new AthleteRegistrationAdminException('Vybraný tým nebo jeho sezona už nejsou aktivní.');

        $groupExists = $pdo->prepare('SELECT 1 FROM sportovec_skupina WHERE sportovec_id=? AND skupina_id=?');
        $groupExists->execute([$personId, $groupId]);
        $createdGroup = !$groupExists->fetchColumn();
        if ($createdGroup) {
            $pdo->prepare('INSERT INTO sportovec_skupina(sportovec_id,skupina_id) VALUES (?,?)')
                ->execute([$personId, $groupId]);
        }
        $subgroupExists = $pdo->prepare('SELECT 1 FROM sportovec_podskupina WHERE sportovec_id=? AND podskupina_id=?');
        $subgroupExists->execute([$personId, $subgroupId]);
        $createdSubgroup = !$subgroupExists->fetchColumn();
        if ($createdSubgroup) {
            $pdo->prepare('INSERT INTO sportovec_podskupina(sportovec_id,podskupina_id) VALUES (?,?)')
                ->execute([$personId, $subgroupId]);
        }

        $memberSql = 'SELECT * FROM club_roster_members WHERE team_id=? AND sportovec_id=?';
        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') $memberSql .= ' FOR UPDATE';
        $member = $pdo->prepare($memberSql);
        $member->execute([$teamId, $personId]);
        $before = $member->fetch(PDO::FETCH_ASSOC) ?: null;
        $rosterChanged = $before === null || (string)$before['status'] !== 'active' || $before['valid_to'] !== null;
        $validFrom = max((new DateTimeImmutable('today'))->format('Y-m-d'), (string)$team['starts_on']);
        if ($before === null) {
            $snapshot = $pdo->prepare('SELECT kis_external_id FROM sportovci WHERE id=?');
            $snapshot->execute([$personId]);
            $kisExternalId = trim((string)$snapshot->fetchColumn()) ?: null;
            $pdo->prepare(
                "INSERT INTO club_roster_members(team_id,sportovec_id,status,source,kis_external_id_snapshot,"
                . "valid_from,valid_to,created_by_trainer_id) VALUES (?,?,'active','manual',?,?,NULL,?)"
            )->execute([$teamId, $personId, $kisExternalId, $validFrom, $trainerId]);
            $memberId = (int)$pdo->lastInsertId();
        } else {
            $memberId = (int)$before['id'];
            if ($rosterChanged) {
                $pdo->prepare(
                    "UPDATE club_roster_members SET status='active',source='manual',valid_from=?,valid_to=NULL,"
                    . 'created_by_trainer_id=?,updated_at=CURRENT_TIMESTAMP WHERE id=?'
                )->execute([$validFrom, $trainerId, $memberId]);
            }
        }

        $changed = $createdGroup || $createdSubgroup || $rosterChanged;
        if ($rosterChanged) {
            $after = [
                'id' => $memberId,
                'team_id' => $teamId,
                'sportovec_id' => $personId,
                'status' => 'active',
                'source' => 'manual',
                'valid_from' => $validFrom,
                'valid_to' => null,
            ];
            kisRosterEvent($pdo, $teamId, $memberId, $trainerId, 'athlete_registration_assign', $before, $after, $reason);
        }
        if ($changed) {
            $detail = json_encode([
                'contract' => ATHLETE_REGISTRATION_CONTRACT,
                'request_id' => $requestId,
                'sportovec_id' => $personId,
                'group_id' => $groupId,
                'subgroup_id' => $subgroupId,
                'team_id' => $teamId,
                'season_id' => (int)$team['season_id'],
                'reason' => $reason,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $pdo->prepare(
                'INSERT INTO ucto_audit_log(uzivatel_id,akce,tabulka,zaznam_id,detail,ip_adresa,user_agent) '
                . 'VALUES (?,?,?,?,?,?,?)'
            )->execute([
                $trainerId,
                'athlete_registration_assign',
                'sportovci',
                $personId,
                $detail,
                (string)($_SERVER['REMOTE_ADDR'] ?? ''),
                (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
            ]);
        }
        $pdo->commit();
        return [
            'request_id' => $requestId,
            'person_id' => $personId,
            'roster_member_id' => $memberId,
            'changed' => $changed,
        ];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        athleteRegistrationAdminRethrow($exception, 'Zařazení sportovce se nepodařilo bezpečně uložit.');
    }
}

/** @param array<string,mixed> $claim */
function athleteRegistrationAdminApplyToPerson(PDO $pdo, array $claim, int $sportovecId): void
{
    $person = $pdo->prepare('SELECT id FROM sportovci WHERE id=?');
    $person->execute([$sportovecId]);
    if (!$person->fetchColumn()) throw new AthleteRegistrationAdminException('Vybraná osoba nebyla nalezena.');

    $sensitive = $pdo->prepare(
        "SELECT id,sportovec_id,rc_blind_index FROM osoba_citlive_udaje WHERE request_id=? AND status<>'erased' LIMIT 1"
    );
    $sensitive->execute([(int)$claim['id']]);
    $requestSensitive = $sensitive->fetch(PDO::FETCH_ASSOC) ?: null;
    $existingSensitive = $pdo->prepare(
        "SELECT id,rc_blind_index FROM osoba_citlive_udaje WHERE sportovec_id=? AND status<>'erased' LIMIT 1"
    );
    $existingSensitive->execute([$sportovecId]);
    $existingSensitive = $existingSensitive->fetch(PDO::FETCH_ASSOC) ?: null;
    if ((int)$claim['has_czech_birth_number'] === 1 && $requestSensitive === null) {
        throw new AthleteRegistrationAdminException('Povinný chráněný záznam rodného čísla chybí.');
    }
    if ((int)$claim['has_czech_birth_number'] === 0 && $requestSensitive !== null) {
        throw new AthleteRegistrationAdminException('Žádost bez přiděleného českého RČ obsahuje neočekávaný chráněný záznam.');
    }
    if ((int)$claim['has_czech_birth_number'] === 0 && $existingSensitive !== null) {
        throw new AthleteRegistrationAdminException('Žádost uvádí, že české RČ nebylo přiděleno, ale vybraná osoba chráněný záznam má. Nejprve opravte konflikt dat.');
    }
    if ($requestSensitive !== null) {
        if ($existingSensitive !== null && (int)$existingSensitive['id'] !== (int)$requestSensitive['id']) {
            throw new AthleteRegistrationAdminException('Vybraná osoba už má jiný chráněný záznam rodného čísla. Nejprve opravte konflikt dat.');
        }
        if ($requestSensitive['sportovec_id'] !== null && (int)$requestSensitive['sportovec_id'] !== $sportovecId) {
            throw new AthleteRegistrationAdminException('Chráněný záznam je už přiřazen jiné osobě.');
        }
    }

    $pdo->prepare(
        'UPDATE sportovci SET email=?,telefon=?,adresa_ulice=?,adresa_cp=?,adresa_co=?,adresa_obec=?,adresa_psc=? WHERE id=?'
    )->execute([
        $claim['contact_email_snapshot'],
        $claim['contact_phone'],
        $claim['address_street'],
        $claim['address_house_number'],
        $claim['address_orientation_number'],
        $claim['address_city'],
        $claim['address_postcode'],
        $sportovecId,
    ]);
    if ($requestSensitive !== null) {
        $pdo->prepare(
            "UPDATE osoba_citlive_udaje SET sportovec_id=?,status='active',retention_reason=NULL,"
            . 'retention_until=NULL,updated_at=CURRENT_TIMESTAMP WHERE id=?'
        )->execute([$sportovecId, (int)$requestSensitive['id']]);
    }
    $pdo->prepare(
        "UPDATE athlete_private_files SET sportovec_id=? WHERE request_id=? AND status='active'"
    )->execute([$sportovecId, (int)$claim['id']]);
}

/** @param array<string,mixed> $claim */
function athleteRegistrationAdminAssertPending(array $claim): void
{
    if ((string)($claim['status'] ?? '') !== 'pending') {
        throw new AthleteRegistrationAdminException('Vyřídit lze pouze čekající registrační žádost.');
    }
}

/** @param array<string,mixed> $claim */
function athleteRegistrationAdminAssertApprovable(array $claim): void
{
    athleteRegistrationAdminAssertPending($claim);
    if ((int)($claim['account_active'] ?? 0) !== 1 || (int)($claim['account_email_verified'] ?? 0) !== 1) {
        throw new AthleteRegistrationAdminException('Účet žadatele už není aktivní nebo nemá ověřený e-mail. Žádost nelze schválit.');
    }
}

/** @param array<string,mixed> $match */
function athleteRegistrationAdminAssertCreateConfirmation(array $match, string $confirmation, string $overrideReason): void
{
    if (($match['level'] ?? '') === PERSON_MATCH_EXACT) {
        if ($confirmation !== 'exact_override') {
            throw new AthleteRegistrationAdminException('Nalezena přesná shoda. Připojte existující osobu, nebo výjimku výslovně potvrďte.');
        }
        if (mb_strlen(trim($overrideReason), 'UTF-8') < 10) {
            throw new InvalidArgumentException('Důvod výjimky musí mít alespoň 10 znaků.');
        }
    }
    if (($match['level'] ?? '') === PERSON_MATCH_SIMILARITY && $confirmation !== 'similarity') {
        throw new AthleteRegistrationAdminException('Nalezeny podobné osoby. Před založením je zkontrolujte a volbu potvrďte.');
    }
}

function athleteRegistrationAdminDecisionNote(int $requestId, int $trainerId, string $note): string
{
    $note = trim($note);
    if ($requestId < 1 || $trainerId < 1 || mb_strlen($note, 'UTF-8') < 10) {
        throw new InvalidArgumentException('Rozhodnutí vyžaduje důvod o délce alespoň 10 znaků.');
    }
    if (mb_strlen($note, 'UTF-8') > 1000) throw new InvalidArgumentException('Důvod smí mít nejvýše 1000 znaků.');
    return $note;
}

function athleteRegistrationAdminRethrow(Throwable $exception, string $message): never
{
    if ($exception instanceof InvalidArgumentException
        || $exception instanceof AthleteRegistrationAdminException
        || $exception instanceof AccountPersonClaimException
        || $exception instanceof AccountPersonRoleException
    ) {
        throw $exception;
    }
    throw new AthleteRegistrationAdminException($message, 0, $exception);
}
