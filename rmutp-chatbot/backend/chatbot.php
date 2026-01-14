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

header('Content-Type: application/json; charset=utf-8');

// Load security helper
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/db.php';

// Set CORS headers (allowlist)
SecurityHelper::setCORSHeaders();

// Check rate limiting (10 req/min per IP)
// Skip rate limiting for whitelisted IPs (localhost/development)
$clientIP = SecurityHelper::getClientIP();
if (!SecurityHelper::isWhitelistedIP($clientIP)) {
    if (!SecurityHelper::checkRateLimit($clientIP, 10, 60)) {
        SecurityHelper::rateLimitExceeded();
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
    
    public function __construct() {
        $this->db = getDB();
        $this->startTime = microtime(true);
    }
    
    /**
     * Main chat handler
     */
    public function handleChat($sessionId, $message) {
        // Validate input
        if (empty($message)) {
            return $this->error("Message cannot be empty");
        }
        
        // Check if asking about news/activities
        // แต่ถ้าถามเกี่ยวกับ "ชมรม", "จิตอาสา", "กิจกรรม", "แข่งขัน" ให้ไปค้นหา FAQ แทน (เพราะ FAQ มีข้อมูลเหล่านี้)
        $skipNews = (mb_stripos($message, 'ชมรม') !== false) || 
                    (mb_stripos($message, 'จิตอาสา') !== false) ||
                    (mb_stripos($message, 'กิจกรรม') !== false) ||
                    (mb_stripos($message, 'แข่งขัน') !== false);
        
        if (!$skipNews) {
            $newsResults = $this->searchNews($message);
            if (!empty($newsResults)) {
                return $this->buildNewsResponse($sessionId, $message, $newsResults);
            }
        }
        
        // ===== กลยุทธ์การค้นหาคำตอบ FAQ (ใช้ LIKE Search เท่านั้น) =====
        // ค้นหา FAQ ก่อน เพราะมีระบบ scoring ที่แม่นยำกว่า
        // ปิด FULLTEXT ชั่วคราว เพื่อให้ระบบใช้ LIKE search ที่มีการคำนวณคะแนนใน PHP
        // ข้อดี: ค้นหาได้หลากหลาย, มีระบบคะแนนแบบละเอียด, ควบคุมได้ง่าย
        $faqResults = $this->searchFAQBroad($message);
        error_log("handleChat: LIKE search returned " . count($faqResults) . " results for '$message'");
        
        // ถ้า FAQ มี confidence ต่ำ (<40%) ให้ลองค้นหา staff
        $checkStaff = empty($faqResults) || (isset($faqResults[0]) && floatval($faqResults[0]['relevance']) < 200);
        
        // ตรวจสอบว่าถามเกี่ยวกับบุคลากร/อาจารย์หรือไม่ (ถ้า FAQ ไม่ดีพอ)
        if ($checkStaff) {
            $staffResults = $this->searchStaff($message);
            if (!empty($staffResults)) {
                return $this->buildStaffResponse($sessionId, $message, $staffResults);
            }
        }
        
        // ===== สร้างคำตอบจากผลการค้นหา =====
        if (!empty($faqResults)) {
            $bestMatch = $faqResults[0];
            
            // คำนวณ Confidence (ความมั่นใจ) จากคะแนน relevance
            // คะแนนเต็ม 1000+ = Exact Match = 95% confidence
            // คะแนน 500+ = Phrase Match = 85% confidence
            // คะแนน 200-500 = Good Match = 60-80% confidence
            // คะแนน 100-200 = Fair Match = 40-60% confidence
            // คะแนน 50-100 = Weak Match = 20-40% confidence
            // คะแนน < 50 = Very Weak = < 20% confidence
            $rawScore = floatval($bestMatch['relevance']);
            
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
            
            // กำหนดเกณฑ์ขั้นต่ำ - ถ้าคะแนนต่ำกว่า 20% ถือว่าไม่มีคำตอบที่เหมาะสม
            // (ลดจาก 30% เพื่อให้รองรับคำตอบที่มีความเกี่ยวข้องปานกลาง)
            if ($confidence < 20) {
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
                $answer = $this->formatFAQAnswer($bestMatch);
                $sources = [[
                    'type' => 'faq',
                    'id' => $bestMatch['id'],
                    'question' => $bestMatch['question']
                ]];
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
        
        // Get related questions from the same category
        $relatedQuestions = [];
        if (!empty($faqResults) && $confidence >= 20 && !empty($bestMatch['category'])) {
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
        $answer = "💬 คำถาม: {$faq['question']}\n\n";
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
    private function searchFAQBroad($query) {
        // ทำความสะอาดและ normalize
        $query = trim($query);
        
        // ===== ตรวจจับประเภทคำถาม (Question Intent) ก่อน normalize =====
        $intentPatterns = [
            'definition' => ['คืออะไร', 'หมายถึง', 'ความหมาย', 'นิยาม', 'อธิบาย'],
            'curriculum' => ['เปิดสอน', 'หลักสูตร', 'เรียน', 'วิชา', 'สาขาวิชา', 'แขนง'],
            'admission' => ['รับสมัคร', 'สมัคร', 'รับนักศึกษา', 'เข้าเรียน', 'สอบเข้า', 'คัดเลือก'],
            'contact' => ['ติดต่อ', 'เบอร์', 'โทร', 'อีเมล', 'email', 'สถานที่', 'ที่อยู่'],
            'facility' => ['ห้องปฏิบัติการ', 'ห้องแล็บ', 'lab', 'อุปกรณ์', 'สิ่งอำนวยความสะดวก'],
            'activity' => ['กิจกรรม', 'โครงการ', 'งานวิจัย', 'research'],
            'staff' => ['อาจารย์', 'คณาจารย์', 'ผู้สอน', 'บุคลากร']
        ];
        
        // ตรวจสอบ intent ของคำถามผู้ใช้ (ใช้ $query ต้นฉบับก่อน normalize)
        $queryIntent = null;
        error_log("Starting intent detection for query: '$query'");
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
        
        // ===== Query แบบง่าย - ดึงข้อมูลที่อาจเกี่ยวข้อง =====
        // ไม่คำนวณ score ใน SQL (ช้า) แต่คำนวณใน PHP แทน (เร็วกว่า)
        $sql = "SELECT f.id, f.question, f.answer, f.category, f.keywords
                FROM faq f
                WHERE f.is_active = 1 
                AND (
                    LOWER(TRIM(f.question)) = ? OR
                    LOWER(TRIM(f.question)) = ? OR
                    f.question LIKE ? OR
                    f.question LIKE ? OR
                    f.keywords LIKE ? OR
                    f.keywords LIKE ?";
        
        $params = [
            mb_strtolower(trim($query)),           // exact match original
            mb_strtolower(trim($normalizedQuery)), // exact match normalized
            "%{$query}%",                           // LIKE original
            "%{$normalizedQuery}%",                // LIKE normalized
            "%{$query}%",                           // keywords original
            "%{$normalizedQuery}%"                 // keywords normalized
        ];
        
        // เพิ่มเงื่อนไข LIKE สำหรับแต่ละ keyword (max 5 คำเพื่อไม่ให้ช้า)
        $limitedKeywords = array_slice($keywords, 0, 5);
        foreach ($limitedKeywords as $keyword) {
            if (mb_strlen($keyword) >= 2) {
                $sql .= " OR f.question LIKE ? OR f.keywords LIKE ?";
                $params[] = "%{$keyword}%";
                $params[] = "%{$keyword}%";
            }
        }
        
        $sql .= ") LIMIT 50";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll();
            
            error_log("searchFAQBroad: query='$query', normalized='$normalizedQuery', keywords=" . json_encode($keywords) . ", found=" . count($results));
            
            if (empty($results)) {
                return [];
            }
            
            // ===== คำนวณคะแนนใน PHP (เร็วกว่า SQL) =====
            foreach ($results as &$row) {
                $score = 0;
                $question = $row['question'];
                $keywords_field = $row['keywords'] ?? '';
                $answer = $row['answer'] ?? '';
                
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
                
                // [1000 pts] Exact Match
                if (mb_strtolower(trim($question)) === mb_strtolower(trim($query))) {
                    $score += 1000;
                }
                
                // [500 pts] Phrase Match (ทั้งข้อความอยู่ใน question)
                $phrasePos = mb_stripos($question, $query);
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
                    
                    // [+400 pts] Length Match Bonus - ถ้าความยาวคำถามใกล้เคียงกับ query
                    $questionLen = mb_strlen($question);
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
                
                // ===== [+800 pts / -800 pts] Intent Match Bonus/Penalty =====
                if ($queryIntent !== null && $faqIntent !== null) {
                    if ($queryIntent === $faqIntent) {
                        // Intent ตรงกัน → Boost มาก
                        $score += 800;
                        error_log("Intent MATCH: query=$queryIntent, faq=$faqIntent, Q: $question");
                    } else {
                        // Intent ไม่ตรงกัน → ลดคะแนนมาก (ให้คะแนนติดลบได้)
                        $score -= 800;
                        error_log("Intent MISMATCH: query=$queryIntent, faq=$faqIntent, Q: $question (penalty -800)");
                    }
                } else {
                    error_log("Intent NOT DETECTED: query=$queryIntent, faq=$faqIntent, Q: $question");
                }
                
                // [300 pts] Normalized Phrase Match
                if ($normalizedQuery !== $query && mb_stripos($question, $normalizedQuery) !== false) {
                    $score += 300;
                }
                
                // [100 pts] Category Match
                if (!empty($categoryBoost) && $row['category'] === $categoryBoost) {
                    $score += 100;
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
                
                $row['relevance'] = $score;
            }
            
            // เรียงตามคะแนน
            usort($results, function($a, $b) {
                return $b['relevance'] - $a['relevance'];
            });
            
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
        $categoryKeywords = [
            'การรับสมัคร' => ['สมัคร', 'รับสมัคร', 'TCAS', 'รับตรง', 'โควตา', 'เข้าเรียน', 'คุณสมบัติ', 'เอกสารสมัคร'],
            'หลักสูตร' => ['หลักสูตร', 'สาขา', 'วิชา', 'เรียน', 'แผนการเรียน', 'รายวิชา', 'ปริญญา'],
            'ทุนการศึกษา' => ['ทุน', 'กยศ', 'กรอ', 'ทุนการศึกษา', 'กู้ยืม', 'ทุนกู้'],
            'สิ่งอำนวยความสะดวก' => ['หอพัก', 'WiFi', 'ห้องสมุด', 'โรงอาหาร', 'ATM', 'ที่พัก'],
            'เอกสารและระบบ' => ['เพิ่มถอน', 'ลงทะเบียน', 'transcript', 'ผลการเรียน', 'เกรด', 'บัตรนักศึกษา'],
            'กิจกรรม' => ['กิจกรรม', 'ต้อนรับน้อง', 'กีฬาสี', 'event', 'อีเว้นท์'],
        ];
        
        foreach ($categoryKeywords as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (mb_stripos($query, $keyword) !== false) {
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
            'รับสมัคร' => 'รับสมัคร สมัคร เปิดรับ admission การรับสมัคร',
            'สมัครเรียน' => 'สมัคร รับสมัคร เปิดรับ การรับสมัคร',
            'เข้าเรียน' => 'สมัคร รับสมัคร เข้าศึกษา เข้าเรียน',
            'TCAS' => 'TCAS ทีแคส รับสมัคร admission',
            'Direct Admission' => 'รับตรง Direct Admission โควตา',
            
            // ทุนการศึกษา
            'กอ งทุนเงินให้กู้ยืมเพื่อการศึกษา' => 'กอ งทุนเงินให้กู้ยืมเพื่อการศึกษา กยศ กรอ ทุนกู้ยืม scholarship loan',
            'กยศ' => 'กอ งทุนเงินให้กู้ยืมเพื่อการศึกษา กยศ ทุนกู้ยืม กู้เงิน',
            'กรอ' => 'กอ งทุนเงินให้กู้ยืมเพื่อการศึกษา กรอ ทุนกู้ยืม',
            'ทุน' => 'ทุน ทุนการศึกษา scholarship กยศ กรอ',
            'กู้ยืม' => 'กู้ยืม กยศ กรอ ทุน กู้เงิน ทุนกู้',
            'ทุนกู้' => 'ทุนกู้ กยศ กรอ กู้ยืม',
            
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
                'คณะ', 'สาขา', 'หลักสูตร', 'เรียน', 'สมัคร', 'รับสมัคร', 'ค่าเทอม', 'ทุน', 'กยศ', 'กรอ',
                'หอพัก', 'ห้องสมุด', 'อาจารย์', 'บุคลากร', 'ติดต่อ', 'โทร', 'อีเมล',
                'อะไร', 'ยังไง', 'อย่างไร', 'เท่าไหร่', 'ที่ไหน', 'เมื่อไหร่', 'ทำไม'
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
    public function listFAQs($limit = 500, $category = null) {
        $sql = "SELECT id, question, category FROM faq WHERE is_active = 1";
        
        if ($category) {
            $sql .= " AND category = :category";
        }
        
        $sql .= " ORDER BY id ASC LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        
        if ($category) {
            $stmt->bindValue(':category', $category, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Error response
     */
    private function error($message) {
        http_response_code(400);
        return ['error' => $message];
    }
}

// Main execution
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
        $limit = isset($input['limit']) ? intval($input['limit']) : 50;
        $category = $input['category'] ?? null;
        
        $faqs = $chatbot->listFAQs($limit, $category);
        
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
