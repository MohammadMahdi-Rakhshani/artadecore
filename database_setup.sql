-- ================================================================
-- ARTA DECOR — Database Setup
-- اجرا در phpMyAdmin → دیتابیس مورد نظر → تب SQL
-- ================================================================

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- ۱. جدول ادمین‌ها
CREATE TABLE IF NOT EXISTS `admins` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `username`      VARCHAR(100) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `name`          VARCHAR(200) NOT NULL,
  `role`          ENUM('super_admin','admin') DEFAULT 'admin',
  `last_login`    DATETIME,
  `created_at`    DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ۲. جدول درخواست‌های مشاوره
CREATE TABLE IF NOT EXISTS `consultation_requests` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `name`        VARCHAR(200) NOT NULL,
  `phone`       VARCHAR(20)  NOT NULL,
  `email`       VARCHAR(200),
  `style`       VARCHAR(50),
  `message`     TEXT,
  `status`      ENUM('new','pending','confirmed','done','cancelled') DEFAULT 'new',
  `admin_note`  TEXT,
  `ip_address`  VARCHAR(45),
  `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ۳. جدول سبک‌های دکوراسیون
CREATE TABLE IF NOT EXISTS `decoration_styles` (
  `id`             INT AUTO_INCREMENT PRIMARY KEY,
  `style_key`      VARCHAR(50)  NOT NULL UNIQUE,
  `name_fa`        VARCHAR(200) NOT NULL,
  `name_en`        VARCHAR(200) NOT NULL,
  `tag_fa`         VARCHAR(100),
  `tag_en`         VARCHAR(100),
  `description_fa` TEXT,
  `description_en` TEXT,
  `materials_fa`   TEXT,
  `materials_en`   TEXT,
  `emoji`          VARCHAR(10)  DEFAULT '🏠',
  `img1_url`       TEXT,
  `img2_url`       TEXT,
  `img3_url`       TEXT,
  `img4_url`       TEXT,
  `img5_url`       TEXT,
  `img6_url`       TEXT,
  `status`         ENUM('active','inactive') DEFAULT 'active',
  `sort_order`     INT DEFAULT 0,
  `created_at`     DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ۴. جدول نماهای ۳D
CREATE TABLE IF NOT EXISTS `views_3d` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `name`        VARCHAR(200) NOT NULL,
  `type`        ENUM('3d','2d') NOT NULL,
  `style_key`   VARCHAR(50),
  `room`        VARCHAR(100),
  `description` TEXT,
  `img_url`     TEXT,
  `dimensions`  VARCHAR(100),
  `status`      ENUM('active','inactive') DEFAULT 'active',
  `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ۵. جدول تنظیمات SEO
CREATE TABLE IF NOT EXISTS `seo_settings` (
  `id`               INT AUTO_INCREMENT PRIMARY KEY,
  `page_key`         VARCHAR(100) NOT NULL UNIQUE,
  `meta_title`       VARCHAR(255),
  `meta_description` TEXT,
  `meta_keywords`    TEXT,
  `canonical_url`    VARCHAR(500),
  `og_title`         VARCHAR(255),
  `og_image`         TEXT,
  `updated_at`       DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ۶. جدول کلمات کلیدی SEO
CREATE TABLE IF NOT EXISTS `seo_keywords` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `keyword`       VARCHAR(255) NOT NULL,
  `search_volume` INT DEFAULT 0,
  `competition`   INT DEFAULT 0,
  `current_rank`  INT,
  `page_key`      VARCHAR(100) DEFAULT 'home',
  `created_at`    DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ۷. جدول تصاویر سایت
CREATE TABLE IF NOT EXISTS `site_images` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `image_key`  VARCHAR(100) NOT NULL UNIQUE,
  `image_url`  TEXT NOT NULL,
  `alt_text`   VARCHAR(255),
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ۸. لاگ بازدیدکنندگان (اختیاری)
CREATE TABLE IF NOT EXISTS `site_visitors_log` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `page_url`   VARCHAR(500),
  `ip_address` VARCHAR(45),
  `user_agent` VARCHAR(500),
  `referer`    VARCHAR(500),
  `visited_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- داده‌های پیش‌فرض
-- ================================================================

-- ادمین پیش‌فرض
-- رمز: Admin@Arta2024 (این را در phpMyAdmin تغییر دهید!)
-- برای تولید hash جدید در PHP: echo password_hash('رمز_جدید', PASSWORD_BCRYPT);
-- رمز پیش‌فرض: Admin@Arta2024 — حتماً بعد از اولین ورود تغییر دهید!
INSERT IGNORE INTO `admins` (`username`, `password_hash`, `name`, `role`)
VALUES ('admin', '$2y$12$sqmf03DPO6C3N.CqpzcL4e7PjQfUKldvbLlXbuoJn6jI6yn6eJQ7i', 'مدیر آرتا دکور', 'super_admin');

-- سبک‌های پیش‌فرض
INSERT IGNORE INTO `decoration_styles` (`style_key`,`name_fa`,`name_en`,`tag_fa`,`tag_en`,`emoji`,`status`,`sort_order`) VALUES
('european', 'اروپایی',      'European',  'کلاسیک و شکوهمند', 'Classic & Grand',      '🏛', 'active', 1),
('american', 'آمریکایی',     'American',  'مدرن و راحت',      'Modern & Cozy',         '🏡', 'active', 2),
('eastasian','آسیای شرقی',   'East Asian','آرامش و سادگی',    'Zen & Minimal',         '⛩', 'active', 3),
('arabic',   'عربی',          'Arabic',    'تجمل و هنر',       'Luxe & Ornate',         '🕌', 'active', 4),
('iranian',  'ایرانی',        'Iranian',   'اصیل و هنرمند',    'Authentic & Artistic',  '🏺', 'active', 5);

-- تنظیمات SEO پیش‌فرض
INSERT IGNORE INTO `seo_settings` (`page_key`,`meta_title`,`meta_description`) VALUES
('home',
 'دکوراسیون آرتا | Arta Decor',
 'شرکت دکوراسیون داخلی آرتا — طراحی داخلی حرفه‌ای با سبک‌های اروپایی، ایرانی، آمریکایی، عربی و آسیای شرقی');

SET foreign_key_checks = 1;

-- ================================================================
-- راهنمای تنظیم رمز عبور ادمین
-- ================================================================
-- ۱. ابتدا جداول بالا را اجرا کنید
-- ۲. سپس در تب SQL این دستور را اجرا کنید (رمز خود را جایگزین کنید):
--
--   UPDATE admins
--   SET password_hash = '$2y$12$...'
--   WHERE username = 'admin';
--
-- برای تولید hash، یک فایل PHP موقت بسازید و اجرا کنید:
--   <?php echo password_hash('رمز_جدید_شما', PASSWORD_BCRYPT); ?>
-- سپس hash خروجی را در دستور UPDATE بالا استفاده کنید.
-- ================================================================
