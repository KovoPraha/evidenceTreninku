# P15 S0: content-free, read-only Windows inventory. Does not inspect Riki.
[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$result = [ordered]@{
    schema_version = 1
    observed_at = [DateTime]::UtcNow.ToString('o')
    target_station_confirmed = $false
    mode = 'read_only_no_riki_access'
    os = $null
    capacity = $null
    commands_available = [ordered]@{}
    scheduler_status = 'unknown'
    kis_root_exists = $null
    disk_protection = 'not_checked'
    account_isolation = 'not_checked'
    network = 'not_checked'
    browser_auth = 'not_checked'
    reboot_headless = 'not_run'
    errors = @()
}
try {
    $os = Get-CimInstance -ClassName Win32_OperatingSystem
    $result.os = [ordered]@{
        caption = $os.Caption
        version = $os.Version
        build = $os.BuildNumber
        architecture = $os.OSArchitecture
    }
    $cpuCount = (Get-CimInstance -ClassName Win32_Processor |
        Measure-Object -Property NumberOfLogicalProcessors -Sum).Sum
    $drive = Get-PSDrive -Name C -PSProvider FileSystem
    $result.capacity = [ordered]@{
        logical_cpu_count = $cpuCount
        ram_total_gib = [math]::Round($os.TotalVisibleMemorySize / 1MB, 2)
        ram_free_gib = [math]::Round($os.FreePhysicalMemory / 1MB, 2)
        c_free_gib = [math]::Round($drive.Free / 1GB, 2)
    }
} catch {
    # Exception messages may contain private paths; do not export them.
    $result.errors += 'os_capacity_unavailable'
}
foreach ($commandName in @('node.exe', 'npm.cmd', 'git.exe', 'wsl.exe', 'docker.exe')) {
    $command = Get-Command -Name $commandName -CommandType Application -ErrorAction SilentlyContinue
    $result.commands_available[$commandName] = [bool]$command
}
try {
    $result.scheduler_status = (Get-Service -Name Schedule).Status.ToString()
} catch {
    $result.errors += 'scheduler_status_unavailable'
}
try {
    # Existence only; never list contents or read a pre-existing directory.
    $result.kis_root_exists = Test-Path -LiteralPath 'C:\ProgramData\KISSync'
} catch {
    $result.errors += 'kis_path_existence_unavailable'
}
$result | ConvertTo-Json -Depth 5
