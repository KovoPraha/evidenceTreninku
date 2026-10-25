$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot '..\..\bin\finance-b1-process-guards.ps1')
$testRoot = [IO.Path]::GetFullPath((Join-Path ([IO.Path]::GetTempPath()) ('b1-compression-' + [guid]::NewGuid().ToString('N'))))
New-Item -ItemType Directory -Path $testRoot | Out-Null
$fixture = Join-Path $testRoot 'synthetic-data.txt'
try {
    & compact.exe /U $testRoot | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'Cannot prepare uncompressed test directory.' }
    [IO.File]::WriteAllText($fixture, ('synthetic fixture only ' * 4096))
    Assert-FinanceB1DataDirectoryUncompressed $testRoot
    & compact.exe /C $fixture | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'NTFS fixture compression failed.' }
    $rejected = $false
    try { Assert-FinanceB1DataDirectoryUncompressed $testRoot }
    catch { $rejected = $_.Exception.Message -like '*NTFS-compressed*' }
    if (-not $rejected) { throw 'A compressed database file was accepted.' }
    & compact.exe /U $fixture | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'NTFS fixture decompression failed.' }
    Assert-FinanceB1DataDirectoryUncompressed $testRoot
    @{status='PASS'; checks=3; production_mutations=$false; real_processes_stopped=0} | ConvertTo-Json -Compress
} finally {
    # Remove only the two exact paths created above; never recurse through temp.
    if (Test-Path -LiteralPath $fixture) { Remove-Item -LiteralPath $fixture }
    if (Test-Path -LiteralPath $testRoot) { Remove-Item -LiteralPath $testRoot }
}
