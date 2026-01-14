@echo off
REM RMUTP News Scraper - Manual Runner
REM Run: run_scraper.bat

echo ========================================
echo RMUTP News Auto-Update
echo ========================================
echo.

C:\xampp\php\php.exe C:\xampp\htdocs\rmutp-chatbot\scripts\scrape_news.php

echo.
echo ========================================
echo Done! Check logs folder for details
echo ========================================
pause
