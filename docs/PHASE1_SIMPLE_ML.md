# Phase 1: Simple ML Implementation - Intent Classification

เริ่มต้น: 15 มกราคม 2026  
ระยะเวลา: 2-4 สัปดาห์  
Budget: ฿0 (ใช้ Open Source)

---

## เป้าหมาย Phase 1

✅ สร้าง **Intent Classifier** ที่สามารถจำแนกคำถามได้  
✅ ไม่ต้องใช้ API หรือ GPU  
✅ ทำงานร่วมกับ Rule-based ที่มีอยู่  
✅ Accuracy เป้าหมาย: **75%+**

---

## Intent ที่จะจำแนก (10 กลุ่ม)

| ID | Intent | ตัวอย่างคำถาม | ความถี่ |
|----|--------|---------------|---------|
| 1 | **ask_staff** | "อาจารย์สาขาคอมมีใครบ้าง" | สูง |
| 2 | **ask_tuition** | "ค่าเทอมเท่าไหร่" | สูงมาก |
| 3 | **ask_admission** | "สมัครเรียนยังไง" "TCAS" | สูงมาก |
| 4 | **ask_loan** | "กยศ คืออะไร" "กู้เงิน" | กลาง |
| 5 | **ask_department** | "มีสาขาอะไรบ้าง" | กลาง |
| 6 | **ask_facility** | "มีห้องแล็บไหม" "สิ่งอำนวยความสะดวก" | ต่ำ |
| 7 | **ask_grade** | "เช็คผลสอบ" "เกรด" | กลาง |
| 8 | **ask_news** | "ข่าวสาร" "กิจกรรม" | ต่ำ |
| 9 | **ask_contact** | "ติดต่อ" "เบอร์โทร" | กลาง |
| 10 | **other** | คำถามที่ไม่อยู่ในกลุ่มอื่น | - |

---

## สถาปัตยกรรม Phase 1

```
┌─────────────────────────────────────────────────┐
│               Frontend (index.html)              │
│         [ส่งคำถาม] ➜ AJAX Request                │
└────────────────────┬────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────┐
│           Backend (chatbot.php)                  │
│  1. รับคำถาม                                     │
│  2. เรียก Python ML API ➜ ได้ Intent            │
│  3. ตัดสินใจ: ใช้ Rule หรือ AI?                 │
└────────────────────┬────────────────────────────┘
                     │
        ┌────────────┴─────────────┐
        │                          │
        ▼                          ▼
┌──────────────┐          ┌──────────────────┐
│  Rule-based  │          │  Python ML API   │
│  (เดิม)      │          │  (ใหม่)          │
│              │          │                  │
│ - FAQ Search │          │ - Intent Model   │
│ - Staff      │          │ - Tokenization   │
│ - News       │          │ - Classification │
└──────────────┘          └──────────────────┘
```

---

## ขั้นตอนการทำงาน

### Week 1: เตรียมข้อมูล

**Day 1-2: สร้างชุดข้อมูลเทรน**
- [ ] รวบรวมคำถามจาก `chat_logs` (100-200 ตัวอย่าง)
- [ ] Annotate Intent ด้วยมือ (ใช้ Excel หรือ CSV)
- [ ] แบ่งข้อมูล Train:Test = 80:20

**Day 3-4: Setup Python Environment**
- [ ] ติดตั้ง Python 3.8+
- [ ] สร้าง Virtual Environment
- [ ] ติดตั้ง Libraries:
  - pythainlp (Thai NLP)
  - scikit-learn (ML)
  - Flask (API)
  - pandas (Data)

**Day 5: เขียน Data Preprocessing**
- [ ] Tokenization (ตัดคำภาษาไทย)
- [ ] Feature Extraction (TF-IDF)
- [ ] Save preprocessor

---

### Week 2: สร้าง ML Model

**Day 6-8: เทรน Model**
- [ ] ทดลอง 3 Algorithms:
  - Naive Bayes (เร็ว)
  - SVM (แม่นยำ)
  - Logistic Regression (สมดุล)
- [ ] เลือก Model ที่ดีที่สุด
- [ ] บันทึก Model (.pkl)

**Day 9-10: ทดสอบ**
- [ ] Test Accuracy
- [ ] Confusion Matrix
- [ ] แก้ไขข้อมูลที่ผิดพลาด

---

### Week 3: สร้าง API

**Day 11-12: Python Flask API**
- [ ] สร้าง `/predict` endpoint
- [ ] Load Model
- [ ] Return JSON: `{intent: "ask_tuition", confidence: 0.92}`

**Day 13-14: เชื่อมต่อ PHP**
- [ ] เรียก Python API จาก `chatbot.php`
- [ ] Handle Response
- [ ] Fallback ถ้า API ล้ม

---

### Week 4: Integration & Testing

**Day 15-16: Hybrid Logic**
- [ ] เขียน Decision Engine
- [ ] กำหนด Threshold (Confidence ≥ 80% = ใช้ Rule)
- [ ] Log ทุกครั้งที่ใช้ AI

**Day 17-18: Testing**
- [ ] Unit Test
- [ ] Integration Test
- [ ] User Testing (5-10 คน)

**Day 19-20: Deploy & Monitor**
- [ ] Deploy Python API (port 5000)
- [ ] Monitor Performance
- [ ] เก็บ Feedback

---

## ไฟล์ที่จะสร้าง

### โครงสร้างโฟลเดอร์ใหม่

```
rmutp-chatbot/
├── ai/                          # ← โฟลเดอร์ AI ใหม่
│   ├── models/                  # เก็บ ML Models
│   │   ├── intent_classifier.pkl
│   │   └── vectorizer.pkl
│   ├── data/                    # ข้อมูลเทรน
│   │   ├── training_data.csv
│   │   └── intents.json
│   ├── scripts/                 # Python Scripts
│   │   ├── train_model.py
│   │   ├── preprocess.py
│   │   └── test_model.py
│   ├── api/                     # Flask API
│   │   ├── app.py
│   │   ├── predict.py
│   │   └── requirements.txt
│   └── logs/                    # AI Logs
│       └── predictions.log
├── backend/
│   ├── chatbot.php              # ← จะอัปเดต
│   └── ai_helper.php            # ← ไฟล์ใหม่
└── docs/
    └── PHASE1_SIMPLE_ML.md      # ← ไฟล์นี้
```

---

## เทคโนโลยีที่ใช้

### Python Libraries

```
pythainlp==4.0.2        # Thai word tokenization
scikit-learn==1.3.0     # ML algorithms
pandas==2.0.0           # Data processing
Flask==3.0.0            # API server
joblib==1.3.0           # Save/Load models
numpy==1.24.0           # Numerical computing
```

### ML Algorithm: Naive Bayes

**เหตุผลที่เลือก**:
- ✅ เร็วมาก (ทำนายใน milliseconds)
- ✅ ทำงานดีกับ Text Classification
- ✅ ไม่ต้องการข้อมูลเทรนเยอะ
- ✅ ไม่ต้องการ GPU

**Accuracy คาดการณ์**: 75-85%

---

## ตัวอย่างข้อมูลเทรน

### training_data.csv

```csv
question,intent
"ค่าเทอมเท่าไหร่",ask_tuition
"ค่าเรียนต่อเทอม",ask_tuition
"ค่าใช้จ่ายในการเรียน",ask_tuition
"อาจารย์สาขาคอมมีใครบ้าง",ask_staff
"อาจารย์ประจำสาขาคอมพิวเตอร์",ask_staff
"สมัครเรียน TCAS",ask_admission
"วิธีสมัครเข้าศึกษา",ask_admission
"กยศ คืออะไร",ask_loan
"กู้เงินยังไง",ask_loan
```

**จำนวนที่ต้องการ**: 
- ขั้นต่ำ: 10 ตัวอย่าง/Intent (รวม 100)
- แนะนำ: 20 ตัวอย่าง/Intent (รวม 200)

---

## วิธีการทดสอบ

### 1. Accuracy Test

```python
from sklearn.metrics import accuracy_score, classification_report

y_pred = model.predict(X_test)
accuracy = accuracy_score(y_test, y_pred)

print(f"Accuracy: {accuracy:.2%}")
# เป้าหมาย: ≥ 75%
```

### 2. Confusion Matrix

```
                Predicted
Actual       ask_staff  ask_tuition  ask_loan
ask_staff         18          1         1
ask_tuition        0         19         1
ask_loan           1          0        18
```

### 3. Real-world Test

```
Test Questions (20 คำถาม):
✅ ถูก 17 คำถาม
❌ ผิด 3 คำถาม
Accuracy: 85%
```

---

## ตัวอย่าง API Response

### Request (จาก PHP)

```php
$ch = curl_init('http://localhost:5000/predict');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'question' => 'ค่าเทอมเท่าไหร่'
]));
$response = curl_exec($ch);
```

### Response (จาก Python)

```json
{
    "intent": "ask_tuition",
    "confidence": 0.92,
    "alternatives": [
        {"intent": "ask_loan", "confidence": 0.05},
        {"intent": "other", "confidence": 0.03}
    ],
    "processing_time_ms": 15
}
```

---

## Hybrid Decision Logic

```php
// chatbot.php

// 1. เรียก AI ดู Intent
$ai_response = callPythonAPI($question);
$intent = $ai_response['intent'];
$confidence = $ai_response['confidence'];

// 2. ตัดสินใจ
if ($confidence >= 0.80) {
    // ใช้ Rule-based (แม่นยำ)
    switch ($intent) {
        case 'ask_tuition':
            return searchFAQ('tuition');
        case 'ask_staff':
            return searchStaff($question);
        // ... other intents
    }
} else {
    // Confidence ต่ำ = ส่งต่อเจ้าหน้าที่
    return [
        'response' => 'ขออภัยครับ ไม่แน่ใจว่าเข้าใจคำถามถูกต้อง 
                       ต้องการให้ติดต่อเจ้าหน้าที่โดยตรงไหมครับ?',
        'confidence' => $confidence,
        'intent' => $intent
    ];
}
```

---

## เมตริกที่จะวัด

| Metric | Before AI | Target (Phase 1) | วัดอย่างไร |
|--------|-----------|------------------|-----------|
| **Intent Detection Accuracy** | N/A | 75%+ | Test set |
| **Response Accuracy** | 70% | 75-80% | User feedback |
| **Response Time** | 50ms | 100ms | Monitoring |
| **ตอบไม่ได้** | 30% | 20% | Chat logs |
| **User Satisfaction** | 3.5/5 | 4.0/5 | Feedback |

---

## Troubleshooting

### ปัญหาที่อาจเจอ

1. **Accuracy ต่ำกว่า 75%**
   - เพิ่มข้อมูลเทรน (20 → 30 ตัวอย่าง/Intent)
   - ปรับ Feature Extraction (TF-IDF → n-grams)
   - ลองเปลี่ยน Algorithm

2. **Python API ช้า**
   - Cache Model ใน Memory
   - ใช้ Threading
   - Optimize Preprocessing

3. **API เชื่อมต่อไม่ได้**
   - เช็ค Python process running
   - เช็ค Port 5000 ว่าง
   - เช็ค CORS settings

4. **ภาษาไทยตัดคำผิด**
   - อัปเดต pythainlp
   - เพิ่ม Custom Dictionary
   - ใช้ newmm tokenizer

---

## Success Criteria

Phase 1 สำเร็จเมื่อ:

- ✅ Intent Classifier มี Accuracy ≥ 75%
- ✅ Python API ทำงานได้เสถียร
- ✅ PHP เรียก API ได้สำเร็จ
- ✅ Hybrid Logic ทำงานถูกต้อง
- ✅ Response Time < 200ms
- ✅ มี Monitoring & Logging

---

## Next Steps (Phase 2)

หลังจาก Phase 1 สำเร็จ:

1. **Entity Recognition**: ดึงชื่อ, สาขา, จำนวนเงิน
2. **Context Management**: จำบริบทการสนทนา
3. **RAG**: ใช้ Vector Database + LLM
4. **Fine-tuning**: ปรับปรุง Model จาก User Feedback

---

## เอกสารที่เกี่ยวข้อง

- [AI_INTEGRATION_PLAN.md](AI_INTEGRATION_PLAN.md): แผนภาพรวม
- [THEORY.md](THEORY.md): ทฤษฎี NLP & ML
- [PROJECT_STATUS_CURRENT.md](PROJECT_STATUS_CURRENT.md): สถานะปัจจุบัน

---

**หมายเหตุ**: Phase 1 นี้เน้นความเรียบง่าย ควบคุมได้ และไม่มีค่าใช้จ่าย  
เป้าหมายคือพิสูจน์ว่า AI ช่วยระบบได้จริงก่อนลงทุนเพิ่ม
