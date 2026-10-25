function Write-FinanceB1Utf8([string]$Path, [string]$Value) {
    [IO.File]::WriteAllText($Path, $Value, [Text.UTF8Encoding]::new($false))
}

function Test-FinanceB1HttpProcess($Process, [string]$LocalAddress, [string]$Php, [string]$Ini, [string]$Root, [string]$Router, [int]$Port = 18086) {
    $expected = '"{0}" -c "{1}" -S 127.0.0.1:{2} -t "{3}" "{4}"' -f $Php, $Ini, $Port, $Root, $Router
    return $LocalAddress -eq '127.0.0.1' -and $Process -and
        [string]::Equals([string]$Process.ExecutablePath, $Php, [StringComparison]::OrdinalIgnoreCase) -and
        [string]::Equals([string]$Process.CommandLine, $expected, [StringComparison]::OrdinalIgnoreCase)
}
