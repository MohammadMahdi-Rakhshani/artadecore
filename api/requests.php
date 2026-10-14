<?php
/**
 * requests.php — مدیریت درخواست‌های مشاوره (نیاز به auth)
 */
require_once __DIR__ . '/config.php';
$admin = requireAuth();

$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();

// =========== شمارش وضعیت‌ها ===========
function getCounts(PDO $db): array {
    $stmt = $db->query(
        "SELECT status, COUNT(*) as cnt FROM consultation_requests GROUP BY status"
    );
    $rows = $stmt->fetchAll();
    $counts = ['all' => 0, 'new' => 0, 'pending' => 0, 'confirmed' => 0, 'done' => 0, 'cancelled' => 0];
    foreach ($rows as $r) {
        $counts[$r['status']] = (int)$r['cnt'];
        $counts['all'] += (int)$r['cnt'];
    }
    return $counts;
}

try {
    switch ($method) {

        // =========== GET: لیست یا جزئیات ===========
        case 'GET':
            // جزئیات یک درخواست
            if (!empty($_GET['id'])) {
                $id   = (int)$_GET['id'];
                $stmt = $db->prepare("SELECT * FROM consultation_requests WHERE id = ?");
                $stmt->execute([$id]);
                $req  = $stmt->fetch();
                if (!$req) respond(['success' => false, 'message' => 'درخواست یافت نشد'], 404);
                respond(['success' => true, 'data' => $req]);
            }

            // لیست با فیلتر
            $allowed = ['new', 'pending', 'confirmed', 'done', 'cancelled'];
            $status  = $_GET['status'] ?? '';
            $search  = sanitize($_GET['search'] ?? '');
            $page    = max(1, (int)($_GET['page'] ?? 1));
            $limit   = 20;
            $offset  = ($page - 1) * $limit;

            $where  = '1=1';
            $params = [];

            if ($status && in_array($status, $allowed)) {
                $where    .= ' AND status = ?';
                $params[]  = $status;
            }
            if ($search) {
                $where    .= ' AND (name LIKE ? OR phone LIKE ? OR email LIKE ?)';
                $like      = "%$search%";
                $params[]  = $like;
                $params[]  = $like;
                $params[]  = $like;
            }

            $cntStmt = $db->prepare("SELECT COUNT(*) FROM consultation_requests WHERE $where");
            $cntStmt->execute($params);
            $total = (int)$cntStmt->fetchColumn();

            $listStmt = $db->prepare("SELECT * FROM consultation_requests WHERE $where ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
            $listStmt->execute($params);
            $list = $listStmt->fetchAll();

            respond([
                'success' => true,
                'data'    => $list,
                'counts'  => getCounts($db),
                'total'   => $total,
                'page'    => $page,
                'pages'   => ceil($total / $limit),
            ]);
            break;

        // =========== POST: ثبت دستی از ادمین ===========
        case 'POST':
            $body = getJsonBody();
            $name    = sanitize($body['name'] ?? '');
            $phone   = sanitize($body['phone'] ?? '');
            $email   = sanitize($body['email'] ?? '');
            $style   = sanitize($body['style'] ?? '');
            $message = sanitize($body['message'] ?? '');

            if (mb_strlen($name, 'UTF-8') < 2 || empty($phone)) {
                respond(['success' => false, 'message' => 'نام و تلفن الزامی است']);
            }

            $stmt = $db->prepare(
                "INSERT INTO consultation_requests (name, phone, email, style, message, status, ip_address)
                 VALUES (?, ?, ?, ?, ?, 'new', ?)"
            );
            $stmt->execute([$name, $phone, $email, $style, $message, 'admin']);
            respond(['success' => true, 'message' => 'درخواست ثبت شد', 'id' => (int)$db->lastInsertId()]);
            break;

        // =========== PUT: تغییر وضعیت / یادداشت ===========
        case 'PUT':
            $body   = getJsonBody();
            $id     = (int)($body['id'] ?? 0);
            if (!$id) respond(['success' => false, 'message' => 'id الزامی است']);

            $allowed_statuses = ['new', 'pending', 'confirmed', 'done', 'cancelled'];
            $updates = [];
            $params  = [];

            if (isset($body['status'])) {
                if (!in_array($body['status'], $allowed_statuses)) {
                    respond(['success' => false, 'message' => 'وضعیت نامعتبر است']);
                }
                $updates[] = 'status = ?';
                $params[]  = $body['status'];
            }
            if (array_key_exists('admin_note', $body)) {
                $updates[] = 'admin_note = ?';
                $params[]  = sanitize($body['admin_note']);
            }

            if (empty($updates)) respond(['success' => false, 'message' => 'هیچ فیلدی برای به‌روزرسانی ارسال نشد']);

            $params[] = $id;
            $db->prepare("UPDATE consultation_requests SET " . implode(', ', $updates) . " WHERE id = ?")
               ->execute($params);

            respond(['success' => true, 'message' => 'به‌روزرسانی انجام شد', 'counts' => getCounts($db)]);
            break;

        // =========== DELETE ===========
        case 'DELETE':
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) respond(['success' => false, 'message' => 'id الزامی است']);
            $db->prepare("DELETE FROM consultation_requests WHERE id = ?")->execute([$id]);
            respond(['success' => true, 'message' => 'درخواست حذف شد', 'counts' => getCounts($db)]);
            break;

        default:
            respond(['success' => false, 'message' => 'Method not allowed'], 405);
    }
} catch (Exception $e) {
    error_log("[ArtaDecor] Requests error: " . $e->getMessage());
    respond(['success' => false, 'message' => 'خطای سرور'], 500);
}
