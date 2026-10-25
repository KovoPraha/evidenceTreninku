param([string]$XamppRoot = 'C:\xampp')
$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'finance-b1-process-guards.ps1')
$applicationRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$sandboxRoot = Join-Path $env:LOCALAPPDATA 'EvidencePavelB1\finance-b1-20261022'
$dataDirectory = Join-Path $sandboxRoot 'mysql'
$php = Join-Path $XamppRoot 'php\php.exe'
$mysqlBase = Join-Path $XamppRoot 'mysql'
$mysql = Join-Path $mysqlBase 'bin\mysql.exe'
$dbPort = 33426
$webPort = 18086
$marker = 'EVIDENCE_FINANCE_B1_SYNTHETIC_ONLY'
$config = Join-Path $applicationRoot 'config.php'
$iniPath = Join-Path $sandboxRoot 'php-web.ini'
$router = Join-Path $applicationRoot 'bin\finance-b1-router.php'
if (-not (Test-Path -LiteralPath $router)) { throw 'The isolated HTTP router is missing.' }
if ((Test-Path -LiteralPath $config) -and -not ((Get-Content -LiteralPath $config -Raw).Contains($marker))) {
    throw 'Refusing to replace an existing non-B1 config.php.'
}
foreach ($directory in @($sandboxRoot, $dataDirectory, (Join-Path $sandboxRoot 'private'), (Join-Path $sandboxRoot 'sessions'), (Join-Path $sandboxRoot 'outbox'), (Join-Path $sandboxRoot 'upload-tmp'))) {
    New-Item -ItemType Directory -Path $directory -Force | Out-Null
}
$ownership = Join-Path $sandboxRoot 'owner.txt'
if (Test-Path -LiteralPath $ownership) {
    if ((Get-Content -LiteralPath $ownership -Raw).Trim() -ne $applicationRoot) { throw 'Sandbox belongs to a different checkout.' }
} else {
    if (@(Get-ChildItem -LiteralPath $dataDirectory -Force).Count -gt 0) { throw 'Refusing existing unowned database files.' }
    Write-FinanceB1Utf8 $ownership $applicationRoot
}
function Assert-OwnedListener([int]$Port, [string]$ExpectedCommand) {
    $listeners = @(Get-NetTCPConnection -State Listen -LocalPort $Port -ErrorAction SilentlyContinue)
    foreach ($listener in $listeners) {
        $process = Get-CimInstance Win32_Process -Filter "ProcessId=$($listener.OwningProcess)"
        if ($listener.LocalAddress -ne '127.0.0.1' -or -not $process.CommandLine.Contains($ExpectedCommand)) { throw "Port $Port belongs to another service." }
    }
    return $listeners.Count -gt 0
}
function Assert-OwnedHttpListener {
    $listeners = @(Get-NetTCPConnection -State Listen -LocalPort $webPort -ErrorAction SilentlyContinue)
    if ($listeners.Count -gt 1) { throw 'Multiple HTTP listeners cannot be adopted.' }
    foreach ($listener in $listeners) {
        $process = Get-CimInstance Win32_Process -Filter "ProcessId=$($listener.OwningProcess)"
        if (-not (Test-FinanceB1HttpProcess $process $listener.LocalAddress $php $iniPath $applicationRoot $router $webPort)) { throw 'HTTP port is not the exact owned executable, ini, bind, document root and router.' }
    }
    return $listeners.Count -gt 0
}
$null = Assert-OwnedHttpListener
Assert-FinanceB1DataDirectoryUncompressed $dataDirectory
if (-not (Test-Path -LiteralPath (Join-Path $dataDirectory 'mysql'))) {
    & (Join-Path $mysqlBase 'bin\mysql_install_db.exe') "--datadir=$dataDirectory" "--port=$dbPort" --default-user --silent
    if ($LASTEXITCODE -ne 0) { throw 'Isolated database initialization failed.' }
}
if (-not (Assert-OwnedListener $dbPort $dataDirectory)) {
    $arguments = '--no-defaults --basedir="{0}" --datadir="{1}" --port={2} --bind-address=127.0.0.1 --skip-name-resolve --log-error="{3}" --pid-file="{4}"' -f $mysqlBase,$dataDirectory,$dbPort,(Join-Path $sandboxRoot 'mysql.log'),(Join-Path $sandboxRoot 'mysql.pid')
    # Crash recovery may run before the TCP listener appears. Never start a second
    # instance on the same files merely because a previous readiness wait expired.
    $starting = @(Get-CimInstance Win32_Process -Filter "Name='mysqld.exe'" | Where-Object { $_.CommandLine -and $_.CommandLine.Contains('--datadir="' + $dataDirectory + '"') })
    if ($starting.Count -gt 1) { throw 'Multiple processes reference the isolated database files.' }
    if ($starting.Count -eq 0) {
        Start-Process -FilePath (Join-Path $mysqlBase 'bin\mysqld.exe') -ArgumentList $arguments -WindowStyle Hidden | Out-Null
    }
    $deadline = (Get-Date).AddSeconds(25)
    do { Start-Sleep -Milliseconds 250; $ready = Assert-OwnedListener $dbPort $dataDirectory } until ($ready -or (Get-Date) -gt $deadline)
    if (-not $ready) { throw 'Isolated MariaDB is not ready yet; recovery may still be running. Inspect mysql.log, then rerun setup; do not delete database files or start another instance.' }
}
$connection = @('--no-defaults', '--protocol=tcp', '-h127.0.0.1', "-P$dbPort", '-uroot')
$readyDeadline = (Get-Date).AddSeconds(25)
do {
    $probe = & $mysql @connection --batch --skip-column-names --execute='SELECT 1' 2>$null
    $databaseReady = $LASTEXITCODE -eq 0 -and "$probe".Trim() -eq '1'
    if (-not $databaseReady) { Start-Sleep -Milliseconds 250 }
} until ($databaseReady -or (Get-Date) -gt $readyDeadline)
if (-not $databaseReady) { throw 'Isolated MariaDB did not become query-ready.' }
& $mysql @connection --execute="CREATE DATABASE IF NOT EXISTS evidence_b1 CHARACTER SET utf8mb4; CREATE DATABASE IF NOT EXISTS evidence_b1_rewards CHARACTER SET utf8mb4; CREATE DATABASE IF NOT EXISTS evidence_b1_receipts CHARACTER SET utf8mb4; CREATE USER IF NOT EXISTS 'b1_app'@'127.0.0.1' IDENTIFIED BY ''; GRANT ALL ON evidence_b1.* TO 'b1_app'@'127.0.0.1'; GRANT ALL ON evidence_b1_rewards.* TO 'b1_app'@'127.0.0.1'; GRANT ALL ON evidence_b1_receipts.* TO 'b1_app'@'127.0.0.1';"
if ($LASTEXITCODE -ne 0) { throw 'Isolated schemas/user setup failed.' }
$portableRoot = $sandboxRoot.Replace('\','/')
$configBody = @'
<?php
// EVIDENCE_FINANCE_B1_SYNTHETIC_ONLY: generated isolated local test configuration.
define('JE_LOKALNE', true);
define('DB_HOST', '127.0.0.1;port=33426');
define('DB_NAME', 'evidence_b1');
define('DB_USER', 'b1_app');
define('DB_PASS', '');
define('APP_BASE_URL', 'http://127.0.0.1:18086');
define('APP_PRIVATE_STORAGE_ROOT', '__ROOT__/private');
putenv('APP_PRIVATE_STORAGE_ROOT=' . APP_PRIVATE_STORAGE_ROOT);
define('AUTH_RATE_LIMIT_PEPPER', 'b1-synthetic-local-only-never-deploy-0000000000');
define('AUTH_TRUSTED_PROXIES', []);
define('VELOCOTA_INTEGRATION', false);
define('FIO_IMPORT_ENABLED', false);
define('FIO_AUTO_CONFIRM_ENABLED', false);
define('STRIPE_ENABLED', false);
define('SUMUP_ENABLED', false);
define('SHOP_BANK_IBAN', '');
define('SHOP_BANK_ACCOUNT_LABEL', 'LOCALHOST B1 NEPLATIT');
'@
[IO.File]::WriteAllText($config, $configBody.Replace('__ROOT__',$portableRoot), [Text.UTF8Encoding]::new($false))
$extensionPath = (Join-Path $XamppRoot 'php\ext').Replace('\','/')
$iniBody = @"
[PHP]
extension_dir="$extensionPath"
extension=pdo_mysql
extension=pdo_sqlite
extension=mbstring
extension=fileinfo
extension=gd
extension=intl
extension=zip
extension=openssl
extension=sodium
date.timezone=Europe/Prague
default_charset=UTF-8
session.save_path="$portableRoot/sessions"
upload_tmp_dir="$portableRoot/upload-tmp"
log_errors=On
display_errors=Off
error_log="$portableRoot/php-error.log"
allow_url_fopen=Off
allow_url_include=Off
disable_functions=mail,curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,stream_socket_server,socket_connect,exec,shell_exec,system,passthru,popen,proc_open,pcntl_exec
max_execution_time=30
memory_limit=256M
upload_max_filesize=12M
post_max_size=16M
"@
[IO.File]::WriteAllText($iniPath, $iniBody, [Text.UTF8Encoding]::new($false))
$tables = & $mysql @connection --batch --skip-column-names --execute="SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='evidence_b1'"
if ($LASTEXITCODE -ne 0) { throw 'Could not inspect isolated schema.' }
if ([int]$tables -eq 0) {
    $schema = Join-Path $applicationRoot 'database\local-demo-schema.sql'
    $import = Start-Process -FilePath $mysql -ArgumentList ($connection + @('evidence_b1')) -RedirectStandardInput $schema -WindowStyle Hidden -Wait -PassThru
    if ($import.ExitCode -ne 0) { throw 'Isolated empty schema import failed.' }
}
if (-not (Test-Path -LiteralPath (Join-Path $applicationRoot 'vendor\autoload.php'))) {
    throw 'Run composer install --no-interaction --prefer-dist in this checkout first (never composer update).'
}
$env:APP_HOST = 'localhost'
$migrationOutput = & $php -c $iniPath (Join-Path $applicationRoot 'bin\migrate.php') --apply --json
$migrationExit = $LASTEXITCODE
Write-FinanceB1Utf8 (Join-Path $sandboxRoot 'migrations.json') ($migrationOutput -join [Environment]::NewLine)
if ($migrationExit -ne 0) { throw 'Isolated migrations failed; see migrations.json.' }
$descriptor = @{ application_root=$applicationRoot; runtime_root=$sandboxRoot; php=$php; web_ini=$iniPath; base_url='http://127.0.0.1:18086'; db_host='127.0.0.1'; db_port=$dbPort; db_name='evidence_b1'; db_user='b1_app'; session_path=(Join-Path $sandboxRoot 'sessions'); private_path=(Join-Path $sandboxRoot 'private'); outbox_path=(Join-Path $sandboxRoot 'outbox'); synthetic_only=$true; external_sends_disabled=$true }
Write-FinanceB1Utf8 (Join-Path $sandboxRoot 'environment.json') ($descriptor | ConvertTo-Json)
$readinessOutput = & $php -c $iniPath (Join-Path $applicationRoot 'bin\probe-finance-b1-sandbox.php')
$readinessExit = $LASTEXITCODE
Write-FinanceB1Utf8 (Join-Path $sandboxRoot 'readiness.json') ($readinessOutput -join [Environment]::NewLine)
if ($readinessExit -ne 0) { throw 'Isolated database/storage readiness failed; see readiness.json. Preserve files and backups; do not reset or delete the database.' }
# Reload only the fully verified owned web process, so it uses the ini just probed.
if (Assert-OwnedHttpListener) {
    $listener = Get-NetTCPConnection -State Listen -LocalPort $webPort
    $process = Get-CimInstance Win32_Process -Filter "ProcessId=$($listener.OwningProcess)"
    if (-not (Test-FinanceB1HttpProcess $process $listener.LocalAddress $php $iniPath $applicationRoot $router $webPort)) { throw 'HTTP ownership changed during setup.' }
    Stop-Process -Id $listener.OwningProcess
    if (Get-Process -Id $listener.OwningProcess -ErrorAction SilentlyContinue) { Wait-Process -Id $listener.OwningProcess -Timeout 25 }
}
if (-not (Assert-OwnedHttpListener)) {
    $arguments = '-c "{0}" -S 127.0.0.1:{1} -t "{2}" "{3}"' -f $iniPath,$webPort,$applicationRoot,$router
    Start-Process -FilePath $php -ArgumentList $arguments -WorkingDirectory $applicationRoot -WindowStyle Hidden -RedirectStandardOutput (Join-Path $sandboxRoot 'http-stdout.log') -RedirectStandardError (Join-Path $sandboxRoot 'http-stderr.log') | Out-Null
    $deadline = (Get-Date).AddSeconds(10)
    do { Start-Sleep -Milliseconds 250; $ready = Assert-OwnedHttpListener } until ($ready -or (Get-Date) -gt $deadline)
    if (-not $ready) { throw 'Isolated PHP HTTP server did not start.' }
}
Write-Output ('B1 sandbox ready: http://127.0.0.1:18086 ; MariaDB 127.0.0.1:33426/evidence_b1 ; descriptor ' + (Join-Path $sandboxRoot 'environment.json'))
