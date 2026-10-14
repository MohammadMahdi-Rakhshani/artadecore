<?php
/**
 * views3d.php — مدیریت نماهای ۳D و نقشه‌های مهندسی
 */
require_once __DIR__ . '/config.php';

$method   = $_SERVER['REQUEST_METHOD'];
$db       = getDB();
$isPublic = isset($_GET['public']) && $_GET['public'] == '1';

// endpoint عمومی
if ($isPublic && $method === 'GET') {
    try {
        $type  = in_array($_GET['type'] ?? '', ['3d','2d']) ? $_GET['type'] : null;
        $where = "status = 'active'" . ($type ? " AND type = '$type'" : '');
        $stmt  = $db->query("SELECT * FROM views_3d WHERE $where ORDER BY id DESC");
        respond(['success' => true, 'data' => $stmt->fetchAll()]);
    } catch (Exception $e) {
        respond(['success' => false, 'message' => 'خطای سرور'], 500);
    }
    exit;
}

$admin = requireAuth();

try {
    switch ($method) {

        case 'GET':
            if (!empty($_GET['id'])) {
                $stmt = $db->prepare("SELECT * FROM views_3d WHERE id = ?");
                $stmt->execute([(int)$_GET['id']]);
                $row = $stmt->fetch();
                if (!$row) respond(['success' => false, 'message' => 'نما یافت نشد'], 404);
                respond(['success' => true, 'data' => $row]);
            }
            $type  = in_array($_GET['type'] ?? '', ['3d','2d']) ? $_GET['type'] : null;
            $where = '1=1';
            $params = [];
            if ($type) { $where .= ' AND type = ?'; $params[] = $type; }
            $stmt = $db->prepare("SELECT * FROM views_3d WHERE $where ORDER BY id DESC");
            $stmt->execute($params);
            respond(['success' => true, 'data' => $stmt->fetchAll()]);
            break;

        case 'POST':
            $body = getJsonBody();
            if (empty($body['name']) || empty($body['type'])) {
                respond(['success' => false, 'message' => 'نام و نوع الزامی است']);
            }
            if (!in_array($body['type'], ['3d','2d'])) {
                respond(['success' => false, 'message' => "نوع باید '3d' یا '2d' باشد"]);
            }
            $stmt = $db->prepare(
                "INSERT INTO views_3d (name, type, style_key, room, description, img_url, dimensions, status)
                 VALUES (?,?,?,?,?,?,?,?)"
            );
            $stmt->execute([
                sanitize($body['name']),
                $body['type'],
                sanitize($body['style_key']   ?? ''),
                sanitize($body['room']        ?? ''),
                sanitize($body['description'] ?? ''),
                sanitize($body['img_url']     ?? ''),
                sanitize($body['dimensions']  ?? ''),
                in_array($body['status'] ?? 'active', ['active','inactive']) ? $body['status'] : 'active',
            ]);
            respond(['success' => true, 'message' => 'نما اضافه شد', 'id' => (int)$db->lastInsertId()]);
            break;

        case 'PUT':
            $body = getJsonBody();
            $id   = (int)($body['id'] ?? 0);
            if (!$id) respond(['success' => false, 'message' => 'id الزامی است']);

            $fields  = ['name','type','style_key','room','description','img_url','dimensions','status'];
            $updates = []; $params = [];
            foreach ($fields as $f) {
                if (array_key_exists($f, $body)) {
                    if ($f === 'type' && !in_array($body[$f], ['3d','2d'])) continue;
                    if ($f === 'status' && !in_array($body[$f], ['active','inactive'])) continue;
                    $updates[] = "$f = ?";
                    $params[]  = sanitize($body[$f]);
                }
            }
            if (empty($updates)) respond(['success' => false, 'message' => 'هیچ فیلدی ارسال نشد']);
            $params[] = $id;
            $db->prepare("UPDATE views_3d SET " . implode(', ', $updates) . " WHERE id = ?")->execute($params);
            respond(['success' => true, 'message' => 'نما به‌روز شد']);
            break;

        case 'DELETE':
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) respond(['success' => false, 'message' => 'id الزامی است']);
            $db->prepare("DELETE FROM views_3d WHERE id = ?")->execute([$id]);
            respond(['success' => true, 'message' => 'نما حذف شد']);
            break;

        default:
            respond(['success' => false, 'message' => 'Method not allowed'], 405);
    }
} catch (Exception $e) {
    error_log("[ArtaDecor] Views3D error: " . $e->getMessage());
    respond(['success' => false, 'message' => 'خطای سرور'], 500);
}
