<?php
declare(strict_types=1);

require_once __DIR__ . '/kis_roster.php';

/** @return list<array<string,mixed>> */
function kisHobbyTransitionSources(PDO $pdo): array
{
    return $pdo->query(
        "SELECT m.id member_id,m.sportovec_id,sp.jmeno,sp.prijmeni,sp.narozeni,"
        . "t.id team_id,t.name team_name,s.name season_name,m.valid_from "
        . "FROM club_roster_members m "
        . "JOIN sportovci sp ON sp.id=m.sportovec_id "
        . "JOIN club_teams t ON t.id=m.team_id "
        . "JOIN club_seasons s ON s.id=t.season_id "
        . "JOIN club_team_series ser ON ser.id=t.series_id AND ser.series_type='hobby' "
        . "WHERE m.status='active' AND m.valid_to IS NULL AND t.status='active' "
        . "ORDER BY sp.prijmeni,sp.jmeno,s.starts_on DESC,m.id"
    )->fetchAll(PDO::FETCH_ASSOC);
}

/** @return list<array<string,mixed>> */
function kisHobbyTransitionTargets(PDO $pdo): array
{
    return $pdo->query(
        "SELECT t.id team_id,t.name team_name,t.age_label,s.id season_id,s.name season_name,"
        . "s.starts_on,s.ends_on,ser.name series_name,ser.age_from_years,ser.age_to_years,"
        . "ser.birth_year_from,ser.birth_year_to "
        . "FROM club_teams t "
        . "JOIN club_seasons s ON s.id=t.season_id AND s.season_type='calendar_year' "
        . "JOIN club_team_series ser ON ser.id=t.series_id AND ser.series_type='age' "
        . "WHERE t.status='active' AND s.status='active' AND ser.status='active' "
        . "ORDER BY s.starts_on DESC,ser.age_from_years,t.name,t.id"
    )->fetchAll(PDO::FETCH_ASSOC);
}

/** @return array<string,mixed> */
function kisHobbyTransitionPreview(PDO $pdo, int $sourceMemberId, int $targetTeamId, string $transitionOn, bool $endHobby): array
{
    if ($sourceMemberId < 1 || $targetTeamId < 1) {
        throw new InvalidArgumentException('Náhled vyžaduje kroužkové členství a cílovou věkovou soupisku.');
    }
    $transitionOn = kisRosterDate($transitionOn);

    $source = $pdo->prepare(
        "SELECT m.*,sp.jmeno,sp.prijmeni,sp.narozeni,sp.stav_clenstvi,"
        . "t.name source_team_name,t.season_id source_season_id,s.name source_season_name,"
        . "ser.id source_series_id,ser.series_type source_series_type "
        . "FROM club_roster_members m "
        . "JOIN sportovci sp ON sp.id=m.sportovec_id "
        . "JOIN club_teams t ON t.id=m.team_id "
        . "JOIN club_seasons s ON s.id=t.season_id "
        . "JOIN club_team_series ser ON ser.id=t.series_id "
        . "WHERE m.id=?"
    );
    $source->execute([$sourceMemberId]);
    $source = $source->fetch(PDO::FETCH_ASSOC);
    if (!$source || $source['source_series_type'] !== 'hobby') {
        throw new KisRosterException('Zdrojové členství není v kroužkové sérii.');
    }
    if ($source['status'] !== 'active' || $source['valid_to'] !== null) {
        throw new KisRosterException('Zdrojové kroužkové členství už není aktivní.');
    }
    if ($source['stav_clenstvi'] === 'archiv') {
        throw new KisRosterException('Archivovaného sportovce nelze převést do závodního týmu.');
    }

    $target = $pdo->prepare(
        "SELECT t.*,s.name target_season_name,s.season_type,s.starts_on,s.ends_on,"
        . "ser.name target_series_name,ser.series_type target_series_type,"
        . "ser.age_from_years,ser.age_to_years,ser.birth_year_from,ser.birth_year_to "
        . "FROM club_teams t "
        . "JOIN club_seasons s ON s.id=t.season_id "
        . "JOIN club_team_series ser ON ser.id=t.series_id "
        . "WHERE t.id=?"
    );
    $target->execute([$targetTeamId]);
    $target = $target->fetch(PDO::FETCH_ASSOC);
    if (!$target || $target['status'] !== 'active' || $target['target_series_type'] !== 'age' || $target['season_type'] !== 'calendar_year') {
        throw new KisRosterException('Cíl musí být aktivní věková soupiska závodního kalendářního roku.');
    }
    if ($transitionOn < $target['starts_on'] || $transitionOn > $target['ends_on']) {
        throw new InvalidArgumentException('Datum přechodu musí ležet v cílové závodní sezóně.');
    }
    if ($transitionOn < $source['valid_from']) {
        throw new InvalidArgumentException('Datum přechodu nesmí předcházet začátku kroužkového členství.');
    }

    $birthDate = DateTimeImmutable::createFromFormat('!Y-m-d', (string)$source['narozeni']);
    if (!$birthDate || $birthDate->format('Y-m-d') !== (string)$source['narozeni']) {
        throw new KisRosterException('Sportovec nemá platné datum narození pro kontrolu věkové soupisky.');
    }
    $birthYear = (int)$birthDate->format('Y');
    $seasonYear = (int)substr((string)$target['starts_on'], 0, 4);
    $categoryAge = $seasonYear - $birthYear;
    $ageConfigured = $target['age_from_years'] !== null && $target['age_to_years'] !== null;
    $birthConfigured = $target['birth_year_from'] !== null && $target['birth_year_to'] !== null;
    if (!$ageConfigured && !$birthConfigured) {
        throw new KisRosterException('Cílová věková série nemá nastavené věkové ani ročníkové meze.');
    }
    $ageMatches = $ageConfigured
        ? $categoryAge >= (int)$target['age_from_years'] && $categoryAge <= (int)$target['age_to_years']
        : $birthYear >= (int)$target['birth_year_from'] && $birthYear <= (int)$target['birth_year_to'];
    if (!$ageMatches) {
        throw new KisRosterException('Sportovec podle data narození nepatří do vybrané věkové soupisky.');
    }

    $existing = $pdo->prepare('SELECT * FROM club_roster_members WHERE team_id=? AND sportovec_id=?');
    $existing->execute([$targetTeamId, (int)$source['sportovec_id']]);
    $targetMember = $existing->fetch(PDO::FETCH_ASSOC) ?: null;
    $otherAgeTeam = $pdo->prepare(
        "SELECT t.id,t.name FROM club_roster_members m "
        . "JOIN club_teams t ON t.id=m.team_id "
        . "JOIN club_team_series ser ON ser.id=t.series_id AND ser.series_type='age' "
        . "WHERE m.sportovec_id=? AND t.season_id=? AND t.id<>? "
        . "AND m.status='active' AND m.valid_to IS NULL LIMIT 1"
    );
    $otherAgeTeam->execute([(int)$source['sportovec_id'], (int)$target['season_id'], $targetTeamId]);
    if ($conflict = $otherAgeTeam->fetch(PDO::FETCH_ASSOC)) {
        throw new KisRosterException('Sportovec už je v této závodní sezóně na jiné věkové soupisce: ' . $conflict['name'] . '.');
    }
    $payload = [
        'operation' => 'hobby_to_race_v1',
        'source_member_id' => $sourceMemberId,
        'source_team_id' => (int)$source['team_id'],
        'sportovec_id' => (int)$source['sportovec_id'],
        'source_status' => (string)$source['status'],
        'source_valid_from' => (string)$source['valid_from'],
        'target_team_id' => $targetTeamId,
        'target_season_id' => (int)$target['season_id'],
        'target_member_id' => $targetMember === null ? null : (int)$targetMember['id'],
        'target_member_status' => $targetMember['status'] ?? null,
        'target_member_valid_to' => $targetMember['valid_to'] ?? null,
        'other_age_team' => null,
        'transition_on' => $transitionOn,
        'end_hobby' => $endHobby,
    ];
    $fingerprint = hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

    return [
        'source' => $source,
        'target' => $target,
        'target_member' => $targetMember,
        'transition_on' => $transitionOn,
        'end_hobby' => $endHobby,
        'category_age' => $categoryAge,
        'fingerprint' => $fingerprint,
        'mutation_count' => 0,
    ];
}

/** @return array<string,mixed> */
function kisHobbyTransitionExecute(PDO $pdo, int $sourceMemberId, int $targetTeamId, string $transitionOn, bool $endHobby, int $actorId, string $reason, bool $confirmed, string $previewFingerprint): array
{
    if (!$confirmed) {
        throw new InvalidArgumentException('Přechod vyžaduje explicitní potvrzení.');
    }
    if ($actorId < 1 || preg_match('/^[a-f0-9]{64}$/D', $previewFingerprint) !== 1) {
        throw new InvalidArgumentException('Přechod nemá platný administrátorský kontext nebo náhled.');
    }
    $reason = kisRosterText($reason, 1000, 'Důvod přechodu');
    $transitionOn = kisRosterDate($transitionOn);
    $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $lockName = null;
    if ($mysql) {
        $databaseName = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
        $lockName = 'kis_transition_' . substr(hash('sha256', $databaseName . ':' . $sourceMemberId . ':' . $targetTeamId), 0, 47);
        $lock = $pdo->prepare('SELECT GET_LOCK(?,10)');
        $lock->execute([$lockName]);
        if ((int)$lock->fetchColumn() !== 1) {
            throw new KisRosterException('Souběžný přechod se stále zpracovává. Zkuste požadavek znovu.');
        }
    }

    try {
        $pdo->beginTransaction();
        $sourceContext = $pdo->prepare('SELECT team_id,sportovec_id FROM club_roster_members WHERE id=?');
        $sourceContext->execute([$sourceMemberId]);
        $sourceContext = $sourceContext->fetch(PDO::FETCH_ASSOC);
        $sourceTeamId = (int)($sourceContext['team_id'] ?? 0);
        $sourceSportovecId = (int)($sourceContext['sportovec_id'] ?? 0);
        $targetContext = $pdo->prepare('SELECT season_id FROM club_teams WHERE id=?');
        $targetContext->execute([$targetTeamId]);
        $targetSeasonId = (int)$targetContext->fetchColumn();
        if ($sourceTeamId < 1 || $targetSeasonId < 1) {
            throw new KisRosterException('Zdrojové členství nebo cílová soupiska už neexistuje.');
        }

        $runSql = 'SELECT * FROM club_roster_rollover_runs WHERE source_team_id=? AND target_season_id=? AND preview_fingerprint=?';
        if ($mysql) $runSql .= ' FOR UPDATE';
        $run = $pdo->prepare($runSql);
        $run->execute([$sourceTeamId, $targetSeasonId, $previewFingerprint]);
        if ($existingRun = $run->fetch(PDO::FETCH_ASSOC)) {
            $pdo->commit();
            $result = json_decode((string)$existingRun['result_json'], true, 512, JSON_THROW_ON_ERROR);
            $result['run_id'] = (int)$existingRun['id'];
            $result['idempotent'] = true;
            kisRosterReleaseMysqlLock($pdo, $lockName);
            return $result;
        }

        if ($mysql) {
            // Match the existing rollover writer: source member -> target team -> target member.
            $sourceLock = $pdo->prepare('SELECT id FROM club_roster_members WHERE id=? FOR UPDATE');
            $sourceLock->execute([$sourceMemberId]);
            if (!$sourceLock->fetchColumn()) throw new KisRosterException('Zdrojové členství se mezitím změnilo.');
            $targetTeamLock = $pdo->prepare('SELECT id FROM club_teams WHERE id=? FOR UPDATE');
            $targetTeamLock->execute([$targetTeamId]);
            if (!$targetTeamLock->fetchColumn()) throw new KisRosterException('Cílová soupiska se mezitím změnila.');
            $targetMemberLock = $pdo->prepare('SELECT id FROM club_roster_members WHERE team_id=? AND sportovec_id=? FOR UPDATE');
            $targetMemberLock->execute([$targetTeamId, $sourceSportovecId]);
            $targetMemberLock->fetchAll();
        }

        $preview = kisHobbyTransitionPreview($pdo, $sourceMemberId, $targetTeamId, $transitionOn, $endHobby);
        if (!hash_equals($preview['fingerprint'], $previewFingerprint)) {
            throw new KisRosterException('Náhled se mezitím změnil. Obnovte jej a znovu potvrďte.');
        }
        $source = $preview['source'];
        $targetBefore = $preview['target_member'];
        $sportovecId = (int)$source['sportovec_id'];

        if ($targetBefore === null) {
            $pdo->prepare("INSERT INTO club_roster_members(team_id,sportovec_id,status,source,kis_external_id_snapshot,valid_from,valid_to,created_by_trainer_id) VALUES(?,?,'active','manual',?,?,NULL,?)")
                ->execute([$targetTeamId, $sportovecId, $source['kis_external_id_snapshot'] ?? null, $transitionOn, $actorId]);
            $targetMemberId = (int)$pdo->lastInsertId();
        } else {
            $targetMemberId = (int)$targetBefore['id'];
            if ($targetBefore['status'] !== 'active' || $targetBefore['valid_to'] !== null) {
                $pdo->prepare("UPDATE club_roster_members SET status='active',source='manual',valid_from=?,valid_to=NULL,created_by_trainer_id=?,updated_at=CURRENT_TIMESTAMP WHERE id=?")
                    ->execute([$transitionOn, $actorId, $targetMemberId]);
            }
        }
        $targetAfter = [
            'id' => $targetMemberId,
            'team_id' => $targetTeamId,
            'sportovec_id' => $sportovecId,
            'status' => 'active',
            'source' => 'manual',
            'valid_from' => $targetBefore !== null && $targetBefore['status'] === 'active' && $targetBefore['valid_to'] === null ? $targetBefore['valid_from'] : $transitionOn,
            'valid_to' => null,
        ];
        if ($targetBefore === null || $targetBefore['status'] !== 'active' || $targetBefore['valid_to'] !== null) {
            kisRosterEvent($pdo, $targetTeamId, $targetMemberId, $actorId, 'hobby_to_race_add_member', $targetBefore, $targetAfter, $reason);
        }

        $sourceAfter = $source;
        if ($endHobby) {
            $sourceAfter['status'] = 'removed';
            $sourceAfter['valid_to'] = $transitionOn;
            $pdo->prepare("UPDATE club_roster_members SET status='removed',valid_to=?,updated_at=CURRENT_TIMESTAMP WHERE id=?")
                ->execute([$transitionOn, $sourceMemberId]);
            kisRosterEvent($pdo, $sourceTeamId, $sourceMemberId, $actorId, 'hobby_to_race_close_hobby', $source, $sourceAfter, $reason);
        }

        $action = $endHobby ? 'hobby_to_race_end_hobby' : 'hobby_to_race_keep_hobby';
        $pdo->prepare('INSERT INTO club_roster_rollover_runs(source_team_id,target_season_id,preview_fingerprint,actor_trainer_id,reason,moved_count,skipped_count,result_json) VALUES(?,?,?,?,?,1,0,?)')
            ->execute([$sourceTeamId, $targetSeasonId, $previewFingerprint, $actorId, $reason, '{}']);
        $runId = (int)$pdo->lastInsertId();
        $after = ['source' => $sourceAfter, 'target' => $targetAfter, 'end_hobby' => $endHobby];
        $pdo->prepare('INSERT INTO club_roster_rollover_run_items(run_id,sportovec_id,source_member_id,target_team_id,target_member_id,action,before_json,after_json) VALUES(?,?,?,?,?,?,?,?)')
            ->execute([$runId, $sportovecId, $sourceMemberId, $targetTeamId, $targetMemberId, $action, json_encode($source, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), json_encode($after, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
        $result = [
            'run_id' => $runId,
            'source_team_id' => $sourceTeamId,
            'target_season_id' => $targetSeasonId,
            'sportovec_id' => $sportovecId,
            'target_member_id' => $targetMemberId,
            'end_hobby' => $endHobby,
            'fingerprint' => $previewFingerprint,
            'moved_count' => 1,
            'skipped_count' => 0,
            'idempotent' => false,
        ];
        $pdo->prepare('UPDATE club_roster_rollover_runs SET result_json=? WHERE id=?')
            ->execute([json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $runId]);
        $pdo->commit();
        kisRosterReleaseMysqlLock($pdo, $lockName);
        return $result;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        kisRosterReleaseMysqlLock($pdo, $lockName);
        if ($e instanceof InvalidArgumentException || $e instanceof KisRosterException) throw $e;
        throw new KisRosterException('Přechod se nepodařilo provést bez částečného zápisu.', 0, $e);
    }
}
