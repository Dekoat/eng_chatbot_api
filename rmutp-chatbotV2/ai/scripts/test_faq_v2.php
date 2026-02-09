<?php
/**
 * Full FAQ Test Script v2
 * ทดสอบ FAQ ทั้งหมด - ผลลัพธ์เขียนลง faq_test_result.txt
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 0);
ini_set('memory_limit', '512M');

// ตั้งค่า UTF-8
mb_internal_encoding('UTF-8');
mb_regex_encoding('UTF-8');

$outputFile = __DIR__ . '/faq_test_result.txt';
$outFp = fopen($outputFile, 'w');
// เขียน UTF-8 BOM เพื่อให้ Windows อ่านภาษาไทยได้
fwrite($outFp, "\xEF\xBB\xBF");
function out($msg) { global $outFp; fwrite($outFp, $msg); }

require_once __DIR__ . '/../../backend/db.php';
require_once __DIR__ . '/../../backend/ChatbotConfig.php';
require_once __DIR__ . '/../../backend/QueryAnalyzer.php';
require_once __DIR__ . '/../../backend/chatbot.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    out("DB Error: " . $e->getMessage() . "\n");
    fclose($outFp);
    exit(1);
}

$stmt = $db->query("SELECT id, question, answer, category FROM faq WHERE is_active = 1 ORDER BY id");
$faqs = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total = count($faqs);

out("======================================================================\n");
out("FULL FAQ TEST - $total FAQ items\n");
out("======================================================================\n\n");

$pass = 0;
$fail = 0;
$failed_list = [];
$category_stats = [];

// Create chatbot instance ONCE (not per FAQ)
$chatbot = new Chatbot();

foreach ($faqs as $i => $faq) {
    $faq_id = $faq['id'];
    $question = $faq['question'];
    $expected_answer = $faq['answer'];
    $category = $faq['category'];

    if (!isset($category_stats[$category])) {
        $category_stats[$category] = ['pass' => 0, 'fail' => 0, 'total' => 0];
    }
    $category_stats[$category]['total']++;

    try {
        ob_start();
        $result = $chatbot->handleChat('test_' . $faq_id, $question);
        ob_end_clean();

        $answer_clean = strip_tags($result['answer'] ?? '');
        $expected_clean = strip_tags($expected_answer);
        $is_pass = false;

        // Method 1: First 50 chars match
        $exp50 = mb_substr($expected_clean, 0, 50);
        if (mb_strpos($answer_clean, $exp50) !== false) $is_pass = true;

        // Method 2: FAQ ID in sources
        if (!$is_pass && isset($result['sources'])) {
            foreach ($result['sources'] as $src) {
                if (isset($src['id']) && $src['id'] == $faq_id) { $is_pass = true; break; }
            }
        }

        // Method 3: First 80 chars exact match
        if (!$is_pass && mb_substr($answer_clean, 0, 80) === mb_substr($expected_clean, 0, 80)) {
            $is_pass = true;
        }

        if ($is_pass) {
            $pass++;
            $category_stats[$category]['pass']++;
        } else {
            $fail++;
            $category_stats[$category]['fail']++;
            $failed_list[] = [
                'id' => $faq_id, 'category' => $category,
                'question' => mb_substr($question, 0, 60),
                'confidence' => $result['confidence'] ?? 0,
                'got' => mb_substr($answer_clean, 0, 80),
                'expected' => mb_substr($expected_clean, 0, 80),
            ];
            out("  [FAIL] FAQ#$faq_id ($category): " . mb_substr($question, 0, 50) . "\n");
        }
    } catch (Exception $e) {
        if (ob_get_level() > 0) ob_end_clean();
        $fail++;
        $category_stats[$category]['fail']++;
        $failed_list[] = [
            'id' => $faq_id, 'category' => $category,
            'question' => mb_substr($question, 0, 60),
            'confidence' => 0,
            'got' => 'ERROR: ' . $e->getMessage(),
            'expected' => mb_substr(strip_tags($expected_answer), 0, 80),
        ];
        out("  [ERROR] FAQ#$faq_id: " . $e->getMessage() . "\n");
    }

    if (($i + 1) % 100 === 0) echo "  ... tested " . ($i + 1) . "/$total\n";
}

out("\n" . str_repeat("=", 70) . "\n");
$pct = round($pass / $total * 100, 1);
out("RESULTS: $pass/$total ($pct%)\n");
out(str_repeat("=", 70) . "\n");
if ($fail === 0) out("\n  >>> ALL $total FAQ PASSED! <<<\n");

out("\nCategory Breakdown:\n" . str_repeat("-", 50) . "\n");
ksort($category_stats);
foreach ($category_stats as $cat => $st) {
    $cp = $st['total'] > 0 ? round($st['pass'] / $st['total'] * 100, 1) : 0;
    $s = $st['fail'] == 0 ? 'OK' : 'FAIL';
    out(sprintf("  %-20s: %3d/%3d (%5.1f%%) [%s]\n", $cat, $st['pass'], $st['total'], $cp, $s));
}

if (!empty($failed_list)) {
    out("\nFailed Details (" . count($failed_list) . "):\n" . str_repeat("-", 70) . "\n");
    foreach ($failed_list as $f) {
        out("  FAQ#{$f['id']} ({$f['category']}): {$f['question']}\n");
        out("    Expected: {$f['expected']}\n");
        out("    Got: {$f['got']}\n\n");
    }
}

out("\nCompleted at " . date('Y-m-d H:i:s') . "\n");
fclose($outFp);
echo "\nDone! $pass/$total ($pct%) - see faq_test_result.txt\n";
