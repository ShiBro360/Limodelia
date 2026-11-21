$ErrorActionPreference = 'Continue'

$projectRoot = Split-Path -Parent $PSScriptRoot
$phpPath = 'D:\XAMPPL\php\php.exe'
$botScript = Join-Path $PSScriptRoot 'telegram_attendance_bot.php'
$logDirectory = Join-Path $projectRoot 'storage\logs'
$logFile = Join-Path $logDirectory 'telegram_bot.log'

New-Item -ItemType Directory -Path $logDirectory -Force | Out-Null

$mutex = New-Object System.Threading.Mutex($false, 'Local\LimodeliaTelegramBotSupervisor')
$hasMutex = $false

try {
    try {
        $hasMutex = $mutex.WaitOne(0)
    } catch [System.Threading.AbandonedMutexException] {
        $hasMutex = $true
    }

    if (-not $hasMutex) {
        exit 0
    }

    Add-Content -LiteralPath $logFile -Value "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss') Supervisor started."

    while ($true) {
        if (-not (Test-Path -LiteralPath $phpPath) -or -not (Test-Path -LiteralPath $botScript)) {
            Add-Content -LiteralPath $logFile -Value "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss') PHP or bot script is missing. Retrying."
            Start-Sleep -Seconds 60
            continue
        }

        try {
            & $phpPath $botScript 2>&1 | ForEach-Object {
                Add-Content -LiteralPath $logFile -Value $_ -Encoding UTF8
            }
            $exitCode = $LASTEXITCODE
            Add-Content -LiteralPath $logFile -Value "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss') Bot stopped with exit code $exitCode. Restarting."
        } catch {
            Add-Content -LiteralPath $logFile -Value "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss') Bot start failed: $($_.Exception.Message)"
        }

        Start-Sleep -Seconds 15
    }
} finally {
    if ($hasMutex) {
        $mutex.ReleaseMutex()
    }
    $mutex.Dispose()
}
