# คู่มือเริ่มต้นใช้งาน AI - ทีละขั้นตอน

สำหรับผู้ที่ไม่เคยใช้งาน Python ML มาก่อน

---

## ขั้นตอนที่ 1: ตรวจสอบและติดตั้ง Python

### 1.1 ตรวจสอบว่ามี Python หรือยัง

เปิด PowerShell หรือ Command Prompt แล้วพิมพ์:

```powershell
python --version
```

**ควรเห็น:**
```
Python 3.8.x หรือ 3.9.x หรือ 3.10.x หรือ 3.11.x
```

### 1.2 ถ้ายังไม่มี Python → ติดตั้ง

**วิธีที่ 1: ดาวน์โหลดจาก python.org**

1. ไปที่: https://www.python.org/downloads/
2. ดาวน์โหลด Python 3.11.x (แนะนำ)
3. ติดตั้ง โดย **ติ๊กถูก "Add Python to PATH"**
4. คลิก Install Now

**วิธีที่ 2: ใช้ Windows Store**

1. เปิด Microsoft Store
2. ค้นหา "Python 3.11"
3. คลิก Install

### 1.3 ทดสอบอีกครั้ง

```powershell
python --version
pip --version
```

**ควรเห็น:**
```
Python 3.11.x
pip 23.x.x from ...
```

✅ **ถ้าเห็นแบบนี้ = พร้อมแล้ว!**

---

## ขั้นตอนที่ 2: ติดตั้ง Python Libraries

### 2.1 เปิด PowerShell/CMD ไปที่โฟลเดอร์โปรเจค

```powershell
cd c:\xampp\htdocs\rmutp-chatbot
```

### 2.2 เข้าไปในโฟลเดอร์ AI

```powershell
cd ai\api
```

### 2.3 ติดตั้ง Dependencies

```powershell
pip install -r requirements.txt
```

**จะเห็นการติดตั้ง:**
```
Collecting Flask==3.0.0
Collecting flask-cors==4.0.0
Collecting pythainlp==4.0.2
Collecting scikit-learn==1.3.0
...
Installing collected packages: ...
Successfully installed ...
```

⏱️ **ใช้เวลาประมาณ 2-5 นาที** (ขึ้นอยู่กับความเร็วอินเทอร์เน็ต)

### 2.4 ตรวจสอบว่าติดตั้งสำเร็จ

```powershell
pip list
```

**ควรเห็น:**
```
Flask             3.0.0
flask-cors        4.0.0
pythainlp         4.0.2
scikit-learn      1.3.0
pandas            2.0.0
...
```

✅ **ถ้าเห็นครบ = พร้อมขั้นตอนต่อไป!**

---

## ขั้นตอนที่ 3: เทรน ML Model (ขั้นตอนสำคัญ!)

### 3.1 ไปที่โฟลเดอร์ scripts

```powershell
cd ..\scripts
```

หรือถ้าอยู่ที่ root:
```powershell
cd ai\scripts
```

### 3.2 รันคำสั่งเทรน Model

```powershell
python train_model.py
```

### 3.3 ดูผลลัพธ์

**ควรเห็นแบบนี้:**

```
======================================================================
RMUTP CHATBOT - INTENT CLASSIFIER TRAINING
======================================================================

[1/4] Loading training data...
Loaded 100 training examples
Intents: ['ask_staff' 'ask_tuition' 'ask_admission' 'ask_loan' 'ask_department'
 'ask_facility' 'ask_grade' 'ask_news' 'ask_contact' 'other']

Intent distribution:
ask_tuition       10
ask_staff         10
ask_admission     10
ask_loan          10
ask_department    10
ask_facility      10
ask_grade         10
ask_news          10
ask_contact       10
other             10

Training set: 80 examples
Test set: 20 examples

Transforming text to TF-IDF features...
Training Naive Bayes model...

==================================================
TRAINING COMPLETED
==================================================
Accuracy: 75.00% หรือสูงกว่า
✅ SUCCESS: Accuracy meets target (≥75%)

--------------------------------------------------
Classification Report:
--------------------------------------------------
              precision    recall  f1-score   support

   ask_staff       0.80      0.85      0.82         2
 ask_tuition       1.00      1.00      1.00         2
ask_admission       0.75      0.75      0.75         2
    ask_loan       0.80      0.80      0.80         2
...

✅ Model saved:
   - ..\models\intent_classifier.pkl
   - ..\models\vectorizer.pkl

[4/4] Testing with sample questions...
======================================================================

Question: ค่าเทอมเท่าไหร่
Intent: ask_tuition
Confidence: 92.45%
Alternatives: ask_loan (5.23%)

Question: อาจารย์สาขาคอมพิวเตอร์
Intent: ask_staff
Confidence: 88.67%
Alternatives: ask_department (8.12%)

...

======================================================================
TRAINING COMPLETE!
======================================================================
```

### 3.4 เช็คว่ามีไฟล์ Model แล้ว

```powershell
dir ..\models
```

**ควรเห็น:**
```
intent_classifier.pkl
vectorizer.pkl
```

✅ **ถ้ามี 2 ไฟล์นี้ = เทรนสำเร็จแล้ว!**

### 🎯 เป้าหมาย: Accuracy ≥ 75%

- ✅ **75-80%** = ดีมาก เริ่มใช้งานได้
- ✅ **80-85%** = ยอดเยี่ยม
- ⚠️ **< 75%** = ควรเพิ่มข้อมูลเทรน

---

## ขั้นตอนที่ 4: ทดสอบ Model (ไม่บังคับ แต่แนะนำ)

### 4.1 รันโปรแกรมทดสอบ

```powershell
python test_model.py
```

### 4.2 ลองพิมพ์คำถาม

```
======================================================================
INTENT CLASSIFIER - INTERACTIVE TESTING
======================================================================

Model loaded successfully!
Type your question (or 'quit' to exit)
----------------------------------------------------------------------

คำถาม: ค่าเทอมเท่าไหร่

==================================================
Intent: ask_tuition
Confidence: 92.45%
Status: ✅ HIGH CONFIDENCE (Use Rule-based)

Alternatives:
  - ask_loan: 5.23%
  - other: 2.32%

คำถาม: อาจารย์สาขาคอม

==================================================
Intent: ask_staff
Confidence: 88.67%
Status: ✅ HIGH CONFIDENCE (Use Rule-based)

คำถาม: quit

Goodbye!
```

### 4.3 ทดสอบคำถามต่างๆ

ลองพิมพ์:
- "สมัครเรียนยังไง"
- "กยศ คืออะไร"
- "มีห้องแล็บไหม"
- "ติดต่อคณะ"

ดูว่า Intent ถูกต้องไหม และ Confidence เท่าไหร่

✅ **ถ้า Confidence ส่วนใหญ่ ≥ 80% = Model ดี!**

---

## ขั้นตอนที่ 5: รัน API Server

### 5.1 ไปที่โฟลเดอร์ API

```powershell
cd ..\api
```

หรือถ้าอยู่ที่ root:
```powershell
cd ai\api
```

### 5.2 รัน Flask Server

```powershell
python app.py
```

### 5.3 ควรเห็น

```
======================================================================
RMUTP CHATBOT - INTENT CLASSIFIER API
======================================================================

Starting Flask API server...
API will be available at: http://localhost:5000

Endpoints:
  - GET  /         : API information
  - GET  /health   : Health check
  - POST /predict  : Predict single question
  - POST /batch_predict : Predict multiple questions

Press Ctrl+C to stop
======================================================================

✅ Model loaded successfully
 * Serving Flask app 'app'
 * Debug mode: on
WARNING: This is a development server. Do not use it in a production deployment.
 * Running on all addresses (0.0.0.0)
 * Running on http://127.0.0.1:5000
 * Running on http://192.168.x.x:5000
Press CTRL+C to quit
```

✅ **เห็นแบบนี้ = API พร้อมใช้งาน!**

⚠️ **อย่าปิด Terminal นี้ ให้รันทิ้งไว้**

### 5.4 ทดสอบ API ด้วย Browser

เปิดเว็บเบราว์เซอร์ แล้วไปที่:

```
http://localhost:5000
```

**ควรเห็น JSON:**
```json
{
  "service": "RMUTP Chatbot Intent Classifier API",
  "version": "1.0.0",
  "status": "running",
  "endpoints": {
    "predict": "/predict (POST)",
    "health": "/health (GET)"
  }
}
```

ลองเช็ค Health:
```
http://localhost:5000/health
```

**ควรเห็น:**
```json
{
  "status": "healthy",
  "model_loaded": true
}
```

✅ **API ทำงานปกติ!**

---

## ขั้นตอนที่ 6: ทดสอบเรียก API จาก PHP

### 6.1 เปิด Terminal ใหม่ (อันเดิมยังต้องรัน API อยู่)

```powershell
cd c:\xampp\htdocs\rmutp-chatbot\backend
```

### 6.2 รัน PHP Test Script

```powershell
php ai_helper.php
```

### 6.3 ควรเห็น

```
✅ AI API is available

Question: ค่าเทอมเท่าไหร่
Intent: ask_tuition
Confidence: 92.45%
Strategy: rule

Question: อาจารย์สาขาคอมพิวเตอร์
Intent: ask_staff
Confidence: 88.67%
Strategy: rule

Question: สมัคร TCAS
Intent: ask_admission
Confidence: 85.23%
Strategy: rule
```

✅ **PHP เรียก API ได้สำเร็จ!**

---

## ขั้นตอนที่ 7: ทดสอบด้วย PowerShell (ทางเลือก)

### 7.1 ทดสอบด้วย curl

```powershell
$body = @{
    question = "ค่าเทอมเท่าไหร่"
} | ConvertTo-Json

Invoke-RestMethod -Uri "http://localhost:5000/predict" `
    -Method Post `
    -Body $body `
    -ContentType "application/json"
```

**ควรเห็น:**
```
intent               : ask_tuition
confidence           : 0.9245
alternatives         : @{...}
processing_time_ms   : 15.23
```

---

## สรุป: ระบบพร้อมใช้งาน! 🎉

### ✅ สิ่งที่ทำสำเร็จ:

1. ✅ ติดตั้ง Python และ Libraries
2. ✅ เทรน ML Model (Accuracy ≥ 75%)
3. ✅ ทดสอบ Model
4. ✅ รัน Flask API Server
5. ✅ ทดสอบเรียก API จาก PHP

### 🚀 ขั้นตอนถัดไป:

1. **เชื่อมต่อกับ chatbot.php จริง**
   - อัปเดต `backend/chatbot.php`
   - เพิ่ม Hybrid Logic
   
2. **ทดสอบจาก Frontend**
   - เปิด `frontend/index.html`
   - ลองถามคำถาม
   
3. **Monitor และปรับปรุง**
   - ดู Logs
   - เก็บ Feedback
   - ปรับปรุง Model

---

## Troubleshooting (แก้ปัญหาที่พบบ่อย)

### ❌ ปัญหา: python command not found

**แก้:**
1. ติดตั้ง Python ใหม่
2. เช็ค "Add Python to PATH" ตอนติดตั้ง
3. รีสตาร์ท Terminal

### ❌ ปัญหา: pip install ติด SSL Error

**แก้:**
```powershell
pip install --trusted-host pypi.org --trusted-host files.pythonhosted.org -r requirements.txt
```

### ❌ ปัญหา: Model ไม่เจอ

**แก้:**
```powershell
cd ai\scripts
python train_model.py
```

ตรวจสอบว่ามีไฟล์ใน `ai\models\`

### ❌ ปัญหา: API Port 5000 ถูกใช้งานแล้ว

**แก้:**
1. หาโปรแกรมที่ใช้ Port 5000:
```powershell
netstat -ano | findstr :5000
```

2. ปิดโปรแกรมนั้น หรือเปลี่ยน Port ใน `app.py`:
```python
app.run(host='0.0.0.0', port=5001, debug=True)
```

### ❌ ปัญหา: PHP ไม่สามารถเรียก API

**เช็ค:**
1. API รันอยู่ไหม? (เปิด http://localhost:5000)
2. XAMPP เปิดอยู่ไหม?
3. PHP curl extension เปิดไหม? (เช็ค `php.ini`)

---

## การรัน API อัตโนมัติเมื่อเปิดเครื่อง (ขั้นสูง)

### วิธีที่ 1: ใช้ Task Scheduler

1. เปิด Task Scheduler
2. Create Task
3. Triggers: At startup
4. Actions: 
   - Program: `python`
   - Arguments: `c:\xampp\htdocs\rmutp-chatbot\ai\api\app.py`
   - Start in: `c:\xampp\htdocs\rmutp-chatbot\ai\api`

### วิธีที่ 2: สร้าง Batch File

สร้างไฟล์ `start_ai_api.bat`:

```batch
@echo off
cd c:\xampp\htdocs\rmutp-chatbot\ai\api
python app.py
pause
```

ดับเบิลคลิกเพื่อรัน

---

## คำสั่งที่ใช้บ่อย (Quick Reference)

```powershell
# ไปที่โฟลเดอร์โปรเจค
cd c:\xampp\htdocs\rmutp-chatbot

# เทรน Model ใหม่
cd ai\scripts
python train_model.py

# ทดสอบ Model
python test_model.py

# รัน API
cd ..\api
python app.py

# ทดสอบ PHP
cd ..\..\backend
php ai_helper.php

# ดู Logs
cd ..\ai\logs
type predictions.log
type api.log
```

---

## ต้องการความช่วยเหลือ?

1. เช็คไฟล์ Log:
   - `ai/logs/api.log`
   - `ai/logs/predictions.log`

2. ดูเอกสาร:
   - `docs/PHASE1_SIMPLE_ML.md`
   - `ai/README.md`

3. ทดสอบทีละขั้นตอน ตามคู่มือนี้

---

**สร้างโดย:** RMUTP Chatbot Team  
**วันที่:** 15 มกราคม 2026  
**เวอร์ชัน:** Phase 1 - Simple ML
