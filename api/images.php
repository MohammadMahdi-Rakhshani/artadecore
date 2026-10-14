<?php
/**
 * images.php — مدیریت تصاویر سایت
 * GET ?public=1 بدون احراز هویت
 */
require_once __DIR__ . '/config.php';

$method   = $_SERVER['REQUEST_METHOD'];
$db       = getDB();
$isPublic = isset($_GET['public']) && $_GET['public'] == '1';

// endpoint عمومی برای سایت اصلی
if ($isPublic && $method === 'GET') {
    try {
        $stmt   = $db->query("SELECT image_key, image_url, alt_text FROM site_images");
        $rows   = $stmt->fetchAll();
        $images = [];
        foreach ($rows as $r) $images[$r['image_key']] = $r['image_url'];
        respond(['success' => true, 'images' => $images]);
    } catch (Exception $e) {
        respond(['success' => false, 'message' => 'خطای سرور'], 500);
    }
    exit;
}

$admin = requireAuth();

try {
    switch ($method) {

        case 'GET':
            if (!empty($_GET['key'])) {
                $stmt = $db->prepare("SELECT * FROM site_images WHERE image_key = ?");
                $stmt->execute([sanitize($_GET['key'])]);
                $row  = $stmt->fetch();
                respond(['success' => true, 'data' => $row ?: null]);
            }
            $stmt = $db->query("SELECT * FROM site_images ORDER BY image_key ASC");
            respond(['success' => true, 'data' => $stmt->fetchAll()]);
            break;

        case 'PUT':
            $body = getJsonBody();
            $key  = sanitize($body['image_key'] ?? '');
            $url  = sanitize($body['image_url'] ?? '');
            $alt  = sanitize($body['alt_text']  ?? '');

            if (empty($key) || empty($url)) {
                respond(['success' => false, 'message' => 'image_key و image_url الزامی است']);
            }

            // بررسی وجود
            $chk = $db->prepare("SELECT id FROM site_images WHERE image_key = ?");
            $chk->execute([$key]);
            if ($chk->fetch()) {
                $db->prepare("UPDATE site_images SET image_url = ?, alt_text = ? WHERE image_key = ?")
                   ->execute([$url, $alt, $key]);
            } else {
                $db->prepare("INSERT INTO site_images (image_key, image_url, alt_text) VALUES (?,?,?)")
                   ->execute([$key, $url, $alt]);
            }
            respond(['success' => true, 'message' => 'تصویر ذخیره شد']);
            break;

        case 'DELETE':
            $key = sanitize($_GET['key'] ?? '');
            if (empty($key)) respond(['success' => false, 'message' => 'key الزامی است']);

            // اگه batch=1 بود همه رو حذف کن
            if (isset($_GET['batch']) && $_GET['batch'] == '1') {
                $db->exec("DELETE FROM site_images");
                respond(['success' => true, 'message' => 'همه تصاویر به پیش‌فرض برگشتند']);
            }

            $db->prepare("DELETE FROM site_images WHERE image_key = ?")->execute([$key]);
            respond(['success' => true, 'message' => 'تصویر به پیش‌فرض بازگشت']);
            break;

        default:
            respond(['success' => false, 'message' => 'Method not allowed'], 405);
    }
} catch (Exception $e) {
    error_log("[ArtaDecor] Images error: " . $e->getMessage());
    respond(['success' => false, 'message' => 'خطای سرور'], 500);
}
