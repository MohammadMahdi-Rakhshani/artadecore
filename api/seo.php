<?php
/**
 * seo.php — مدیریت تنظیمات SEO
 */
require_once __DIR__ . '/config.php';
$admin  = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();

try {
    // =========== کلمات کلیدی ===========
    if (isset($_GET['keywords']) || (isset($_GET['keyword_id']) && $method === 'DELETE')) {

        if ($method === 'GET') {
            $pageFilter = sanitize($_GET['page_key'] ?? '');
            $where  = '1=1'; $params = [];
            if ($pageFilter) { $where .= ' AND page_key = ?'; $params[] = $pageFilter; }
            $stmt = $db->prepare("SELECT * FROM seo_keywords WHERE $where ORDER BY search_volume DESC");
            $stmt->execute($params);
            respond(['success' => true, 'data' => $stmt->fetchAll()]);
        }

        if ($method === 'POST') {
            $body = getJsonBody();
            if (empty($body['keyword'])) respond(['success' => false, 'message' => 'کلمه کلیدی الزامی است']);
            $stmt = $db->prepare(
                "INSERT INTO seo_keywords (keyword, search_volume, competition, current_rank, page_key)
                 VALUES (?,?,?,?,?)"
            );
            $stmt->execute([
                sanitize($body['keyword']),
                (int)($body['search_volume'] ?? 0),
                (int)($body['competition']   ?? 0),
                isset($body['current_rank']) ? (int)$body['current_rank'] : null,
                sanitize($body['page_key'] ?? 'home'),
            ]);
            respond(['success' => true, 'message' => 'کلمه کلیدی اضافه شد', 'id' => (int)$db->lastInsertId()]);
        }

        if ($method === 'DELETE') {
            $kid = (int)($_GET['keyword_id'] ?? 0);
            if (!$kid) respond(['success' => false, 'message' => 'keyword_id الزامی است']);
            $db->prepare("DELETE FROM seo_keywords WHERE id = ?")->execute([$kid]);
            respond(['success' => true, 'message' => 'کلمه کلیدی حذف شد']);
        }

        respond(['success' => false, 'message' => 'Method not allowed'], 405);
    }

    // =========== تنظیمات صفحه ===========
    switch ($method) {

        case 'GET':
            if (!empty($_GET['page'])) {
                $pageKey = sanitize($_GET['page']);
                $stmt    = $db->prepare("SELECT * FROM seo_settings WHERE page_key = ?");
                $stmt->execute([$pageKey]);
                $row = $stmt->fetch();
                respond(['success' => true, 'data' => $row ?: null]);
            }
            $stmt = $db->query("SELECT * FROM seo_settings ORDER BY page_key ASC");
            respond(['success' => true, 'data' => $stmt->fetchAll()]);
            break;

        case 'PUT':
            $body    = getJsonBody();
            $pageKey = sanitize($body['page_key'] ?? '');
            if (empty($pageKey)) respond(['success' => false, 'message' => 'page_key الزامی است']);

            $fields   = ['meta_title','meta_description','meta_keywords','canonical_url','og_title','og_image'];
            $updates  = []; $params = [];
            foreach ($fields as $f) {
                $updates[] = "$f = ?";
                $params[]  = sanitize($body[$f] ?? '');
            }
            $params[] = $pageKey;

            // بررسی وجود
            $chk = $db->prepare("SELECT id FROM seo_settings WHERE page_key = ?");
            $chk->execute([$pageKey]);
            if ($chk->fetch()) {
                $db->prepare("UPDATE seo_settings SET " . implode(', ', $updates) . " WHERE page_key = ?")->execute($params);
            } else {
                $params2 = [$pageKey];
                foreach ($fields as $f) $params2[] = sanitize($body[$f] ?? '');
                $db->prepare("INSERT INTO seo_settings (page_key," . implode(',', $fields) . ") VALUES (?" . str_repeat(',?', count($fields)) . ")")->execute($params2);
            }
            respond(['success' => true, 'message' => 'تنظیمات SEO ذخیره شد']);
            break;

        default:
            respond(['success' => false, 'message' => 'Method not allowed'], 405);
    }
} catch (Exception $e) {
    error_log("[ArtaDecor] SEO error: " . $e->getMessage());
    respond(['success' => false, 'message' => 'خطای سرور'], 500);
}
