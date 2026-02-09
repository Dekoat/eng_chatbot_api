<?php
/**
 * Admin API - CRUD Operations
 * จัดการ FAQ, Staff, News, Chat Logs
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/security.php';
require_once __DIR__ . '/db.php';

// Set CORS headers
SecurityHelper::setCORSHeaders();

// Check rate limiting (20 req/min for admin)
// Skip rate limiting for whitelisted IPs (localhost/development)
$clientIP = SecurityHelper::getClientIP();
if (!SecurityHelper::isWhitelistedIP($clientIP)) {
    if (!SecurityHelper::checkRateLimit($clientIP, 20, 60)) {
        SecurityHelper::rateLimitExceeded();
    }
}

class AdminAPI {
    private $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    /**
     * ตรวจสอบ token
     */
    private function verifyToken($token) {
        if (empty($token)) {
            $this->sendError('Token required', 401);
        }
        
        // แยก token
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            $this->sendError('Invalid token format', 401);
        }
        
        list($payload, $signature) = $parts;
        
        // ตรวจสอบ signature
        $secret = 'rmutp_secret_key_2026';
        $expected = hash_hmac('sha256', $payload, $secret);
        
        if (!hash_equals($expected, $signature)) {
            $this->sendError('Invalid token signature', 401);
        }
        
        // Decode payload
        $data = json_decode(base64_decode($payload), true);
        if (!$data) {
            $this->sendError('Invalid token payload', 401);
        }
        
        // ตรวจสอบ expiration
        if ($data['expires_at'] < time()) {
            $this->sendError('Token expired', 401);
        }
        
        // ตรวจสอบใน database
        $sql = "SELECT * FROM admin_sessions 
                WHERE token = ? AND is_active = 1 AND expires_at > NOW()";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$token]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$session) {
            $this->sendError('Token not found or expired', 401);
        }
        
        return $data['username'];
    }
    
    /**
     * FAQ Operations
     */
    public function listFAQs($token, $search = '', $category = '', $page = 1, $limit = 20) {
        $this->verifyToken($token);
        
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT id, question, answer, category, keywords, is_active 
                FROM faq WHERE 1=1";
        $params = [];
        
        if ($search) {
            $sql .= " AND (question LIKE ? OR answer LIKE ? OR keywords LIKE ?)";
            $searchTerm = "%{$search}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if ($category) {
            $sql .= " AND category = ?";
            $params[] = $category;
        }
        
        $sql .= " ORDER BY id ASC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $faqs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Count total
        $countSql = "SELECT COUNT(*) as total FROM faq WHERE 1=1";
        $countParams = [];
        if ($search) {
            $countSql .= " AND (question LIKE ? OR answer LIKE ? OR keywords LIKE ?)";
            $searchTerm = "%{$search}%";
            $countParams = [$searchTerm, $searchTerm, $searchTerm];
        }
        if ($category) {
            $countSql .= " AND category = ?";
            $countParams[] = $category;
        }
        
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($countParams);
        $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        $this->sendSuccess([
            'faqs' => $faqs,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / $limit)
        ]);
    }
    
    public function createFAQ($token, $data) {
        $this->verifyToken($token);
        
        if (empty($data['question']) || empty($data['answer']) || empty($data['category'])) {
            $this->sendError('Missing required fields', 400);
        }
        
        $sql = "INSERT INTO faq (question, answer, category, keywords, is_active) 
                VALUES (?, ?, ?, ?, 1)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['question'],
            $data['answer'],
            $data['category'],
            $data['keywords'] ?? ''
        ]);
        
        $this->sendSuccess([
            'id' => $this->db->lastInsertId(),
            'message' => 'FAQ created successfully'
        ]);
    }
    
    public function updateFAQ($token, $id, $data) {
        $this->verifyToken($token);
        
        if (empty($id)) {
            $this->sendError('FAQ ID required', 400);
        }
        
        $sql = "UPDATE faq SET 
                question = ?, 
                answer = ?, 
                category = ?, 
                keywords = ?,
                updated_at = NOW()
                WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['question'] ?? '',
            $data['answer'] ?? '',
            $data['category'] ?? '',
            $data['keywords'] ?? '',
            $id
        ]);
        
        $this->sendSuccess(['message' => 'FAQ updated successfully']);
    }
    
    public function deleteFAQ($token, $id) {
        $this->verifyToken($token);
        
        // Soft delete
        $sql = "UPDATE faq SET is_active = 0 WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        
        $this->sendSuccess(['message' => 'FAQ deleted successfully']);
    }
    
    /**
     * Staff Operations
     */
    public function listStaff($token, $search = '', $department = '', $page = 1, $limit = 20) {
        $this->verifyToken($token);
        
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT * FROM staff WHERE 1=1";
        $params = [];
        
        if ($search) {
            $sql .= " AND (name_th LIKE ? OR name_en LIKE ? OR email LIKE ?)";
            $searchTerm = "%{$search}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if ($department) {
            $sql .= " AND department LIKE ?";
            $params[] = "%{$department}%";
        }
        
        $sql .= " ORDER BY id ASC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Count total
        $countSql = "SELECT COUNT(*) as total FROM staff WHERE 1=1";
        $countParams = [];
        if ($search) {
            $countSql .= " AND (name_th LIKE ? OR name_en LIKE ? OR email LIKE ?)";
            $searchTerm = "%{$search}%";
            $countParams = [$searchTerm, $searchTerm, $searchTerm];
        }
        if ($department) {
            $countSql .= " AND department LIKE ?";
            $countParams[] = "%{$department}%";
        }
        
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($countParams);
        $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        $totalPages = ceil($total / $limit);
        
        // Get unique departments for filter
        $deptSql = "SELECT DISTINCT department FROM staff ORDER BY department";
        $deptStmt = $this->db->query($deptSql);
        $departments = $deptStmt->fetchAll(PDO::FETCH_COLUMN);
        
        $this->sendSuccess([
            'staff' => $staff,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => $totalPages,
            'departments' => $departments
        ]);
    }
    
    public function updateStaff($token, $id, $data) {
        $this->verifyToken($token);
        
        $sql = "UPDATE staff SET 
                name_th = ?, name_en = ?, position_th = ?, position_en = ?,
                department = ?, email = ?, phone = ?, expertise = ?,
                room = ?, office_hours = ?, availability = ?,
                updated_at = NOW()
                WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['name_th'] ?? '',
            $data['name_en'] ?? '',
            $data['position_th'] ?? '',
            $data['position_en'] ?? '',
            $data['department'] ?? '',
            $data['email'] ?? '',
            $data['phone'] ?? '',
            $data['expertise'] ?? '',
            $data['room'] ?? '',
            $data['office_hours'] ?? '',
            $data['availability'] ?? '',
            $id
        ]);
        
        $this->sendSuccess(['message' => 'Staff updated successfully']);
    }
    
    /**
     * Chat Logs
     */
    public function listChatLogs($token, $limit = 50, $offset = 0) {
        $this->verifyToken($token);
        
        $sql = "SELECT * FROM chat_logs 
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$limit, $offset]);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $this->sendSuccess(['logs' => $logs]);
    }
    
    /**
     * News Operations
     */
    public function listNews($token, $search = '', $category = '', $page = 1, $limit = 20) {
        $this->verifyToken($token);
        
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT id, title, summary, category, thumbnail_url, link_url, 
                       published_date, view_count, is_active, created_at 
                FROM news WHERE 1=1";
        $params = [];
        
        if ($search) {
            $sql .= " AND (title LIKE ? OR content LIKE ? OR summary LIKE ?)";
            $searchTerm = "%{$search}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if ($category) {
            $sql .= " AND category = ?";
            $params[] = $category;
        }
        
        // Count total
        $countSql = "SELECT COUNT(*) as total FROM news WHERE 1=1";
        if ($search) {
            $countSql .= " AND (title LIKE ? OR content LIKE ? OR summary LIKE ?)";
        }
        if ($category) {
            $countSql .= " AND category = ?";
        }
        
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = $countStmt->fetch()['total'];
        
        $sql .= " ORDER BY published_date DESC, created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $news = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $this->sendSuccess([
            'news' => $news,
            'total' => $total,
            'page' => $page,
            'total_pages' => ceil($total / $limit)
        ]);
    }
    
    public function createNews($token, $data) {
        $this->verifyToken($token);
        
        $sql = "INSERT INTO news (title, content, summary, category, thumbnail_url, 
                                 link_url, published_date, department, tags, is_active) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $this->db->prepare($sql)->execute([
            $data['title'] ?? '',
            $data['content'] ?? '',
            $data['summary'] ?? '',
            $data['category'] ?? 'ข่าวประชาสัมพันธ์',
            $data['thumbnail_url'] ?? '',
            $data['link_url'] ?? '',
            $data['published_date'] ?? date('Y-m-d'),
            $data['department'] ?? 'general',
            $data['tags'] ?? '',
            $data['is_active'] ?? 1
        ]);
        
        $this->sendSuccess(['message' => 'News created successfully']);
    }
    
    public function updateNews($token, $id, $data) {
        $this->verifyToken($token);
        
        $sql = "UPDATE news SET 
                title = ?, content = ?, summary = ?, category = ?, 
                thumbnail_url = ?, link_url = ?, published_date = ?, 
                department = ?, tags = ?, is_active = ?, 
                updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?";
        
        $this->db->prepare($sql)->execute([
            $data['title'] ?? '',
            $data['content'] ?? '',
            $data['summary'] ?? '',
            $data['category'] ?? 'ข่าวประชาสัมพันธ์',
            $data['thumbnail_url'] ?? '',
            $data['link_url'] ?? '',
            $data['published_date'] ?? date('Y-m-d'),
            $data['department'] ?? 'general',
            $data['tags'] ?? '',
            $data['is_active'] ?? 1,
            $id
        ]);
        
        $this->sendSuccess(['message' => 'News updated successfully']);
    }
    
    public function deleteNews($token, $id) {
        $this->verifyToken($token);
        
        // Soft delete
        $sql = "UPDATE news SET is_active = 0 WHERE id = ?";
        $this->db->prepare($sql)->execute([$id]);
        
        $this->sendSuccess(['message' => 'News deleted successfully']);
    }
    
    /**
     * Analytics
     */
    public function getStats($token) {
        $this->verifyToken($token);
        
        $stats = [];
        
        // FAQ count
        $sql = "SELECT COUNT(*) as total FROM faq WHERE is_active = 1";
        $stats['total_faqs'] = $this->db->query($sql)->fetch()['total'];
        
        // Staff count
        $sql = "SELECT COUNT(*) as total FROM staff WHERE is_active = 1";
        $stats['total_staff'] = $this->db->query($sql)->fetch()['total'];
        
        // News count
        $sql = "SELECT COUNT(*) as total FROM news WHERE is_active = 1";
        $stats['total_news'] = $this->db->query($sql)->fetch()['total'];
        
        // Chat logs count
        $sql = "SELECT COUNT(*) as total FROM chat_logs";
        $stats['total_chats'] = $this->db->query($sql)->fetch()['total'];
        
        // Today's chats
        $sql = "SELECT COUNT(*) as total FROM chat_logs WHERE DATE(created_at) = CURDATE()";
        $stats['today_chats'] = $this->db->query($sql)->fetch()['total'];
        
        // Avg confidence
        $sql = "SELECT AVG(confidence) as avg FROM chat_logs WHERE confidence > 0";
        $stats['avg_confidence'] = round($this->db->query($sql)->fetch()['avg'], 2);
        
        $this->sendSuccess($stats);
    }
    
    /**
     * Helper methods
     */
    private function sendSuccess($data) {
        echo json_encode([
            'success' => true,
            'data' => $data
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit();
    }
    
    private function sendError($message, $code = 400) {
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'error' => $message
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
}

// Main execution
try {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? $_GET['action'] ?? '';
    $token = $input['token'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    
    // Remove "Bearer " prefix if present
    $token = str_replace('Bearer ', '', $token);
    
    $api = new AdminAPI();
    
    switch ($action) {
        // FAQ operations
        case 'list_faqs':
            $api->listFAQs(
                $token,
                $input['search'] ?? '',
                $input['category'] ?? '',
                $input['page'] ?? 1,
                $input['limit'] ?? 20
            );
            break;
            
        case 'create_faq':
            $api->createFAQ($token, $input['data'] ?? []);
            break;
            
        case 'update_faq':
            $api->updateFAQ($token, $input['id'] ?? 0, $input['data'] ?? []);
            break;
            
        case 'delete_faq':
            $api->deleteFAQ($token, $input['id'] ?? 0);
            break;
        
        // Staff operations
        case 'list_staff':
            $api->listStaff(
                $token,
                $input['search'] ?? '',
                $input['department'] ?? '',
                $input['page'] ?? 1,
                $input['limit'] ?? 20
            );
            break;
            
        case 'update_staff':
            $api->updateStaff($token, $input['id'] ?? 0, $input['data'] ?? []);
            break;
        
        // News operations
        case 'list_news':
            $api->listNews(
                $token,
                $input['search'] ?? '',
                $input['category'] ?? '',
                $input['page'] ?? 1,
                $input['limit'] ?? 20
            );
            break;
            
        case 'create_news':
            $api->createNews($token, $input['data'] ?? []);
            break;
            
        case 'update_news':
            $api->updateNews($token, $input['id'] ?? 0, $input['data'] ?? []);
            break;
            
        case 'delete_news':
            $api->deleteNews($token, $input['id'] ?? 0);
            break;
        
        // Chat logs
        case 'list_chatlogs':
            $api->listChatLogs($token, $input['limit'] ?? 50, $input['offset'] ?? 0);
            break;
        
        // Analytics
        case 'get_stats':
            $api->getStats($token);
            break;
        
        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Invalid action',
                'available_actions' => [
                    'list_faqs', 'create_faq', 'update_faq', 'delete_faq',
                    'list_staff', 'update_staff',
                    'list_news', 'create_news', 'update_news', 'delete_news',
                    'list_chatlogs', 'get_stats'
                ]
            ], JSON_UNESCAPED_UNICODE);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
