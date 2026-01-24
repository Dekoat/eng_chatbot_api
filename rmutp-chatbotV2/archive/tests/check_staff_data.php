<?php
/**
 * Quick test script to verify staff data encoding
 */

header('Content-Type: text/html; charset=UTF-8');

require_once __DIR__ . '/../backend/db.php';

$db = getDB();

echo "<!DOCTYPE html>\n<html lang='th'>\n<head>\n";
echo "<meta charset='UTF-8'>\n";
echo "<title>ตรวจสอบข้อมูล Staff</title>\n";
echo "<style>body{font-family: 'Sarabun', Arial, sans-serif; padding: 20px;} table{border-collapse:collapse; width:100%;} th,td{border:1px solid #ddd; padding:8px; text-align:left;} th{background:#940032; color:white;}</style>\n";
echo "</head>\n<body>\n";

$sql = "SELECT id, name_th, department, room, office_hours, availability 
        FROM staff 
        WHERE id IN (1,2,15,30,50,70,90,110) 
        ORDER BY id";

$stmt = $db->query($sql);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>✅ ตรวจสอบข้อมูล Staff (UTF-8)</h2>\n";
echo "<p>จำนวน: " . count($results) . " รายการ</p>\n";

echo "<table>\n";
echo "<tr><th>ID</th><th>ชื่อ</th><th>ภาควิชา</th><th>ห้อง</th><th>เวลาทำงาน</th><th>ความพร้อม</th></tr>\n";

foreach ($results as $row) {
    echo "<tr>";
    echo "<td>{$row['id']}</td>";
    echo "<td>{$row['name_th']}</td>";
    echo "<td>{$row['department']}</td>";
    echo "<td>{$row['room']}</td>";
    echo "<td>{$row['office_hours']}</td>";
    echo "<td>{$row['availability']}</td>";
    echo "</tr>\n";
}

echo "</table>\n";

// แสดงสถิติ
$sql2 = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN office_hours IS NOT NULL AND office_hours <> '' THEN 1 ELSE 0 END) as office_hours_ok,
    SUM(CASE WHEN availability IS NOT NULL AND availability <> '' THEN 1 ELSE 0 END) as availability_ok,
    SUM(CASE WHEN room IS NOT NULL AND room <> '' THEN 1 ELSE 0 END) as room_ok
FROM staff";

$stmt2 = $db->query($sql2);
$stats = $stmt2->fetch(PDO::FETCH_ASSOC);

echo "<h3>📊 สถิติ</h3>\n";
echo "<ul>\n";
echo "<li>รวม: {$stats['total']} คน</li>\n";
echo "<li>มี office_hours: {$stats['office_hours_ok']}/{$stats['total']}</li>\n";
echo "<li>มี availability: {$stats['availability_ok']}/{$stats['total']}</li>\n";
echo "<li>มี room: {$stats['room_ok']}/{$stats['total']}</li>\n";
echo "</ul>\n";

echo "</body>\n</html>";
