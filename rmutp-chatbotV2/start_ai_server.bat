@echo off
title RMUTP Chatbot AI Server
color 0A

echo ======================================
echo   RMUTP Chatbot AI Server
echo   Flask API - Intent Classification
echo ======================================
echo.

cd /d "%~dp0ai\api"

REM Check if Python is available
python --version >nul 2>&1
if errorlevel 1 (
    echo [ERROR] Python not found! Please install Python 3.8+
    pause
    exit /b 1
)

REM Check if required packages are installed
echo [INFO] Checking dependencies...
pip show flask >nul 2>&1
if errorlevel 1 (
    echo [INFO] Installing required packages...
    pip install -r requirements.txt
)

echo.
echo [INFO] Starting AI Server on http://localhost:5000
echo [INFO] Press Ctrl+C to stop the server
echo.
echo ======================================
echo.

REM Run Flask app (threaded mode for better performance)
python -c "from app import app; app.run(host='0.0.0.0', port=5000, debug=False, threaded=True)"

pause
