@echo off
chcp 65001 >nul
echo ======================================================================
echo RMUTP CHATBOT - เทรน ML Model
echo ======================================================================
echo.

cd /d "%~dp0"
cd ai\scripts

echo [1/3] ตรวจสอบ Python...
python --version
if errorlevel 1 (
    echo ❌ ไม่พบ Python! กรุณาติดตั้ง Python ก่อน
    echo ดาวน์โหลดที่: https://www.python.org/downloads/
    pause
    exit /b 1
)
echo ✅ พบ Python แล้ว
echo.

echo [2/3] ตรวจสอบ Dependencies...
python -c "import pythainlp, sklearn, flask" 2>nul
if errorlevel 1 (
    echo ⚠️  ยังไม่ได้ติดตั้ง Libraries
    echo กำลังติดตั้ง...
    cd ..\api
    pip install -r requirements.txt
    if errorlevel 1 (
        echo ❌ ติดตั้ง Libraries ไม่สำเร็จ
        pause
        exit /b 1
    )
    cd ..\scripts
)
echo ✅ Dependencies พร้อมแล้ว
echo.

echo [3/3] เริ่มเทรน Model...
echo ======================================================================
echo.

python train_model.py

echo.
echo ======================================================================
if errorlevel 1 (
    echo ❌ เทรน Model ไม่สำเร็จ
    echo กรุณาตรวจสอบข้อผิดพลาดด้านบน
) else (
    echo ✅ เทรน Model สำเร็จแล้ว!
    echo.
    echo ขั้นตอนถัดไป:
    echo 1. ทดสอบ Model: run_test_model.bat
    echo 2. รัน API Server: run_api_server.bat
)
echo ======================================================================

pause
