param([Parameter(Mandatory)][string]$Output)
$ErrorActionPreference='Stop'
$root=(Resolve-Path -LiteralPath "$PSScriptRoot/../..").Path
$outputPath=(Resolve-Path -LiteralPath $Output).Path
if (!(Test-Path -LiteralPath "$outputPath/environment.json")) { throw 'Verified current application fingerprints required before measurement.' }
Set-Location -LiteralPath $root
function Invoke-Current([string]$Action,[string]$Level) {
 & C:/xampp/php/php.exe -d memory_limit=1024M tests/Support/MedicalPlannerScaleProfile.php $Action $outputPath $Level
 if ($LASTEXITCODE -ne 0) { throw "Current planner failed: $Action / $Level" }
}
function Wait-CurrentWeb {
 $deadline=[DateTime]::UtcNow.AddSeconds(30)
 do {
  $socket=[System.Net.Sockets.TcpClient]::new()
  try {
   $task=$socket.ConnectAsync('127.0.0.1',18115)
   if ($task.Wait(500) -and $socket.Connected) { return }
  } catch { } finally { $socket.Dispose() }
  Start-Sleep -Milliseconds 100
 } while ([DateTime]::UtcNow -lt $deadline)
 throw 'Owned web readiness timeout before any measured request.'
}
foreach ($level in @('planner520','roster1000')) {
 if (!(Test-Path -LiteralPath "$outputPath/$level/seed.json")) { Invoke-Current 'seed' $level }
 if (Test-Path -LiteralPath "$outputPath/$level/http.json") { throw 'Refusing to overwrite existing current timings.' }
 & "$PSScriptRoot/MedicalPlannerScaleRuntime.ps1" -Action StartWeb -Output $outputPath -Root $root -Phase "current-$level-plain"
 try {
  Wait-CurrentWeb
  if ($level -eq 'planner520') {
   & C:/xampp/php/php.exe tests/Support/FullHttpReadProfile.php --login $outputPath
   if ($LASTEXITCODE -ne 0) { throw 'Standard login failed.' }
   Invoke-Current 'restrict' $level
  }
  Invoke-Current 'verify' $level
  Invoke-Current 'project' $level
  Invoke-Current 'measure' $level
 } finally {
  & "$PSScriptRoot/MedicalPlannerScaleRuntime.ps1" -Action StopWeb -Output $outputPath
 }
 & "$PSScriptRoot/MedicalPlannerScaleRuntime.ps1" -Action StartWeb -Output $outputPath -Root $root -Phase "current-$level-diag" -Instrument
 try { Wait-CurrentWeb; Invoke-Current 'diagnose' $level }
 finally { & "$PSScriptRoot/MedicalPlannerScaleRuntime.ps1" -Action StopWeb -Output $outputPath }
 Write-Output "CURRENT LEVEL $level COMPLETE"
}
