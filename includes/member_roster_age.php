<?php

declare(strict_types=1);

require_once __DIR__ . '/roster_hierarchy.php';

/** Rule changes are audited with the same locked registrar operation as roster edits. */
function memberRosterAgeSave(PDO $pdo, int $actor, int $teamId, array $input, string $reason): array
{
    if (!$pdo->inTransaction()) {
        throw new LogicException('Věkové pravidlo vyžaduje transakci.');
    }
    $mode = kisRosterEnum((string) ($input['reference_mode'] ?? ''), ['none','on_date','year_end'], 'Rozhodný den');
    $min = filter_var($input['age_min'] ?? '', FILTER_VALIDATE_INT);
    $max = filter_var($input['age_max'] ?? '', FILTER_VALIDATE_INT);
    $nextId = (int) ($input['next_team_id'] ?? 0);
    if ($mode !== 'none' && ($min === false || $max === false || $min < 0 || $max > 120 || $min > $max
    || $nextId === $teamId)) {
        throw new InvalidArgumentException('Vyplňte věkové rozmezí 0–120 a odlišnou navazující soupisku.');
    }
    $query = $pdo->prepare("SELECT id FROM club_teams WHERE id IN (?,?) AND team_kind='roster' AND status='active' ORDER BY id"
        . ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : ''));
    $query->execute([$teamId,$nextId]);
    $found = array_map('intval', $query->fetchAll(PDO::FETCH_COLUMN));
    if (!in_array($teamId, $found, true) || ($nextId > 0 && !in_array($nextId, $found, true))) {
        throw new KisRosterException('Pravidlo vyžaduje aktivní soupisky.');
    }
    $query = $pdo->prepare('SELECT * FROM member_roster_age_rules WHERE team_id=?');
    $query->execute([$teamId]);
    $before = $query->fetch(PDO::FETCH_ASSOC) ?: [];
    $pdo->prepare('DELETE FROM member_roster_age_rules WHERE team_id=?')->execute([$teamId]);
    $after = [];
    if ($mode !== 'none') {
        $after = ['team_id' => $teamId,'age_min' => $min,'age_max' => $max,'reference_mode' => $mode,'next_team_id'
    => $nextId ?: null];
        $pdo->prepare('INSERT INTO member_roster_age_rules(team_id,age_min,age_max,reference_mode,next_team_id) VALUES(?,?,?,?,?)')
            ->execute(array_values($after));
    }
    kisRosterStructureEvent($pdo, 'team', $teamId, $actor, 'age_rule', $reason, $before, $after);
    return ['id' => $teamId];
}

/** Suggestions never create membership. Unknown birthdays remain review items. */
function memberRosterAgeReview(PDO $pdo, int $teamId, string $on): array
{
    $on = kisRosterDate($on);
    $query = $pdo->prepare('SELECT * FROM member_roster_age_rules WHERE team_id=?');
    $query->execute([$teamId]);
    $rule = $query->fetch(PDO::FETCH_ASSOC);
    if (!$rule) {
        return ['rule' => null,'rows' => [],'on' => $on];
    }
    $date = $rule['reference_mode'] === 'year_end' ? substr($on, 0, 4) . '-12-31' : $on;
    $ids = kisRosterEffectiveMemberIds($pdo, $teamId, $on);
    if (!$ids) {
        return ['rule' => $rule,'rows' => [],'on' => $date];
    }
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $query = $pdo->prepare("SELECT id,jmeno,prijmeni,narozeni FROM sportovci WHERE id IN ($marks) ORDER BY prijmeni,jmeno,id");
    $query->execute($ids);
    $rows = $query->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $born = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $row['narozeni']);
        $age = $born && $born->format('Y-m-d') === $row['narozeni'] && $row['narozeni'] <= $date
            ? $born->diff(new DateTimeImmutable($date))->y : null;
        $row['age'] = $age;
        $row['review'] = $age === null ? 'unknown' : ($age < (int) $rule['age_min'] ? 'too_young' : ($age > (int) $rule['age_max']
    ? 'too_old' : 'eligible'));
        $row['suggested_team_id'] = $row['review'] === 'too_old' ? $rule['next_team_id'] : null;
    }
    unset($row);
    return ['rule' => $rule,'rows' => $rows,'on' => $date];
}
