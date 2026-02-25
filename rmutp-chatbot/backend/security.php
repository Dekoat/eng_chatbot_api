<?php
/**
 * Security Helper Functions
 * CORS และ Rate Limiting
 */

class SecurityHelper {
    
    /**
     * ตรวจสอบว่า IP อยู่ใน whitelist หรือไม่ (สำหรับ development)
     * @param string $ip
     * @return bool
     */
    public static function isWhitelistedIP($ip) {
        $whitelist = [
            '127.0.0.1',
            '::1',
            'localhost'
        ];
        return in_array($ip, $whitelist);
    }
    
    /**
     * ตั้งค่า CORS headers ที่ปลอดภัย
     */
    public static function setCORSHeaders() {
        $allowed_origins = [
            'http://localhost',
            'http://localhost:80',
            'http://127.0.0.1',
            'http://127.0.0.1:80',
            // เพิ่ม production domain ตอน deploy
            // 'https://eng.rmutp.ac.th'
        ];
        
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        if (in_array($origin, $allowed_origins)) {
            header("Access-Control-Allow-Origin: $origin");
        }
        // ถ้า origin ไม่อยู่ใน whitelist → ไม่ส่ง CORS header (browser จะ block เอง)
        
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400'); // 24 hours
        
        // Handle preflight
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
    }
    
    /**
     * ตรวจสอบ Rate Limiting (10 requests/minute per IP)
     * @return bool true = pass, false = exceeded
     */
    public static function checkRateLimit($ip, $limit = 10, $window = 60) {
        $db = getDB();
        
        // สร้างตาราง rate_limits ถ้ายังไม่มี
        self::createRateLimitTable($db);
        
        // ลบ records เก่าที่เกิน window
        $cutoff = date('Y-m-d H:i:s', time() - $window);
        $sql = "DELETE FROM rate_limits WHERE created_at < ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$cutoff]);
        
        // นับจำนวน requests ของ IP นี้
        $sql = "SELECT COUNT(*) as count FROM rate_limits 
                WHERE ip_address = ? AND created_at > ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$ip, $cutoff]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] >= $limit) {
            return false; // เกินขั้น
        }
        
        // บันทึก request นี้
        $sql = "INSERT INTO rate_limits (ip_address, endpoint, created_at) 
                VALUES (?, ?, NOW())";
        $stmt = $db->prepare($sql);
        $stmt->execute([$ip, $_SERVER['REQUEST_URI'] ?? '']);
        
        return true;
    }
    
    /**
     * สร้างตาราง rate_limits
     */
    private static function createRateLimitTable($db) {
        $sql = "CREATE TABLE IF NOT EXISTS rate_limits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            endpoint VARCHAR(255),
            created_at DATETIME NOT NULL,
            INDEX idx_ip_time (ip_address, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $db->exec($sql);
    }
    
    /**
     * ส่ง response เมื่อเกิน rate limit
     */
    public static function rateLimitExceeded() {
        http_response_code(429);
        header('Retry-After: 60');
        echo json_encode([
            'error' => 'Too many requests',
            'message' => 'คุณส่ง request มากเกินไป กรุณารอ 1 นาทีแล้วลองใหม่',
            'retry_after' => 60
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    /**
     * ดึง IP address ของ client
     * ใช้ REMOTE_ADDR เป็นหลัก (ปลอดภัยที่สุด)
     * Proxy headers (X-Forwarded-For ฯลฯ) สามารถ spoof ได้ จึงใช้เฉพาะเมื่อ TRUST_PROXY=true
     */
    public static function getClientIP() {
        // อ่านค่า TRUST_PROXY จาก .env (default: false)
        $trustProxy = strtolower(trim($_ENV['TRUST_PROXY'] ?? 'false')) === 'true';
        
        if ($trustProxy) {
            $headers = [
                'HTTP_CF_CONNECTING_IP',  // Cloudflare
                'HTTP_X_FORWARDED_FOR',
                'HTTP_X_REAL_IP',
            ];
            
            foreach ($headers as $header) {
                if (!empty($_SERVER[$header])) {
                    $ip = $_SERVER[$header];
                    if (strpos($ip, ',') !== false) {
                        $ips = explode(',', $ip);
                        $ip = trim($ips[0]);
                    }
                    if (filter_var($ip, FILTER_VALIDATE_IP)) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * ตรวจสอบ Input validation พื้นฐาน
     */
    public static function validateInput($data, $maxLength = 1000) {
        if (empty($data)) {
            return false;
        }
        
        if (mb_strlen($data, 'UTF-8') > $maxLength) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Sanitize output สำหรับ HTML
     */
    public static function sanitizeOutput($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}
