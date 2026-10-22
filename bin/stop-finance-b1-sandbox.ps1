param([string]$XamppRoot = 'C:\xampp')
$ErrorActionPreference = 'Stop'
$applicationRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$sandboxRoot = Join-Path $env:LOCALAPPDATA 'EvidencePavelB1\finance-b1-20261022'
$ownership = Join-Path $sandboxRoot 'owner.txt'
if (-not (Test-Path -LiteralPath $ownership) -or (Get-Content -LiteralPath $ownership -Raw).Trim() -ne $applicationRoot) { throw 'Sandbox ownership does not match this checkout.' }
foreach ($port in @(18086,33426)) {
    $expected = if ($port -eq 18086) { $applicationRoot } else { Join-Path $sandboxRoot 'mysql' }
    $listeners = @(Get-NetTCPConnection -State Listen -LocalPort $port -ErrorAction SilentlyContinue)
    foreach ($listener in $listeners) {
        $process = Get-CimInstance Win32_Process -Filter "ProcessId=$($listener.OwningProcess)"
        if ($listener.LocalAddress -ne '127.0.0.1' -or -not $process.CommandLine.Contains($expected)) { throw "Port $port is not the owned sandbox." }
        if ($port -eq 18086) { Stop-Process -Id $listener.OwningProcess }
        else {
            $shutdownArguments = @('--no-defaults', '--protocol=tcp', '--host=127.0.0.1', '--port=33426', '--user=root', 'shutdown')
            & (Join-Path $XamppRoot 'mysql\bin\mysqladmin.exe') @shutdownArguments
            if ($LASTEXITCODE -ne 0) { throw 'Isolated MariaDB graceful shutdown failed.' }
        }
        # mysqladmin may return before the server releases its files and socket.
        # A following setup must never mistake the shutting-down listener for readiness.
        if (Get-Process -Id $listener.OwningProcess -ErrorAction SilentlyContinue) {
            Wait-Process -Id $listener.OwningProcess -Timeout 25 -ErrorAction Stop
        }
    }
}
Write-Output 'Only the owned B1 processes stopped; synthetic data and evidence remain on disk.'
