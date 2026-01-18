# ============================================================
# RMUTP News Scraper - Log Checker
# Check scraper execution history
# ============================================================

param(
    [int]$Days = 7
)

$logsPath = Join-Path $PSScriptRoot "logs"
$today = Get-Date

Write-Host ""
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "  News Scraper Log Checker" -ForegroundColor Cyan
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host ""

# Check if logs folder exists
if (!(Test-Path $logsPath)) {
    Write-Host "[ERROR] Logs folder not found" -ForegroundColor Red
    Write-Host "   Path: $logsPath" -ForegroundColor Gray
    exit 1
}

# Get all log files
$logFiles = Get-ChildItem $logsPath -Filter "scraper_*.log" | Sort-Object LastWriteTime -Descending

if ($logFiles.Count -eq 0) {
    Write-Host "[ERROR] No log files found" -ForegroundColor Red
    exit 1
}

Write-Host "[OK] Found $($logFiles.Count) log files" -ForegroundColor Green
Write-Host ""

# Show log files list
Write-Host "Log Files:" -ForegroundColor Yellow
Write-Host ""
$logFiles | Select-Object @{
    Name='Date'; 
    Expression={$_.Name -replace 'scraper_|\.log',''}
}, @{
    Name='Size'; 
    Expression={'{0:N1} KB' -f ($_.Length/1KB)}
}, @{
    Name='LastModified'; 
    Expression={$_.LastWriteTime.ToString('yyyy-MM-dd HH:mm:ss')}
}, @{
    Name='Age';
    Expression={
        $age = (Get-Date) - $_.LastWriteTime
        if ($age.Days -eq 0) {
            "$($age.Hours)h $($age.Minutes)m ago"
        } else {
            "$($age.Days)d $($age.Hours)h ago"
        }
    }
} | Format-Table -AutoSize

Write-Host ""
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "  Summary (Last $Days days)" -ForegroundColor Cyan
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host ""

# Analyze logs from last N days
$recentLogs = $logFiles | Where-Object { 
    $_.LastWriteTime -ge $today.AddDays(-$Days) 
}

$daysWithLogs = @{}
$totalRuns = 0
$totalNewsAdded = 0
$totalNewsDuplicate = 0

foreach ($log in $recentLogs) {
    $date = ($log.Name -replace 'scraper_|\.log','')
    
    # Count runs
    $content = Get-Content $log.FullName -Encoding UTF8 -ErrorAction SilentlyContinue
    if ($content) {
        $runs = ($content | Select-String "รัน|เชื่อมต่อฐานข้อมูล").Count
        $added = ($content | Select-String "เพิ่มข่าว:|เพิ่ม:").Count
        $duplicate = ($content | Select-String "ข่าวซ้ำ").Count
        
        $totalRuns += $runs
        $totalNewsAdded += $added
        $totalNewsDuplicate += $duplicate
        
        if (!$daysWithLogs.ContainsKey($date)) {
            $daysWithLogs[$date] = @{
                Runs = 0
                Added = 0
                Duplicate = 0
            }
        }
        
        $daysWithLogs[$date].Runs += $runs
        $daysWithLogs[$date].Added += $added
        $daysWithLogs[$date].Duplicate += $duplicate
    }
}

# Show daily summary
Write-Host "Daily Report:" -ForegroundColor Yellow
Write-Host ""

$daysWithLogs.GetEnumerator() | Sort-Object Name -Descending | ForEach-Object {
    $dateStr = $_.Key
    $data = $_.Value
    
    Write-Host "  $dateStr" -ForegroundColor White
    Write-Host "     - Runs: $($data.Runs) times" -ForegroundColor Gray
    Write-Host "     - New news: $($data.Added) items" -ForegroundColor Green
    Write-Host "     - Duplicates: $($data.Duplicate) items" -ForegroundColor Yellow
    Write-Host ""
}

Write-Host ""
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "  Overall Summary ($Days days)" -ForegroundColor Cyan
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host ""

Write-Host "  - Days with logs:    $($daysWithLogs.Count) / $Days days" -ForegroundColor $(if($daysWithLogs.Count -eq $Days){"Green"}else{"Yellow"})
Write-Host "  - Total runs:        $totalRuns times" -ForegroundColor White
Write-Host "  - New news:          $totalNewsAdded items" -ForegroundColor Green
Write-Host "  - Duplicates:        $totalNewsDuplicate items" -ForegroundColor Yellow

# Calculate average
if ($daysWithLogs.Count -gt 0) {
    $avgRunsPerDay = [math]::Round($totalRuns / $daysWithLogs.Count, 1)
    Write-Host "  - Average/day:       $avgRunsPerDay runs" -ForegroundColor Cyan
}

Write-Host ""

# Check if scraper runs regularly
if ($daysWithLogs.Count -ge $Days) {
    Write-Host "[OK] Scraper runs regularly ($Days consecutive days)" -ForegroundColor Green
} else {
    $missingDays = $Days - $daysWithLogs.Count
    Write-Host "[WARNING] Missing $missingDays days of logs" -ForegroundColor Yellow
    Write-Host "   Task Scheduler might not be working" -ForegroundColor Gray
}

Write-Host ""

# Show latest log
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "  Latest Log (first 15 lines)" -ForegroundColor Cyan
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host ""

$latestLog = $logFiles | Select-Object -First 1
if ($latestLog) {
    Write-Host "File: $($latestLog.Name)" -ForegroundColor White
    Write-Host "Time: $($latestLog.LastWriteTime.ToString('yyyy-MM-dd HH:mm:ss'))" -ForegroundColor Gray
    Write-Host ""
    
    Get-Content $latestLog.FullName -Encoding UTF8 | Select-Object -First 15 | ForEach-Object {
        if ($_ -match "ERROR") {
            Write-Host "   $_" -ForegroundColor Red
        } elseif ($_ -match "WARNING") {
            Write-Host "   $_" -ForegroundColor Yellow
        } elseif ($_ -match "เพิ่ม") {
            Write-Host "   $_" -ForegroundColor Green
        } else {
            Write-Host "   $_" -ForegroundColor Gray
        }
    }
}

Write-Host ""
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host ""
