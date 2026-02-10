<?php
/**
 * Scrape News from eng.rmutp.ac.th via WordPress REST API
 * ดึงข่าวจากเว็บคณะวิศวกรรมศาสตร์ มทร.พระนคร
 */

header('Content-Type: application/json; charset=utf-8');

// Database connection
require_once __DIR__ . '/../backend/db.php';
$pdo = getDB();

$baseUrl = 'https://eng.rmutp.ac.th/wp-json/wp/v2';
$perPage = 30; // จำนวนข่าวที่ดึงต่อครั้ง

// WP Category ID → DB category mapping
// cat 7 = กิจกรรมคณะวิศวกรรมศาสตร์, cat 1 = ข่าวประชาสัมพันธ์, อื่นๆ = ข่าวประชาสัมพันธ์
$categoryMap = [
    7 => 'กิจกรรม',
    1 => 'ข่าวประชาสัมพันธ์',
];

$inserted = 0;
$skipped = 0;
$errors = [];

try {
    // ดึง posts จาก WordPress REST API
    $apiUrl = "{$baseUrl}/posts?per_page={$perPage}&orderby=date&order=desc&_embed";
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 30,
            'user_agent' => 'RMUTP-Chatbot/1.0',
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ]
    ]);
    
    $response = @file_get_contents($apiUrl, false, $context);
    
    if ($response === false) {
        throw new Exception('ไม่สามารถเชื่อมต่อ API ได้: ' . ($http_response_header[0] ?? 'Connection failed'));
    }
    
    $posts = json_decode($response, true);
    
    if (!is_array($posts)) {
        throw new Exception('ข้อมูลที่ได้ไม่ถูกต้อง');
    }
    
    // เตรียม statement สำหรับเช็ค duplicate
    $checkStmt = $pdo->prepare("SELECT id FROM news WHERE link_url = ? LIMIT 1");
    
    // เตรียม statement สำหรับ insert
    $insertStmt = $pdo->prepare(
        "INSERT INTO news (title, content, summary, category, thumbnail_url, link_url, 
                          published_date, department, tags, is_active, is_published) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1)"
    );
    
    foreach ($posts as $post) {
        try {
            $linkUrl = $post['link'] ?? '';
            
            // เช็ค duplicate จาก link_url
            $checkStmt->execute([$linkUrl]);
            if ($checkStmt->fetch()) {
                $skipped++;
                continue;
            }
            
            // ดึง title (ลบ HTML tags)
            $title = html_entity_decode(strip_tags($post['title']['rendered'] ?? ''), ENT_QUOTES, 'UTF-8');
            
            // ดึง content (ลบ HTML tags แล้วตัดสั้น)
            $rawContent = $post['content']['rendered'] ?? '';
            $content = trim(strip_tags($rawContent));
            // ลบ whitespace ซ้ำ
            $content = preg_replace('/\s+/', ' ', $content);
            
            // สร้าง summary จาก excerpt หรือตัดจาก content
            $summary = '';
            if (!empty($post['excerpt']['rendered'])) {
                $summary = trim(strip_tags($post['excerpt']['rendered']));
                $summary = preg_replace('/\s+/', ' ', $summary);
            }
            if (empty($summary) && !empty($content)) {
                $summary = mb_substr($content, 0, 200, 'UTF-8');
                if (mb_strlen($content, 'UTF-8') > 200) {
                    $summary .= '...';
                }
            }
            
            // แปลง WP categories → DB category
            $wpCategories = $post['categories'] ?? [];
            $category = 'ข่าวประชาสัมพันธ์'; // default
            foreach ($wpCategories as $catId) {
                if (isset($categoryMap[$catId])) {
                    $category = $categoryMap[$catId];
                    break;
                }
            }
            
            // ดึง thumbnail
            $thumbnailUrl = '';
            if (!empty($post['_embedded']['wp:featuredmedia'][0]['source_url'])) {
                $thumbnailUrl = $post['_embedded']['wp:featuredmedia'][0]['source_url'];
            }
            // ถ้าไม่มี featured media ลองดึงจาก content
            if (empty($thumbnailUrl) && preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $rawContent, $imgMatch)) {
                $thumbnailUrl = $imgMatch[1];
            }
            
            // วันที่เผยแพร่
            $publishedDate = null;
            if (!empty($post['date'])) {
                $publishedDate = date('Y-m-d', strtotime($post['date']));
            }
            
            // Department
            $department = 'คณะวิศวกรรมศาสตร์';
            
            // Tags
            $tags = '';
            
            // Insert
            $insertStmt->execute([
                $title,
                $content,
                $summary,
                $category,
                $thumbnailUrl,
                $linkUrl,
                $publishedDate,
                $department,
                $tags
            ]);
            
            $inserted++;
            
        } catch (Exception $e) {
            $errors[] = "Post {$post['id']}: " . $e->getMessage();
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => "ดึงข่าวเสร็จสิ้น",
        'inserted' => $inserted,
        'skipped' => $skipped,
        'total_fetched' => count($posts),
        'errors' => $errors
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
