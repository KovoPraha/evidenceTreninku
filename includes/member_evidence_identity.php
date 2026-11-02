<?php

declare(strict_types=1);

/** Resolve only an audited cutover rename. Never match by a similar name or create another roster. */
function memberEvidenceMappedTeam(PDO $pdo, string $oldCode, ?string $oldName = null): ?array
{
    if (!kisRosterHierarchyHasTable($pdo, 'member_evidence_cutover_runs')
        || !$pdo->query('SELECT 1 FROM member_evidence_cutover_runs LIMIT 1')->fetchColumn()) {
        return null;
    }
    $found = [];
    $query = $pdo->query("SELECT subject_id,before_json,after_json FROM club_roster_structure_events "
        . "WHERE subject_type='team' AND action='standing_cutover' ORDER BY id");
    foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $event) {
        $before = json_decode($event['before_json'], true, 512, JSON_THROW_ON_ERROR);
        if (($before['code'] ?? null) !== $oldCode || ($oldName !== null && ($before['name'] ?? null) !== $oldName)) {
            continue;
        }
        $after = json_decode($event['after_json'], true, 512, JSON_THROW_ON_ERROR);
        $lookup = $pdo->prepare('SELECT * FROM club_teams WHERE id=?');
        $lookup->execute([$event['subject_id']]);
        $team = $lookup->fetch(PDO::FETCH_ASSOC);
        if (!$team || (int) $team['id'] !== (int) ($after['id'] ?? 0)
            || $team['code'] !== ($after['standing_code'] ?? null) || $team['standing_code'] !== $team['code']
            || $team['name'] !== ($after['name'] ?? null) || $team['status'] !== 'active'
            || $team['team_kind'] !== 'roster' || $team['season_id'] !== null) {
            throw new RuntimeException('Převedená soupiska se liší od auditované mapy.');
        }
        $found[] = $team;
    }
    if (count($found) > 1) {
        throw new RuntimeException('Původní kód má nejednoznačnou převodní mapu.');
    }
    return $found[0] ?? null;
}
