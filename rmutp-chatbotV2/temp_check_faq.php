<?php
require_once __DIR__ . '/backend/db.php';

$db = getDB();

// ค้นหา FAQ ที่เกี่ยวกับไฟฟ้า
$stmt = $db->prepare("SELECT id, question, category FROM faq WHERE question LIKE ? OR answer LIKE ? LIMIT 20");
$search = '%ไฟฟ้า%';
$stmt->execute([$search, $search]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== FAQ ที่เกี่ยวกับ 'ไฟฟ้า' ===\n\n";

if (count($results) > 0) {
    foreach ($results as $row) {
        echo "ID: {$row['id']}\n";
        echo "Question: {$row['question']}\n";
        echo "Category: {$row['category']}\n";
        echo "---\n";
    }
    echo "\nรวม: " . count($results) . " รายการ\n";
} else {
    echo "❌ ไม่มี FAQ เกี่ยวกับ 'ไฟฟ้า' ในฐานข้อมูล\n";
    echo "\n💡 แนะนำ: ควรเพิ่ม FAQ เกี่ยวกับวิศวะไฟฟ้า\n";
}
