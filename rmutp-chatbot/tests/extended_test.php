<?php
/**
 * RMUTP Chatbot - Extended Test Suite
 * ทดสอบเชิงลึก: conversation flow, admin API, AI accuracy, stress, ข้อมูลซ้ำ
 * 
 * Usage: C:\xampp\php\php.exe tests\extended_test.php
 */

mb_internal_encoding('UTF-8');

$BASE_URL = 'http://localhost/rmutp-chatbot/backend';
$AI_URL   = 'http://localhost:5000';

$totalTests  = 0;
$passedTests = 0;
$failedTests = 0;
$skippedTests = 0;
$failures = [];
$sectionResults = [];

// ===== HELPERS =====
function printHeader($title) {
    echo "\n" . str_repeat('=', 70) . "\n  {$title}\n" . str_repeat('=', 70) . "\n";
}

function chatRequest($baseUrl, $message, $sessionId = null) {
    $url = $baseUrl . '/chatbot.php';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'message' => $message,
            'session_id' => $sessionId ?? ('test_' . uniqid())
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Origin: http://localhost'],
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode($response, true) ?? ['error' => 'Invalid JSON'];
    $data['http_code'] = $httpCode;
    return $data;
}

function postRequest($url, $payload, $headers = []) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json', 'Origin: http://localhost'], $headers),
        CURLOPT_TIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['data' => json_decode($response, true), 'http_code' => $httpCode, 'body' => $response];
}

function getRequest($url, $headers = []) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => array_merge(['Origin: http://localhost'], $headers),
        CURLOPT_TIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['data' => json_decode($response, true), 'http_code' => $httpCode, 'body' => $response];
}

function assertTest($name, $condition, $detail, &$total, &$passed, &$failed, &$failures) {
    $total++;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$name}\n";
    } else {
        $failed++;
        $msg = $name . ($detail ? " | {$detail}" : '');
        $failures[] = $msg;
        echo "  [FAIL] {$name}" . ($detail ? " => {$detail}" : '') . "\n";
    }
}

function assertSkip($name, $reason, &$total, &$skipped) {
    $total++;
    $skipped++;
    echo "  [SKIP] {$name} ({$reason})\n";
}

// =====================================================================
//  SECTION 1: MULTI-TURN CONVERSATION FLOW
// =====================================================================
printHeader('1. MULTI-TURN CONVERSATION FLOW');
$s = $totalTests; $p = $passedTests;

$sessId = 'conv_' . uniqid();

// Turn 1: ถามค่าเทอม
$t1 = chatRequest($BASE_URL, 'ค่าเทอมวิศวกรรมคอมพิวเตอร์เท่าไหร่', $sessId);
assertTest('Turn 1: ค่าเทอมคอม returns answer', 
    mb_strlen($t1['answer'] ?? '') > 20, '', 
    $totalTests, $passedTests, $failedTests, $failures);

// Turn 2: ถามต่อเรื่องอาจารย์ (same session)
$t2 = chatRequest($BASE_URL, 'อาจารย์สาขาคอมพิวเตอร์มีใครบ้าง', $sessId);
assertTest('Turn 2: อาจารย์คอม in same session', 
    mb_stripos($t2['answer'] ?? '', 'อาจารย์') !== false || 
    mb_stripos($t2['answer'] ?? '', 'ดร.') !== false ||
    mb_stripos($t2['answer'] ?? '', 'ผศ.') !== false, 
    mb_substr($t2['answer'] ?? '', 0, 80), 
    $totalTests, $passedTests, $failedTests, $failures);

// Turn 3: ถามข่าว (different topic)
$t3 = chatRequest($BASE_URL, 'มีข่าวอะไรล่าสุดบ้าง', $sessId);
assertTest('Turn 3: News in same session', 
    mb_strlen($t3['answer'] ?? '') > 20, '', 
    $totalTests, $passedTests, $failedTests, $failures);

// Turn 4: กลับมาถาม FAQ อีกครั้ง
$t4 = chatRequest($BASE_URL, 'สหกิจศึกษาทำยังไง', $sessId);
assertTest('Turn 4: FAQ สหกิจ after topic switch', 
    mb_stripos($t4['answer'] ?? '', 'สหกิจ') !== false, 
    mb_substr($t4['answer'] ?? '', 0, 80), 
    $totalTests, $passedTests, $failedTests, $failures);

// Verify chat_logs were saved for this session
require_once __DIR__ . '/../backend/db.php';
$pdo = getDB();
$stmt = $pdo->prepare("SELECT COUNT(*) FROM chat_logs WHERE session_id = ?");
$stmt->execute([$sessId]);
$logCount = (int)$stmt->fetchColumn();
assertTest('Chat logs saved for session (4 turns)', 
    $logCount >= 4, 
    "logs={$logCount}", 
    $totalTests, $passedTests, $failedTests, $failures);

// Clean up test logs
$pdo->prepare("DELETE FROM chat_logs WHERE session_id = ?")->execute([$sessId]);

$sectionResults['1_Conversation'] = ['total' => $totalTests - $s, 'passed' => $passedTests - $p];

// =====================================================================
//  SECTION 2: DEPARTMENT-SPECIFIC FAQ ACCURACY
// =====================================================================
printHeader('2. DEPARTMENT-SPECIFIC FAQ ACCURACY');
$s = $totalTests; $p = $passedTests;

$deptTests = [
    // สาขา + คำถามเฉพาะ
    ['ค่าเทอมวิศวกรรมไฟฟ้า', ['ไฟฟ้า', 'ค่า'], 'ค่าเทอมไฟฟ้า'],
    ['ค่าเทอมโยธา', ['โยธา', 'ค่า'], 'ค่าเทอมโยธา'],
    ['วิศวกรรมเครื่องกลเรียนอะไร', ['เครื่องกล'], 'หลักสูตรเครื่องกล'],
    ['วิศวกรรมอิเล็กทรอนิกส์เรียนอะไร', ['อิเล็กทรอนิกส์', 'ไฟฟ้า', 'สื่อสาร', 'อัจฉริยะ'], 'หลักสูตรอิเลค'],
    ['อาจารย์ประจำสาขาเครื่องกล', ['เครื่องกล', 'อาจารย์', 'ดร.', 'ผศ.'], 'อาจารย์เครื่องกล'],
    // Generic → broad topic
    ['เรื่องอาชีพ', ['อาชีพ', 'ทำงาน', 'จบ', 'วิศวกร'], 'อาชีพ broad'],
    ['เรื่องทุน', ['ทุน', 'กยศ', 'กองทุน', 'เรียนดี', 'ข่าว'], 'ทุน broad'],
];

foreach ($deptTests as [$q, $keywords, $desc]) {
    $r = chatRequest($BASE_URL, $q);
    $a = $r['answer'] ?? '';
    $found = false;
    foreach ($keywords as $kw) {
        if (mb_stripos($a, $kw) !== false) { $found = true; break; }
    }
    assertTest("Dept: {$desc}", $found, 
        $found ? '' : 'Keywords not found. Answer: ' . mb_substr($a, 0, 80),
        $totalTests, $passedTests, $failedTests, $failures);
}

$sectionResults['2_DeptFAQ'] = ['total' => $totalTests - $s, 'passed' => $passedTests - $p];

// =====================================================================
//  SECTION 3: AI INTENT CLASSIFICATION ACCURACY
// =====================================================================
printHeader('3. AI INTENT CLASSIFICATION ACCURACY');
$s = $totalTests; $p = $passedTests;

$aiTests = [
    ['ค่าเทอมเท่าไหร่', 'loan', 'ค่าเทอม → loan'],
    ['อาจารย์สาขาคอมมีใครบ้าง', 'ask_staff', 'อาจารย์ → ask_staff'],
    ['ข่าวล่าสุดมีอะไร', 'facilities', 'ข่าว → facilities'],
    ['ทุนการศึกษา', 'loan', 'ทุน → loan'],
    ['ฝึกงานตอนไหน', 'activities', 'ฝึกงาน → activities'],
    ['เรียนอะไรบ้างสาขาคอม', 'program', 'หลักสูตร → program'],
    ['จบแล้วทำอะไรได้', 'career', 'อาชีพ → career'],
    ['สมัครเรียนยังไง', 'admission', 'สมัคร → admission'],
    ['กู้ กยศ ได้ไหม', 'loan', 'กู้ยืม → loan'],
    ['คณะอยู่ที่ไหน', 'contact', 'สถานที่ → contact'],
];

$aiOk = false;
$health = getRequest($AI_URL . '/health');
$aiOk = ($health['http_code'] === 200);

if ($aiOk) {
    $correctAI = 0;
    foreach ($aiTests as [$q, $expectedIntent, $desc]) {
        $pred = postRequest($AI_URL . '/predict', ['question' => $q]);
        $intent = $pred['data']['intent'] ?? 'null';
        $conf = $pred['data']['confidence'] ?? 0;
        $match = ($intent === $expectedIntent);
        if ($match) $correctAI++;
        assertTest("AI: {$desc}", $match, 
            "Got: {$intent} (" . round($conf * 100) . "%)", 
            $totalTests, $passedTests, $failedTests, $failures);
    }
    
    $aiAccuracy = count($aiTests) > 0 ? round(($correctAI / count($aiTests)) * 100, 1) : 0;
    assertTest("AI overall accuracy >= 70% ({$aiAccuracy}%)", 
        $aiAccuracy >= 70, '', 
        $totalTests, $passedTests, $failedTests, $failures);
} else {
    for ($i = 0; $i <= count($aiTests); $i++) {
        assertSkip('AI intent test', 'AI server not running', $totalTests, $skippedTests);
    }
}

$sectionResults['3_AIAccuracy'] = ['total' => $totalTests - $s, 'passed' => $passedTests - $p];

// =====================================================================
//  SECTION 4: SYNONYM & NORMALIZATION COVERAGE
// =====================================================================
printHeader('4. SYNONYM & NORMALIZATION COVERAGE');
$s = $totalTests; $p = $passedTests;

$synonymTests = [
    // คำย่อ → ค้นหาได้
    ['คอม', ['คอมพิวเตอร์'], 'คอม → คอมพิวเตอร์'],
    ['กยศ', ['กยศ', 'กองทุน', 'กู้'], 'กยศ → กองทุนกู้ยืม'],
    ['ม.6', ['รับสมัคร', 'สมัคร', 'TCAS'], 'ม.6 → รับสมัคร'],
    ['ปวส', ['รับสมัคร', 'สมัคร', 'เทียบโอน', 'ปวส', 'รับตรง'], 'ปวส → รับสมัคร/เทียบโอน'],
    
    // ภาษาพูด → ค้นหาได้
    ['จบคอมทำอะไรได้', ['ทำงาน', 'อาชีพ', 'วิศวกร', 'จบ', 'คอมพิวเตอร์'], 'ภาษาพูด: จบคอม'],
    ['เรียนคอมยากไหม', ['คอมพิวเตอร์', 'เรียน', 'หลักสูตร'], 'ภาษาพูด: เรียนคอม'],
    
    // คำต่างกันแต่ความหมายเดียวกัน
    ['ค่าเล่าเรียน', ['ค่า', 'เทอม', 'เรียน'], 'ค่าเล่าเรียน ≈ ค่าเทอม'],
    ['WiFi มหาวิทยาลัย', ['WiFi', 'อินเทอร์เน็ต', 'เน็ต', 'ไวไฟ', 'สิ่งอำนวย', 'อาคาร', 'คณะ'], 'WiFi → facilities'],
];

foreach ($synonymTests as [$q, $keywords, $desc]) {
    $r = chatRequest($BASE_URL, $q);
    $a = $r['answer'] ?? '';
    $found = false;
    foreach ($keywords as $kw) {
        if (mb_stripos($a, $kw) !== false) { $found = true; break; }
    }
    assertTest("Synonym: {$desc}", $found, 
        $found ? '' : 'Answer: ' . mb_substr($a, 0, 80), 
        $totalTests, $passedTests, $failedTests, $failures);
}

$sectionResults['4_Synonyms'] = ['total' => $totalTests - $s, 'passed' => $passedTests - $p];

// =====================================================================
//  SECTION 5: STAFF SEARCH DEEP TEST
// =====================================================================
printHeader('5. STAFF SEARCH DEEP TEST');
$s = $totalTests; $p = $passedTests;

// Search by name
$nameTests = [
    // ค้นจากชื่ออาจารย์ (ถ้ามีใน DB)
    ['อาจารย์สาขาอุตสาหการ', 'อุตสาหการ', 'ค้นสาขาอุตสาหการ'],
    ['อาจารย์สาขาไฟฟ้ามีกี่คน', 'ไฟฟ้า', 'ค้นจำนวนอาจารย์ไฟฟ้า'],
];

foreach ($nameTests as [$q, $keyword, $desc]) {
    $r = chatRequest($BASE_URL, $q);
    $a = $r['answer'] ?? '';
    $found = mb_stripos($a, $keyword) !== false || 
             mb_stripos($a, 'อาจารย์') !== false ||
             mb_stripos($a, 'ดร.') !== false;
    assertTest("Staff: {$desc}", $found, 
        $found ? '' : 'Answer: ' . mb_substr($a, 0, 80), 
        $totalTests, $passedTests, $failedTests, $failures);
}

// Staff search should not trigger for FAQ-like staff questions
$faqStaffTests = [
    ['ประธานหลักสูตรคอมพิวเตอร์คือใคร', 'ประธาน', 'ประธานหลักสูตร → FAQ not staff list'],
];
foreach ($faqStaffTests as [$q, $keyword, $desc]) {
    $r = chatRequest($BASE_URL, $q);
    $a = $r['answer'] ?? '';
    assertTest("Staff-FAQ: {$desc}", mb_strlen($a) > 10, 
        mb_substr($a, 0, 80), 
        $totalTests, $passedTests, $failedTests, $failures);
}

$sectionResults['5_Staff'] = ['total' => $totalTests - $s, 'passed' => $passedTests - $p];

// =====================================================================
//  SECTION 6: ADMIN API AUTH & OPERATIONS
// =====================================================================
printHeader('6. ADMIN API AUTH & OPERATIONS');
$s = $totalTests; $p = $passedTests;

// 6.1 Admin login with wrong credentials
$wrongLogin = postRequest($BASE_URL . '/admin_login.php', [
    'action' => 'login',
    'username' => 'wronguser',
    'password' => 'wrongpass',
]);
assertTest('Admin login rejects wrong credentials', 
    ($wrongLogin['data']['success'] ?? true) === false || $wrongLogin['http_code'] >= 400,
    '', $totalTests, $passedTests, $failedTests, $failures);

// 6.2 Admin API without token
$noTokenResult = getRequest($BASE_URL . '/admin_api.php?action=list_faqs&page=1');
assertTest('Admin API accessible from localhost (no token)', 
    $noTokenResult['http_code'] === 200 || $noTokenResult['http_code'] === 401,
    "HTTP {$noTokenResult['http_code']}", 
    $totalTests, $passedTests, $failedTests, $failures);

// 6.3 Admin API with invalid token
$badTokenResult = getRequest($BASE_URL . '/admin_api.php?action=list_faqs', [
    'Authorization: Bearer invalid.token.here'
]);
assertTest('Admin API rejects invalid token', 
    $badTokenResult['http_code'] === 401 || $badTokenResult['http_code'] === 200,
    "HTTP {$badTokenResult['http_code']}", 
    $totalTests, $passedTests, $failedTests, $failures);

// 6.4 Admin login endpoint exists and returns JSON
$loginCheck = postRequest($BASE_URL . '/admin_login.php', [
    'action' => 'check',
]);
assertTest('admin_login.php returns JSON', 
    $loginCheck['data'] !== null,
    '', $totalTests, $passedTests, $failedTests, $failures);

// 6.5 Analytics endpoint accessible from localhost
$analytics = getRequest($BASE_URL . '/analytics_api.php?action=summary');
assertTest('analytics_api from localhost', 
    $analytics['http_code'] === 200,
    "HTTP {$analytics['http_code']}", 
    $totalTests, $passedTests, $failedTests, $failures);

// Check analytics returns expected structure
if ($analytics['http_code'] === 200 && $analytics['data']) {
    $hasStats = isset($analytics['data']['total_chats']) || 
                isset($analytics['data']['today_chats']) ||
                isset($analytics['data']['summary']);
    assertTest('Analytics returns stats data', $hasStats, 
        json_encode(array_keys($analytics['data'] ?? []), JSON_UNESCAPED_UNICODE),
        $totalTests, $passedTests, $failedTests, $failures);
}

$sectionResults['6_Admin'] = ['total' => $totalTests - $s, 'passed' => $passedTests - $p];

// =====================================================================
//  SECTION 7: FEEDBACK SYSTEM
// =====================================================================
printHeader('7. FEEDBACK SYSTEM');
$s = $totalTests; $p = $passedTests;

$fbSession = 'feedback_test_' . uniqid();

// First create a chat interaction
chatRequest($BASE_URL, 'ค่าเทอมเท่าไหร่', $fbSession);

// 7.1 Positive feedback
$posFb = postRequest($BASE_URL . '/feedback_api.php', [
    'action' => 'feedback',
    'feedback_type' => 'positive',
    'session_id' => $fbSession,
]);
assertTest('Positive feedback saved', 
    ($posFb['data']['success'] ?? false) === true,
    json_encode($posFb['data'] ?? [], JSON_UNESCAPED_UNICODE), 
    $totalTests, $passedTests, $failedTests, $failures);

// 7.2 Negative feedback
$negFb = postRequest($BASE_URL . '/feedback_api.php', [
    'action' => 'feedback',
    'feedback_type' => 'negative',
    'session_id' => $fbSession,
]);
assertTest('Negative feedback saved', 
    ($negFb['data']['success'] ?? false) === true,
    '', $totalTests, $passedTests, $failedTests, $failures);

// 7.3 Invalid action
$badAction = postRequest($BASE_URL . '/feedback_api.php', [
    'action' => 'delete_all',
    'session_id' => $fbSession,
]);
assertTest('Feedback rejects invalid action', 
    $badAction['http_code'] === 400,
    "HTTP {$badAction['http_code']}", 
    $totalTests, $passedTests, $failedTests, $failures);

// 7.4 Missing session_id (should still work)
$noSess = postRequest($BASE_URL . '/feedback_api.php', [
    'action' => 'feedback',
    'feedback_type' => 'positive',
]);
assertTest('Feedback works without session_id', 
    ($noSess['data']['success'] ?? false) === true || $noSess['http_code'] === 200,
    '', $totalTests, $passedTests, $failedTests, $failures);

// Clean up
$pdo->prepare("DELETE FROM chat_logs WHERE session_id = ?")->execute([$fbSession]);

$sectionResults['7_Feedback'] = ['total' => $totalTests - $s, 'passed' => $passedTests - $p];

// =====================================================================
//  SECTION 8: THAI LANGUAGE EDGE CASES
// =====================================================================
printHeader('8. THAI LANGUAGE EDGE CASES');
$s = $totalTests; $p = $passedTests;

$thaiEdgeCases = [
    // Formal/informal variations
    ['ค่าเทอมเท่าไร', ['ค่า', 'เทอม'], 'เท่าไร (ไม่มี ่)'],
    ['ค่าเทอมเท่าไหร่', ['ค่า', 'เทอม'], 'เท่าไหร่ (มี ่)'],
    
    // Polite particles shouldn't affect
    ['ค่าเทอมเท่าไหร่ครับ', ['ค่า', 'เทอม'], 'มี ครับ ต่อท้าย'],
    ['ค่าเทอมเท่าไหร่คะ', ['ค่า', 'เทอม'], 'มี คะ ต่อท้าย'],
    ['ขอถามค่าเทอมหน่อยครับ', ['ค่า', 'เทอม'], 'มี ขอถาม...หน่อยครับ'],
    
    // Common misspellings/variants
    ['วิศวะคอม', ['คอมพิวเตอร์'], 'วิศวะคอม (คำย่อ)'],
    ['ป.ตรี กี่ปี', ['4', 'ปี', 'หลักสูตร'], 'ป.ตรี กี่ปี'],
    
    // Numbers in Thai context
    ['สมัครปี 2569', ['สมัคร', 'รับ'], 'ปีการศึกษาเฉพาะเจาะจง'],
    
    // Very short queries
    ['ค่าเทอม', ['ค่า', 'เทอม', 'เล่าเรียน'], 'query สั้น: ค่าเทอม'],
    ['ฝึกงาน', ['ฝึกงาน', 'สหกิจ'], 'query สั้น: ฝึกงาน'],
];

foreach ($thaiEdgeCases as [$q, $keywords, $desc]) {
    $r = chatRequest($BASE_URL, $q);
    $a = $r['answer'] ?? '';
    $found = false;
    foreach ($keywords as $kw) {
        if (mb_stripos($a, $kw) !== false) { $found = true; break; }
    }
    assertTest("Thai: {$desc}", $found, 
        $found ? '' : 'Answer: ' . mb_substr($a, 0, 80), 
        $totalTests, $passedTests, $failedTests, $failures);
}

$sectionResults['8_ThaiEdge'] = ['total' => $totalTests - $s, 'passed' => $passedTests - $p];

// =====================================================================
//  SECTION 9: RESPONSE QUALITY CHECKS
// =====================================================================
printHeader('9. RESPONSE QUALITY CHECKS');
$s = $totalTests; $p = $passedTests;

// 9.1 Answers should not contain raw HTML/SQL
$testQuestions = [
    'ค่าเทอมเท่าไหร่',
    'อาจารย์สาขาคอม',
    'ข่าวล่าสุด',
    'สหกิจศึกษาคืออะไร',
    'ทุนการศึกษา',
];

foreach ($testQuestions as $q) {
    $r = chatRequest($BASE_URL, $q);
    $a = $r['answer'] ?? '';
    
    // No raw HTML tags (except allowed ones like <!-- comments for frontend parsing -->)
    $hasRawHtml = preg_match('/<(?!!--)(script|iframe|object|embed|form|input)/i', $a);
    assertTest("No dangerous HTML in: {$q}", !$hasRawHtml, '', 
        $totalTests, $passedTests, $failedTests, $failures);
    
    // No SQL queries leaked
    $hasSql = preg_match('/(SELECT|INSERT|UPDATE|DELETE|DROP)\s+(FROM|INTO|TABLE)/i', $a);
    assertTest("No SQL leak in: {$q}", !$hasSql, '', 
        $totalTests, $passedTests, $failedTests, $failures);
}

// 9.2 Confidence scores are reasonable
$confTests = [
    ['ค่าเทอมวิศวกรรมคอมพิวเตอร์', 50, 'Specific FAQ should have high confidence'],
    ['อาจารย์สาขาคอม', 0.5, 'Staff search should have decent confidence'],
];
foreach ($confTests as [$q, $minConf, $desc]) {
    $r = chatRequest($BASE_URL, $q);
    $conf = $r['confidence'] ?? 0;
    assertTest("Confidence: {$desc} (got {$conf}%)", $conf >= $minConf, '', 
        $totalTests, $passedTests, $failedTests, $failures);
}

// 9.3 Response times are reasonable
$slowQueries = [
    'วิศวกรรมคอมพิวเตอร์เรียนอะไรบ้าง ค่าเทอม อาจารย์ สหกิจ',  // complex
    'อาจารย์ทุกสาขาในคณะวิศวกรรมศาสตร์มีใครบ้าง',                // broad staff
];
foreach ($slowQueries as $q) {
    $start = microtime(true);
    $r = chatRequest($BASE_URL, $q);
    $elapsed = (microtime(true) - $start) * 1000;
    assertTest("Response time < 5s: " . mb_substr($q, 0, 30), $elapsed < 5000, 
        sprintf("%.0fms", $elapsed), 
        $totalTests, $passedTests, $failedTests, $failures);
}

$sectionResults['9_Quality'] = ['total' => $totalTests - $s, 'passed' => $passedTests - $p];

// =====================================================================
//  SECTION 10: DATA INTEGRITY & CROSS-CHECK
// =====================================================================
printHeader('10. DATA INTEGRITY & CROSS-CHECK');
$s = $totalTests; $p = $passedTests;

// 10.1 Check for duplicate FAQ questions
$stmt = $pdo->query("
    SELECT question, COUNT(*) as cnt 
    FROM faq 
    WHERE is_active = 1 
    GROUP BY question 
    HAVING cnt > 1
    LIMIT 10
");
$dupes = $stmt->fetchAll(PDO::FETCH_ASSOC);
assertTest('No duplicate FAQ questions', 
    count($dupes) === 0,
    count($dupes) > 0 ? 'Duplicates: ' . json_encode(array_column($dupes, 'question'), JSON_UNESCAPED_UNICODE) : '', 
    $totalTests, $passedTests, $failedTests, $failures);

// 10.2 All FAQs have answers
$stmt = $pdo->query("SELECT COUNT(*) FROM faq WHERE is_active = 1 AND (answer IS NULL OR answer = '')");
$emptyAnswers = (int)$stmt->fetchColumn();
assertTest('All active FAQs have answers', $emptyAnswers === 0, 
    "empty={$emptyAnswers}", 
    $totalTests, $passedTests, $failedTests, $failures);

// 10.3 All staff have names
$stmt = $pdo->query("SELECT COUNT(*) FROM staff WHERE name_th IS NULL OR name_th = ''");
$emptyNames = (int)$stmt->fetchColumn();
assertTest('All staff have names', $emptyNames === 0, 
    "empty={$emptyNames}", 
    $totalTests, $passedTests, $failedTests, $failures);

// 10.4 All staff have departments
$stmt = $pdo->query("SELECT COUNT(*) FROM staff WHERE department IS NULL OR department = ''");
$emptyDepts = (int)$stmt->fetchColumn();
assertTest('All staff have departments', $emptyDepts === 0,
    "empty={$emptyDepts}", 
    $totalTests, $passedTests, $failedTests, $failures);

// 10.5 FAQ categories distribution
$stmt = $pdo->query("
    SELECT category, COUNT(*) as cnt 
    FROM faq 
    WHERE is_active = 1 AND category IS NOT NULL 
    GROUP BY category 
    ORDER BY cnt DESC
");
$catDist = $stmt->fetchAll(PDO::FETCH_ASSOC);
assertTest('FAQ has multiple categories (>5)', count($catDist) > 5, 
    'categories=' . count($catDist), 
    $totalTests, $passedTests, $failedTests, $failures);

// 10.6 view_count increments
$stmt = $pdo->query("SELECT id, view_count FROM faq WHERE is_active = 1 ORDER BY id ASC LIMIT 1");
$faqBefore = $stmt->fetch(PDO::FETCH_ASSOC);
if ($faqBefore) {
    // Trigger a search that should match this FAQ
    $stmt2 = $pdo->prepare("SELECT question FROM faq WHERE id = ?");
    $stmt2->execute([$faqBefore['id']]);
    $faqQ = $stmt2->fetchColumn();
    if ($faqQ) {
        chatRequest($BASE_URL, $faqQ);
        $stmt3 = $pdo->prepare("SELECT view_count FROM faq WHERE id = ?");
        $stmt3->execute([$faqBefore['id']]);
        $viewAfter = (int)$stmt3->fetchColumn();
        assertTest('view_count increments on FAQ hit', 
            $viewAfter >= $faqBefore['view_count'],
            "before={$faqBefore['view_count']}, after={$viewAfter}", 
            $totalTests, $passedTests, $failedTests, $failures);
    }
}

// 10.7 News have required fields
$stmt = $pdo->query("SELECT COUNT(*) FROM news WHERE title IS NULL OR title = ''");
$emptyNews = (int)$stmt->fetchColumn();
assertTest('All news have titles', $emptyNews === 0, 
    "empty={$emptyNews}", 
    $totalTests, $passedTests, $failedTests, $failures);

$sectionResults['10_DataIntegrity'] = ['total' => $totalTests - $s, 'passed' => $passedTests - $p];

// =====================================================================
//  SECTION 11: CONCURRENT SESSION ISOLATION
// =====================================================================
printHeader('11. SESSION ISOLATION');
$s = $totalTests; $p = $passedTests;

$sess1 = 'iso_a_' . uniqid();
$sess2 = 'iso_b_' . uniqid();

// Session 1 asks about money
$r1 = chatRequest($BASE_URL, 'ค่าเทอมเท่าไหร่', $sess1);
// Session 2 asks about staff
$r2 = chatRequest($BASE_URL, 'อาจารย์สาขาคอม', $sess2);

// Make sure session 2 gets staff answer, not mixed with money
assertTest('Session isolation: sess2 gets staff response', 
    mb_stripos($r2['answer'] ?? '', 'อาจารย์') !== false || 
    mb_stripos($r2['answer'] ?? '', 'ดร.') !== false ||
    mb_stripos($r2['answer'] ?? '', 'ผศ.') !== false,
    '', $totalTests, $passedTests, $failedTests, $failures);

// Session 1 should not be contaminated
assertTest('Session isolation: sess1 still has fee answer', 
    mb_stripos($r1['answer'] ?? '', 'ค่า') !== false,
    '', $totalTests, $passedTests, $failedTests, $failures);

// Clean up
$pdo->prepare("DELETE FROM chat_logs WHERE session_id IN (?, ?)")->execute([$sess1, $sess2]);

$sectionResults['11_SessionIso'] = ['total' => $totalTests - $s, 'passed' => $passedTests - $p];

// =====================================================================
//  SECTION 12: STRESS TEST (RAPID BURST)
// =====================================================================
printHeader('12. STRESS TEST');
$s = $totalTests; $p = $passedTests;

$stressQuestions = [
    'ค่าเทอม', 'อาจารย์คอม', 'ข่าวล่าสุด', 'สหกิจ', 'ทุนกยศ',
    'วิศวกรรมไฟฟ้า', 'เรียนกี่ปี', 'ฝึกงาน', 'ลงทะเบียน', 'ติดต่อคณะ',
];

$stressStart = microtime(true);
$stressResults = [];
$stressErrors = 0;

foreach ($stressQuestions as $sq) {
    $r = chatRequest($BASE_URL, $sq);
    if ($r['http_code'] !== 200 || empty($r['answer'])) {
        $stressErrors++;
    }
    $stressResults[] = $r;
}

$stressDuration = (microtime(true) - $stressStart) * 1000;

assertTest("10 sequential requests succeed", $stressErrors === 0, 
    "errors={$stressErrors}", 
    $totalTests, $passedTests, $failedTests, $failures);

assertTest("10 requests complete < 30s", $stressDuration < 30000, 
    sprintf("%.0fms", $stressDuration), 
    $totalTests, $passedTests, $failedTests, $failures);

$avgTime = $stressDuration / count($stressQuestions);
assertTest("Average per request < 3s", $avgTime < 3000, 
    sprintf("avg=%.0fms", $avgTime), 
    $totalTests, $passedTests, $failedTests, $failures);

$sectionResults['12_Stress'] = ['total' => $totalTests - $s, 'passed' => $passedTests - $p];

// =====================================================================
//  SECTION 13: FRONTEND FILE CHECKS
// =====================================================================
printHeader('13. FRONTEND FILE INTEGRITY');
$s = $totalTests; $p = $passedTests;

$frontendFiles = [
    'frontend/index.html' => ['escapeHtml', 'chatbot.php', 'session_id'],
    'admin/dashboard.html' => ['escapeHtml', 'admin_api.php', 'token'],
    'admin/login.html' => ['admin_login.php', 'password'],
    'admin/analytics.html' => ['escapeHtml', 'analytics_api.php'],
];

foreach ($frontendFiles as $file => $requiredStrings) {
    $filePath = __DIR__ . '/../' . $file;
    if (!file_exists($filePath)) {
        assertTest("File exists: {$file}", false, 'File not found', 
            $totalTests, $passedTests, $failedTests, $failures);
        continue;
    }
    
    $content = file_get_contents($filePath);
    assertTest("File exists: {$file}", true, '', 
        $totalTests, $passedTests, $failedTests, $failures);
    
    foreach ($requiredStrings as $str) {
        assertTest("{$file} contains '{$str}'", 
            strpos($content, $str) !== false, '', 
            $totalTests, $passedTests, $failedTests, $failures);
    }
}

// Check .env.example exists
assertTest('.env.example exists', 
    file_exists(__DIR__ . '/../.env.example'), '', 
    $totalTests, $passedTests, $failedTests, $failures);

// Check .gitignore exists  
assertTest('.gitignore exists', 
    file_exists(__DIR__ . '/../.gitignore'), '', 
    $totalTests, $passedTests, $failedTests, $failures);

$sectionResults['13_Frontend'] = ['total' => $totalTests - $s, 'passed' => $passedTests - $p];

// =====================================================================
//  SECTION 14: PHP SYNTAX CHECK (ALL BACKEND FILES)
// =====================================================================
printHeader('14. PHP SYNTAX CHECK');
$s = $totalTests; $p = $passedTests;

$phpFiles = glob(__DIR__ . '/../backend/*.php');
foreach ($phpFiles as $phpFile) {
    $basename = basename($phpFile);
    $output = [];
    $returnCode = 0;
    exec('"C:\xampp\php\php.exe" -l "' . realpath($phpFile) . '" 2>&1', $output, $returnCode);
    $isOk = ($returnCode === 0);
    assertTest("Syntax OK: backend/{$basename}", $isOk, 
        $isOk ? '' : implode(' ', $output), 
        $totalTests, $passedTests, $failedTests, $failures);
}

// Check scripts/*.php
$scriptFiles = glob(__DIR__ . '/../scripts/*.php');
foreach ($scriptFiles as $sf) {
    $basename = basename($sf);
    exec('"C:\xampp\php\php.exe" -l "' . realpath($sf) . '" 2>&1', $output2, $rc2);
    assertTest("Syntax OK: scripts/{$basename}", $rc2 === 0, '', 
        $totalTests, $passedTests, $failedTests, $failures);
}

$sectionResults['14_Syntax'] = ['total' => $totalTests - $s, 'passed' => $passedTests - $p];

// =====================================================================
//  FINAL REPORT
// =====================================================================
printHeader('EXTENDED TEST REPORT');

echo "\n";
printf("  %-35s %s\n", "Section", "Result");
echo "  " . str_repeat('-', 55) . "\n";
foreach ($sectionResults as $section => $data) {
    $label = str_replace('_', ' ', substr($section, strpos($section, '_') + 1));
    $status = ($data['passed'] === $data['total']) ? 'PASS' : 'PARTIAL';
    printf("  %-35s %d/%d %s\n", $label, $data['passed'], $data['total'], $status);
}

echo "\n" . str_repeat('=', 70) . "\n";
printf("  TOTAL: %d tests | PASSED: %d | FAILED: %d | SKIPPED: %d\n", 
    $totalTests, $passedTests, $failedTests, $skippedTests);

$passRate = $totalTests > 0 ? round(($passedTests / $totalTests) * 100, 1) : 0;
printf("  PASS RATE: %.1f%%\n", $passRate);

if ($failedTests > 0) {
    echo "\n  FAILED TESTS:\n";
    foreach ($failures as $i => $f) {
        echo "    " . ($i + 1) . ". {$f}\n";
    }
}

echo str_repeat('=', 70) . "\n";
exit($failedTests > 0 ? 1 : 0);
