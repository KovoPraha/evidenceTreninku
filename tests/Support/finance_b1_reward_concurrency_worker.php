<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__, 2) . '/includes/athlete_reward_guard.php';

// Standalone synthetic test database; deliberately cannot use application config or production credentials.
const B1_REWARD_RACE_DSN = 'mysql:host=127.0.0.1;port=33426;dbname=evidence_b1_rewards;charset=utf8mb4';

final class B1RewardCommitBarrierPdo extends PDO
{
    public function __construct(private string $barrier)
    {
        parent::__construct(B1_REWARD_RACE_DSN, 'b1_app', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }

    public function commit(): bool
    {
        file_put_contents($this->barrier . '.locked', 'transaction mutated; locks still held');
        $deadline = microtime(true) + 15;
        while (!is_file($this->barrier . '.release')) {
            if (microtime(true) > $deadline) throw new RuntimeException('Test barrier timed out.');
            usleep(10000);
        }
        return parent::commit();
    }
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) !== __FILE__) return;

$_SESSION = ['trener_id' => 1, 'role' => 'admin', 'opravneni' => ['kreditni_obdobi' => 'hlavni']];
$operation = (string)($argv[1] ?? '');
$periodId = (int)($argv[2] ?? 0);
$barrier = (string)($argv[3] ?? '');
$hold = ($argv[4] ?? '') === 'hold';
if (!in_array($operation, ['close', 'save', 'bulk'], true) || $periodId <= 0 || !is_dir(dirname($barrier))) exit(2);

$pdo = $hold ? new B1RewardCommitBarrierPdo($barrier)
    : new PDO(B1_REWARD_RACE_DSN, 'b1_app', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
file_put_contents($barrier . ($hold ? '.first-started' : '.second-started'), 'ready');
try {
    $result = match ($operation) {
        'close' => athleteRewardClose($pdo, 1, $periodId, '2026-10-20'),
        'save' => athleteRewardSave($pdo, 1, $periodId, 99, '2026-10-01', null),
        'bulk' => athleteRewardBulkRates($pdo, [1 => 77], '2026-10-22'),
    };
    echo json_encode(['status' => 'ok', 'result' => $result], JSON_THROW_ON_ERROR);
} catch (DomainException $e) {
    echo json_encode(['status' => 'blocked', 'message' => $e->getMessage()], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage());
    exit(1);
}
