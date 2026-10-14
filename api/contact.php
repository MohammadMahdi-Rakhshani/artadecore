<?php
/**
 * contact.php — فرم تماس سایت اصلی (بدون احراز هویت)
 */
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['success' => false, 'message' => 'Method not allowed'], 405);
}

$body   = getJsonBody();
$errors = [];

// =========== اعتبارسنجی ===========
$name    = sanitize($body['name'] ?? '');
$phone   = trim($body['phone'] ?? '');
$email   = sanitize($body['email'] ?? '');
$style   = sanitize($body['style'] ?? '');
$message = sanitize($body['message'] ?? '');

if (mb_strlen($name, 'UTF-8') < 2) {
    $errors[] = 'نام باید حداقل ۲ کاراکتر باشد';
}

// اعتبارسنجی شماره تلفن — موبایل، ثابت ایران، یا بین‌المللی
$phone = preg_replace('/[\s\-\(\)]+/', '', $phone); // حذف فاصله، خط تیره، پرانتز
if (mb_strlen($phone, 'UTF-8') < 7 || mb_strlen($phone, 'UTF-8') > 20) {
    $errors[] = 'شماره تلفن معتبر نیست';
}

if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'آدرس ایمیل معتبر نیست';
}

$allowed_styles = ['european', 'american', 'eastasian', 'arabic', 'iranian', ''];
if (!in_array($style, $allowed_styles)) {
    $style = '';
}

if (!empty($errors)) {
    respond(['success' => false, 'message' => implode('، ', $errors), 'errors' => $errors]);
}

// =========== محدودیت spam (۳ درخواست در ۱ ساعت از هر IP) ===========
$ip = getClientIp();

try {
    $db = getDB();

    $spamCheck = $db->prepare(
        "SELECT COUNT(*) FROM consultation_requests WHERE ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
    );
    $spamCheck->execute([$ip]);
    $recentCount = (int)$spamCheck->fetchColumn();

    if ($recentCount >= 3) {
        respond(['success' => false, 'message' => 'تعداد درخواست‌های شما از این IP بیش از حد مجاز است — لطفاً ۱ ساعت دیگر تلاش کنید']);
    }

    // =========== ثبت درخواست ===========
    $stmt = $db->prepare(
        "INSERT INTO consultation_requests (name, phone, email, style, message, status, ip_address)
         VALUES (?, ?, ?, ?, ?, 'new', ?)"
    );
    $stmt->execute([$name, $phone, $email, $style, $message, $ip]);
    $newId = $db->lastInsertId();

    // =========== ارسال ایمیل (اختیاری) ===========
    // ایمیل ادمین را در env var قرار دهید: SetEnv ADMIN_EMAIL admin@yourdomain.com
    $adminEmail = getenv('ADMIN_EMAIL') ?: '';
    $fromEmail  = getenv('FROM_EMAIL')  ?: ('noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    if (!empty($adminEmail) && function_exists('mail')) {
        $subject  = '=?UTF-8?B?' . base64_encode('درخواست مشاوره جدید — آرتا دکور') . '?=';
        $bodyMail = "درخواست مشاوره جدید دریافت شد:\n\n";
        $bodyMail .= "نام: $name\n";
        $bodyMail .= "تلفن: $phone\n";
        $bodyMail .= "ایمیل: " . ($email ?: '---') . "\n";
        $bodyMail .= "سبک: " . ($style ?: '---') . "\n";
        $bodyMail .= "پیام: " . ($message ?: '---') . "\n\n";
        $bodyMail .= "IP: $ip\n";
        $bodyMail .= "زمان: " . date('Y-m-d H:i:s') . "\n";
        $headers  = "From: $fromEmail\r\nContent-Type: text/plain; charset=UTF-8\r\n";
        @mail($adminEmail, $subject, $bodyMail, $headers);
    }

    respond(['success' => true, 'message' => 'درخواست شما با موفقیت ثبت شد — به زودی با شما تماس می‌گیریم', 'id' => (int)$newId]);

} catch (Exception $e) {
    error_log("[ArtaDecor] Contact form error: " . $e->getMessage());
    respond(['success' => false, 'message' => 'خطای سرور — لطفاً دوباره تلاش کنید'], 500);
}
