<?php
// feedback_api.php - Simple API for handling feedback
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/security.php';
require_once __DIR__ . '/db.php';

// Set CORS headers (allowlist)
SecurityHelper::setCORSHeaders();

// Rate limiting (30 req/min per IP)
$clientIP = SecurityHelper::getClientIP();
if (!SecurityHelper::isWhitelistedIP($clientIP)) {
    if (!SecurityHelper::checkRateLimit($clientIP, 30, 60)) {
        SecurityHelper::rateLimitExceeded();
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Only POST method is allowed']);
    exit;
}

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!isset($data['action']) || $data['action'] !== 'feedback') {
        throw new Exception('Invalid action');
    }
    
    $feedbackType = $data['feedback_type'] ?? null;
    $sessionId = $data['session_id'] ?? null;
    
    if (!in_array($feedbackType, ['positive', 'negative'])) {
        throw new Exception('Invalid feedback type');
    }
    
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    // Get the latest chat log for this session
    $stmt = $pdo->prepare("
        SELECT id FROM chat_logs 
        WHERE session_id = ? 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$sessionId]);
    $chatLog = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$chatLog) {
        // If no chat log found, still save feedback with null chat_log_id
        $chatLogId = null;
    } else {
        $chatLogId = $chatLog['id'];
    }
    
    // Insert feedback
    $stmt = $pdo->prepare("
        INSERT INTO feedback (chat_log_id, feedback_type, created_at) 
        VALUES (?, ?, NOW())
    ");
    $stmt->execute([$chatLogId, $feedbackType]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Feedback saved',
        'feedback_id' => $pdo->lastInsertId()
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
?>
