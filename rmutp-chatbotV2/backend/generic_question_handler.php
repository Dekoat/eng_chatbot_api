<?php
/**
 * Generic Question Handler
 * จัดการคำถามทั่วไปที่ไม่ระบุสาขา เช่น "ค่าเทอมเท่าไหร่" "เรียนอะไร"
 */

class GenericQuestionHandler {
    
    /**
     * ตรวจสอบว่าเป็น Generic Question หรือไม่
     */
    public static function isGenericQuestion($message, $faqResults) {
        // Keywords ที่แต่ละสาขาอาจตอบต่างกัน (เฉพาะข้อมูลเฉพาะสาขา)
        $genericKeywords = [
            'ค่าเทอม', 'ค่าเล่าเรียน', 'ค่าใช้จ่าย', 'tuition', 'fee',
            'หลักสูตร', 'curriculum',
            'เปิดสอน', 'สอนอะไร',
            'รับสมัคร', 'สมัครเรียน', 'admission', 'เข้าเรียน',
            'โอกาสทำงาน', 'อาชีพ', 'career', 'job'
        ];
        
        // Keywords ที่เป็นเรื่องทั่วไป (ทุกสาขาเหมือนกัน) - ไม่ควรเป็น Generic
        $universalKeywords = [
            'ลงทะเบียน', 'register', 'เพิ่มวิชา', 'ถอนวิชา', 'add', 'drop',
            'ชำระเงิน', 'จ่ายเงิน', 'โอนเงิน', 'payment', 'pay',
            'ยังไง', 'อย่างไร', 'วิธี', 'ขั้นตอน', 'how to',
            'ฝึกงาน', 'internship', 'สหกิจ', 'coop',
            'หอพัก', 'dormitory', 'dorm',
            'โรงอาหาร', 'cafeteria', 'canteen',
            'ทุนการศึกษา', 'scholarship',
            // เพิ่มเกี่ยวกับเกรด (ทุกสาขาใช้ระบบเดียวกัน)
            'เกรด', 'GPA', 'GPAX', 'ติด F', 'ติด I', 'สอบตก', 'เรียนซ้ำ', 'แก้เกรด', 'Re-grade',
            'พ้นสภาพ', 'รีไทร์', 'retire', 'Incomplete'
        ];
        
        error_log("[GenericHandler] Checking message: " . $message);
        
        // ตรวจสอบว่ามี universal keyword (คำถามทั่วไป) - ถ้ามีไม่ควรเป็น Generic
        foreach ($universalKeywords as $uk) {
            if (mb_stripos($message, $uk) !== false) {
                error_log("[GenericHandler] ❌ Has universal keyword '$uk' - Not Generic");
                return false;
            }
        }
        
        // ตรวจสอบว่ามี generic keyword (เฉพาะสาขา)
        $hasGenericKeyword = false;
        foreach ($genericKeywords as $gk) {
            if (mb_stripos($message, $gk) !== false) {
                $hasGenericKeyword = true;
                error_log("[GenericHandler] Found generic keyword: $gk");
                break;
            }
        }
        
        if (!$hasGenericKeyword) {
            return false;
        }
        
        // ตรวจสอบว่าระบุสาขาหรือไม่
        $departments = [
            'ไฟฟ้า', 'คอมพิวเตอร์', 'คอม', 'เครื่องกล', 'อุตสาหการ',
            'เมคคาทรอนิกส์', 'โยธา', 'อิเล็กทรอนิกส์', 'เครื่องประดับ',
            'เครื่องมือ', 'แม่พิมพ์', 'สื่อสาร', 'อัจฉริยะ',
            'อส.บ', 'อส.บ.', 'SIME', 'วศ.บ', 'วศ.บ.', 'วศ.ม', 'วศ.ม.',
            'electrical', 'computer', 'mechanical', 'industrial',
            'civil', 'electronics', 'jewelry'
        ];
        
        $hasDepartmentName = false;
        foreach ($departments as $dept) {
            if (mb_stripos($message, $dept) !== false) {
                $hasDepartmentName = true;
                break;
            }
        }
        
        // ถ้ามี generic keyword แต่ไม่มีชื่อสาขา และมี FAQ หลายอัน
        if ($hasGenericKeyword && !$hasDepartmentName && count($faqResults) >= 2) {
            error_log("[GenericHandler] ✅ Detected as Generic Question");
            return true;
        }
        
        error_log("[GenericHandler] ❌ Not Generic Question - hasKeyword: $hasGenericKeyword, hasDept: $hasDepartmentName, FAQs: " . count($faqResults));
        return false;
    }
    
    /**
     * ดึง Department-Specific Answers
     */
    public static function getDepartmentSpecificAnswers($faqResults) {
        $departmentAnswers = [];
        $seenDepartments = [];
        
        $bestScore = floatval($faqResults[0]['relevance']);
        error_log("[GenericHandler] Best score: $bestScore");
        
        foreach ($faqResults as $faq) {
            $faqScore = floatval($faq['relevance']);
            $scoreDiff = abs($bestScore - $faqScore);
            $scoreRatio = $bestScore > 0 ? ($scoreDiff / $bestScore) : 1;
            
            // ผ่อนปรนมากขึ้น: ยอมรับ FAQ คะแนนห่างกันมาก (70%) และคะแนนต่ำกว่า (50)
            if ($scoreRatio <= 0.7 && $faqScore >= 50) {
                $dept = $faq['department'] ?? 'general';
                
                if (!in_array($dept, $seenDepartments)) {
                    $seenDepartments[] = $dept;
                    $departmentAnswers[] = [
                        'id' => $faq['id'],
                        'question' => explode('|', $faq['question'])[0],
                        'department' => $dept,
                        'score' => $faqScore,
                        'category' => $faq['category'] ?? 'general'
                    ];
                    error_log("[GenericHandler] Added dept: $dept (score: $faqScore, ratio: " . round($scoreRatio, 2) . ")");
                }
            }
            
            if (count($departmentAnswers) >= 6) break;
        }
        
        error_log("[GenericHandler] Found " . count($departmentAnswers) . " different departments");
        return $departmentAnswers;
    }
    
    /**
     * สร้างคำตอบสำหรับ Generic Question
     */
    public static function buildGenericAnswer($departmentAnswers) {
        $deptLabels = [
            'electrical' => '🔌 วิศวกรรมไฟฟ้า',
            'computer' => '💻 วิศวกรรมคอมพิวเตอร์',
            'mechanical' => '⚙️ วิศวกรรมเครื่องกล',
            'industrial' => '🏭 วิศวกรรมอุตสาหการ',
            'civil' => '🏗️ วิศวกรรมโยธา',
            'mechatronics' => '🤖 วิศวกรรมเมคคาทรอนิกส์',
            'electronics' => '📡 วิศวกรรมอิเล็กทรอนิกส์และโทรคมนาคม',
            'jewelry' => '💎 วิศวกรรมเครื่องประดับ',
            'tool' => '🔧 วิศวกรรมเครื่องมือและแม่พิมพ์',
            'sime' => '📢 วิศวกรรมสื่อสารและระบบอัจฉริยะ',
            'general' => 'ℹ️ ทั่วไป'
        ];
        
        $answer = "📊 ข้อมูลแตกต่างกันในแต่ละสาขา\n\n";
        $answer .= "คำถามที่คุณถามมีคำตอบที่แตกต่างกันสำหรับแต่ละสาขา\n";
        $answer .= "กรุณาเลือกสาขาที่คุณสนใจ:\n\n";
        
        foreach ($departmentAnswers as $idx => $deptAnswer) {
            $deptName = $deptLabels[$deptAnswer['department']] ?? $deptAnswer['department'];
            $answer .= ($idx + 1) . ". " . $deptName . "\n";
            $answer .= "   📝 " . $deptAnswer['question'] . "\n\n";
        }
        
        $answer .= str_repeat("─", 50) . "\n";
        $answer .= "💡 กรุณาถามใหม่โดยระบุสาขา เช่น:\n";
        $answer .= "   • \"ค่าเทอมวิศวกรรมคอมพิวเตอร์\"\n";
        $answer .= "   • \"หลักสูตรเครื่องกล\"\n";
        $answer .= "   • \"รับสมัครไฟฟ้า\"\n";
        $answer .= "   ฯลฯ";
        
        return $answer;
    }
    
    /**
     * สร้าง Sources สำหรับ Generic Answer
     */
    public static function buildSources($departmentAnswers) {
        $sources = [];
        foreach ($departmentAnswers as $deptAnswer) {
            $sources[] = [
                'type' => 'faq',
                'id' => $deptAnswer['id'],
                'question' => $deptAnswer['question'],
                'department' => $deptAnswer['department']
            ];
        }
        return $sources;
    }
}
