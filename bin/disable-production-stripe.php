<?php
declare(strict_types=1);

require_once __DIR__ . '/configure-production-stripe.php';

/** Change only the values passed to define('STRIPE_ENABLED', ...). Keep all keys intact. */
function kisStripeDisabledConfig(string $config): string
{
    if (!str_starts_with(ltrim($config), '<?php')) {
        throw new RuntimeException('Production config.php is not a PHP file.');
    }
    $tokens = token_get_all($config, TOKEN_PARSE);
    $offsets = [];
    $position = 0;
    foreach ($tokens as $index => $token) {
        $offsets[$index] = $position;
        $position += strlen(is_array($token) ? $token[1] : $token);
    }
    $replacements = [];
    $count = count($tokens);
    $definitions = 0;
    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];
        if (!is_array($token) || $token[0] !== T_STRING || strtolower($token[1]) !== 'define') {
            continue;
        }
        $j = $i + 1;
        while ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) $j++;
        if (($tokens[$j] ?? null) !== '(') continue;
        $j++;
        while ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) $j++;
        $name = $tokens[$j] ?? null;
        if (!is_array($name) || $name[0] !== T_CONSTANT_ENCAPSED_STRING
            || !in_array($name[1], ["'STRIPE_ENABLED'", '"STRIPE_ENABLED"'], true)) continue;
        $j++;
        while ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) $j++;
        if (($tokens[$j] ?? null) !== ',') throw new RuntimeException('Unexpected Stripe flag definition.');
        $start = $offsets[++$j];
        $depth = 0;
        for (; $j < $count; $j++) {
            if ($tokens[$j] === '(' || $tokens[$j] === '[') $depth++;
            if ($tokens[$j] === ')' || $tokens[$j] === ']') {
                if ($depth === 0) break;
                $depth--;
            }
            if ($tokens[$j] === ',' && $depth === 0) break;
        }
        if ($j >= $count || $offsets[$j] === $start) throw new RuntimeException('Unexpected Stripe flag expression.');
        $replacements[] = [$start, $offsets[$j] - $start, ' false'];
        $definitions++;
    }
    for ($i = $count - 1; $i >= 0; $i--) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_CONST) continue;
        $j = $i + 1;
        while ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) $j++;
        if (is_array($tokens[$j] ?? null) && $tokens[$j][1] === 'STRIPE_ENABLED') {
            throw new RuntimeException('Unsupported const STRIPE_ENABLED definition.');
        }
    }
    foreach (array_reverse($replacements) as [$start, $length, $replacement]) {
        $config = substr_replace($config, $replacement, $start, $length);
    }
    if ($definitions === 0) {
        $opening = '~\A(<\?php(?:\s*declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;)?)~';
        $config = preg_replace($opening, "$1\n\ndefined('STRIPE_ENABLED') || define('STRIPE_ENABLED', false);", $config, 1, $inserted);
        if (!is_string($config) || $inserted !== 1) throw new RuntimeException('Stripe flag could not be inserted.');
    }
    token_get_all($config, TOKEN_PARSE);
    return $config;
}

function kisStripeDisableMain(): void
{
    if (PHP_SAPI !== 'cli' || getenv('DISABLE_PRODUCTION_STRIPE') !== '1') {
        throw new RuntimeException('CLI and DISABLE_PRODUCTION_STRIPE=1 are required.');
    }
    $action = (string)getenv('STRIPE_DISABLE_ACTION');
    $host = strtolower(trim((string)getenv('APP_HOST')));
    $root = rtrim(trim((string)getenv('APP_ROOT')), '/\\');
    if (!in_array($action, ['preview', 'disable', 'probe'], true)
        || $host !== 'kis.kovopraha.cz' || $root === '' || !is_file($root . '/config.php')) {
        throw new RuntimeException('Unexpected action or production target.');
    }
    if ($action === 'probe') {
        $probe = kisProductionStripeProbe($root, $host);
        echo json_encode(['ok' => $probe['ok'], 'environment' => $probe['environment'],
            'stripe_enabled' => $probe['stripe_enabled'],
            'stripe_test_pair' => $probe['stripe_test_pair'],
            'stripe_live_pair' => $probe['stripe_live_pair'],
            'webhook_secret' => $probe['webhook_secret'],
            'base_url_ok' => $probe['base_url_ok']], JSON_THROW_ON_ERROR) . PHP_EOL;
        return;
    }
    $path = $root . '/config.php';
    $old = file_get_contents($path);
    if (!is_string($old)) throw new RuntimeException('Production config could not be read.');
    $new = kisStripeDisabledConfig($old);
    $oldHash = hash('sha256', $old);
    $newHash = hash('sha256', $new);
    if ($action === 'preview') {
        echo json_encode(['ok' => true, 'changed' => $oldHash !== $newHash,
            'current_sha256' => $oldHash, 'planned_sha256' => $newHash], JSON_THROW_ON_ERROR) . PHP_EOL;
        return;
    }
    if (getenv('STRIPE_DISABLE_CONFIRM') !== 'VYPNOUT-STRIPE'
        || !hash_equals($oldHash, (string)getenv('EXPECTED_CONFIG_SHA256'))) {
        throw new RuntimeException('Confirmation or preview hash mismatch.');
    }
    $backupDir = rtrim(trim((string)getenv('CONFIG_BACKUP_DIR')), '/\\');
    if ($backupDir === '') throw new RuntimeException('Backup directory is required.');
    if ($oldHash !== $newHash) {
        if (!is_dir($backupDir) && !mkdir($backupDir, 0700, true) && !is_dir($backupDir)) {
            throw new RuntimeException('Backup directory could not be created.');
        }
        $backup = $backupDir . '/config-stripe-disable-' . gmdate('Ymd-His') . '-'
            . substr($oldHash, 0, 12) . '-' . bin2hex(random_bytes(4)) . '.php';
        if (file_put_contents($backup, $old, LOCK_EX) === false) throw new RuntimeException('Config backup failed.');
        chmod($backup, 0600);
        $temporary = $path . '.stripe-disable-' . bin2hex(random_bytes(6)) . '.tmp';
        if (file_put_contents($temporary, $new, LOCK_EX) === false) throw new RuntimeException('Temporary config write failed.');
        chmod($temporary, 0600);
        if (!hash_equals($oldHash, (string)hash_file('sha256', $path))) {
            @unlink($temporary);
            throw new RuntimeException('Production config changed after preview.');
        }
        if (!rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('Atomic config activation failed.');
        }
        chmod($path, 0600);
    }
    echo json_encode(['ok' => true, 'changed' => $oldHash !== $newHash,
        'mode' => 'disabled', 'sha256' => $newHash], JSON_THROW_ON_ERROR) . PHP_EOL;
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    try {
        kisStripeDisableMain();
    } catch (Throwable $exception) {
        fwrite(STDERR, 'Stripe disable failed: ' . $exception->getMessage() . PHP_EOL);
        exit(1);
    }
}
