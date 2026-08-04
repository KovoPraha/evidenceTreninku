<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/includes/family_calendar_feed.php';

$token = (string)($_GET['token'] ?? '');
$accountId = familyCalendarFeedResolveAccount($pdo, $token);
if ($accountId === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: private, no-store, max-age=0');
    header('Referrer-Policy: no-referrer');
    echo 'Kalendář nebyl nalezen.';
    exit;
}

$today = new DateTimeImmutable('today', new DateTimeZone('Europe/Prague'));
$from = (string)($_GET['from'] ?? $today->modify('-7 days')->format('Y-m-d'));
$to = (string)($_GET['to'] ?? $today->modify('+360 days')->format('Y-m-d'));
try {
    $calendar = publicCalendarRender(
        familyCalendarItems($pdo, $accountId, $from, $to),
        null,
        'Kovopraha – rodinný kalendář'
    );
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: private, no-store, max-age=0');
    header('Referrer-Policy: no-referrer');
    echo $exception->getMessage();
    exit;
} catch (Throwable $exception) {
    error_log('Family calendar feed failed: ' . $exception->getMessage());
    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: private, no-store, max-age=0');
    header('Referrer-Policy: no-referrer');
    echo 'Rodinný kalendář je dočasně nedostupný.';
    exit;
}

header('Content-Type: text/calendar; charset=UTF-8');
header('Content-Disposition: inline; filename="kovopraha-rodinny-kalendar.ics"');
header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow, noarchive');
echo $calendar;
