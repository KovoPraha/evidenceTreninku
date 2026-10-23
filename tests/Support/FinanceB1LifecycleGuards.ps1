$ErrorActionPreference = 'Stop'
$applicationRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$script:stopped = $false
# Mock only process observation/termination. No real process or database is stopped.
function Get-NetTCPConnection {
    param($State, $LocalPort, $ErrorAction)
    [pscustomobject]@{ LocalAddress = '127.0.0.1'; LocalPort = $LocalPort; OwningProcess = $LocalPort }
}
function Get-CimInstance {
    param($ClassName, $Filter)
    if ($Filter -eq 'ProcessId=18086') { [pscustomobject]@{ CommandLine = 'php -t "' + $applicationRoot + '"' } }
    else { [pscustomobject]@{ CommandLine = 'foreign database service' } }
}
function Stop-Process { param($Id) $script:stopped = $true; throw 'A process must not be stopped before both ports are verified.' }
$rejected = $false
try { & (Join-Path $applicationRoot 'bin\stop-finance-b1-sandbox.ps1') }
catch { $rejected = $_.Exception.Message -like '*no services were stopped*' }
if (-not $rejected -or $script:stopped) { throw 'Lifecycle preflight did not preserve the first service when the second port was foreign.' }
@{status='PASS'; checks=1; real_processes_stopped=0; scenario='Foreign database listener prevents stopping the owned web listener'} | ConvertTo-Json -Compress
