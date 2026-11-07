<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Tests\Support\ExternalSyncRegistrarFixture as Fixture;
use Tests\Support\ExternalSyncRegistrarSandbox;

final class ExternalSyncRegistrarHttpTest extends TestCase
{
    public function testRegistrarPageIsReadOnlyAndRejectsOtherContexts(): void
    {
        $sandbox = new ExternalSyncRegistrarSandbox();
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        self::assertIsResource($socket);
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $server = proc_open(
            [PHP_BINARY, '-S', $address, '-t', $sandbox->directory],
            [0 => ['pipe', 'r'], 1 => ['file', $sandbox->directory . '/server.log', 'a'], 2 => ['file', $sandbox->directory . '/server.log', 'a']],
            $pipes
        );
        self::assertIsResource($server);
        fclose($pipes[0]);
        try {
            for ($attempt = 0; $attempt < 100; $attempt++) {
                $probe = @stream_socket_client('tcp://' . $address, $errno, $message, 0.1);
                if (is_resource($probe)) {
                    fclose($probe);
                    break;
                }
                usleep(20000);
            }
            $request = static function (string $role, string $query = '', string $method = 'GET') use ($address): array {
                $curl = curl_init('http://' . $address . '/external_sync_admin.php?' . $query);
                curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method,
                    CURLOPT_HTTPHEADER => ['X-Synthetic-Role: ' . $role], CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 20]);
                $raw = (string)curl_exec($curl);
                $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
                $size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
                curl_close($curl);
                return [$status, substr($raw, $size), substr($raw, 0, $size)];
            };
            $before = Fixture::state($sandbox->pdo);
            foreach (['anonymous', 'family', 'coach', 'nonregistrar', 'inactive'] as $role) {
                foreach (['GET', 'POST'] as $method) {
                    [$status, $body] = $request($role, 'connection=TEST-csc', $method);
                    self::assertSame(403, $status, $role . ' ' . $method . ': ' . $body);
                    self::assertStringNotContainsString('TEST Syntetický', $body);
                }
            }
            [$status, $body, $headers] = $request('registrar', 'connection=TEST-csc&filter=all');
            self::assertSame(200, $status, $body);
            self::assertStringContainsString('Cache-Control: no-store, private', $headers);
            self::assertStringContainsString('Referrer-Policy: no-referrer', $headers);
            self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt; TEST', $body);
            self::assertStringNotContainsString('<script>alert(1)</script>', $body);
            self::assertStringContainsString('Karta osoby #1', $body);
            self::assertSame(50, substr_count($body, 'sync-record'));
            self::assertSame(200, $request('registrar', 'connection=TEST-csc&page=2')[0]);
            self::assertSame(405, $request('registrar', '', 'POST')[0]);
            self::assertSame(400, $request('registrar', 'page[]=1')[0]);
            self::assertSame(404, $request('registrar', 'connection=missing')[0]);
            self::assertSame($before, Fixture::state($sandbox->pdo));
            $sandbox->pdo->exec('DELETE FROM staff_user_positions WHERE trainer_id=7');
            self::assertSame(403, $request('registrar')[0]);
            $sandbox->pdo->exec("INSERT INTO staff_user_positions VALUES(7,'registrar'); DROP TABLE external_sync_records");
            self::assertSame(503, $request('registrar', 'connection=TEST-csc')[0]);
        } finally {
            proc_terminate($server);
            proc_close($server);
            $sandbox->cleanup();
        }
    }
}
