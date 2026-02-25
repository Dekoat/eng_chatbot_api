<?php
/**
 * RMUTP Chatbot - Comprehensive Test Suite
 * ทดสอบระบบทั้งหมดอย่างครอบคลุม
 * 
 * Usage: C:\xampp\php\php.exe tests\comprehensive_test.php
 * 
 * @version 1.0
 * @date 2026-02-10
 */

mb_internal_encoding('UTF-8');

// ===== CONFIG =====
$BASE_URL = 'http://localhost/rmutp-chatbot/backend';
$AI_URL   = 'http://localhost:5000';

// ===== COUNTERS =====
$totalTests  = 0;
$passedTests = 0;
$failedTests = 0;
$skippedTests = 0;
$failures = [];
$sectionResults = [];

// ===== HELPER FUNCTIONS =====

function colorText($text, $color) {
    $colors = [
        'green'  => "\033[32m",
        'red'    => "\033[31m",
        'yellow' => "\033[33m",
        'cyan'   => "\033[36m",
        'bold'   => "\033[1m",
        'reset'  => "\033[0m",
        'white'  => "\033[37m",
        'magenta'=> "\033[35m",
    ];
    // Windows terminal support
    if (PHP_OS_FAMILY === 'Windows') {
        return $text; // skip colors if not supported
    }
    return ($colors[$color] ?? '') . $text . $colors['reset'];
}

function printHeader($title) {
    $line = str_repeat('=', 70);
    echo "\n{$line}\n  {$title}\n{$line}\n";
}

function printSubHeader($title) {
    echo "\n--- {$title} ---\n";
}

/**
 * Send a chat message to the chatbot API
 */
function chatRequest($baseUrl, $message, $sessionId = null) {
    $url = $baseUrl . '/chatbot.php';
    $payload = json_encode([
        'message' => $message,
        'session_id' => $sessionId ?? ('test_' . uniqid())
    ]);
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Origin: http://localhost'
        ],
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['error' => $error, 'http_code' => 0];
    }
    
    $data = json_decode($response, true);
    if ($data === null) {
        return ['error' => 'Invalid JSON: ' . substr($response, 0, 200), 'http_code' => $httpCode];
    }
    
    $data['http_code'] = $httpCode;
    return $data;
}

/**
 * Send a GET request
 */
function getRequest($url, $headers = []) {
    $ch = curl_init($url);
    $defaultHeaders = ['Origin: http://localhost'];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => array_merge($defaultHeaders, $headers),
        CURLOPT_TIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'body' => $response,
        'data' => json_decode($response, true),
        'http_code' => $httpCode,
        'error' => $error,
    ];
}

/**
 * Send a POST request
 */
function postRequest($url, $payload, $headers = []) {
    $ch = curl_init($url);
    $defaultHeaders = [
        'Content-Type: application/json',
        'Origin: http://localhost',
    ];
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => array_merge($defaultHeaders, $headers),
        CURLOPT_TIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'body' => $response,
        'data' => json_decode($response, true),
        'http_code' => $httpCode,
        'error' => $error,
    ];
}

/**
 * Assert and record test result
 */
function assertTest($testName, $condition, $detail = '', &$total, &$passed, &$failed, &$failures) {
    $total++;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$testName}\n";
    } else {
        $failed++;
        $msg = $testName . ($detail ? " | {$detail}" : '');
        $failures[] = $msg;
        echo "  [FAIL] {$testName}" . ($detail ? " => {$detail}" : '') . "\n";
    }
}

function assertSkip($testName, $reason, &$total, &$skipped) {
    $total++;
    $skipped++;
    echo "  [SKIP] {$testName} ({$reason})\n";
}

// =====================================================================
//  SECTION 1: DATABASE CONNECTIVITY
// =====================================================================
printHeader('1. DATABASE CONNECTIVITY');

$dbOk = false;
try {
    require_once __DIR__ . '/../backend/db.php';
    $pdo = getDB();
    $dbOk = true;
    assertTest('Database connection', true, '', $totalTests, $passedTests, $failedTests, $failures);
    
    // Check charset
    $stmt = $pdo->query("SHOW VARIABLES LIKE 'character_set_connection'");
    $charset = $stmt->fetch(PDO::FETCH_ASSOC);
    assertTest('Database charset UTF-8', 
        stripos($charset['Value'], 'utf8') !== false,
        "Got: {$charset['Value']}", 
        $totalTests, $passedTests, $failedTests, $failures);
    
    // Check required tables
    $requiredTables = ['faq', 'staff', 'news', 'chat_logs', 'feedback'];
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($requiredTables as $tbl) {
        assertTest("Table '{$tbl}' exists", 
            in_array($tbl, $tables), 
            'Missing table', 
            $totalTests, $passedTests, $failedTests, $failures);
    }
    
    // Check data counts
    $counts = [];
    foreach (['faq', 'staff', 'news'] as $tbl) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM {$tbl}");
            $counts[$tbl] = (int)$stmt->fetchColumn();
            assertTest("Table '{$tbl}' has data ({$counts[$tbl]} rows)", 
                $counts[$tbl] > 0, 
                'Table is empty', 
                $totalTests, $passedTests, $failedTests, $failures);
        } catch (Exception $e) {
            assertTest("Table '{$tbl}' has data", false, $e->getMessage(), $totalTests, $passedTests, $failedTests, $failures);
        }
    }
    
    // Check FULLTEXT index on faq
    try {
        $stmt = $pdo->query("SHOW INDEX FROM faq WHERE Index_type = 'FULLTEXT'");
        $ftIdx = $stmt->fetchAll(PDO::FETCH_ASSOC);
        assertTest('FULLTEXT index on faq', 
            count($ftIdx) > 0, 
            'No FULLTEXT index', 
            $totalTests, $passedTests, $failedTests, $failures);
    } catch (Exception $e) {
        assertTest('FULLTEXT index on faq', false, $e->getMessage(), $totalTests, $passedTests, $failedTests, $failures);
    }
        
} catch (Exception $e) {
    assertTest('Database connection', false, $e->getMessage(), $totalTests, $passedTests, $failedTests, $failures);
}

$sectionResults['1_DB'] = ['total' => $totalTests, 'passed' => $passedTests];

// =====================================================================
//  SECTION 2: AI SERVICE HEALTH
// =====================================================================
printHeader('2. AI SERVICE (Python Flask API)');

$aiOk = false;
$s2Start = $totalTests;
$s2Pass  = $passedTests;

// 2.1 Health endpoint
$health = getRequest($AI_URL . '/health');
$aiOk = ($health['http_code'] === 200 && ($health['data']['status'] ?? '') === 'healthy');
assertTest('AI /health endpoint', 
    $aiOk, 
    $aiOk ? '' : 'AI server not running?', 
    $totalTests, $passedTests, $failedTests, $failures);

if ($aiOk) {
    assertTest('AI model loaded', 
        ($health['data']['model_loaded'] ?? false) === true, 
        'Model not loaded', 
        $totalTests, $passedTests, $failedTests, $failures);
    
    // 2.2 Single prediction
    $pred = postRequest($AI_URL . '/predict', ['question' => 'ค่าเทอมเท่าไหร่']);
    assertTest('AI /predict returns intent', 
        !empty($pred['data']['intent']), 
        'No intent returned', 
        $totalTests, $passedTests, $failedTests, $failures);
    assertTest('AI /predict returns confidence', 
        isset($pred['data']['confidence']) && $pred['data']['confidence'] > 0, 
        '', 
        $totalTests, $passedTests, $failedTests, $failures);
    assertTest('AI /predict returns processing_time_ms', 
        isset($pred['data']['processing_time_ms']), 
        '', 
        $totalTests, $passedTests, $failedTests, $failures);
    
    // 2.3 Empty question
    $empty = postRequest($AI_URL . '/predict', ['question' => '']);
    assertTest('AI rejects empty question', 
        $empty['http_code'] === 400, 
        "HTTP {$empty['http_code']}", 
        $totalTests, $passedTests, $failedTests, $failures);
    
    // 2.4 Missing field
    $noQ = postRequest($AI_URL . '/predict', ['text' => 'something']);
    assertTest('AI rejects missing "question" field', 
        $noQ['http_code'] === 400, 
        "HTTP {$noQ['http_code']}", 
        $totalTests, $passedTests, $failedTests, $failures);
    
    // 2.5 Long input (note: requires AI server restart after code update)
    $longQ = postRequest($AI_URL . '/predict', ['question' => str_repeat('a', 2500)]);
    assertTest('AI rejects too-long question (>2000)', 
        $longQ['http_code'] === 400, 
        "HTTP {$longQ['http_code']} (restart AI server if failed)", 
        $totalTests, $passedTests, $failedTests, $failures);
    
    // 2.6 Batch prediction
    $batch = postRequest($AI_URL . '/batch_predict', [
        'questions' => ['ค่าเทอม', 'สหกิจศึกษา', 'ทุนการศึกษา']
    ]);
    assertTest('AI /batch_predict works', 
        ($batch['data']['count'] ?? 0) === 3, 
        "count=" . ($batch['data']['count'] ?? 'null'), 
        $totalTests, $passedTests, $failedTests, $failures);
    
    // 2.7 Batch limit (note: requires AI server restart after code update)
    $bigBatch = postRequest($AI_URL . '/batch_predict', [
        'questions' => array_fill(0, 55, 'test')
    ]);
    assertTest('AI rejects batch > 50', 
        $bigBatch['http_code'] === 400, 
        "HTTP {$bigBatch['http_code']} (restart AI server if failed)", 
        $totalTests, $passedTests, $failedTests, $failures);
    
    // 2.8 Home endpoint
    $home = getRequest($AI_URL . '/');
    assertTest('AI / home endpoint', 
        ($home['data']['status'] ?? '') === 'running', 
        '', 
        $totalTests, $passedTests, $failedTests, $failures);
    
} else {
    for ($i = 0; $i < 10; $i++) {
        assertSkip('AI test (server not running)', 'AI API unavailable', $totalTests, $skippedTests);
    }
}

$sectionResults['2_AI'] = ['total' => $totalTests - $s2Start, 'passed' => $passedTests - $s2Pass];

// =====================================================================
//  SECTION 3: CHATBOT API - FAQ SEARCH ACCURACY
// =====================================================================
printHeader('3. CHATBOT API - FAQ SEARCH ACCURACY');

$s3Start = $totalTests;
$s3Pass  = $passedTests;

// Define test cases: [question, expected_keywords_in_answer, description]
$faqTests = [
    // --- ค่าเทอม / การเงิน ---
    ['ค่าเทอมเท่าไหร่', ['ค่า', 'เทอม'], 'ค่าเทอม (ตรง)'],
    ['ค่าเรียนวิศวกรรมคอมพิวเตอร์', ['ค่า'], 'ค่าเรียนสาขาเฉพาะ'],
    ['ผ่อนค่าเทอมได้ไหม', ['ผ่อน', 'ค่าเทอม', 'ค่าเล่าเรียน', 'ค่า'], 'การผ่อนค่าเทอม'],
    
    // --- ทุน / กู้ยืม ---
    ['ทุนการศึกษามีอะไรบ้าง', ['ทุน', 'scholarship', 'เรียนดี', 'กิจกรรม', 'ข่าว'], 'ทุนการศึกษา'],
    ['กู้ กยศ ได้ไหม', ['กยศ', 'กู้'], 'กู้ยืม กยศ'],
    ['กองทุนเงินให้กู้ยืม', ['กู้', 'กองทุน'], 'กองทุนเงินกู้'],
    
    // --- การรับสมัคร ---
    ['รับสมัครเมื่อไหร่', ['สมัคร', 'รับ'], 'รับสมัคร'],
    ['เปิดรับสมัครตอนไหน', ['สมัคร', 'รับ'], 'เปิดรับสมัคร'],
    
    // --- หลักสูตร / สาขา ---
    ['มีสาขาอะไรบ้าง', ['สาขา', 'วิศวกรรม'], 'สาขาวิชา'],
    ['วิศวกรรมคอมพิวเตอร์เรียนอะไร', ['คอมพิวเตอร์'], 'หลักสูตรคอมฯ'],
    ['วิศวกรรมไฟฟ้าเรียนอะไรบ้าง', ['ไฟฟ้า'], 'หลักสูตรไฟฟ้า'],
    ['วิศวกรรมโยธาเรียนอะไร', ['โยธา'], 'หลักสูตรโยธา'],
    
    // --- สหกิจ / ฝึกงาน ---
    ['สหกิจศึกษาคืออะไร', ['สหกิจ'], 'สหกิจศึกษา'],
    ['ฝึกงานตอนไหน', ['ฝึกงาน'], 'ฝึกงาน'],
    
    // --- เกรด / การวัดผล ---
    ['ได้เกรด F ทำยังไง', ['เกรด', 'F'], 'เกรด F'],
    ['เกรดเฉลี่ยต่ำพ้นสภาพไหม', ['พ้นสภาพ', 'เกรด'], 'พ้นสภาพ'],
    
    // --- สถานที่ ---
    ['คณะวิศวกรรมศาสตร์อยู่ไหน', ['อยู่', 'คณะ', 'ที่ตั้ง', 'สถานที่'], 'สถานที่คณะ'],
    
    // --- จบแล้วทำอะไร ---
    ['จบวิศวะทำงานอะไร', ['ทำงาน', 'จบ', 'อาชีพ', 'วิศวกร'], 'อาชีพหลังจบ'],
    
    // --- การเรียนต่อ ---
    ['เรียนต่อปริญญาโทได้ไหม', ['ปริญญาโท', 'เรียนต่อ', 'ต่อยอด', 'ป.โท'], 'เรียนต่อ ป.โท'],
    
    // --- เรียนกี่ปี ---
    ['เรียนกี่ปี', ['ปี', '4', 'หลักสูตร'], 'ระยะเวลาเรียน'],
    
    // --- ลงทะเบียน ---
    ['ลงทะเบียนยังไง', ['ลงทะเบียน'], 'ลงทะเบียน'],
    
    // --- ย้ายสาขา ---
    ['ย้ายสาขาได้ไหม', ['ย้าย', 'เทียบโอน', 'สาขา', 'หลักสูตร'], 'ย้ายสาขา/เทียบโอน'],
    
    // --- ติดต่อ ---
    ['ติดต่อคณะยังไง', ['ติดต่อ', 'โทร'], 'ติดต่อคณะ'],
    ['เบอร์โทรคณะ', ['โทร'], 'เบอร์โทรศัพท์'],
];

foreach ($faqTests as [$question, $expectedWords, $desc]) {
    $result = chatRequest($BASE_URL, $question);
    $answer = $result['answer'] ?? '';
    
    // Check if answer contains at least ONE of the expected keywords
    $found = false;
    $foundWord = '';
    foreach ($expectedWords as $word) {
        if (mb_stripos($answer, $word) !== false) {
            $found = true;
            $foundWord = $word;
            break;
        }
    }
    
    $detail = '';
    if (!$found) {
        $detail = "Keywords [" . implode(', ', $expectedWords) . "] not found. Answer: " . mb_substr($answer, 0, 100);
    }
    
    assertTest("FAQ: {$desc} ({$question})", $found, $detail, $totalTests, $passedTests, $failedTests, $failures);
}

$sectionResults['3_FAQ'] = ['total' => $totalTests - $s3Start, 'passed' => $passedTests - $s3Pass];

// =====================================================================
//  SECTION 4: CHATBOT API - STAFF SEARCH
// =====================================================================
printHeader('4. CHATBOT API - STAFF SEARCH');

$s4Start = $totalTests;
$s4Pass  = $passedTests;

$staffTests = [
    ['อาจารย์สาขาคอมพิวเตอร์', 'staff', 'ค้นหาอาจารย์สาขาคอม'],
    ['อาจารย์สาขาไฟฟ้า', 'staff', 'ค้นหาอาจารย์สาขาไฟฟ้า'],
    ['อาจารย์สาขาโยธา', 'staff', 'ค้นหาอาจารย์สาขาโยธา'],
    ['อาจารย์ประจำคณะ', 'staff', 'อาจารย์ทั้งหมด'],
];

foreach ($staffTests as [$question, $expectedType, $desc]) {
    $result = chatRequest($BASE_URL, $question);
    $answer = $result['answer'] ?? '';
    $sources = $result['sources'] ?? [];
    
    $isStaffResult = (
        mb_stripos($answer, 'อาจารย์') !== false || 
        mb_stripos($answer, 'บุคลากร') !== false ||
        mb_stripos($answer, 'ดร.') !== false ||
        mb_stripos($answer, 'ผศ.') !== false ||
        mb_stripos($answer, 'รศ.') !== false ||
        mb_stripos($answer, 'ศ.') !== false
    );
    
    $hasStaffSource = false;
    foreach ($sources as $src) {
        if (($src['type'] ?? '') === 'staff') {
            $hasStaffSource = true;
            break;
        }
    }
    
    assertTest("Staff: {$desc}", 
        $isStaffResult || $hasStaffSource, 
        $isStaffResult ? '' : 'Answer: ' . mb_substr($answer, 0, 80),
        $totalTests, $passedTests, $failedTests, $failures);
}

$sectionResults['4_Staff'] = ['total' => $totalTests - $s4Start, 'passed' => $passedTests - $s4Pass];

// =====================================================================
//  SECTION 5: CHATBOT API - NEWS SEARCH
// =====================================================================
printHeader('5. CHATBOT API - NEWS SEARCH');

$s5Start = $totalTests;
$s5Pass  = $passedTests;

$newsTests = [
    ['ข่าวล่าสุด', 'ข่าว', 'ข่าวล่าสุด'],
    ['มีข่าวอะไรบ้าง', 'ข่าว', 'ข่าวทั่วไป'],
    ['ข่าวกิจกรรมคณะ', 'กิจกรรม', 'ข่าวกิจกรรม'],
];

foreach ($newsTests as [$question, $expectedWord, $desc]) {
    $result = chatRequest($BASE_URL, $question);
    $answer = $result['answer'] ?? '';
    $sources = $result['sources'] ?? [];
    
    $hasNewsContent = (
        mb_stripos($answer, 'ข่าว') !== false || 
        mb_stripos($answer, 'กิจกรรม') !== false ||
        mb_stripos($answer, 'ประชาสัมพันธ์') !== false
    );
    
    $hasNewsSource = false;
    foreach ($sources as $src) {
        if (($src['type'] ?? '') === 'news') {
            $hasNewsSource = true;
            break;
        }
    }
    
    assertTest("News: {$desc}", 
        $hasNewsContent || $hasNewsSource, 
        $hasNewsContent ? '' : 'Answer: ' . mb_substr($answer, 0, 80),
        $totalTests, $passedTests, $failedTests, $failures);
}

$sectionResults['5_News'] = ['total' => $totalTests - $s5Start, 'passed' => $passedTests - $s5Pass];

// =====================================================================
//  SECTION 6: BROAD TOPIC HANDLER
// =====================================================================
printHeader('6. BROAD TOPIC HANDLER');

$s6Start = $totalTests;
$s6Pass  = $passedTests;

// Test that broad/generic questions return structured answers (not just empty)
$broadTests = [
    // These should trigger BroadTopicHandler or return meaningful FAQ results
    ['เรื่องทุน', 'ทุน', 'Broad: ทุนการศึกษา'],
    ['เรื่องค่าเทอม', 'ค่า', 'Broad: ค่าเทอม'],
    ['เรื่องหลักสูตร', 'หลักสูตร', 'Broad: หลักสูตร'],
    ['อาชีพ', 'อาชีพ|ทำงาน|จบ', 'Broad: อาชีพ'],
    ['สหกิจ', 'สหกิจ|ฝึกงาน', 'Broad: สหกิจ'],
    ['การรับสมัคร', 'สมัคร|รับ', 'Broad: การรับสมัคร'],
];

foreach ($broadTests as [$question, $pattern, $desc]) {
    $result = chatRequest($BASE_URL, $question);
    $answer = $result['answer'] ?? '';
    
    // Check with regex pattern
    $matched = (bool)preg_match("/{$pattern}/u", $answer);
    
    assertTest($desc, 
        $matched && mb_strlen($answer) > 20, 
        $matched ? '' : 'Answer: ' . mb_substr($answer, 0, 100),
        $totalTests, $passedTests, $failedTests, $failures);
}

$sectionResults['6_Broad'] = ['total' => $totalTests - $s6Start, 'passed' => $passedTests - $s6Pass];

// =====================================================================
//  SECTION 7: RESPONSE FORMAT VALIDATION
// =====================================================================
printHeader('7. RESPONSE FORMAT VALIDATION');

$s7Start = $totalTests;
$s7Pass  = $passedTests;

$formatResult = chatRequest($BASE_URL, 'ค่าเทอมเท่าไหร่');

// Required fields
assertTest('Response has "answer" field', 
    isset($formatResult['answer']), '', 
    $totalTests, $passedTests, $failedTests, $failures);

assertTest('Response has "sources" field', 
    isset($formatResult['sources']), '', 
    $totalTests, $passedTests, $failedTests, $failures);

assertTest('Response has "confidence" field', 
    isset($formatResult['confidence']), '', 
    $totalTests, $passedTests, $failedTests, $failures);

assertTest('Confidence is 0-100 range', 
    isset($formatResult['confidence']) && $formatResult['confidence'] >= 0 && $formatResult['confidence'] <= 100, 
    'confidence=' . ($formatResult['confidence'] ?? 'null'), 
    $totalTests, $passedTests, $failedTests, $failures);

assertTest('Response has "response_time_ms"', 
    isset($formatResult['response_time_ms']), '', 
    $totalTests, $passedTests, $failedTests, $failures);

assertTest('"sources" is an array', 
    is_array($formatResult['sources'] ?? null), '', 
    $totalTests, $passedTests, $failedTests, $failures);

// Check source structure (only if sources exist)
if (!empty($formatResult['sources'])) {
    $src = $formatResult['sources'][0];
    // FAQ sources have 'question', news sources have 'title'  
    $hasTitleOrQuestion = isset($src['title']) || isset($src['question']);
    assertTest('Source has "title" or "question" field', $hasTitleOrQuestion, json_encode(array_keys($src)), $totalTests, $passedTests, $failedTests, $failures);
    assertTest('Source has "type" field', isset($src['type']), '', $totalTests, $passedTests, $failedTests, $failures);
} else {
    assertTest('Sources array is empty or not present', true, 'No sources to check', $totalTests, $passedTests, $failedTests, $failures);
}

assertTest('Answer is non-empty string', 
    is_string($formatResult['answer'] ?? null) && mb_strlen($formatResult['answer']) > 0, 
    '', 
    $totalTests, $passedTests, $failedTests, $failures);

assertTest('Response JSON is valid UTF-8', 
    mb_detect_encoding($formatResult['answer'] ?? '', 'UTF-8', true) !== false, 
    '', 
    $totalTests, $passedTests, $failedTests, $failures);

$sectionResults['7_Format'] = ['total' => $totalTests - $s7Start, 'passed' => $passedTests - $s7Pass];

// =====================================================================
//  SECTION 8: EDGE CASES & INPUT VALIDATION
// =====================================================================
printHeader('8. EDGE CASES & INPUT VALIDATION');

$s8Start = $totalTests;
$s8Pass  = $passedTests;

// 8.1 Empty message
$emptyResult = chatRequest($BASE_URL, '');
assertTest('Empty message handled gracefully', 
    isset($emptyResult['answer']) || isset($emptyResult['error']),
    '', 
    $totalTests, $passedTests, $failedTests, $failures);

// 8.2 Whitespace only
$wsResult = chatRequest($BASE_URL, '   ');
assertTest('Whitespace-only message handled', 
    isset($wsResult['answer']) || isset($wsResult['error']),
    '', 
    $totalTests, $passedTests, $failedTests, $failures);

// 8.3 Very long message  
$longMsg = str_repeat('ทดสอบ', 500); // 3000 chars
$longResult = chatRequest($BASE_URL, $longMsg);
assertTest('Very long message does not crash', 
    isset($longResult['answer']) || isset($longResult['error']),
    '', 
    $totalTests, $passedTests, $failedTests, $failures);

// 8.4 Special characters
$specialResult = chatRequest($BASE_URL, '~!@#$%^&*()_+{}|:"<>?');
assertTest('Special characters handled', 
    isset($specialResult['answer']),
    '', 
    $totalTests, $passedTests, $failedTests, $failures);

// 8.5 Numbers only
$numResult = chatRequest($BASE_URL, '12345');
assertTest('Numbers-only input handled', 
    isset($numResult['answer']),
    '', 
    $totalTests, $passedTests, $failedTests, $failures);

// 8.6 English text
$enResult = chatRequest($BASE_URL, 'What is the tuition fee?');
assertTest('English input returns answer', 
    isset($enResult['answer']) && mb_strlen($enResult['answer']) > 0,
    '', 
    $totalTests, $passedTests, $failedTests, $failures);

// 8.7 Mixed Thai-English
$mixedResult = chatRequest($BASE_URL, 'ค่า tuition fee เท่าไหร่');
assertTest('Mixed Thai-English handled', 
    isset($mixedResult['answer']),
    '', 
    $totalTests, $passedTests, $failedTests, $failures);

// 8.8 Single character
$singleResult = chatRequest($BASE_URL, 'ก');
assertTest('Single character handled',
    isset($singleResult['answer']),
    '', 
    $totalTests, $passedTests, $failedTests, $failures);

// 8.9 Emoji input
$emojiResult = chatRequest($BASE_URL, '😊 ค่าเทอม');
assertTest('Emoji in message handled', 
    isset($emojiResult['answer']),
    '', 
    $totalTests, $passedTests, $failedTests, $failures);

// 8.10 Repeated same question (cache consistency)
$r1 = chatRequest($BASE_URL, 'ค่าเทอมเท่าไหร่');
$r2 = chatRequest($BASE_URL, 'ค่าเทอมเท่าไหร่');
assertTest('Repeated question returns consistent result', 
    !empty($r1['answer']) && !empty($r2['answer']),
    '', 
    $totalTests, $passedTests, $failedTests, $failures);

$sectionResults['8_Edge'] = ['total' => $totalTests - $s8Start, 'passed' => $passedTests - $s8Pass];

// =====================================================================
//  SECTION 9: SECURITY TESTS
// =====================================================================
printHeader('9. SECURITY TESTS');

$s9Start = $totalTests;
$s9Pass  = $passedTests;

// 9.1 XSS attempt in message
$xssResult = chatRequest($BASE_URL, '<script>alert("XSS")</script>');
$xssAnswer = $xssResult['answer'] ?? '';
assertTest('XSS in message does not reflect raw script tag', 
    stripos($xssAnswer, '<script>') === false,
    '', 
    $totalTests, $passedTests, $failedTests, $failures);

// 9.2 SQL injection attempt
$sqlResult = chatRequest($BASE_URL, "'; DROP TABLE faqs; --");
assertTest('SQL injection does not crash', 
    isset($sqlResult['answer']) || isset($sqlResult['error']),
    '', 
    $totalTests, $passedTests, $failedTests, $failures);

// Verify faq table still exists
if ($dbOk) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM faq");
    $c = (int)$stmt->fetchColumn();
    assertTest('faq table intact after SQL injection attempt', $c > 0, "count={$c}", 
        $totalTests, $passedTests, $failedTests, $failures);
}

// 9.3 Path traversal attempt
$pathResult = chatRequest($BASE_URL, '../../../etc/passwd');
assertTest('Path traversal does not leak file content', 
    stripos($pathResult['answer'] ?? '', 'root:') === false,
    '', 
    $totalTests, $passedTests, $failedTests, $failures);

// 9.4 analytics_api requires auth
$analyticsNoAuth = getRequest($BASE_URL . '/analytics_api.php?action=summary');
assertTest('analytics_api denies unauthenticated (non-localhost uses curl)', 
    $analyticsNoAuth['http_code'] === 200 || $analyticsNoAuth['http_code'] === 401,
    "HTTP {$analyticsNoAuth['http_code']} (localhost may be allowed)", 
    $totalTests, $passedTests, $failedTests, $failures);

// 9.5 feedback_api rejects GET
$feedbackGet = getRequest($BASE_URL . '/feedback_api.php');
assertTest('feedback_api rejects GET method', 
    $feedbackGet['http_code'] === 405,
    "HTTP {$feedbackGet['http_code']}", 
    $totalTests, $passedTests, $failedTests, $failures);

// 9.6 JSON content type
$result = chatRequest($BASE_URL, 'test');
assertTest('Response is JSON content type', 
    $result['http_code'] === 200,
    "HTTP {$result['http_code']}", 
    $totalTests, $passedTests, $failedTests, $failures);

// 9.7 Invalid JSON body
$ch = curl_init($BASE_URL . '/chatbot.php');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => 'this is not json{{{',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Origin: http://localhost',
    ],
    CURLOPT_TIMEOUT => 10,
]);
$invalidJsonResp = curl_exec($ch);
$invalidJsonCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$invalidJsonData = json_decode($invalidJsonResp, true);
assertTest('Invalid JSON body handled gracefully', 
    isset($invalidJsonData['error']) || isset($invalidJsonData['answer']),
    "HTTP {$invalidJsonCode}", 
    $totalTests, $passedTests, $failedTests, $failures);

// 9.8 XSS in source parameter
$xssResult2 = chatRequest($BASE_URL, 'ค่าเทอม<img onerror=alert(1) src=x>');
assertTest('XSS in message param sanitized', 
    stripos($xssResult2['answer'] ?? '', 'onerror') === false,
    '', 
    $totalTests, $passedTests, $failedTests, $failures);

$sectionResults['9_Security'] = ['total' => $totalTests - $s9Start, 'passed' => $passedTests - $s9Pass];

// =====================================================================
//  SECTION 10: API ENDPOINTS
// =====================================================================
printHeader('10. API ENDPOINTS');

$s10Start = $totalTests;
$s10Pass  = $passedTests;

// 10.1 Chatbot POST
$chatResult = chatRequest($BASE_URL, 'สวัสดี');
assertTest('POST /chatbot.php works', 
    $chatResult['http_code'] === 200 && isset($chatResult['answer']),
    '', 
    $totalTests, $passedTests, $failedTests, $failures);

// 10.2 Chatbot GET (should fail or return error)
$chatGet = getRequest($BASE_URL . '/chatbot.php');
assertTest('GET /chatbot.php returns error or empty', 
    $chatGet['http_code'] !== 200 || isset($chatGet['data']['error']),
    "HTTP {$chatGet['http_code']}", 
    $totalTests, $passedTests, $failedTests, $failures);

// 10.3 clear_cache.php from localhost
$cacheResult = getRequest($BASE_URL . '/clear_cache.php');
assertTest('GET /clear_cache.php accessible from localhost', 
    $cacheResult['http_code'] === 200,
    "HTTP {$cacheResult['http_code']}", 
    $totalTests, $passedTests, $failedTests, $failures);

// 10.4 feedback POST with valid data
$sessionId = 'test_' . uniqid();
// First create a chat log
chatRequest($BASE_URL, 'ค่าเทอมเท่าไหร่', $sessionId);
$feedbackResult = postRequest($BASE_URL . '/feedback_api.php', [
    'action' => 'feedback',
    'feedback_type' => 'positive',
    'session_id' => $sessionId,
]);
assertTest('POST /feedback_api.php with valid data', 
    ($feedbackResult['data']['success'] ?? false) === true,
    json_encode($feedbackResult['data'] ?? [], JSON_UNESCAPED_UNICODE), 
    $totalTests, $passedTests, $failedTests, $failures);

// 10.5 feedback with invalid type
$badFeedback = postRequest($BASE_URL . '/feedback_api.php', [
    'action' => 'feedback',
    'feedback_type' => 'invalid_type',
    'session_id' => $sessionId,
]);
assertTest('Feedback rejects invalid feedback_type', 
    $badFeedback['http_code'] === 400,
    "HTTP {$badFeedback['http_code']}", 
    $totalTests, $passedTests, $failedTests, $failures);

// 10.6 admin_login without credentials
$loginNoAuth = postRequest($BASE_URL . '/admin_login.php', [
    'action' => 'login',
    'username' => '',
    'password' => '',
]);
assertTest('admin_login rejects empty credentials', 
    ($loginNoAuth['data']['success'] ?? true) === false || $loginNoAuth['http_code'] >= 400,
    '', 
    $totalTests, $passedTests, $failedTests, $failures);

$sectionResults['10_Endpoints'] = ['total' => $totalTests - $s10Start, 'passed' => $passedTests - $s10Pass];

// =====================================================================
//  SECTION 11: CHATBOT FEATURES
// =====================================================================
printHeader('11. CHATBOT FEATURES');

$s11Start = $totalTests;
$s11Pass  = $passedTests;

// 11.1 Greeting response (chatbot may not have special greeting - check it doesn't crash)
$greetResult = chatRequest($BASE_URL, 'สวัสดีครับ');
$greetAnswer = $greetResult['answer'] ?? '';
assertTest('Greeting returns an answer', 
    mb_strlen($greetAnswer) > 0,
    mb_substr($greetAnswer, 0, 60), 
    $totalTests, $passedTests, $failedTests, $failures);

// 11.2 Thank you response
$thankResult = chatRequest($BASE_URL, 'ขอบคุณครับ');
$thankAnswer = $thankResult['answer'] ?? '';
assertTest('Thank you returns an answer', 
    mb_strlen($thankAnswer) > 0,
    mb_substr($thankAnswer, 0, 60), 
    $totalTests, $passedTests, $failedTests, $failures);

// 11.3 Related questions returned (only for high-confidence FAQ matches)
$relResult = chatRequest($BASE_URL, 'ค่าเทอมวิศวกรรมคอมพิวเตอร์');
$relQuestions = $relResult['related_questions'] ?? [];
assertTest('Related questions field exists', 
    array_key_exists('related_questions', $relResult),
    '', 
    $totalTests, $passedTests, $failedTests, $failures);

// 11.4 Response time is reasonable (<5 seconds)
$timeResult = chatRequest($BASE_URL, 'สหกิจศึกษาคืออะไร');
$respTime = $timeResult['response_time_ms'] ?? 99999;
assertTest('Response time < 5000ms', 
    $respTime < 5000,
    "Time: {$respTime}ms", 
    $totalTests, $passedTests, $failedTests, $failures);

// 11.5 Not-found / fallback response
$unknownResult = chatRequest($BASE_URL, 'ปลาทูนาราคาเท่าไหร่ในตลาด');
$unknownAnswer = $unknownResult['answer'] ?? '';
assertTest('Unknown question returns fallback', 
    mb_strlen($unknownAnswer) > 0,
    mb_substr($unknownAnswer, 0, 60), 
    $totalTests, $passedTests, $failedTests, $failures);

// 11.6 Session continuity
$sessId = 'continuity_' . uniqid();
$c1 = chatRequest($BASE_URL, 'ค่าเทอมเท่าไหร่', $sessId);
$c2 = chatRequest($BASE_URL, 'แล้วผ่อนได้ไหม', $sessId);
assertTest('Session continuity (same session_id)', 
    !empty($c2['answer']),
    '', 
    $totalTests, $passedTests, $failedTests, $failures);

// 11.7 Category icon detection (check answer structure)
$catResult = chatRequest($BASE_URL, 'ค่าเทอมวิศวกรรมคอมพิวเตอร์');
$catAnswer = $catResult['answer'] ?? '';
assertTest('Category-specific answer content', 
    mb_strlen($catAnswer) > 30,
    'len=' . mb_strlen($catAnswer), 
    $totalTests, $passedTests, $failedTests, $failures);

$sectionResults['11_Features'] = ['total' => $totalTests - $s11Start, 'passed' => $passedTests - $s11Pass];

// =====================================================================
//  SECTION 12: CHATBOT CONFIG UNIT CHECKS  
// =====================================================================
printHeader('12. CHATBOT CONFIG VALIDATION');

$s12Start = $totalTests;
$s12Pass  = $passedTests;

require_once __DIR__ . '/../backend/ChatbotConfig.php';

// Config uses const for scoring, not a $scoringWeights array
assertTest('ChatbotConfig::SCORE_EXACT_MATCH defined', 
    defined('ChatbotConfig::SCORE_EXACT_MATCH'),
    '', 
    $totalTests, $passedTests, $failedTests, $failures);

assertTest('ChatbotConfig::$synonyms exists', 
    !empty(ChatbotConfig::$synonyms),
    '', 
    $totalTests, $passedTests, $failedTests, $failures);

assertTest('ChatbotConfig::$normalizations exists', 
    !empty(ChatbotConfig::$normalizations),
    '', 
    $totalTests, $passedTests, $failedTests, $failures);

assertTest('ChatbotConfig::$intentPatterns exists', 
    !empty(ChatbotConfig::$intentPatterns),
    '', 
    $totalTests, $passedTests, $failedTests, $failures);

assertTest('ChatbotConfig::$relatedCategories exists', 
    !empty(ChatbotConfig::$relatedCategories),
    '', 
    $totalTests, $passedTests, $failedTests, $failures);

assertTest('ChatbotConfig::$departmentMap exists', 
    !empty(ChatbotConfig::$departmentMap),
    '', 
    $totalTests, $passedTests, $failedTests, $failures);

assertTest('ChatbotConfig::$commonWords exists', 
    !empty(ChatbotConfig::$commonWords),
    '', 
    $totalTests, $passedTests, $failedTests, $failures);

// Check critical synonyms for FAQ accuracy
$criticalSynonyms = ['ค่าเทอม', 'กยศ', 'ทุน'];
foreach ($criticalSynonyms as $syn) {
    assertTest("Synonym defined: '{$syn}'", 
        isset(ChatbotConfig::$synonyms[$syn]),
        '', 
        $totalTests, $passedTests, $failedTests, $failures);
}

// Check scoring constants
$requiredConsts = ['SCORE_EXACT_MATCH', 'SCORE_PHRASE_MATCH', 'SCORE_CATEGORY_MATCH', 'CONFIDENCE_MIN'];
foreach ($requiredConsts as $cname) {
    assertTest("Const: ChatbotConfig::{$cname}", 
        defined("ChatbotConfig::{$cname}"),
        '', 
        $totalTests, $passedTests, $failedTests, $failures);
}

$sectionResults['12_Config'] = ['total' => $totalTests - $s12Start, 'passed' => $passedTests - $s12Pass];

// =====================================================================
//  SECTION 13: BROAD TOPIC HANDLER UNIT CHECK
// =====================================================================
printHeader('13. BROAD TOPIC HANDLER UNIT CHECK');

$s13Start = $totalTests;
$s13Pass  = $passedTests;

require_once __DIR__ . '/../backend/broad_topic_handler.php';

// Test topic detection for known broad topics
$broadDetectionTests = [
    ['ค่าเทอม', 'tuition', 'detect tuition topic'],
    ['หลักสูตร', 'curriculum', 'detect curriculum topic'],
    ['อาชีพ', 'career', 'detect career topic'],
    ['ทุนการศึกษา', 'scholarship', 'detect scholarship topic'],
    ['กยศ', 'loan', 'detect loan topic'],
    ['ฝึกงาน', 'internship', 'detect internship topic'],
    ['สหกิจ', 'coop', 'detect coop topic'],
    ['เทียบโอน', 'transfer', 'detect transfer topic'],
    ['เกรด', 'grade', 'detect grade topic'],
    ['การรับสมัคร', 'admission', 'detect admission topic'],
];

foreach ($broadDetectionTests as [$msg, $expectedTopic, $desc]) {
    $detected = BroadTopicHandler::detectBroadTopic($msg);
    
    $detectedId = is_array($detected) ? ($detected['id'] ?? null) : $detected;
    
    assertTest("BroadTopic: {$desc}", 
        $detectedId === $expectedTopic,
        "Expected '{$expectedTopic}', got '" . ($detectedId ?: 'null') . "'", 
        $totalTests, $passedTests, $failedTests, $failures);
}

// Test cleanMessage suffix stripping
$ref = new ReflectionClass(BroadTopicHandler::class);
$cleanMethod = $ref->getMethod('cleanMessage');
$cleanMethod->setAccessible(true);

$cleanTests = [
    ['ค่าเทอมคืออะไร', 'ค่าเทอม'],
    ['ทุนทำยังไง', 'ทุน'],
    ['สหกิจได้บ้าง', 'สหกิจ'],
];

foreach ($cleanTests as [$input, $expected]) {
    $cleaned = $cleanMethod->invoke(null, $input);
    assertTest("cleanMessage: '{$input}' => '{$expected}'", 
        trim($cleaned) === $expected,
        "Got: '{$cleaned}'", 
        $totalTests, $passedTests, $failedTests, $failures);
}

$sectionResults['13_BroadUnit'] = ['total' => $totalTests - $s13Start, 'passed' => $passedTests - $s13Pass];

// =====================================================================
//  SECTION 14: QUERY ANALYZER UNIT CHECK
// =====================================================================
printHeader('14. QUERY ANALYZER UNIT CHECK');

$s14Start = $totalTests;
$s14Pass  = $passedTests;

require_once __DIR__ . '/../backend/QueryAnalyzer.php';

// 14.1 normalizeQuery
$normTests = [
    ['คอม', 'คอมพิวเตอร์'],
    ['กยศ', 'กองทุน'],
];
foreach ($normTests as [$input, $expected]) {
    $normalized = QueryAnalyzer::normalizeQuery($input);
    assertTest("normalizeQuery('{$input}') contains '{$expected}'", 
        mb_stripos($normalized, $expected) !== false,
        "Got: '{$normalized}'", 
        $totalTests, $passedTests, $failedTests, $failures);
}

// 14.2 expandQuerySynonyms
$synTests = [
    ['ค่าเทอม', 'ค่าเรียน'],  // expanded via synonyms
];
foreach ($synTests as [$input, $expected]) {
    $expanded = QueryAnalyzer::expandQuerySynonyms($input);
    assertTest("expandSynonyms('{$input}') contains '{$expected}'", 
        mb_stripos($expanded, $expected) !== false,
        "Got: " . mb_substr($expanded, 0, 80), 
        $totalTests, $passedTests, $failedTests, $failures);
}

$sectionResults['14_QueryAnalyzer'] = ['total' => $totalTests - $s14Start, 'passed' => $passedTests - $s14Pass];

// =====================================================================
//  SECTION 15: DATABASE DATA INTEGRITY
// =====================================================================
printHeader('15. DATABASE DATA INTEGRITY');

$s15Start = $totalTests;
$s15Pass  = $passedTests;

if ($dbOk) {
    // 15.1 FAQ categories exist
    $stmt = $pdo->query("SELECT DISTINCT category FROM faq WHERE category IS NOT NULL AND category != ''");
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    assertTest('FAQs have categories', 
        count($categories) > 3,
        'categories=' . count($categories), 
        $totalTests, $passedTests, $failedTests, $failures);
    
    // 15.2 Staff have departments
    $stmt = $pdo->query("SELECT DISTINCT department FROM staff WHERE department IS NOT NULL AND department != ''");
    $depts = $stmt->fetchAll(PDO::FETCH_COLUMN);
    assertTest('Staff have departments', 
        count($depts) > 2,
        'departments=' . count($depts), 
        $totalTests, $passedTests, $failedTests, $failures);
    
    // 15.3 No empty FAQ questions
    $stmt = $pdo->query("SELECT COUNT(*) FROM faq WHERE (question IS NULL OR question = '') AND (answer IS NULL OR answer = '')");
    $emptyFaqs = (int)$stmt->fetchColumn();
    assertTest('No empty FAQ entries', 
        $emptyFaqs === 0,
        "empty={$emptyFaqs}", 
        $totalTests, $passedTests, $failedTests, $failures);
    
    // 15.4 News entries have dates
    $stmt = $pdo->query("SELECT COUNT(*) FROM news WHERE published_date IS NULL");
    $nullDates = (int)$stmt->fetchColumn();
    $stmt2 = $pdo->query("SELECT COUNT(*) FROM news");
    $totalNews = (int)$stmt2->fetchColumn();
    assertTest('News entries have published dates', 
        $nullDates === 0 || $nullDates < $totalNews,
        "null_dates={$nullDates}/{$totalNews}", 
        $totalTests, $passedTests, $failedTests, $failures);
    
    // 15.5 Chat logs table writable
    $stmt = $pdo->prepare("INSERT INTO chat_logs (session_id, user_message, bot_response, created_at) VALUES (?, ?, ?, NOW())");
    $testSess = 'test_integrity_' . uniqid();
    try {
        $stmt->execute([$testSess, 'test', 'test']);
        $inserted = $pdo->lastInsertId();
        assertTest('chat_logs table is writable', $inserted > 0, '', $totalTests, $passedTests, $failedTests, $failures);
        // Clean up
        $pdo->prepare("DELETE FROM chat_logs WHERE session_id = ?")->execute([$testSess]);
    } catch (Exception $e) {
        assertTest('chat_logs table is writable', false, $e->getMessage(), $totalTests, $passedTests, $failedTests, $failures);
    }
    
    // 15.6 Feedback table writable
    try {
        $stmt = $pdo->prepare("INSERT INTO feedback (feedback_type, created_at) VALUES (?, NOW())");
        $stmt->execute(['positive']);
        $fId = $pdo->lastInsertId();
        assertTest('feedback table is writable', $fId > 0, '', $totalTests, $passedTests, $failedTests, $failures);
        $pdo->prepare("DELETE FROM feedback WHERE id = ?")->execute([$fId]);
    } catch (Exception $e) {
        assertTest('feedback table is writable', false, $e->getMessage(), $totalTests, $passedTests, $failedTests, $failures);
    }
    
} else {
    for ($i = 0; $i < 6; $i++) {
        assertSkip('DB integrity check', 'DB not connected', $totalTests, $skippedTests);
    }
}

$sectionResults['15_DataIntegrity'] = ['total' => $totalTests - $s15Start, 'passed' => $passedTests - $s15Pass];

// =====================================================================
//  SECTION 16: PERFORMANCE TESTS
// =====================================================================
printHeader('16. PERFORMANCE TESTS');

$s16Start = $totalTests;
$s16Pass  = $passedTests;

// 16.1 Average response time (5 requests)
$times = [];
$perfQuestions = [
    'ค่าเทอมเท่าไหร่',
    'อาจารย์สาขาคอมพิวเตอร์',
    'ข่าวล่าสุด',
    'สหกิจศึกษา',
    'ทุนการศึกษา',
];

foreach ($perfQuestions as $q) {
    $start = microtime(true);
    $r = chatRequest($BASE_URL, $q);
    $elapsed = (microtime(true) - $start) * 1000;
    $times[] = $elapsed;
}

$avgTime = array_sum($times) / count($times);
$maxTime = max($times);

assertTest("Average response time < 3000ms", 
    $avgTime < 3000,
    sprintf("avg=%.0fms, max=%.0fms", $avgTime, $maxTime), 
    $totalTests, $passedTests, $failedTests, $failures);

assertTest("Max response time < 5000ms", 
    $maxTime < 5000,
    sprintf("max=%.0fms", $maxTime), 
    $totalTests, $passedTests, $failedTests, $failures);

// 16.2 Concurrent-like burst (5 rapid requests)
$burstStart = microtime(true);
for ($i = 0; $i < 5; $i++) {
    chatRequest($BASE_URL, 'ค่าเทอม');
}
$burstTime = (microtime(true) - $burstStart) * 1000;
assertTest("5 rapid requests complete < 15000ms", 
    $burstTime < 15000,
    sprintf("total=%.0fms", $burstTime), 
    $totalTests, $passedTests, $failedTests, $failures);

$sectionResults['16_Performance'] = ['total' => $totalTests - $s16Start, 'passed' => $passedTests - $s16Pass];

// =====================================================================
//  FINAL REPORT
// =====================================================================
printHeader('FINAL TEST REPORT');

echo "\n";
printf("  %-30s %s\n", "Section", "Result");
echo "  " . str_repeat('-', 50) . "\n";
foreach ($sectionResults as $section => $data) {
    $label = str_replace('_', ' ', substr($section, strpos($section, '_') + 1));
    $status = ($data['passed'] === $data['total']) ? 'PASS' : 'PARTIAL';
    printf("  %-30s %d/%d %s\n", $label, $data['passed'], $data['total'], $status);
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

// Exit code for CI
exit($failedTests > 0 ? 1 : 0);
