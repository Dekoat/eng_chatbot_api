<?php
/**
 * Broad Topic Handler
 * จัดการคำถามกว้างๆ ที่ไม่ระบุรายละเอียด เช่น "ทุนการศึกษา", "ค่าเทอม", "ฝึกงาน"
 * ระบบจะแสดงภาพรวมหัวข้อ + รายการคำถามที่เกี่ยวข้องให้เลือก
 *
 * มี 2 แบบ:
 *   1. department — หัวข้อที่แตกต่างตามสาขา (ค่าเทอม, หลักสูตร) → แสดงตัวเลือกสาขา
 *   2. faq_list — หัวข้อที่มี FAQ หลายข้อ (ทุน, กยศ, ฝึกงาน) → แสดงรายการคำถาม
 *
 * รวม GenericQuestionHandler (post-FAQ-search detection) ไว้ในนี้ด้วย
 */

require_once __DIR__ . '/ChatbotConfig.php';

class BroadTopicHandler {

    /**
     * Topic Definitions — เพิ่ม/แก้ไขหัวข้อได้ที่นี่
     * - keywords: คำที่ trigger topic นี้
     * - title: ชื่อแสดงผล
     * - type: 'department' | 'faq_list'
     * - category (optional): กรอง FAQ ตาม category
     * - searchTerms: คำค้นหาใน question/keywords
     * - suggestion: ตัวอย่างคำถามที่เจาะจงกว่า
     */
    private static $topicDefinitions = [
        // ===== หัวข้อแบบ per-department (แสดงตัวเลือกสาขา) =====
        [
            'id' => 'tuition',
            'keywords' => ['ค่าเทอม', 'ค่าเล่าเรียน'],
            'title' => '💵 ค่าเทอม / ค่าเล่าเรียน',
            'type' => 'department',
            'searchTerms' => ['ค่าเทอม'],
            'suggestion' => '"ค่าเทอมวิศวกรรมคอมพิวเตอร์" หรือ "ค่าเทอมเครื่องกล"',
        ],
        [
            'id' => 'curriculum',
            'keywords' => ['หลักสูตร', 'แผนการเรียน', 'เรียนอะไร', 'รายละเอียดหลักสูตร'],
            'title' => '📚 หลักสูตร / แผนการเรียน',
            'type' => 'department',
            'queryPrefix' => 'รายละเอียดหลักสูตร',
            'categories' => ['หลักสูตร', 'curriculum', 'program'],
            'searchTerms' => ['หลักสูตร', 'รายละเอียดหลักสูตร', 'แผนการเรียน', 'เปิดสอน', 'เรียนอะไร', 'ปริญญา', 'วิชา'],
            'suggestion' => '"หลักสูตรวิศวกรรมคอมพิวเตอร์" หรือ "เรียนอะไรบ้าง สาขาไฟฟ้า"',
        ],
        [
            'id' => 'career',
            'keywords' => ['อาชีพ', 'จบแล้วทำอะไร', 'ทำงานอะไร'],
            'title' => '💼 โอกาสทำงาน / อาชีพ',
            'type' => 'department',
            'searchTerms' => ['อาชีพ', 'ทำงาน', 'จบแล้ว'],
            'suggestion' => '"จบวิศวกรรมคอมทำงานอะไร" หรือ "อาชีพสาขาไฟฟ้า"',
        ],

        // ===== หัวข้อแบบ faq_list (แสดงรายการคำถาม) =====
        [
            'id' => 'scholarship',
            'keywords' => ['ทุนการศึกษา', 'ทุน'],
            'title' => '🎓 ทุนการศึกษา',
            'type' => 'faq_list',
            'category' => 'loan',
            'searchTerms' => ['ทุนการศึกษา'],
            'suggestion' => '"มีทุนการศึกษาอะไรบ้าง" หรือ "วิธีสมัครทุน"',
        ],
        [
            'id' => 'loan',
            'keywords' => ['กยศ', 'กู้ยืม', 'กองทุน', 'กรอ'],
            'title' => '💰 กองทุนกู้ยืมเพื่อการศึกษา (กยศ./กรอ.)',
            'type' => 'faq_list',
            'category' => 'loan',
            'searchTerms' => ['กยศ', 'กู้ยืม', 'กรอ.'],
            'suggestion' => '"คุณสมบัติกู้ยืม กยศ." หรือ "เอกสารกู้ยืม"',
        ],
        [
            'id' => 'internship',
            'keywords' => ['ฝึกงาน'],
            'title' => '🏢 ฝึกงาน / สหกิจศึกษา',
            'type' => 'faq_list',
            'searchTerms' => ['ฝึกงาน', 'สหกิจ'],
            'suggestion' => '"ฝึกงานปีไหน" หรือ "ฝึกงานสาขาเครื่องกล"',
        ],
        [
            'id' => 'coop',
            'keywords' => ['สหกิจ', 'สหกิจศึกษา'],
            'title' => '🤝 สหกิจศึกษา',
            'type' => 'faq_list',
            'searchTerms' => ['สหกิจ'],
            'suggestion' => '"สหกิจศึกษา เรียนปีไหน" หรือ "สหกิจ สาขาอุตสาหการ"',
        ],
        [
            'id' => 'transfer',
            'keywords' => ['โอนหน่วยกิต', 'เทียบโอน'],
            'title' => '🔄 โอนหน่วยกิต / เทียบโอน',
            'type' => 'faq_list',
            'searchTerms' => ['โอนหน่วยกิต', 'เทียบโอน'],
            'suggestion' => '"เทียบโอน ปวส." หรือ "โอนหน่วยกิต สาขาเครื่องกล"',
        ],
        [
            'id' => 'admission',
            'keywords' => ['สมัครเรียน', 'รับสมัคร'],
            'title' => '📝 การรับสมัครนักศึกษา',
            'type' => 'faq_list',
            'category' => 'admission',
            'searchTerms' => ['สมัคร', 'รับสมัคร'],
            'suggestion' => '"คุณสมบัติสมัครเรียน" หรือ "สมัครเรียนออนไลน์"',
        ],
        [
            'id' => 'grade',
            'keywords' => ['เกรด', 'ผลการเรียน'],
            'title' => '📊 เกรด / ผลการเรียน',
            'type' => 'faq_list',
            'searchTerms' => ['เกรด', 'GPA', 'ผลการเรียน'],
            'suggestion' => '"เกรดเฉลี่ยขั้นต่ำ" หรือ "ติด F ทำอย่างไร"',
        ],
    ];

    /**
     * Department label mapping — ใช้จาก ChatbotConfig
     */
    private static function getDeptLabel($dept) {
        return ChatbotConfig::$departmentDisplayLabels[$dept] ?? null;
    }

    /**
     * ตัดคำนำหน้าทั่วไปออกจากข้อความ
     */
    private static function cleanMessage($message) {
        $prefixes = [
            'อยากรู้เรื่อง', 'อยากถามเรื่อง', 'ถามเรื่อง',
            'ถามเกี่ยวกับ', 'เกี่ยวกับ', 'เรื่อง',
            'ถามว่า', 'ถาม', 'สอบถาม', 'ขอข้อมูล',
            'อยากรู้', 'อยากทราบ', 'อยากดู', 'มีข้อมูล',
            'ดูรายละเอียด', 'ขอดู', 'ดู',
        ];
        $message = trim($message);
        foreach ($prefixes as $prefix) {
            if (mb_strpos($message, $prefix) === 0) {
                $message = trim(mb_substr($message, mb_strlen($prefix)));
                break;
            }
        }
        return $message;
    }

    /**
     * ตรวจจับว่าข้อความเป็นคำถามกว้างๆ เกี่ยวกับหัวข้อหรือไม่
     * @return array|null topic config ถ้าเป็น broad topic, null ถ้าไม่ใช่
     */
    public static function detectBroadTopic($message) {
        $message = trim($message);

        // ข้อความยาวเกินกว่า 30 chars → น่าจะเจาะจงแล้ว
        if (mb_strlen($message) > 30) {
            return null;
        }

        // ตัดคำนำหน้าออก
        $cleanMsg = self::cleanMessage($message);
        if (mb_strlen($cleanMsg) > 20 || mb_strlen($cleanMsg) < 2) {
            return null;
        }

        // ตรวจว่าระบุสาขาหรือยัง — ถ้าระบุแล้วไม่ใช่ broad
        foreach (ChatbotConfig::$departmentDetectKeywords as $dept) {
            if (mb_stripos($cleanMsg, $dept) !== false) {
                return null;
            }
        }

        // จับคู่ topic — keyword ต้องเป็นส่วนใหญ่ของข้อความ (≥ 60%)
        foreach (self::$topicDefinitions as $topic) {
            foreach ($topic['keywords'] as $kw) {
                if (mb_stripos($cleanMsg, $kw) !== false) {
                    $ratio = mb_strlen($kw) / mb_strlen($cleanMsg);
                    if ($ratio >= 0.6) {
                        error_log("[BroadTopic] Detected topic '{$topic['id']}' — keyword='$kw', ratio=" . round($ratio, 2));
                        return $topic;
                    }
                }
            }
        }

        return null;
    }

    /**
     * ค้นหา FAQ ที่เกี่ยวข้องกับ topic
     */
    public static function searchRelatedFAQs($topic, $db) {
        $results = [];
        $existingIds = [];

        // สำหรับ department type: ค้นหาด้วย searchTerms เป็นหลัก (ต้องครอบคลุมทุกสาขา)
        // สำหรับ faq_list type: ค้นหาตาม category + searchTerms

        // 1. ค้นหาตาม searchTerms (สำคัญสำหรับทุก type)
        if (!empty($topic['searchTerms'])) {
            $conditions = [];
            $params = [];
            foreach ($topic['searchTerms'] as $term) {
                $conditions[] = "question LIKE ?";
                $conditions[] = "keywords LIKE ?";
                $params[] = "%{$term}%";
                $params[] = "%{$term}%";
            }
            $limit = ($topic['type'] === 'department') ? 100 : 30;
            $sql = "SELECT id, question, answer, category, department 
                    FROM faq WHERE is_active = 1 AND (" . implode(' OR ', $conditions) . ") 
                    ORDER BY id ASC LIMIT {$limit}";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
            foreach ($rows as $row) {
                $results[] = $row;
                $existingIds[] = $row['id'];
            }
        }

        // 2. เสริมด้วย category (ถ้ามี — ใช้ทั้ง faq_list และ department type)
        $categories = [];
        if (!empty($topic['category'])) {
            $categories = is_array($topic['category']) ? $topic['category'] : [$topic['category']];
        }
        if (!empty($topic['categories'])) {
            $categories = array_merge($categories, $topic['categories']);
        }
        $categories = array_unique($categories);
        
        if (!empty($categories)) {
            $placeholders = implode(',', array_fill(0, count($categories), '?'));
            $catLimit = ($topic['type'] === 'department') ? 500 : 40;
            $stmt = $db->prepare("SELECT id, question, answer, category, department 
                                  FROM faq WHERE is_active = 1 AND category IN ({$placeholders}) 
                                  ORDER BY id ASC LIMIT {$catLimit}");
            $stmt->execute($categories);
            $rows = $stmt->fetchAll();
            foreach ($rows as $row) {
                if (!in_array($row['id'], $existingIds)) {
                    $results[] = $row;
                    $existingIds[] = $row['id'];
                }
            }
        }

        error_log("[BroadTopic] Found " . count($results) . " FAQs for topic '{$topic['id']}'");
        return $results;
    }

    /**
     * สร้างคำตอบภาพรวมสำหรับ broad topic
     */
    public static function buildTopicOverview($topic, $faqs) {
        if ($topic['type'] === 'department') {
            return self::buildDepartmentOverview($topic, $faqs);
        }
        return self::buildFAQListOverview($topic, $faqs);
    }

    /**
     * สร้างคำตอบแบบแสดงตัวเลือกสาขา
     */
    private static function buildDepartmentOverview($topic, $faqs) {
        // รวบรวมสาขาที่มี FAQ — ใช้ searchTerms กรองให้ได้สาขาที่เกี่ยวข้องจริง
        $departments = [];
        $searchTerms = $topic['searchTerms'] ?? [];

        foreach ($faqs as $faq) {
            $dept = $faq['department'] ?? 'general';
            if ($dept === 'general' || $dept === 'student_affairs') continue;
            if (isset($departments[$dept])) continue;

            // ตรวจว่า FAQ เกี่ยวข้องกับ topic (มี searchTerm อยู่ใน question)
            if (!empty($searchTerms)) {
                $question = explode('|', $faq['question'])[0];
                $isRelevant = false;
                foreach ($searchTerms as $term) {
                    if (mb_stripos($question, $term) !== false) {
                        $isRelevant = true;
                        break;
                    }
                }
                if (!$isRelevant) continue;
            }

            // กรองเฉพาะสาขาวิศวกรรมที่รู้จักเท่านั้น
            if (!isset(ChatbotConfig::$departmentDisplayLabels[$dept])) continue;

            $departments[$dept] = ChatbotConfig::$departmentDisplayLabels[$dept];
        }

        if (empty($departments)) {
            return null;
        }

        $answer = "{$topic['title']}\n\n";
        $answer .= "ข้อมูลนี้แตกต่างกันในแต่ละสาขา กรุณาเลือกสาขาที่สนใจ:\n\n";

        // สร้างปุ่มกดซึ่งฝังอยู่ในคำตอบเลย — format: [[query||ข้อความแสดง]]
        $mainKeyword = $topic['queryPrefix'] ?? $searchTerms[0] ?? $topic['keywords'][0] ?? '';

        $num = 1;
        foreach ($departments as $dept => $label) {
            // ตัด emoji ออก เอาแค่ชื่อสาขา
            $deptName = preg_replace('/^[^\p{L}]+/u', '', $label);
            $query = "{$mainKeyword}{$deptName}";
            $answer .= "{$num}. [[{$query}||{$label}]]\n";
            $num++;
        }

        $answer .= "\n💡 กดเลือกสาขาที่สนใจ หรือพิมพ์ถามได้เลย";

        return [
            'answer' => $answer,
            'related_questions' => [],
        ];
    }

    /**
     * สร้างคำตอบแบบแสดงรายการ FAQ ที่เกี่ยวข้อง
     */
    private static function buildFAQListOverview($topic, $faqs) {
        // เลือก FAQ ที่เกี่ยวข้องที่สุด + ไม่ซ้ำกัน
        $selectedFAQs = self::selectRepresentativeFAQs($topic, $faqs);

        if (empty($selectedFAQs)) {
            return null;
        }

        $answer = "{$topic['title']}\n\n";
        $answer .= "มีข้อมูลที่เกี่ยวข้องหลายหัวข้อ กรุณาเลือกสิ่งที่สนใจ:\n\n";

        foreach ($selectedFAQs as $idx => $faq) {
            $question = explode('|', $faq['question'])[0];
            $question = trim($question);

            // แสดงสาขา (ถ้ามี)
            $dept = $faq['department'] ?? '';
            $deptSuffix = '';
            if ($dept && $dept !== 'general' && $dept !== 'student_affairs') {
                $deptLabel = ChatbotConfig::$departmentDisplayLabels[$dept] ?? '';
                if ($deptLabel) {
                    // เอาแค่ชื่อสาขา (ตัด emoji)
                    $deptName = preg_replace('/^[^\p{L}]+/u', '', $deptLabel);
                    $deptSuffix = " ({$deptName})";
                }
            }

            // ฝังปุ่มกดในข้อความ — [[query||ข้อความแสดง]]
            $answer .= "[[{$question}||📌 {$question}{$deptSuffix}]]\n";
        }

        $answer .= "\n💡 กดเลือกหัวข้อที่สนใจ หรือพิมพ์ถามได้เลย";

        return [
            'answer' => $answer,
            'related_questions' => [],
        ];
    }

    /**
     * เลือก FAQ ที่เป็นตัวแทนของหัวข้อ — จำกัด 8 ข้อ ไม่ซ้ำ
     */
    private static function selectRepresentativeFAQs($topic, $faqs) {
        $selected = [];
        $seenQuestions = [];

        // แยก FAQ เป็น 2 กลุ่ม: ตรง searchTerm (สำคัญกว่า) vs ตรงแค่ category
        $searchTermMatches = [];
        $categoryOnlyMatches = [];

        foreach ($faqs as $faq) {
            $question = explode('|', $faq['question'])[0];
            $question = trim($question);

            $matchesSearchTerm = false;
            if (!empty($topic['searchTerms'])) {
                foreach ($topic['searchTerms'] as $term) {
                    if (mb_stripos($question, $term) !== false) {
                        $matchesSearchTerm = true;
                        break;
                    }
                }
            }

            if ($matchesSearchTerm) {
                $searchTermMatches[] = $faq;
            } else {
                $categoryOnlyMatches[] = $faq;
            }
        }

        // ใช้ searchTerm matches ก่อน เสริมด้วย category matches ถ้ายังไม่ครบ 8
        $orderedFAQs = $searchTermMatches;
        if (count($orderedFAQs) < 8) {
            $orderedFAQs = array_merge($orderedFAQs, $categoryOnlyMatches);
        }

        // ถ้าไม่มีเลย ใช้ทั้งหมด
        if (empty($orderedFAQs)) {
            $orderedFAQs = $faqs;
        }

        foreach ($orderedFAQs as $faq) {
            $question = explode('|', $faq['question'])[0];
            $question = trim($question);

            // ตรวจซ้ำ — เทียบ 25 chars แรก
            $qKey = mb_substr($question, 0, 25);
            $isDuplicate = false;
            foreach ($seenQuestions as $seen) {
                if ($qKey === $seen) {
                    $isDuplicate = true;
                    break;
                }
                similar_text($qKey, $seen, $pct);
                if ($pct > 70) {
                    $isDuplicate = true;
                    break;
                }
            }

            if (!$isDuplicate) {
                $selected[] = $faq;
                $seenQuestions[] = $qKey;
            }

            if (count($selected) >= 8) break;
        }

        return $selected;
    }

    /**
     * สร้าง sources array สำหรับ response
     */
    public static function buildSources($faqs) {
        $sources = [];
        foreach ($faqs as $faq) {
            $question = explode('|', $faq['question'])[0];
            $sources[] = [
                'type' => 'faq',
                'id' => $faq['id'],
                'question' => trim($question),
                'department' => $faq['department'] ?? '',
            ];
        }
        return $sources;
    }

    /**
     * ดึง topic definitions (สำหรับ debug/testing)
     */
    public static function getTopicDefinitions() {
        return self::$topicDefinitions;
    }

    // ===========================================================
    // ===== Generic Question Detection (post-FAQ-search) =====
    // จัดการคำถามที่ยาวกว่า (>30 chars) แต่ไม่ระบุสาขา
    // เช่น "ค่าเทอมเท่าไหร่" → แสดงตัวเลือกสาขา
    // ===========================================================

    /**
     * ตรวจสอบว่าเป็น Generic Question หรือไม่ (หลัง FAQ search)
     */
    public static function isGenericQuestion($message, $faqResults) {
        // ตรวจ universal keyword (ทุกสาขาเหมือนกัน) → ไม่ใช่ Generic
        foreach (ChatbotConfig::$universalKeywords as $uk) {
            if (mb_stripos($message, $uk) !== false) {
                return false;
            }
        }

        // ตรวจ generic keyword (เฉพาะสาขา)
        $hasGenericKeyword = false;
        foreach (ChatbotConfig::$genericKeywords as $gk) {
            if (mb_stripos($message, $gk) !== false) {
                $hasGenericKeyword = true;
                break;
            }
        }
        if (!$hasGenericKeyword) return false;

        // ตรวจว่าระบุสาขาหรือไม่
        foreach (ChatbotConfig::$departmentDetectKeywords as $dept) {
            if (mb_stripos($message, $dept) !== false) {
                return false;
            }
        }

        // ถ้ามี generic keyword แต่ไม่มีชื่อสาขา และมี FAQ หลายอัน
        return ($hasGenericKeyword && count($faqResults) >= 2);
    }

    /**
     * ดึง department-specific answers จากผล FAQ search
     */
    public static function getDepartmentSpecificAnswers($faqResults) {
        $departmentAnswers = [];
        $seenDepartments = [];
        $bestScore = floatval($faqResults[0]['relevance']);

        foreach ($faqResults as $faq) {
            $faqScore = floatval($faq['relevance']);
            $scoreDiff = abs($bestScore - $faqScore);
            $scoreRatio = $bestScore > 0 ? ($scoreDiff / $bestScore) : 1;

            if ($scoreRatio <= 0.8 && $faqScore >= 30) {
                $dept = $faq['department'] ?? 'general';
                if ($dept === 'general') continue;

                $question = explode('|', $faq['question'])[0];

                if (!in_array($dept, $seenDepartments)) {
                    $seenDepartments[] = $dept;
                    $departmentAnswers[] = [
                        'id' => $faq['id'],
                        'question' => trim($question),
                        'department' => $dept,
                        'score' => $faqScore,
                        'category' => $faq['category'] ?? 'general',
                    ];
                } else {
                    // สาขาเดิม — เลือก FAQ ที่เหมาะสมกว่า (ป.ตรี > ป.โท)
                    foreach ($departmentAnswers as $idx => $existing) {
                        if ($existing['department'] === $dept) {
                            $existingHasMaster = (mb_strpos($existing['question'], 'ป.โท') !== false);
                            $newHasMaster = (mb_strpos($question, 'ป.โท') !== false);
                            if (($existingHasMaster && !$newHasMaster) || (!$existingHasMaster && !$newHasMaster && $faqScore > $existing['score'])) {
                                $departmentAnswers[$idx] = [
                                    'id' => $faq['id'],
                                    'question' => trim($question),
                                    'department' => $dept,
                                    'score' => $faqScore,
                                    'category' => $faq['category'] ?? 'general',
                                ];
                            }
                            break;
                        }
                    }
                }
            }
            if (count($departmentAnswers) >= 12) break;
        }
        return $departmentAnswers;
    }

    /**
     * สร้างคำตอบแบบแสดงตัวเลือกสาขา (หลัง FAQ search)
     */
    public static function buildGenericAnswer($departmentAnswers) {
        $answer = "📊 ข้อมูลแตกต่างกันในแต่ละสาขา\n\n";
        $answer .= "คำถามที่คุณถามมีคำตอบที่แตกต่างกันสำหรับแต่ละสาขา\n";
        $answer .= "กรุณาเลือกสาขาที่คุณสนใจ:\n\n";

        foreach ($departmentAnswers as $idx => $deptAnswer) {
            $deptName = ChatbotConfig::$departmentDisplayLabels[$deptAnswer['department']] ?? $deptAnswer['department'];
            $answer .= ($idx + 1) . ". " . $deptName . "\n";
            // ฝังปุ่มกดในคำตอบ — [[query||ข้อความแสดง]]
            $answer .= "   [[" . $deptAnswer['question'] . "||📝 " . $deptAnswer['question'] . "]]\n\n";
        }

        $answer .= "💡 กดเลือกสาขาที่สนใจ หรือพิมพ์ถามได้เลย";

        return [
            'answer' => $answer,
            'related_questions' => [],
        ];
    }

    /**
     * สร้าง sources สำหรับ generic answer
     */
    public static function buildGenericSources($departmentAnswers) {
        $sources = [];
        foreach ($departmentAnswers as $deptAnswer) {
            $sources[] = [
                'type' => 'faq',
                'id' => $deptAnswer['id'],
                'question' => $deptAnswer['question'],
                'department' => $deptAnswer['department'],
            ];
        }
        return $sources;
    }
}
