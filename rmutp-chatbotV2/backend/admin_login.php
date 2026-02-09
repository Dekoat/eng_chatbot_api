<?php
/**
 * Admin Login API
 * ระบบล็อกอินสำหรับ Admin Dashboard
 */

header('Content-Type: application/json; charset=utf-8');

// Load security helper
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/db.php';

// Set CORS headers (allowlist)
SecurityHelper::setCORSHeaders();

// Check rate limiting (20 req/min for admin - more lenient)
// Skip rate limiting for whitelisted IPs (localhost/development)
$clientIP = SecurityHelper::getClientIP();
if (!SecurityHelper::isWhitelistedIP($clientIP)) {
    if (!SecurityHelper::checkRateLimit($clientIP, 20, 60)) {
        SecurityHelper::rateLimitExceeded();
    }
}

class AdminAuth {
    private $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    /**
     * ล็อกอิน
     */
    public function login($username, $password) {
        try {
            // ตรวจสอบ username และ password
            // TODO: ในโปรเจกต์จริง ควรใช้ bcrypt hash
            // ตอนนี้ใช้ค่า default: admin/admin123
            
            if ($username === 'admin' && $password === 'admin123') {
                // สร้าง token (simple JWT-like)
                $token = $this->generateToken($username);
                
                // บันทึก session
                $this->saveSession($username, $token);
                
                return [
                    'success' => true,
                    'message' => 'เข้าสู่ระบบสำเร็จ',
                    'token' => $token,
                    'username' => $username
                ];
            }
            
            return [
                'success' => false,
                'message' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * สร้าง token
     */
    private function generateToken($username) {
        $payload = [
            'username' => $username,
            'issued_at' => time(),
            'expires_at' => time() + (24 * 60 * 60) // 24 ชั่วโมง
        ];
        
        $token = base64_encode(json_encode($payload));
        $signature = hash_hmac('sha256', $token, 'rmutp_secret_key_2026');
        
        return $token . '.' . $signature;
    }
    
    /**
     * บันทึก session
     */
    private function saveSession($username, $token) {
        try {
            $sql = "INSERT INTO admin_sessions (username, token, created_at, expires_at)
                    VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 24 HOUR))";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$username, $token]);
            
        } catch (PDOException $e) {
            // ถ้า table ยังไม่มี ให้สร้าง
            if ($e->getCode() == '42S02') { // Table doesn't exist
                $this->createAdminSessionsTable();
                // ลองอีกครั้ง
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$username, $token]);
            }
        }
    }
    
    /**
     * สร้าง table admin_sessions (ถ้ายังไม่มี)
     */
    private function createAdminSessionsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS admin_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(100) NOT NULL,
            token TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NULL,
            is_active TINYINT(1) DEFAULT 1,
            INDEX idx_username (username),
            INDEX idx_token (token(100)),
            INDEX idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->exec($sql);
    }
    
    /**
     * ตรวจสอบ token
     */
    public function verifyToken($token) {
        try {
            list($payload, $signature) = explode('.', $token);
            
            // ตรวจสอบ signature
            $expectedSignature = hash_hmac('sha256', $payload, 'rmutp_secret_key_2026');
            if ($signature !== $expectedSignature) {
                return ['valid' => false, 'message' => 'Token ไม่ถูกต้อง'];
            }
            
            // ตรวจสอบ expiration
            $data = json_decode(base64_decode($payload), true);
            if ($data['expires_at'] < time()) {
                return ['valid' => false, 'message' => 'Token หมดอายุ'];
            }
            
            // ตรวจสอบใน database
            $sql = "SELECT * FROM admin_sessions 
                    WHERE token = ? AND is_active = 1 AND expires_at > NOW()
                    LIMIT 1";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$token]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$session) {
                return ['valid' => false, 'message' => 'Session ไม่ถูกต้อง'];
            }
            
            return [
                'valid' => true,
                'username' => $data['username'],
                'session' => $session
            ];
            
        } catch (Exception $e) {
            return ['valid' => false, 'message' => 'เกิดข้อผิดพลาด'];
        }
    }
    
    /**
     * Logout
     */
    public function logout($token) {
        try {
            $sql = "UPDATE admin_sessions SET is_active = 0 WHERE token = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$token]);
            
            return ['success' => true, 'message' => 'ออกจากระบบสำเร็จ'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'เกิดข้อผิดพลาด'];
        }
    }
}

// Main Execution
try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    $auth = new AdminAuth();
    
    // ตรวจสอบ action
    $action = $input['action'] ?? 'login';
    
    if ($action === 'login') {
        $username = $input['username'] ?? '';
        $password = $input['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            throw new Exception('กรุณากรอกชื่อผู้ใช้และรหัสผ่าน');
        }
        
        $result = $auth->login($username, $password);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        
    } elseif ($action === 'verify') {
        $token = $input['token'] ?? '';
        
        if (empty($token)) {
            throw new Exception('ไม่พบ token');
        }
        
        $result = $auth->verifyToken($token);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        
    } elseif ($action === 'logout') {
        $token = $input['token'] ?? '';
        
        if (empty($token)) {
            throw new Exception('ไม่พบ token');
        }
        
        $result = $auth->logout($token);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        
    } else {
        throw new Exception('Action ไม่ถูกต้อง');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
