# ===============================================
# Windows Task Scheduler Setup Script
# สำหรับตั้งค่า Auto-update ข่าวสาร ทุก 6 ชั่วโมง
# ===============================================

Write-Host "🔧 กำลังตั้งค่า Task Scheduler สำหรับ Auto-update ข่าวสาร..." -ForegroundColor Cyan
Write-Host "=" * 70 -ForegroundColor Gray

# กำหนดค่า
$taskName = "RMUTP_News_AutoUpdate"
$taskDescription = "Auto-update ข่าวสารคณะวิศวกรรมศาสตร์ มทร.พระนคร ทุก 6 ชั่วโมง"
$scriptPath = "C:\xampp\htdocs\rmutp-chatbot\scripts\update_news.bat"
$phpExe = "C:\xampp\php\php.exe"
$phpScript = "C:\xampp\htdocs\rmutp-chatbot\scripts\scrape_news.php"
$logDir = "C:\xampp\htdocs\rmutp-chatbot\scripts\logs"

# ตรวจสอบว่าไฟล์มีอยู่หรือไม่
if (-not (Test-Path $scriptPath)) {
    Write-Host "❌ ไม่พบไฟล์: $scriptPath" -ForegroundColor Red
    Write-Host "กรุณาตรวจสอบ path" -ForegroundColor Yellow
    exit 1
}

# ลบ task เก่า (ถ้ามี)
$existingTask = Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue
if ($existingTask) {
    Write-Host "🗑️  ลบ task เก่า..." -ForegroundColor Yellow
    Unregister-ScheduledTask -TaskName $taskName -Confirm:$false
}

# สร้าง Action (รัน PHP script)
$action = New-ScheduledTaskAction `
    -Execute $phpExe `
    -Argument $phpScript `
    -WorkingDirectory "C:\xampp\htdocs\rmutp-chatbot\scripts"

# สร้าง Trigger หลายแบบ
Write-Host "`n⏰ กำลังสร้าง triggers..." -ForegroundColor Cyan

# Trigger 1: รันทุกวันเวลา 06:00
$trigger1 = New-ScheduledTaskTrigger -Daily -At "06:00"

# Trigger 2: รันทุกวันเวลา 12:00
$trigger2 = New-ScheduledTaskTrigger -Daily -At "12:00"

# Trigger 3: รันทุกวันเวลา 18:00
$trigger3 = New-ScheduledTaskTrigger -Daily -At "18:00"

# Trigger 4: รันทุกวันเวลา 00:00 (เที่ยงคืน)
$trigger4 = New-ScheduledTaskTrigger -Daily -At "00:00"

# สร้าง Settings
$settings = New-ScheduledTaskSettingsSet `
    -AllowStartIfOnBatteries `
    -DontStopIfGoingOnBatteries `
    -StartWhenAvailable `
    -RunOnlyIfNetworkAvailable `
    -ExecutionTimeLimit (New-TimeSpan -Minutes 30) `
    -RestartCount 3 `
    -RestartInterval (New-TimeSpan -Minutes 5)

# สร้าง Principal (รันด้วยสิทธิ์ user ปัจจุบัน)
$principal = New-ScheduledTaskPrincipal `
    -UserId "$env:USERDOMAIN\$env:USERNAME" `
    -LogonType Interactive `
    -RunLevel Highest

# ลงทะเบียน Task
Write-Host "`n📝 กำลังลงทะเบียน scheduled task..." -ForegroundColor Cyan
try {
    Register-ScheduledTask `
        -TaskName $taskName `
        -Description $taskDescription `
        -Action $action `
        -Trigger @($trigger1, $trigger2, $trigger3, $trigger4) `
        -Settings $settings `
        -Principal $principal `
        -Force | Out-Null
    
    Write-Host "✅ ตั้งค่า Task Scheduler สำเร็จ!" -ForegroundColor Green
    
    # แสดงข้อมูล task
    Write-Host "`n📋 รายละเอียด Task:" -ForegroundColor Yellow
    Write-Host "  • ชื่อ: $taskName" -ForegroundColor White
    Write-Host "  • คำอธิบาย: $taskDescription" -ForegroundColor White
    Write-Host "  • รันทุก: 06:00, 12:00, 18:00, 00:00 (ทุกวัน)" -ForegroundColor White
    Write-Host "  • สคริปต์: $phpScript" -ForegroundColor White
    Write-Host "  • Log: $logDir\scraper_[date].log" -ForegroundColor White
    
    # ทดสอบรัน
    Write-Host "`n🧪 ทดสอบรัน task..." -ForegroundColor Cyan
    Start-ScheduledTask -TaskName $taskName
    Start-Sleep -Seconds 3
    
    $taskInfo = Get-ScheduledTaskInfo -TaskName $taskName
    Write-Host "  • Last Run: $($taskInfo.LastRunTime)" -ForegroundColor White
    Write-Host "  • Last Result: $($taskInfo.LastTaskResult)" -ForegroundColor White
    Write-Host "  • Next Run: $($taskInfo.NextRunTime)" -ForegroundColor White
    
    Write-Host "`n✅ Auto-update ข่าวสารตั้งค่าเรียบร้อยแล้ว!" -ForegroundColor Green
    Write-Host "=" * 70 -ForegroundColor Gray
    
    # แสดงวิธีจัดการ task
    Write-Host "`n💡 วิธีจัดการ Task:" -ForegroundColor Yellow
    Write-Host "  • ดู task:   Get-ScheduledTask -TaskName '$taskName'" -ForegroundColor Gray
    Write-Host "  • รันทันที:  Start-ScheduledTask -TaskName '$taskName'" -ForegroundColor Gray
    Write-Host "  • หยุด task: Stop-ScheduledTask -TaskName '$taskName'" -ForegroundColor Gray
    Write-Host "  • ลบ task:   Unregister-ScheduledTask -TaskName '$taskName'" -ForegroundColor Gray
    Write-Host "  • ดู logs:   Get-Content '$logDir\scraper_*.log' -Tail 50" -ForegroundColor Gray
    
} catch {
    Write-Host "❌ เกิดข้อผิดพลาด: $_" -ForegroundColor Red
    Write-Host "กรุณาเปิด PowerShell ด้วยสิทธิ์ Administrator" -ForegroundColor Yellow
    exit 1
}

Write-Host "`n🎉 เสร็จสิ้น!" -ForegroundColor Green
