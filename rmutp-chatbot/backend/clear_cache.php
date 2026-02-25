<?php
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');
SecurityHelper::setCORSHeaders();

// อนุญาตเฉพาะ localhost เท่านั้น
$clientIP = SecurityHelper::getClientIP();
if (!SecurityHelper::isWhitelistedIP($clientIP)) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied'], JSON_UNESCAPED_UNICODE);
    exit;
}

session_start();

// ล้าง session cache ทั้งหมด
$_SESSION = [];

// ทำลาย session
session_destroy();

// เริ่ม session ใหม่
session_start();

echo json_encode([
    'success' => true,
    'message' => 'Session cache cleared successfully'
], JSON_UNESCAPED_UNICODE);
