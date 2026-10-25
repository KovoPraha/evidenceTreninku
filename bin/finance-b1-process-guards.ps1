function Write-FinanceB1Utf8([string]$Path, [string]$Value) {
    [IO.File]::WriteAllText($Path, $Value, [Text.UTF8Encoding]::new($false))
}

function Assert-FinanceB1DataDirectoryUncompressed([string]$Path) {
    # Check files too: clearing a directory's flag does not decompress its files.
    $items = @((Get-Item -LiteralPath $Path -Force)) + @(Get-ChildItem -LiteralPath $Path -Force -Recurse)
    foreach ($item in $items) {
        if (($item.Attributes -band [IO.FileAttributes]::Compressed) -ne 0) {
            throw 'The isolated MariaDB data directory contains NTFS-compressed files or directories. Stop this owned database and decompress its data directory before starting MariaDB.'
        }
    }
}

function Test-FinanceB1HttpProcess($Process, [string]$LocalAddress, [string]$Php, [string]$Ini, [string]$Root, [string]$Router, [int]$Port = 18086) {
    $expected = '"{0}" -c "{1}" -S 127.0.0.1:{2} -t "{3}" "{4}"' -f $Php, $Ini, $Port, $Root, $Router
    return $LocalAddress -eq '127.0.0.1' -and $Process -and
        [string]::Equals([string]$Process.ExecutablePath, $Php, [StringComparison]::OrdinalIgnoreCase) -and
        [string]::Equals([string]$Process.CommandLine, $expected, [StringComparison]::OrdinalIgnoreCase)
}
