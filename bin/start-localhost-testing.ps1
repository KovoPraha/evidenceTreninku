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
$mysqlExecutable = Join-Path $XamppRoot 'mysql\bin\mysqld.exe'
$mysqlDataDirectory = [IO.Path]::GetFullPath($DataDirectory)
$mysqlLog = Join-Path $mysqlDataDirectory 'evidence-local.log'
$mysqlPid = Join-Path $mysqlDataDirectory 'evidence-local.pid'
$apacheExecutable = Join-Path $XamppRoot 'apache\bin\httpd.exe'
$applicationFolder = Split-Path -Leaf $applicationRoot
$applicationUrl = 'http://localhost/{0}/' -f $applicationFolder

if ($DatabaseName -notmatch '^[a-zA-Z0-9_]+$') {
    throw 'Název databáze smí obsahovat jen písmena, číslice a podtržítko.'
}

foreach ($requiredPath in @($mysqlExecutable, $mysqlDataDirectory, $apacheExecutable, $applicationRoot)) {
    if (-not (Test-Path -LiteralPath $requiredPath)) {
        throw "Chybí povinná lokální součást: $requiredPath"
    }
}

function Wait-LocalPort([int]$Port, [int]$Seconds = 25) {
    $deadline = (Get-Date).AddSeconds($Seconds)
    do {
        $listener = Get-NetTCPConnection -State Listen -LocalPort $Port -ErrorAction SilentlyContinue
        if ($listener) { return $listener }
        Start-Sleep -Milliseconds 500
    } until ((Get-Date) -gt $deadline)
    throw "Lokální služba nezačala naslouchat na portu $Port."
}

$databaseListener = Get-NetTCPConnection -State Listen -LocalPort $Port -ErrorAction SilentlyContinue
if ($databaseListener) {
    $databaseProcess = Get-CimInstance Win32_Process -Filter "ProcessId=$($databaseListener.OwningProcess)"
    if (-not $databaseProcess -or $databaseProcess.CommandLine -notlike "*$mysqlDataDirectory*") {
        throw "Port $Port používá jiná služba. Evidence databáze nebyla spuštěna."
    }
} else {
    $mysqlBase = Join-Path $XamppRoot 'mysql'
    $mysqlArguments = '--no-defaults --basedir="{0}" --datadir="{1}" --port={2} --bind-address=127.0.0.1 --skip-name-resolve --log-error="{3}" --pid-file="{4}"' -f $mysqlBase, $mysqlDataDirectory, $Port, $mysqlLog, $mysqlPid
    Start-Process -FilePath $mysqlExecutable -ArgumentList $mysqlArguments `
        -WorkingDirectory $mysqlBase -WindowStyle Hidden | Out-Null
    $databaseListener = Wait-LocalPort $Port
}

$env:APP_HOST = 'localhost'
$env:EVIDENCE_LOCAL_DB_HOST = "127.0.0.1;port=$Port"
$env:EVIDENCE_LOCAL_DB_NAME = $DatabaseName
$env:EVIDENCE_LOCAL_DB_USER = 'root'
$env:EVIDENCE_LOCAL_DB_PASS = ''

if (-not (Get-NetTCPConnection -State Listen -LocalPort 80 -ErrorAction SilentlyContinue)) {
    Start-Process -FilePath $apacheExecutable -WorkingDirectory 'C:\xampp\apache' -WindowStyle Hidden | Out-Null
    Wait-LocalPort 80 | Out-Null
}

$response = Invoke-WebRequest -Uri $applicationUrl -UseBasicParsing -TimeoutSec 15
if ([int]$response.StatusCode -ne 200) {
    throw "Aplikace vrátila neočekávaný stav $($response.StatusCode)."
}

Write-Host ''
Write-Host 'EvidencePavel je připravena k offline testování.' -ForegroundColor Green
Write-Host "Aplikace: $applicationUrl"
Write-Host "Databáze: 127.0.0.1:$Port / $DatabaseName"
Write-Host "Návod:    $applicationRoot\outputs\localhost-test-2026-08-25\OFFLINE_TESTOVANI.md"
Write-Host 'Toto okno lze zavřít; Apache a databáze zůstanou spuštěné.'
