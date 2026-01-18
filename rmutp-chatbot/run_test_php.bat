@echo off
chcp 65001 >nul
echo ======================================================================
echo RMUTP CHATBOT - ทดสอบ PHP Integration
echo ======================================================================
echo.

cd /d "%~dp0"
cd backend

echo กำลังทดสอบการเชื่อมต่อ PHP กับ Python API...
echo.
echo หมายเหตุ: API Server ต้องรันอยู่ที่ http://localhost:5000
echo ถ้ายังไม่รัน กรุณารัน run_api_server.bat ก่อน
echo ======================================================================
echo.

php ai_helper.php

echo.
echo ======================================================================
pause
