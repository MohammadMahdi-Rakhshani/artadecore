<?php
/**
 * auth.php — احراز هویت ادمین آرتا دکور
 */
require_once __DIR__ . '/config.php';

$action = $_GET['action'] ?? '';

switch ($action) {

    // =========== لاگین ===========
    case 'login':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            respond(['success' => false, 'message' => 'Method not allowed'], 405);
        }
        $body = getJsonBody();
        $username = sanitize($body['username'] ?? '');
        $password = $body['password'] ?? '';

        if (empty($username) || empty($password)) {
            respond(['success' => false, 'message' => 'نام کاربری و رمز عبور الزامی است']);
        }

        // بررسی تعداد تلاش‌های ناموفق (rate limiting با session)
        $lockKey = 'login_attempts_' . md5(getClientIp());
        $attempts = $_SESSION[$lockKey] ?? ['count' => 0, 'last' => 0];

        if ($attempts['count'] >= 5 && (time() - $attempts['last']) < 900) {
            $remaining = 900 - (time() - $attempts['last']);
            respond(['success' => false, 'message' => "حساب قفل شده — " . ceil($remaining / 60) . " دقیقه دیگر تلاش کنید"]);
        }
        // ریست بعد از ۱۵ دقیقه
        if ((time() - $attempts['last']) >= 900) {
            $attempts = ['count' => 0, 'last' => 0];
        }

        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT id, username, password_hash, name, role FROM admins WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();

            if (!$admin || !password_verify($password, $admin['password_hash'])) {
                // افزایش تعداد تلاش
                $attempts['count']++;
                $attempts['last'] = time();
                $_SESSION[$lockKey] = $attempts;
                respond(['success' => false, 'message' => 'نام کاربری یا رمز عبور اشتباه است']);
            }

            // ریست تلاش‌های ناموفق
            unset($_SESSION[$lockKey]);

            // ست کردن session
            session_regenerate_id(true);
            $_SESSION['admin_id']         = $admin['id'];
            $_SESSION['admin_name']        = $admin['name'];
            $_SESSION['admin_role']        = $admin['role'];
            $_SESSION['admin_login_time']  = time();

            // ثبت آخرین ورود
            $db->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?")->execute([$admin['id']]);

            respond([
                'success' => true,
                'admin'   => [
                    'id'   => $admin['id'],
                    'name' => $admin['name'],
                    'role' => $admin['role'],
                ]
            ]);
        } catch (Exception $e) {
            error_log("[ArtaDecor] Login error: " . $e->getMessage());
            respond(['success' => false, 'message' => 'خطای سرور — لطفاً دوباره تلاش کنید'], 500);
        }
        break;

    // =========== لاگ‌اوت ===========
    case 'logout':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            respond(['success' => false, 'message' => 'Method not allowed'], 405);
        }
        session_unset();
        session_destroy();
        respond(['success' => true, 'message' => 'خروج موفق']);
        break;

    // =========== بررسی session ===========
    case 'check':
        if (!empty($_SESSION['admin_id']) && !empty($_SESSION['admin_login_time'])) {
            if (time() - $_SESSION['admin_login_time'] <= SESSION_LIFETIME) {
                $_SESSION['admin_login_time'] = time();
                respond([
                    'authenticated' => true,
                    'admin' => [
                        'id'   => $_SESSION['admin_id'],
                        'name' => $_SESSION['admin_name'] ?? '',
                        'role' => $_SESSION['admin_role'] ?? 'admin',
                    ]
                ]);
            } else {
                session_destroy();
            }
        }
        respond(['authenticated' => false]);
        break;

    // =========== تغییر رمز عبور ===========
    case 'change_password':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            respond(['success' => false, 'message' => 'Method not allowed'], 405);
        }
        $admin = requireAuth();
        $body  = getJsonBody();
        $currentPass = $body['current_password'] ?? '';
        $newPass      = $body['new_password'] ?? '';

        if (empty($currentPass) || empty($newPass)) {
            respond(['success' => false, 'message' => 'رمز فعلی و رمز جدید الزامی است']);
        }
        if (strlen($newPass) < 8) {
            respond(['success' => false, 'message' => 'رمز جدید باید حداقل ۸ کاراکتر باشد']);
        }

        try {
            $db   = getDB();
            $stmt = $db->prepare("SELECT password_hash FROM admins WHERE id = ?");
            $stmt->execute([$admin['id']]);
            $row  = $stmt->fetch();

            if (!$row || !password_verify($currentPass, $row['password_hash'])) {
                respond(['success' => false, 'message' => 'رمز فعلی اشتباه است']);
            }

            $newHash = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 12]);
            $db->prepare("UPDATE admins SET password_hash = ? WHERE id = ?")->execute([$newHash, $admin['id']]);

            respond(['success' => true, 'message' => 'رمز عبور با موفقیت تغییر یافت']);
        } catch (Exception $e) {
            error_log("[ArtaDecor] Change password error: " . $e->getMessage());
            respond(['success' => false, 'message' => 'خطای سرور'], 500);
        }
        break;

    default:
        respond(['success' => false, 'message' => 'action نامعتبر است'], 400);
}
