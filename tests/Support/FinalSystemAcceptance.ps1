param([string[]]$Only = @())
$ErrorActionPreference = 'Stop'
$appRoot = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$runtime = Join-Path $env:LOCALAPPDATA 'EvidencePavelB1/finance-b1-20261022'
$php = 'C:/xampp/php/php.exe'
$output = Join-Path $appRoot 'output/final-system'
$scripts = @(
 'FinanceB1HttpAcceptance','FinanceB1RouterHttpAcceptance','ClubFoundationHttpAcceptance',
 'MemberAdmissionHttpAcceptance','DailyCoachRegistrarHttpAcceptance','PersonLifeDocumentsHttpAcceptance',
 'TrainingClosureHttpAcceptance','SportsActivityHttpAcceptance','MedicalClearanceHttpAcceptance',
 'ClubAdministrationHttpAcceptance','ClubExpensesHttpAcceptance','ClubRewardsHttpAcceptance',
 'SeasonOperationsHttpAcceptance','ShopInventoryHttpAcceptance','ShopFulfillmentHttpAcceptance',
 'ShopReturnsHttpAcceptance','AccountingLedgerHttpAcceptance','AccountingDocumentsHttpAcceptance',
 'AccountingAdjustmentsHttpAcceptance','AccountingStockHttpAcceptance','AccountingExportHttpAcceptance',
 'ClubAccountingHttpAcceptance','ClubCutoverHttpAcceptance','WeeklyReportPrivacyHttpAcceptance','ClubOperatingMonth'
)
foreach($name in $Only) { if($name -notin $scripts) { throw 'Unknown acceptance script.' } }
if($Only.Count) { $scripts = @($scripts | Where-Object { $_ -in $Only }) }
if((Get-Content -LiteralPath (Join-Path $runtime 'owner.txt') -Raw).Trim() -ne $appRoot) { throw 'Wrong sandbox owner.' }
New-Item -ItemType Directory -Path $output -Force | Out-Null
Push-Location $appRoot
try {
 & $php -c (Join-Path $runtime 'php-web.ini') 'bin/probe-finance-b1-sandbox.php' *> (Join-Path $output 'readiness.json')
 if($LASTEXITCODE -ne 0) { throw 'Sandbox probe failed; no acceptance writes allowed.' }
 $results = @()
 foreach($name in $scripts) {
   $started = [DateTimeOffset]::Now
   & $php (Join-Path $PSScriptRoot ($name + '.php')) *> (Join-Path $output ($name + '.log'))
   $code = $LASTEXITCODE
   $row = [ordered]@{ script=$name; status=$(if($code -eq 0){'PASS'}else{'FAIL'}); exit_code=$code; started_at=$started.ToString('o'); seconds=[math]::Round(([DateTimeOffset]::Now-$started).TotalSeconds,2); log=($name+'.log') }
   $results += $row
   $results | ConvertTo-Json -Depth 5 | Set-Content -LiteralPath (Join-Path $output 'runs.json') -Encoding utf8
   Write-Output ($row.status + ' ' + $name + ' ' + $row.seconds + 's')
 }
 if(@($results | Where-Object { $_.exit_code -ne 0 }).Count) { exit 1 }
} finally { Pop-Location }
