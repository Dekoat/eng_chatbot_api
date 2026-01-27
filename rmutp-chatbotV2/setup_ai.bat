@echo off
chcp 65001 >nul
echo ======================================================================
echo RMUTP CHATBOT - ติดตั้งระบบ AI (ครั้งแรก)
echo ======================================================================
echo.

cd /d "%~dp0"

echo [1/4] ตรวจสอบ Python...
python --version
if errorlevel 1 (
    echo.
    echo ❌ ไม่พบ Python!
    echo.
    echo กรุณาติดตั้ง Python 3.8 หรือสูงกว่า
    echo ดาวน์โหลดที่: https://www.python.org/downloads/
    echo.
    echo หมายเหตุ: อย่าลืมเลือก "Add Python to PATH" ตอนติดตั้ง
    pause
    exit /b 1
)
echo ✅ พบ Python แล้ว
echo.

echo [2/4] ติดตั้ง Python Libraries...
cd ai\api
pip install -r requirements.txt
if errorlevel 1 (
    echo ❌ ติดตั้ง Libraries ไม่สำเร็จ
    pause
    exit /b 1
)
echo ✅ ติดตั้ง Libraries สำเร็จ
echo.

echo [3/4] เทรน ML Model...
cd ..\scripts
python train_model.py
if errorlevel 1 (
    echo ❌ เทรน Model ไม่สำเร็จ
    pause
    exit /b 1
)
echo ✅ เทรน Model สำเร็จ
echo.

echo [4/4] ตรวจสอบไฟล์ Model...
if exist "..\models\intent_classifier.pkl" (
    if exist "..\models\vectorizer.pkl" (
        echo ✅ พบไฟล์ Model ทั้งหมด
    ) else (
        echo ❌ ไม่พบไฟล์ vectorizer.pkl
        pause
        exit /b 1
    )
) else (
    echo ❌ ไม่พบไฟล์ intent_classifier.pkl
    pause
    exit /b 1
)

echo.
echo ======================================================================
echo ✅ ติดตั้งระบบ AI สำเร็จ!
echo ======================================================================
echo.
echo ขั้นตอนถัดไป:
echo 1. รัน API Server: ดับเบิลคลิก run_api_server.bat
echo 2. ทดสอบ PHP: ดับเบิลคลิก run_test_php.bat (ในหน้าต่างใหม่)
echo 3. เปิดเว็บไซต์: frontend/index.html
echo.
echo ======================================================================

pause
