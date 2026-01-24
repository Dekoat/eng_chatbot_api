# NEXT STEPS (สิ่งที่ต้องทำต่อ)

อ้างอิงจาก ROADMAP.md (Phase 1) + ตรวจสถานะโค้ด/DB จริง  
**Last Update:** 23 มกราคม 2026

---

## 📊 สถานะปัจจุบัน (23 ม.ค. 2026)

**AI System Status:** ✅ WORKING (100% Accuracy)
- Test Results: 21/21 Natural Questions ถูกทั้งหมด
- Hybrid System: Rule-based (95% confidence) + AI fallback (47-68%)
- Current FAQ Count: **107 รายการ** (ข้อมูลจริงจาก RMUTP เท่านั้น)
- Target FAQ Count: **250-400 รายการ** (ต้องเก็บข้อมูลจริงจากเว็บ RMUTP)

**Phase 1 (AI Model):** 🟢 COMPLETE (100%)  
**Phase 2 (Integration):** 🟢 COMPLETE (100%)  
**Phase 3 (UX):** 🟡 IN PROGRESS (30%)  
**Phase 0 (Data Collection):** 🔴 BLOCKED - ต้องเก็บข้อมูลจริงก่อน (40%)

---

## 🎯 Priority 1: ขยาย FAQ Database (Phase 0)

**เป้าหมาย:** เพิ่ม FAQ จาก 107 → 250-400 รายการ

### แผนการขยาย FAQ:

#### 1. FAQ แยกตามสาขา (200 รายการ)
⚠️ **สำคัญ: ต้องใช้ข้อมูลจริงจากเว็บ RMUTP เท่านั้น ห้ามคิดเอง**

- [ ] **วิศวกรรมไฟฟ้า** - 20 FAQs
  - แหล่งข้อมูล: https://ee.eng.rmutp.ac.th/
  - หลักสูตร, วิชาเรียน, โครงงาน, ห้องแล็บ, อาจารย์
  
- [ ] **วิศวกรรมคอมพิวเตอร์** - 20 FAQs
  - แหล่งข้อมูล: https://www.cpe.eng.rmutp.ac.th/
  - Programming languages, โครงงาน, Lab facilities
  
- [ ] **วิศวกรรมเครื่องกล** - 20 FAQs
- [ ] **วิศวกรรมอุตสาหการ** - 20 FAQs
- [ ] **วิศวกรรมโยธา** - 20 FAQs
- [ ] **วิศวกรรมเคมี** - 20 FAQs
- [ ] **วิศวกรรมอิเล็กทรอนิกส์** - 20 FAQs
- [ ] **วิศวกรรมเมคคาทรอนิกส์** - 20 FAQs
- [ ] **วิศวกรรมพลังงาน** - 20 FAQs
- [ ] **วิศวกรรมวัสดุ** - 20 FAQs

#### 2. FAQ ทั่วไป (50+ รายการ)
- [ ] **ห้องเรียน & สถานที่** - 10 FAQs
  - ห้องเรียน, ห้องประชุม, canteen
  
- [ ] **หอพัก & ที่พัก** - 10 FAQs
  - หอในพื้นที่, หอนอกพื้นที่, ค่าหอ
  
- [ ] **กิจกรรม & องค์กร** - 10 FAQs
  - ชมรม, กิจกรรมนักศึกษา
  
- [ ] **ทุนการศึกษาเพิ่มเติม** - 10 FAQs
  - ทุนการศึกษา, ทุนวิจัย, work-study
  
- [ ] **ระบบการศึกษา** - 10 FAQs
  - ระบบเกรด, การลงทะเบียน, การสอบ

#### 3. ขั้นตอนการทำงาน:
1. **Generate Templates** - ใช้ AI สร้าง FAQ templates
2. **Review & Edit** - ตรวจสอบและแก้ไขให้ถูกต้อง
3. **Import to DB** - นำเข้าฐานข้อมูล
4. **Generate Variations** - สร้าง training data 3-5 รูปแบบต่อ FAQ
5. **Re-train Model** - เทรน model ใหม่ (ถ้าจำเป็น)

**Timeline:** 23-25 ม.ค. 2026 (3 วัน)  
**Tools:** 
- `ai/scripts/generate_faq_templates.py` (ถ้ามี)
- `ai/scripts/import_faq_to_db.py`
- `ai/scripts/generate_training_variations.py`

---

## งานที่เสร็จสมบูรณ์ (Phase 1):

1. Data Completion (100%)
- FAQ: 200 รายการ (ครบตามเป้า)
- Staff: 118 รายการ (ข้อมูลครบทุกฟิลด์)
- News: Auto-update + cleanup 180 วัน
- Scraper ทำงานได้ (ทดสอบแล้ว 2026-01-02)
- Log system ทำงานปกติ

2. Security (100%)
- CORS Allowlist (ไม่ใช่ wildcard *)
- Rate Limiting: 10 req/min (chatbot), 20 req/min (admin)
- Whitelist localhost สำหรับ development
- Token-based authentication (JWT-like)
- Session management (database-backed)

3. Admin System (100%)
- Login system (admin/login.html + backend/admin_login.php)
- Dashboard UI (admin/dashboard.html)
- Admin API (backend/admin_api.php)
- FAQ Management: CRUD ครบ (Create/Edit/Delete/List)
- Staff Management: List + Edit (ครบตามสโคป)
- Chat Logs Viewer
- Real-time Statistics

4. User Experience (100%)
- Better Error Messages (suggestion buttons)
- Quick action buttons (4 คำถามยอดนิยม)
- Contact information (โทร + อีเมล)
- Responsive design
- Dark mode support

5. Automation (95%)
- News scraper พร้อมใช้งาน
- Manual trigger: scripts/run_scraper.bat
- Task Scheduler: ต้องรันด้วย Admin (ผู้ใช้ทำเอง)
- Log rotation อัตโนมัติ

---

## 📋 งานที่เสร็จสมบูรณ์ (Phase 1 & 2)

### 1. AI System (100%) ✅
- **Hybrid System:** Rule-based + AI Fallback
- **Accuracy:** 100% (21/21 Natural Questions)
- **Intent Classification:** 8 categories
- **API:** Flask API on port 5000
- **Integration:** PHP Backend → AI API working

### 2. Data Completion (100%) ✅
- FAQ: 107 รายการ (ต้องขยายเป็น 250-400)
- Staff: 118 รายการ (ข้อมูลครบทุกฟิลด์)
- News: Auto-update + cleanup 180 วัน
- Scraper ทำงานได้ (ทดสอบแล้ว)
- Log system ทำงานปกติ

### 3. Security (100%) ✅
- CORS Allowlist (ไม่ใช่ wildcard *)
- Rate Limiting: 10 req/min (chatbot), 20 req/min (admin)
- Whitelist localhost สำหรับ development
- Token-based authentication (JWT-like)
- Session management (database-backed)

### 4. Admin System (100%) ✅
- Login system (admin/login.html + backend/admin_login.php)
- Dashboard UI (admin/dashboard.html)
- Admin API (backend/admin_api.php)
- FAQ Management: CRUD ครบ (Create/Edit/Delete/List)
- Staff Management: List + Edit (ครบตามสโคป)
- Chat Logs Viewer
- Real-time Statistics

### 5. User Experience (100%) ✅
- Better Error Messages (suggestion buttons)
- Quick action buttons (4 คำถามยอดนิยม)
- Contact information (โทร + อีเมล)
- Responsive design
- Dark mode support

### 6. Automation (95%) ✅
- News scraper พร้อมใช้งาน
- Manual trigger: scripts/run_scraper.bat
- Task Scheduler: ต้องรันด้วย Admin (ผู้ใช้ทำเอง)
- Log rotation อัตโนมัติ

---

## 📊 Progress Summary (23 ม.ค. 2026)

| Phase | งาน | Progress | Status |40% | 🔴 Blocked |
| **Phase 1** | AI Model Development | 100% | ✅ Complete |
| **Phase 2** | Integration (PHP ↔ AI API) | 100% | ✅ Complete |
| **Phase 3** | UX Improvements | 30% | 🟡 In Progress |
| **Phase 4** | Continuous Learning | 0% | ⚪ Pending |
| **Overall** | System Readiness | 70% | 🟡 Need Real Data |

**Progress Today (23 ม.ค. 2026):**
- ✅ ทดสอบ AI: 21/21 (100% Accuracy)
- ✅ ตรวจสอบแหล่งข้อมูล: ต้องใช้ข้อมูลจริงจาก RMUTP
- ❌ ลบ FAQ templates ออก (40 รายการ) - เป็นข้อมูลตัวอย่าง
- 📋 FAQ ปัจจุบัน: 107 รายการ (ข้อมูลจริงเท่านั้น) ไฟฟ้า (20) + คอมพิวเตอร์ (20)
- ✅ Import FAQ: +40 รายการ (107 → 147)
- ✅ อัพเดท NEXT_STEPS.md

---

## 🎯 Milestones

- ✅ **20 ม.ค. 2026:** AI Model Accuracy 50% → 100%
- ✅ **23 ม.ค. 2026:** Integration Complete, System Working
- 🎯 **25 ม.ค. 2026:** FAQ Database ≥ 250 รายการ
- 🎯 **27 ม.ค. 2026:** UX Improvements Complete
- 🎯 **29 ม.ค. 2026:** Monitoring & Stability Complete
- 🎯 **31 ม.ค. 2026:** Full Production Ready

---

## 📁 ไฟล์ที่ใช้สำหรับ FAQ Expansion

**Scripts พร้อมใช้:**
- `ai/scripts/import_faq_to_db.py` - Import FAQ จาก JSON/CSV
- `ai/scripts/generate_training_variations.py` - สร้าง variations
- `ai/scripts/test_natural_questions.py` - ทดสอบ AI accuracy
- `ai/scripts/test_priority.py` - ทดสอบ priority keywords

**Database:**
- `database/faq_*.sql` - SQL files สำหรับ import FAQ

**Data Files:**
- `ai/data/intents.json` - Intent definitions
- `ai/data/training_data.csv` - Training dataset

---

## 🎨 Priority 2: UX Improvements (Phase 3)

**เป้าหมาย:** เพิ่มประสบการณ์ผู้ใช้และความน่าเชื่อถือของระบบ

### 2.1 AI Indicators (แสดงว่าใช้ AI หรือ Rule)
- [ ] **Badge Display**
  - 🤖 AI-Powered (confidence < 95%)
  - 📋 Rule-Based (confidence = 95%)
  - แสดงใน chat bubble
  
- [ ] **Confidence Score**
  - แสดง "ความมั่นใจ: XX%" เมื่อ confidence < 80%
  - เตือนผู้ใช้ว่าอาจไม่ถูกต้อง 100%

### 2.2 Feedback System
- [ ] **Thumbs Up/Down Buttons**
  - เพิ่มปุ่ม 👍 👎 หลังคำตอบ
  - เก็บ feedback ลง database (chat_logs table)
  - ใช้วิเคราะห์ AI performance
  
- [ ] **Alternative Intent Suggestions**
  - แสดงคำถามที่เกี่ยวข้องเมื่อ confidence ต่ำ
  - "คุณหมายถึงเรื่องนี้ใช่ไหม?"

### 2.3 Loading States
- [ ] **Animation**
  - "AI กำลังคิด..." ขณะรอ API response
  - Typing indicator dots animation
  - แสดงว่าระบบกำลังทำงาน

**Timeline:** 26-27 ม.ค. 2026 (2 วัน)  
**Files to Edit:**
- `frontend/index.html` (JavaScript + CSS)
- `backend/chatbot.php` (เพิ่ม feedback endpoint)

---

## 🔧 Priority 3: API Stability & Monitoring (Phase 2 - Complete)

### 3.1 Auto-Start AI API
- [ ] **Windows Service / Task Scheduler**
  - สร้าง `scripts/start_ai_service.bat`
  - เปิด AI API อัตโนมัติตอนบูต
  - สร้าง `scripts/monitor_ai_api.ps1`
  - Health check ทุก 5 นาที, auto-restart ถ้า down

### 3.2 Logging & Monitoring
- [ ] **AI Prediction Logs**
  - บันทึกทุก prediction: question, intent, confidence, method
  - เก็บใน `ai/logs/predictions.log`
  
- [ ] **Performance Metrics**
  - Response time average
  - Error rate (API down / timeout)
  - Intent distribution (ใช้ intent ไหนบ่อย)

**Timeline:** 28-29 ม.ค. 2026 (2 วัน)

---

## 📈 Priority 4: Continuous Learning (Phase 4 - Future)

- [ ] **Export Chat Logs** - CSV format for analysis
- [ ] **Analyze Wrong Predictions** - หาคำถามที่ AI ตอบผิด
- [ ] **Re-train Model** - เทรนใหม่ด้วยข้อมูลจาก chat logs
- [ ] **A/B Testing** - ทดสอบ Rule-based vs AI

**Timeline:** TBD (เมื่อมีข้อมูล chat logs เพียงพอ)

---

## งานที่เหลือ (Stretch Goals / Optional):

1. Multi-language TH/EN (ไม่บังคับ)
- Stretch goal นอก MVP
- ถ้ามีเวลาเหลือเพื่อเพิ่มคะแนน
- เวลาโดยประมาณ: 1-2 ชั่วโมง

2. Task Scheduler Setup (ต้อง Admin)
- Script พร้อมแล้ว: scripts/setup_scheduler.ps1
- ต้องรันด้วย Administrator privileges
- หรือใช้ manual trigger: scripts/run_scraper.bat

3. Advanced Features (Future Work)
- Charts/Analytics ขั้นสูง
- Export CSV chat logs
- CAPTCHA integration
- Context-aware conversations

---

## 🎓 วิธีเดโม/ส่งงาน

### 1. เริ่มต้นระบบ

**XAMPP:**
```bash
# เปิด Apache + MySQL
C:\xampp\xampp-control.exe
```

**AI API:**
```bash
# เปิด AI API (Terminal 1)
cd c:\xampp\htdocs\rmutp-chatbotV2\ai\api
python app.py

# หรือใช้ batch file
run_api_server.bat
```

### 2. Import Database (ครั้งแรก)
```bash
# Import FAQ ล่าสุด
mysql -u root eng_chatbot < database/faq_phase4_loan.sql
mysql -u root eng_chatbot < database/faq_phase4_grade_check.sql
```

### 3. ทดสอบระบบ

**Frontend (User):**
- เปิด: `http://localhost/rmutp-chatbotV2/frontend/index.html`
- ทดสอบถามคำถาม
- ทดสอบ error handling (ถามคำถามมั่ว ๆ)

**Admin Dashboard:**
- เปิด: `http://localhost/rmutp-chatbotV2/admin/login.html`
- Login: `admin` / `admin123`
- ทดสอบ CRUD FAQ
- ทดสอบ edit Staff
- ดู Chat Logs
- ดู Statistics

**AI API Test:**
```bash
# ทดสอบ AI accuracy
cd ai\scripts
python test_natural_questions.py

# ทดสอบ priority keywords
python test_priority.py
```

**News Scraper:**
- รัน: `scripts\run_scraper.bat`
- ดู log: `scripts\logs\scraper_*.log`

---

## ✅ System Status: Production Ready (70%)

**Working Features:**
- ✅ AI System (100% Accuracy)
- ✅ Chat Interface (Responsive)
- ✅ Admin Dashboard (Full CRUD)
- ✅ Security (CORS + Rate Limit)
- ✅ News Auto-Update
- ✅ FAQ Database (107 items - ข้อมูลจริงจาก RMUTP)
- ✅ Staff Database (118 items)

**Blocked / Need Action:**
- 🔴 FAQ Expansion (107 → 250-400) - **ต้องเก็บข้อมูลจริงจากเว็บ RMUTP**
- 🟡 UX Improvements (AI badges, feedback)
- 🟡 API Monitoring & Auto-restart

**Pending:**
- ⚪ Continuous Learning System
- ⚪ Advanced Analytics

---

## 📚 Documentation Status

- README.md - Project overview
- NEXT_STEPS.md - This file (Updated: 23 ม.ค. 2026)
- DEVELOPMENT_PLAN18.md - Detailed development plan
- docs/PROJECT_STATUS_CURRENT.md - สถานะปัจจุบัน
- docs/PROJECT_REPORT.md - รายงานโปรเจค
- docs/THEORY.md - ทฤษฎีที่เกี่ยวข้อง (120+ หน้า)
- docs/Final/FINAL.md - สรุปโปรเจคฉบับสมบูรณ์
- docs/Final/FAQ_IMPROVEMENT_SUMMARY.md - สรุปการปรับปรุง FAQ
- scripts/คู่มือฉบับสมบูรณ์.md - คู่มือใช้งาน Scripts (ภาษาไทย)
- ai/README.md - AI Module Documentation
- ai/GETTING_STARTED.md - Quick start guide for AI

---

## 🚀 Next Actions (Priority Order)

1. **[IN PROGRESS]** FAQ Expansion - สร้าง FAQ templates 200+ รายการ
2. **[PENDING]** Import FAQs to Database
3. **[PENDING]** Generate Training Variations
4. **[PENDING]** UX Improvements (AI badges, feedback)
5. **[PENDING]** API Monitoring & Auto-restart

---

Last Updated: **23 มกราคม 2026**  
System Status: **Production Ready - 70%** (รอข้อมูลจริง)  
AI Accuracy: **100%** (21/21 Test Cases)  
FAQ Count: **107 รายการ** (ข้อมูลจริงจาก RMUTP เท่านั้น)  
Next Milestone: **เก็บข้อมูลจริงจากเว็บ RMUTP → 250-400 FAQs**

---

## 🔴 ลำดับงานที่ควรทำต่อ

### ~~1-4) เสร็จแล้ว~~ ✅

### 5) 🎨 UX Improvements (ต่อไป)
**เป้าหมาย:** ทำให้ API ไม่เปิดกว้าง/กันสแปม
- [ ] Task 1.2.3: CORS allowlist (เลิกใช้ `*`)
  - ทำใน: `backend/chatbot.php`, `backend/admin_login.php`
  - กำหนด allowed origins เช่น `http://localhost`, `http://localhost:80`, หรือโดเมนจริงตอน deploy
  - ต้องรองรับ preflight OPTIONS

- [ ] Task 1.2.2: Rate limiting (10 req/min per IP) + CAPTCHA หลัง 20 req
  - แนะนำทำแบบง่ายด้วย DB table หรือ file-based counter (เพราะเป็น PHP เดี่ยว)
  - ครอบคลุม endpoints หลักทั้งหมด

### 2) 🛠️ Admin Dashboard ให้ “ใช้งานจริง” (CRUD)
**เป้าหมาย:** แก้/เพิ่ม/ลบข้อมูลผ่าน UI ได้จริง
- [ ] Task 1.2.1: CRUD FAQ
  - เพิ่ม endpoint ใหม่: `backend/admin_api.php`
  - ต้อง verify token ทุก request
  - Operations:
    - list_faqs (รองรับ search/category/pagination)
    - create_faq
    - update_faq
    - delete_faq (soft delete: `is_active = 0`)

- [ ] Task 1.2.1: CRUD Staff
  - list_staff/search/department
  - update_staff
  - (optional) upload photo (เก็บ URL หรือ upload จริง)

- [ ] Task 1.2.1: View chat logs + analytics
  - list_chat_logs (limit/date range)
  - สถิติ: total chats/day, top intents/questions (เริ่มจาก top FAQs)

### 3) 📰 Auto-update News ให้ตรงสเปก Roadmap
**เป้าหมาย:** อัปเดตทุก 6 ชม. + archive ข่าวเก่า > 6 เดือน
- [ ] Task 1.1.3: ปรับ cleanup เป็น 180 วัน (6 เดือน)
  - ตอนนี้ใช้ 90 วันอยู่ใน `scripts/scrape_news.php`
- [ ] ยืนยัน Scheduled Task รันจริง + มี log ชัดเจน
  - ตรวจ log ใน `scripts/logs/`

### 4) 👥 Staff Data Cleanup (เติมข้อมูลให้ครบ)
**เป้าหมาย:** staff 118 records “สมบูรณ์”
- [ ] Task 1.1.2: เติม `office_hours`, `availability`, `room` ให้ไม่เป็น `xxxx`
- [ ] ตรวจ phone/email ที่ซ้ำ/ผิด format

### 5) 🎨 UX Improvements
- [ ] Task 1.3.1: Better error messages + “คำถามใกล้เคียง” + ปุ่มลัด
  - ตอนนี้มีแนะนำแบบข้อความทั่วไป แต่ยังไม่ทำ “suggestions จริง”
- [ ] Task 1.3.2: Multi-language (TH/EN) + toggle

---

## ✅ เส้นชัย Phase 1 (นิยามว่า Done เมื่อไหร่)
Phase 1 ถือว่า “ผ่าน” เมื่อ:
- FAQ >= 200 ✅
- Admin สามารถ CRUD FAQ/Staff/News ได้จริง
- News auto-update ทุก 6 ชม. + archive > 6 เดือน
- มี rate limiting + CORS allowlist
- Error message มี suggestion/quick buttons อย่างน้อยระดับพื้นฐาน

---

## Next Action (แนะนำทำทันที)
1) ทำ CORS allowlist + rate limit (กระทบความปลอดภัยมากที่สุด)
2) ทำ `backend/admin_api.php` แล้วค่อยต่อ UI CRUD ใน `admin/dashboard.html`
