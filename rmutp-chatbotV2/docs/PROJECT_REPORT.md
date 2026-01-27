# รายงานโครงงานระบบ RMUTP Chatbot

> เอกสารฉบับรวม (All-in-One) สำหรับใช้จัดทำเล่มโครงงาน / วิทยานิพนธ์ ประกอบด้วย: บทนำ, วัตถุประสงค์, ขอบเขต, ทฤษฎีที่เกี่ยวข้อง, ออกแบบระบบ, การพัฒนา, การทดสอบ, ผลการทำงาน, สรุป, อ้างอิง และภาคผนวกการติดตั้ง

---
## สารบัญ
1. บทที่ 1 บทนำ
   - 1.1 ที่มาและความสำคัญของโครงงาน
   - 1.2 วัตถุประสงค์
   - 1.3 ขอบเขตของระบบ (Scope)
   - 1.4 ประโยชน์ที่คาดว่าจะได้รับ
   - 1.5 ข้อจำกัดของระบบ (Limitations)
2. บทที่ 2 ทฤษฎีที่เกี่ยวข้อง
3. บทที่ 3 การออกแบบระบบ
   - 3.1 สถาปัตยกรรมระบบ (3-Tier)
   - 3.2 โครงสร้างฐานข้อมูลและแบบจำลองข้อมูล
   - 3.3 โครงสร้างโปรเจคและโมดูลหลัก
   - 3.4 การออกแบบ API และรูปแบบข้อมูล
4. บทที่ 4 การพัฒนาระบบ (Implementation)
   - 4.1 เทคโนโลยีและเครื่องมือ
   - 4.2 ขั้นตอนการพัฒนา
   - 4.3 เทคนิค NLP ที่ใช้
   - 4.4 การจัดอันดับและค้นคืนข้อมูล (IR)
5. บทที่ 5 การทดสอบและประเมินผล
   - 5.1 วิธีการทดสอบ (Test Strategy)
   - 5.2 ผลการทดสอบฟังก์ชัน
   - 5.3 ประสิทธิภาพระบบ (Performance Metrics)
   - 5.4 การประเมินผู้ใช้ (User Feedback)
6. บทที่ 6 สรุปผลการดำเนินงานและอภิปรายผล
7. บทที่ 7 ข้อเสนอแนะและการพัฒนาต่อยอด
8. อ้างอิง
9. ภาคผนวก A: ขั้นตอนการติดตั้งและรันระบบ
10. ภาคผนวก B: ตัวอย่างคำถามและผลลัพธ์

---
## บทที่ 1 บทนำ

### 1.1 ที่มาและความสำคัญของโครงงาน
คณะวิศวกรรมศาสตร์มีข้อมูลจำนวนมาก เช่น รายชื่ออาจารย์ สาขาวิชา ข่าวสาร กิจกรรม และ FAQ ที่นักศึกษาและผู้สนใจสอบถามซ้ำบ่อยครั้ง การให้บุคลากรตอบแบบเดิมใช้ทรัพยากรสูงและไม่สามารถรองรับการสอบถามได้ตลอด 24 ชั่วโมง จึงพัฒนาระบบ Chatbot ที่ใช้ NLP และแนวคิด Information Retrieval เพื่อให้บริการตอบคำถามอัตโนมัติอย่างมีประสิทธิภาพและรวดเร็ว

### 1.2 วัตถุประสงค์
1. พัฒนาระบบ Chatbot รองรับภาษาไทยเพื่อค้นคืนข้อมูลบุคลากร สาขาวิชา ข่าว และ FAQ
2. ลดภาระงานตอบคำถามซ้ำ ๆ ของเจ้าหน้าที่
3. จัดเก็บและจัดระเบียบข้อมูลคณะในรูปแบบที่เข้าถึงง่าย
4. ประยุกต์ใช้เทคนิค NLP และ IR กับฐานข้อมูลจริงของคณะ
5. สร้างรากฐานสำหรับต่อยอดสู่ระบบที่ฉลาดขึ้น (Machine Learning / Multi-channel)

### 1.3 ขอบเขตของระบบ (Scope)
ครอบคลุม: รายชื่ออาจารย์ 118 คน (10 สาขา), ข้อมูลสาขา, ข่าว/กิจกรรมพื้นฐาน, FAQ เชิงข้อมูลทั่วไป, การโต้ตอบผ่านเว็บเบื้องต้น (Browser)
นอกขอบเขต: ระบบสมัครเรียน, ประมวลผลเสียงจริง, บูรณาการระบบทะเบียนนักศึกษา, Analytics ขั้นสูง

### 1.4 ประโยชน์ที่คาดว่าจะได้รับ
- ผู้ใช้งานได้รับข้อมูลรวดเร็ว ตลอด 24/7
- ลดเวลาและต้นทุนการตอบคำถามของบุคลากร
- สนับสนุนภาพลักษณ์ด้านเทคโนโลยีของคณะ
- วางรากฐานระบบอัจฉริยะเชิงภาษาไทย

### 1.5 ข้อจำกัดของระบบ (Limitations)
- ใช้การจับคีย์เวิร์ดและคำพ้องเชิง Rule-based (ยังไม่ใช้โมเดล ML)
- ไม่รองรับหลายภาษา (รองรับไทยเป็นหลัก)
- คุณภาพคำตอบขึ้นกับความครบถ้วนของฐานข้อมูล
- ระบบผู้ดูแล (Admin Dashboard) มีในระดับ MVP และยังอยู่ระหว่างปรับปรุงให้ครอบคลุมงานจัดการข้อมูลทุกส่วน
- ไม่มีระบบจัดการ Session เชิงวิเคราะห์พฤติกรรมเชิงลึก

---
## บทที่ 2 ทฤษฎีที่เกี่ยวข้อง (สรุปจากเอกสารทฤษฎี)

### 2.1 ระบบ Chatbot
ประเภท: Rule-based, AI-powered, Hybrid (เลือก Hybrid เพื่อสมดุลควบคุม+ยืดหยุ่น)
องค์ประกอบ: UI → NLP → Dialog Manager → Knowledge Base → Database
คุณค่า: บริการ 24/7, ลดงานซ้ำซ้อน, เก็บสถิติการใช้งาน

### 2.2 Natural Language Processing (NLP)
Pipeline: Preprocessing → Analysis → Intent Recognition → Entity Extraction
เทคนิคใช้จริง: Keyword Matching, Synonym Expansion, Fuzzy Matching (LIKE), Relevance Scoring
ภาษาไทย: ไม่มีเว้นวรรค, คำผสม, คำย่อ → ใช้ UTF-8, mb_* functions, พจนานุกรมคำพ้อง

### 2.3 Information Retrieval (IR)
โมเดล: Boolean / Vector Space / Probabilistic (ประยุกต์เชิงน้ำหนักฟิลด์)
Ranking: กำหนดน้ำหนัก question (1.0) keywords (0.9) answer (0.8) → ORDER BY relevance
Query Expansion: เพิ่มคำพ้อง "คอม" → "คอมพิวเตอร์ computer cpe"
Metrics: Precision, Recall, F1, Response Time (< 1000ms เป้าหมาย)

### 2.4 Database Management
RDBMS (MySQL/MariaDB) ใช้ตาราง staff, faq, news, chat_logs
Optimization: Index (BTREE / FULLTEXT), ลด SELECT *, Prepared Statements
Normalization: ถึง 3NF เพื่อลดซ้ำซ้อน
ACID: Atomicity/Consistency/Isolation/Durability รับประกันความถูกต้อง

### 2.5 Web Architecture
3-Tier: Presentation / Application / Data
MVC แนวคิด: Controller → Model → View (เชิงตรรกะ)
Client-Server Flow: Browser → Apache/PHP → MySQL → JSON Response

### 2.6 API และ REST
หลักการ: Stateless, Resource-Oriented, JSON, HTTP Methods (GET/POST/PUT/DELETE)
แนวปฏิบัติ: Endpoint สั้น, Status Code มาตรฐาน, Error JSON พร้อม details, Versioning /api/v1
Security เบื้องต้น: HTTPS, API Key, Rate Limiting (เชิงออกแบบ), Input Validation

### 2.7 UI / UX / Conversational Design
หลัก UI: Consistency / Simplicity / Visibility / Feedback
หลัก UX: Usability (เรียนรู้ง่าย เร็ว จำง่าย ผิดพลาดต่ำ พึงพอใจสูง)
Responsive: Mobile-first CSS Media Queries
Accessibility: WCAG 2.1 แนวคิด Perceivable / Operable / Understandable / Robust
Conversational: โทนเป็นกันเอง + ปุ่มลัด + ข้อผิดพลาดเสนอทางเลือกใหม่

(รายละเอียดเชิงลึกทั้งหมดอยู่ในไฟล์ `THEORY.md`)

---
## บทที่ 3 การออกแบบระบบ

### 3.1 สถาปัตยกรรมระบบ (3-Tier)
```
┌─────────────────────────────────────┐
│   Frontend (HTML/CSS/JS)            │
│   - Chat Interface / Responsive     │
└──────────────┬──────────────────────┘
               │ HTTP / JSON
┌──────────────▼──────────────────────┐
│   Backend (PHP 8 + Logic + NLP)     │
│   - Query Expansion / Ranking       │
└──────────────┬──────────────────────┘
               │ SQL (PDO)
┌──────────────▼──────────────────────┐
│   Database (MySQL/MariaDB)          │
│   - staff / faq / news / logs       │
└─────────────────────────────────────┘
```
เลือกใช้ Model แบบง่าย (PHP Functions + PDO) เพื่อความเบาและดูแลรักษาง่าย

### 3.2 แบบจำลองข้อมูล (Data Modeling)
ตารางหลัก: `staff`, `faq`, `news`, `chat_logs` (รองรับข้อความสนทนา) และสามารถขยายด้วย `sessions` ในอนาคตเพื่อบันทึกช่วงการใช้งาน

#### 3.2.1 ER Diagram (ASCII)
```
            +----------------+
            |    staff       |
            |----------------|
 PK id      | name_th        |
    name_en | position_th    |
    dept    | email          |
    phone   | expertise      |
            | is_active      |
            +--------+-------+
                     ^ (reference by)
                     | department (TEXT)
                     |
        +------------+-------------+
        |                          |
 +------+--------+         +-------+-------+
 |    faq        |         |     news      |
 |---------------|         |---------------|
 PK id           |         | PK id         |
   question      |         |   title       |
   answer        |         |   summary     |
   keywords      |         |   category    |
   category      |         |   link_url    |
   is_active     |         |   published   |
 +------+--------+         |   is_active   |
        ^                  +-------+-------+
        | (referenced via answer/keywords for query expansion)
        |
        |    +------------------------------+
        |    |         chat_logs            |
        |    |------------------------------|
        |    | PK id                        |
        +----| FK staff_id (nullable)       |
             | session_id                   |
             | user_message                 |
             | bot_response                 |
             | confidence                   |
             | response_time_ms             |
             | created_at                   |
             +------------------------------+
```
หมายเหตุ:
- ปัจจุบัน `staff.department` เป็นข้อความ (denormalized) เพื่อความง่าย อาจแยกเป็นตาราง `departments(id, name)` ในเวอร์ชันถัดไป (1:N staff → department)
- ความสัมพันธ์ `chat_logs` → `staff` เป็นแบบ N:1 (หนึ่ง log อาจอ้างถึงอาจารย์ 1 คน หรือไม่มี)
- ตาราง `faq` และ `news` ไม่มี FK ตรง แต่ใช้ใน logic NLP/IR สำหรับจับคำค้น

#### 3.2.2 คีย์และดัชนี (Keys & Indexes)
| ตาราง | Primary Key | Index/อื่นๆ | เหตุผล |
|-------|-------------|-------------|--------|
| staff | id | (department), (is_active) | ค้นตามสาขาและสถานะ |
| faq | id | FULLTEXT(question, keywords, answer) | เร่งการค้น FAQ โดยคำถาม/คำพ้อง |
| news | id | (category), (published_date) | ดึงข่าวตามหมวดและเรียงเวลา |
| chat_logs | id | (session_id), (staff_id), (created_at) | วิเคราะห์การใช้งาน/เรียงประวัติ |

#### 3.2.3 ความสมบูรณ์ข้อมูล (Data Integrity)
- ใช้ `TINYINT is_active` เพื่อ soft-disable records แทนการลบจริง
- กำหนด `UTF8MB4` ทุกคอลัมน์ข้อความเพื่อรองรับภาษาไทยและอีโมจิ
- เลี่ยง `NULL` ในฟิลด์หลัก (เช่นชื่อ) เพื่อลดเงื่อนไขตรวจสอบในแอปพลิเคชัน

#### 3.2.4 แนวทางปรับปรุงในอนาคต
1. แยกตาราง `departments` เพื่อความสอดคล้องและลดความเสี่ยงสะกดต่างกัน
2. เพิ่มตาราง `sessions(session_id, started_at, last_active, user_agent)` เพื่อวิเคราะห์การใช้งานเชิงเวลาจริง
3. เพิ่มตาราง `intent_logs(intent, matched_keywords, confidence, created_at)` สำหรับวิเคราะห์การปรับปรุง NLP
4. สร้าง Materialized View (หรือ Table cache) สำหรับสถิติ เช่น จำนวนถามต่อสาขา

#### 3.2.5 เหตุผลการไม่แยกบางตารางในเวอร์ชันนี้
- ขนาดข้อมูลบุคลากรและสาขายังเล็ก → การ denormalize department ลดจำนวน JOIN
- ต้องการความเร็วในการพัฒนา (Time-to-Deploy) มากกว่าความยืดหยุ่นสูงสุด
- ตาราง `faq` ยังมีจำนวนไม่มาก (สามารถเต็มโชคทุน FULLTEXT ได้โดยไม่สร้างดัชนีซ้ำซ้อน)

สรุป: โครงสร้างปัจจุบันเป็นการออกแบบให้สมดุลระหว่างความเรียบง่ายกับความสามารถในการขยาย (Scalability Ready) โดยระบุจุดเพิ่มตารางรองรับในอนาคตอย่างชัดเจน

### 3.3 โครงสร้างโปรเจค
```
rmutp-chatbot/
├─ frontend/ (UI)                ─ index.html
├─ backend/  (API & NLP)         ─ chatbot.php, db.php
├─ database/ (SQL scripts)       ─ schema.sql, staff.sql, faq_staff.sql, news.sql
├─ scripts/  (utility update)    ─ scrape_news.php, update_news.bat
├─ tests/    (API test scripts)  ─ test_chat_api.ps1, test_chat_api.sh
├─ README.md                     ─ เอกสารระบบใช้งาน
├─ THEORY.md                     ─ ทฤษฎีเชิงลึก
└─ PROJECT_REPORT.md             ─ รวมเล่ม (ไฟล์นี้)
```

### 3.4 การออกแบบ API
Endpoint หลัก: `POST /backend/chatbot.php`
Request:
```json
{
  "session_id": "sess_123456",
  "message": "อาจารย์สาขาคอมพิวเตอร์"
}
```
Response:
```json
{
  "answer": "อาจารย์สาขาวิศวกรรมคอมพิวเตอร์ (ทั้งหมด 14 คน)...",
  "sources": [{"type":"staff","id":1,"name":"อ.นิลมิต นิลาศ"}],
  "confidence": 0.90,
  "response_time_ms": 245
}
```
Error Format:
```json
{"error":"Invalid input","code":"VALIDATION_ERROR"}
```
หลักการ: Stateless / JSON / HTTP Status ชัดเจน / ปลอดภัยพื้นฐาน

### 3.5 Activity Diagram (Chat Interaction Flow)
แสดงลำดับกิจกรรมตั้งแต่ผู้ใช้ส่งข้อความจนระบบตอบกลับและวนลูปรอคำถามต่อไป
```
     ┌────────────────────────────────┐
     │            ผู้ใช้ (User)       │
     └───────────────┬────────────────┘
           │ พิมพ์คำถาม / ส่งข้อความ
           ▼
        ┌──────────────┐
        │ รับ Request  │ (chatbot.php)
        └───────┬──────┘
           │ ตรวจสอบรูปแบบ / message ว่าง?
        ┌───────▼──────┐
        ┌───▶ Validate Input│─┐ (ถ้าไม่ผ่าน -> error response)
        │    └───────┬──────┘ │
        │            │ผ่าน    │
        │            ▼        │
        │   ┌────────────────────┐
        │   │ Preprocess Query   │ (Trim / Lower / Remove symbols)
        │   └─────────┬──────────┘
        │             │
        │             ▼
        │   ┌────────────────────┐
        │   │ Synonym Expansion   │ (เพิ่มคำพ้อง / สาขา)
        │   └─────────┬──────────┘
        │             │
        │             ▼
        │   ┌────────────────────┐
        │   │ Intent Detection    │ (Staff? FAQ? News?)
        │   └───────┬────────────┘
        │           │
        │      ┌────▼────┐   ┌──────────┐   ┌──────────┐
        │      │Staff Flow│   │ FAQ Flow │   │ News Flow │ (แตกแขนง)
        │      └────┬────┘   └────┬─────┘   └────┬─────┘
        │           │ Query DB     │ Query DB      │ Query DB
        │           ▼              ▼               ▼
        │   ┌──────────────────────────────────────────┐
        │   │ Ranking & Merge Results (กรณีหลายแหล่ง) │
        │   └─────────┬────────────────────────────────┘
        │             │ สร้างข้อความตอบ
        │             ▼
        │   ┌────────────────────┐
        │   │ Compose Response    │ (answer + sources + confidence)
        │   └─────────┬──────────┘
        │             │
        │             ▼
        │   ┌────────────────────┐
        │   │ Log Interaction     │ (chat_logs insert)
        │   └─────────┬──────────┘
        │             │
        │             ▼ ส่งกลับ JSON
        │   ┌────────────────────┐
        └──▶│ Send Response       │
       └─────────┬──────────┘
            │
            ▼
          ┌──────────────┐
          │ Wait Next Q? │───(Yes)──▶ วนกลับ Preprocess
          └───────┬──────┘
             │(No)
             ▼
         ┌────────┐
         │  End   │
         └────────┘
```

คำอธิบายเพิ่มเติม:
- จุดตัดสินใจหลักอยู่ที่ Intent Detection เพื่อเลือกเส้นทางค้นหา
- แต่ละ Flow (Staff / FAQ / News) ใช้รูปแบบ Query และ Ranking ต่างกันเล็กน้อย
- ส่วน Log แยกออกจากการสร้างคำตอบเพื่อไม่บล็อกการส่ง Response (แนวคิดในอนาคตสามารถ Async ได้)
- วงวนกลับ (Loop) เกิดจากผู้ใช้พิมพ์คำถามใหม่ในหน้า UI โดยยังใช้ `session_id` เดิม

โอกาสปรับปรุงในอนาคต:
1. แทรกขั้นตอน Intent Classification ด้วยโมเดล ML แทน Keyword
2. เพิ่ม Branch สำหรับ Sentiment / Smalltalk
3. ทำ Parallel Query (FAQ + Staff) แล้วรวมผลเพื่อความเร็ว
4. ใช้ Queue สำหรับ Log Interaction ลด Latency Peak

---
## บทที่ 4 การพัฒนาระบบ (Implementation)

### 4.1 เทคโนโลยีและเครื่องมือ
- Backend: PHP 8.0+, PDO
- Database: MySQL/MariaDB (utf8mb4)
- Frontend: HTML5, CSS3, JavaScript (Fetch API)
- Environment: XAMPP (Apache + MySQL)
- Testing: PowerShell / Bash script ส่ง HTTP request

### 4.2 ขั้นตอนการพัฒนา (ย่อ)
1. ออกแบบโครงสร้างข้อมูลและสร้างตาราง
2. ปรับปรุงความสะอาดข้อมูลบุคลากร (มาตรฐานชื่อสาขา, ลบซ้ำ)
3. พัฒนา `chatbot.php` (รับ message → ประมวลผล → คืน JSON)
4. เพิ่ม Synonym และแผนที่สาขา
5. เพิ่มการจัดอันดับผล FAQ และบุคลากร
6. สร้างหน้า `index.html` สำหรับโต้ตอบ
7. ทดสอบกับชุดคำถามจริง และปรับปรุง response

### 4.3 เทคนิค NLP ที่ใช้ในโค้ด
- คีย์เวิร์ดตรวจสอบ: `mb_stripos()` สำหรับภาษาไทย
- พจนานุกรมคำพ้อง (เช่น "คอม" → "คอมพิวเตอร์ computer cpe")
- ขยายคำค้น (Query Expansion) ก่อนยิงค้นหา
- การจัดอันดับเบื้องต้นด้วย CASE + น้ำหนักฟิลด์

### 4.4 Information Retrieval & Ranking
ใช้การจัดน้ำหนักฟิลด์ (question > keywords > answer) เพื่อแสดงผลคำตอบ FAQ ที่เกี่ยวข้องที่สุดก่อน และรวม staff matching ในกรณีถามเกี่ยวกับอาจารย์

---
## บทที่ 5 การทดสอบและประเมินผล

### 5.1 แนวทางการทดสอบ (Test Strategy)
- Unit-like: ทดสอบฟังก์ชันค้นหา staff ด้วยคำพ้อง
- Functional: ส่งข้อความผ่าน API ตรวจสอบโครงสร้าง JSON
- Negative: ส่งข้อความว่าง / ไม่รู้จัก → ได้ error ที่เหมาะสม
- Load (เบื้องต้น): ยิง 100 คำถามวัดเวลาตอบสนอง

### 5.2 ตัวอย่างสคริปต์ทดสอบ
PowerShell:
```powershell
Invoke-RestMethod -Method Post -Uri http://localhost/rmutp-chatbot/backend/chatbot.php -Body @{message='อาจารย์ไฟฟ้า'}
```
Bash:
```bash
curl -X POST -d "message=อาจารย์ไฟฟ้า" http://localhost/rmutp-chatbot/backend/chatbot.php
```

### 5.3 ตัวชี้วัดประสิทธิภาพ (Performance Metrics – เป้าหมาย)
- Response Time เฉลี่ย: < 800 ms (คำถามบุคลากร)
- ความถูกต้องการจำสาขา (Intent): ≥ 90%
- Precision FAQ เบื้องต้น: ≥ 0.85 (ตัวอย่างชุดเล็ก)
- Error Rate (500): < 2% ในการทดสอบ 200 คำถาม

### 5.4 การประเมินผู้ใช้ (User Feedback – Placeholder)
แบบสอบถามหลังใช้งาน (ตัวอย่าง): ความพึงพอใจ (1–5) / ความง่ายในการใช้งาน / ความเร็วตอบสนอง (รอเติมข้อมูลจริง)

---
## บทที่ 6 สรุปผลการดำเนินงานและอภิปรายผล
ระบบสามารถตอบคำถามพื้นฐานเกี่ยวกับบุคลากรและสาขาได้อย่างรวดเร็ว รองรับภาษาไทยและคำย่อที่พบบ่อย โครงสร้างข้อมูลมีความสะอาดหลังปรับมาตรฐานชื่อสาขา เวลาตอบสนองอยู่ในระดับเหมาะสมสำหรับการใช้งานเบื้องต้น ข้อจำกัดคือยังไม่ใช้ Machine Learning และยังไม่มีแดชบอร์ดจัดการ เนื้อหา FAQ ต้องเพิ่มเพื่อขยายขอบเขตการตอบ

---
## บทที่ 7 ข้อเสนอแนะและการพัฒนาต่อยอด
ระยะสั้น: เพิ่มฐานข้อมูล FAQ / ปรับปรุงคำพ้อง / เพิ่มการจัดหมวดหมู่คำถาม
ระยะกลาง: เพิ่ม Dashboard ผู้ดูแล / Analytics การใช้งาน / Multi-channel (LINE/Facebook)
ระยะยาว: นำโมเดลภาษาไทย (เช่น Thai2Transformers) มาทำ Intent Classification และ Semantic Search / รองรับเสียง / รองรับอังกฤษ

---
## อ้างอิง
1. Russell, S., & Norvig, P. (2020). Artificial Intelligence: A Modern Approach (4th ed.). Pearson.
2. Jurafsky, D., & Martin, J. H. (2023). Speech and Language Processing (3rd ed.). Pearson.
3. Manning, C. D., Raghavan, P., & Schütze, H. (2008). Introduction to Information Retrieval. Cambridge University Press.
4. Elmasri, R., & Navathe, S. B. (2015). Fundamentals of Database Systems (7th ed.). Pearson.
5. Fielding, R. T. (2000). Architectural Styles and the Design of Network-based Software Architectures. Doctoral dissertation.
6. Nielsen, J. (1994). Usability Engineering. Morgan Kaufmann.
7. Krug, S. (2014). Don't Make Me Think, Revisited. New Riders.
8. W3C. (2018). Web Content Accessibility Guidelines (WCAG) 2.1.

---
## ภาคผนวก A: ขั้นตอนการติดตั้ง

### ความต้องการระบบ
- XAMPP 8.0+ (Apache, MySQL, PHP)
- PHP 8.0+
- MySQL 8.0 / MariaDB 10.4+
- Browser รองรับ ES6

### คำสั่งติดตั้ง (Windows)
```powershell
# สร้างฐานข้อมูลและนำเข้า
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE eng_chatbot CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
C:\xampp\mysql\bin\mysql.exe -u root eng_chatbot < database/schema.sql
C:\xampp\mysql\bin\mysql.exe -u root eng_chatbot < database/staff.sql
C:\xampp\mysql\bin\mysql.exe -u root eng_chatbot < database/faq_staff.sql
C:\xampp\mysql\bin\mysql.exe -u root eng_chatbot < database/news.sql
```
เปิด `http://localhost/rmutp-chatbot/frontend/index.html`

## ภาคผนวก B: ตัวอย่างคำถาม
- "อาจารย์สาขาคอมพิวเตอร์"
- "อาจารย์ไฟฟ้า"
- "ข่าวสารล่าสุด"
- "ติดต่ออาจารย์เกรียงไกร"
- "สาขาวิชามีอะไรบ้าง"

---
**จัดทำโดย:** ระบบ Chatbot คณะวิศวกรรมศาสตร์ มทร.พระนคร  
**ปรับปรุงล่าสุด:** 30 พฤศจิกายน 2568  
**เวอร์ชันรวมเล่ม:** 1.0
