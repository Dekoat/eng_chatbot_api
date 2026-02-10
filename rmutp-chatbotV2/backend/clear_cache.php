<?php
session_start();

// ล้าง session cache ทั้งหมด
$_SESSION = [];

// ทำลาย session
session_destroy();

// เริ่ม session ใหม่
session_start();

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'message' => 'Session cache cleared successfully'
], JSON_UNESCAPED_UNICODE);
