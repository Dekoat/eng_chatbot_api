# NEXT STEPS (สิ่งที่ต้องทำต่อ)

อ้างอิงจาก ROADMAP.md (Phase 1) + ตรวจสถานะโค้ด/DB จริง ณ 2026-01-02 (Final Update)

สถานะ Phase 1: COMPLETE (95%)

งานที่เสร็จสมบูรณ์:

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

Progress Summary:

| Category | Status | Progress |
|----------|--------|----------|
| Phase 1.1: Data | Complete | 100% (3/3) |
| Phase 1.2: Features | Complete | 100% (3/3) |
| Phase 1.3: UX | Partial | 50% (1/2) |
| Overall Phase 1 | Ready | ~95% |

---

ระบบพร้อมใช้งาน:

MVP Features (100%):
- Chat interface (frontend)
- FAQ database (200 items)
- Staff database (118 items)
- News auto-update
- Admin dashboard (CRUD)
- Security (CORS + Rate limit)
- Better error handling

Production Readiness: 95%

พร้อมส่งงาน/เดโม:
- Frontend chat ใช้งานได้
- Backend API สมบูรณ์
- Admin system ครบ
- Security ทำงาน
- Documentation ครบ

---

งานที่เหลือ (Stretch Goals / Optional):

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
- Intent classification (ML/LLM)

---

Documentation Status:

- README.md - Project overview
- NEXT_STEPS.md - This file (updated)
- docs/PROJECT_STATUS_CURRENT.md - สถานะปัจจุบัน
- docs/PROJECT_REPORT.md - รายงานโปรเจค
- docs/THEORY.md - ทฤษฎีที่เกี่ยวข้อง (120+ หน้า)
- docs/Final/FINAL.md - สรุปโปรเจคฉบับสมบูรณ์
- docs/Final/FAQ_IMPROVEMENT_SUMMARY.md - สรุปการปรับปรุง FAQ
- scripts/คู่มือฉบับสมบูรณ์.md - คู่มือใช้งาน Scripts (ภาษาไทย)

---

## 🎓 วิธีเดโม/ส่งงาน

### 1. เริ่มต้น XAMPP
```bash
# เปิด Apache + MySQL
C:\xampp\xampp-control.exe
```

### 2. Import Database (ครั้งแรก)
```bash
# Import FAQ ล่าสุด
mysql -u root eng_chatbot < database/faq_phase4_loan.sql
mysql -u root eng_chatbot < database/faq_phase4_grade_check.sql
```

### 3. ทดสอบระบบ

**Frontend (User):**
- เปิด: `http://localhost/rmutp-chatbot/frontend/index.html`
- ทดสอบถามคำถาม
- ทดสอบ error handling (ถามคำถามมั่ว ๆ)

**Admin Dashboard:**
- เปิด: `http://localhost/rmutp-chatbot/admin/login.html`
- Login: `admin` / `admin123`
- ทดสอบ CRUD FAQ
- ทดสอบ edit Staff
- ดู Chat Logs
- ดู Statistics

**News Scraper:**
- รัน: `scripts\run_scraper.bat`
- ดู log: `scripts\logs\scraper_*.log`

---

## ✅ เส้นชัย: ระบบพร้อมส่งงาน!

**Phase 1 Complete (~95%)**  
**MVP Ready (100%)**  
**Production Ready (95%)**

🎉 **Congratulations! โปรเจกต์พร้อมเดโม/ส่งงาน** 🎉

---

Last Updated: 15 มกราคม 2026 (Structure Update)

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
