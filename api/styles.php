<?php
/**
 * styles.php — مدیریت سبک‌های دکوراسیون
 * GET ?public=1 بدون احراز هویت — بقیه نیاز به auth دارند
 */
require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();
$isPublic = isset($_GET['public']) && $_GET['public'] == '1';

// endpoint عمومی برای سایت اصلی
if ($isPublic && $method === 'GET') {
    try {
        $stmt = $db->query("SELECT * FROM decoration_styles WHERE status = 'active' ORDER BY sort_order ASC, id ASC");
        respond(['success' => true, 'data' => $stmt->fetchAll()]);
    } catch (Exception $e) {
        respond(['success' => false, 'message' => 'خطای سرور'], 500);
    }
    exit;
}

// همه endpoint‌های دیگر نیاز به auth دارند
$admin = requireAuth();

try {
    switch ($method) {

        // =========== GET ===========
        case 'GET':
            if (!empty($_GET['id'])) {
                $stmt = $db->prepare("SELECT * FROM decoration_styles WHERE id = ?");
                $stmt->execute([(int)$_GET['id']]);
                $row = $stmt->fetch();
                if (!$row) respond(['success' => false, 'message' => 'سبک یافت نشد'], 404);
                respond(['success' => true, 'data' => $row]);
            }
            $stmt = $db->query("SELECT * FROM decoration_styles ORDER BY sort_order ASC, id ASC");
            respond(['success' => true, 'data' => $stmt->fetchAll()]);
            break;

        // =========== POST: افزودن سبک ===========
        case 'POST':
            $body = getJsonBody();
            $required = ['style_key', 'name_fa', 'name_en'];
            foreach ($required as $field) {
                if (empty($body[$field])) {
                    respond(['success' => false, 'message' => "فیلد $field الزامی است"]);
                }
            }

            $style_key = sanitize($body['style_key']);
            // بررسی تکراری نبودن style_key
            $ck = $db->prepare("SELECT id FROM decoration_styles WHERE style_key = ?");
            $ck->execute([$style_key]);
            if ($ck->fetch()) respond(['success' => false, 'message' => 'این کلید سبک قبلاً ثبت شده است']);

            $stmt = $db->prepare(
                "INSERT INTO decoration_styles
                 (style_key, name_fa, name_en, tag_fa, tag_en, description_fa, description_en,
                  materials_fa, materials_en, emoji,
                  img1_url, img2_url, img3_url, img4_url, img5_url, img6_url,
                  status, sort_order)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
            );
            $stmt->execute([
                $style_key,
                sanitize($body['name_fa']),
                sanitize($body['name_en']),
                sanitize($body['tag_fa']  ?? ''),
                sanitize($body['tag_en']  ?? ''),
                sanitize($body['description_fa'] ?? ''),
                sanitize($body['description_en'] ?? ''),
                sanitize($body['materials_fa']   ?? ''),
                sanitize($body['materials_en']   ?? ''),
                sanitize($body['emoji'] ?? '🏠'),
                sanitize($body['img1_url'] ?? ''),
                sanitize($body['img2_url'] ?? ''),
                sanitize($body['img3_url'] ?? ''),
                sanitize($body['img4_url'] ?? ''),
                sanitize($body['img5_url'] ?? ''),
                sanitize($body['img6_url'] ?? ''),
                in_array($body['status'] ?? 'active', ['active','inactive']) ? $body['status'] : 'active',
                (int)($body['sort_order'] ?? 0),
            ]);
            respond(['success' => true, 'message' => 'سبک اضافه شد', 'id' => (int)$db->lastInsertId()]);
            break;

        // =========== PUT: ویرایش ===========
        case 'PUT':
            $body = getJsonBody();
            $id   = (int)($body['id'] ?? 0);
            if (!$id) respond(['success' => false, 'message' => 'id الزامی است']);

            // بررسی وجود
            $chk = $db->prepare("SELECT id FROM decoration_styles WHERE id = ?");
            $chk->execute([$id]);
            if (!$chk->fetch()) respond(['success' => false, 'message' => 'سبک یافت نشد'], 404);

            $fields = ['name_fa','name_en','tag_fa','tag_en','description_fa','description_en',
                       'materials_fa','materials_en','emoji',
                       'img1_url','img2_url','img3_url','img4_url','img5_url','img6_url',
                       'status','sort_order'];
            $updates = [];
            $params  = [];

            foreach ($fields as $f) {
                if (array_key_exists($f, $body)) {
                    if ($f === 'status' && !in_array($body[$f], ['active','inactive'])) continue;
                    if ($f === 'sort_order') { $updates[] = "$f = ?"; $params[] = (int)$body[$f]; }
                    else { $updates[] = "$f = ?"; $params[] = sanitize($body[$f]); }
                }
            }

            if (empty($updates)) respond(['success' => false, 'message' => 'هیچ فیلدی برای به‌روزرسانی ارسال نشد']);

            $params[] = $id;
            $db->prepare("UPDATE decoration_styles SET " . implode(', ', $updates) . " WHERE id = ?")->execute($params);
            respond(['success' => true, 'message' => 'سبک به‌روز شد']);
            break;

        // =========== DELETE ===========
        case 'DELETE':
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) respond(['success' => false, 'message' => 'id الزامی است']);
            $db->prepare("DELETE FROM decoration_styles WHERE id = ?")->execute([$id]);
            respond(['success' => true, 'message' => 'سبک حذف شد']);
            break;

        default:
            respond(['success' => false, 'message' => 'Method not allowed'], 405);
    }
} catch (Exception $e) {
    error_log("[ArtaDecor] Styles error: " . $e->getMessage());
    respond(['success' => false, 'message' => 'خطای سرور'], 500);
}
