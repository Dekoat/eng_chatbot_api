<?php
/**
 * Web Scraper for RMUTP Engineering Faculty
 * Auto-sync news and personnel data from official website
 * 
 * ⚠️ Note: ตัวอย่างนี้เป็น Template - ต้องปรับ URL และ selector ให้ตรงกับเว็บจริง
 * สำหรับการทำงานจริง ควรใช้ร่วมกับ Cron Job หรือ Task Scheduler
 */

include "db.php";
header('Content-Type: application/json; charset=utf-8');

// ===== Helper Functions =====
function ensureRequirements() {
    if(!function_exists('curl_init')) {
        throw new Exception('PHP cURL extension ยังไม่เปิดใช้งาน');
    }
}

function httpGet($url) {
    ensureRequirements();
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'EngBot-Scraper/1.0'
    ]);
    $response = curl_exec($ch);
    if(curl_errno($ch)) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new Exception('cURL Error: ' . $err);
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if($status >= 400 || !$response) {
        throw new Exception("HTTP Status $status จาก $url");
    }
    return $response;
}

function cleanText($html) {
    $text = strip_tags($html);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim(preg_replace('/\s+/', ' ', $text));
}

function upsertNews($conn, $title, $summary, $url, $date, $categoryLabel = '') {
    $source = $categoryLabel ? ('scraped:' . $categoryLabel) : 'scraped';
    $check = $conn->prepare("SELECT id FROM news WHERE url = ? LIMIT 1");
    $check->bind_param("s", $url);
    $check->execute();
    $check->store_result();
    if($check->num_rows > 0) {
        $check->bind_result($existingId);
        $check->fetch();
        $update = $conn->prepare("UPDATE news SET title=?, summary=?, date_post=?, source=?, is_active=1 WHERE id=?");
        $update->bind_param("ssssi", $title, $summary, $date, $source, $existingId);
        $update->execute();
        $update->close();
    } else {
        $insert = $conn->prepare("INSERT INTO news (title, summary, url, date_post, source, is_active) VALUES (?, ?, ?, ?, ?, 1)");
        $insert->bind_param("sssss", $title, $summary, $url, $date, $source);
        $insert->execute();
        $insert->close();
    }
    $check->close();
}

/**
 * ดึงข่าวผ่าน WordPress REST API
 */
function fetchCategoryLookup() {
    static $cache = null;
    if($cache !== null) {
        return $cache;
    }

    $cache = ['name' => [], 'slug' => []];
    try {
        $endpoint = 'https://eng.rmutp.ac.th/wp-json/wp/v2/categories?per_page=100&_fields=id,name,slug';
        $json = httpGet($endpoint);
        $cats = json_decode($json, true);
        if(is_array($cats)) {
            foreach($cats as $cat) {
                $id = $cat['id'] ?? null;
                if(!$id) continue;
                if(!empty($cat['name'])) {
                    $cache['name'][mb_strtolower($cat['name'])] = $id;
                }
                if(!empty($cat['slug'])) {
                    $cache['slug'][mb_strtolower($cat['slug'])] = $id;
                }
            }
        }
    } catch(Exception $e) {
        // ถ้าดึงหมวดหมู่ไม่ได้ให้ปล่อยให้ฟังก์ชันหลักเป็นคนแจ้ง error
    }

    return $cache;
}

function resolveCategoryIds(array $candidates) {
    $lookup = fetchCategoryLookup();
    $ids = [];
    foreach($candidates as $candidate) {
        $key = mb_strtolower($candidate);
        if(isset($lookup['name'][$key])) {
            $ids[] = $lookup['name'][$key];
        }
        if(isset($lookup['slug'][$key])) {
            $ids[] = $lookup['slug'][$key];
        }
    }
    return array_values(array_unique(array_filter($ids)));
}

function scrapeNews($conn, $categoryKey = 'all') {
    $configMap = [
        'pr' => [
            'label' => 'ข่าวประชาสัมพันธ์',
            'matches' => ['ข่าวประชาสัมพันธ์', 'public-relations', 'ประชาสัมพันธ์']
        ],
        'activities' => [
            'label' => 'กิจกรรมคณะวิศวกรรมศาสตร์',
            'matches' => ['กิจกรรมคณะวิศวกรรมศาสตร์', 'กิจกรรมคณะ', 'faculty-activities']
        ],
    ];

    if($categoryKey !== 'all' && !isset($configMap[$categoryKey])) {
        throw new Exception('ไม่รู้จักหมวดหมู่ข่าวที่ระบุ');
    }

    $targetConfig = $categoryKey === 'all' ? null : $configMap[$categoryKey];
    $categoryIds = $targetConfig ? resolveCategoryIds($targetConfig['matches']) : [];

    $categoryLabel = $targetConfig['label'] ?? 'ทั้งหมด';
    $result = [
        'items' => 0,
        'errors' => [],
        'warnings' => [],
        'category' => $categoryLabel
    ];

    try {
        $query = [
            'per_page' => 10,
            '_fields' => 'id,date,title.rendered,excerpt.rendered,link,categories'
        ];

        if(!empty($categoryIds)) {
            $query['categories'] = implode(',', $categoryIds);
        } elseif($targetConfig) {
            // ถ้ายังหา id ไม่เจอให้แจ้งเตือน แต่ยังคงดึงข้อมูลทั้งหมดเพื่อลองกรองภายหลัง
            $result['warnings'][] = 'ไม่พบรหัสหมวดหมู่สำหรับ ' . $targetConfig['label'] . ' (ดึงทั้งหมดแล้วกรองภายหลัง)';
        }

        $endpoint = 'https://eng.rmutp.ac.th/wp-json/wp/v2/posts?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        $json = httpGet($endpoint);
        $posts = json_decode($json, true);
        if(!is_array($posts)) {
            throw new Exception('รูปแบบข้อมูลข่าวไม่ถูกต้อง');
        }

        foreach($posts as $post) {
            $postCategories = $post['categories'] ?? [];
            if($targetConfig && !empty($categoryIds)) {
                if(empty(array_intersect($postCategories, $categoryIds))) {
                    continue;
                }
            }

            $title = cleanText($post['title']['rendered'] ?? '');
            $summary = cleanText($post['excerpt']['rendered'] ?? '');
            $link = $post['link'] ?? '';
            $date = isset($post['date']) ? date('Y-m-d', strtotime($post['date'])) : date('Y-m-d');

            if(!$title || !$link) continue;

            upsertNews($conn, $title, $summary, $link, $date, $categoryLabel);
            $result['items']++;
        }
    } catch(Exception $e) {
        $result['errors'][] = $e->getMessage();
    }
    return $result;
}

/**
 * (Template) ดึงข้อมูลบุคลากร - ยังต้องปรับ selector/endpoint ให้ตรงกับเว็บจริง
 */
function scrapeStaff($conn) {
    $result = ['items' => 0, 'errors' => ['ยังไม่ได้กำหนดแหล่งข้อมูลบุคลากร']];
    // TODO: ปรับให้ดึงข้อมูลจาก REST API หรือ HTML ของเว็บจริง
    return $result;
}

// ===== Main Execution =====
$action = $_GET['action'] ?? 'news';
$result = ['success' => false, 'message' => '', 'data' => []];

try {
    switch($action) {
        case 'news':
            $categoryKey = $_GET['category'] ?? 'all';
            $news = scrapeNews($conn, $categoryKey);
            $result['success'] = empty($news['errors']);
            $result['message'] = empty($news['errors'])
                ? "อัปเดตข่าว ({$news['category']}) สำเร็จ {$news['items']} รายการ"
                : 'ดึงข่าวไม่ครบถ้วน';
            $result['data'] = $news;
            break;

        case 'staff':
            $staff = scrapeStaff($conn);
            $result['success'] = empty($staff['errors']);
            $result['message'] = empty($staff['errors']) ? "อัปเดตบุคลากรสำเร็จ {$staff['items']} รายการ" : 'ยังไม่พร้อมดึงข้อมูลบุคลากร';
            $result['data'] = $staff;
            break;

        case 'all':
            $news = scrapeNews($conn);
            $staff = scrapeStaff($conn);
            $success = empty($news['errors']) && empty($staff['errors']);
            $result['success'] = $success;
            $result['message'] = $success ? "อัปเดตข่าว {$news['items']} และบุคลากร {$staff['items']} รายการ" : 'บางส่วนดึงข้อมูลไม่สำเร็จ';
            $result['data'] = ['news' => $news, 'staff' => $staff];
            break;

        default:
            throw new Exception('Invalid action. Use: news, staff, all');
    }
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'data' => []
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

// ===== คำแนะนำการใช้งาน =====
// 1. เรียกใช้ผ่านเว็บ:
//    - http://localhost/eng_chatbot_api/scraper.php?action=news
//    - http://localhost/eng_chatbot_api/scraper.php?action=staff
//    - http://localhost/eng_chatbot_api/scraper.php?action=all
//
// 2. ตั้ง Cron Job (ตัวอย่าง Linux/Mac):
//    - รันทุก 6 ชั่วโมง: 0 */6 * * * php /path/to/scraper.php action=all
//
// 3. ตั้ง Task Scheduler (Windows):
//    - Program: C:\xampp\php\php.exe
//    - Arguments: C:\xampp\htdocs\eng_chatbot_api\scraper.php
//    - Trigger: ทุก 6 ชั่วโมง
//
// 4. เรียกใช้ผ่าน Webhook:
//    - อาจสั่งจาก GitHub Actions, Zapier, หรือ IFTTT
//
// ⚠️ สำคัญ: ต้องปรับ XPath selector ให้ตรงกับโครงสร้าง HTML ของเว็บจริง
?>
