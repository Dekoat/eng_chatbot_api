@echo off
REM ========================================
REM RMUTP News Auto Update Script (PHP)
REM รันทุกวันเวลา 06:00 ผ่าน Task Scheduler
REM ========================================

cd /d c:\xampp\htdocs\rmutp-chatbot\scripts

echo ========================================
echo RMUTP Engineering News Scraper
echo %date% %time%
echo ========================================

REM รัน PHP script
c:\xampp\php\php.exe scrape_news.php

REM บันทึก log (optional)
REM c:\xampp\php\php.exe scrape_news.php >> logs\scrape_%date:~-4,4%%date:~-10,2%%date:~-7,2%.log 2>&1

echo.
echo ========================================
echo Completed at %time%
echo ========================================
pause
