<?php
/**
 * config.php — پیکربندی مرکزی آرتا دکور
 * Vanilla PHP — No framework, no Composer
 */

// =========== تنظیمات دیتابیس ===========
// توصیه: مقادیر را در فایل .env یا php environment variables قرار دهید
// مثال: در cPanel → PHP Selector → Environment Variables یا در .htaccess:
//   SetEnv DB_HOST localhost
//   SetEnv DB_NAME h422656_mahdi
//   SetEnv DB_USER h422656_mahdi
//   SetEnv DB_PASS your_password
define('DB_HOST',    getenv('DB_HOST') ?: 'localhost');
define('DB_NAME',    getenv('DB_NAME') ?: '*******');
define('DB_USER',    getenv('DB_USER') ?: '*******');
define('DB_PASS',    getenv('DB_PASS') ?: '**********');   // حتماً از env var استفاده کنید
define('DB_CHARSET', 'utf8mb4');

// =========== تنظیمات سایت ===========
// اگر SITE_URL در env تنظیم نشده، به صورت خودکار از Host header ساخته می‌شود
if (!defined('SITE_URL')) {
    $envUrl = getenv('SITE_URL');
    if ($envUrl) {
        define('SITE_URL', rtrim($envUrl, '/'));
    } else {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        define('SITE_URL', $scheme . '://' . $host);
    }
}
define('UPLOAD_DIR', __DIR__ . '/uploads_images/');
define('UPLOAD_URL', SITE_URL . '/api/uploads_images/');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
define('SESSION_LIFETIME', 8 * 3600); // 8 ساعت

// =========== تنظیمات Session ===========
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
    session_start();
}

// =========== هدرهای CORS ===========
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
// اگر origin خالی بود (same-origin request) نیازی به CORS header نیست
// اگر origin همان سایت بود مجاز است
if (!empty($origin)) {
    $parsedSite   = parse_url(SITE_URL, PHP_URL_HOST);
    $parsedOrigin = parse_url($origin, PHP_URL_HOST);
    $localHosts   = ['localhost', '127.0.0.1', '::1'];
    if ($parsedOrigin === $parsedSite
        || in_array($parsedOrigin, $localHosts)
        || in_array($parsedSite, $localHosts)
    ) {
        header('Access-Control-Allow-Origin: ' . $origin);
    } else {
        header('Access-Control-Allow-Origin: ' . SITE_URL);
    }
}
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

// پاسخ به Preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// =========== مدیریت خطا ===========
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("[ArtaDecor] Error $errno: $errstr in $errfile:$errline");
    return false;
});
set_exception_handler(function($e) {
    error_log("[ArtaDecor] Exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    respond(['success' => false, 'message' => 'خطای داخلی سرور'], 500);
});

// =========== اتصال PDO Singleton ===========
$_db_instance = null;

function getDB(): PDO {
    global $_db_instance;
    if ($_db_instance !== null) return $_db_instance;
    try {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ];
        $_db_instance = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        error_log("[ArtaDecor] DB Connection failed: " . $e->getMessage());
        respond(['success' => false, 'message' => 'خطا در اتصال به دیتابیس'], 500);
    }
    return $_db_instance;
}

// =========== توابع کمکی ===========

/**
 * ارسال پاسخ JSON
 */
function respond(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * بررسی احراز هویت ادمین
 */
function requireAuth(): array {
    if (empty($_SESSION['admin_id']) || empty($_SESSION['admin_login_time'])) {
        respond(['success' => false, 'message' => 'احراز هویت الزامی است', 'authenticated' => false], 401);
    }
    // بررسی timeout
    if (time() - $_SESSION['admin_login_time'] > SESSION_LIFETIME) {
        session_destroy();
        respond(['success' => false, 'message' => 'نشست منقضی شده — مجدداً وارد شوید', 'authenticated' => false], 401);
    }
    // تجدید زمان session
    $_SESSION['admin_login_time'] = time();
    return [
        'id'   => $_SESSION['admin_id'],
        'name' => $_SESSION['admin_name'] ?? '',
        'role' => $_SESSION['admin_role'] ?? 'admin',
    ];
}

/**
 * پاکسازی ورودی‌ها
 */
function sanitize($input): string {
    if (is_array($input)) return '';
    return htmlspecialchars(trim((string)$input), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * خواندن body درخواست JSON
 */
function getJsonBody(): array {
    $raw = file_get_contents('php://input');
    if (empty($raw)) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/**
 * دریافت IP کاربر
 */
function getClientIp(): string {
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = trim(explode(',', $_SERVER[$key])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// =========== تست اتصال حذف شد — اطلاعات حساس را عمومی نکنید ===========
// برای تست اتصال از CLI استفاده کنید: php -r "require 'config.php'; $db=getDB(); echo 'OK';"
