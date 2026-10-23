$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot '..\..\bin\finance-b1-process-guards.ps1')
$php='C:\xampp\php\php.exe';$ini='C:\owned\php-web.ini';$root='C:\owned\app';$router='C:\owned\app\bin\finance-b1-router.php'
$command='"{0}" -c "{1}" -S 127.0.0.1:18086 -t "{2}" "{3}"' -f $php,$ini,$root,$router
$process=[pscustomobject]@{ExecutablePath=$php;CommandLine=$command}
if(-not(Test-FinanceB1HttpProcess $process '127.0.0.1' $php $ini $root $router)){throw 'Owned process rejected'}
foreach($change in @(@{ExecutablePath='C:\foreign\php.exe';CommandLine=$command},@{ExecutablePath=$php;CommandLine=$command.Replace($ini,'C:\foreign\php.ini')},@{ExecutablePath=$php;CommandLine=$command+' -d allow_url_fopen=1'},@{ExecutablePath=$php;CommandLine=$command.Replace('-S 127.0.0.1','-S 0.0.0.0')},@{ExecutablePath=$php;CommandLine=$command.Replace('-t "C:\owned\app"','-t "C:\foreign"')})){
    if(Test-FinanceB1HttpProcess ([pscustomobject]$change) '127.0.0.1' $php $ini $root $router){throw 'Foreign process accepted'}
}
$file=Join-Path ([IO.Path]::GetTempPath()) ('b1-bom-'+[guid]::NewGuid().ToString('N')+'.json')
try{Write-FinanceB1Utf8 $file '{"synthetic_only":true}';$bytes=[IO.File]::ReadAllBytes($file);if($bytes.Length-lt1-or$bytes[0]-ne123){throw 'Machine JSON has BOM'};if(-not([IO.File]::ReadAllText($file)|ConvertFrom-Json).synthetic_only){throw 'JSON changed'}}finally{if(Test-Path -LiteralPath $file){Remove-Item -LiteralPath $file}}
@{status='PASS';checks=7;real_processes_stopped=0;meaning='Exact process identity and BOM-free machine files'}|ConvertTo-Json -Compress
