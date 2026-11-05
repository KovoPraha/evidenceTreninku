<?php

declare(strict_types=1);

const KIS_MEMBERSHIPS_BLOCK_BEGIN = '// BEGIN KIS MANAGED MEMBERSHIPS PRODUCTION';
const KIS_MEMBERSHIPS_BLOCK_END = '// END KIS MANAGED MEMBERSHIPS PRODUCTION';

function kisProductionMembershipsManagedBlock(bool $enabled): string
{
    return KIS_MEMBERSHIPS_BLOCK_BEGIN . "\n"
        . "defined('MONTHLY_MEMBERSHIP_AUTOMATION_ENABLED') || define('MONTHLY_MEMBERSHIP_AUTOMATION_ENABLED', "
        . ($enabled ? 'true' : 'false') . ");\n" . KIS_MEMBERSHIPS_BLOCK_END;
}

function kisProductionMembershipsMergeConfig(string $config, string $block): string
{
    if (!str_starts_with(ltrim($config), '<?php')) {
        throw new RuntimeException('Production config.php is not a PHP file.');
    }
    $pattern = '/\R*'.preg_quote(KIS_MEMBERSHIPS_BLOCK_BEGIN, '/').'.*?'.preg_quote(KIS_MEMBERSHIPS_BLOCK_END, '/').'\R*/s';
    $without = preg_replace($pattern, "\n", $config);
    if (!is_string($without)) {
        throw new RuntimeException('Managed Memberships block could not be replaced.');
    }
    $legacy = '/^([ \t]*)(define\s*\(\s*[\'\"](MONTHLY_MEMBERSHIP_AUTOMATION_ENABLED)[\'\"]\s*,[^\r\n]*\);)([ \t]*)$/m';
    $without = preg_replace_callback($legacy, static fn (array $match): string => $match[1]."defined('".$match[3]."') || ".$match[2].$match[4], $without);
    if (!is_string($without)) {
        throw new RuntimeException('Legacy Memberships configuration could not be guarded.');
    }
    $opening = '/\A(<\?php(?:\s*declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;)?)/';
    $merged = preg_replace($opening, "$1\n\n".$block, $without, 1, $count);
    if (!is_string($merged) || $count !== 1) {
        throw new RuntimeException('Managed Memberships block could not be inserted safely.');
    }
    if (token_get_all($merged, TOKEN_PARSE) === []) {
        throw new RuntimeException('Merged production config is empty.');
    }
    return $merged;
}

function kisProductionMembershipsWriteConfig(string $configPath, string $newConfig, string $backupDir): bool
{
    if (token_get_all($newConfig, TOKEN_PARSE) === []) {
        throw new RuntimeException('New production config is empty.');
    }
    $oldConfig = (string)file_get_contents($configPath);
    $changed = !hash_equals(hash('sha256', $oldConfig), hash('sha256', $newConfig));
    if (!$changed) {
        return false;
    }
    if (!is_dir($backupDir) && !mkdir($backupDir, 0700, true) && !is_dir($backupDir)) {
        throw new RuntimeException('Production config backup directory could not be created.');
    }
    $backupPath = $backupDir.'/config-memberships-'.gmdate('Ymd-His').'-'.substr(hash('sha256', $oldConfig), 0, 12).'-'.bin2hex(random_bytes(4)).'.php';
    if (file_put_contents($backupPath, $oldConfig, LOCK_EX) !== strlen($oldConfig)) {
        throw new RuntimeException('Production config backup could not be written.');
    }
    if (!chmod($backupPath, 0600) || !hash_equals(hash('sha256', $oldConfig), (string)hash_file('sha256', $backupPath))) {
        throw new RuntimeException('Private production config backup could not be verified.');
    }
    $temporaryPath = $configPath.'.memberships-'.bin2hex(random_bytes(6)).'.tmp';
    if (file_put_contents($temporaryPath, $newConfig, LOCK_EX) !== strlen($newConfig)) {
        throw new RuntimeException('Temporary production config could not be written.');
    }
    if (!chmod($temporaryPath, 0600) || !hash_equals(hash('sha256', $newConfig), (string)hash_file('sha256', $temporaryPath))) {
        @unlink($temporaryPath);
        throw new RuntimeException('Temporary production config could not be verified.');
    }
    if (!rename($temporaryPath, $configPath)) {
        @unlink($temporaryPath);
        throw new RuntimeException('Production config could not be activated atomically.');
    }
    chmod($configPath, 0600);
    return true;
}

/** @return array<string,mixed> */
function kisProductionMembershipsProbe(string $appRoot, string $appHost): array
{
    $_SERVER['HTTP_HOST'] = $appHost;
    $_SERVER['SERVER_NAME'] = $appHost;
    require $appRoot.'/config.php';
    if (!defined('JE_LOKALNE') || JE_LOKALNE !== false) {
        throw new RuntimeException('Production environment is required.');
    }
    foreach (['bin/monthly-memberships.php', 'includes/monthly_membership_worker.php'] as $file) {
        if (!is_file($appRoot.'/'.$file)) {
            throw new RuntimeException('Membership worker is not deployed.');
        }
    }
    $pdo = new PDO(
        'mysql:host='.(string)constant('DB_HOST').';dbname='.(string)constant('DB_NAME').';charset=utf8mb4',
        (string)constant('DB_USER'),
        (string)constant('DB_PASS'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    $pdo->exec('SET SESSION TRANSACTION READ ONLY');
    $pdo->beginTransaction();
    try {
        $counts = [];
        foreach (['monthly_memberships', 'monthly_membership_tariffs', 'monthly_membership_periods',
            'monthly_membership_receipts', 'monthly_membership_allocations', 'monthly_membership_messages',
            'monthly_membership_events', 'monthly_membership_tariff_teams', 'monthly_membership_team_grants'] as $table) {
            $counts[$table] = (int)$pdo->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();
        }
        $pdo->query('SELECT billing_interval FROM monthly_membership_tariffs WHERE 1=0');
        if ((int)$pdo->query('SELECT COUNT(*) FROM monthly_membership_mutex WHERE id=1')->fetchColumn() !== 1) {
            throw new RuntimeException('Membership mutex is not initialized.');
        }
        $heartbeat = $pdo->query("SELECT started_at,finished_at,status,summary_code FROM operational_job_runs "
            ."WHERE job_key='monthly_memberships' ORDER BY id DESC LIMIT 1")->fetch();
    } finally {
        $pdo->rollBack();
    }
    $flags = [];
    foreach (['MONTHLY_MEMBERSHIP_MAIL_ENABLED', 'FIO_IMPORT_ENABLED', 'FIO_AUTO_CONFIRM_ENABLED',
        'SUMUP_ENABLED', 'STRIPE_ENABLED', 'VELOCOTA_INTEGRATION', 'PACKETA_ENABLED'] as $name) {
        $flags[$name] = defined($name) ? constant($name) === true : false;
    }
    return ['ok' => true, 'environment' => 'production', 'inventory_read_only' => true,
        'automation_enabled' => defined('MONTHLY_MEMBERSHIP_AUTOMATION_ENABLED') && MONTHLY_MEMBERSHIP_AUTOMATION_ENABLED === true,
        'unchanged_flags' => $flags, 'counts' => $counts, 'last_run' => $heartbeat ?: null,
        'config_sha256' => hash_file('sha256', $appRoot.'/config.php')];
}

function kisProductionMembershipsMain(): void
{
    if (PHP_SAPI !== 'cli') {
        http_response_code(404);
        exit;
    }
    if (getenv('CONFIGURE_PRODUCTION_MEMBERSHIPS') !== '1') {
        throw new RuntimeException('Explicit production configuration guard is required.');
    }
    $action = (string)getenv('MEMBERSHIPS_CONFIG_ACTION');
    $appRoot = rtrim(trim((string)getenv('APP_ROOT')), '/\\');
    $appHost = strtolower(trim((string)getenv('APP_HOST')));
    if (!in_array($action, ['probe', 'enable', 'disable'], true) || $appHost !== 'kis.kovopraha.cz'
        || $appRoot === '' || !is_file($appRoot.'/config.php')) {
        throw new RuntimeException('Invalid action or production location.');
    }
    $state = kisProductionMembershipsProbe($appRoot, $appHost);
    if ($action === 'probe') {
        echo json_encode($state, JSON_THROW_ON_ERROR).PHP_EOL;
        return;
    }
    $backupDir = trim((string)getenv('CONFIG_BACKUP_DIR'));
    if ($backupDir === '') {
        throw new RuntimeException('Private config backup directory is required.');
    }
    $configPath = $appRoot.'/config.php';
    $newConfig = kisProductionMembershipsMergeConfig((string)file_get_contents($configPath), kisProductionMembershipsManagedBlock($action === 'enable'));
    $changed = kisProductionMembershipsWriteConfig($configPath, $newConfig, $backupDir);
    echo json_encode(['ok' => true, 'changed' => $changed, 'requested_mode' => $action,
        'config_sha256' => hash_file('sha256', $configPath)], JSON_THROW_ON_ERROR).PHP_EOL;
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    try {
        kisProductionMembershipsMain();
    } catch (Throwable $exception) {
        // Config contents and database connection diagnostics must never reach Actions logs.
        fwrite(STDERR, 'Membership production configuration failed; inspect private server diagnostics.'.PHP_EOL);
        exit(1);
    }
}
