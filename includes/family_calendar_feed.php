<?php
declare(strict_types=1);

require_once __DIR__ . '/family_portal.php';
require_once __DIR__ . '/public_calendar_feed.php';

final class FamilyCalendarFeedException extends RuntimeException
{
}

/** @return array<string,mixed>|null */
function familyCalendarFeedState(PDO $pdo, int $accountId): ?array
{
    if ($accountId < 1 || !publicCalendarHasTables($pdo, ['family_calendar_feeds'])) return null;
    $statement = $pdo->prepare('SELECT id,account_id,token_hint,active,created_at,rotated_at,revoked_at FROM family_calendar_feeds WHERE account_id=?');
    $statement->execute([$accountId]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

/** @return array{token:string,hint:string,created:bool} */
function familyCalendarFeedIssue(PDO $pdo, int $accountId): array
{
    if ($accountId < 1 || !publicCalendarHasTables($pdo, ['family_calendar_feeds', 'family_calendar_feed_events'])) {
        throw new FamilyCalendarFeedException('Rodinný kalendář zatím není připravený.');
    }
    if (familyPortalAuthorizedPeople($pdo, $accountId) === []) {
        throw new FamilyCalendarFeedException('Rodinný kalendář vyžaduje alespoň jeden schválený profil.');
    }
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    $hint = substr($token, -8);
    $pdo->beginTransaction();
    try {
        $sql = 'SELECT * FROM family_calendar_feeds WHERE account_id=?';
        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') $sql .= ' FOR UPDATE';
        $statement = $pdo->prepare($sql);
        $statement->execute([$accountId]);
        $existing = $statement->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            $pdo->prepare(
                'UPDATE family_calendar_feeds SET token_hash=?,token_hint=?,active=1,rotated_at=CURRENT_TIMESTAMP,'
                . 'revoked_at=NULL,updated_at=CURRENT_TIMESTAMP WHERE id=?'
            )->execute([$hash, $hint, (int)$existing['id']]);
            $feedId = (int)$existing['id'];
            $action = 'rotate';
            $created = false;
        } else {
            $pdo->prepare(
                'INSERT INTO family_calendar_feeds(account_id,token_hash,token_hint,active,rotated_at) '
                . 'VALUES(?,?,?,1,CURRENT_TIMESTAMP)'
            )->execute([$accountId, $hash, $hint]);
            $feedId = (int)$pdo->lastInsertId();
            $action = 'create';
            $created = true;
        }
        familyCalendarFeedAudit($pdo, $feedId, $accountId, $action, $hint,
            $created ? 'Vytvořen rodinný kalendář.' : 'Odkaz rodinného kalendáře byl otočen.');
        $pdo->commit();
        return compact('token', 'hint', 'created');
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($exception instanceof FamilyCalendarFeedException) throw $exception;
        throw new FamilyCalendarFeedException('Odkaz kalendáře se nepodařilo bezpečně vytvořit.', 0, $exception);
    }
}

/** @return array{changed:bool} */
function familyCalendarFeedRevoke(PDO $pdo, int $accountId): array
{
    if ($accountId < 1 || !publicCalendarHasTables($pdo, ['family_calendar_feeds', 'family_calendar_feed_events'])) {
        return ['changed' => false];
    }
    $pdo->beginTransaction();
    try {
        $sql = 'SELECT * FROM family_calendar_feeds WHERE account_id=?';
        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') $sql .= ' FOR UPDATE';
        $statement = $pdo->prepare($sql);
        $statement->execute([$accountId]);
        $feed = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$feed || (int)$feed['active'] !== 1) {
            $pdo->commit();
            return ['changed' => false];
        }
        $pdo->prepare(
            'UPDATE family_calendar_feeds SET active=0,revoked_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP WHERE id=?'
        )->execute([(int)$feed['id']]);
        familyCalendarFeedAudit($pdo, (int)$feed['id'], $accountId, 'revoke', (string)$feed['token_hint'],
            'Rodinný kalendář byl odvolán.');
        $pdo->commit();
        return ['changed' => true];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw new FamilyCalendarFeedException('Odkaz kalendáře se nepodařilo bezpečně odvolat.', 0, $exception);
    }
}

function familyCalendarFeedResolveAccount(PDO $pdo, string $token): ?int
{
    $token = trim($token);
    if (preg_match('/^[a-f0-9]{64}$/D', $token) !== 1 || !publicCalendarHasTables($pdo, ['family_calendar_feeds', 'verejni_uzivatele'])) {
        return null;
    }
    $statement = $pdo->prepare(
        'SELECT f.account_id FROM family_calendar_feeds f JOIN verejni_uzivatele a ON a.id=f.account_id '
        . 'WHERE f.token_hash=? AND f.active=1 AND a.aktivni=1 AND a.email_overeno=1 LIMIT 1'
    );
    $statement->execute([hash('sha256', $token)]);
    $accountId = (int)$statement->fetchColumn();
    return $accountId > 0 ? $accountId : null;
}

/** @return list<array{uid:string,start:string,end:string,all_day:bool,summary:string,location:string,description:string,category:string}> */
function familyCalendarItems(PDO $pdo, int $accountId, string $from, string $to): array
{
    $fromDate = publicCalendarDate($from);
    $toDate = publicCalendarDate($to);
    if ($fromDate === null || $toDate === null || $toDate < $fromDate || $toDate->diff($fromDate)->days > 370) {
        throw new InvalidArgumentException('Neplatné období rodinného kalendáře.');
    }
    $people = familyPortalAuthorizedPeople($pdo, $accountId);
    $personIds = array_values(array_unique(array_map(static fn (array $person): int => (int)$person['sportovec_id'], $people)));
    if ($personIds === []) return [];
    $placeholders = implode(',', array_fill(0, count($personIds), '?'));
    $items = [];

    if (publicCalendarHasTables($pdo, ['training_roster_links', 'club_roster_members', 'planovane_treninky', 'sportovci', 'sportovist', 'skupiny'])) {
        $statement = $pdo->prepare(
            'SELECT p.id,p.datum,p.cas_od,p.cas_do,p.nazev,p.kategorie,s.nazev AS sportoviste,g.nazev AS skupina,'
            . 'rm.sportovec_id,sp.jmeno,sp.prijmeni,l.team_name_snapshot FROM training_roster_links l '
            . 'JOIN club_roster_members rm ON rm.team_id=l.team_id JOIN sportovci sp ON sp.id=rm.sportovec_id '
            . 'JOIN planovane_treninky p ON p.id=l.plan_id LEFT JOIN sportovist s ON s.id=p.sportoviste_id '
            . 'LEFT JOIN skupiny g ON g.id=p.skupina_id WHERE rm.sportovec_id IN (' . $placeholders . ') '
            . "AND rm.status='active' AND rm.valid_from<=p.datum AND (rm.valid_to IS NULL OR rm.valid_to>=p.datum) "
            . "AND p.stav='planovany' AND p.datum BETWEEN ? AND ? ORDER BY p.datum,p.cas_od,p.id,rm.sportovec_id"
        );
        $statement->execute([...$personIds, $from, $to]);
        $seen = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $key = (int)$row['id'] . ':' . (int)$row['sportovec_id'];
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $item = familyCalendarTrainingItem($row);
            if ($item !== null) $items[] = $item;
        }
    }

    if (publicCalendarHasTables($pdo, ['club_event_registrations', 'club_events', 'club_event_sessions', 'sportovci'])) {
        $statement = $pdo->prepare(
            'SELECT r.id AS registration_id,r.status AS registration_status,r.sportovec_id,e.name,s.id AS session_id,'
            . 's.starts_at,s.ends_at,s.location,sp.jmeno,sp.prijmeni FROM club_event_registrations r '
            . 'JOIN club_events e ON e.id=r.event_id JOIN club_event_sessions s ON s.event_id=e.id '
            . 'JOIN sportovci sp ON sp.id=r.sportovec_id WHERE r.sportovec_id IN (' . $placeholders . ') '
            . "AND r.status IN ('confirmed','waitlisted','payment_pending') AND s.status='scheduled' "
            . 'AND s.starts_at<? AND s.ends_at>=? ORDER BY s.starts_at,s.id,r.id'
        );
        $statement->execute([...$personIds, $toDate->modify('+1 day')->format('Y-m-d 00:00:00'), $from . ' 00:00:00']);
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $status = familyCalendarRegistrationStatus((string)$row['registration_status']);
            $item = publicCalendarTimedItem(
                'family-event-' . (int)$row['registration_id'] . '-session-' . (int)$row['session_id'],
                (string)$row['starts_at'], (string)$row['ends_at'],
                (string)$row['name'] . ' – ' . trim((string)$row['jmeno'] . ' ' . (string)$row['prijmeni']),
                (string)$row['location'], 'Stav přihlášky: ' . $status, 'Rodinná akce'
            );
            if ($item !== null) $items[] = $item;
        }
    }

    if (publicCalendarHasTables($pdo, ['verejne_rezervace', 'individualni_lekce', 'sportovist', 'sportovci'])) {
        $statement = $pdo->prepare(
            'SELECT r.id AS reservation_id,r.stav,r.sportovec_id,il.datum,il.cas_od,il.cas_do,il.nazev,'
            . 's.nazev AS sportoviste,sp.jmeno,sp.prijmeni FROM verejne_rezervace r '
            . 'JOIN individualni_lekce il ON il.id=r.lekce_id JOIN sportovist s ON s.id=il.sportoviste_id '
            . 'JOIN sportovci sp ON sp.id=r.sportovec_id WHERE r.sportovec_id IN (' . $placeholders . ') '
            . "AND r.stav IN ('ceka','potvrzena') AND il.stav='aktivni' AND il.datum BETWEEN ? AND ? "
            . 'ORDER BY il.datum,il.cas_od,r.id'
        );
        $statement->execute([...$personIds, $from, $to]);
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $item = publicCalendarTimedItem(
                'family-reservation-' . (int)$row['reservation_id'],
                (string)$row['datum'] . ' ' . (string)$row['cas_od'],
                (string)$row['datum'] . ' ' . (string)$row['cas_do'],
                (string)$row['nazev'] . ' – ' . trim((string)$row['jmeno'] . ' ' . (string)$row['prijmeni']),
                (string)$row['sportoviste'], 'Stav rezervace: ' . ((string)$row['stav'] === 'potvrzena' ? 'potvrzená' : 'čeká na potvrzení'),
                'Rodinná rezervace'
            );
            if ($item !== null) $items[] = $item;
        }
    }

    if (publicCalendarHasTables($pdo, ['club_member_charges', 'sportovci'])) {
        $statement = $pdo->prepare(
            'SELECT c.id,c.due_on,c.title_snapshot,c.amount_minor,c.currency,c.sportovec_id,sp.jmeno,sp.prijmeni '
            . 'FROM club_member_charges c JOIN sportovci sp ON sp.id=c.sportovec_id '
            . 'WHERE c.sportovec_id IN (' . $placeholders . ") AND c.status='pending' AND c.due_on BETWEEN ? AND ? "
            . 'ORDER BY c.due_on,c.id'
        );
        $statement->execute([...$personIds, $from, $to]);
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $date = publicCalendarDate((string)$row['due_on']);
            if ($date === null) continue;
            $items[] = [
                'uid' => 'family-charge-' . (int)$row['id'], 'start' => $date->format('Y-m-d'),
                'end' => $date->modify('+1 day')->format('Y-m-d'), 'all_day' => true,
                'summary' => 'Splatnost: ' . (string)$row['title_snapshot'] . ' – ' . trim((string)$row['jmeno'] . ' ' . (string)$row['prijmeni']),
                'location' => '', 'description' => number_format(((int)$row['amount_minor']) / 100, 2, ',', ' ') . ' ' . (string)$row['currency'],
                'category' => 'Členský předpis',
            ];
        }
    }

    usort($items, static fn (array $a, array $b): int => [$a['start'], $a['uid']] <=> [$b['start'], $b['uid']]);
    return $items;
}

/** @param array<string,mixed> $row @return array{uid:string,start:string,end:string,all_day:bool,summary:string,location:string,description:string,category:string}|null */
function familyCalendarTrainingItem(array $row): ?array
{
    $date = publicCalendarDate((string)$row['datum']);
    if ($date === null) return null;
    $personName = trim((string)$row['jmeno'] . ' ' . (string)$row['prijmeni']);
    $summary = (string)$row['nazev'] . ' – ' . $personName;
    $description = trim((string)($row['team_name_snapshot'] ?? $row['skupina'] ?? ''));
    $startTime = trim((string)($row['cas_od'] ?? ''));
    if ($startTime === '') {
        return ['uid' => 'family-training-' . (int)$row['id'] . '-person-' . (int)$row['sportovec_id'],
            'start' => $date->format('Y-m-d'), 'end' => $date->modify('+1 day')->format('Y-m-d'), 'all_day' => true,
            'summary' => $summary, 'location' => (string)($row['sportoviste'] ?? ''),
            'description' => $description, 'category' => 'Rodinný trénink'];
    }
    $start = $date->format('Y-m-d') . ' ' . $startTime;
    $startAt = publicCalendarLocalDateTime($start);
    if ($startAt === null) return null;
    $endTime = trim((string)($row['cas_do'] ?? ''));
    $end = $endTime === '' ? $startAt->modify('+1 hour')->format('Y-m-d H:i:s') : $date->format('Y-m-d') . ' ' . $endTime;
    return publicCalendarTimedItem(
        'family-training-' . (int)$row['id'] . '-person-' . (int)$row['sportovec_id'],
        $start, $end, $summary, (string)($row['sportoviste'] ?? ''), $description, 'Rodinný trénink'
    );
}

function familyCalendarRegistrationStatus(string $status): string
{
    return ['confirmed' => 'potvrzeno', 'waitlisted' => 'čeká na místo', 'payment_pending' => 'čeká na úhradu'][$status] ?? $status;
}

function familyCalendarFeedUrl(string $token): string
{
    $base = defined('JE_LOKALNE') && JE_LOKALNE === true
        ? 'http://localhost/evidencePavel/booking/rodinny_kalendar.php'
        : 'https://data.kovopraha.cz/evidence/booking/rodinny_kalendar.php';
    return $base . '?token=' . rawurlencode($token);
}

function familyCalendarFeedAudit(PDO $pdo, int $feedId, int $accountId, string $action, string $hint, string $note): void
{
    $pdo->prepare(
        'INSERT INTO family_calendar_feed_events(feed_id,actor_account_id,action,token_hint_snapshot,note) VALUES(?,?,?,?,?)'
    )->execute([$feedId, $accountId, $action, $hint, $note]);
}
