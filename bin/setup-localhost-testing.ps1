param(
    [string]$XamppRoot = 'C:\xampp',
    [string]$DataDirectory = '',
    [ValidateRange(1024, 65535)]
    [int]$Port = 3308,
    [string]$DatabaseName = 'evidence'
)

$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = [System.Text.UTF8Encoding]::new($false)

$applicationRoot = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path
$XamppRoot = (Resolve-Path -LiteralPath $XamppRoot).Path
if ($DataDirectory -eq '') {
    $DataDirectory = Join-Path $XamppRoot 'mysql\evidence-local-data'
}
$DataDirectory = [IO.Path]::GetFullPath($DataDirectory)

if ($DatabaseName -notmatch '^[a-zA-Z0-9_]+$') {
    throw 'Název databáze smí obsahovat jen písmena, číslice a podtržítko.'
}

$mysqlBase = Join-Path $XamppRoot 'mysql'
$mysqlBin = Join-Path $mysqlBase 'bin'
$mysqlServer = Join-Path $mysqlBin 'mysqld.exe'
$mysqlClient = Join-Path $mysqlBin 'mysql.exe'
$mysqlInstaller = Join-Path $mysqlBin 'mysql_install_db.exe'
$php = Join-Path $XamppRoot 'php\php.exe'
$schema = Join-Path $applicationRoot 'database\local-demo-schema.sql'
$config = Join-Path $applicationRoot 'config.php'
$configExample = Join-Path $applicationRoot 'config.example.php'
$fixture = Join-Path $applicationRoot 'tests\fixtures\shoptet\products-valid.csv'

foreach ($requiredPath in @($mysqlServer, $mysqlClient, $mysqlInstaller, $php, $schema, $configExample, $fixture)) {
    if (-not (Test-Path -LiteralPath $requiredPath -PathType Leaf)) {
        throw "Chybí povinná součást prvního spuštění: $requiredPath"
    }
}

if (-not (Test-Path -LiteralPath (Join-Path $DataDirectory 'mysql') -PathType Container)) {
    if (Test-Path -LiteralPath $DataDirectory) {
        $existing = @(Get-ChildItem -LiteralPath $DataDirectory -Force -ErrorAction Stop)
        if ($existing.Count -gt 0) {
            throw "Datový adresář existuje, ale není platnou MariaDB instalací: $DataDirectory"
        }
    } else {
        New-Item -ItemType Directory -Path $DataDirectory | Out-Null
    }

    Write-Host 'Inicializuji samostatnou lokální MariaDB...' -ForegroundColor Cyan
    & $mysqlInstaller "--datadir=$DataDirectory" "--port=$Port" --default-user --silent
    if ($LASTEXITCODE -ne 0) {
        throw 'Inicializace lokální MariaDB selhala.'
    }
}

$databaseListener = Get-NetTCPConnection -State Listen -LocalPort $Port -ErrorAction SilentlyContinue
if ($databaseListener) {
    $databaseProcess = Get-CimInstance Win32_Process -Filter "ProcessId=$($databaseListener.OwningProcess)"
    if (-not $databaseProcess -or $databaseProcess.CommandLine -notlike "*$DataDirectory*") {
        throw "Port $Port používá jiná služba. Evidence databáze nebyla spuštěna."
    }
} else {
    $mysqlLog = Join-Path $DataDirectory 'evidence-local.log'
    $mysqlPidFile = Join-Path $DataDirectory 'evidence-local.pid'
    $mysqlArguments = '--no-defaults --basedir="{0}" --datadir="{1}" --port={2} --bind-address=127.0.0.1 --skip-name-resolve --log-error="{3}" --pid-file="{4}"' -f $mysqlBase, $DataDirectory, $Port, $mysqlLog, $mysqlPidFile
    Start-Process -FilePath $mysqlServer -ArgumentList $mysqlArguments `
        -WorkingDirectory $mysqlBase -WindowStyle Hidden | Out-Null

    $deadline = (Get-Date).AddSeconds(30)
    do {
        Start-Sleep -Milliseconds 500
        $databaseListener = Get-NetTCPConnection -State Listen -LocalPort $Port -ErrorAction SilentlyContinue
    } until ($databaseListener -or (Get-Date) -gt $deadline)
    if (-not $databaseListener) {
        if (Test-Path -LiteralPath $mysqlLog) { Get-Content -LiteralPath $mysqlLog -Tail 80 }
        throw "Lokální databáze nezačala naslouchat na portu $Port."
    }
}

$env:APP_HOST = 'localhost'
$env:EVIDENCE_LOCAL_DB_HOST = "127.0.0.1;port=$Port"
$env:EVIDENCE_LOCAL_DB_NAME = $DatabaseName
$env:EVIDENCE_LOCAL_DB_USER = 'root'
$env:EVIDENCE_LOCAL_DB_PASS = ''

if (-not (Test-Path -LiteralPath $config -PathType Leaf)) {
    Copy-Item -LiteralPath $configExample -Destination $config
    Write-Host 'Vytvořen lokální config.php z bezpečného vzoru.' -ForegroundColor Cyan
}

$configPathForPhp = $config.Replace('\', '/').Replace("'", "\'")
$expectedHost = "127.0.0.1;port=$Port"
$configCheck = "putenv('APP_HOST=localhost'); require '$configPathForPhp'; exit(defined('JE_LOKALNE') && JE_LOKALNE === true && defined('DB_HOST') && DB_HOST === getenv('EVIDENCE_LOCAL_DB_HOST') && defined('DB_NAME') && DB_NAME === getenv('EVIDENCE_LOCAL_DB_NAME') ? 0 : 2);"
& $php -r $configCheck
if ($LASTEXITCODE -ne 0) {
    throw "config.php neukazuje na bezpečnou lokální databázi $expectedHost / $DatabaseName. Upravte jej podle config.example.php."
}

if (-not (Test-Path -LiteralPath (Join-Path $applicationRoot 'vendor\autoload.php') -PathType Leaf)) {
    $composer = Get-Command composer -ErrorAction SilentlyContinue
    if (-not $composer) {
        throw 'Chybí Composer. Připojte počítač k internetu, nainstalujte Composer a spusťte přípravu znovu.'
    }
    Write-Host 'Instaluji přesně zamčené PHP závislosti...' -ForegroundColor Cyan
    & $composer.Source install --working-dir=$applicationRoot --no-interaction --prefer-dist
    if ($LASTEXITCODE -ne 0) {
        throw 'Composer install selhal.'
    }
}

$mysqlConnection = @('--protocol=tcp', '-h', '127.0.0.1', '-P', "$Port", '-u', 'root')
& $mysqlClient @mysqlConnection --execute="CREATE DATABASE IF NOT EXISTS ``$DatabaseName`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
if ($LASTEXITCODE -ne 0) {
    throw 'Vytvoření lokální databáze selhalo.'
}

$tableCountOutput = & $mysqlClient @mysqlConnection --batch --skip-column-names --execute="SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$DatabaseName'"
if ($LASTEXITCODE -ne 0 -or $tableCountOutput -notmatch '^\d+$') {
    throw 'Nelze ověřit stav lokální databáze.'
}
$tableCount = [int]$tableCountOutput

if ($tableCount -eq 0) {
    Write-Host 'Importuji prázdné aplikační schéma bez osobních dat...' -ForegroundColor Cyan
    $importArguments = @('--protocol=tcp', '-h', '127.0.0.1', '-P', "$Port", '-u', 'root', $DatabaseName)
    $import = Start-Process -FilePath $mysqlClient -ArgumentList $importArguments `
        -RedirectStandardInput $schema -Wait -NoNewWindow -PassThru
    if ($import.ExitCode -ne 0) {
        throw 'Import lokálního schématu selhal.'
    }
}

Write-Host 'Kontroluji a aplikuji verzované migrace...' -ForegroundColor Cyan
& $php (Join-Path $applicationRoot 'bin\migrate.php') --apply --json
if ($LASTEXITCODE -ne 0) {
    throw 'Databázové migrace selhaly.'
}

$importRunCount = & $mysqlClient @mysqlConnection --batch --skip-column-names $DatabaseName --execute='SELECT COUNT(*) FROM shop_catalog_import_runs'
if ($LASTEXITCODE -ne 0 -or $importRunCount -notmatch '^\d+$') {
    throw 'Nelze ověřit testovací katalog.'
}
if ([int]$importRunCount -eq 0) {
    Write-Host 'Připravuji syntetický katalog z verzované fixture...' -ForegroundColor Cyan
    & $php (Join-Path $applicationRoot 'bin\shoptet-products-dry-run.php') "--input=$fixture" --json
    if ($LASTEXITCODE -ne 0) { throw 'Kontrola syntetického katalogu selhala.' }
    & $php (Join-Path $applicationRoot 'bin\shoptet-products-stage.php') "--input=$fixture" --apply --json
    if ($LASTEXITCODE -ne 0) { throw 'Příprava syntetického katalogu selhala.' }
}

Write-Host 'Zakládám nebo obnovuji výhradně syntetická demo data...' -ForegroundColor Cyan
& $php (Join-Path $applicationRoot 'bin\seed-local-demo.php')
if ($LASTEXITCODE -ne 0) {
    throw 'Seed lokálního dema selhal.'
}

& (Join-Path $PSScriptRoot 'start-localhost-testing.ps1') `
    -XamppRoot $XamppRoot -DataDirectory $DataDirectory -Port $Port -DatabaseName $DatabaseName

Write-Host ''
Write-Host 'První localhost instalace je hotová.' -ForegroundColor Green
Write-Host "Další spuštění: $applicationRoot\START_LOCALHOST_TESTOVANI.cmd"
Write-Host "Návod:          $applicationRoot\outputs\localhost-test-2026-08-25\OFFLINE_TESTOVANI.md"
