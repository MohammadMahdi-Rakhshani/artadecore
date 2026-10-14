<?php
/**
 * dashboard.php — آمار داشبورد ادمین
 */
require_once __DIR__ . '/config.php';
$admin = requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond(['success' => false, 'message' => 'Method not allowed'], 405);
}

try {
    $db = getDB();

    // =========== آمار کلی ===========
    $totalReqs     = (int)$db->query("SELECT COUNT(*) FROM consultation_requests")->fetchColumn();
    $newReqs       = (int)$db->query("SELECT COUNT(*) FROM consultation_requests WHERE status = 'new'")->fetchColumn();
    $confirmedReqs = (int)$db->query("SELECT COUNT(*) FROM consultation_requests WHERE status = 'confirmed'")->fetchColumn();
    $doneReqs      = (int)$db->query("SELECT COUNT(*) FROM consultation_requests WHERE status = 'done'")->fetchColumn();
    $activeStyles  = (int)$db->query("SELECT COUNT(*) FROM decoration_styles WHERE status = 'active'")->fetchColumn();
    $activeViews   = (int)$db->query("SELECT COUNT(*) FROM views_3d WHERE status = 'active'")->fetchColumn();

    // =========== درخواست‌های امروز ===========
    $today = (int)$db->query("SELECT COUNT(*) FROM consultation_requests WHERE DATE(created_at) = CURDATE()")->fetchColumn();

    // =========== این ماه ===========
    $thisMonth = (int)$db->query("SELECT COUNT(*) FROM consultation_requests WHERE YEAR(created_at) = YEAR(NOW()) AND MONTH(created_at) = MONTH(NOW())")->fetchColumn();

    // =========== تعداد بر اساس وضعیت ===========
    $byStatus = [];
    $statusRows = $db->query("SELECT status, COUNT(*) as cnt FROM consultation_requests GROUP BY status")->fetchAll();
    foreach ($statusRows as $r) $byStatus[$r['status']] = (int)$r['cnt'];

    // =========== ۵ درخواست آخر ===========
    $recentRows = $db->query(
        "SELECT id, name, phone, style, status, created_at FROM consultation_requests ORDER BY created_at DESC LIMIT 5"
    )->fetchAll();

    // =========== آمار هفتگی (۷ روز اخیر) ===========
    $weeklyRows = $db->query(
        "SELECT DATE(created_at) as day, COUNT(*) as cnt
         FROM consultation_requests
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
         GROUP BY DATE(created_at)
         ORDER BY day ASC"
    )->fetchAll();

    respond([
        'success' => true,
        'stats' => [
            'total_requests'     => $totalReqs,
            'new_requests'       => $newReqs,
            'confirmed_requests' => $confirmedReqs,
            'done_requests'      => $doneReqs,
            'active_styles'      => $activeStyles,
            'active_views'       => $activeViews,
        ],
        'recent_requests'     => $recentRows,
        'requests_by_status'  => $byStatus,
        'requests_today'      => $today,
        'requests_this_month' => $thisMonth,
        'weekly_chart'        => $weeklyRows,
    ]);
} catch (Exception $e) {
    error_log("[ArtaDecor] Dashboard error: " . $e->getMessage());
    respond(['success' => false, 'message' => 'خطای سرور'], 500);
}
