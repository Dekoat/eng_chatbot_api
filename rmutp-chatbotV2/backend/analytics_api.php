<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once 'db.php';

// Get database connection
$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = $_REQUEST['action'] ?? '';

try {
    switch ($action) {
        case 'feedback_stats':
            // Feedback statistics (👍/👎 ratio)
            $stmt = $pdo->query("
                SELECT 
                    feedback_type,
                    COUNT(*) as count,
                    ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM feedback), 2) as percentage
                FROM feedback
                GROUP BY feedback_type
            ");
            $feedback_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $total = $pdo->query("SELECT COUNT(*) as total FROM feedback")->fetch()['total'];
            
            echo json_encode([
                'success' => true,
                'total' => $total,
                'stats' => $feedback_stats
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'top_questions':
            // Top 10 most asked questions
            $stmt = $pdo->query("
                SELECT 
                    user_message,
                    COUNT(*) as ask_count,
                    AVG(confidence) as avg_confidence,
                    MAX(created_at) as last_asked
                FROM chat_logs
                WHERE user_message IS NOT NULL 
                    AND user_message != ''
                    AND LENGTH(user_message) > 5
                GROUP BY user_message
                ORDER BY ask_count DESC
                LIMIT 10
            ");
            $top_questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'questions' => $top_questions
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'low_confidence':
            // Questions with low confidence (<35%)
            $stmt = $pdo->query("
                SELECT 
                    user_message,
                    confidence as confidence_score,
                    bot_response,
                    created_at,
                    session_id
                FROM chat_logs
                WHERE confidence < 3.5
                    AND user_message IS NOT NULL
                    AND user_message != ''
                ORDER BY created_at DESC
                LIMIT 20
            ");
            $low_confidence = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'queries' => $low_confidence
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'response_time':
            // Average response time (if tracked)
            $stmt = $pdo->query("
                SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as total_queries,
                    AVG(confidence) as avg_confidence
                FROM chat_logs
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY DATE(created_at)
                ORDER BY date DESC
            ");
            $daily_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'daily_stats' => $daily_stats
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'faq_performance':
            // FAQ usage statistics with feedback
            $stmt = $pdo->query("
                SELECT 
                    f.id as faq_id,
                    f.question,
                    f.category,
                    COUNT(DISTINCT cl.id) as usage_count,
                    AVG(cl.confidence) as avg_confidence,
                    SUM(CASE WHEN fb.feedback_type = 'positive' THEN 1 ELSE 0 END) as positive_feedback,
                    SUM(CASE WHEN fb.feedback_type = 'negative' THEN 1 ELSE 0 END) as negative_feedback,
                    MAX(cl.created_at) as last_used
                FROM faq f
                LEFT JOIN chat_logs cl ON LOWER(cl.bot_response) LIKE CONCAT('%', LOWER(SUBSTRING(f.answer, 1, 50)), '%')
                LEFT JOIN feedback fb ON fb.chat_log_id = cl.id
                GROUP BY f.id, f.question, f.category
                HAVING usage_count > 0
                ORDER BY usage_count DESC
                LIMIT 20
            ");
            $faq_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'faqs' => $faq_performance
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'summary':
            // Overall system summary
            $total_chats = $pdo->query("SELECT COUNT(*) as count FROM chat_logs")->fetch()['count'];
            $total_feedback = $pdo->query("SELECT COUNT(*) as count FROM feedback")->fetch()['count'];
            $avg_confidence = $pdo->query("SELECT AVG(confidence) * 10 as avg FROM chat_logs")->fetch()['avg'];
            $positive_feedback = $pdo->query("SELECT COUNT(*) as count FROM feedback WHERE feedback_type = 'positive'")->fetch()['count'];
            $total_faqs = $pdo->query("SELECT COUNT(*) as count FROM faq")->fetch()['count'];
            
            $satisfaction_rate = $total_feedback > 0 ? round(($positive_feedback / $total_feedback) * 100, 2) : 0;
            
            echo json_encode([
                'success' => true,
                'summary' => [
                    'total_chats' => $total_chats,
                    'total_feedback' => $total_feedback,
                    'avg_confidence' => round($avg_confidence, 2),
                    'satisfaction_rate' => $satisfaction_rate,
                    'total_faqs' => $total_faqs,
                    'positive_feedback' => $positive_feedback,
                    'negative_feedback' => $total_feedback - $positive_feedback
                ]
            ], JSON_UNESCAPED_UNICODE);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action'], JSON_UNESCAPED_UNICODE);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
