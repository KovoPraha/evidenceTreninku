<?php

declare(strict_types=1);

/** UTC expressions never alter the shared KIS connection's session time zone. */
function barSqlUtcNow(PDO $pdo): string
{
    return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? 'UTC_TIMESTAMP()' : 'CURRENT_TIMESTAMP';
}

function barBusinessToday(): DateTimeImmutable
{
    return new DateTimeImmutable('now', new DateTimeZone('Europe/Prague'));
}

function barRequireUtcClock(PDO $pdo): void
{
    if ($pdo->query('SELECT clock_contract FROM kis_bar_state WHERE id=1')->fetchColumn() !== 'utc-v1') {
        throw new DomainException('Bar obsahuje historii ze starého časového režimu. Před novým provozem je nutný řízený nový začátek.');
    }
}

function barDisplayTime(?string $utc): string
{
    if ($utc === null || $utc === '') {
        return '';
    }
    $time = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $utc, new DateTimeZone('UTC'));
    return $time ? $time->setTimezone(new DateTimeZone('Europe/Prague'))->format('d. m. Y H:i:s T') : $utc;
}

/** Local calendar days may have 23 or 25 hours. SQL stores and compares UTC. */
function barUtcPeriod(string $from, string $to): array
{
    $zone = new DateTimeZone('Europe/Prague');
    $first = DateTimeImmutable::createFromFormat('!Y-m-d', $from, $zone);
    $last = DateTimeImmutable::createFromFormat('!Y-m-d', $to, $zone);
    if (!$first || !$last || $first->format('Y-m-d') !== $from || $last->format('Y-m-d') !== $to
        || $last < $first || $first->diff($last)->days > 366) {
        throw new InvalidArgumentException('Vyberte platné období nejvýše 367 dní.');
    }
    $utc = new DateTimeZone('UTC');
    return [$first->setTimezone($utc)->format('Y-m-d H:i:s'), $last->modify('+1 day')->setTimezone($utc)->format('Y-m-d H:i:s')];
}
