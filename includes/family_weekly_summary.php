<?php
declare(strict_types=1);

require_once __DIR__ . '/family_calendar_feed.php';

function familyWeeklySummaryPlain(string $value): string
{
    return trim((string)preg_replace('/\s+/u', ' ', $value));
}

function familyWeeklySummaryPortalUrl(): string
{
    return defined('JE_LOKALNE') && JE_LOKALNE === true
        ? 'http://localhost/evidencePavel/booking/sportovni_prehled.php'
        : 'https://data.kovopraha.cz/evidence/booking/sportovni_prehled.php';
}

function familyWeeklySummaryStartDate(string $requested, DateTimeImmutable $today): DateTimeImmutable
{
    $today = $today->setTimezone(new DateTimeZone('Europe/Prague'))->setTime(0, 0);
    if ($requested === '') return $today;
    $date = publicCalendarDate($requested);
    if ($date === null || $date < $today->modify('-90 days') || $date > $today->modify('+370 days')) {
        throw new InvalidArgumentException('Požadovaný týden je mimo povolené období.');
    }
    return $date;
}

/**
 * @return array{
 *   from:string,to:string,period_label:string,subject:string,body:string,
 *   items:list<array<string,mixed>>,counts:array{training:int,event:int,reservation:int,charge:int,other:int,total:int}
 * }
 */
function familyWeeklySummaryPreview(PDO $pdo, int $accountId, string $from): array
{
    $fromDate = publicCalendarDate($from);
    if ($accountId < 1 || $fromDate === null) throw new InvalidArgumentException('Neplatný začátek týdenního souhrnu.');
    $toDate = $fromDate->modify('+6 days');
    $items = familyCalendarAgenda($pdo, $accountId, $from, 7);
    $counts = ['training' => 0, 'event' => 0, 'reservation' => 0, 'charge' => 0, 'other' => 0, 'total' => count($items)];
    $categoryMap = [
        'Rodinný trénink' => 'training',
        'Rodinná akce' => 'event',
        'Rodinná rezervace' => 'reservation',
        'Členský předpis' => 'charge',
    ];
    $lines = [];
    foreach ($items as $item) {
        $bucket = $categoryMap[(string)$item['category']] ?? 'other';
        $counts[$bucket]++;
        $line = '- ' . (string)$item['date_label'] . ' · ' . (string)$item['time_label']
            . ' · ' . familyWeeklySummaryPlain((string)$item['summary']);
        if (trim((string)$item['location']) !== '') $line .= ' · ' . familyWeeklySummaryPlain((string)$item['location']);
        if (trim((string)$item['description']) !== '') $line .= ' (' . familyWeeklySummaryPlain((string)$item['description']) . ')';
        $lines[] = $line;
    }
    $periodLabel = $fromDate->format('d. m.') . '–' . $toDate->format('d. m. Y');
    $body = "Dobrý den,\n\n"
        . 'zde je rodinný program na období ' . $periodLabel . ".\n\n"
        . ($lines === [] ? "V tomto období nemáte evidovanou žádnou položku.\n" : implode("\n", $lines) . "\n")
        . "\nAktuální přehled po přihlášení: " . familyWeeklySummaryPortalUrl()
        . "\n\nKlub KOVO Praha";
    return [
        'from' => $fromDate->format('Y-m-d'),
        'to' => $toDate->format('Y-m-d'),
        'period_label' => $periodLabel,
        'subject' => 'Rodinný program ' . $periodLabel,
        'body' => $body,
        'items' => $items,
        'counts' => $counts,
    ];
}
