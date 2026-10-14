<?php
/**
 * upload.php — آپلود تصویر (نیاز به auth)
 */
require_once __DIR__ . '/config.php';
$admin = requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['success' => false, 'message' => 'Method not allowed'], 405);
}

if (empty($_FILES['file'])) {
    respond(['success' => false, 'message' => 'فایلی ارسال نشده است']);
}

$file = $_FILES['file'];

// بررسی خطای آپلود
if ($file['error'] !== UPLOAD_ERR_OK) {
    $errors = [
        UPLOAD_ERR_INI_SIZE   => 'فایل بزرگتر از حد مجاز سرور است',
        UPLOAD_ERR_FORM_SIZE  => 'فایل بزرگتر از حد مجاز فرم است',
        UPLOAD_ERR_PARTIAL    => 'آپلود ناقص است',
        UPLOAD_ERR_NO_FILE    => 'فایلی انتخاب نشده',
        UPLOAD_ERR_NO_TMP_DIR => 'پوشه موقت موجود نیست',
        UPLOAD_ERR_CANT_WRITE => 'امکان نوشتن فایل نیست',
    ];
    respond(['success' => false, 'message' => $errors[$file['error']] ?? 'خطای ناشناخته آپلود']);
}

// بررسی حجم
if ($file['size'] > MAX_UPLOAD_SIZE) {
    respond(['success' => false, 'message' => 'حجم فایل بیش از ۵ مگابایت است']);
}

// بررسی نوع فایل (دوگانه: MIME + extension)
$allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
$allowedExts  = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);

if (!in_array($mimeType, $allowedMimes)) {
    respond(['success' => false, 'message' => 'فقط فرمت‌های JPG، PNG، WebP و GIF مجاز است']);
}

// پسوند را از MIME type استخراج کن — به نام فایل کاربر اعتماد نکن
$mimeToExt = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
];
$safeExt = $mimeToExt[$mimeType] ?? null;
if (!$safeExt) {
    respond(['success' => false, 'message' => 'فرمت فایل پشتیبانی نمی‌شود']);
}

// ساخت نام تصادفی
$newName  = uniqid('img_', true) . '_' . bin2hex(random_bytes(4)) . '.' . $safeExt;
$destPath = UPLOAD_DIR . $newName;

// ساخت پوشه در صورت نبود
if (!is_dir(UPLOAD_DIR)) {
    if (!mkdir(UPLOAD_DIR, 0755, true)) {
        respond(['success' => false, 'message' => 'خطا در ساخت پوشه آپلود'], 500);
    }
}

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    respond(['success' => false, 'message' => 'خطا در ذخیره فایل'], 500);
}

// تبدیل بک‌اسلش به اسلش برای URL
$url = UPLOAD_URL . $newName;

respond([
    'success'  => true,
    'url'      => $url,
    'filename' => $newName,
    'size'     => $file['size'],
    'type'     => $mimeType,
]);
