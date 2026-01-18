# เอกสารทฤษฎีระบบ Chatbot สำหรับคณะวิศวกรรมศาสตร์

> เอกสารนี้รวบรวมพื้นฐานเชิงทฤษฎีและหลักการออกแบบสำหรับระบบ Chatbot ที่พัฒนาขึ้นเพื่อสนับสนุนการให้ข้อมูลคณะวิศวกรรมศาสตร์ ครอบคลุม 7 หมวดสำคัญ: Chatbot, NLP, Information Retrieval, Database, Web Architecture, API, และ UI/UX เพื่อใช้เป็นบทที่ 2 ในเล่มรายงาน

## Executive Summary (ภาพรวมเร็ว)
| หมวด | ประเด็นสำคัญ | เป้าหมายระบบ |
|------|---------------|---------------|
| Chatbot | Hybrid (Rule + NLP) | ตอบคำถามบุคลากร/สาขา/ข่าว | 
| NLP | Tokenization, Synonym Expansion, Fuzzy | เข้าใจคำถามไทยหลายรูปแบบ |
| IR | Ranking, Query Expansion | คืนคำตอบเกี่ยวข้องสูง รวดเร็ว |
| Database | RDBMS + Index + Normalization | ค้นหา บุคลากร/FAQ < 1 วินาที |
| Architecture | 3-Tier + MVC แนวคิด | แยกส่วน พัฒนา/บำรุงรักษาง่าย |
| API | RESTful + JSON + Secure | สื่อสารมาตรฐานและปลอดภัย |
| UI/UX | Conversational & Accessible | ใช้งานง่าย บริการ 24/7 |

---

## สารบัญ (Table of Contents)
1. [ระบบ Chatbot](#1-ระบบ-chatbot)
2. [Natural Language Processing (NLP)](#2-natural-language-processing-nlp)
3. [Information Retrieval](#3-information-retrieval)
4. [Database Management System](#4-database-management-system)
5. [Web Application Architecture](#5-web-application-architecture)
6. [API และ RESTful Service](#6-api-และ-restful-service)
7. [User Interface และ User Experience](#7-user-interface-และ-user-experience)
8. [สรุป](#สรุป)
9. [อ้างอิง](#อ้างอิง)

---

## 1. ระบบ Chatbot

### 1.1 ความหมายและความสำคัญ
**Chatbot** คือโปรแกรมที่จำลอง/สนับสนุนการสนทนากับมนุษย์ผ่านข้อความหรือเสียง โดยอาศัย NLP และ AI เพื่อเพิ่มความรวดเร็ว ความพร้อมให้บริการ และลดภาระงานซ้ำของบุคลากร

### 1.2 ประเภทหลัก
| ประเภท | ลักษณะ | ข้อดี | ข้อจำกัด | เหมาะกับ |
|--------|---------|-------|-----------|-----------|
| Rule-based | กฎ IF/ELSE, Pattern Matching | ควบคุมผลลัพธ์ได้ | ยืดหยุ่นต่ำ | FAQ คงที่ |
| AI-powered | ใช้ ML / DL เรียนรู้จากข้อมูล | รองรับคำถามหลากหลาย | ต้องการข้อมูล/ทรัพยากรสูง | ระบบขนาดใหญ่ |
| Hybrid | ผสม Rule + NLP | สมดุลควบคุม/ความยืดหยุ่น | ออกแบบซับซ้อนกว่า | งานองค์กรทั่วไป |

### 1.3 องค์ประกอบสำคัญ
```
ผู้ใช้ ↔ UI (Frontend) ↔ NLP Engine ↔ Dialog Manager ↔ Knowledge Base ↔ Database
```
1. **UI**: ช่องทางโต้ตอบ (Web Chat Interface)
2. **NLP Engine**: แยกวิเคราะห์ความตั้งใจ/คำหลัก
3. **Dialog Management**: กำหนดโฟลว์ ตัดสินใจขั้นต่อไป
4. **Knowledge Base**: แหล่งคำตอบ (Staff, FAQ, News)
5. **Database**: จัดเก็บและจัดทำดัชนีข้อมูล

### 1.4 คุณค่าเชิงองค์กร
- บริการ 24/7: เพิ่มการเข้าถึงข้อมูลคณะ
- Response ทันที: ลดเวลารอผู้ใช้ใหม่
- ลดงานซ้ำซ้อน: บุคลากรโฟกัสงานเชิงลึก
- ต้นทุนลด: ใช้ทรัพยากรบุคลากรอย่างมีประสิทธิภาพ
- วิเคราะห์ข้อมูล: เก็บ log ปรับปรุงคุณภาพคำตอบ

---

## 2. Natural Language Processing (NLP)

### 2.1 ความหมาย
**NLP** คือเทคนิคทำให้ระบบเข้าใจและประมวลผลภาษาธรรมชาติของมนุษย์ (ในที่นี้เน้นภาษาไทย) เพื่อแปลงข้อความเป็นโครงสร้างที่เครื่องนำไปใช้ได้

### 2.2 ขั้นตอนหลัก (Pipeline)

1. **Text Preprocessing**
```
Input: "อาจารย์สาขาคอมพิวเตอร์มีใครบ้าง?"
→ Tokenization: [อาจารย์, สาขา, คอมพิวเตอร์, มี, ใคร, บ้าง]
→ Normalization: ตัวพิมพ์เดียว, ตัด ?
```

2. **Text Analysis**: Lexical / Syntactic / Semantic

3. **Intent Recognition**: ระบุเจตนา (เช่น ขอข้อมูลบุคลากร)

4. **Entity Extraction**: ดึงหน่วยข้อมูล (ชื่อสาขา, บุคคล)

### 2.3 เทคนิคที่ใช้ในระบบ

1. **Keyword Matching**
```php
if (mb_stripos($query, 'อาจารย์') !== false) {
    return $this->searchStaff($query);
}
```

2. **Synonym Expansion**
```php
$synonyms = [
    'คอม' => 'คอมพิวเตอร์ computer cpe',
    'ไฟฟ้า' => 'electrical ee',
];
```

3. **Fuzzy Matching**: ใช้ LIKE จับคำคลาดเคลื่อน

4. **Relevance Scoring**
```php
CASE 
    WHEN question LIKE ? THEN 1.0
    WHEN keywords LIKE ? THEN 0.9
    WHEN answer LIKE ? THEN 0.8
    ELSE 0.6
END as relevance
```

### 2.4 ประเด็นเฉพาะของภาษาไทย

ความท้าทาย: ไม่มีเว้นวรรค, คำผสมยาว, คำย่อ, วรรณยุกต์
แนวทาง: UTF-8 / mb_* ฟังก์ชัน / พจนานุกรมคำพ้อง / Regex

---

## 3. Information Retrieval

### 3.1 ความหมาย
**IR**: กระบวนการดึงข้อมูลที่ "เกี่ยวข้อง" สูงจากคลังข้อมูลจำนวนมาก ภายใต้เวลาตอบสนองต่ำ

### 3.2 โมเดลค้นหา (หลักที่เกี่ยวข้อง)

1. **Boolean Model**
```sql
WHERE is_active = 1 AND department = 'วิศวกรรมคอมพิวเตอร์'
```

2. **Vector Space Model**: เอกสาร→เวกเตอร์, ใช้ Cosine Similarity

3. **Probabilistic Model**: ใช้โอกาสความเกี่ยวข้อง

### 3.3 การจัดอันดับ (Ranking)

สูตรทั่วไป:
```
Score = Σ(weight_field × match_field)
```
ตัวอย่างน้ำหนัก:
- question: 1.0
- keywords: 0.9
- answer: 0.8

**ตัวอย่างในโค้ด:**
```php
CASE 
    WHEN question LIKE '%อาจารย์%' THEN 1.0
    WHEN keywords LIKE '%อาจารย์%' THEN 0.9
    WHEN answer LIKE '%อาจารย์%' THEN 0.8
    ELSE 0.6
END as relevance
ORDER BY relevance DESC
```

### 3.4 Query Expansion

เป้าหมาย: เพิ่ม recall โดยเพิ่มคำพ้อง/รูปแบบสะกด

**ตัวอย่าง:**
```
Query: "อาจารย์คอม"
↓ Expansion
"อาจารย์ คอม คอมพิวเตอร์ computer cpe teacher professor"
```

### 3.5 Metrics วัดผล

Precision = ถูกต้อง / ทั้งหมด ที่ส่งกลับ

Recall = ถูกต้อง / ทั้งหมด ที่ควรเจอ

F1 = 2 × (P×R)/(P+R)

Response Time: < 1000ms (เป้าหมายระบบ)

---

## 4. Database Management System

### 4.1 ความหมาย
**DBMS**: ซอฟต์แวร์จัดเก็บ/จัดการ/ป้องกันข้อมูล ให้ใช้ร่วมได้อย่างมีโครงสร้าง

### 4.2 Relational Model

ลักษณะ: ตาราง / คีย์ / ความสัมพันธ์ / ใช้ SQL / รองรับ Transaction

**โครงสร้างตารางหลัก (ย่อ):**

```sql
-- Table: staff (บุคลากร)
CREATE TABLE staff (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name_th VARCHAR(255) NOT NULL,
    name_en VARCHAR(255),
    position_th VARCHAR(255),
    department VARCHAR(100),
    email VARCHAR(255),
    phone VARCHAR(50),
    expertise TEXT,
    is_active TINYINT DEFAULT 1
);

-- Table: faq (คำถามที่พบบ่อย)
CREATE TABLE faq (
    id INT PRIMARY KEY AUTO_INCREMENT,
    question TEXT NOT NULL,
    answer TEXT NOT NULL,
    keywords TEXT,
    category VARCHAR(100),
    is_active TINYINT DEFAULT 1
);

-- Table: chat_logs (ประวัติการสนทนา)
CREATE TABLE chat_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    session_id VARCHAR(255),
    user_message TEXT,
    bot_response TEXT,
    confidence DECIMAL(3,2),
    response_time_ms INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### 4.3 การปรับปรุงประสิทธิภาพ Query

1. **Index**: เร่งค้นหา (ต้องเลือกคอลัมน์ใช้งานบ่อย)
```sql
-- เพิ่ม Index เพื่อเร่งการค้นหา
CREATE INDEX idx_department ON staff(department);
CREATE INDEX idx_active ON staff(is_active);
CREATE FULLTEXT INDEX idx_faq_search ON faq(question, keywords, answer);
```

ประโยชน์: เร็วขึ้น / ลด full scan / ตอบสนองไว

2. **เทคนิคปรับปรุง**: ระบุ column / ลด SELECT * / ใช้ WHERE/ LIMIT
```sql
-- ไม่ดี: SELECT *
SELECT * FROM staff WHERE department = 'วิศวกรรมคอมพิวเตอร์';

-- ดีกว่า: ระบุ Column ที่ต้องการ
SELECT id, name_th, email, phone 
FROM staff 
WHERE department = 'วิศวกรรมคอมพิวเตอร์' 
  AND is_active = 1;
```

3. **Prepared Statements**: ป้องกัน SQL Injection + แคชแผน
```php
// ป้องกัน SQL Injection
$stmt = $db->prepare("SELECT * FROM staff WHERE department = ?");
$stmt->execute([$department]);
```

### 4.4 Normalization (เป้าหมายลดซ้ำ / เพิ่มความถูกต้อง)

ความหมาย: จัดตารางให้ไม่ซ้ำซ้อน ลด anomaly เวลาแก้/ลบ/เพิ่ม

ระดับที่ใช้: 1NF / 2NF / 3NF (เพียงพอสำหรับระบบนี้)

### 4.5 Transaction & ACID

Atomicity / Consistency / Isolation / Durability → รับประกันความถูกต้องข้อมูลผู้ใช้หลายคนพร้อมกัน

---

## 5. Web Application Architecture

### 5.1 3-Tier Overview

```
┌─────────────────────────────────────┐
│   Presentation Layer (Frontend)     │
│   - HTML, CSS, JavaScript           │
│   - User Interface                  │
└──────────────┬──────────────────────┘
               │ HTTP Request/Response
┌──────────────▼──────────────────────┐
│   Application Layer (Backend)       │
│   - PHP, Business Logic             │
│   - API Endpoints                   │
└──────────────┬──────────────────────┘
               │ SQL Queries
┌──────────────▼──────────────────────┐
│   Data Layer (Database)             │
│   - MySQL/MariaDB                   │
│   - Data Storage                    │
└─────────────────────────────────────┘
```

ชั้น 1 (Presentation): HTML/CSS/JS — UI และ Interaction

ชั้น 2 (Application): PHP Logic / API / Session

ชั้น 3 (Data): MySQL / Index / Integrity

### 5.2 แนวคิด MVC (เชิงตรรกะ)

**Model-View-Controller (MVC)**

```
┌──────────┐      ┌──────────┐      ┌──────────┐
│  View    │◄─────│Controller│◄─────│  Model   │
│(Frontend)│      │(Backend) │      │(Database)│
└──────────┘      └──────────┘      └──────────┘
     │                   │                 │
     └───────────────────┴─────────────────┘
              Request/Response Flow
```

Model: เชื่อม DB + กฎข้อมูล

View: แสดงผล/อินเทอร์เฟซ

Controller: รับ request → เรียก Model → เลือก View/Response

### 5.3 Client-Server Flow (ตัวอย่างเรียก Chatbot)

**แบบ Request-Response:**

```
Client (Browser)              Server (Apache+PHP)
     │                               │
     ├──── HTTP Request ────────────>│
     │     POST /chatbot.php         │
     │     Body: {message: "..."}    │
     │                               │
     │                      ┌────────┴────────┐
     │                      │ Process Request │
     │                      │ Query Database  │
     │                      │ Generate Answer │
     │                      └────────┬────────┘
     │                               │
     │<──── HTTP Response ───────────┤
     │     Status: 200 OK            │
     │     Body: {answer: "..."}     │
     │                               │
```

องค์ประกอบ: Apache (รับ/ส่ง) → PHP (Logic) → MySQL (Data)

---

## 6. API และ RESTful Service

### 6.1 API คืออะไร

**ความหมาย:** ชุดของกฎและโปรโตคอลที่ใช้ในการสื่อสารระหว่างซอฟต์แวร์

ประเภท: Web / Library / OS

### 6.2 REST หลักการสำคัญ

**REST (Representational State Transfer)**

Stateless / Client-Server / Uniform Interface / Resource-Oriented

HTTP Methods: GET / POST / PUT / DELETE (CRUD mapping)

### 6.3 รูปแบบข้อมูล: JSON

**ตัวอย่าง Request:**
```json
{
    "session_id": "sess_123456",
    "message": "อาจารย์สาขาคอมพิวเตอร์"
}
```

**ตัวอย่าง Response:**
```json
{
    "answer": "อาจารย์สาขาวิศวกรรมคอมพิวเตอร์ (ทั้งหมด 14 คน)...",
    "sources": [
        {
            "type": "staff",
            "id": 1,
            "name": "อ.นิลมิต นิลาศ"
        }
    ],
    "confidence": 0.90,
    "response_time_ms": 245
}
```

### 6.4 หลักออกแบบ API (ย่อ)

1. Endpoint สั้น อิง resource
```
Good:
POST /api/chat
GET /api/staff
GET /api/staff/:id

Bad:
POST /api/getChatResponse
GET /api/getAllStaff
```

2. ใช้รหัสสถานะมาตรฐาน
```
200 - OK (Success)
201 - Created
400 - Bad Request
401 - Unauthorized
404 - Not Found
500 - Internal Server Error
```

3. JSON Error ชัด พร้อม code/details
```json
{
    "error": "Invalid input",
    "code": "VALIDATION_ERROR",
    "details": {
        "field": "message",
        "issue": "Message cannot be empty"
    }
}
```

4. รองรับ version (/api/v1)
```
/api/v1/chat
/api/v2/chat
```

### 6.5 ความปลอดภัย (Key Points)

HTTPS: บังคับการเข้ารหัส

AuthN/AuthZ: ตรวจ API Key
```php
// API Key
if ($_SERVER['HTTP_API_KEY'] !== $valid_key) {
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized']));
}
```

Rate Limiting: ป้องกัน abuse

Input Validation: กัน injection / malformed
```php
// Sanitize input
$message = trim(strip_tags($_POST['message']));
if (empty($message)) {
    return ['error' => 'Message required'];
}
```

---

## 7. User Interface และ User Experience

### 7.1 UI หลักการ

Consistency / Simplicity / Visibility / Feedback → ลดภาระผู้ใช้

### 7.2 UX หลักการ

Usability: เรียนรู้ง่าย / เร็ว / จำง่าย / ผิดพลาดต่ำ / พึงพอใจสูง

User Flow (ย่อ): เริ่ม → ถาม → รอ → อ่าน → ถามต่อ/จบ
```
Start → ถามคำถาม → รอคำตอบ → อ่านคำตอบ → พอใจ/ถามต่อ
```

Information Architecture: Chat / Quick Actions / History
```
Home
├── Chat Interface
├── Quick Actions
│   ├── ถามเกี่ยวกับสาขา
│   ├── ถามเกี่ยวกับอาจารย์
│   └── ข่าวสาร
└── History
```

### 7.3 Responsive (Mobile-first)

**Mobile-First Approach:**
```css
/* Mobile (default) */
.chat-container {
    width: 100%;
    padding: 10px;
}

/* Tablet */
@media (min-width: 768px) {
    .chat-container {
        width: 80%;
        padding: 20px;
    }
}

/* Desktop */
@media (min-width: 1024px) {
    .chat-container {
        width: 60%;
        max-width: 1200px;
    }
}
```

### 7.4 Accessibility (WCAG 2.1)

แนวคิดหลัก: Perceivable / Operable / Understandable / Robust

### 7.5 Conversational Design (ปรับโทนมนุษย์)

หลัก: โทนเป็นกันเอง / ตั้งความคาดหวัง / ปุ่มลัด / ข้อผิดพลาดให้ทางเลือกใหม่

---

## สรุป

ระบบนี้ผสานทฤษฎีหลายแขนงเพื่อให้บริการข้อมูลคณะอย่างมีประสิทธิภาพ:

### เทคโนโลยีหลัก
1. **Natural Language Processing** - ประมวลผลภาษาไทย
2. **Information Retrieval** - ค้นหาข้อมูลที่เกี่ยวข้อง
3. **Database Management** - จัดเก็บและจัดการข้อมูล
4. **Web Application** - สถาปัตยกรรม 3-Tier
5. **RESTful API** - การสื่อสารระหว่าง Frontend-Backend
6. **UI/UX Design** - ออกแบบประสบการณ์ผู้ใช้

### จุดเด่น
✅ ตอบคำถามบุคลากร/สาขาได้ตรงเป้า
✅ รองรับภาษาไทยและคำพ้องที่หลากหลาย
✅ สถาปัตยกรรมแยกชั้นบำรุงรักษาง่าย
✅ เวลาโต้ตอบต่ำ (เป้าหมาย < 1 วินาที)
✅ ช่วยลดภาระงานตอบคำถามทั่วไป

### ทิศทางพัฒนา
- Machine Learning Ranking
- Voice Interface
- เชื่อมระบบทะเบียน / นักศึกษา
- Mobile App / Multi-channel (LINE / FB)
- ภาษาอังกฤษ

---

## อ้างอิง

1. Russell, S., & Norvig, P. (2020). *Artificial Intelligence: A Modern Approach* (4th ed.). Pearson.

2. Jurafsky, D., & Martin, J. H. (2023). *Speech and Language Processing* (3rd ed.). Pearson.

3. Manning, C. D., Raghavan, P., & Schütze, H. (2008). *Introduction to Information Retrieval*. Cambridge University Press.

4. Elmasri, R., & Navathe, S. B. (2015). *Fundamentals of Database Systems* (7th ed.). Pearson.

5. Fielding, R. T. (2000). *Architectural Styles and the Design of Network-based Software Architectures*. Doctoral dissertation, University of California, Irvine.

6. Nielsen, J. (1994). *Usability Engineering*. Morgan Kaufmann.

7. Krug, S. (2014). *Don't Make Me Think, Revisited: A Common Sense Approach to Web Usability* (3rd ed.). New Riders.

8. W3C. (2018). *Web Content Accessibility Guidelines (WCAG) 2.1*. Retrieved from https://www.w3.org/TR/WCAG21/

---

**จัดทำโดย:** ระบบ Chatbot คณะวิศวกรรมศาสตร์ มหาวิทยาลัยเทคโนโลยีราชมงคลพระนคร  
**ปรับปรุงล่าสุด:** 30 พฤศจิกายน 2568  
**เวอร์ชัน:** 1.1 (ปรับปรุงรูปแบบอ่านง่าย)
