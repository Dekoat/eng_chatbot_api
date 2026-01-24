# RMUTP Chatbot - ระบบแชทบอทคณะวิศวกรรมศาสตร์

ระบบตอบคำถามอัจฉริยะสำหรับนักศึกษาและผู้สนใจคณะวิศวกรรมศาสตร์ 
มหาวิทยาลัยเทคโนโลยีราชมงคลพระนคร

เทคโนโลยี: PHP 8.0+ | MySQL 8.0 | JavaScript ES6+

---

## สารบัญ

- [ภาพรวมระบบ](#ภาพรวมระบบ)
- [คุณสมบัติหลัก](#คุณสมบัติหลัก)
- [สถาปัตยกรรมระบบ](#สถาปัตยกรรมระบบ)
- [การติดตั้งและใช้งาน](#การติดตั้งและใช้งาน)
- [โครงสร้างโปรเจค](#โครงสร้างโปรเจค)
- [API Documentation](#api-documentation)
- [ฐานข้อมูล](#ฐานข้อมูล)
- [ทฤษฎีที่เกี่ยวข้อง](#ทฤษฎีที่เกี่ยวข้อง)
- [การพัฒนาต่อยอด](#การพัฒนาต่อยอด)

---

## ภาพรวมระบบ

วัตถุประสงค์:
- ให้บริการตอบคำถามอัตโนมัติตลอด 24/7
- ลดภาระงานของเจ้าหน้าที่ในการตอบคำถามซ้ำๆ
- รวบรวมข้อมูลสำคัญไว้ในที่เดียว (อาจารย์, สาขาวิชา, ข่าวสาร, FAQ)
- เพิ่มประสิทธิภาพการให้บริการด้วย NLP และ AI

กลุ่มเป้าหมาย:
- นักเรียน/นักศึกษาที่สนใจสมัครเข้าศึกษา
- นักศึกษาปัจจุบันที่ต้องการข้อมูล
- ผู้ปกครอง
- บุคคลทั่วไปที่สนใจคณะวิศวกรรมศาสตร์

---

## คุณสมบัติหลัก

1. ตอบคำถามอัจฉริยะ
- รองรับภาษาไทยและคำพูดธรรมชาติ
- ค้นหาข้อมูลจาก FAQ, อาจารย์, ข่าวสาร
- จัดอันดับความเกี่ยวข้อง (Relevance Ranking)
- แสดงคะแนนความมั่นใจ (Confidence Score)

2. ข้อมูลบุคลากร
- ข้อมูลอาจารย์ 118 คน จาก 10 สาขา
- อีเมล, เบอร์โทร, ความเชี่ยวชาญ
- แยกตามสาขาวิชา
- ค้นหาด้วยชื่อ, สาขา, ความเชี่ยวชาญ

3. ข้อมูลสาขาวิชา
- วิศวกรรมคอมพิวเตอร์ (14 คน)
- วิศวกรรมไฟฟ้า (16 คน)
- วิศวกรรมโยธา (12 คน)
- วิศวกรรมอิเล็กทรอนิกส์และโทรคมนาคม (14 คน)
- วิศวกรรมเครื่องกล (14 คน)
- วิศวกรรมอุตสาหการ (20 คน)
- วิศวกรรมเมคคาทรอนิกส์ (6 คน)
- วิศวกรรมการผลิตเครื่องประดับ (6 คน)
- วิศวกรรมเครื่องมือและแม่พิมพ์ (6 คน)
- วิศวกรรมการจัดการอุตสาหกรรมเพื่อความยั่งยืน (10 คน)

4. ข่าวสารและกิจกรรม
- ข่าวประชาสัมพันธ์
- กิจกรรม
- ลิงก์ไปยังเว็บไซต์ต้นทาง

---

## การติดตั้งและใช้งาน

ความต้องการของระบบ:

- XAMPP 8.0+ (Apache, MySQL, PHP)
- PHP 8.0 หรือสูงกว่า
- MySQL 8.0 หรือ MariaDB 10.4+
- Browser ที่รองรับ ES6+ (Chrome, Firefox, Edge)

### ขั้นตอนการติดตั้ง

1. ติดตั้ง XAMPP

Windows:
```powershell
# ดาวน์โหลดจาก https://www.apachefriends.org/
# ติดตั้งที่ C:\xampp
```

2. Clone โปรเจค

```bash
cd C:\xampp\htdocs
git clone https://github.com/your-repo/rmutp-chatbot.git
cd rmutp-chatbot
```

3. สร้างฐานข้อมูล

ใช้ Command Line:
```bash
# Windows
cd C:\xampp\htdocs\rmutp-chatbot
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE eng_chatbot CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Import ฐานข้อมูลหลัก
C:\xampp\mysql\bin\mysql.exe -u root eng_chatbot < database/faq_phase4_loan.sql
C:\xampp\mysql\bin\mysql.exe -u root eng_chatbot < database/faq_phase4_grade_check.sql
```

4. เริ่มใช้งาน

1. เปิด XAMPP Control Panel
2. Start Apache และ MySQL
3. เปิดเบราว์เซอร์ไปที่: http://localhost/rmutp-chatbot/frontend/index.html

การทดสอบระบบ:

ตัวอย่างคำถามที่ทดสอบได้:
- "อาจารย์สาขาคอมพิวเตอร์"
- "อาจารย์ไฟฟ้า"
- "ติดต่ออาจารย์เกรียงไกร"
- "สาขาวิชามีอะไรบ้าง"
- "ข่าวสารล่าสุด"

---

## สถาปัตยกรรมระบบ

3-Tier Architecture:

```
┌─────────────────────────────────────┐
│   Frontend (Presentation Layer)     │
│   - HTML5, CSS3, JavaScript         │
│   - Responsive Design               │
│   - Chat Interface                  │
└──────────────┬──────────────────────┘
               │ HTTP/JSON API
┌──────────────▼──────────────────────┐
│   Backend (Application Layer)       │
│   - PHP 8.0+                        │
│   - Natural Language Processing     │
│   - Business Logic                  │
│   - API Endpoints                   │
└──────────────┬──────────────────────┘
               │ SQL Queries
┌──────────────▼──────────────────────┐
│   Database (Data Layer)             │
│   - MySQL/MariaDB                   │
│   - Tables: staff, faq, news        │
│   - Full-text Search Index          │
└─────────────────────────────────────┘
```

Frontend: HTML5, CSS3, JavaScript (ES6+)
Backend: PHP 8.0+, PDO
Database: MySQL 8.0 / MariaDB 10.4+
Web Server: Apache 2.4+ (XAMPP)

---

## โครงสร้างโปรเจค

```
rmutp-chatbot/
├── frontend/                 # ส่วน Frontend
│   └── index.html           # หน้าหลัก Chatbot UI
│
├── backend/                  # ส่วน Backend (API)
│   ├── chatbot.php          # API หลัก (Chat Endpoint)
│   ├── db.php               # Database Connection
│   ├── admin_login.php      # Admin Authentication
│   ├── admin_api.php        # Admin CRUD API
│   └── security.php         # CORS + Rate Limiting
│
├── admin/                    # Admin Dashboard
│   ├── login.html           # Admin Login Page
│   └── dashboard.html       # Admin Management UI
│
├── database/                 # ไฟล์ฐานข้อมูล SQL
│   ├── faq_phase3_diverse_part2.sql
│   ├── faq_phase3_lab_part1.sql
│   ├── faq_phase4_loan.sql  # FAQ: กยศ./กรอ. (7 items)
│   ├── faq_phase4_grade_check.sql  # FAQ: เช็คผลสอบ (6 items)
│   └── faq_utf8bom.sql
│
├── scripts/                  # Automation Scripts
│   ├── scrape_news.php      # ดึงข่าวจากเว็บไซต์ + Cleanup
│   ├── run_scraper.bat      # Manual Trigger
│   ├── setup_scheduler.ps1  # Windows Task Scheduler Setup
│   ├── check_logs.ps1       # ตรวจสอบ Log อัตโนมัติ
│   ├── คู่มือฉบับสมบูรณ์.md  # คู่มือรวม (ภาษาไทย)
│   └── logs/                # โฟลเดอร์เก็บ log รายวัน
│
├── image/                    # รูปภาพและ Assets
│   └── rmutp-logo1.png      # โลโก้มหาวิทยาลัย
│
├── docs/                     # เอกสาร Planning & Reports
│   ├── PROJECT_STATUS_CURRENT.md  # สถานะโปรเจคปัจจุบัน
│   ├── PROJECT_REPORT.md    # รายงานโปรเจค
│   ├── THEORY.md            # ทฤษฎีที่เกี่ยวข้อง (120+ หน้า)
│   ├── README.md            # ภาพรวมเอกสาร
│   └── Final/               # เอกสารส่งงานฉบับสมบูรณ์
│       ├── FINAL.md         # สรุปโปรเจคฉบับสมบูรณ์
│       ├── FAQ_IMPROVEMENT_SUMMARY.md
│       └── FAQ_IMPROVEMENT_PLAN.md
│
├── archive/                  # ไฟล์เก่า/Backup (ไม่กระทบระบบ)
│   ├── database/            # SQL files เวอร์ชันเก่า
│   ├── frontend/            # Backup UI
│   └── scripts/             # Legacy scripts
│
├── .env                      # Environment Configuration
├── .env.example             # ตัวอย่าง Environment Variables
├── .gitignore               # Git Ignore Rules
├── README.md                # เอกสารหลัก (คู่มือนี้)
└── NEXT_STEPS.md            # แผนงานถัดไป
```

---

## API Documentation

Endpoint: POST /backend/chatbot.php

Request:
```json
{
    "session_id": "sess_123456",
    "message": "อาจารย์สาขาคอมพิวเตอร์"
}
```

Response (Success):
```json
{
    "answer": "อาจารย์สาขาวิศวกรรมคอมพิวเตอร์ (ทั้งหมด 14 คน):...",
    "sources": [{"type": "staff", "id": 1, "name": "อ.นิลมิต นิลาศ"}],
    "confidence": 0.90,
    "response_time_ms": 245
}
```

Response (Error):
```json
{
    "error": "ไม่สามารถเชื่อมต่อฐานข้อมูลได้"
}
```

HTTP Status:
- 200 OK - สำเร็จ
- 400 Bad Request - ข้อมูล Input ผิด
- 500 Internal Server Error - เกิดข้อผิดพลาด

---

## ฐานข้อมูล

โครงสร้างตารางหลัก:

#### 1. staff (บุคลากร - 118 คน)
```sql
- id, name_th, name_en
- position_th, position_en
- department, email, phone
- expertise, photo_url
- is_active, created_at, updated_at
```

#### 2. faq (คำถาม-คำตอบ)
```sql
- id, question, answer
- keywords, category
- is_active
- FULLTEXT INDEX (question, keywords, answer)
```

#### 3. news (ข่าวสาร)
```sql
- id, title, summary
- category, link_url
- published_date, is_active
```

#### 4. chat_logs (บันทึกการสนทนา)
```sql
- id, session_id
- user_message, bot_response
- confidence, response_time_ms
- created_at
```

---

## ทฤษฎีที่เกี่ยวข้อง

ระบบนี้ประยุกต์ใช้ทฤษฎีและเทคโนโลยี:

1. Natural Language Processing (NLP) - ประมวลผลภาษาไทย
2. Information Retrieval (IR) - ค้นหาและจัดอันดับ
3. Database Management - MySQL, Indexing
4. Web Architecture - 3-Tier, RESTful API
5. UI/UX Design - Conversational Design

เอกสารเต็ม: THEORY.md (120+ หน้า เหมาะสำหรับทำเล่มรายงาน)

---

## การแก้ปัญหา

1. ไม่สามารถเชื่อมต่อฐานข้อมูล
- ตรวจสอบ MySQL ทำงานใน XAMPP
- ตรวจสอบไฟล์ .env
- ตรวจสอบฐานข้อมูล eng_chatbot มีอยู่

2. API ตอบกลับ HTML แทน JSON
- เพิ่ม ini_set('display_errors', 0); ในไฟล์ PHP
- ตรวจสอบ PHP error log

3. ภาษาไทยแสดงผิด
- ใช้ utf8mb4_unicode_ci ในฐานข้อมูล
- บันทึกไฟล์เป็น UTF-8
- เพิ่ม <meta charset="UTF-8">

---

## การพัฒนาต่อยอด

ฟีเจอร์ที่วางแผน:

Phase 2:
- Machine Learning Model
- รองรับภาษาอังกฤษ
- Voice Command
- Mobile Application

Phase 3:
- เชื่อมต่อระบบทะเบียน
- Sentiment Analysis
- Multi-channel Support (LINE, Facebook)

Phase 4:
- Admin Dashboard
- Analytics & Reporting
- Auto-learning

---

## สถิติโปรเจค

- บรรทัดโค้ด: ~2,500 บรรทัด
- ข้อมูลบุคลากร: 118 คน
- สาขาวิชา: 10 สาขา
- ระยะเวลาพัฒนา: 4 เดือน

---

## ทีมพัฒนา

มหาวิทยาลัยเทคโนโลยีราชมงคลพระนคร
คณะวิศวกรรมศาสตร์

---

## ติดต่อ

- เว็บไซต์: https://eng.rmutp.ac.th
- อีเมล: eng@rmutp.ac.th
- โทรศัพท์: 02-665-3777

---

อัปเดตล่าสุด: 15 มกราคม 2026
