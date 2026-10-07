<?php
declare(strict_types=1);

/**
 * @param list<string> $arguments
 * @return array{limit:int,transport:string}
 */
function rosterMessageWorkerOptions(
    array $arguments,
    string|false $environmentLimit,
    string|false $environmentTransport
): array {
    $limit = 20;
    $transport = 'mail';

    if (is_string($environmentLimit) && $environmentLimit !== '') {
        if (preg_match('/^(?:[1-9]|[1-9][0-9])$/D', $environmentLimit) !== 1) {
            throw new InvalidArgumentException('ROSTER_MESSAGE_LIMIT musi byt cele cislo od 1 do 99.');
        }
        $limit = (int)$environmentLimit;
    }

    if (is_string($environmentTransport) && $environmentTransport !== '') {
        if (!in_array($environmentTransport, ['mail', 'local-outbox'], true)) {
            throw new InvalidArgumentException('ROSTER_MESSAGE_TRANSPORT musi byt mail nebo local-outbox.');
        }
        $transport = $environmentTransport;
    }

    foreach ($arguments as $argument) {
        if (preg_match('/^--limit=([1-9]|[1-9][0-9])$/D', $argument, $match) === 1) {
            $limit = (int)$match[1];
            continue;
        }
        if (preg_match('/^--transport=(mail|local-outbox)$/D', $argument, $match) === 1) {
            $transport = $match[1];
            continue;
        }
        throw new InvalidArgumentException(
            'Pouziti: APP_HOST=<host> [ROSTER_MESSAGE_LIMIT=20] [ROSTER_MESSAGE_TRANSPORT=mail] '
            . 'php bin/roster-message-worker.php [--limit=20] [--transport=mail|local-outbox]'
        );
    }

    return ['limit' => $limit, 'transport' => $transport];
}
