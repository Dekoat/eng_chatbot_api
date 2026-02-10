# RMUTP Chatbot - สถานะปัจจุบัน

**อัปเดตล่าสุด:** 10 กุมภาพันธ์ 2026

---

## ✅ ระบบพร้อมใช้งาน 100%

### 📊 สถิติข้อมูลปัจจุบัน

| ประเภท | จำนวน |
|--------|-------|
| FAQ | 598 รายการ |
| บุคลากร | 118 คน |
| ข่าวสาร | 33 ข่าว |
| Chat Logs | 391+ รายการ |
| Feedback | 2 รายการ |
| Departments | 16 หน่วยงาน |
| Categories | 16 หมวดหมู่ |
| **FAQ Accuracy** | **598/598 (100%)** |
| **Variant Test** | **659/659 (100%)** |
| **AI Accuracy** | **96.4% (15 intents, 3,615 examples)** |

---

## 🛠️ ฟีเจอร์ที่ทำงานแล้ว

### 1. Chat Interface
- ✅ Responsive design
- ✅ Dark mode support
- ✅ Quick Action Cards (4 ปุ่ม)
- ✅ Smart Suggestions (แนะนำคำถามในหมวดเดียวกัน)
- ✅ Typing indicator
- ✅ Emoji support

### 2. FAQ System
- ✅ 598 FAQ รายการ (เพิ่มค่าเทอมครบทุกสาขา + merge categories + แก้ keywords ซ้ำ)
- ✅ ครอบคลุม 10 สาขาวิศวกรรม
- ✅ 16 departments, 16 categories
- ✅ Training Data: 3,615 records, 15 intents, 96.4% accuracy
- ✅ **Accuracy 100%** (598/598 + Variant 659/659 ทดสอบผ่านหมด)
- ✅ Refactored code: ChatbotConfig.php, QueryAnalyzer.php, BroadTopicHandler
- ✅ BroadTopic Handler: จัดการคำถามกว้างๆ (ทุน, ค่าเทอม, หลักสูตร) + Generic Question
- ✅ related_questions: ปุ่มกดเลือกสาขา/หัวข้อ ในหน้า chat

### 3. AI/ML System
- ✅ TF-IDF + Logistic Regression (C=10)
- ✅ 15 Intent Categories
- ✅ Flask API (port 5000)
- ✅ AI Accuracy: 96.4%
- ✅ Average Confidence: 87%
- ✅ Hybrid System (AI + Rule-based fallback)
- ✅ relatedCategories: cross-category search expansion

### 4. Admin Dashboard
- ✅ Login system (Token-based)
- ✅ FAQ Management (CRUD)
- ✅ Staff Management
- ✅ Chat Logs Viewer + Export CSV
- ✅ Statistics Overview
- ✅ Quick Access Cards
- ✅ News Management (CRUD + Scraper)

### 5. Analytics Dashboard
- ✅ Summary Cards (Total Chats, Feedback, Avg Confidence)
- ✅ Feedback Statistics (👍/👎 ratio)
- ✅ Top 10 Popular Questions
- ✅ Low Confidence Queries (<35%)
- ✅ Daily Statistics (7-day trend)
- ✅ FAQ Performance Ranking

### 6. Feedback System
- ✅ 👍/👎 Buttons ใน Chat
- ✅ Comment field
- ✅ Database logging
- ✅ Real-time update

### 7. Security
- ✅ CORS Allowlist
- ✅ Rate Limiting (10 req/min)
- ✅ Token Authentication
- ✅ Input Validation
- ✅ SQL Injection Protection

### 8. News Automation
- ✅ Auto scraper
- ✅ 180 days retention
- ✅ Manual trigger
- ✅ Category filter

---

## 📁 โครงสร้างไฟล์หลัก

```
rmutp-chatbotV2/
├── README.md                         # คู่มือการใช้งานหลัก
│
├── backend/
│   ├── chatbot.php               # Main Chat API (2,525 บรรทัด)
│   ├── ChatbotConfig.php         # ค่าคงที่ + config ทั้งหมด (622 บรรทัด)
│   ├── QueryAnalyzer.php         # วิเคราะห์คำถาม (186 บรรทัด)
│   ├── broad_topic_handler.php   # จัดการคำถามกว้าง + Generic (586 บรรทัด)
│   ├── db.php / security.php
│   ├── admin_api.php / admin_login.php
│   └── ...
│
├── docs/
│   ├── PROJECT_STATUS_CURRENT.md # สถานะปัจจุบัน (ไฟล์นี้)
│   ├── PROJECT_REPORT.md         # รายงานโครงงาน
│   └── THEORY.md                 # ทฤษฎีที่เกี่ยวข้อง
│
└── ai/
    ├── scripts/
    │   ├── train_model.py            # เทรนโมเดล AI
    │   ├── test_model.py             # ทดสอบโมเดล
    │   ├── _test_thai.php            # ทดสอบ Chatbot รวม (ภาษาไทย)
    │   └── ...
    └── api/app.py                    # Flask AI Server (port 5000)
```

---

## 📈 ประวัติการอัปเดต

### 10 กุมภาพันธ์ 2026 ⭐ (AI Retrain + Full Variant Test + UX Cleanup)
- **Retrain AI Model** — แก้ normalize_intent() แยก about/staff/document/cooperation เป็น intent แยก
- **AI Accuracy: 96.4%** (15 intents, 3,615 training examples — เพิ่มจาก 3,466)
- **Variant Test: 659/659 (100%)** — ทดสอบทุกรูปแบบคำถาม (/ และ |) ผ่านหมด
- **แก้ FAQ keywords ซ้ำกัน 8 รายการ** — "ทำไมต้องเลือกเรียน" เพิ่มชื่อสาขา, SIME→สื่อสารอัจฉริยะ
- **ขยาย relatedCategories** — program↔contact,admission | contact↔about,facilities,general | etc.
- **เพิ่ม Staff FAQ: hasCuratedStaffFaq()** — ตรวจสอบว่ามี FAQ สำหรับอาจารย์ก่อนค้น staff table
- **forceStaffFaqCategory** — เฉพาะ role keywords (ประธาน, ผู้รับผิดชอบ, หัวหน้า)
- **เพิ่ม FAQ เป็น 598 รายการ** (16 departments, 16 categories)
- ลบไฟล์ temp 10+ ไฟล์
- ทดสอบผ่าน: **598/598 (100%) + 659/659 (100%)**

### 10 กุมภาพันธ์ 2026 (UX + Code Cleanup)
- **รวม GenericQuestionHandler เข้า BroadTopicHandler** — 1 ไฟล์จัดการทั้งคำถามกว้าง + Generic
- **ลบ generic_question_handler.php** (ไม่ใช้แล้ว)
- **ย้าย Keywords เข้า ChatbotConfig.php** — staffKeywords, notStaff, skipNews
- **UX: เพิ่ม related_questions** — BroadTopic + Generic ส่งปุ่มกดเลือกสาขา/FAQ ให้ frontend
- เพิ่ม `'อ.'`, `'ผู้ช่วยศาสตราจารย์'`, `'รองศาสตราจารย์'` ใน staffKeywordsCheck
- ลบไฟล์ temp ที่ไม่ใช้แล้ว (_test_quick.php, _test_result.txt)
- ทดสอบผ่าน: 13/13 (chatbot) + 5/5 (related_questions)

### 9 กุมภาพันธ์ 2026 (Staff + BroadTopic + Tuition)
- **แก้ Staff Search**: เพิ่ม `searchStaffByName()` fallback, cleanName ตัดคำนำหน้า → 44/44 PASS
- **สร้าง BroadTopicHandler**: จัดการคำถามกว้าง "ทุนการศึกษา" → แสดงรายการ FAQ
- **เพิ่มค่าเทอมครบ 10 สาขา**: FAQ 577 → 594 รายการ
- **Refactored chatbot.php** (2,366 → 2,459 บรรทัด)
  - แยก ChatbotConfig.php (616 บรรทัด) — ค่าคงที่ + config ทั้งหมด
  - แยก QueryAnalyzer.php (186 บรรทัด) — วิเคราะห์คำถาม
  - ลบ dead code: searchFAQ(), buildKeywordScoring(), buildMultiKeywordBonus()
  - Extract calculateFAQScore() ออกจาก searchFAQBroad()
- **แก้ bug และเพิ่ม accuracy**:
  - FAQ#180: เพิ่ม "วิศวกรรมอุตสาหการ" ในคำถามเพื่อแม่น dept
  - FAQ#96: เพิ่ม 'ผู้จบ' ใน skipDeptKeywords
  - FAQ#524: detectDepartment() ข้ามถ้าเจอหลาย dept
- **ผลทดสอบ: 577/577 (100.0%)** ทุก 15 categories
- ลบไฟล์ไม่ใช้ 16 ไฟล์ (SQL backups, training backups, old logs, temp scripts)

### 8 กุมภาพันธ์ 2026
- Merge 27 → 15 categories
- แก้ curriculum accuracy จาก 29.7% → 100%
- ลบ 46 FAQ course-specific ที่ซ้ำซ้อน
- เพิ่ม relatedCategories mapping
- ผลทดสอบ: 574/577 (99.5%)

### 5 กุมภาพันธ์ 2026
- เพิ่ม FAQ "ค่าเทอมวิศวกรรมคอมพิวเตอร์" (ID: 623)
- สร้าง PROJECT_FINAL.md (เอกสารสรุปโครงการ)
- ลบไฟล์ .md เก่าที่ไม่ใช้ (18 ไฟล์)
- อัปเดตเอกสารให้ตรงกับสถานะปัจจุบัน

### 4 กุมภาพันธ์ 2026
- เพิ่ม Analytics Dashboard
- เพิ่ม Feedback System
- ลบ FAQ เรื่อง "วิชาเลือกทาง" (9 รายการ)
- Reindex FAQ IDs (663 → 622)

### 30 มกราคม 2026
- เพิ่ม FAQ ทุนกู้ยืม กยศ./กรอ.
- เพิ่ม FAQ ห้องสมุด, ห้องออกกำลังกาย

### 25 มกราคม 2026
- ปรับปรุง AI System
- สร้าง export_faq_from_db.py
- Train โมเดลใหม่

---

## 🚀 การรันระบบ

### 1. รัน Web Server
```bash
# เปิด XAMPP (Apache + MySQL)
```

### 2. รัน AI Server
```bash
cd ai/api
python app.py
# Server: http://localhost:5000
```

### 3. เข้าใช้งาน
- **Chat:** http://localhost/rmutp-chatbotV2/frontend/
- **Admin:** http://localhost/rmutp-chatbotV2/admin/login.html
- **Analytics:** http://localhost/rmutp-chatbotV2/admin/analytics.html

---

## 📞 ติดต่อ

**คณะวิศวกรรมศาสตร์ มทร.พระนคร**
- 📧 eng@rmutp.ac.th
- 📞 02-836-3000
- 🌐 https://eng.rmutp.ac.th/
