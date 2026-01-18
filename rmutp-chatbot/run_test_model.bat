@echo off
chcp 65001 >nul
echo ======================================================================
echo RMUTP CHATBOT - ทดสอบ ML Model
echo ======================================================================
echo.

cd /d "%~dp0"
cd ai\scripts

echo กำลังโหลด Model...
echo.

python test_model.py

pause
