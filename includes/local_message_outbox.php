<?php
declare(strict_types=1);

/**
 * Localhost-only plain-text message sink shared by notification features.
 * It deliberately stores the original recipient for inspection but never
 * opens a network connection.
 *
 * @return callable(string,string,string):bool
 */
function localMessageOutboxSender(string $appHost, string $schema, string $directory): callable
{
    $host = strtolower((string)preg_replace('/:\d+$/D', '', trim($appHost)));
    if (!in_array($host, ['localhost', '127.0.0.1'], true)) {
        throw new RuntimeException('Lokální testovací outbox je povolen pouze na localhostu.');
    }
    if (preg_match('/^[a-z0-9][a-z0-9.-]{2,80}$/D', $schema) !== 1) {
        throw new InvalidArgumentException('Neplatné schéma lokálního outboxu.');
    }
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Adresář testovacího outboxu nelze vytvořit.');
    }
    $resolved = realpath($directory);
    if ($resolved === false || !is_writable($resolved)) {
        throw new RuntimeException('Adresář testovacího outboxu není zapisovatelný.');
    }

    return static function (string $recipient, string $subject, string $body) use ($resolved, $schema): bool {
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $recipient . $subject) === 1) {
            throw new InvalidArgumentException('Zpráva obsahuje neplatnou e-mailovou hlavičku.');
        }
        $payload = json_encode([
            'schema' => $schema,
            'captured_at' => (new DateTimeImmutable())->format(DATE_ATOM),
            'original_recipient' => $recipient,
            'subject' => $subject,
            'body' => $body,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $path = $resolved . DIRECTORY_SEPARATOR . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.json';
        if (file_put_contents($path, $payload . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('Testovací zprávu nelze uložit do outboxu.');
        }
        @chmod($path, 0600);
        return true;
    };
}
