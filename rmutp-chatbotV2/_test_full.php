<?php
/**
 * Full Test: ทดสอบ FAQ ทั้งหมด + วิเคราะห์ confidence
 */
ini_set('display_errors', 0);
error_reporting(0);

require_once __DIR__ . '/backend/chatbot.php';

$chatbot = new Chatbot();
$pdo = getDB();

// ดึง FAQ ทั้งหมดที่ active
$stmt = $pdo->query("SELECT id, category, question, answer FROM faq WHERE is_active = 1 ORDER BY id");
$allFaq = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total = count($allFaq);

echo "=== FULL TEST: $total FAQs ===\n\n";

$pass = 0;
$fail = 0;
$failures = [];
$lowConfidence = [];
$sessionId = 'test_full_' . time();

foreach ($allFaq as $i => $faq) {
    $faqId = $faq['id'];
    $questions = array_map('trim', explode('/', $faq['question']));
    $testQ = $questions[0]; // ใช้คำถามแรก

    // เรียก chatbot
    $result = $chatbot->handleChat($sessionId, $testQ);
    $answer = $result['answer'] ?? '';
    $sources = $result['sources'] ?? [];
    $confidence = $result['confidence'] ?? 0;

    // ตรวจสอบว่าตอบ FAQ ถูกตัว
    $matched = false;
    foreach ($sources as $s) {
        if (isset($s['id']) && $s['id'] == $faqId) {
            $matched = true;
            break;
        }
    }
    // fallback: ถ้าไม่ match จาก sources ให้เช็คเนื้อหา
    if (!$matched) {
        $faqAnswer = mb_substr($faq['answer'], 0, 60);
        if (mb_stripos($answer, $faqAnswer) !== false) {
            $matched = true;
        }
    }

    if ($matched) {
        $pass++;
        // เก็บ low confidence (ต่ำกว่า 60%)
        if ($confidence > 0 && $confidence < 60) {
            $lowConfidence[] = [
                'id' => $faqId,
                'question' => $testQ,
                'confidence' => $confidence,
                'category' => $faq['category'],
            ];
        }
    } else {
        $fail++;
        $failures[] = [
            'id' => $faqId,
            'question' => $testQ,
            'expected_cat' => $faq['category'],
            'got_answer' => mb_substr($answer, 0, 120),
            'got_sources' => array_map(function($s) { return ($s['id'] ?? '?') . ':' . ($s['category'] ?? '?'); }, $sources),
            'confidence' => $confidence,
        ];
    }

    // Progress
    $done = $i + 1;
    if ($done % 50 == 0) {
        echo "  ... $done/$total\n";
    }
}

echo "\n=== RESULT: $pass/$total PASS (" . round($pass/$total*100, 1) . "%) ===\n";
echo "FAIL: $fail\n\n";

if (!empty($failures)) {
    echo "=== FAILURES ($fail) ===\n";
    foreach ($failures as $f) {
        echo "  #" . $f['id'] . " [" . $f['expected_cat'] . "]\n";
        echo "    Q: " . $f['question'] . "\n";
        echo "    Got: " . $f['got_answer'] . "\n";
        echo "    Sources: " . implode(', ', $f['got_sources']) . "\n";
        echo "    Confidence: " . $f['confidence'] . "%\n\n";
    }
}

// เรียงตาม confidence ต่ำสุด
usort($lowConfidence, fn($a, $b) => $a['confidence'] <=> $b['confidence']);
$showLow = array_slice($lowConfidence, 0, 20);
if (!empty($showLow)) {
    echo "=== LOW CONFIDENCE (< 60%) - Top 20 ===\n";
    foreach ($showLow as $lc) {
        echo "  #" . $lc['id'] . " (" . $lc['confidence'] . "%) [" . $lc['category'] . "] " . $lc['question'] . "\n";
    }
}

echo "\nDone.\n";
