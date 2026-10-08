<?php
declare(strict_types=1);

final class WebhookRequestTooLargeException extends RuntimeException
{
}

function webhookReadBoundedBody(string $streamPath = 'php://input', int $maximumBytes = 262144, ?int $declaredBytes = null): string
{
    if ($maximumBytes < 1 || ($declaredBytes !== null && $declaredBytes > $maximumBytes)) {
        throw new WebhookRequestTooLargeException('Tělo webhooku je příliš velké.');
    }
    $stream = fopen($streamPath, 'rb');
    if (!is_resource($stream)) throw new RuntimeException('Tělo webhooku nelze načíst.');
    try {
        $payload = stream_get_contents($stream, $maximumBytes + 1);
    } finally {
        fclose($stream);
    }
    if (!is_string($payload)) throw new RuntimeException('Tělo webhooku nelze načíst.');
    if (strlen($payload) > $maximumBytes) throw new WebhookRequestTooLargeException('Tělo webhooku je příliš velké.');
    return $payload;
}
