<?php
declare(strict_types=1);

function uiFormatH(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function uiFormatDateValue(string $value): ?DateTimeImmutable
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    try {
        return new DateTimeImmutable($value);
    } catch (Throwable) {
        return null;
    }
}

function uiFormatDate(string $value, bool $withYear = true): string
{
    $date = uiFormatDateValue($value);
    if ($date === null) {
        return $value;
    }

    return $date->format($withYear ? 'j. n. Y' : 'j. n.');
}

function uiFormatDateTime(string $value): string
{
    $date = uiFormatDateValue($value);
    return $date === null ? $value : $date->format('j. n. Y H:i');
}

function uiFormatTime(string $value): string
{
    $date = uiFormatDateValue($value);
    if ($date !== null && str_contains($value, '-')) {
        return $date->format('H:i');
    }

    return substr($value, 0, 5);
}

function uiFormatDateTimeRange(string $start, string $end): string
{
    $startDate = uiFormatDateValue($start);
    $endDate = uiFormatDateValue($end);
    if ($startDate === null || $endDate === null) {
        return trim($start . '–' . $end, '–');
    }

    if ($startDate->format('Y-m-d') === $endDate->format('Y-m-d')) {
        return $startDate->format('j. n. Y H:i') . '–' . $endDate->format('H:i');
    }

    return $startDate->format('j. n. Y H:i') . ' – ' . $endDate->format('j. n. Y H:i');
}

function uiFormatMonthName(DateTimeInterface $date): string
{
    $months = [
        1 => 'leden', 2 => 'únor', 3 => 'březen', 4 => 'duben',
        5 => 'květen', 6 => 'červen', 7 => 'červenec', 8 => 'srpen',
        9 => 'září', 10 => 'říjen', 11 => 'listopad', 12 => 'prosinec',
    ];

    return ($months[(int)$date->format('n')] ?? $date->format('m')) . ' ' . $date->format('Y');
}

function uiFormatDayName(DateTimeInterface $date): string
{
    $days = [1 => 'Pondělí', 2 => 'Úterý', 3 => 'Středa', 4 => 'Čtvrtek', 5 => 'Pátek', 6 => 'Sobota', 7 => 'Neděle'];
    return $days[(int)$date->format('N')] ?? '';
}
