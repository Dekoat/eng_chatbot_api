@echo off
chcp 65001 >nul
echo ======================================================================
echo RMUTP CHATBOT - AI API Server
echo ======================================================================
echo.

cd /d "%~dp0"
cd ai\api

echo กำลังเริ่ม Flask API Server...
echo API จะทำงานที่: http://localhost:5000
echo.
echo กด Ctrl+C เพื่อหยุด API Server
echo ======================================================================
echo.

python app.py
