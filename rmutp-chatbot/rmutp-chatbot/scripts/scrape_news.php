<?php
/**
 * RMUTP Engineering News Scraper (PHP Version)
 * ดึงข่าวและกิจกรรมจากเว็บไซต์คณะวิศวกรรมศาสตร์ มทร.พระนคร
 * อัปเดตอัตโนมัติเข้าฐานข้อมูล
 */

require_once __DIR__ . '/../backend/db.php';

class EngNewsScraper {
    private $db;
    private $startTime;
    private $logFile;
    
    public function __construct() {
        $this->db = getDB();
        $this->startTime = microtime(true);
        $this->logFile = __DIR__ . '/logs/scraper_' . date('Y-m-d') . '.log';
        
        // สร้าง logs directory ถ้ายังไม่มี
        if (!is_dir(__DIR__ . '/logs')) {
            mkdir(__DIR__ . '/logs', 0755, true);
        }
        
        $this->log("เชื่อมต่อฐานข้อมูลสำเร็จ");
        echo "✅ เชื่อมต่อฐานข้อมูลสำเร็จ\n";
    }
    
    /**
     * บันทึก log
     */
    private function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$level}] {$message}\n";
        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
    }
    
    /**
     * ดึงข่าวจากหน้าแรก https://eng.rmutp.ac.th/
     */
    public function scrapeEngHomepage() {
        $this->log("เริ่มดึงข่าวจาก https://eng.rmutp.ac.th/");
        echo "\n🔍 กำลังดึงข่าวจาก https://eng.rmutp.ac.th/ ...\n";
        
        try {
            // ดึง HTML จากเว็บไซต์
            $html = $this->fetchUrl('https://eng.rmutp.ac.th/');
            
            if (!$html) {
                $this->log("ไม่สามารถดึงข้อมูลจากเว็บไซต์", 'ERROR');
                echo "⚠️  ไม่สามารถดึงข้อมูลจากเว็บไซต์\n";
                return 0;
            }
            
            // แปลง HTML เป็น DOM
            $dom = new DOMDocument();
            @$dom->loadHTML($html);
            $xpath = new DOMXPath($dom);
            
            $newsCount = 0;
            
            // วิธีที่ 1: หา links ที่เป็นข่าว
            $links = $xpath->query('//a[@href]');
            
            echo "📰 พบ links ทั้งหมด: " . $links->length . " รายการ\n";
            
            $processedLinks = [];
            
            foreach ($links as $link) {
                try {
                    $href = $link->getAttribute('href');
                    $text = trim($link->textContent);
                    
                    // กรองเฉพาะข่าวที่น่าสนใจ
                    if (strlen($text) < 20) continue;
                    
                    $keywords = ['ข่าว', 'กิจกรรม', 'ประกาศ', 'โครงการ', 'รับสมัคร', 
                                'แข่งขัน', 'มอบ', 'ความยินดี', 'จัดงาน', 'อบรม'];
                    
                    $hasKeyword = false;
                    foreach ($keywords as $keyword) {
                        if (mb_stripos($text, $keyword) !== false) {
                            $hasKeyword = true;
                            break;
                        }
                    }
                    
                    if (!$hasKeyword) continue;
                    
                    // แปลง relative URL เป็น absolute
                    if (!empty($href)) {
                        if (strpos($href, 'http') !== 0) {
                            $href = (strpos($href, '/') === 0) 
                                ? 'https://eng.rmutp.ac.th' . $href 
                                : 'https://eng.rmutp.ac.th/' . $href;
                        }
                    } else {
                        $href = 'https://eng.rmutp.ac.th/';
                    }
                    
                    // ข้าม javascript: และ # links
                    if (strpos($href, 'javascript:') !== false || $href === '#') {
                        continue;
                    }
                    
                    // ข้ามลิงก์ซ้ำ
                    if (in_array($href, $processedLinks)) {
                        continue;
                    }
                    $processedLinks[] = $href;
                    
                    // หารูปภาพ
                    $imageUrl = null;
                    $imgs = $link->getElementsByTagName('img');
                    if ($imgs->length > 0) {
                        $imgSrc = $imgs->item(0)->getAttribute('src');
                        if (!empty($imgSrc)) {
                            $imageUrl = (strpos($imgSrc, 'http') !== 0)
                                ? 'https://eng.rmutp.ac.th' . $imgSrc
                                : $imgSrc;
                        }
                    }
                    
                    // ตรวจสอบซ้ำ
                    if ($this->newsExists($href)) {
                        echo "⏭️  ข่าวซ้ำ: " . mb_substr($text, 0, 50) . "...\n";
                        continue;
                    }
                    
                    // กำหนดประเภท
                    $category = $this->detectCategory($text);
                    
                    // สกัด tags
                    $tags = $this->extractTags($text);
                    
                    // บันทึกลงฐานข้อมูล
                    if ($this->insertNews($text, $text, $href, $imageUrl, $category, $tags)) {
                        $newsCount++;
                        $this->log("เพิ่มข่าว: " . mb_substr($text, 0, 60) . "...");
                        echo "✅ เพิ่ม: " . mb_substr($text, 0, 60) . "...\n";
                    }
                    
                    // จำกัดไม่เกิน 10 ข่าว
                    if ($newsCount >= 10) break;
                    
                } catch (Exception $e) {
                    continue;
                }
            }
            
            return $newsCount;
            
        } catch (Exception $e) {
            $this->log("เกิดข้อผิดพลาด: " . $e->getMessage(), 'ERROR');
            echo "❌ เกิดข้อผิดพลาด: " . $e->getMessage() . "\n";
            return 0;
        }
    }
    
    /**
     * ดึง HTML จาก URL
     */
    private function fetchUrl($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        
        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            $this->log("CURL Error: {$error}", 'ERROR');
        }
        
        if ($httpCode !== 200) {
            $this->log("เว็บไซต์ตอบกลับ HTTP {$httpCode}", 'WARNING');
            echo "⚠️  เว็บไซต์ตอบกลับ HTTP {$httpCode}\n";
            return false;
        }
        
        return $html;
    }
    
    /**
     * ตรวจสอบว่ามีข่าวนี้ในฐานข้อมูลแล้วหรือไม่
     */
    private function newsExists($link) {
        $stmt = $this->db->prepare("SELECT id FROM news WHERE link_url = ? LIMIT 1");
        $stmt->execute([$link]);
        return $stmt->fetch() !== false;
    }
    
    /**
     * เพิ่มข่าวใหม่เข้าฐานข้อมูล
     */
    private function insertNews($title, $content, $link, $image, $category, $tags) {
        try {
            // สรุปเนื้อหา
            $summary = mb_strlen($title) > 200 ? mb_substr($title, 0, 200) . '...' : $title;
            
            // วันที่ปัจจุบัน
            $today = date('Y-m-d');
            
            $sql = "INSERT INTO news (title, content, summary, category, thumbnail_url, 
                                     link_url, published_date, tags, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)";
            
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$title, $content, $summary, $category, $image, $link, $today, $tags]);
            
        } catch (PDOException $e) {
            echo "❌ บันทึกข้อมูลไม่สำเร็จ: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * ตรวจจับประเภทข่าวจากหัวข้อ
     */
    private function detectCategory($title) {
        $keywords_activity = [
            'กิจกรรม', 'โครงการ', 'แข่งขัน', 'ประชุม', 'สัมมนา', 
            'อบรม', 'ทัศนศึกษา', 'ดูงาน', 'จัดงาน', 'งาน',
            'มอบ', 'มอบทุน', 'แลกเปลี่ยน', 'ร่วม'
        ];
        
        $title_lower = mb_strtolower($title);
        
        foreach ($keywords_activity as $keyword) {
            if (mb_stripos($title_lower, $keyword) !== false) {
                return 'กิจกรรม';
            }
        }
        
        return 'ข่าวประชาสัมพันธ์';
    }
    
    /**
     * สกัดคำสำคัญจากหัวข้อ
     */
    private function extractTags($title) {
        $important_words = [];
        
        $keywords = [
            'รับสมัคร', 'ทุน', 'ทุนการศึกษา', 'แข่งขัน', 'สอบ', 
            'อบรม', 'วิจัย', 'รางวัล', 'ความยินดี', 'ประกาศ',
            'ปฏิทิน', 'ตารางเรียน', 'ลงทะเบียน', 'เปิดรับ',
            'ผ่อนผัน', 'ทหาร', 'วิศวกรรม', 'นิสิต', 'นักศึกษา'
        ];
        
        $title_lower = mb_strtolower($title);
        
        foreach ($keywords as $word) {
            if (mb_stripos($title_lower, $word) !== false) {
                $important_words[] = $word;
            }
        }
        
        return !empty($important_words) ? implode(',', $important_words) : 'ข่าวทั่วไป';
    }
    
    /**
     * ลบข่าวเก่าที่เกินกำหนด (set is_active = 0)
     * @param int $days จำนวนวันที่เก็บไว้ (default: 180 วัน = 6 เดือน)
     */
    public function cleanupOldNews($days = 180) {
        try {
            $sql = "UPDATE news 
                    SET is_active = 0 
                    WHERE published_date < DATE_SUB(NOW(), INTERVAL ? DAY)
                    AND is_active = 1";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$days]);
            
            $deleted = $stmt->rowCount();
            if ($deleted > 0) {
                $this->log("ปิดการแสดงข่าวเก่าเกิน {$days} วัน: {$deleted} รายการ");
                echo "🗑️  ปิดการแสดงข่าวเก่าเกิน {$days} วัน: {$deleted} รายการ\n";
            }
            
        } catch (PDOException $e) {
            $this->log("ทำความสะอาดข่าวเก่าไม่สำเร็จ: " . $e->getMessage(), 'ERROR');
            echo "⚠️  ทำความสะอาดข่าวเก่าไม่สำเร็จ: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * แสดงสถิติข่าวในฐานข้อมูล
     */
    public function getStats() {
        try {
            $sql = "SELECT category, COUNT(*) as count 
                    FROM news 
                    WHERE is_active = 1 
                    GROUP BY category";
            
            $stmt = $this->db->query($sql);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "\n📊 สถิติข่าวในระบบ:\n";
            $total = 0;
            foreach ($results as $row) {
                echo "   - {$row['category']}: {$row['count']} รายการ\n";
                $total += $row['count'];
            }
            echo "   รวมทั้งหมด: {$total} รายการ\n";
            
        } catch (PDOException $e) {
            echo "⚠️  ไม่สามารถแสดงสถิติ: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * แสดงเวลาที่ใช้
     */
    public function showExecutionTime() {
        $elapsed = round((microtime(true) - $this->startTime), 2);
        echo "\n⏱️  ใช้เวลา: {$elapsed} วินาที\n";
    }
}

// ========================
// Main Execution
// ========================

echo str_repeat("=", 60) . "\n";
echo "🚀 RMUTP Engineering News Scraper (PHP Version)\n";
echo "   คณะวิศวกรรมศาสตร์ มหาวิทยาลัยเทคโนโลยีราชมงคลพระนคร\n";
echo str_repeat("=", 60) . "\n";
echo "📅 วันที่: " . date('Y-m-d H:i:s') . "\n";

try {
    $scraper = new EngNewsScraper();
    
    // ดึงข่าวจากหน้าแรก
    $newsCount = $scraper->scrapeEngHomepage();
    
    // ทำความสะอาดข่าวเก่า (เกิน 180 วัน = 6 เดือน)
    $scraper->cleanupOldNews(180);
    
    // แสดงสถิติ
    $scraper->getStats();
    
    // แสดงเวลาที่ใช้
    $scraper->showExecutionTime();
    
    echo "\n" . str_repeat("=", 60) . "\n";
    if ($newsCount > 0) {
        echo "✅ เพิ่มข่าวใหม่: {$newsCount} รายการ\n";
    } else {
        echo "ℹ️  ไม่มีข่าวใหม่ (หรือข่าวทั้งหมดมีในระบบแล้ว)\n";
    }
    echo str_repeat("=", 60) . "\n";
    echo "📝 Log file: scripts/logs/scraper_" . date('Y-m-d') . ".log\n";
    
} catch (Exception $e) {
    $errorMsg = "เกิดข้อผิดพลาดร้ายแรง: " . $e->getMessage();
    error_log($errorMsg);
    echo "\n❌ {$errorMsg}\n";
    exit(1);
}
