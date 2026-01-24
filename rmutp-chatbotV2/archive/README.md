# Archive (ไฟล์เก็บประวัติ)

โฟลเดอร์นี้ใช้เก็บไฟล์สำรอง/ไฟล์ระหว่างทาง/ไฟล์ legacy เพื่อให้โฟลเดอร์หลักของโปรเจคดูสะอาด โดย **ไม่ได้ลบทิ้ง** และสามารถย้ายกลับได้เสมอ

## ทำไมถึงย้ายมาไว้ที่นี่
- ลดความสับสนว่าไฟล์ไหนคือ “ตัวจริงที่ใช้รันระบบ”
- ป้องกันเผลอ import SQL ชุดเก่าทับข้อมูลใหม่
- เก็บหลักฐานการพัฒนา/ชุดข้อมูลที่เคยใช้งานระหว่างทาง

## ไฟล์หลักที่ใช้จริง (ควรอยู่โฟลเดอร์หลัก)
- Database
  - `database/eng_chatbot.sql` (dump หลักสำหรับสร้างฐานข้อมูล)
  - `database/faq_final_2_jan2026.sql` (FAQ final ที่อัปเดตล่าสุด)
  - `database/staff_update_fixed_utf8.sql` (สคริปต์อัปเดต/แก้ UTF-8 ของ staff)
- Scripts
  - `scripts/scrape_news.php` (ดึงข่าว + cleanup)
  - `scripts/setup_scheduler.ps1` (ตั้ง Task Scheduler)
- Tests
  - `tests/test_chat_api.ps1` (ทดสอบ API บน Windows)

## ของที่ถูกย้ายมาไว้ใน archive/
- `archive/database/` : ไฟล์ SQL ชุดเก่า/ชุดทดลอง
- `archive/frontend/index.html.backup` : สำรองหน้า UI
- `archive/scripts/update_news.bat` : วิธีรันข่าวแบบเก่า (แทนด้วย Task Scheduler)
- `archive/tests/` : ไฟล์ทดสอบ/ตรวจสอบเฉพาะกิจ
