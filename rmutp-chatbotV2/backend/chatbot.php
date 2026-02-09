<?php
/**
 * RMUTP Chatbot API - Main Endpoint
 * Handles chat requests with FULLTEXT search
 */

// Disable error display (log errors instead)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// ===== ตั้งค่า UTF-8 encoding สำหรับ PHP =====
mb_internal_encoding('UTF-8');
mb_regex_encoding('UTF-8');
ini_set('default_charset', 'UTF-8');

if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/json; charset=utf-8');
}

// Load security helper
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/generic_question_handler.php';

// AI Helper Class - Using API first, CLI as fallback
class AIHelper {
    private $apiUrl;
    private $timeout;
    private $enabled;
    private $useAPI; // ใช้ API ก่อน (เร็วกว่า CLI)
    private $useCLI; // CLI เป็น fallback
    
    public function __construct($apiUrl = 'http://localhost:5000', $timeout = 3) {
        $this->apiUrl = rtrim($apiUrl, '/');
        $this->timeout = $timeout;
        
        // ลอง API ก่อน (เร็วกว่า 40-100x เพราะ model อยู่ใน memory)
        $this->useAPI = $this->checkHealth();
        
        // ถ้า API ไม่ได้ ใช้ CLI
        if (!$this->useAPI) {
            $this->useCLI = $this->checkCLI();
            $this->enabled = $this->useCLI;
        } else {
            $this->useCLI = false;
            $this->enabled = true;
        }
        
        error_log("AIHelper: API=" . ($this->useAPI ? "ON" : "OFF") . ", CLI=" . ($this->useCLI ? "ON" : "OFF"));
    }
    
    private function checkCLI() {
        // ตรวจสอบว่า predict_cli.py มีหรือไม่
        $scriptPath = __DIR__ . '/../ai/scripts/predict_cli.py';
        if (!file_exists($scriptPath)) {
            error_log("AI CLI: predict_cli.py not found at $scriptPath");
            return false;
        }
        
        // ทดสอบว่า Python และ Model ใช้ได้
        try {
            $testCmd = 'python "' . $scriptPath . '" "test" 2>&1';
            $output = shell_exec($testCmd);
            $result = json_decode($output, true);
            
            if ($result && isset($result['intent'])) {
                error_log("AI CLI: Available and working");
                return true;
            }
            error_log("AI CLI: Failed test - " . substr($output, 0, 200));
            return false;
        } catch (Exception $e) {
            error_log("AI CLI: Exception - " . $e->getMessage());
            return false;
        }
    }
    
    public function checkHealth() {
        try {
            $ch = curl_init($this->apiUrl . '/health');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 2);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($httpCode === 200) {
                $data = json_decode($response, true);
                return isset($data['status']) && $data['status'] === 'healthy';
            }
            return false;
        } catch (Exception $e) { return false; }
    }
    
    public function predictIntent($question) {
        if (!$this->enabled || empty(trim($question))) return null;
        
        // ใช้ API ก่อน (เร็วกว่า 40-100x)
        if ($this->useAPI) {
            $result = $this->predictIntentAPI($question);
            if ($result) return $result;
            
            // ถ้า API fail ลอง CLI
            error_log("AI API failed, trying CLI fallback");
        }
        
        // fallback to CLI
        if ($this->useCLI || $this->checkCLI()) {
            return $this->predictIntentCLI($question);
        }
        
        return null;
    }
    
    private function predictIntentCLI($question) {
        $scriptPath = __DIR__ . '/../ai/scripts/predict_cli.py';
        
        // ใช้ session cache เพื่อลดเวลา (cache 5 นาที)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $cacheKey = 'ai_prediction_' . md5($question);
        
        // ตรวจสอบ cache (เพิ่มเป็น 5 นาที เพื่อประสิทธิภาพ)
        if (isset($_SESSION[$cacheKey]) && 
            isset($_SESSION[$cacheKey . '_time']) && 
            (time() - $_SESSION[$cacheKey . '_time']) < 300) { // 5 minutes cache
            error_log("AI CLI: Using cached result for: $question");
            return $_SESSION[$cacheKey];
        }
        
        // Escape คำถามสำหรับ command line
        $escapedQuestion = addslashes($question);
        $cmd = 'python "' . $scriptPath . '" "' . $escapedQuestion . '" 2>&1';
        
        $startTime = microtime(true);
        $output = shell_exec($cmd);
        $execTime = round((microtime(true) - $startTime) * 1000, 2);
        
        if (empty($output)) {
            error_log("AI CLI: No output for: $question");
            return null;
        }
        
        $result = json_decode($output, true);
        
        if (!$result || !isset($result['intent'])) {
            error_log("AI CLI: Invalid response - " . substr($output, 0, 200));
            return null;
        }
        
        $prediction = [
            'intent' => $result['intent'],
            'confidence' => floatval($result['confidence']),
            'alternatives' => $result['alternatives'] ?? [],
            'method' => 'cli',
            'exec_time_ms' => $execTime
        ];
        
        // บันทึกลง cache
        $_SESSION[$cacheKey] = $prediction;
        $_SESSION[$cacheKey . '_time'] = time();
        
        error_log("AI CLI: $question -> {$result['intent']} (" . round($result['confidence']*100, 2) . "%) in {$execTime}ms");
        
        return $prediction;
    }
    
    private function predictIntentAPI($question) {
        // ใช้ cache ร่วมกับ CLI
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $cacheKey = 'ai_prediction_' . md5($question);
        
        // ตรวจสอบ cache (5 นาที)
        if (isset($_SESSION[$cacheKey]) && 
            isset($_SESSION[$cacheKey . '_time']) && 
            (time() - $_SESSION[$cacheKey . '_time']) < 300) {
            error_log("AI API: Using cached result for: $question");
            return $_SESSION[$cacheKey];
        }
        
        try {
            $startTime = microtime(true);
            
            $ch = curl_init($this->apiUrl . '/predict');
            $payload = json_encode(['question' => $question], JSON_UNESCAPED_UNICODE);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $execTime = round((microtime(true) - $startTime) * 1000, 2);
            
            if ($httpCode !== 200) return null;
            $result = json_decode($response, true);
            
            if (!$result || !isset($result['intent'])) return null;
            
            $prediction = [
                'intent' => $result['intent'], 
                'confidence' => floatval($result['confidence']), 
                'alternatives' => $result['alternatives'] ?? [],
                'method' => 'api',
                'exec_time_ms' => $execTime
            ];
            
            // บันทึกลง cache
            $_SESSION[$cacheKey] = $prediction;
            $_SESSION[$cacheKey . '_time'] = time();
            
            error_log("AI API: $question -> {$result['intent']} (" . round($result['confidence']*100, 2) . "%) in {$execTime}ms");
            
            return $prediction;
        } catch (Exception $e) { 
            error_log("AI API Exception: " . $e->getMessage());
            return null; 
        }
    }
    
    public function isEnabled() { return $this->enabled; }
    
    public function mapIntentToCategory($intent) {
        // Map new AI intents to categories (15 categories after merge)
        $mapping = [
            // Old format (from API server keyword rules)
            'ask_tuition' => 'loan',       // tuition รวมเข้า loan แล้ว
            'ask_staff' => 'staff', 
            'ask_admission' => 'admission',
            'ask_loan' => 'loan',
            'ask_department' => 'program',
            'ask_facility' => 'facilities', // facility/library รวมเข้า facilities
            'ask_grade' => 'general',       // information/faq รวมเข้า general
            'ask_news' => 'activities',     // sports รวมเข้า activities
            'ask_contact' => 'contact',     // location รวมเข้า contact
            
            // New format (from trained model)
            'tuition' => 'loan',            // tuition → loan
            'admission' => 'admission',
            'loan' => 'loan',
            'program' => 'program',
            'career' => 'career',
            'facilities' => 'facilities',
            'contact' => 'contact',
            'general' => 'general',
            'activities' => 'activities',
            'research' => 'research',
            'graduation' => 'graduation',
            'regulations' => 'general',
            'cooperation' => 'cooperation',
            'about' => 'about',
            'curriculum' => 'curriculum',
            'document' => 'document',
            'greeting' => null,
            'internship' => 'internship',
            
            // Merged categories (backward compat)
            'location' => 'contact',
            'sports' => 'activities',
            'library' => 'facilities',
            'information' => 'general',
            'history' => 'about',
            'fee' => 'loan',
            
            'other' => null
        ];
        return $mapping[$intent] ?? null;
    }
}

// Set CORS headers and rate limiting (skip in CLI mode)
if (php_sapi_name() !== 'cli') {
    SecurityHelper::setCORSHeaders();
    
    // Check rate limiting (10 req/min per IP)
    $clientIP = SecurityHelper::getClientIP();
    if (!SecurityHelper::isWhitelistedIP($clientIP)) {
        if (!SecurityHelper::checkRateLimit($clientIP, 10, 60)) {
            SecurityHelper::rateLimitExceeded();
        }
    }
}

// Load environment variables
if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($key, $value) = explode('=', $line, 2);
        putenv(trim($key) . '=' . trim($value));
    }
}

class Chatbot {
    private $db;
    private $startTime;
    private $ai;
    
    public function __construct() {
        $this->db = getDB();
        $this->startTime = microtime(true);
        $this->ai = new AIHelper('http://localhost:5000', 3);
    }
    
    /**
     * Main chat handler
     */
    public function handleChat($sessionId, $message) {
        // Validate input
        if (empty($message)) {
            return $this->error("Message cannot be empty");
        }
        
        // ===== Phase 1: AI Intent Classification =====
        // ใช้ AI ตรวจสอบ intent ก่อน (ถ้า AI เปิดใช้งาน)
        $aiIntent = null;
        $aiConfidence = 0;
        
        if ($this->ai->isEnabled()) {
            $prediction = $this->ai->predictIntent($message);
            if ($prediction && $prediction['confidence'] > 0.7) {
                // AI มีความมั่นใจสูง (>70%)
                $aiIntent = $prediction['intent'];
                $aiConfidence = $prediction['confidence'];
                error_log("AI Intent: {$aiIntent} (confidence: " . round($aiConfidence * 100, 2) . "%)");
                
                // ถ้า AI แนะนำให้ค้นหาข่าว
                if ($aiIntent === 'ask_news') {
                    $newsResults = $this->searchNews($message);
                    if (!empty($newsResults)) {
                        return $this->buildNewsResponse($sessionId, $message, $newsResults);
                    }
                }
                
                // ถ้า AI แนะนำให้ค้นหาบุคลากร
                // แต่ถ้าข้อความมี keyword ที่ไม่ใช่เรื่อง staff (เช่น จบการศึกษา, เงื่อนไข, ค่าเทอม) ให้ข้ามไปค้น FAQ แทน
                $notStaffKeywords = ['จบการศึกษา', 'สำเร็จการศึกษา', 'เงื่อนไขจบ', 'เกณฑ์จบ', 'เงื่อนไข',
                                     'ค่าเทอม', 'หลักสูตร', 'แผนการเรียน', 'วิชาเรียน', 'ห้องแล็บ',
                                     'ความร่วมมือ', 'กู้ยืม', 'ทุน', 'กยศ', 'กรอ', 'เอกสาร', 'สมัครเรียน'];
                $isReallyStaff = true;
                foreach ($notStaffKeywords as $nsk) {
                    if (mb_stripos($message, $nsk) !== false) {
                        $isReallyStaff = false;
                        error_log("AI said ask_staff but message contains '$nsk' - skipping staff shortcut");
                        break;
                    }
                }
                if ($aiIntent === 'ask_staff' && $isReallyStaff) {
                    $staffResults = $this->searchStaff($message);
                    if (!empty($staffResults)) {
                        return $this->buildStaffResponse($sessionId, $message, $staffResults);
                    }
                }
            }
        }
        
        // ===== Phase 2: Staff Search (ค้นหาอาจารย์ก่อน ถ้าเป็นคำถามเกี่ยวกับอาจารย์) =====
        // ตรวจสอบว่าเป็นคำถามเกี่ยวกับอาจารย์หรือไม่
        // หมายเหตุ: 'รศ' ต้องเป็น 'รศ.' เพราะ 'รศ' match กับ "การศึกษา" (กา-รศ-ึกษา) ผิด!
        // เช่นเดียวกับ 'ผศ' ต้องเป็น 'ผศ.' เพื่อความแม่นยำ
        $staffKeywordsCheck = ['อาจารย์', 'ผศ.', 'รศ.', 'ดร.', 'หัวหน้าสาขา', 'คณาจารย์', 'บุคลากร', 'ใครสอน', 'รายชื่ออาจารย์'];
        $isStaffQuestion = false;
        foreach ($staffKeywordsCheck as $keyword) {
            if (mb_stripos($message, $keyword) !== false) {
                $isStaffQuestion = true;
                error_log("Phase 2: Staff keyword '$keyword' found in message");
                break;
            }
        }
        
        // ถ้ามีคำว่า "อาจารย์" แต่บริบทเป็นเรื่อง FAQ ไม่ใช่ขอรายชื่อ → ข้าม staff search
        // เช่น "ประธานหลักสูตร", "อาจารย์ใหญ่คนแรก", "เอกสารอาจารย์นิเทศ", "แบบฟอร์มอาจารย์"
        if ($isStaffQuestion) {
            $notStaffContext = [
                'คนแรก', 'ที่ปรึกษา',
                'เอกสาร', 'แบบฟอร์ม', 'นิเทศ', 'บันทึก', 'เข้าพบ',
                'เชี่ยวชาญ', 'ความเชี่ยวชาญ', 'งานวิจัย', 'ติดตาม',
                'ระดับปริญญาเอก', 'ปริญญาเอก', 'กี่ท่าน', 'กี่คน',
                'ผู้ทรงคุณวุฒิ', 'ภายนอก', 'พัฒนาหลักสูตร',
                'มาตรฐาน', 'ผลการเรียนรู้', 'ชื่อเต็ม', 'ชื่อย่อ',
                'ผลิตบุคลากร', 'เน้นผลิต'
            ];
            foreach ($notStaffContext as $ctx) {
                if (mb_stripos($message, $ctx) !== false) {
                    $isStaffQuestion = false;
                    error_log("Phase 2: Overridden! Found notStaffContext '$ctx' → skip staff search");
                    break;
                }
            }
        }
        
        // ถ้าเป็นคำถามเกี่ยวกับอาจารย์ ให้ค้นหาจาก staff table ก่อน
        if ($isStaffQuestion) {
            $staffResults = $this->searchStaff($message);
            if (!empty($staffResults) && !isset($staffResults['_show_department_list'])) {
                // พบข้อมูลอาจารย์จาก staff table
                return $this->buildStaffResponse($sessionId, $message, $staffResults);
            }
            // ถ้าเป็นการขอดูรายชื่อทั่วไป (_show_department_list) ก็ให้ตอบได้เลย
            if (isset($staffResults['_show_department_list'])) {
                return $this->buildStaffResponse($sessionId, $message, $staffResults);
            }
        }
        
        // ===== Phase 2.5: News Search (ถ้า AI ไม่แนะนำอื่น) =====
        // Check if asking about news/activities
        // แต่ถ้าถามเกี่ยวกับ "ชมรม", "จิตอาสา", "กิจกรรม", "แข่งขัน" ให้ไปค้นหา FAQ แทน (เพราะ FAQ มีข้อมูลเหล่านี้)
        // เพิ่ม: ข้ามถ้าคำถามเกี่ยวกับ กยศ/กรอ/เอกสาร/จบการศึกษา/ห้องแล็บ/ความร่วมมือ
        $skipNewsKeywords = ['ชมรม', 'จิตอาสา', 'กิจกรรม', 'แข่งขัน',
                             'กยศ', 'กรอ', 'เอกสาร', 'กู้ยืม', 'ทุน', 'แบบฟอร์ม',
                             'จบการศึกษา', 'สำเร็จการศึกษา', 'เงื่อนไขจบ', 'เกณฑ์จบ',
                             'ห้องแล็บ', 'ห้องปฏิบัติการ', 'Lab',
                             'ความร่วมมือ', 'ร่วมมือ', 'บริษัทภายนอก',
                             'ค่าเทอม', 'หลักสูตร', 'แผนการเรียน', 'วิชาเรียน',
                             'สมัครเรียน', 'รับสมัคร', 'คุณสมบัติ',
                             'ห้องสมุด', 'หนังสือ', 'นิตยสาร', 'หนังสือพิมพ์',
                             'โรงฝึก', 'ฝึกปฏิบัติ', 'เครื่องมือ',
                             'มาตรฐาน', 'ผลการเรียนรู้', 'คาดหวัง',
                             'วิสัยทัศน์', 'พันธกิจ', 'ปรัชญา',
                             'วิชาพื้นฐาน', 'วิทยาศาสตร์', 'คณิตศาสตร์',
                             'วัตถุประสงค์', 'ปริญญานิพนธ์', 'โครงงาน',
                             'สหกิจ', 'นิเทศ', 'ความร่วมมือ'];
        $skipNews = false;
        foreach ($skipNewsKeywords as $skw) {
            if (mb_stripos($message, $skw) !== false) {
                $skipNews = true;
                break;
            }
        }
        
        // ข้าม news search ถ้า AI บอกว่าไม่ใช่ ask_news และ confidence สูง
        if ($aiIntent && $aiIntent !== 'ask_news' && $aiConfidence > 0.7) {
            $skipNews = true;
        }
        
        if (!$skipNews) {
            $newsResults = $this->searchNews($message);
            if (!empty($newsResults)) {
                return $this->buildNewsResponse($sessionId, $message, $newsResults);
            }
        }
        
        // ===== Phase 3: FAQ Search (with AI Intent Filtering) =====
        // ใช้ AI intent เพื่อ filter FAQ ให้ตรงกับสิ่งที่ต้องการมากขึ้น
        $aiCategory = null;
        if ($aiIntent && $aiConfidence > 0.4) { // ลด threshold ลงเพื่อใช้ AI มากขึ้น
            $aiCategory = $this->ai->mapIntentToCategory($aiIntent);
            error_log("AI suggests category: $aiCategory (from intent: $aiIntent, confidence: " . round($aiConfidence * 100, 2) . "%)");
        }
        
        // ค้นหา FAQ พร้อม filter ด้วย AI category
        $faqResults = $this->searchFAQBroad($message, $aiCategory);
        error_log("handleChat: FAQ search returned " . count($faqResults) . " results for '$message'" . ($aiCategory ? " (filtered by: $aiCategory)" : ""));
        
        // ===== Fallback: ถ้า AI filter แล้วไม่เจอ ลองค้นหาใหม่โดยไม่ filter =====
        if (empty($faqResults) && $aiCategory) {
            error_log("handleChat: AI filter '$aiCategory' returned 0 results, retrying without filter");
            $faqResults = $this->searchFAQBroad($message, null);
            error_log("handleChat: Retry without filter returned " . count($faqResults) . " results");
        }
        
        // ถ้า FAQ มี confidence ต่ำ (<40%) หรือ AI แนะนำให้ค้นหา staff ให้ลองค้นหา staff
        // แต่ต้องเป็นคำถามเกี่ยวกับ staff จริงๆ ไม่ใช่ fallback ไปดึง staff ทุกครั้ง
        $checkStaff = (empty($faqResults) || 
                      (isset($faqResults[0]) && floatval($faqResults[0]['relevance']) < 200)) &&
                      ($isStaffQuestion || ($aiIntent === 'ask_staff' && $aiConfidence > 0.7));
        
        // ===== Phase 4: Staff Search =====
        if ($checkStaff) {
            $staffResults = $this->searchStaff($message);
            if (!empty($staffResults)) {
                return $this->buildStaffResponse($sessionId, $message, $staffResults);
            }
        }
        
        // ===== Phase 5: Build FAQ Response (with AI Enhancement) =====
        if (!empty($faqResults)) {
            $bestMatch = $faqResults[0];
            
            // ถ้า Best Match มีคะแนนสูงมาก (>= 2000 = Exact Match) ให้ใช้คำตอบนั้นเลย ไม่ต้องเช็ค Generic
            $bestScore = floatval($bestMatch['relevance']);
            $skipGenericCheck = ($bestScore >= 2000);
            
            // ===== ตรวจสอบ Generic Questions (คำถามไม่ระบุสาขา) =====
            $isGenericQuestion = $skipGenericCheck ? false : GenericQuestionHandler::isGenericQuestion($message, $faqResults);
            
            if ($isGenericQuestion) {
                $departmentAnswers = GenericQuestionHandler::getDepartmentSpecificAnswers($faqResults);
                
                // ลดเกณฑ์จาก 2 → 1 และเช็คว่ามี FAQ หลายอัน
                if (count($departmentAnswers) >= 1 && count($faqResults) >= 3) {
                    // แสดงตัวเลือกสาขา
                    $answer = GenericQuestionHandler::buildGenericAnswer($departmentAnswers);
                    $sources = GenericQuestionHandler::buildSources($departmentAnswers);
                    $confidence = 85; // ความมั่นใจสูงเพราะให้เลือกชัดเจน
                    
                    error_log("[Chatbot] Showing generic answer with " . count($departmentAnswers) . " options");
                    
                    // Log
                    $responseTime = round((microtime(true) - $this->startTime) * 1000);
                    $this->logChat($sessionId, $message, $answer, $sources, $confidence, $responseTime);
                    
                    // ไม่ต้องแสดง related questions เพราะมีตัวเลือกอยู่แล้ว
                    return [
                        'answer' => $answer,
                        'sources' => $sources,
                        'confidence' => $confidence,
                        'response_time_ms' => $responseTime,
                        'related_questions' => [],
                        'ai_used' => false
                    ];
                } else {
                    error_log("[Chatbot] Generic detected but not enough diversity: " . count($departmentAnswers) . " depts, " . count($faqResults) . " FAQs");
                }
            }
            
            // คำนวณ Confidence (ความมั่นใจ) จากคะแนน relevance
            // ถ้ามี AI intent ที่ตรงกับ FAQ category ให้เพิ่ม confidence
            // คะแนนเต็ม 1000+ = Exact Match = 95% confidence
            // คะแนน 500+ = Phrase Match = 85% confidence
            // คะแนน 200-500 = Good Match = 60-80% confidence
            // คะแนน 100-200 = Fair Match = 40-60% confidence
            // คะแนน 50-100 = Weak Match = 20-40% confidence
            // คะแนน < 50 = Very Weak = < 20% confidence
            $rawScore = floatval($bestMatch['relevance']);
            
            // ปรับคะแนนถ้า AI สนับสนุน
            if ($aiIntent && $aiConfidence > 0.7) {
                $category = $this->ai->mapIntentToCategory($aiIntent);
                if ($category && isset($bestMatch['category']) && $bestMatch['category'] === $category) {
                    $rawScore *= 1.2; // เพิ่มคะแนน 20% ถ้า AI ยืนยัน
                    error_log("AI confirmed category '{$category}', boosting score to {$rawScore}");
                }
            }
            
            if ($rawScore >= 1000) {
                // Exact Match - ความมั่นใจสูงสุด 95%
                $confidence = 95;
            } elseif ($rawScore >= 500) {
                // Phrase Match - ความมั่นใจสูง 85%
                $confidence = 85;
            } elseif ($rawScore >= 200) {
                // Good Match - ความมั่นใจดี 60-80%
                $confidence = 60 + (($rawScore - 200) / 300) * 20;
            } elseif ($rawScore >= 100) {
                // Fair Match - ความมั่นใจปานกลาง 40-60%
                $confidence = 40 + (($rawScore - 100) / 100) * 20;
            } elseif ($rawScore >= 50) {
                // Weak Match - ความมั่นใจต่ำ 20-40%
                $confidence = 20 + (($rawScore - 50) / 50) * 20;
            } else {
                // Very Weak Match - ความมั่นใจต่ำมาก < 20%
                $confidence = ($rawScore / 50) * 20;
            }
            
            // กำหนดเกณฑ์ขั้นต่ำ - ถ้าคะแนนต่ำกว่า 35% ถือว่าไม่มีคำตอบที่เหมาะสม
            // เพิ่มจาก 20% → 35% เพื่อให้ได้คำตอบที่แม่นยำมากขึ้น
            if ($confidence < 35) {
                // Score too low, treat as no match
                $answer = "❓ ไม่พบข้อมูลที่ตรงกับคำถาม\n\n";
                $answer .= "ขออภัยครับ ไม่พบข้อมูลที่ตรงกับคำถามของคุณในขณะนี้\n\n";
                $answer .= "💡 แนะนำ:\n";
                $answer .= "• ลองถามคำถามด้วยวิธีอื่น\n";
                $answer .= "• สอบถามเรื่องทั่วไป เช่น \"รับสมัครนักศึกษา\", \"ค่าเทอม\"\n";
                $answer .= "• ถามข่าวสาร เช่น \"ข่าวล่าสุด\", \"มีกิจกรรมอะไรบ้าง\"\n\n";
                $answer .= str_repeat("─", 50) . "\n";
                $answer .= "📞 ติดต่อเจ้าหน้าที่โดยตรง:\n";
                $answer .= "โทร: 02-836-3000 | อีเมล: eng@rmutp.ac.th\n";
                $answer .= "🌐 เว็บไซต์: eng.rmutp.ac.th";
                $confidence = 0.0;
                $sources = [];
            } else {
                // Good confidence, return answer
                
                // ===== ถ้าเป็น Generic Question → แสดงตัวเลือกสาขา =====
                if ($isGenericQuestion && !empty($departmentSpecificAnswers)) {
                    $answer = "📊 คำตอบขึ้นอยู่กับสาขา\n\n";
                    $answer .= "ข้อมูลที่คุณถามมีความแตกต่างกันในแต่ละสาขา กรุณาเลือกสาขาที่คุณสนใจ:\n\n";
                    
                    $deptLabels = [
                        'electrical' => 'วิศวกรรมไฟฟ้า',
                        'computer' => 'วิศวกรรมคอมพิวเตอร์',
                        'mechanical' => 'วิศวกรรมเครื่องกล',
                        'industrial' => 'วิศวกรรมอุตสาหการ',
                        'civil' => 'วิศวกรรมโยธา',
                        'mechatronics' => 'วิศวกรรมเมคคาทรอนิกส์',
                        'electronics' => 'วิศวกรรมอิเล็กทรอนิกส์',
                        'jewelry' => 'วิศวกรรมเครื่องประดับ',
                        'tool' => 'วิศวกรรมเครื่องมือและแม่พิมพ์',
                        'sime' => 'วิศวกรรมสื่อสารและระบบอัจฉริยะ',
                        'general' => 'ทั่วไป'
                    ];
                    
                    foreach ($departmentSpecificAnswers as $idx => $deptAnswer) {
                        $deptName = $deptLabels[$deptAnswer['department']] ?? $deptAnswer['department'];
                        $answer .= ($idx + 1) . ". " . $deptName . "\n";
                        $answer .= "   🔹 " . $deptAnswer['question'] . "\n\n";
                    }
                    
                    $answer .= str_repeat("─", 50) . "\n";
                    $answer .= "💡 กรุณาถามใหม่โดยระบุสาขา เช่น:\n";
                    $answer .= "\" ค่าเทอมวิศวกรรมคอมพิวเตอร์\"\n";
                    $answer .= "\" หลักสูตรเครื่องกล\"\n";
                    $answer .= "ฯลฯ";
                    
                    $confidence = 85; // ความมั่นใจสูงเพราะให้เลือกชัดเจน
                    
                    $sources = [];
                    foreach ($departmentSpecificAnswers as $deptAnswer) {
                        $sources[] = [
                            'type' => 'faq',
                            'id' => $deptAnswer['id'],
                            'question' => $deptAnswer['question']
                        ];
                    }
                } else {
                    // ตอบปกติ (ไม่ใช่ generic question)
                    $answer = $this->formatFAQAnswer($bestMatch);
                    
                    // แสดงระดับความมั่นใจและคำเตือนตามความเหมาะสม
                    if ($confidence >= 70) {
                        // ความมั่นใจสูง (70%+) - ไม่ต้องเตือน
                        $confidenceLabel = "✅ ความมั่นใจสูง";
                    } elseif ($confidence >= 50) {
                        // ความมั่นใจปานกลาง-สูง (50-70%) - เตือนเล็กน้อย
                        $confidenceLabel = "⚠️ ความมั่นใจปานกลาง";
                        $answer .= "\n\n" . str_repeat("─", 50);
                        $answer .= "\n💡 หมายเหตุ: คำตอบนี้อาจไม่ตรงกับที่ต้องการทั้งหมด";
                        $answer .= "\nหากต้องการข้อมูลเพิ่มเติม สามารถติดต่อเจ้าหน้าที่ได้โดยตรง";
                    } else {
                        // ความมั่นใจปานกลาง-ต่ำ (35-50%) - เตือนชัดเจน
                        $confidenceLabel = "⚠️ ความมั่นใจต่ำ";
                        $answer .= "\n\n" . str_repeat("─", 50);
                        $answer .= "\n⚠️ คำเตือน: คำตอบนี้อาจไม่ตรงกับคำถามของคุณ";
                        $answer .= "\n💡 แนะนำ: ลองถามใหม่ด้วยวิธีอื่น หรือติดต่อเจ้าหน้าที่โดยตรง";
                        $answer .= "\n📞 ติดต่อ: 02-836-3000 | อีเมล: eng@rmutp.ac.th";
                    }
                    
                    // เพิ่ม view_count
                    $faqId = $bestMatch['id'];
                    $updateViewCount = $this->db->prepare("UPDATE faq SET view_count = view_count + 1 WHERE id = ?");
                    $updateViewCount->bindValue(1, $faqId, PDO::PARAM_INT);
                    $updateViewCount->execute();
                    
                    // แสดงเฉพาะคำถามแรกใน sources
                    $displayQuestion = explode('|', $bestMatch['question'])[0];
                    $displayQuestion = trim($displayQuestion);
                    
                    $sources = [[
                        'type' => 'faq',
                        'id' => $bestMatch['id'],
                        'question' => $displayQuestion
                    ]];
                }
            }
        } else {
            // No match found
            $answer = "❓ ไม่พบข้อมูลที่ตรงกับคำถาม\n\n";
            $answer .= "ขออภัยครับ ไม่พบข้อมูลที่ตรงกับคำถามของคุณในขณะนี้\n\n";
            $answer .= "💡 แนะนำ:\n";
            $answer .= "• ลองถามคำถามด้วยวิธีอื่น\n";
            $answer .= "• สอบถามเรื่องทั่วไป เช่น \"รับสมัครนักศึกษา\", \"ค่าเทอม\"\n";
            $answer .= "• ถามข่าวสาร เช่น \"ข่าวล่าสุด\", \"มีกิจกรรมอะไรบ้าง\"\n\n";
            $answer .= str_repeat("─", 50) . "\n";
            $answer .= "📞 ติดต่อเจ้าหน้าที่โดยตรง:\n";
            $answer .= "โทร: 02-836-3000 | อีเมล: eng@rmutp.ac.th\n";
            $answer .= "🌐 เว็บไซต์: eng.rmutp.ac.th";
            $confidence = 0.0;
            $sources = [];
        }
        
        // ===== Clarification System: แสดงตัวเลือกเพิ่มเติมถ้ามีคำตอบที่คล้ายกัน =====
        $alternativeAnswers = [];
        if (!empty($faqResults) && count($faqResults) >= 2 && $confidence >= 35) {
            // ตรวจสอบว่ามีคำตอบอื่นที่คะแนนใกล้เคียงกับคำตอบแรกหรือไม่
            $bestScore = floatval($faqResults[0]['relevance']);
            for ($i = 1; $i < min(3, count($faqResults)); $i++) {
                $altScore = floatval($faqResults[$i]['relevance']);
                // ถ้าคะแนนต่างกันไม่เกิน 30% ถือว่าใกล้เคียง
                $scoreDiff = abs($bestScore - $altScore);
                $scoreRatio = $bestScore > 0 ? ($scoreDiff / $bestScore) : 1;
                
                if ($scoreRatio <= 0.3 && $altScore >= 100) {
                    $altQuestion = explode('|', $faqResults[$i]['question'])[0];
                    $altQuestion = trim($altQuestion);
                    $alternativeAnswers[] = [
                        'id' => $faqResults[$i]['id'],
                        'question' => $altQuestion,
                        'score' => $altScore,
                        'category' => $faqResults[$i]['category'] ?? 'general'
                    ];
                }
            }
            
            // ถ้ามีคำตอบสำรอง แสดงให้เลือก
            if (!empty($alternativeAnswers) && $confidence < 80) {
                $answer .= "\n\n" . str_repeat("─", 50);
                $answer .= "\n🔍 คำถามอื่นที่คล้ายกัน:";
                foreach ($alternativeAnswers as $idx => $alt) {
                    $answer .= "\n   " . ($idx + 2) . ". " . $alt['question'];
                }
                $answer .= "\n\n💡 หากคำตอบข้างต้นไม่ตรงกับที่ต้องการ ลองถามใหม่ให้ชัดเจนขึ้น";
            }
        }
        
        // Get related questions from the same category
        $relatedQuestions = [];
        if (!empty($faqResults) && $confidence >= 35 && !empty($bestMatch['category'])) {
            $relatedQuestions = $this->getRelatedQuestions($bestMatch['category'], $bestMatch['id']);
        }
        
        // Log the conversation
        $responseTime = round((microtime(true) - $this->startTime) * 1000);
        $this->logChat($sessionId, $message, $answer, $sources, $confidence, $responseTime);
        
        return [
            'answer' => $answer,
            'sources' => $sources,
            'confidence' => $confidence,
            'response_time_ms' => $responseTime,
            'category' => $bestMatch['category'] ?? null,
            'related_questions' => $relatedQuestions
        ];
    }
    
    /**
     * Format FAQ answer with university branding
     */
    private function formatFAQAnswer($faq) {
        // แสดงเฉพาะคำถามแรก (ก่อน | ถ้ามี)
        $displayQuestion = explode('|', $faq['question'])[0];
        $displayQuestion = trim($displayQuestion);
        
        $answer = "💬 คำถาม: {$displayQuestion}\n\n";
        $answer .= str_repeat("─", 50) . "\n\n";
        
        // Format the actual answer
        $formattedAnswer = $this->formatAnswer($faq['answer']);
        $answer .= "✅ คำตอบ:\n{$formattedAnswer}\n\n";
        
        // Add category badge if available
        if (!empty($faq['category'])) {
            $categoryIcon = $this->getCategoryIcon($faq['category']);
            $answer .= "{$categoryIcon} หมวดหมู่: {$faq['category']}\n\n";
        }
        
        $answer .= str_repeat("─", 50) . "\n";
        $answer .= "💡 มีคำถามเพิ่มเติม? ถามได้เลยครับ!\n";
        $answer .= "หรือติดต่อ: 02-836-3000 | eng@rmutp.ac.th";
        
        return $answer;
    }
    
    /**
     * Get icon for category
     */
    private function getCategoryIcon($category) {
        $icons = [
            'ทั่วไป' => '📌',
            'การรับสมัคร' => '📝',
            'หลักสูตร' => '📚',
            'ชีวิตมหาวิทยาลัย' => '🏫',
            'เอกสารและระบบ' => '📄',
            'สิ่งอำนวยความสะดวก' => '🏢',
            'อาจารย์และบุคลากร' => '👨‍🏫',
            'ทุนและการเงิน' => '💰',
            'สหกิจศึกษา' => '💼',
            'ติดต่อฉุกเฉิน' => '🚨'
        ];
        
        return $icons[$category] ?? '📋';
    }
    
    /**
     * Format answer for better readability
     */
    private function formatAnswer($answer) {
        // ไม่แก้ไขอะไร คืนค่ากลับตามเดิม
        // เพราะคำตอบมีการจัดรูปแบบไว้แล้วใน database
        return trim($answer);
    }
    
    /**
     * Search FAQ using FULLTEXT MATCH AGAINST (most precise)
     */
    private function searchFAQ($query) {
        // Normalize and expand query with synonyms for better matching
        $normalizedQuery = $this->normalizeQuery($query);
        $expandedQuery = $this->expandQuerySynonyms($normalizedQuery);
        
        $sql = "SELECT id, question, answer,
                MATCH(question, keywords) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
                FROM faq 
                WHERE is_active = 1 
                AND MATCH(question, keywords) AGAINST(? IN NATURAL LANGUAGE MODE)
                HAVING relevance > 0
                ORDER BY relevance DESC
                LIMIT 5";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$expandedQuery, $expandedQuery]);
        $results = $stmt->fetchAll();
        
        // If no results with expanded query, try original query
        if (empty($results)) {
            $stmt->execute([$query, $query]);
            $results = $stmt->fetchAll();
        }
        
        // FULLTEXT อาจให้ score ต่ำมาก (< 1) ซึ่งไม่เหมาะสม
        // ถ้า best result มี relevance < 1.0 ให้ return [] เพื่อให้ใช้ LIKE search แทน
        if (!empty($results) && isset($results[0]['relevance']) && floatval($results[0]['relevance']) < 1.0) {
            error_log("searchFAQ: FULLTEXT score too low (" . $results[0]['relevance'] . "), skip to LIKE search");
            return [];
        }
        
        return $results;
    }
    
    /**
     * ค้นหา FAQ แบบกว้าง (LIKE search) - เวอร์ชันเร็ว
     * เปลี่ยนจาก SQL scoring เป็น PHP scoring เพื่อความเร็ว
     */
    private function searchFAQBroad($query, $filterCategory = null) {
        error_log("searchFAQBroad CALLED: query='$query', filterCategory='$filterCategory'");
        
        // ทำความสะอาดและ normalize
        $query = trim($query);
        
        // ===== ตรวจจับประเภทคำถาม (Question Intent) ก่อน normalize =====
        $intentPatterns = [
            // location รวมเข้า contact แล้ว
            'contact' => ['ติดต่อ', 'เบอร์', 'โทร', 'อีเมล', 'email', 'โทรศัพท์', 'เว็บไซต์', 'Facebook', 'Line', 'ช่องทาง', 'อยู่ที่ไหน', 'ตั้งอยู่', 'ที่ตั้ง', 'สถานที่จัดการเรียนการสอน', 'สถานที่เรียน', 'อาคาร', 'แผนที่', 'เดินทาง', 'MRT', 'BTS'],
            'definition' => ['คืออะไร', 'หมายถึง', 'ความหมาย', 'นิยาม', 'อธิบาย', 'เรียนเกี่ยวกับ'],
            'curriculum' => ['เปิดสอน', 'หลักสูตร', 'วิชา', 'สาขาวิชา', 'แขนง', 'รายวิชา', 'เรียนอะไร', 'โปรแกรม'],
            'admission' => ['รับสมัคร', 'สมัคร', 'รับนักศึกษา', 'เข้าเรียน', 'สอบเข้า', 'คัดเลือก', 'คุณสมบัติ', 'TCAS', 'โควตา'],
            'qualification' => ['วุฒิ', 'รับผู้จบ', 'จบอะไร', 'ม.6', 'ม.3', 'ปวช', 'ปวส', 'มัธยม', 'อาชีวะ', 'รับวุฒิอะไร', 'วุฒิการศึกษา', 'เรียนต่อ'],
            'grade' => ['เกรด', 'ติด F', 'ติด I', 'สอบตก', 'เรียนซ้ำ', 'แก้เกรด', 'Re-grade', 'GPA', 'GPAX', 'ระดับคะแนน', 'พ้นสภาพ', 'รีไทร์'],
            'graduation' => ['จบการศึกษา', 'สำเร็จการศึกษา', 'เกณฑ์จบ', 'เงื่อนไขจบ', 'เงื่อนไขการจบ', 'สอบจบ', 'graduation', 'ปริญญา', 'รับปริญญา'],
            // facility + library รวมเข้า facilities
            'facility' => ['ห้องปฏิบัติการ', 'ห้องแล็บ', 'ห้อง Lab', 'ห้องLab', 'lab', 'อุปกรณ์', 'สิ่งอำนวยความสะดวก', 'ห้องเรียน', 'เครื่องมือ', 'แล็บ', 'ห้องสมุด', 'library', 'หนังสือ'],
            'cooperation' => ['ความร่วมมือ', 'ร่วมมือ', 'บริษัทภายนอก', 'MOU', 'สหกิจศึกษา', 'พันธมิตร', 'หน่วยงานภายนอก'],
            // sports รวมเข้า activity
            'activity' => ['กิจกรรม', 'โครงการ', 'งานวิจัย', 'research', 'ฝึกงาน', 'สหกิจ', 'อบรม', 'กีฬา', 'กีฬาสี', 'แข่งขัน'],
            'staff' => ['อาจารย์', 'คณาจารย์', 'ผู้สอน', 'บุคลากร', 'หัวหน้าสาขา', 'ใครสอน', 'อ.', 'รายชื่ออาจารย์', 'รายชื่อ', 'ประธานหลักสูตร'],
            'duration' => ['เรียนกี่ปี', 'ระยะเวลา', 'กี่เทอม', 'กี่ภาค', 'กี่หน่วยกิต', 'ใช้เวลา', 'นานแค่ไหน'],
            // tuition รวมเข้า scholarship/loan
            'scholarship' => ['ทุน', 'กยศ', 'กรอ', 'ทุนการศึกษา', 'กู้ยืม', 'เงินกู้', 'ทุนเรียนดี', 'scholarship', 'เอกสารกู้', 'แบบฟอร์มกู้', 'ค่าเทอม', 'ค่าเล่าเรียน', 'ค่าใช้จ่าย', 'ค่าลงทะเบียน', 'ค่าธรรมเนียม', 'กี่บาท'],
            'career' => ['จบแล้วทำงาน', 'อาชีพ', 'เงินเดือน', 'ทำงานอะไร', 'ตำแหน่ง', 'บริษัท', 'งาน']
        ];
        
        // ตรวจสอบ intent ของคำถามผู้ใช้ (ใช้ $query ต้นฉบับก่อน normalize)
        $queryIntent = null;
        error_log("Starting intent detection for query: '$query'" . ($filterCategory ? " [AI Filter: $filterCategory]" : ""));
        foreach ($intentPatterns as $intent => $patterns) {
            foreach ($patterns as $pattern) {
                $pos = mb_stripos($query, $pattern);
                if ($pos !== false) {
                    $queryIntent = $intent;
                    error_log("Detected query intent: $queryIntent (pattern: '$pattern' found at pos $pos)");
                    break 2;
                }
            }
        }
        if ($queryIntent === null) {
            error_log("No query intent detected for: '$query'");
        }
        
        $normalizedQuery = $this->normalizeQuery($query);
        
        // ขยาย query ด้วย synonyms
        $expandedQuery = $this->expandQuerySynonyms($normalizedQuery);
        
        // แยกคำสำคัญ
        $keywords = $this->extractKeywords($expandedQuery);
        
        if (empty($keywords)) {
            return [];
        }
        
        // ตรวจหาหมวดหมู่
        $categoryBoost = $this->detectCategory($normalizedQuery);
        
        // ===== ตรวจจับชื่อสาขาและ department filtering =====
        // ⚠️ เรียง longer/specific patterns ก่อน shorter ones เพราะ break ที่ match แรก
        // เช่น "วิศวกรรมไฟฟ้าสื่อสาร" ต้องจับก่อน "วิศวกรรมไฟฟ้า" เพราะเป็นคนละสาขา
        $departmentMap = [
            // ยาว/เฉพาะเจาะจง ก่อน
            // อส.บ. ยั่งยืน → sime_engineering (ก่อน สื่อสาร/อัจฉริยะ)
            'อส.บ.' => 'sime_engineering',
            'ยั่งยืน' => 'sime_engineering',
            'วิศวกรรมไฟฟ้าสื่อสาร' => 'electronics_telecom_engineering',
            'ไฟฟ้าสื่อสาร' => 'electronics_telecom_engineering',
            'สื่อสารและระบบอัจฉริยะ' => 'electronics_telecom_engineering',
            'ระบบอัจฉริยะ' => 'electronics_telecom_engineering',
            'วิศวกรรมเมคคาทรอนิกส์' => 'mechatronics_engineering',
            'เมคคาทรอนิกส์' => 'mechatronics_engineering',
            'วิศวกรรมอิเล็กทรอนิกส์' => 'electronics_telecom_engineering',
            'อิเล็กทรอนิกส์' => 'electronics_telecom_engineering',
            'โทรคมนาคม' => 'electronics_telecom_engineering',
            'วิศวกรรมเครื่องประดับ' => 'jewelry_engineering',
            'วิศวกรรมเครื่องมือ' => 'tool_engineering',
            'ช่างเทคนิคคอมพิวเตอร์' => 'vocational_computer',
            'วิศวกรรมคอมพิวเตอร์' => 'computer_engineering',
            'วิศวกรรมเครื่องกล' => 'mechanical_engineering',
            'วิศวกรรมอุตสาหการ' => 'industrial_engineering',
            'วิศวกรรมโยธา' => 'civil_engineering',
            'วิศวกรรมไฟฟ้า' => 'electrical_engineering',
            // สั้น ทีหลัง
            'เครื่องประดับ' => 'jewelry_engineering',
            'เครื่องกล' => 'mechanical_engineering',
            'คอมพิวเตอร์' => 'computer_engineering',
            'อุตสาหการ' => 'industrial_engineering',
            'โยธา' => 'civil_engineering',
            'ไฟฟ้า' => 'electrical_engineering',
            'SIME' => 'sime_engineering',
            'ปวช' => 'vocational',
        ];
        
        $detectedDepartment = null;
        foreach ($departmentMap as $keyword => $dept) {
            if (mb_stripos($query, $keyword) !== false) {
                $detectedDepartment = $dept;
                error_log("Detected department from query: $keyword -> $dept");
                break;
            }
        }
        
        // ⚠️ ยกเลิก department filter ถ้าคำถามเป็นเรื่องทั่วไป (ไม่ได้ถามเฉพาะสาขา)
        // เช่น "คอมพิวเตอร์ที่คณะแรงพอไหม" ไม่ควร filter เป็น computer_engineering
        // หรือ "วุฒิ อส.บ. ต่างจาก วศ.บ." ไม่ควร filter เป็น sime_engineering
        $skipDeptKeywords = ['แรงพอ', 'สเปค', 'ต่างจาก', 'เปรียบเทียบ', 'ข้อแตกต่าง', 'ต่างกัน', 'เหมือนกัน'];
        foreach ($skipDeptKeywords as $skipKw) {
            if (mb_stripos($query, $skipKw) !== false) {
                error_log("Skip department filter: found '$skipKw' in query (generic question)");
                $detectedDepartment = null;
                break;
            }
        }
        
        // ===== Query แบบง่าย - ดึงข้อมูลที่อาจเกี่ยวข้อง =====
        // เพิ่ม filter ด้วย AI category ถ้ามี
        $sql = "SELECT f.id, f.question, f.answer, f.category, f.keywords, f.department
                FROM faq f
                WHERE f.is_active = 1 ";
        
        // เพิ่ม category filter ถ้า AI แนะนำ (แต่รวม general department ด้วยเสมอ)
        // + รวม related categories ที่ AI มักสับสน (เช่น program ↔ curriculum)
        $params = [];
        if ($filterCategory) {
            // กำหนด related categories ที่ AI มักจำแนกผิด
            $relatedCategories = [
                'program' => ['curriculum', 'about', 'general'],  // หลักสูตร↔รายวิชา↔เกี่ยวกับ↔ทั่วไป
                'curriculum' => ['program'],
                'about' => ['program', 'general'],
                'general' => ['about', 'document', 'cooperation'],
                'loan' => ['admission'],               // ค่าเทอม↔การสมัคร
                'admission' => ['loan'],               // สมัคร↔ค่าเทอม
                'document' => ['cooperation'],          // เอกสาร↔ความร่วมมือ
                'cooperation' => ['document', 'about'],  // ความร่วมมือ↔เอกสาร↔เกี่ยวกับ
            ];
            
            $categories = [$filterCategory];
            if (isset($relatedCategories[$filterCategory])) {
                $categories = array_merge($categories, $relatedCategories[$filterCategory]);
            }
            
            $catPlaceholders = implode(',', array_fill(0, count($categories), '?'));
            $sql .= "AND (f.category IN ($catPlaceholders) OR f.department = 'general') ";
            $params = array_merge($params, $categories);
            error_log("Filtering FAQ by categories: " . implode(', ', $categories) . " (including general dept)");
        }
        
        // เพิ่ม department filter ถ้าตรวจพบชื่อสาขาชัดเจน (รวม general และ student_affairs เสมอ)
        if ($detectedDepartment) {
            $sql .= "AND (f.department = ? OR f.department = 'general' OR f.department = 'student_affairs') ";
            $params[] = $detectedDepartment;
            error_log("Filtering FAQ by department: $detectedDepartment (including general & student_affairs)");
        }
        
        $sql .= "AND (
                    LOWER(TRIM(f.question)) = ? OR
                    LOWER(TRIM(f.question)) = ? OR
                    f.question LIKE ? OR
                    f.question LIKE ? OR
                    f.keywords LIKE ? OR
                    f.keywords LIKE ?";
        
        $params = array_merge($params, [
            mb_strtolower(trim($query)),           // exact match original
            mb_strtolower(trim($normalizedQuery)), // exact match normalized
            "%{$query}%",                           // LIKE original
            "%{$normalizedQuery}%",                // LIKE normalized
            "%{$query}%",                           // keywords original
            "%{$normalizedQuery}%"                 // keywords normalized
        ]);
        
        // เพิ่มเงื่อนไข LIKE สำหรับแต่ละ keyword (max 5 คำเพื่อไม่ให้ช้า)
        $limitedKeywords = array_slice($keywords, 0, 5);
        foreach ($limitedKeywords as $keyword) {
            if (mb_strlen($keyword) >= 2) {
                $sql .= " OR f.question LIKE ? OR f.keywords LIKE ?";
                $params[] = "%{$keyword}%";
                $params[] = "%{$keyword}%";
            }
        }
        
        // เรียงลำดับให้ FAQ ที่ตรง query มากกว่ามาก่อน และรวม general department
        $sql .= ") ORDER BY 
                    CASE WHEN f.question LIKE ? THEN 0 ELSE 1 END,
                    CASE WHEN f.keywords LIKE ? THEN 0 ELSE 1 END,
                    CASE WHEN f.department = 'general' THEN 0 ELSE 1 END
                 LIMIT 100";
        $params[] = "%{$query}%";
        $params[] = "%{$query}%";
        
        try {
            $stmt = $this->db->prepare($sql);
            
            // Bind parameters
            for ($i = 0; $i < count($params); $i++) {
                $stmt->bindValue($i + 1, $params[$i], PDO::PARAM_STR);
            }
            
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("searchFAQBroad: query='$query', normalized='$normalizedQuery', filterCategory='$filterCategory', found=" . count($results) . " FAQs");
            
            // Debug: แสดง FAQ IDs ที่พบ
            if (!empty($results)) {
                $ids = array_map(function($r) { return $r['id']; }, $results);
                error_log("searchFAQBroad: FAQ IDs found: " . implode(', ', $ids));
            }
            
            if (empty($results)) {
                return [];
            }
            
            // ===== คำนวณคะแนนใน PHP (เร็วกว่า SQL) =====
            foreach ($results as &$row) {
                $score = 0;
                $question = $row['question'];
                $keywords_field = $row['keywords'] ?? '';
                $answer = $row['answer'] ?? '';
                
                // สำหรับ FAQ ที่มี pipe-separated alternatives → ใช้เฉพาะคำถามแรก
                $questionFirst = (strpos($question, '|') !== false) 
                    ? trim(explode('|', $question)[0]) 
                    : $question;
                
                // [+2000 pts] EXACT MATCH - สูงสุด! (เทียบกับคำถามแรกก่อน pipe)
                if (mb_strtolower(trim($questionFirst)) === mb_strtolower(trim($query))) {
                    $score += 2000;
                    error_log("EXACT MATCH FOUND! Q: $questionFirst (+2000)");
                }
                
                // [+1000 pts] IMPORTANT PHRASE BOOST - phrases ที่สำคัญต้องมาตรงกัน
                $importantPhrases = [
                    'แผนการเรียน',
                    'แผนเรียน', 
                    'ค่าเทอม',
                    'ค่าเรียน',
                    'กยศ',
                    'กรอ',
                    'รับสมัคร',
                    'สอบเข้า',
                    'โควตา',
                    'จบการศึกษา',
                    'สำเร็จการศึกษา',
                    'เกณฑ์จบ',
                    'ห้องแล็บ',
                    'ห้อง Lab',
                    'ห้องปฏิบัติการ',
                    'ความร่วมมือ',
                    'เอกสารกู้',
                    'แบบฟอร์มกู้',
                    'ปริญญานิพนธ์',
                    'โครงงาน',
                    'แบบฟอร์ม',
                    'นิเทศ',
                    'ปรัชญา',
                    'วัตถุประสงค์',
                    'วิชาพื้นฐาน'
                ];
                foreach ($importantPhrases as $phrase) {
                    if (mb_stripos($query, $phrase) !== false) {
                        // Query มี important phrase นี้
                        if (mb_stripos($question, $phrase) !== false || mb_stripos($keywords_field, $phrase) !== false) {
                            // FAQ ก็มี phrase นี้ด้วย → boost มาก
                            $score += 1000;
                            error_log("IMPORTANT PHRASE MATCH: '$phrase' found in both query and FAQ (Q: $question) +1000");
                            break;
                        }
                    }
                }
                
                // [+1500 pts] CRITICAL KEYWORD MATCH - คำสำคัญที่ต้องตอบตรงเรื่อง
                $criticalKeywords = [
                    'เกรด F' => ['เกรด F', 'ติด F', 'grade F'],
                    'เกรด' => ['เกรด', 'GPA', 'GPAX'],
                    'รีไทร์' => ['รีไทร์', 'พ้นสภาพ', 'retire'],
                    'ติด I' => ['ติด I', 'Incomplete'],
                ];
                foreach ($criticalKeywords as $topic => $patterns) {
                    $queryHas = false;
                    $faqHas = false;
                    foreach ($patterns as $p) {
                        if (mb_stripos($query, $p) !== false) $queryHas = true;
                        if (mb_stripos($question, $p) !== false || mb_stripos($keywords_field, $p) !== false) $faqHas = true;
                    }
                    if ($queryHas && $faqHas) {
                        $score += 1500;
                        error_log("CRITICAL KEYWORD MATCH: '$topic' in query AND FAQ (Q: $question) +1500");
                        break;
                    }
                }
                
                // ตรวจสอบ intent ของคำถามใน FAQ  
                $faqIntent = null;
                foreach ($intentPatterns as $intent => $patterns) {
                    foreach ($patterns as $pattern) {
                        if (mb_stripos($question, $pattern) !== false) {
                            $faqIntent = $intent;
                            break 2;
                        }
                    }
                }
                
                // [1000 pts] Exact Match (ใช้ questionFirst สำหรับ pipe-separated)
                if (mb_strtolower(trim($questionFirst)) === mb_strtolower(trim($query))) {
                    $score += 1000;
                }
                
                // [500 pts] Phrase Match (ทั้งข้อความอยู่ใน question — ตรวจทั้ง full และ first)
                $phrasePos = mb_stripos($questionFirst, $query);
                if ($phrasePos === false) {
                    $phrasePos = mb_stripos($question, $query);
                }
                if ($phrasePos !== false) {
                    $score += 500;
                    
                    // [+300 pts] Position Bonus - ถ้าคำค้นอยู่ต้นประโยค (ตำแหน่ง 0-2)
                    if ($phrasePos <= 2) {
                        $score += 300;
                    } 
                    // [+100 pts] Position Bonus - ใกล้ต้นประโยค (ตำแหน่ง 3-8)
                    elseif ($phrasePos <= 8) {
                        $score += 100;
                    }
                    
                    // [+400 pts] Length Match Bonus - ใช้ questionFirst เพื่อไม่ให้ pipe content ทำให้ length diff สูง
                    $questionLen = mb_strlen($questionFirst);
                    $queryLen = mb_strlen($query);
                    $lengthDiff = abs($questionLen - $queryLen);
                    
                    if ($lengthDiff <= 5) {
                        // คำถามยาวใกล้เคียงมาก (+400)
                        $score += 400;
                    } elseif ($lengthDiff <= 15) {
                        // คำถามยาวใกล้เคียงปานกลาง (+200)
                        $score += 200;
                    } elseif ($lengthDiff <= 30) {
                        // คำถามยาวต่างกันพอสมควร (+50)
                        $score += 50;
                    }
                    // ถ้าต่างกันมาก (>30) ไม่ได้ bonus
                }
                
                // ===== [+400 pts / -100 pts] Intent Match Bonus/Penalty =====
                // ลด bonus จาก +800 → +400 และ penalty จาก -200 → -100
                // เพื่อให้ Phrase Match และ Length Match มีน้ำหนักมากกว่า
                if ($queryIntent !== null && $faqIntent !== null) {
                    if ($queryIntent === $faqIntent) {
                        // Intent ตรงกัน → Boost ปานกลาง
                        $score += 400;
                        error_log("Intent MATCH: query=$queryIntent, faq=$faqIntent, Q: $question (+400)");
                    } else {
                        // Intent ไม่ตรงกัน → ลดคะแนนเล็กน้อย
                        $score -= 100;
                        error_log("Intent MISMATCH: query=$queryIntent, faq=$faqIntent, Q: $question (penalty -100)");
                    }
                } else {
                    // ถ้าไม่มี intent ของข้างใดข้างหนึ่ง ไม่ลงโทษ
                    error_log("Intent NOT DETECTED: query=$queryIntent, faq=$faqIntent, Q: $question (no penalty)");
                }
                
                // ===== [+800 pts] Department/Major Keyword Boost (เพิ่มจาก 600) =====
                // ถ้าผู้ใช้พูดถึงชื่อสาขาเฉพาะ ให้เพิ่มคะแนนมากถ้า FAQ มีชื่อสาขานั้นด้วย
                $departments = [
                    'ไฟฟ้า' => ['ไฟฟ้า', 'electrical', 'EE', 'วิศวกรรมไฟฟ้า', 'Electrical Engineering'],
                    'คอมพิวเตอร์' => ['คอมพิวเตอร์', 'คอม', 'computer', 'CPE', 'CE', 'วิศวคอม', 'programming', 'โปรแกรม', 'วิศวกรรมคอมพิวเตอร์', 'Computer Engineering'],
                    'เครื่องกล' => ['เครื่องกล', 'mechanical', 'ME', 'วิศวกรรมเครื่องกล', 'Mechanical Engineering'],
                    'อุตสาหการ' => ['อุตสาหการ', 'industrial', 'IE', 'วิศวกรรมอุตสาหการ', 'Industrial Engineering'],
                    'เมคคาทรอนิกส์' => ['เมคคาทรอนิกส์', 'mechatronics', 'วิศวกรรมเมคคาทรอนิกส์', 'Mechatronics'],
                    'โยธา' => ['โยธา', 'civil', 'วิศวกรรมโยธา', 'Civil Engineering', 'ก่อสร้าง'],
                    'อิเล็กทรอนิกส์' => ['อิเล็กทรอนิกส์', 'โทรคมนาคม', 'electronics', 'ETE', 'สื่อสาร', 'ระบบอัจฉริยะ', 'telecommunication'],
                    'อส.บ' => ['อส.บ', 'อส.บ.', 'อุตสาหกรรมศาสตรบัณฑิต', 'ยั่งยืน', 'BIndTech', 'B.Ind.Tech'],
                    'SIME' => ['SIME', 'วิศวกรรมการจัดการอุตสาหกรรม', 'ความยั่งยืน', 'sustainable', 'นวัตกรรม'],
                    'วศ.ม' => ['วศ.ม', 'วศ.ม.', 'ปริญญาโท', 'มหาบัณฑิต', 'Master', 'ป.โท'],
                    'กยศ' => ['กยศ', 'กยศ.', 'กองทุนกู้ยืม', 'student loan', 'DSL', 'ทุนการศึกษา', 'เงินกู้'],
                    'กรอ' => ['กรอ', 'กรอ.', 'income contingent', 'ICL', 'กองทุนรายได้'],
                    'รับสมัคร' => ['รับสมัคร', 'TCAS', 'admission', 'สมัคร', 'เปิดรับ', 'รับตรง', 'โควตา'],
                    'มทร' => ['มทร.พระนคร', 'RMUTP', 'ราชมงคล', 'พระนคร', 'ศูนย์'],
                    'เครื่องประดับ' => ['เครื่องประดับ', 'อัญมณี', 'jewelry', 'Jewelry', 'พลอย', 'เจียระไน'],
                    'เครื่องมือแม่พิมพ์' => ['เครื่องมือ', 'แม่พิมพ์', 'Tool', 'Die', 'CNC', 'ซ่อมบำรุง'],
                    'ห้องสมุด' => ['ห้องสมุด', 'library', 'หนังสือ', 'ยืม', 'คืน'],
                    'ออกกำลังกาย' => ['ออกกำลังกาย', 'ฟิตเนส', 'fitness', 'gym', 'ยิม', 'กีฬา', 'ลู่วิ่ง']
                ];
                
                foreach ($departments as $dept => $keywords_dept) {
                    $queryHasDept = false;
                    $faqHasDept = false;
                    
                    foreach ($keywords_dept as $kw) {
                        if (mb_stripos($query, $kw) !== false) $queryHasDept = true;
                        if (mb_stripos($question, $kw) !== false || mb_stripos($answer, $kw) !== false) $faqHasDept = true;
                    }
                    
                    if ($queryHasDept && $faqHasDept) {
                        // ทั้ง query และ FAQ พูดถึงสาขาเดียวกัน → เพิ่มคะแนนมาก (เพิ่มจาก 600 → 800)
                        $score += 800;
                        error_log("Department MATCH: '$dept' found in both query and FAQ (Q: $question) +800");
                        break;
                    } elseif ($queryHasDept && !$faqHasDept) {
                        // query พูดถึงสาขา แต่ FAQ ไม่มี → ลดคะแนนมากขึ้น (เพิ่มจาก -300 → -400)
                        $score -= 400;
                        error_log("Department MISMATCH: '$dept' in query but not in FAQ (Q: $question) -400");
                    }
                }
                
                // [300 pts] Normalized Phrase Match
                if ($normalizedQuery !== $query && mb_stripos($question, $normalizedQuery) !== false) {
                    $score += 300;
                }
                
                // [500 pts] Category Match - เพิ่มน้ำหนักจาก 100 เป็น 500 (case-insensitive)
                $normalizedCategory = strtolower(trim($row['category'] ?? ''));
                $normalizedCategoryBoost = strtolower(trim($categoryBoost ?? ''));
                if (!empty($normalizedCategoryBoost) && $normalizedCategory === $normalizedCategoryBoost) {
                    $score += 500;
                    error_log("Category MATCH: '$normalizedCategoryBoost' (Q: $question) +500");
                }
                
                // [50 pts per keyword] Keywords in question
                $keywordCount = 0;
                foreach ($keywords as $keyword) {
                    if (mb_stripos($question, $keyword) !== false) {
                        $score += 50;
                        $keywordCount++;
                    }
                }
                
                // [100 pts] Multi-keyword Bonus (ถ้ามี >= 2 คำ)
                if ($keywordCount >= 2) {
                    $score += 100;
                }
                
                // [30 pts per keyword] Keywords field
                foreach ($keywords as $keyword) {
                    if (mb_stripos($keywords_field, $keyword) !== false) {
                        $score += 30;
                    }
                }
                
                // [5 pts per keyword] Answer field (น้ำหนักต่ำมาก)
                foreach ($keywords as $keyword) {
                    if (mb_stripos($answer, $keyword) !== false) {
                        $score += 5;
                    }
                }
                
                // [+300 pts] General Department Bonus - ถ้าคำถามไม่ระบุสาขาชัดเจน และ FAQ เป็น general
                // ช่วยให้คำถามทั่วไปเช่น "เกรด F" ตอบจาก FAQ ทั่วไปแทน FAQ เฉพาะสาขา
                if (!$detectedDepartment && $row['department'] === 'general') {
                    $score += 300;
                    error_log("General Department Boost: No specific dept in query, FAQ is general (Q: $question) +300");
                }
                
                $row['relevance'] = $score;
                
                // Log top candidates for debugging
                if ($score > 200) {
                    error_log("FAQ Candidate [ID:{$row['id']}] Score: $score - Q: {$question}");
                }
            }
            
            // เรียงตามคะแนน
            usort($results, function($a, $b) {
                return $b['relevance'] - $a['relevance'];
            });
            
            // Log TOP 5 matches for debugging
            if (!empty($results)) {
                error_log("========== TOP 5 FAQ MATCHES ==========");
                for ($i = 0; $i < min(5, count($results)); $i++) {
                    $faq = $results[$i];
                    $num = $i + 1;
                    error_log("#{$num} [ID:{$faq['id']}] Score: {$faq['relevance']} - Q: " . substr($faq['question'], 0, 80));
                }
                error_log("=======================================");
            }
            
            // คืนค่า top 5
            return array_slice($results, 0, 5);
            
        } catch (PDOException $e) {
            error_log("searchFAQBroad Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * สร้าง SQL สำหรับคำนวณคะแนนจาก Keywords
     * ทำงาน: แต่ละคำที่เจอใน field ได้คะแนนตาม weight ที่กำหนด
     * ตัวอย่าง: ถ้ามี 3 คำและ weight = 50 → ได้ 50+50+50 = 150 คะแนน
     * 
     * @param string $field ชื่อ field ที่จะค้นหา (question, keywords, answer)
     * @param array $keywords รายการคำสำคัญที่ต้องการหา
     * @param float $weight น้ำหนักคะแนนต่อ 1 คำ
     * @return string SQL expression สำหรับคำนวณคะแนน
     */
    private function buildKeywordScoring($field, $keywords, $weight) {
        $conditions = [];
        foreach ($keywords as $keyword) {
            // พิจารณาเฉพาะคำที่ยาว >= 2 ตัวอักษร (กรองคำสั้นๆ ที่ไม่มีความหมาย)
            if (mb_strlen($keyword) >= 2) {
                $conditions[] = "CASE WHEN {$field} LIKE ? THEN {$weight} ELSE 0 END";
            }
        }
        return !empty($conditions) ? implode(' + ', $conditions) : '0';
    }
    
    /**
     * สร้าง SQL สำหรับคำนวณคะแนนโบนัสจากการมีหลายคำพร้อมกัน (Combo Bonus)
     * ทำงาน: ถ้ามีทุกคำปรากฏใน field เดียวกัน = ความเฉพาะเจาะจงสูง → ได้คะแนนโบนัส
     * ตัวอย่าง: 
     *   - ถาม "วิศวกรรม ไฟฟ้า เรียน" และทั้ง 3 คำอยู่ใน question เดียวกัน
     *   - แสดงว่าเป็นคำตอบที่ตรงมาก → ได้โบนัส 80 * 3 = 240 คะแนน
     * 
     * @param string $field ชื่อ field ที่จะตรวจสอบ
     * @param array $keywords รายการคำสำคัญทั้งหมด
     * @param float $bonusPerKeyword คะแนนโบนัสต่อคำ
     * @return string SQL expression สำหรับคะแนนโบนัส
     */
    private function buildMultiKeywordBonus($field, $keywords, $bonusPerKeyword) {
        // กรองเฉพาะคำที่มีความหมาย (>= 2 ตัวอักษร)
        $validKeywords = array_filter($keywords, function($k) {
            return mb_strlen($k) >= 2;
        });
        
        // ถ้ามีแค่ 1 คำ ไม่มีโบนัส (ต้องมีอย่างน้อย 2 คำถึงจะได้โบนัส combo)
        if (count($validKeywords) < 2) {
            return '0';
        }
        
        // สร้างเงื่อนไข: ทุกคำต้องอยู่ใน field เดียวกัน (AND)
        $conditions = [];
        foreach ($validKeywords as $keyword) {
            $conditions[] = "{$field} LIKE ?";
        }
        
        $allConditions = implode(' AND ', $conditions);
        $totalBonus = $bonusPerKeyword * count($validKeywords);
        
        return "CASE WHEN ({$allConditions}) THEN {$totalBonus} ELSE 0 END";
    }
    
    /**
     * Detect category from query
     */
    private function detectCategory($query) {
        // อัปเดตให้ตรงกับ 15 categories หลังรวม
        $categoryKeywords = [
            'การรับสมัคร' => ['สมัคร', 'รับสมัคร', 'TCAS', 'รับตรง', 'โควตา', 'เข้าเรียน', 'คุณสมบัติ', 'เอกสารสมัคร', 'admission'],
            'หลักสูตร' => ['หลักสูตร', 'สาขา', 'แผนการเรียน', 'ปริญญา', 'curriculum', 'program'],
            // tuition+fee รวมเข้า ทุนการศึกษา (loan)
            'ทุนการศึกษา' => ['ทุน', 'กยศ', 'กรอ', 'ทุนการศึกษา', 'กู้ยืม', 'ทุนกู้', 'scholarship', 'มีทุนอะไรบ้าง', 'มีทุนไหม', 'เอกสารกู้', 'แบบฟอร์มกู้', 'ค่าเทอม', 'ค่าเล่าเรียน', 'ค่าธรรมเนียม'],
            // library+facility รวมเข้า สิ่งอำนวยความสะดวก (facilities)
            'สิ่งอำนวยความสะดวก' => ['หอพัก', 'WiFi', 'ห้องสมุด', 'โรงอาหาร', 'ATM', 'ที่พัก', 'dormitory', 'library', 'ห้องแล็บ', 'ห้อง Lab', 'ห้องปฏิบัติการ', 'แล็บ', 'หนังสือ', 'ยืมหนังสือ'],
            'การลงทะเบียน' => ['ลงทะเบียน', 'registration', 'เพิ่มวิชา', 'ถอนวิชา', 'add', 'drop', 'เพิ่มถอน'],
            'การชำระเงิน' => ['ชำระเงิน', 'จ่ายเงิน', 'โอนเงิน', 'payment', 'pay', 'จ่ายค่าเทอม', 'ชำระค่าเทอม'],
            'ฝึกงาน' => ['ฝึกงาน', 'internship', 'สหกิจ', 'coop', 'ฝึกงานเรียน'],
            // sports รวมเข้า กิจกรรม (activities)
            'กิจกรรม' => ['กิจกรรม', 'ต้อนรับน้อง', 'กีฬาสี', 'event', 'อีเว้นท์', 'กีฬา', 'แข่งขัน'],
            'จบการศึกษา' => ['จบการศึกษา', 'สำเร็จการศึกษา', 'เกณฑ์จบ', 'เงื่อนไขจบ', 'graduation', 'สอบจบ', 'รับปริญญา'],
            'ความร่วมมือ' => ['ความร่วมมือ', 'ร่วมมือ', 'บริษัทภายนอก', 'MOU', 'พันธมิตร', 'หน่วยงานภายนอก', 'cooperation'],
            // location รวมเข้า ติดต่อ (contact)
            'ติดต่อ' => ['อยู่ที่ไหน', 'ตั้งอยู่', 'ที่ตั้ง', 'สถานที่', 'แผนที่', 'เดินทาง'],
        ];
        
        foreach ($categoryKeywords as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (mb_stripos($query, $keyword) !== false) {
                    error_log("detectCategory: Found '$keyword' → Category: $category");
                    return $category;
                }
            }
        }
        
        return '';
    }
    
    /**
     * Expand query with synonyms and related terms
     */
    private function expandQuerySynonyms($query) {
        $synonyms = [
            // คณะและสาขาวิชา
            'วิศวกรรมศาสตร์' => 'วิศวกรรม วิศวะ engineering คณะวิศวะ วศ',
            'วิศวกรรมไฟฟ้า' => 'ไฟฟ้า electrical สาขาไฟฟ้า EE',
            'วิศวกรรมคอมพิวเตอร์' => 'คอมพิวเตอร์ คอม computer CE CPE',
            'วิศวกรรมโยธา' => 'โยธา civil โครงสร้าง สถาปัตย์',
            'วิศวกรรมอุตสาหการ' => 'อุตสาหการ industrial IE อุตสาห',
            'วิศวกรรมเครื่องกล' => 'เครื่องกล mechanical ME',
            'อส.บ' => 'อส.บ. อส.บ อุตสาหกรรมศาสตรบัณฑิต ยั่งยืน sustainable innovation BIndTech',
            'อส.บ.' => 'อส.บ. อส.บ อุตสาหกรรมศาสตรบัณฑิต ยั่งยืน sustainable innovation',
            'SIME' => 'SIME วิศวกรรมการจัดการอุตสาหกรรมเพื่อความยั่งยืน Sustainable Industrial Management',
            'วศ.ม' => 'วศ.ม. วศ.ม วิศวกรรมศาสตรมหาบัณฑิต ปริญญาโท Master MEng',
            'วิศวคอม' => 'วิศวกรรมคอมพิวเตอร์ คอมพิวเตอร์ คอม computer CPE CE programming โปรแกรม',
            'สาขา' => 'สาขา วิชา หลักสูตร แผนก department program',
            'หลักสูตร' => 'หลักสูตร วิชา curriculum รายวิชา program',
            'เรียน' => 'เรียน ศึกษา วิชา รายวิชา เนื้อหา',
            
            // ปีการศึกษา
            'ปีที่' => 'ปี ชั้นปี ปีการศึกษา year',
            'ปี 1' => 'ปีหนึ่ง ชั้นปีที่ 1 ปีแรก freshman',
            'ปี 2' => 'ปีสอง ชั้นปีที่ 2 sophomore',
            'ปี 3' => 'ปีสาม ชั้นปีที่ 3 junior',
            'ปี 4' => 'ปีสี่ ชั้นปีที่ 4 ปีจบ senior',
            
            // ค่าใช้จ่าย
            'ค่าเรียน' => 'ค่าเรียน ค่าเทอม ค่าธรรมเนียม ค่าใช้จ่าย tuition fee',
            'ค่าเทอม' => 'ค่าเทอม ค่าเรียน ค่าใช้จ่าย tuition fee ผ่อน',
            'ค่าใช้จ่าย' => 'ค่าใช้จ่าย ค่าเรียน ค่าเทอม ราคา เท่าไหร่',
            'ผ่อน' => 'ผ่อน ผ่อนชำระ ผ่อนค่าเทอม จ่ายเป็นงวด installment',
            
            // การรับสมัคร
            'รับสมัคร' => 'รับสมัคร สมัคร เปิดรับ admission การรับสมัคร ระดับ ปวช ปริญญาตรี ปริญญาโท',
            'สมัครเรียน' => 'สมัคร รับสมัคร เปิดรับ การรับสมัคร apply enrollment',
            'เข้าเรียน' => 'สมัคร รับสมัคร เข้าศึกษา เข้าเรียน admission',
            'TCAS' => 'TCAS ทีแคส รับสมัคร admission portfolio ม.6',
            'รับตรง' => 'รับตรง Direct Admission โควตา quota ปวส ปวช',
            'ปวช' => 'ปวช ปวช. ประกาศนียบัตรวิชาชีพ vocational certificate ม.3',
            'ปริญญาตรี' => 'ปริญญาตรี bachelor ป.ตรี undergraduate ม.6 ปวส',
            'ปริญญาโท' => 'ปริญญาโท master ป.โท graduate วศ.ม มหาบัณฑิต',
            'มทร.พระนคร' => 'มทร.พระนคร RMUTP ราชมงคล พระนคร มหาวิทยาลัย',
            'ศูนย์' => 'ศูนย์ campus วิทยาเขต เทเวศร์ โชติเวช พณิชยการ พระนครเหนือ',
            
            // ทุนการศึกษา
            'กอ งทุนเงินให้กู้ยืมเพื่อการศึกษา' => 'กอ งทุนเงินให้กู้ยืมเพื่อการศึกษา กยศ กรอ ทุนกู้ยืม scholarship loan',
            'กยศ' => 'กยศ กยศ. กองทุนกู้ยืม กองทุนเงินให้กู้ยืม ทุนการศึกษา student loan DSL กู้เงิน ทุนกู้',
            'กรอ' => 'กรอ กรอ. กองทุนรายได้ income contingent loan ICL กองทุนกู้ยืม ทุนการศึกษา DSL',
            'ทุน' => 'ทุน ทุนการศึกษา scholarship กยศ กรอ กองทุน loan',
            'กู้ยืม' => 'กู้ยืม กยศ กรอ ทุน กู้เงิน ทุนกู้ student loan DSL',
            'ทุนกู้' => 'ทุนกู้ กยศ กรอ กู้ยืม กองทุน loan',
            'DSL' => 'DSL ระบบ e-studentloan ระบบกู้ยืม digital student loan',
            'สัมภาษณ์' => 'สัมภาษณ์ interview กยศ กรอ รายใหม่ ผู้กู้',
            'จิตอาสา' => 'จิตอาสา volunteer กิจกรรม กยศ กรอ e-learning',
            'แบบฟอร์ม' => 'แบบฟอร์ม ฟอร์ม form ดาวน์โหลด download เอกสาร',
            'เอกสารกู้ยืม' => 'เอกสาร แบบฟอร์ม กยศ กรอ checklist check list ส่งเอกสาร',
            
            // ระบบลงทะเบียน
            'เพิ่มถอนรายวิชา' => 'เพิ่ม ถอน รายวิชา ลงทะเบียน add drop',
            'ลงทะเบียนเรียน' => 'ลงทะเบียน registration เลือกวิชา เพิ่มถอน',
            'ตรวจสอบผลการเรียน' => 'ผลการเรียน เกรด เช็คเกรด ผลสอบ grade',
            'ผลการเรียน' => 'เกรด grade ผลสอบ คะแนน transcript',
            
            // สิ่งอำนวยความสะดวก
            'หอพัก' => 'หอพัก ที่พักนักศึกษา dormitory หอใน ที่พัก',
            'ที่พักนักศึกษา' => 'หอพัก ที่พัก dormitory',
            'WiFi' => 'WiFi ไวไฟ อินเทอร์เน็ต internet wireless เน็ต',
            'อินเทอร์เน็ต' => 'WiFi internet ไวไฟ เน็ต อินเทอร์เน็ต',
            'ห้องสมุด' => 'ห้องสมุด ศูนย์สารสนเทศ library',
            
            // ติดต่อ
            'ติดต่อ' => 'ติดต่อ เบอร์ โทร อีเมล email phone โทรศัพท์',
            'โทร' => 'โทร โทรศัพท์ เบอร์ tel phone ติดต่อ',
            'อีเมล' => 'อีเมล email อีเมล์ mail ติดต่อ',
            'เบอร์' => 'เบอร์ โทรศัพท์ โทร phone เบอร์โทร',
            
            // สถานที่
            'ที่อยู่' => 'ที่อยู่ ตั้งอยู่ สถานที่ location address',
            'อยู่ไหน' => 'ที่อยู่ สถานที่ location ตั้งอยู่',
            'ไปยังไง' => 'เดินทาง ไป มา location การเดินทาง',
            
            // อาจารย์/บุคลากร
            'อาจารย์' => 'อาจารย์ ผศ รศ ศ อ. ดร. teacher professor คณาจารย์',
            'ครู' => 'อาจารย์ ครู teacher professor',
            'หัวหน้า' => 'หัวหน้า head chief หัวหน้าแผนก',
            'เบอร์อาจารย์' => 'อาจารย์ ติดต่อ โทร เบอร์',
            'อีเมลอาจารย์' => 'อาจารย์ อีเมล email',
            
            // Auto-expand short queries about departments (match AFTER normalization)
            'วิศวกรรมโยธา' => 'วิศวกรรมโยธา เรียน หลักสูตร curriculum',
            'วิศวกรรมไฟฟ้า' => 'วิศวกรรมไฟฟ้า เรียน หลักสูตร electrical',
            'วิศวกรรมคอมพิวเตอร์' => 'วิศวกรรมคอมพิวเตอร์ เรียน หลักสูตร computer',
            'วิศวกรรมเครื่องกล' => 'วิศวกรรมเครื่องกล เรียน หลักสูตร mechanical',
            'วิศวกรรมอิเล็กทรอนิกส์' => 'วิศวกรรมอิเล็กทรอนิกส์ เรียน หลักสูตร electronics',
            'สาขาโยธา' => 'วิศวกรรมโยธา เรียน หลักสูตร',
            'สาขาไฟฟ้า' => 'วิศวกรรมไฟฟ้า เรียน หลักสูตร',
            'สาขาคอม' => 'วิศวกรรมคอมพิวเตอร์ เรียน หลักสูตร',
            'สาขาเครื่องกล' => 'วิศวกรรมเครื่องกล เรียน หลักสูตร',
            'สาขาอิเล็กทรอนิกส์' => 'วิศวกรรมอิเล็กทรอนิกส์ เรียน หลักสูตร',
            'หลักสูตรโยธา' => 'วิศวกรรมโยธา เรียน',
            'หลักสูตรไฟฟ้า' => 'วิศวกรรมไฟฟ้า เรียน',
            'หลักสูตรคอม' => 'วิศวกรรมคอมพิวเตอร์ เรียน',
            'หลักสูตรเครื่องกล' => 'วิศวกรรมเครื่องกล เรียน',
            
            // กิจกรรม
            'กิจกรรม' => 'กิจกรรม event อีเว้นท์ งาน',
            'ต้อนรับน้องใหม่' => 'รับน้อง ต้อนรับน้องใหม่ orientation',
            'กีฬาสี' => 'กีฬาสี sport day งานกีฬา',
            
            // คำถามทั่วไป
            'ทำไม' => 'เหตุผล why ข้อดี',
            'อย่างไร' => 'วิธี how ขั้นตอน วิธีการ',
            'เมื่อไหร่' => 'เวลา วันที่ when กำหนดการ',
            'ที่ไหน' => 'สถานที่ where location ตำแหน่ง',
            'มีอะไรบ้าง' => 'มี อะไร บ้าง มีกี่ รายชื่อ รายการ',
            'มีกี่' => 'จำนวน มีกี่ มี กี่',
        ];
        
        $expandedQuery = $query;
        foreach ($synonyms as $word => $expansion) {
            if (mb_stripos($query, $word) !== false) {
                $expandedQuery .= ' ' . $expansion;
            }
        }
        
        // Auto-append "เรียนอะไร" for department-only queries (after normalization)
        // Pattern: just department name without any question word
        if (!preg_match('/(เรียน|มี|คือ|ทำ|อะไร|ไหน|เท่าไหร่|กี่)/u', $query)) {
            if (preg_match('/วิศวกรรม(ไฟฟ้า|คอมพิวเตอร์|โยธา|เครื่องกล|อิเล็กทรอนิกส์|อุตสาหการ|เคมี|สิ่งแวดล้อม)/u', $query)) {
                $expandedQuery .= ' เรียนอะไร หลักสูตร curriculum';
            }
        }
        
        return $expandedQuery;
    }
    
    /**
     * Normalize user query to standard terms
     */
    private function normalizeQuery($query) {
        $normalizations = [
            // Remove noise words before department names
            '/\bสาขา\s*(วิศวกรรม)/ui' => '$1',
            '/\bหลักสูตร\s*(วิศวกรรม)/ui' => '$1',
            
            // คณะและสาขา
            '/\b(คณะ|คณะ\s*)?วศ\.?\b/ui' => 'คณะวิศวกรรมศาสตร์',
            '/\b(คณะ\s*)?วิศวะ\b/ui' => 'คณะวิศวกรรมศาสตร์',
            '/\bไฟฟ้า\b/ui' => 'วิศวกรรมไฟฟ้า',
            '/\bคอม(พิว(เตอร์)?)?\b/ui' => 'วิศวกรรมคอมพิวเตอร์',
            '/\bโยธา\b/ui' => 'วิศวกรรมโยธา',
            '/\bอุตสาห(กรรม)?\b/ui' => 'วิศวกรรมอุตสาหการ',
            '/\bเครื่องกล\b/ui' => 'วิศวกรรมเครื่องกล',
            
            // ปีการศึกษา
            '/\bปี\s*(1|๑|หนึ่ง)\b/ui' => 'ปีที่ 1',
            '/\bปี\s*(2|๒|สอง)\b/ui' => 'ปีที่ 2',
            '/\bปี\s*(3|๓|สาม)\b/ui' => 'ปีที่ 3',
            '/\bปี\s*(4|๔|สี่)\b/ui' => 'ปีที่ 4',
            '/\bชั้นปี\s*(\d)/ui' => 'ปีที่ $1',
            
            // ทุนการศึกษา
            '/\bกยศ\.?\b/ui' => 'กองทุนเงินให้กู้ยืมเพื่อการศึกษา กยศ',
            '/\bกรอ\.?\b/ui' => 'กองทุนเงินให้กู้ยืมเพื่อการศึกษา กรอ',
            '/\bทุนกู้\b/ui' => 'กองทุนเงินให้กู้ยืมเพื่อการศึกษา',
            
            // ระบบและการลงทะเบียน
            '/\b(เพิ่ม[- ]?ถอน|เพิ่มถอน)\b/ui' => 'เพิ่มถอนรายวิชา',
            '/\bลงทะเบียน\b/ui' => 'ลงทะเบียนเรียน',
            '/\bเช็ค\s*เกรด\b/ui' => 'ตรวจสอบผลการเรียน',
            '/\bเกรด\b/ui' => 'ผลการเรียน เกรด',
            
            // การรับสมัคร
            '/\bทีแคส\b/ui' => 'TCAS',
            '/\bรับตรง\b/ui' => 'รับตรง Direct Admission',
            '/\bโควตา\b/ui' => 'โควตาพิเศษ',
            
            // สิ่งอำนวยความสะดวก
            '/\bหอพัก\b/ui' => 'หอพัก ที่พักนักศึกษา',
            '/\bไวไฟ|wifi\b/ui' => 'WiFi อินเทอร์เน็ต',
            '/\bห้องสมุด\b/ui' => 'ห้องสมุด ศูนย์สารสนเทศ',
            
            // คำกริยาทั่วไป
            '/\bเท่าไหร่\b/ui' => 'ค่าใช้จ่าย ราคา เท่าไหร่',
            '/\bยังไง\b/ui' => 'อย่างไร วิธีการ ขั้นตอน',
            '/\bมี(อะไร)?บ้าง\b/ui' => 'มี รายชื่อ รายการ',
            '/\bเรียน(อะไร)?\b/ui' => 'หลักสูตร เรียน วิชา',
        ];
        
        $normalized = $query;
        foreach ($normalizations as $pattern => $replacement) {
            $normalized = preg_replace($pattern, $replacement, $normalized);
        }
        
        return $normalized;
    }
    
    /**
     * แยกคำสำคัญจากคำถาม (Extract Keywords)
     * หน้าที่: กรองคำไม่จำเป็น แต่เก็บคำสำคัญสำหรับค้นหา
     * 
     * กลยุทธ์:
     * 1. กรองเฉพาะคำขอร้อง/สุภาพ (ครับ, ค่ะ, นะ) - ไม่สำคัญต่อการค้นหา
     * 2. เก็บคำสำคัญไว้: เรียน, หลักสูตร, ค่าเทอม, สมัคร, ฯลฯ
     * 3. ถ้าไม่มีช่องว่าง (ภาษาไทยติดกัน) ใช้ทั้งข้อความ + ตัดคำด้วยรูปแบบ
     */
    private function extractKeywords($query) {
        // กรองเฉพาะคำที่ไม่มีความหมาย (Politeness/Filler words เท่านั้น)
        // ไม่กรอง: เรียน, อะไร, ยังไง, ที่ไหน - เพราะคำเหล่านี้สำคัญต่อการค้นหา
        $stopWords = ['ครับ', 'ค่ะ', 'คะ', 'ครับผม', 'จ้า', 'นะ', 'หน่อย', 
                      'จะ', 'ได้', 'ไหม', 'มั้ย', 'หรอ', 
                      'กับ', 'และ', 'หรือว่า', 'แล้วก็', 'เพราะ', 'เลย',
                      'อ่ะ', 'เอ่อ', 'อืม', 'the', 'a', 'an', 'is', 'are'];
        
        $cleaned = $query;
        foreach ($stopWords as $stopWord) {
            $cleaned = preg_replace('/\b' . preg_quote($stopWord, '/') . '\b/ui', ' ', $cleaned);
        }
        
        // แยกคำด้วยช่องว่าง (ถ้ามี)
        $keywords = preg_split('/\s+/', trim($cleaned), -1, PREG_SPLIT_NO_EMPTY);
        $keywords = array_filter($keywords, function($k) {
            return mb_strlen($k) >= 2;
        });
        
        // ถ้าไม่มีคำหรือมีแค่คำเดียวที่ยาวพอสมควร (ภาษาไทยไม่มีช่องว่าง)
        // ให้ใช้ทั้งข้อความ + พยายามตัดคำด้วยรูปแบบที่รู้จัก
        // เกณฑ์: >= 4 ตัวอักษร (เพราะคำไทยสั้นๆ เช่น "กยศ"=3, "ทุน"=3, "ค่าเทอม"=7)
        if (empty($keywords) || (count($keywords) === 1 && mb_strlen($keywords[0]) >= 4)) {
            // ใช้ทั้งข้อความเป็น keyword หลัก
            $keywords = [$query];
            
            // พยายามแยกคำด้วย patterns ที่รู้จัก (คำที่พบบ่อย)
            $commonWords = [
                'วิศวกรรม', 'ไฟฟ้า', 'คอมพิวเตอร์', 'โยธา', 'เครื่องกล', 'อุตสาหการ', 'อิเล็กทรอนิกส์',
                'เมคคาทรอนิกส์', 'เครื่องประดับ', 'เครื่องมือ', 'แม่พิมพ์',
                'คณะ', 'สาขา', 'หลักสูตร', 'เรียน', 'สมัคร', 'รับสมัคร', 'ค่าเทอม', 'ทุน', 'กยศ', 'กรอ',
                'หอพัก', 'ห้องสมุด', 'อาจารย์', 'บุคลากร', 'ติดต่อ', 'โทร', 'อีเมล',
                'อะไร', 'ยังไง', 'อย่างไร', 'เท่าไหร่', 'ที่ไหน', 'เมื่อไหร่', 'ทำไม',
                'ห้องแล็บ', 'ห้องปฏิบัติการ', 'แล็บ', 'Lab',
                'จบการศึกษา', 'สำเร็จการศึกษา', 'เงื่อนไข', 'เกณฑ์จบ', 'เกณฑ์',
                'ความร่วมมือ', 'ร่วมมือ', 'บริษัท', 'ภายนอก', 'พันธมิตร', 'สหกิจ',
                'เอกสาร', 'แบบฟอร์ม', 'กู้ยืม', 'ดาวน์โหลด',
                'ฝึกงาน', 'โครงงาน', 'วิจัย', 'กิจกรรม',
                'อาชีพ', 'เงินเดือน', 'ทำงาน', 'จบแล้ว',
                'ลงทะเบียน', 'ถอนวิชา', 'เพิ่มวิชา',
                'ประวัติ', 'ข้อมูล', 'รายละเอียด'
            ];
            
            foreach ($commonWords as $word) {
                if (mb_stripos($query, $word) !== false && !in_array($word, $keywords)) {
                    $keywords[] = $word;
                }
            }
        }
        
        // ถ้ายังไม่มีเลย ให้ใช้คำเดิมทั้งหมด
        if (empty($keywords)) {
            $keywords = preg_split('/\s+/', $query, -1, PREG_SPLIT_NO_EMPTY);
            $keywords = array_filter($keywords, function($k) {
                return mb_strlen($k) >= 2;
            });
        }
        
        return array_values(array_unique($keywords));
    }
    
    /**
     * Shorten URL for display
     */
    private function shortenUrl($url) {
        // ถ้าเป็น URL ภายในคณะ แสดงเฉพาะส่วนสำคัญ
        if (strpos($url, 'eng.rmutp.ac.th') !== false) {
            return 'eng.rmutp.ac.th';
        }
        
        // ถ้าเป็น URL ภายในมหาวิทยาลัย
        if (strpos($url, 'rmutp.ac.th') !== false) {
            preg_match('/https?:\/\/([^\/]+)/', $url, $matches);
            return $matches[1] ?? $url;
        }
        
        // ถ้าเป็น Google Calendar
        if (strpos($url, 'calendar.google.com') !== false) {
            return 'ปฏิทิน Google Calendar';
        }
        
        // ถ้าเป็น registration form
        if (strpos($url, 'reg.rmutp.ac.th') !== false) {
            return 'ระบบลงทะเบียน';
        }
        
        // ถ้าเป็น admission
        if (strpos($url, 'admission.rmutp.ac.th') !== false) {
            return 'ระบบรับสมัคร';
        }
        
        // URL อื่นๆ ให้ตัดเฉพาะ domain
        preg_match('/https?:\/\/([^\/]+)/', $url, $matches);
        if (isset($matches[1])) {
            $domain = $matches[1];
            // ถ้ายาวเกิน 40 ตัว ให้ตัดต่อท้ายด้วย ...
            return mb_strlen($domain) > 40 ? mb_substr($domain, 0, 40) . '...' : $domain;
        }
        
        return $url;
    }
    
    /**
     * Search for news and activities
     */
    private function searchNews($query) {
        // Check if query is about news/activities
        $newsKeywords = ['ข่าว', 'ข่าวสาร', 'ประชาสัมพันธ์', 'กิจกรรม', 'อีเว้นท์', 'event', 'news', 'ล่าสุด', 'มีอะไรบ้าง'];
        $isNewsQuery = false;
        
        foreach ($newsKeywords as $keyword) {
            if (mb_stripos($query, $keyword) !== false) {
                $isNewsQuery = true;
                break;
            }
        }
        
        if (!$isNewsQuery) {
            return [];
        }
        
        // Determine category - ตรวจสอบตามลำดับความชัดเจน
        $category = null;
        
        // ตรวจสอบ "ข่าวประชาสัมพันธ์" หรือ "ประชาสัมพันธ์" ก่อน
        if (mb_stripos($query, 'ข่าวประชาสัมพันธ์') !== false || 
            mb_stripos($query, 'ประชาสัมพันธ์') !== false ||
            mb_stripos($query, 'ข่าว pr') !== false ||
            preg_match('/ข่าว.*ประชาสัมพันธ์/ui', $query)) {
            $category = 'ข่าวประชาสัมพันธ์';
        } 
        // ตรวจสอบ "ข่าวกิจกรรม" หรือ "กิจกรรม"
        elseif (mb_stripos($query, 'ข่าวกิจกรรม') !== false ||
                mb_stripos($query, 'กิจกรรม') !== false || 
                mb_stripos($query, 'อีเว้นท์') !== false || 
                mb_stripos($query, 'event') !== false ||
                preg_match('/ข่าว.*กิจกรรม/ui', $query)) {
            $category = 'กิจกรรม';
        }
        
        // Build search query
        $sql = "SELECT * FROM news WHERE is_active = 1";
        $params = [];
        
        if ($category) {
            $sql .= " AND category = ?";
            $params[] = $category;
        }
        
        $sql .= " ORDER BY published_date DESC LIMIT 10";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Build response for news query
     */
    private function buildNewsResponse($sessionId, $message, $newsList) {
        if (empty($newsList)) {
            $answer = "ขออภัยครับ ไม่พบข่าวสารที่คุณค้นหา";
            $confidence = 0.0;
            $sources = [];
        } else {
            $answer = $this->formatNewsList($newsList);
            $confidence = 0.9;
            $sources = array_map(function($news) {
                return [
                    'type' => 'news',
                    'id' => $news['id'],
                    'title' => $news['title'],
                    'category' => $news['category']
                ];
            }, $newsList);
        }
        
        $responseTime = round((microtime(true) - $this->startTime) * 1000);
        $this->logChat($sessionId, $message, $answer, $sources, $confidence, $responseTime);
        
        return [
            'answer' => $answer,
            'sources' => $sources,
            'confidence' => $confidence,
            'response_time_ms' => $responseTime
        ];
    }
    
    /**
     * Format news list into readable text
     */
    private function formatNewsList($newsList) {
        $category = $newsList[0]['category'];
        $icon = $category === 'ข่าวประชาสัมพันธ์' ? '📢' : '🎉';
        
        // Header พร้อมจำนวนข่าว
        $count = count($newsList);
        $answer = "\n{$icon} {$category}ล่าสุด\n";
        $answer .= "คณะวิศวกรรมศาสตร์ มหาวิทยาลัยเทคโนโลยีราชมงคลพระนคร\n";
        $answer .= "📊 แสดง {$count} รายการ จากทั้งหมด\n";
        $answer .= str_repeat("═", 60) . "\n\n";
        
        foreach ($newsList as $index => $news) {
            $num = $index + 1;
            
            // แสดงหัวข้อพร้อมหมวดหมู่
            $categoryBadge = $news['category'] === 'ข่าวประชาสัมพันธ์' ? '📰' : '🎯';
            $answer .= "{$categoryBadge} {$num}. {$news['title']}\n\n";
            
            // แสดงวันที่
            if (!empty($news['published_date'])) {
                $date = date('d/m/Y', strtotime($news['published_date']));
                $answer .= "📅 วันที่: {$date}\n";
            }
            
            // แสดงสรุปข่าว (ถ้ามี)
            if (!empty($news['summary']) && $news['summary'] !== $news['title']) {
                $summary = mb_strlen($news['summary']) > 150 
                    ? mb_substr($news['summary'], 0, 150) . '...' 
                    : $news['summary'];
                $answer .= "📝 เนื้อหา: {$summary}\n";
            }
            
            // Tags (ถ้ามี)
            if (!empty($news['tags'])) {
                $tags = array_slice(explode(',', $news['tags']), 0, 3); // แสดงแค่ 3 tags แรก
                $tagsStr = implode(' • ', array_map(function($tag) {
                    return trim($tag);
                }, $tags));
                $answer .= "🏷️  หมวดหมู่: {$tagsStr}\n";
            }
            
            // แสดงลิงก์
            if (!empty($news['link_url'])) {
                $shortLink = $this->shortenUrl($news['link_url']);
                $answer .= "🔗 อ่านเพิ่มเติม: {$shortLink}\n";
            }
            
            // เส้นแบ่งระหว่างข่าว
            if ($num < $count) {
                $answer .= "\n" . str_repeat("─", 60) . "\n\n";
            }
        }
        
        // Footer
        $answer .= "\n" . str_repeat("═", 60) . "\n";
        $answer .= "💡 ต้องการข้อมูลเพิ่มเติม?\n";
        $answer .= "📞 โทร: 02-836-3000 | 📧 อีเมล: eng@rmutp.ac.th\n";
        $answer .= "🌐 เว็บไซต์: https://eng.rmutp.ac.th";
        
        return trim($answer);
    }
    
    /**
     * Search for staff members
     */
    private function searchStaff($query) {
        // Check if query is about staff/teachers
        $staffKeywords = [
            'อาจารย์', 'ผศ', 'รศ', 'ศ.', 'ศาสตราจารย์', 'อ.', 'ดร.',
            'หัวหน้าสาขา', 'หัวหน้า', 'ครู', 'teacher', 'professor',
            'ติดต่ออาจารย์', 'เบอร์อาจารย์', 'อีเมลอาจารย์',
            'สอน', 'ผู้สอน', 'คณาจารย์', 'บุคลากร', 'staff',
            'รายชื่อ', 'รายชื่ออาจารย์', 'ดูรายชื่อ', 'list', 'ดูอาจารย์'
        ];
        $isStaffQuery = false;
        
        foreach ($staffKeywords as $keyword) {
            if (mb_stripos($query, $keyword) !== false) {
                $isStaffQuery = true;
                break;
            }
        }
        
        if (!$isStaffQuery) {
            return [];
        }
        
        // Extract department from query with more variations
        $departments = [
            // คอมพิวเตอร์
            'คอมพิวเตอร์' => 'วิศวกรรมคอมพิวเตอร์',
            'คอม' => 'วิศวกรรมคอมพิวเตอร์',
            'computer' => 'วิศวกรรมคอมพิวเตอร์',
            'cpe' => 'วิศวกรรมคอมพิวเตอร์',
            
            // ไฟฟ้า
            'ไฟฟ้า' => 'วิศวกรรมไฟฟ้า',
            'electrical' => 'วิศวกรรมไฟฟ้า',
            'ee' => 'วิศวกรรมไฟฟ้า',
            
            // อุตสาหการ
            'อุตสาหการ' => 'สาขาวิศวกรรมอุตสาหการ',
            'industrial' => 'สาขาวิศวกรรมอุตสาหการ',
            'ie' => 'สาขาวิศวกรรมอุตสาหการ',
            
            // เครื่องกล
            'เครื่องกล' => 'สาขาวิศวกรรมเครื่องกล',
            'mechanical' => 'สาขาวิศวกรรมเครื่องกล',
            'me' => 'สาขาวิศวกรรมเครื่องกล',
            
            // โยธา
            'โยธา' => 'วิศวกรรมโยธา',
            'civil' => 'วิศวกรรมโยธา',
            'ce' => 'วิศวกรรมโยธา',
            
            // อิเล็กทรอนิกส์
            'อิเล็กทรอนิกส์' => 'วิศวกรรมอิเล็กทรอนิกส์และโทรคมนาคม',
            'โทรคมนาคม' => 'วิศวกรรมอิเล็กทรอนิกส์และโทรคมนาคม',
            'electronics' => 'วิศวกรรมอิเล็กทรอนิกส์และโทรคมนาคม',
            'telecom' => 'วิศวกรรมอิเล็กทรอนิกส์และโทรคมนาคม',
            
            // เมคคาทรอนิกส์
            'เมคคาทรอนิกส์' => 'วิศวกรรมเมคคาทรอนิกส์',
            'mechatronics' => 'วิศวกรรมเมคคาทรอนิกส์',
            
            // เครื่องประดับ
            'เครื่องประดับ' => 'วิศวกรรมการผลิตเครื่องประดับ',
            'jewelry' => 'วิศวกรรมการผลิตเครื่องประดับ',
            
            // เครื่องมือและแม่พิมพ์
            'เครื่องมือ' => 'สาขาวิศวกรรมเครื่องมือและแม่พิมพ์',
            'แม่พิมพ์' => 'สาขาวิศวกรรมเครื่องมือและแม่พิมพ์',
            'tool' => 'สาขาวิศวกรรมเครื่องมือและแม่พิมพ์',
            'die' => 'สาขาวิศวกรรมเครื่องมือและแม่พิมพ์',
            
            // นวัตกรรม
            'นวัตกรรม' => 'สาขาวิชาวิศวกรรมการจัดการอุตสาหกรรมเพื่อความยั่งยืน',
            'ยั่งยืน' => 'สาขาวิชาวิศวกรรมการจัดการอุตสาหกรรมเพื่อความยั่งยืน',
            'sustainable' => 'สาขาวิชาวิศวกรรมการจัดการอุตสาหกรรมเพื่อความยั่งยืน',
        ];
        
        $targetDept = null;
        foreach ($departments as $keyword => $deptName) {
            if (mb_stripos($query, $keyword) !== false) {
                $targetDept = $deptName;
                break;
            }
        }
        
        // Build search query
        $sql = "SELECT * FROM staff WHERE is_active = 1";
        $params = [];
        
        if ($targetDept) {
            $sql .= " AND department = ?";
            $params[] = $targetDept;
        } else {
            // Check if asking for general list (ดูรายชื่อ, รายชื่ออาจารย์)
            $generalListKeywords = ['ดูรายชื่อ', 'รายชื่ออาจารย์', 'รายชื่อ', 'ดูอาจารย์', 'list'];
            $isGeneralList = false;
            foreach ($generalListKeywords as $keyword) {
                if (mb_stripos($query, $keyword) !== false) {
                    $isGeneralList = true;
                    break;
                }
            }
            
            if ($isGeneralList) {
                // Return special flag to show department list instead
                return ['_show_department_list' => true];
            }
            
            // Search all fields for specific name/position
            $sql .= " AND (name_th LIKE ? OR name_en LIKE ? OR position_th LIKE ? OR position_en LIKE ? OR department LIKE ? OR expertise LIKE ?)";
            $searchTerm = "%{$query}%";
            $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm];
        }
        
        $sql .= " ORDER BY id ASC LIMIT 20";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Build response for staff query
     */
    private function buildStaffResponse($sessionId, $message, $staffList) {
        // Check if this is a request to show department list
        if (isset($staffList['_show_department_list']) && $staffList['_show_department_list']) {
            $answer = "👨‍🏫 รายชื่ออาจารย์คณะวิศวกรรมศาสตร์\n\n";
            $answer .= "กรุณาระบุสาขาวิชาที่ต้องการดูรายชื่ออาจารย์:\n\n";
            $answer .= "🔹 สาขาวิศวกรรมโยธา\n";
            $answer .= "🔹 สาขาวิศวกรรมไฟฟ้า\n";
            $answer .= "🔹 สาขาวิศวกรรมอิเล็กทรอนิกส์และโทรคมนาคม\n";
            $answer .= "🔹 สาขาวิศวกรรมเครื่องกล\n";
            $answer .= "🔹 สาขาวิศวกรรมอุตสาหการ\n";
            $answer .= "🔹 สาขาวิศวกรรมคอมพิวเตอร์\n";
            $answer .= "🔹 สาขาวิศวกรรมเมคคาทรอนิกส์\n";
            $answer .= "🔹 สาขาวิศวกรรมเครื่องมือและแม่พิมพ์\n";
            $answer .= "🔹 สาขาวิศวกรรมการผลิตเครื่องประดับ\n";
            $answer .= "🔹 สาขาวิศวกรรมการจัดการอุตสาหกรรมเพื่อความยั่งยืน\n\n";
            $answer .= "💡 ตัวอย่าง: \"อาจารย์สาขาโยธา\" หรือ \"ดูรายชื่ออาจารย์คอมพิวเตอร์\"\n\n";
            $answer .= str_repeat("─", 50) . "\n";
            $answer .= "📞 ติดต่อสอบถาม: 02-836-3000 | eng@rmutp.ac.th";
            
            $confidence = 95.0;
            $sources = [['type' => 'staff', 'info' => 'department_list']];
        } elseif (empty($staffList)) {
            $answer = "ขออภัยครับ ไม่พบข้อมูลอาจารย์ที่คุณค้นหา";
            $confidence = 0.0;
            $sources = [];
        } else {
            $answer = $this->formatStaffList($staffList);
            $confidence = 0.9;
            $sources = array_map(function($staff) {
                return [
                    'type' => 'staff',
                    'id' => $staff['id'],
                    'name' => $staff['name_th']
                ];
            }, $staffList);
        }
        
        $responseTime = round((microtime(true) - $this->startTime) * 1000);
        $this->logChat($sessionId, $message, $answer, $sources, $confidence, $responseTime);
        
        return [
            'answer' => $answer,
            'sources' => $sources,
            'confidence' => $confidence,
            'response_time_ms' => $responseTime
        ];
    }
    
    /**
     * Format staff list into readable text
     */
    private function formatStaffList($staffList) {
        if (count($staffList) == 1) {
            $staff = $staffList[0];
            $answer = "👨‍🏫 ข้อมูลอาจารย์\n\n";
            $answer .= "──────────────────────────────────────────────────\n\n";
            
            // Name and position
            $answer .= "👤 {$staff['name_th']}";
            if ($staff['position_th']) {
                $answer .= "\n📋 ตำแหน่ง: {$staff['position_th']}";
            }
            $answer .= "\n\n";
            
            // Department
            if ($staff['department']) {
                $answer .= "🏢 สาขา: {$staff['department']}\n\n";
            }
            
            // Expertise
            if ($staff['expertise']) {
                $answer .= "💼 ความเชี่ยวชาญ:\n{$staff['expertise']}\n\n";
            }
            
            // Contact info with fallback
            $answer .= "📞 ช่องทางติดต่อ:\n";
            
            // Email (always available)
            if ($staff['email']) {
                $answer .= "📧 อีเมล: {$staff['email']}\n";
            }
            
            // Phone with fallback
            if (!empty($staff['phone'])) {
                $answer .= "☎️ โทรศัพท์: {$staff['phone']}\n";
            } else {
                // Fallback to department phone
                $deptPhone = $this->getDepartmentPhone($staff['department']);
                if ($deptPhone) {
                    $answer .= "☎️ โทรศัพท์ภาควิชา: {$deptPhone}\n";
                } else {
                    $answer .= "☎️ โทรศัพท์: 02-836-3000 (สำนักงานคณะ)\n";
                }
            }
            
            // Room/Office hours/Availability
            $hasOfficeInfo = false;
            if (!empty($staff['room'])) {
                $answer .= "🚪 ห้องทำงาน: {$staff['room']}\n";
                $hasOfficeInfo = true;
            }
            
            if (!empty($staff['office_hours'])) {
                $answer .= "🕐 ชั่วโมงให้คำปรึกษา: {$staff['office_hours']}\n";
                $hasOfficeInfo = true;
            }
            
            if (!$hasOfficeInfo) {
                $answer .= "💡 แนะนำ: ติดต่อทางอีเมลหรือนัดหมายล่วงหน้า\n";
            }
            
            $answer .= "\n──────────────────────────────────────────────────\n";
            $answer .= "💡 มีคำถามเพิ่มเติม? ถามได้เลยครับ!";
            
        } else {
            // Multiple staff members
            $department = $staffList[0]['department'];
            $count = count($staffList);
            $answer = "👨‍🏫 อาจารย์สาขา{$department} (ทั้งหมด {$count} คน)\n\n";
            $answer .= "──────────────────────────────────────────────────\n\n";
            
            foreach ($staffList as $index => $staff) {
                $answer .= ($index + 1) . ". {$staff['name_th']}";
                if ($staff['position_th']) {
                    $answer .= "\n   📋 {$staff['position_th']}";
                }
                $answer .= "\n";
                
                if ($staff['expertise']) {
                    $answer .= "   💼 {$staff['expertise']}\n";
                }
                
                if ($staff['email']) {
                    $answer .= "   📧 {$staff['email']}\n";
                }
                
                // Phone with fallback
                if (!empty($staff['phone'])) {
                    $answer .= "   ☎️ {$staff['phone']}\n";
                } else {
                    $deptPhone = $this->getDepartmentPhone($staff['department']);
                    if ($deptPhone) {
                        $answer .= "   ☎️ {$deptPhone} (ภาควิชา)\n";
                    }
                }
                
                $answer .= "\n";
            }
            
            $answer .= "──────────────────────────────────────────────────\n";
            $answer .= "💡 ต้องการข้อมูลเพิ่มเติม? ลองถามชื่ออาจารย์ที่สนใจ";
        }
        
        return trim($answer);
    }
    
    /**
     * Get department phone number for fallback
     */
    private function getDepartmentPhone($department) {
        $departmentPhones = [
            'วิศวกรรมคอมพิวเตอร์' => '02-836-3000 ต่อ 4160',
            'วิศวกรรมไฟฟ้า' => '02-836-3000 ต่อ 4150, 4151',
            'วิศวกรรมอิเล็กทรอนิกส์และโทรคมนาคม' => '02-836-3000 ต่อ 4165',
            'วิศวกรรมเครื่องกล' => '02-836-3000 ต่อ 4140, 4141',
            'สาขาวิศวกรรมเครื่องกล' => '02-836-3000 ต่อ 4138',
            'วิศวกรรมอุตสาหการ' => '02-836-3000 ต่อ 4180',
            'สาขาวิศวกรรมอุตสาหการ' => '02-836-3000 ต่อ 4180',
            'วิศวกรรมโยธา' => '02-836-3000 ต่อ 4170-4173',
            'วิศวกรรมเมคคาทรอนิกส์' => '02-836-3000 ต่อ 4145',
            'วิศวกรรมการผลิตเครื่องประดับ' => '02-836-3000 ต่อ 4135',
            'สาขาวิศวกรรมเครื่องมือและแม่พิมพ์' => '02-836-3000 ต่อ 4142',
            'สาขาวิชาวิศวกรรมการจัดการอุตสาหกรรมเพื่อความยั่งยืน' => '02-836-3000 ต่อ 4180',
        ];
        
        return $departmentPhones[$department] ?? null;
    }
    
    /**
     * Log chat to database
     */
    private function logChat($sessionId, $userMessage, $botResponse, $sources, $confidence, $responseTime) {
        $sql = "INSERT INTO chat_logs 
                (session_id, user_message, bot_response, sources, confidence, response_time_ms, user_ip, user_agent)
                VALUES (:session_id, :user_message, :bot_response, :sources, :confidence, :response_time, :user_ip, :user_agent)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'session_id' => $sessionId,
            'user_message' => $userMessage,
            'bot_response' => $botResponse,
            'sources' => json_encode($sources, JSON_UNESCAPED_UNICODE),
            'confidence' => $confidence,
            'response_time' => $responseTime,
            'user_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
        
        // Update session
        $this->updateSession($sessionId);
    }
    
    /**
     * Update or create session
     */
    private function updateSession($sessionId) {
        $sql = "INSERT INTO sessions (session_id, last_activity) 
                VALUES (:session_id, NOW())
                ON DUPLICATE KEY UPDATE last_activity = NOW()";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['session_id' => $sessionId]);
    }
    
    /**
     * Get related questions from the same category
     */
    private function getRelatedQuestions($category, $excludeId, $limit = 5) {
        $sql = "SELECT DISTINCT SUBSTRING_INDEX(question, '|', 1) as question_text
                FROM faq 
                WHERE is_active = 1 
                AND category = :category 
                AND id != :exclude_id
                ORDER BY RAND()
                LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':category', $category, PDO::PARAM_STR);
        $stmt->bindValue(':exclude_id', $excludeId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return array_map('trim', $results);
    }
    
    /**
     * List FAQs for browsing
     */
    public function listFAQs($limit = 1000, $category = null, $department = null) {
        // แสดงเฉพาะคำถามแรก (ก่อน |) และ department
        $sql = "SELECT id, SUBSTRING_INDEX(question, '|', 1) as question, category, department 
                FROM faq WHERE is_active = 1";
        
        if ($category) {
            $sql .= " AND category = :category";
        }
        
        if ($department && $department !== 'all') {
            $sql .= " AND department = :department";
        }
        
        $sql .= " ORDER BY id ASC LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        
        if ($category) {
            $stmt->bindValue(':category', $category, PDO::PARAM_STR);
        }
        if ($department && $department !== 'all') {
            $stmt->bindValue(':department', $department, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        
        $stmt->execute();
        
        // Trim whitespace จากคำถาม และเพิ่ม deptName
        $deptNames = [
            'electrical_engineering' => 'ไฟฟ้า',
            'computer_engineering' => 'คอมพิวเตอร์',
            'mechanical_engineering' => 'เครื่องกล',
            'industrial_engineering' => 'อุตสาหการ',
            'civil_engineering' => 'โยธา',
            'mechatronics_engineering' => 'เมคคาทรอนิกส์',
            'electronics_telecom_engineering' => 'อิเล็กทรอนิกส์',
            'jewelry_engineering' => 'เครื่องประดับ',
            'tool_engineering' => 'เครื่องมือ',
            'sime_engineering' => 'SIME',
            'general' => 'ทั่วไป',
            'student_affairs' => 'กิจการนักศึกษา',
            'graduate' => 'บัณฑิตศึกษา',
            'undergraduate' => 'ปริญญาตรี',
            'vocational' => 'ปวช./ปวส.',
            'vocational_computer' => 'ปวช.คอม'
        ];
        
        $results = $stmt->fetchAll();
        foreach ($results as &$row) {
            $row['question'] = trim($row['question']);
            $row['deptName'] = $deptNames[$row['department']] ?? 'ทั่วไป';
        }
        return $results;
    }
    
    /**
     * Error response
     */
    private function error($message) {
        http_response_code(400);
        return ['error' => $message];
    }
}

// Main execution - only when called via HTTP (not CLI)
if (php_sapi_name() === 'cli') {
    return; // Skip main execution when included from CLI
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Only POST method is allowed");
    }
    
    // Get JSON input with explicit UTF-8 handling
    $rawInput = file_get_contents('php://input');
    
    // ตรวจสอบและแปลง encoding ถ้าจำเป็น
    if (!mb_check_encoding($rawInput, 'UTF-8')) {
        $rawInput = mb_convert_encoding($rawInput, 'UTF-8', 'auto');
    }
    
    $input = json_decode($rawInput, true, 512, JSON_UNESCAPED_UNICODE);
    
    if (!$input) {
        throw new Exception("Invalid JSON input");
    }
    
    // Check if this is a FAQ list request
    if (isset($input['action']) && $input['action'] === 'list_faqs') {
        $chatbot = new Chatbot();
        $limit = isset($input['limit']) ? intval($input['limit']) : 1000;
        $category = $input['category'] ?? null;
        $department = $input['department'] ?? null;
        
        $faqs = $chatbot->listFAQs($limit, $category, $department);
        
        echo json_encode([
            'success' => true,
            'faqs' => $faqs,
            'count' => count($faqs)
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    $sessionId = $input['session_id'] ?? 'guest_' . uniqid();
    // รองรับทั้ง 'message' และ 'question' (backward compatibility)
    $message = trim($input['message'] ?? $input['question'] ?? '');
    
    if (empty($message)) {
        throw new Exception("Message cannot be empty");
    }
    
    // Process chat
    $chatbot = new Chatbot();
    $response = $chatbot->handleChat($sessionId, $message);
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้ กรุณาตรวจสอบการตั้งค่า'
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log("Application error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log("Fatal error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'เกิดข้อผิดพลาดภายในระบบ กรุณาลองใหม่อีกครั้ง'
    ], JSON_UNESCAPED_UNICODE);
}
