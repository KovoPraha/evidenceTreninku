param([Parameter(Mandatory)][string]$Output)
$ErrorActionPreference='Stop'
$root=(Resolve-Path -LiteralPath "$PSScriptRoot/../..").Path
$outputPath=(Resolve-Path -LiteralPath $Output).Path
if ($root.Replace('\','/') -ne 'C:/Users/Marek/Documents/Codex/evidencePavel-medical-planner-scale-20261107') {
 throw 'Only the owned dated checkout is allowed.'
}
Set-Location -LiteralPath $root
function Invoke-Profile([string]$Action,[string]$Level) {
 & C:/xampp/php/php.exe -d memory_limit=1024M tests/Support/MedicalPlannerScaleProfile.php $Action $outputPath $Level
 if ($LASTEXITCODE -ne 0) { throw "Scale profile failed: $Action / $Level" }
}
$levels=@('baseline','medical50k','medical100k','planner260','planner520','roster200','roster1000')
foreach ($level in $levels) {
 if (!(Test-Path -LiteralPath "$outputPath/$level/seed.json")) { Invoke-Profile 'seed' $level }
 if (Test-Path -LiteralPath "$outputPath/$level/http.json") { throw 'Refusing to silently overwrite prior timing.' }
 & "$PSScriptRoot/MedicalPlannerScaleRuntime.ps1" -Action StartWeb -Output $outputPath -Root $root -Phase "$level-plain"
 try {
  if ($level -eq 'baseline' -and !(Test-Path -LiteralPath "$outputPath/web-select-only.json")) {
   & C:/xampp/php/php.exe tests/Support/FullHttpReadProfile.php --login $outputPath
   if ($LASTEXITCODE -ne 0) { throw 'Standard form login failed.' }
   Invoke-Profile 'restrict' $level
  }
  Invoke-Profile 'verify' $level
  Invoke-Profile 'project' $level
  Invoke-Profile 'measure' $level
 } finally {
  & "$PSScriptRoot/MedicalPlannerScaleRuntime.ps1" -Action StopWeb -Output $outputPath
 }
 & "$PSScriptRoot/MedicalPlannerScaleRuntime.ps1" -Action StartWeb -Output $outputPath -Root $root -Phase "$level-diag" -Instrument
 try { Invoke-Profile 'diagnose' $level }
 finally { & "$PSScriptRoot/MedicalPlannerScaleRuntime.ps1" -Action StopWeb -Output $outputPath }
 Write-Output "LEVEL $level COMPLETE"
}
