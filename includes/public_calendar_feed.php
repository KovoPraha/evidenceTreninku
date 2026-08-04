<?php
declare(strict_types=1);

function publicCalendarTableExists(PDO $pdo, string $table): bool
{
    if (preg_match('/^[a-z0-9_]+$/D', $table) !== 1) return false;
    $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'mysql') {
        $statement = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    if ($driver === 'sqlite') {
        $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    return false;
}

/** @return list<array{uid:string,start:string,end:string,all_day:bool,summary:string,location:string,description:string,category:string}> */
function publicCalendarItems(PDO $pdo, string $from, string $to): array
{
    $fromDate = publicCalendarDate($from);
    $toDate = publicCalendarDate($to);
    if ($fromDate === null || $toDate === null || $toDate < $fromDate
        || $toDate->diff($fromDate)->days > 370
    ) {
        throw new InvalidArgumentException('Neplatné období veřejného kalendáře. Maximum je 371 dní.');
    }
    $items = [];
    if (publicCalendarHasTables($pdo, ['planovane_treninky', 'sportovist', 'skupiny'])) {
        $statement = $pdo->prepare(
            'SELECT p.id,p.datum,p.cas_od,p.cas_do,p.nazev,p.kategorie,'
            . 's.nazev AS sportoviste,g.nazev AS skupina FROM planovane_treninky p '
            . 'LEFT JOIN sportovist s ON s.id=p.sportoviste_id LEFT JOIN skupiny g ON g.id=p.skupina_id '
            . "WHERE p.je_verejny=1 AND p.stav='planovany' AND p.datum BETWEEN ? AND ? "
            . 'ORDER BY p.datum,p.cas_od,p.id'
        );
        $statement->execute([$from, $to]);
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $item = publicCalendarTrainingItem($row);
            if ($item !== null) $items[] = $item;
        }
    }
    if (publicCalendarHasTables($pdo, ['club_events', 'club_event_sessions'])) {
        $statement = $pdo->prepare(
            'SELECT s.id,s.starts_at,s.ends_at,s.location,e.name,e.audience_label,e.event_type '
            . 'FROM club_event_sessions s JOIN club_events e ON e.id=s.event_id '
            . "WHERE e.status='open' AND s.status='scheduled' AND s.starts_at<? AND s.ends_at>=? "
            . 'ORDER BY s.starts_at,s.id'
        );
        $statement->execute([$toDate->modify('+1 day')->format('Y-m-d 00:00:00'), $from . ' 00:00:00']);
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $item = publicCalendarTimedItem(
                'club-event-session-' . (int)$row['id'],
                (string)$row['starts_at'],
                (string)$row['ends_at'],
                (string)$row['name'],
                (string)($row['location'] ?? ''),
                (string)($row['audience_label'] ?? ''),
                'Klubová akce'
            );
            if ($item !== null) $items[] = $item;
        }
    }
    if (publicCalendarHasTables($pdo, ['individualni_lekce', 'sportovist'])) {
        $statement = $pdo->prepare(
            'SELECT il.id,il.datum,il.cas_od,il.cas_do,il.nazev,s.nazev AS sportoviste '
            . 'FROM individualni_lekce il JOIN sportovist s ON s.id=il.sportoviste_id '
            . "WHERE s.kod='velodrom' AND s.je_verejne=1 AND s.aktivni=1 AND il.stav='aktivni' "
            . 'AND il.datum BETWEEN ? AND ? ORDER BY il.datum,il.cas_od,il.id'
        );
        $statement->execute([$from, $to]);
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $item = publicCalendarTimedItem(
                'velodrome-slot-' . (int)$row['id'],
                (string)$row['datum'] . ' ' . (string)$row['cas_od'],
                (string)$row['datum'] . ' ' . (string)$row['cas_do'],
                trim((string)$row['nazev']) !== '' ? (string)$row['nazev'] : 'Veřejná hodina velodromu',
                (string)($row['sportoviste'] ?? 'Velodrom'),
                'Veřejný rezervační termín.',
                'Velodrom'
            );
            if ($item !== null) $items[] = $item;
        }
    }
    usort($items, static fn (array $a, array $b): int => [$a['start'], $a['uid']] <=> [$b['start'], $b['uid']]);
    return $items;
}

/** @param list<array{uid:string,start:string,end:string,all_day:bool,summary:string,location:string,description:string,category:string}> $items */
function publicCalendarRender(
    array $items,
    ?DateTimeImmutable $generatedAt = null,
    string $calendarName = 'Kovopraha – veřejný program'
): string
{
    $generatedAt ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//TJ Kovo Praha//Verejny kalendar//CS',
        'CALSCALE:GREGORIAN', 'METHOD:PUBLISH', 'X-WR-CALNAME:' . publicCalendarEscape($calendarName),
        'X-WR-TIMEZONE:Europe/Prague'];
    foreach ($items as $item) {
        $lines[] = 'BEGIN:VEVENT';
        $lines[] = 'UID:' . publicCalendarEscape($item['uid'] . '@data.kovopraha.cz');
        $lines[] = 'DTSTAMP:' . $generatedAt->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
        if ($item['all_day']) {
            $lines[] = 'DTSTART;VALUE=DATE:' . str_replace('-', '', $item['start']);
            $lines[] = 'DTEND;VALUE=DATE:' . str_replace('-', '', $item['end']);
        } else {
            $lines[] = 'DTSTART:' . publicCalendarUtc($item['start']);
            $lines[] = 'DTEND:' . publicCalendarUtc($item['end']);
        }
        $lines[] = 'SUMMARY:' . publicCalendarEscape($item['summary']);
        if ($item['location'] !== '') $lines[] = 'LOCATION:' . publicCalendarEscape($item['location']);
        if ($item['description'] !== '') $lines[] = 'DESCRIPTION:' . publicCalendarEscape($item['description']);
        $lines[] = 'CATEGORIES:' . publicCalendarEscape($item['category']);
        $lines[] = 'TRANSP:OPAQUE';
        $lines[] = 'END:VEVENT';
    }
    $lines[] = 'END:VCALENDAR';
    $folded = [];
    foreach ($lines as $line) array_push($folded, ...publicCalendarFoldLine($line));
    return implode("\r\n", $folded) . "\r\n";
}

/** @param array<string,mixed> $row @return array{uid:string,start:string,end:string,all_day:bool,summary:string,location:string,description:string,category:string}|null */
function publicCalendarTrainingItem(array $row): ?array
{
    $date = publicCalendarDate((string)($row['datum'] ?? ''));
    if ($date === null) return null;
    $startTime = trim((string)($row['cas_od'] ?? ''));
    $endTime = trim((string)($row['cas_do'] ?? ''));
    $description = trim(implode(' · ', array_filter([(string)($row['skupina'] ?? ''), (string)($row['kategorie'] ?? '')])));
    if ($startTime === '') {
        return ['uid' => 'training-' . (int)$row['id'], 'start' => $date->format('Y-m-d'),
            'end' => $date->modify('+1 day')->format('Y-m-d'), 'all_day' => true,
            'summary' => (string)$row['nazev'], 'location' => (string)($row['sportoviste'] ?? ''),
            'description' => $description, 'category' => 'Trénink'];
    }
    $start = $date->format('Y-m-d') . ' ' . $startTime;
    $parsedStart = publicCalendarLocalDateTime($start);
    if ($parsedStart === null) return null;
    $end = $endTime !== '' ? $date->format('Y-m-d') . ' ' . $endTime : $parsedStart->modify('+1 hour')->format('Y-m-d H:i:s');
    return publicCalendarTimedItem('training-' . (int)$row['id'], $start, $end, (string)$row['nazev'],
        (string)($row['sportoviste'] ?? ''), $description, 'Trénink');
}

/** @return array{uid:string,start:string,end:string,all_day:bool,summary:string,location:string,description:string,category:string}|null */
function publicCalendarTimedItem(string $uid, string $start, string $end, string $summary, string $location, string $description, string $category): ?array
{
    $startAt = publicCalendarLocalDateTime($start);
    $endAt = publicCalendarLocalDateTime($end);
    if ($startAt === null || $endAt === null || $endAt <= $startAt || trim($summary) === '') return null;
    return ['uid' => $uid, 'start' => $startAt->format('Y-m-d H:i:s'), 'end' => $endAt->format('Y-m-d H:i:s'),
        'all_day' => false, 'summary' => trim($summary), 'location' => trim($location),
        'description' => trim($description), 'category' => $category];
}

function publicCalendarDate(string $value): ?DateTimeImmutable
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('Europe/Prague'));
    return $date && $date->format('Y-m-d') === $value ? $date : null;
}

function publicCalendarLocalDateTime(string $value): ?DateTimeImmutable
{
    foreach (['!Y-m-d H:i:s', '!Y-m-d H:i'] as $format) {
        $date = DateTimeImmutable::createFromFormat($format, trim($value), new DateTimeZone('Europe/Prague'));
        if ($date !== false && $date->format(str_contains($format, ':s') ? 'Y-m-d H:i:s' : 'Y-m-d H:i') === trim($value)) return $date;
    }
    return null;
}

function publicCalendarUtc(string $value): string
{
    $date = publicCalendarLocalDateTime($value);
    if ($date === null) throw new InvalidArgumentException('Neplatný termín kalendáře.');
    return $date->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
}

function publicCalendarEscape(string $value): string
{
    $value = str_replace(["\r\n", "\r"], "\n", $value);
    return str_replace(['\\', ';', ',', "\n"], ['\\\\', '\\;', '\\,', '\\n'], $value);
}

/** @return list<string> */
function publicCalendarFoldLine(string $line): array
{
    $result = [];
    $prefix = '';
    while (strlen($line) > ($prefix === '' ? 75 : 74)) {
        $length = $prefix === '' ? 75 : 74;
        $chunk = mb_strcut($line, 0, $length, 'UTF-8');
        $result[] = $prefix . $chunk;
        $line = substr($line, strlen($chunk));
        $prefix = ' ';
    }
    $result[] = $prefix . $line;
    return $result;
}

/** @param list<string> $tables */
function publicCalendarHasTables(PDO $pdo, array $tables): bool
{
    foreach ($tables as $table) if (!publicCalendarTableExists($pdo, $table)) return false;
    return true;
}
