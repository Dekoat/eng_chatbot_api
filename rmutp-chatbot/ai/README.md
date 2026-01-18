# RMUTP Chatbot - AI Module

Intent Classification ด้วย Simple Machine Learning

## Quick Start

### 1. ติดตั้ง Python Dependencies

```bash
cd ai/api
pip install -r requirements.txt
```

### 2. เทรน Model

```bash
cd ai/scripts
python train_model.py
```

คาดว่าจะได้ Accuracy: **75-85%**

### 3. ทดสอบ Model

```bash
python test_model.py
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
├── models/                      # ML Models (สร้างหลังเทรน)
│   ├── intent_classifier.pkl    # Trained model
│   └── vectorizer.pkl            # TF-IDF vectorizer
│
├── data/                         # Training Data
│   ├── training_data.csv         # 100 ตัวอย่างคำถาม
│   └── intents.json              # คำอธิบาย 10 Intents
│
├── scripts/                      # Python Scripts
│   ├── train_model.py            # เทรน Model
│   └── test_model.py             # ทดสอบ Model
│
├── api/                          # Flask API
│   ├── app.py                    # API Server
│   ├── predict.py                # Helper functions
│   └── requirements.txt          # Dependencies
│
└── logs/                         # Logs
    ├── api.log                   # API logs
    └── predictions.log           # Prediction logs
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

## Intents (10 กลุ่ม)

1. **ask_staff** - ถามเกี่ยวกับอาจารย์/บุคลากร
2. **ask_tuition** - ถามเกี่ยวกับค่าเทอม
3. **ask_admission** - ถามเกี่ยวกับการรับสมัคร
4. **ask_loan** - ถามเกี่ยวกับกู้เงิน/ทุนการศึกษา
5. **ask_department** - ถามเกี่ยวกับสาขาวิชา
6. **ask_facility** - ถามเกี่ยวกับสิ่งอำนวยความสะดวก
7. **ask_grade** - ถามเกี่ยวกับผลสอบ/ระบบทะเบียน
8. **ask_news** - ถามเกี่ยวกับข่าวสาร/กิจกรรม
9. **ask_contact** - ถามเกี่ยวกับการติดต่อ
10. **other** - คำถามอื่นๆ

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
