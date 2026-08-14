<?php
declare(strict_types=1);

const KIS_BANK_BLOCK_BEGIN = '// BEGIN KIS MANAGED BANK ACCOUNT';
const KIS_BANK_BLOCK_END = '// END KIS MANAGED BANK ACCOUNT';

function kisBankIbanChecksumIsValid(string $iban): bool
{
    $compact = strtoupper((string)preg_replace('/\s+/', '', $iban));
    if (preg_match('/^CZ[0-9]{22}$/D', $compact) !== 1) {
        return false;
    }

    $rearranged = substr($compact, 4) . substr($compact, 0, 4);
    $remainder = 0;
    foreach (str_split($rearranged) as $character) {
        $digits = ctype_alpha($character) ? (string)(ord($character) - 55) : $character;
        foreach (str_split($digits) as $digit) {
            $remainder = (($remainder * 10) + (int)$digit) % 97;
        }
    }
    return $remainder === 1;
}

/** @param array<string,mixed> $input @return array{iban:string,bic:string,account_label:string,due_days:int} */
function kisProductionBankValidate(array $input): array
{
    $iban = strtoupper((string)preg_replace('/\s+/', '', trim((string)($input['iban'] ?? ''))));
    $bic = strtoupper(trim((string)($input['bic'] ?? '')));
    $label = trim((string)($input['account_label'] ?? ''));
    $dueDays = filter_var($input['due_days'] ?? null, FILTER_VALIDATE_INT);

    if (!kisBankIbanChecksumIsValid($iban)) {
        throw new RuntimeException('Production bank IBAN is invalid.');
    }
    if ($bic !== '' && preg_match('/^[A-Z0-9]{8}(?:[A-Z0-9]{3})?$/D', $bic) !== 1) {
        throw new RuntimeException('Production bank BIC is invalid.');
    }
    if (mb_strlen($label) < 3 || mb_strlen($label) > 120 || preg_match('/[\x00-\x1F\x7F]/u', $label) === 1) {
        throw new RuntimeException('Production bank account label is invalid.');
    }
    if (!is_int($dueDays) || $dueDays < 1 || $dueDays > 30) {
        throw new RuntimeException('Production bank due days must be between 1 and 30.');
    }

    return ['iban' => $iban, 'bic' => $bic, 'account_label' => $label, 'due_days' => $dueDays];
}

/** @param array{iban:string,bic:string,account_label:string,due_days:int} $settings */
function kisProductionBankManagedBlock(array $settings): string
{
    return KIS_BANK_BLOCK_BEGIN . "\n"
        . "defined('SHOP_BANK_IBAN') || define('SHOP_BANK_IBAN', " . var_export($settings['iban'], true) . ");\n"
        . "defined('SHOP_BANK_BIC') || define('SHOP_BANK_BIC', " . var_export($settings['bic'], true) . ");\n"
        . "defined('SHOP_BANK_ACCOUNT_LABEL') || define('SHOP_BANK_ACCOUNT_LABEL', " . var_export($settings['account_label'], true) . ");\n"
        . "defined('SHOP_BANK_DUE_DAYS') || define('SHOP_BANK_DUE_DAYS', " . $settings['due_days'] . ");\n"
        . KIS_BANK_BLOCK_END;
}

function kisProductionBankMergeConfig(string $config, string $block): string
{
    if (!str_starts_with(ltrim($config), '<?php')) {
        throw new RuntimeException('Production config.php is not a PHP file.');
    }

    $pattern = '/\R*' . preg_quote(KIS_BANK_BLOCK_BEGIN, '/') . '.*?'
        . preg_quote(KIS_BANK_BLOCK_END, '/') . '\R*/s';
    $withoutManaged = preg_replace($pattern, "\n", $config);
    if (!is_string($withoutManaged)) {
        throw new RuntimeException('Managed bank block could not be replaced.');
    }

    $openingPattern = '/\A(<\?php(?:\s*declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;)?)/';
    $merged = preg_replace($openingPattern, "$1\n\n" . $block, $withoutManaged, 1, $count);
    if (!is_string($merged) || $count !== 1) {
        throw new RuntimeException('Managed bank block could not be inserted safely.');
    }

    token_get_all($merged, TOKEN_PARSE);
    return $merged;
}

/** @param array{iban:string,bic:string,account_label:string,due_days:int} $expected @return array<string,mixed> */
function kisProductionBankProbe(string $appRoot, string $appHost, array $expected): array
{
    $_SERVER['HTTP_HOST'] = $appHost;
    $_SERVER['SERVER_NAME'] = $appHost;
    require $appRoot . '/config.php';
    require_once $appRoot . '/includes/shop_checkout.php';

    try {
        $actual = shopBankSettingsFromConfig();
        $valid = true;
    } catch (Throwable) {
        $actual = [];
        $valid = false;
    }

    return [
        'ok' => true,
        'environment' => defined('JE_LOKALNE') && JE_LOKALNE === false ? 'production' : 'invalid',
        'bank_valid' => $valid,
        'iban_match' => $valid && hash_equals(hash('sha256', $expected['iban']), hash('sha256', (string)($actual['iban'] ?? ''))),
        'bic_match' => $valid && hash_equals(hash('sha256', $expected['bic']), hash('sha256', (string)($actual['bic'] ?? ''))),
        'label_match' => $valid && hash_equals(hash('sha256', $expected['account_label']), hash('sha256', (string)($actual['account_label'] ?? ''))),
        'due_days_match' => $valid && $expected['due_days'] === (int)($actual['due_days'] ?? 0),
    ];
}

function kisProductionBankMain(): void
{
    if (PHP_SAPI !== 'cli') {
        http_response_code(404);
        exit;
    }
    if ((string)getenv('CONFIGURE_PRODUCTION_BANK') !== '1') {
        throw new RuntimeException('CONFIGURE_PRODUCTION_BANK=1 is required.');
    }

    $appRoot = rtrim(trim((string)getenv('APP_ROOT')), '/\\');
    $appHost = strtolower(trim((string)getenv('APP_HOST')));
    $settingsFile = trim((string)getenv('BANK_SETTINGS_FILE'));
    $backupDir = rtrim(trim((string)getenv('CONFIG_BACKUP_DIR')), '/\\');
    if ($appRoot === '' || $appHost === '' || !is_file($appRoot . '/config.php')) {
        throw new RuntimeException('Production app root, host, or config.php is unavailable.');
    }
    if ($settingsFile === '' || !is_file($settingsFile) || $backupDir === '') {
        throw new RuntimeException('Bank settings bundle or backup directory is unavailable.');
    }

    $decoded = json_decode((string)file_get_contents($settingsFile), true, 16, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new RuntimeException('Bank settings bundle is invalid.');
    }
    $settings = kisProductionBankValidate($decoded);
    $configPath = $appRoot . '/config.php';
    $oldConfig = (string)file_get_contents($configPath);
    $newConfig = kisProductionBankMergeConfig($oldConfig, kisProductionBankManagedBlock($settings));
    $changed = !hash_equals(hash('sha256', $oldConfig), hash('sha256', $newConfig));

    if ($changed) {
        if (!is_dir($backupDir) && !mkdir($backupDir, 0700, true) && !is_dir($backupDir)) {
            throw new RuntimeException('Production config backup directory could not be created.');
        }
        $backupPath = $backupDir . '/config-bank-' . gmdate('Ymd-His') . '-'
            . substr(hash('sha256', $oldConfig), 0, 12) . '.php';
        if (file_put_contents($backupPath, $oldConfig, LOCK_EX) === false) {
            throw new RuntimeException('Production config backup could not be written.');
        }
        chmod($backupPath, 0600);

        $temporaryPath = $configPath . '.bank-' . bin2hex(random_bytes(6)) . '.tmp';
        if (file_put_contents($temporaryPath, $newConfig, LOCK_EX) === false) {
            throw new RuntimeException('Temporary production config could not be written.');
        }
        chmod($temporaryPath, 0600);
        if (!rename($temporaryPath, $configPath)) {
            @unlink($temporaryPath);
            throw new RuntimeException('Production config could not be activated atomically.');
        }
        chmod($configPath, 0600);
    }

    $result = kisProductionBankProbe($appRoot, $appHost, $settings);
    $result['changed'] = $changed;
    echo json_encode($result, JSON_THROW_ON_ERROR) . PHP_EOL;
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    try {
        kisProductionBankMain();
    } catch (Throwable $exception) {
        fwrite(STDERR, 'Production bank configuration failed: ' . $exception->getMessage() . PHP_EOL);
        exit(1);
    }
}
