<?php
declare(strict_types=1);

require_once __DIR__ . '/ui_format.php';

function calendarUiMonth(string $value): DateTimeImmutable
{
    if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $value) !== 1) {
        $value = date('Y-m');
    }

    return new DateTimeImmutable($value . '-01');
}

/** @return list<list<DateTimeImmutable>> */
function calendarUiWeeks(DateTimeImmutable $month): array
{
    $cursor = $month->modify('monday this week');
    if ((int)$month->format('N') === 1) {
        $cursor = $month;
    }
    $last = $month->modify('last day of this month');
    $weeks = [];

    do {
        $week = [];
        for ($day = 0; $day < 7; $day++) {
            $week[] = $cursor;
            $cursor = $cursor->modify('+1 day');
        }
        $weeks[] = $week;
    } while ($cursor <= $last || (int)$cursor->format('N') !== 1);

    return $weeks;
}

/**
 * @param list<array{date:string,title:string,time?:string,meta?:string,href?:string,tone?:string,popover?:string}> $items
 */
function calendarUiRender(DateTimeImmutable $month, array $items, string $emptyText = 'Pro tento den není nic naplánováno.'): string
{
    $byDate = [];
    foreach ($items as $item) {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/D', (string)($item['date'] ?? '')) === 1) {
            $byDate[(string)$item['date']][] = $item;
        }
    }

    $today = date('Y-m-d');
    $html = '<div class="app-calendar-wrap"><div class="app-calendar" role="grid" aria-label="' . uiFormatH('Kalendář ' . uiFormatMonthName($month)) . '">';
    foreach (['Po', 'Út', 'St', 'Čt', 'Pá', 'So', 'Ne'] as $dayName) {
        $html .= '<div class="app-calendar-weekday" role="columnheader">' . uiFormatH($dayName) . '</div>';
    }

    foreach (calendarUiWeeks($month) as $week) {
        foreach ($week as $date) {
            $key = $date->format('Y-m-d');
            $outside = $date->format('Y-m') !== $month->format('Y-m');
            $classes = 'app-calendar-day' . ($outside ? ' is-outside' : '') . ($key === $today ? ' is-today' : '');
            $label = uiFormatDayName($date) . ' ' . uiFormatDate($key);
            $html .= '<section class="' . $classes . '" role="gridcell" aria-label="' . uiFormatH($label) . '">';
            $html .= '<div class="app-calendar-date"><span>' . $date->format('j') . '</span>' . ($key === $today ? '<small>dnes</small>' : '') . '</div>';
            foreach ($byDate[$key] ?? [] as $item) {
                $title = (string)$item['title'];
                $time = trim((string)($item['time'] ?? ''));
                $meta = trim((string)($item['meta'] ?? ''));
                $popover = trim((string)($item['popover'] ?? $meta));
                $tone = preg_replace('/[^a-z0-9_-]/', '', (string)($item['tone'] ?? 'primary')) ?: 'primary';
                $tag = isset($item['href']) && (string)$item['href'] !== '' ? 'a' : 'button';
                $attributes = $tag === 'a'
                    ? ' href="' . uiFormatH((string)$item['href']) . '"'
                    : ' type="button"';
                if ($popover !== '') {
                    $attributes .= ' data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="auto" data-bs-title="' . uiFormatH($title) . '" data-bs-content="' . uiFormatH($popover) . '"';
                }
                $html .= '<' . $tag . ' class="app-calendar-event tone-' . $tone . '"' . $attributes . '>';
                if ($time !== '') {
                    $html .= '<span class="app-calendar-event-time">' . uiFormatH($time) . '</span>';
                }
                $html .= '<span class="app-calendar-event-title">' . uiFormatH($title) . '</span></' . $tag . '>';
            }
            if (!$outside && ($byDate[$key] ?? []) === []) {
                $html .= '<span class="visually-hidden">' . uiFormatH($emptyText) . '</span>';
            }
            $html .= '</section>';
        }
    }

    return $html . '</div></div>';
}
