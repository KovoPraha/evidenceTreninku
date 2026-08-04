<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/includes/public_calendar_feed.php';

$today = new DateTimeImmutable('today', new DateTimeZone('Europe/Prague'));
$from = (string)($_GET['from'] ?? $today->modify('-7 days')->format('Y-m-d'));
$to = (string)($_GET['to'] ?? $today->modify('+360 days')->format('Y-m-d'));
try {
    $calendar = publicCalendarRender(publicCalendarItems($pdo, $from, $to));
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo $exception->getMessage();
    exit;
} catch (Throwable $exception) {
    error_log('Public calendar feed failed: ' . $exception->getMessage());
    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Veřejný kalendář je dočasně nedostupný.';
    exit;
}
header('Content-Type: text/calendar; charset=UTF-8');
header('Content-Disposition: attachment; filename="kovopraha-verejny-kalendar.ics"');
header('Cache-Control: public, max-age=300');
header('X-Content-Type-Options: nosniff');
echo $calendar;
