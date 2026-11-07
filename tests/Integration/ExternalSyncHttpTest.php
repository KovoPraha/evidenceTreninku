<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

final class ExternalSyncHttpTest extends TestCase
{
    public function testMachineEndpointRejectsCookiesAndDefaultsOffWithoutDatabase(): void
    {
        $root = dirname(__DIR__, 2);
        $sandbox = sys_get_temp_dir() . '/evidence-external-http-' . bin2hex(random_bytes(8));
        mkdir($sandbox . '/includes', 0700, true);
        $files = ['api_external_sync.php', 'includes/external_sync_ingest.php', 'includes/external_sync.php',
            'includes/external_sync_contract.php', 'includes/external_identity_match.php'];
        foreach ($files as $file) {
            copy($root . '/' . $file, $sandbox . '/' . $file);
        }
        // No application config, credentials, sessions or database copied.
        file_put_contents($sandbox . '/config.php', '<?php');
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        self::assertIsResource($socket);
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $server = proc_open(
            [PHP_BINARY, '-S', $address, '-t', $sandbox],
            [0 => ['pipe', 'r'], 1 => ['file', $sandbox . '/server.log', 'a'], 2 => ['file', $sandbox . '/server.log', 'a']],
            $pipes
        );
        self::assertIsResource($server);
        fclose($pipes[0]);
        try {
            $ready = false;
            for ($attempt = 0; $attempt < 100; $attempt++) {
                $probe = @stream_socket_client('tcp://' . $address, $errno, $message, 0.1);
                if (is_resource($probe)) {
                    fclose($probe);
                    $ready = true;
                    break;
                }
                usleep(20000);
            }
            self::assertTrue($ready);
            $request = static function (array $headers, string $method = 'POST') use ($address): array {
                $curl = curl_init('http://' . $address . '/api_external_sync.php?action=claim');
                curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method,
                    CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json'], $headers),
                    CURLOPT_POSTFIELDS => '{}', CURLOPT_TIMEOUT => 5]);
                $body = curl_exec($curl);
                $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
                curl_close($curl);
                return [$status, json_decode((string)$body, true)];
            };
            self::assertSame([401, ['error' => 'unauthorized']], $request([], 'GET'));
            self::assertSame([401, ['error' => 'unauthorized']], $request([]));
            self::assertSame([401, ['error' => 'unauthorized']], $request(['Cookie: PHPSESSID=SYNTHETIC-REGISTRAR']));
            self::assertSame([401, ['error' => 'unauthorized']], $request(['Authorization: Bearer invalid']));
            $token = 'Authorization: Bearer ' . str_repeat('a', 64);
            self::assertSame([503, ['error' => 'integration_disabled']], $request([$token]));
            file_put_contents($sandbox . '/config.php', "<?php define('KIS_EXTERNAL_SYNC_ENABLED', false);");
            self::assertSame([503, ['error' => 'integration_disabled']], $request([$token]));
            file_put_contents($sandbox . '/config.php', "<?php define('KIS_EXTERNAL_SYNC_ENABLED', true);");
            self::assertSame([403, ['error' => 'https_required']], $request([$token, 'X-Forwarded-Proto: https']));
        } finally {
            proc_terminate($server);
            proc_close($server);
            foreach (array_merge($files, ['config.php', 'server.log']) as $file) {
                if (is_file($sandbox . '/' . $file)) {
                    unlink($sandbox . '/' . $file);
                }
            }
            rmdir($sandbox . '/includes');
            rmdir($sandbox);
        }
    }
}
