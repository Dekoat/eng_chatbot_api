# 🤖 ระบบแชทบอทอัจฉริยะ คณะวิศวกรรมศาสตร์
## มหาวิทยาลัยเทคโนโลยีราชมงคลพระนคร

**RMUTP Engineering Faculty Chatbot with AI Integration**

---

## 📌 สไลด์ 1: ภาพรวมโครงการ

### ปัญหาที่พบ
- นักศึกษาและผู้สนใจมีคำถามซ้ำ ๆ เกี่ยวกับหลักสูตร, การรับสมัคร, ค่าเทอม, ทุนการศึกษา
- บุคลากรต้องตอบคำถามเดิมซ้ำหลายครั้งต่อวัน
- ไม่มีระบบตอบคำถามอัตโนมัติที่ให้บริการ 24/7

### แนวทางแก้ไข
พัฒนาระบบ **Chatbot อัจฉริยะ** ที่ผสานเทคโนโลยี **Machine Learning (AI)** เข้ากับระบบค้นหาแบบ Rule-based เพื่อตอบคำถามให้ถูกต้องและรวดเร็ว

---

## 📌 สไลด์ 2: วัตถุประสงค์

1. พัฒนาระบบ Chatbot ที่รองรับภาษาไทย สามารถตอบคำถามเกี่ยวกับคณะวิศวกรรมศาสตร์ได้อัตโนมัติ
2. ใช้เทคโนโลยี AI/ML (TF-IDF + Logistic Regression) จำแนกประเภทคำถาม
3. รวบรวมข้อมูลอาจารย์ 118 คน, FAQ 598 รายการ, ข่าวสาร 33 รายการ ไว้ในระบบเดียว
4. สร้างระบบ Admin Dashboard สำหรับจัดการข้อมูล
5. ลดภาระงานซ้ำซ้อนของเจ้าหน้าที่

---

## 📌 สไลด์ 3: เทคโนโลยีที่ใช้

### Backend

| เทคโนโลยี | เวอร์ชัน | หน้าที่ |
|-----------|---------|---------|
| **PHP** | 8.2+ | REST API, Business Logic |
| **MySQL** | 8.0 | Database, Data Storage |
| **Python** | 3.12 | AI/ML Module |
| **Flask** | 3.1+ | AI API Server |

### Machine Learning

| เทคโนโลยี | หน้าที่ |
|-----------|---------|
| **scikit-learn** | Model Training & Prediction |
| **TF-IDF** | Feature Extraction (แปลงข้อความเป็นตัวเลข) |
| **Logistic Regression** (C=10) | Intent Classification (จำแนกประเภทคำถาม) |
| **pythainlp** | Thai Language Tokenization (ตัดคำภาษาไทย) |

### Frontend

| เทคโนโลยี | หน้าที่ |
|-----------|---------|
| **HTML5 + CSS3** | Structure & Styling |
| **JavaScript ES6+** | Interactive Logic |
| **Bootstrap 5** | UI Framework |
| **SweetAlert2** | Alert Dialogs |

---

## 📌 สไลด์ 4: สถาปัตยกรรมระบบ (3-Tier Architecture)

```
┌─────────────────────────────────────────────────────────┐
│                  Frontend Layer                          │
│   index.html (Chat UI, Responsive, Dark Mode)           │
│   admin/dashboard.html (Admin Panel)                    │
│   admin/analytics.html (Analytics Dashboard)            │
└───────────────────────┬─────────────────────────────────┘
                        │ AJAX POST (JSON)
                        ▼
┌─────────────────────────────────────────────────────────┐
│                  Backend Layer (PHP)                      │
│                                                          │
│   chatbot.php (2,525 lines) ← Main Chat Engine          │
│   ┌──────────────────────────────────────────────────┐  │
│   │  5-Phase Pipeline:                                │  │
│   │  Phase 1: AI Intent Classification               │  │
│   │  Phase 2: Staff Search (118 คน)                   │  │
│   │  Phase 2.5: News Search (33 ข่าว)                 │  │
│   │  Phase 3: FAQ Search + Scoring (598 FAQ)          │  │
│   │  Phase 4: Staff Fallback                          │  │
│   │  Phase 5: Build Response                          │  │
│   └──────────────────────────────────────────────────┘  │
│                                                          │
│   ChatbotConfig.php (622 lines) ← ค่าคงที่ + Config     │
│   QueryAnalyzer.php (186 lines) ← วิเคราะห์คำถาม       │
│   broad_topic_handler.php (586 lines) ← คำถามกว้าง     │
│                                                          │
│   รวม Backend: 3,919 บรรทัด                              │
└───────────────────────┬─────────────────────────────────┘
                        │
            ┌───────────┼───────────┐
            ▼                       ▼
┌──────────────────┐    ┌──────────────────────────────┐
│  MySQL Database  │    │  Python Flask API (:5000)     │
│  ├─ faq (598)    │    │  POST /predict                │
│  ├─ staff (118)  │    │  ├─ Load Model (.pkl)         │
│  ├─ news (33)    │    │  ├─ Tokenize (pythainlp)      │
│  ├─ chat_logs    │    │  ├─ TF-IDF Transform          │
│  └─ feedback     │    │  └─ Return intent+confidence  │
└──────────────────┘    └──────────────────────────────┘
```

---

## 📌 สไลด์ 5: ระบบ AI - Hybrid Approach

### ทำไมต้อง Hybrid?

| แนวทาง | ข้อดี | ข้อเสีย |
|--------|------|---------|
| **Rule-based อย่างเดียว** | เร็ว, ควบคุมได้ | ไม่ยืดหยุ่น, ต้องเขียน rules เยอะ |
| **AI อย่างเดียว** | ยืดหยุ่น | อาจตอบผิดถ้า confidence ต่ำ |
| **Hybrid (ที่เลือก) ✅** | ได้ทั้งความแม่นยำ + ยืดหยุ่น | ซับซ้อนกว่า |

### ขั้นตอนการทำงาน

```
คำถามผู้ใช้: "ค่าเทอมวิศวกรรมคอมพิวเตอร์เท่าไหร่"
    │
    ▼
[1. AI Intent Classification]
    → intent: "loan" (92.8% confidence)
    │
    ▼
[2. Detect Department]
    → department: "computer_engineering" (จากคำว่า "คอมพิวเตอร์")
    │
    ▼
[3. FAQ Search + Category Filter]
    → ค้นจาก category: loan + related categories
    → กรอง department: computer_engineering
    │
    ▼
[4. Scoring Algorithm]
    → Exact Match: +2000
    → Important Phrase ("ค่าเทอม"): +1000
    → Dept Match: +800
    → Intent Match: +400
    │
    ▼
[5. Best Answer]
    → FAQ #623: "ค่าเทอมวิศวกรรมคอมพิวเตอร์ประมาณ 25,000 บาท/เทอม"
    → Confidence: 9.5/10
```

---

## 📌 สไลด์ 6: ข้อมูลในระบบ

### สถิติข้อมูล (10 กุมภาพันธ์ 2026)

| ประเภท | จำนวน | รายละเอียด |
|--------|-------|-----------|
| **FAQ** | 598 รายการ | 16 หมวดหมู่, 10 สาขาวิชา |
| **บุคลากร** | 118 คน | อาจารย์ทั้ง 10 สาขา + ข้อมูลติดต่อ |
| **ข่าวสาร** | 33 ข่าว | อัปเดตจากเว็บไซต์ (เก็บ 180 วัน) |
| **Chat Logs** | 391+ ครั้ง | บันทึกการสนทนา |
| **Training Data** | 3,615 ตัวอย่าง | 15 intents สำหรับ AI |

### FAQ 16 หมวดหมู่

| หมวด | ตัวอย่างคำถาม |
|------|-------------|
| program | หลักสูตร, สาขาวิชา, แผนการศึกษา |
| admission | การรับสมัคร, TCAS, คุณสมบัติ |
| loan | กยศ., กรอ., ทุนการศึกษา, ค่าเทอม |
| curriculum | รายวิชา, หน่วยกิต, แผนการเรียน |
| career | อาชีพหลังจบ, ตลาดงาน |
| contact | ติดต่อ, เบอร์โทร, สถานที่ |
| facilities | ห้องสมุด, ห้องปฏิบัติการ |
| activities | สหกิจศึกษา, กิจกรรม, กีฬา |
| staff | ข้อมูลอาจารย์, ตำแหน่ง |
| about | ประวัติสาขา, จุดเด่น |
| research | งานวิจัย, ผลงานวิชาการ |
| document | แบบฟอร์ม, เอกสาร |
| cooperation | ความร่วมมือ, MOU |
| general | ข้อมูลทั่วไป |
| graduation | การสำเร็จการศึกษา |
| academic | ข้อบังคับ, ระเบียบ |

### 10 สาขาวิชาที่ครอบคลุม

1. วิศวกรรมคอมพิวเตอร์ (14 คน)
2. วิศวกรรมไฟฟ้า (16 คน)
3. วิศวกรรมโยธา (12 คน)
4. วิศวกรรมอิเล็กทรอนิกส์และโทรคมนาคม (14 คน)
5. วิศวกรรมเครื่องกล (14 คน)
6. วิศวกรรมอุตสาหการ (20 คน)
7. วิศวกรรมเมคคาทรอนิกส์ (6 คน)
8. วิศวกรรมการผลิตเครื่องประดับ (6 คน)
9. วิศวกรรมเครื่องมือและแม่พิมพ์ (6 คน)
10. วิศวกรรมการจัดการอุตสาหกรรมเพื่อความยั่งยืน (10 คน)

---

## 📌 สไลด์ 7: ฟีเจอร์ของระบบ

### 1. Chat Interface (หน้าแชท)
- ✅ Responsive Design — ใช้งานได้ทุกอุปกรณ์
- ✅ Dark/Light Mode — รองรับทั้ง 2 โหมด
- ✅ Quick Action Cards — 4 ปุ่มลัด (FAQ, อาจารย์, ข่าวสาร, ทุน)
- ✅ Smart Suggestions — แนะนำคำถามที่เกี่ยวข้อง
- ✅ Confidence Score — แสดงระดับความมั่นใจ
- ✅ Related Questions — ปุ่มเลือกสาขา/หัวข้อเพิ่มเติม

### 2. AI/ML System (ระบบ AI)
- ✅ Intent Classification — จำแนก 15 ประเภทคำถาม
- ✅ Hybrid Search — AI + Rule-based scoring
- ✅ relatedCategories — ขยายค้นหาข้าม category ที่เกี่ยวข้อง
- ✅ Synonym Expansion — รู้จักคำพ้อง 80+ คำ
- ✅ Department Detection — ตรวจจับสาขาวิชาจากคำถาม

### 3. Admin Dashboard (หลังบ้าน)
- ✅ Login System — Token-based authentication
- ✅ FAQ Management — เพิ่ม/แก้ไข/ลบ FAQ
- ✅ Staff Management — จัดการข้อมูลอาจารย์
- ✅ Analytics — สถิติการใช้งาน, คำถามยอดนิยม

### 4. Feedback System
- ✅ 👍/👎 Buttons — ให้คะแนนคำตอบ
- ✅ Comment Field — แสดงความคิดเห็น
- ✅ Database Logging — เก็บข้อมูลวิเคราะห์

### 5. Security
- ✅ CORS Allowlist, Rate Limiting (10 req/min)
- ✅ SQL Injection Protection, Input Validation
- ✅ Token Authentication

---

## 📌 สไลด์ 8: ผลการทดสอบ

### Performance Metrics

| Metric | ระบบเก่า (Rule-based) | ระบบใหม่ (AI Hybrid v4.0) | ผลต่าง |
|--------|--------------------|--------------------------|--------|
| **Response Time** | ~300ms | ~500ms | +200ms |
| **FAQ Accuracy** | 60-70% | **100%** (598/598) | +30-40% |
| **Variant Accuracy** | ❌ ไม่มี | **100%** (659/659) | New |
| **AI Accuracy** | ❌ ไม่มี | **96.4%** | New |
| **Confidence Score** | ❌ ไม่มี | ✅ 87% avg | New |
| **Synonym Support** | ❌ | ✅ 80+ คำพ้อง | New |
| **Categories** | 5 หมวด | **16 หมวดหมู่** | +11 |

### การทดสอบ FAQ

| การทดสอบ | จำนวน | ผ่าน | อัตราความสำเร็จ |
|---------|-------|------|--------------|
| **Primary Test** (คำถามหลัก) | 598 | 598 | **100%** |
| **Variant Test** (ทุกรูปแบบ / และ \|) | 659 | 659 | **100%** |
| **รวม** | **1,257** | **1,257** | **100%** |

### AI Model Performance

| Metric | ค่า |
|--------|-----|
| Training Data | 3,615 ตัวอย่าง |
| Intents | 15 หมวดหมู่ |
| AI Accuracy | 96.4% |
| Training Time | ~2 วินาที |
| Inference Time | ~200ms |
| Model Size | 85KB |

---

## 📌 สไลด์ 9: Scoring Algorithm (การให้คะแนน FAQ)

### ระบบคะแนนแบบหลายชั้น

| ลำดับ | ประเภท | คะแนน | คำอธิบาย |
|------|--------|-------|---------|
| 1 | **Exact Match** | +2,000 | คำถามตรงเป๊ะ |
| 2 | **Important Phrase** | +1,000 | คำสำคัญ (ค่าเทอม, กยศ., เกรด) |
| 3 | **Department Match** | +800 | สาขาตรงกัน |
| 4 | **Critical Keyword** | +1,500 | คำสำคัญระดับสูง |
| 5 | **Phrase Match** | +500 | วลีตรงกัน |
| 6 | **Intent Match** | +400 | AI intent ตรงกัน |
| 7 | **Dept Mismatch** | -400 | สาขาไม่ตรง (ลดคะแนน) |

### ตัวอย่างการคำนวณ

```
คำถาม: "หลักสูตรวิศวกรรมคอมพิวเตอร์ เรียนกี่ปี"

FAQ #540 (ตรงสาขา):  score = 500(phrase) + 800(dept) + 400(intent) = 1,700 ✅
FAQ #285 (ต่างสาขา): score = 500(phrase) - 400(dept)  + 400(intent) = 500  ✗
```

---

## 📌 สไลด์ 10: โครงสร้างโปรเจค

```
rmutp-chatbotV2/
├── frontend/                     # 📱 หน้าเว็บผู้ใช้
│   └── index.html                #     Chat Interface
│
├── admin/                        # 🔧 หน้าผู้ดูแล
│   ├── login.html                #     หน้า Login
│   ├── dashboard.html            #     หน้า Dashboard
│   └── analytics.html            #     หน้า Analytics
│
├── backend/                      # ⚙️ PHP API (3,919 บรรทัด)
│   ├── chatbot.php               #     Main Chat API (2,525 lines)
│   ├── ChatbotConfig.php         #     Config + Constants (622 lines)
│   ├── QueryAnalyzer.php         #     Query Analysis (186 lines)
│   ├── broad_topic_handler.php   #     Broad Topic + Generic (586 lines)
│   ├── db.php                    #     Database Connection
│   ├── security.php              #     CORS + Rate Limiting
│   ├── admin_api.php             #     Admin CRUD API
│   ├── admin_login.php           #     Admin Login API
│   ├── analytics_api.php         #     Analytics API
│   └── feedback_api.php          #     Feedback API
│
├── ai/                           # 🤖 AI/ML Module
│   ├── api/app.py                #     Flask API Server (port 5000)
│   ├── scripts/train_model.py    #     Train ML Model
│   ├── scripts/export_faq_from_db.py # Export FAQ → Training Data
│   ├── models/*.pkl              #     Trained Models (85KB)
│   └── data/training_data.csv    #     Training Data (3,615 rows)
│
├── database/                     # 🗄️ SQL Import Files
├── docs/                         # 📖 Documentation
└── image/                        # 🖼️ Assets
```

---

## 📌 สไลด์ 11: จุดเด่นของโครงการ

### 1. Hybrid AI + Rule-based
ไม่ใช้ AI อย่างเดียว แต่ผสาน:
- **AI** → เข้าใจ intent (ความตั้งใจ) ของผู้ถาม
- **Rule-based** → ค้นหาข้อมูลจริงในฐานข้อมูล
- **Confidence Boost** → AI ช่วยเพิ่มคะแนนผลที่ตรง intent

### 2. ข้อมูลจริง ไม่ใช่แค่ Demo
- ✅ FAQ 598 รายการ — เนื้อหาจริงจากคณะ
- ✅ อาจารย์ 118 คน — ชื่อ, ตำแหน่ง, อีเมล, ความเชี่ยวชาญ
- ✅ ข่าวสาร 33 รายการ — ดึงจากเว็บไซต์จริง

### 3. ทดสอบอย่างละเอียด
- ✅ Primary Test: **598/598 (100%)** — ทดสอบคำถามหลักทุก FAQ
- ✅ Variant Test: **659/659 (100%)** — ทดสอบ **ทุกรูปแบบ** คำถาม
- ✅ รวม **1,257 test cases** ผ่าน 100%

### 4. Production-Ready
- ✅ Error Handling ครบถ้วน
- ✅ Security (SQL Injection, XSS, Rate Limiting)
- ✅ Session Management + Chat Logging
- ✅ UTF-8 สำหรับภาษาไทย

### 5. Cost-Effective
- ✅ ใช้ Open-Source ทั้งหมด (ไม่มีค่าใช้จ่าย API)
- ✅ รันบน Local Server (XAMPP)
- ✅ ไม่ต้องจ่าย Cloud / OpenAI

---

## 📌 สไลด์ 12: วิธีรันโปรเจค

### Quick Start (3 ขั้นตอน)

**1. เริ่ม Backend**
```bash
# เปิด XAMPP → Start Apache + MySQL
# Import Database: database/*.sql → eng_chatbot
```

**2. เริ่ม AI Server**
```bash
cd ai/api
pip install -r requirements.txt
python app.py
# → Running on http://127.0.0.1:5000
```

**3. เปิด Frontend**
```
http://localhost/rmutp-chatbotV2/frontend/
```

### Admin Dashboard
```
http://localhost/rmutp-chatbotV2/admin/login.html
```

---

## 📌 สไลด์ 13: ข้อจำกัดและการพัฒนาต่อ

### ข้อจำกัดปัจจุบัน
1. ⚠️ รองรับเฉพาะภาษาไทยเป็นหลัก
2. ⚠️ ยังไม่เชื่อมต่อ LINE / Facebook Messenger
3. ⚠️ ไม่รองรับ Voice Input (เสียง)
4. ⚠️ ข้อมูลต้องอัปเดตด้วยตนเอง (ยกเว้นข่าวสาร)

### แนวทางพัฒนาต่อยอด (Future Work)
1. 🌐 Multi-language Support (ไทย + อังกฤษ)
2. 🧠 Deep Learning (LSTM / Transformer) แทน Logistic Regression
3. 📱 Mobile Application (React Native)
4. 🔗 LINE Official Account Integration
5. 📊 Advanced Analytics Dashboard
6. 🎤 Voice Command Support
7. 🔄 เชื่อมต่อระบบทะเบียนนักศึกษา

---

## 📌 สไลด์ 14: สรุปผลการดำเนินงาน

| หัวข้อ | สถานะ | รายละเอียด |
|-------|-------|-----------|
| **AI Integration** | ✅ สำเร็จ | Hybrid AI System, 96.4% accuracy |
| **FAQ System** | ✅ สำเร็จ | 598 FAQ, 16 หมวดหมู่ |
| **FAQ Accuracy** | ✅ **100%** | 598/598 + Variant 659/659 |
| **Staff Search** | ✅ สำเร็จ | 118 คน, 10 สาขา |
| **Admin Panel** | ✅ สำเร็จ | Login + CRUD + Analytics |
| **News System** | ✅ สำเร็จ | Auto scraper, 33 ข่าว |
| **Code Quality** | ✅ Refactored | 4 ไฟล์หลัก, 3,919 บรรทัด |
| **Security** | ✅ สำเร็จ | CORS, Rate Limit, Token Auth |
| **Production** | ✅ **พร้อมใช้งาน** | Stable, ทดสอบผ่าน 1,257/1,257 |

---

## 📌 สไลด์ 15: ทีมพัฒนาและข้อมูลติดต่อ

**โครงการ:** ระบบแชทบอทอัจฉริยะ คณะวิศวกรรมศาสตร์  
**สถาบัน:** มหาวิทยาลัยเทคโนโลยีราชมงคลพระนคร  
**ภาควิชา:** วิศวกรรมคอมพิวเตอร์

- 🌐 เว็บไซต์: https://eng.rmutp.ac.th
- 📧 อีเมล: eng@rmutp.ac.th
- 📞 โทรศัพท์: 02-836-3000

---

**เวอร์ชัน:** 4.0 (AI Retrained + Full Variant Test)  
**วันที่:** 10 กุมภาพันธ์ 2026  
**สถานะ:** ✅ Production Ready — **1,257/1,257 Test Cases Passed (100%)**
