param(
 [Parameter(Mandatory)][ValidateSet('StartDatabase','StartWeb','StopWeb','StopDatabase')][string]$Action,
 [Parameter(Mandatory)][string]$Output,
 [string]$Root,
 [ValidatePattern('^[a-z0-9-]{3,40}$')][string]$Phase='baseline-plain',
 [switch]$Instrument
)
$ErrorActionPreference='Stop'
$outputPath=(Resolve-Path -LiteralPath $Output).Path
$runtimeFile=Join-Path $outputPath 'runtime.json'
$owned=Get-Content -LiteralPath $runtimeFile | ConvertFrom-Json
if (!$owned.synthetic_only -or $owned.runtime -notmatch '^C:/xampp/tmp/kis-medical-planner-scale-[0-9]{8}-[a-f0-9]{8}$' -or
 $owned.datadir -ne "$($owned.runtime)/data" -or $owned.port -ne 33479 -or $owned.http_port -ne 18115) { throw 'Unexpected owned runtime.' }
function Save-Owned { $owned | ConvertTo-Json -Depth 10 | Set-Content -LiteralPath $runtimeFile -Encoding utf8 }
function Set-Owned([string]$Name,$Value) { $owned | Add-Member -NotePropertyName $Name -NotePropertyValue $Value -Force }
function Assert-Process([int]$ProcessId,[string]$Name,[string]$Marker) {
 $p=Get-CimInstance Win32_Process -Filter "ProcessId=$ProcessId"
 if (!$p -or $p.Name -ne $Name -or !$p.CommandLine.Contains($Marker)) { throw 'Owned process identity differs.' }
 return $p
}
switch ($Action) {
 'StartDatabase' {
  if ($owned.db_process_id) { throw 'Database process already recorded.' }
  $p=Start-Process -FilePath C:/xampp/mysql/bin/mysqld.exe -ArgumentList @("--defaults-file=$($owned.runtime)/my.ini",'--console') -WindowStyle Hidden -PassThru -RedirectStandardOutput "$outputPath/mariadb-stdout.log" -RedirectStandardError "$outputPath/mariadb-stderr.log"
  Set-Owned 'db_process_id' $p.Id;Save-Owned
  Write-Output "Started owned database PID $($p.Id)."
 }
 'StartWeb' {
  if ($owned.web_process_id) { throw 'Stop recorded web process before changing variant.' }
  $webRoot=(Resolve-Path -LiteralPath $Root).Path.Replace('\','/')
  if ($webRoot -notmatch '^C:/Users/Marek/Documents/Codex/evidencePavel-medical-planner-scale-20261107$' -or
   (Get-FileHash -LiteralPath "$webRoot/config.php" -Algorithm SHA256).Hash.ToLowerInvariant() -ne $owned.config_sha256) { throw 'Unexpected isolated web root/config.' }
  $argsList=@('-d',"session.save_path=$($owned.runtime)/sessions",'-d','session.gc_maxlifetime=14400','-d','date.timezone=Europe/Prague','-d','display_errors=1','-d','log_errors=1')
  if ($Instrument) {
   $env:EVIDENCE_PERFORMANCE_CONFIRM='SYNTHETIC-ONLY'
   $env:P11_HTTP_METRICS="$($owned.runtime)/metrics"
   $argsList+=@('-d',"auto_prepend_file=$webRoot/tests/Support/FullHttpReadInstrument.php")
  } else {
   Remove-Item -LiteralPath Env:P11_HTTP_METRICS -ErrorAction SilentlyContinue
   Remove-Item -LiteralPath Env:EVIDENCE_PERFORMANCE_CONFIRM -ErrorAction SilentlyContinue
  }
  $argsList+=@('-S','127.0.0.1:18115','-t',$webRoot)
  $p=Start-Process -FilePath C:/xampp/php/php.exe -WorkingDirectory $webRoot -ArgumentList $argsList -WindowStyle Hidden -PassThru -RedirectStandardOutput "$outputPath/web-$Phase-stdout.log" -RedirectStandardError "$outputPath/web-$Phase-stderr.log"
  Set-Owned 'web_process_id' $p.Id;Set-Owned 'web_root' $webRoot;Save-Owned
  Write-Output "Started owned $Phase web PID $($p.Id)."
 }
 'StopWeb' {
  $p=Assert-Process $owned.web_process_id 'php.exe' "$($owned.runtime)/sessions"
  if (!$p.CommandLine.Contains('127.0.0.1:18115') -or !$p.CommandLine.Contains($owned.web_root)) { throw 'Web command differs.' }
  Stop-Process -Id $p.ProcessId
  Set-Owned 'web_process_id' $null;Save-Owned
  Write-Output 'Stopped exact owned web process.'
 }
 'StopDatabase' {
  $p=Assert-Process $owned.db_process_id 'mysqld.exe' "$($owned.runtime)/my.ini"
  & C:/xampp/mysql/bin/mysqladmin.exe --protocol=TCP --host=127.0.0.1 --port=33479 --user=root shutdown
  if ($LASTEXITCODE -ne 0) { throw 'Owned database shutdown failed.' }
  Set-Owned 'db_process_id' $null;Save-Owned
  Write-Output 'Stopped exact owned database.'
 }
}
