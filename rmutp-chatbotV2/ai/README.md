# RMUTP Chatbot - AI Module

Intent Classification ด้วย Machine Learning (TF-IDF + Logistic Regression (C=10))

**Last Update:** 9 กุมภาพันธ์ 2026

---

## Quick Start

### 1. ติดตั้ง Python Dependencies

```bash
cd ai/api
pip install -r requirements.txt
pip install mysql-connector-python  # สำหรับดึง FAQ จาก DB
```

### 2. Export FAQ จากฐานข้อมูล

```bash
cd ai/scripts
python export_faq_from_db.py
```

สคริปต์นี้จะ:
- ดึง FAQ จาก MySQL database
- สร้าง question variations อัตโนมัติ
- บันทึกเป็น `faq_training_data.csv`
- สำรองไฟล์เก่าเป็น backup

### 3. เทรน Model

```bash
python train_model.py
```

โมเดลจะถูกบันทึกใน `models/`:
- `intent_classifier.pkl` - Trained model
- `vectorizer.pkl` - TF-IDF vectorizer

### 4. ทดสอบ Model

```bash
# ทดสอบแบบรวดเร็ว
python quick_test.py

# ทดสอบแบบละเอียด
python test_model.py

# ทดสอบระบบแบบสมบูรณ์ (AI → Category → FAQ Answer)
python test_chatbot_complete.py
```

### 4. รัน API Server

```bash
cd ai/api
python app.py
```

API จะทำงานที่: `http://localhost:5000`

### 5. ทดสอบจาก PHP

```bash
cd backend
php ai_helper.php
```

---

## โครงสร้างโฟลเดอร์

```
ai/
├── models/                      # ML Models (สร้างหลัง train)
│   ├── intent_classifier.pkl    # Trained model
│   └── vectorizer.pkl            # TF-IDF vectorizer
│
├── data/                         # Training Data
│   ├── faq_training_data.csv     # Training data จาก DB (อัตโนมัติ)
│   ├── training_data.csv         # Main training file
│   └── training_data_backup_*.csv # Backup files
│
├── scripts/                      # Python Scripts
│   ├── export_faq_from_db.py     # ดึง FAQ จาก MySQL
│   ├── train_model.py            # Train Model
│   ├── test_model.py             # ทดสอบ Model
│   ├── test_chatbot_complete.py  # ทดสอบระบบแบบสมบูรณ์
│   ├── quick_test.py             # ทดสอบรวดเร็ว
│   ├── text_utils.py             # Thai tokenizer utilities
│   ├── analyze_training_data.py  # วิเคราะห์ training data
│   └── import_faq_to_db.py       # Import FAQ เข้า DB
│
├── api/                          # Flask API
│   ├── app.py                    # API Server
│   └── requirements.txt          # Dependencies
│
└── logs/                         # Logs (ถ้ามี)
```

---

## วิธีการทำงาน

### 1. Export FAQ จาก Database
`export_faq_from_db.py` ทำหน้าที่:
1. เชื่อมต่อ MySQL database
2. ดึง FAQ ทั้งหมด (id, question, answer, category, keywords)
3. สร้าง question variations สำหรับแต่ละ category
4. บันทึกเป็น CSV สำหรับ training

### 2. Train Model
`train_model.py` ทำหน้าที่:
1. โหลด training data จาก CSV
2. Tokenize ภาษาไทยด้วย pythainlp (newmm engine)
3. สร้าง TF-IDF features
4. Train Logistic Regression (C=10) model
5. บันทึก model และ vectorizer

### 3. Prediction Flow
```
คำถามผู้ใช้
    ↓
Tokenize (pythainlp)
    ↓
TF-IDF Transform
    ↓
Model Prediction
    ↓
Category (intent)
    ↓
ค้นหา FAQ จาก DB ด้วย category
    ↓
แสดงคำตอบ
```

---

## API Endpoints

### GET /

ข้อมูล API

### GET /health

Health check

### POST /predict

Predict intent สำหรับคำถามเดียว

**Request:**
```json
{
  "question": "ค่าเทอมเท่าไหร่"
}
```

**Response:**
```json
{
  "intent": "ask_tuition",
  "confidence": 0.92,
  "alternatives": [
    {"intent": "ask_loan", "confidence": 0.05}
  ],
  "processing_time_ms": 15
}
```

---

## Intents (14 กลุ่ม)

1. **ask_staff** - ถามเกี่ยวกับอาจารย์/บุคลากร
2. **ask_tuition** - ถามเกี่ยวกับค่าเทอม
3. **ask_admission** - ถามเกี่ยวกับการรับสมัคร
4. **ask_loan** - ถามเกี่ยวกับกู้เงิน/ทุนการศึกษา
5. **ask_department** - ถามเกี่ยวกับสาขาวิชา
6. **ask_facility** - ถามเกี่ยวกับสิ่งอำนวยความสะดวก
7. **ask_grade** - ถามเกี่ยวกับผลสอบ/ระบบทะเบียน
8. **ask_news** - ถามเกี่ยวกับข่าวสาร/กิจกรรม
9. **ask_contact** - ถามเกี่ยวกับการติดต่อ
10. **ask_career** - ถามเกี่ยวกับอาชีพหลังจบ
11. **ask_internship** - ถามเกี่ยวกับฝึกงาน/สหกิจศึกษา
12. **ask_curriculum** - ถามเกี่ยวกับหลักสูตรเฉพาะสาขา
13. **greeting** - ทักทาย/สวัสดี
14. **other** - คำถามอื่นๆ

---

## Hybrid Decision Logic

```
Confidence ≥ 80%  ➜ ใช้ Rule-based (รวดเร็ว, แม่นยำ)
Confidence 60-79% ➜ พิจารณาใช้ AI
Confidence < 60%  ➜ ส่งต่อเจ้าหน้าที่
```

---

## Troubleshooting

### Model ไม่เจอ
```bash
python ai/scripts/train_model.py
```

### API ไม่ทำงาน
ตรวจสอบ:
- Python ติดตั้งแล้ว? `python --version`
- Dependencies ครบ? `pip list`
- Port 5000 ว่าง? `netstat -an | findstr 5000`

### Accuracy ต่ำ
- เพิ่มข้อมูลเทรน (20-30 ตัวอย่าง/Intent)
- ตรวจสอบ Intent distribution (ควรสมดุล)
- ลอง Algorithm อื่น (SVM, Logistic Regression)

---

## Next Steps

เมื่อ Phase 1 สำเร็จ (Accuracy ≥ 75%):

1. เชื่อมต่อกับ `chatbot.php`
2. เพิ่ม Entity Recognition (NER)
3. Context Management
4. RAG + LLM (Phase 2)

---

## เอกสารที่เกี่ยวข้อง

- [PHASE1_SIMPLE_ML.md](../docs/PHASE1_SIMPLE_ML.md) - แผนงาน Phase 1
- [AI_INTEGRATION_PLAN.md](../docs/AI_INTEGRATION_PLAN.md) - แผนภาพรวม
- [THEORY.md](../docs/THEORY.md) - ทฤษฎี NLP & ML

Start-Process powershell -ArgumentList "-NoExit", "-Command", "cd c:\xampp\htdocs\rmutp-chatbotV2\ai\api; python app.py"