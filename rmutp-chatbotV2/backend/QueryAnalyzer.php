<?php
/**
 * QueryAnalyzer - วิเคราะห์และประมวลผลคำถามผู้ใช้
 * 
 * แยกออกจาก Chatbot class เพื่อให้โค้ดสั้นลงและดูแลง่าย
 * รวมฟังก์ชัน: normalize, expand synonyms, extract keywords,
 * detect intent, detect department, detect category
 * 
 * @version 2.0 (Refactored 2026-02-09)
 */

require_once __DIR__ . '/ChatbotConfig.php';

class QueryAnalyzer
{
    /**
     * Normalize คำถาม → แปลงคำย่อ/ภาษาพูด เป็นรูปแบบมาตรฐาน
     * เช่น "คอม" → "วิศวกรรมคอมพิวเตอร์", "กยศ" → "กองทุนเงินให้กู้ยืม..."
     */
    public static function normalizeQuery(string $query): string
    {
        $normalized = $query;
        foreach (ChatbotConfig::$normalizations as $pattern => $replacement) {
            $normalized = preg_replace($pattern, $replacement, $normalized);
        }
        return $normalized;
    }

    /**
     * ขยาย query ด้วย synonyms เพื่อให้ค้นหาได้กว้างขึ้น
     * เช่น "ค่าเทอม" → "ค่าเทอม ค่าเรียน ค่าใช้จ่าย tuition fee ผ่อน"
     */
    public static function expandQuerySynonyms(string $query): string
    {
        $expandedQuery = $query;
        foreach (ChatbotConfig::$synonyms as $word => $expansion) {
            if (mb_stripos($query, $word) !== false) {
                $expandedQuery .= ' ' . $expansion;
            }
        }

        // Auto-append "เรียนอะไร" ถ้าพิมพ์แค่ชื่อสาขา ไม่มีคำถาม
        if (!preg_match('/(เรียน|มี|คือ|ทำ|อะไร|ไหน|เท่าไหร่|กี่)/u', $query)) {
            if (preg_match('/วิศวกรรม(ไฟฟ้า|คอมพิวเตอร์|โยธา|เครื่องกล|อิเล็กทรอนิกส์|อุตสาหการ|เคมี|สิ่งแวดล้อม)/u', $query)) {
                $expandedQuery .= ' เรียนอะไร หลักสูตร curriculum';
            }
        }

        return $expandedQuery;
    }

    /**
     * แยกคำสำคัญจากคำถาม
     * กรองคำไม่จำเป็น (ครับ, ค่ะ) แต่เก็บคำสำคัญ (เรียน, ค่าเทอม)
     * ถ้าภาษาไทยไม่มีช่องว่าง จะพยายามตัดคำด้วย patterns ที่รู้จัก
     */
    public static function extractKeywords(string $query): array
    {
        $cleaned = $query;
        foreach (ChatbotConfig::$stopWords as $stopWord) {
            $cleaned = preg_replace('/\b' . preg_quote($stopWord, '/') . '\b/ui', ' ', $cleaned);
        }

        // แยกด้วยช่องว่าง
        $keywords = preg_split('/\s+/', trim($cleaned), -1, PREG_SPLIT_NO_EMPTY);
        $keywords = array_filter($keywords, function ($k) {
            return mb_strlen($k) >= 2;
        });

        // ถ้าไม่มีคำ หรือเหลือคำเดียวที่ยาว (ภาษาไทยติดกัน) → พยายามตัดคำ
        if (empty($keywords) || (count($keywords) === 1 && mb_strlen($keywords[0]) >= 4)) {
            $keywords = [$query];

            foreach (ChatbotConfig::$commonWords as $word) {
                if (mb_stripos($query, $word) !== false && !in_array($word, $keywords)) {
                    $keywords[] = $word;
                }
            }
        }

        // Fallback: ใช้คำเดิมทั้งหมด
        if (empty($keywords)) {
            $keywords = preg_split('/\s+/', $query, -1, PREG_SPLIT_NO_EMPTY);
            $keywords = array_filter($keywords, function ($k) {
                return mb_strlen($k) >= 2;
            });
        }

        return array_values(array_unique($keywords));
    }

    /**
     * ตรวจจับประเภทคำถาม (Intent) จากรูปแบบคำ
     * เช่น "ค่าเทอม" → scholarship, "ติดต่อ" → contact
     * 
     * @return string|null intent name หรือ null ถ้าตรวจไม่ได้
     */
    public static function detectQueryIntent(string $query): ?string
    {
        foreach (ChatbotConfig::$intentPatterns as $intent => $patterns) {
            foreach ($patterns as $pattern) {
                if (mb_stripos($query, $pattern) !== false) {
                    return $intent;
                }
            }
        }
        return null;
    }

    /**
     * ตรวจจับสาขาจากคำถาม (ใช้สำหรับ SQL filtering)
     * เช่น "วิศวกรรมคอมพิวเตอร์" → computer_engineering
     * 
     * @return string|null department code หรือ null
     */
    public static function detectDepartment(string $query): ?string
    {
        // ตรวจหา department ทั้งหมดที่ match
        $detected = [];
        foreach (ChatbotConfig::$departmentMap as $keyword => $dept) {
            if (mb_stripos($query, $keyword) !== false) {
                if (!in_array($dept, $detected)) {
                    $detected[] = $dept;
                }
            }
        }

        // ถ้าเจอหลาย dept ที่ขัดแย้งกัน → skip filter (ambiguous)
        if (count($detected) === 1) {
            return $detected[0];
        } elseif (count($detected) > 1) {
            error_log("Multiple departments detected: " . implode(', ', $detected) . " → skipping dept filter");
            return null;
        }

        return null;
    }

    /**
     * ตรวจสอบว่าควรยกเลิก department filter หรือไม่
     * เช่น "คอมพิวเตอร์ที่คณะแรงพอไหม" → ไม่ควร filter เป็น computer_engineering
     */
    public static function shouldSkipDeptFilter(string $query): bool
    {
        foreach (ChatbotConfig::$skipDeptKeywords as $skipKw) {
            if (mb_stripos($query, $skipKw) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * ตรวจจับหมวดหมู่จาก query (ใช้สำหรับ scoring boost)
     * 
     * @return string ชื่อหมวดหมู่ หรือ '' ถ้าไม่พบ
     */
    public static function detectCategory(string $query): string
    {
        foreach (ChatbotConfig::$categoryKeywords as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (mb_stripos($query, $keyword) !== false) {
                    return $category;
                }
            }
        }
        return '';
    }

    /**
     * ตรวจจับ intent ของ FAQ (ใช้ในการ scoring เปรียบเทียบกับ query intent)
     * 
     * @return string|null intent name หรือ null
     */
    public static function detectFAQIntent(string $question): ?string
    {
        foreach (ChatbotConfig::$intentPatterns as $intent => $patterns) {
            foreach ($patterns as $pattern) {
                if (mb_stripos($question, $pattern) !== false) {
                    return $intent;
                }
            }
        }
        return null;
    }
}
