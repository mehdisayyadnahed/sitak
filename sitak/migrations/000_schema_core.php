<?php
/**
 * migration ۰۰ — ساختار پایه دیتابیس (از config.php منتقل شد)
 * این بلوک قبلاً در هر درخواست config.php اجرا می‌شد (~۱۲۰ دستورالعمل دیتابیس در هر درخواست!)؛
 * اکنون با مکانیزمِ schema_migrations فقط یک‌بار و هنگام نصب/ارتقا اجرا می‌شود.
 * تنظیمات/ستون‌های جدید بعدی باید در migrationهای شماره‌دار بعدی اضافه شوند.
 */
return static function (mysqli $conn): void {
// ساخت جدول کاربران
$conn->query("CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `phone` VARCHAR(15) UNIQUE NOT NULL,
    `name` VARCHAR(100) NULL,
    `referral_code` VARCHAR(15) NULL,
    `referral_rolls` INT DEFAULT 0,
    `last_daily_roll` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;");

// ارتقای خودکار جدول کاربران جهت ذخیره مراجعات (Visits)
$check_daily_used = $conn->query("SHOW COLUMNS FROM `users` LIKE 'daily_rolls_used'");
if ($check_daily_used && $check_daily_used->num_rows == 0) {
    $conn->query("ALTER TABLE `users` ADD `daily_rolls_used` INT DEFAULT 0");
}
$check_visits = $conn->query("SHOW COLUMNS FROM `users` LIKE 'visits'");
if ($check_visits && $check_visits->num_rows == 0) {
    $conn->query("ALTER TABLE `users` ADD `visits` INT DEFAULT 0 AFTER `referral_rolls`");
}

// ارتقای خودکار جدول کاربران جهت ذخیره نشانی (Address)
$check_address = $conn->query("SHOW COLUMNS FROM `users` LIKE 'address'");
if ($check_address && $check_address->num_rows == 0) {
    $conn->query("ALTER TABLE `users` ADD `address` VARCHAR(255) NULL AFTER `name`");
}

foreach ([
    "last_name VARCHAR(100) NULL AFTER `name`",
    "province VARCHAR(80) NULL AFTER `address`",
    "city VARCHAR(80) NULL AFTER `province`",
    "plaque VARCHAR(20) NULL AFTER `city`",
    "unit VARCHAR(20) NULL AFTER `plaque`",
    "postal_code VARCHAR(20) NULL AFTER `unit`",
    "extra_notes TEXT NULL AFTER `postal_code`"
] as $profile_col) {
    $col_name = preg_replace('/\s.*$/', '', $profile_col);
    $has_col = $conn->query("SHOW COLUMNS FROM `users` LIKE '" . $conn->real_escape_string($col_name) . "'");
    if ($has_col && $has_col->num_rows === 0) $conn->query("ALTER TABLE `users` ADD `$col_name` " . substr($profile_col, strlen($col_name)));
}

// ارتقای خودکار جدول کاربران جهت مسدودسازی کاربر
$check_blocked = $conn->query("SHOW COLUMNS FROM `users` LIKE 'blocked'");
if ($check_blocked && $check_blocked->num_rows == 0) {
    $conn->query("ALTER TABLE `users` ADD `blocked` TINYINT(1) DEFAULT 0 AFTER `address`");
}
$check_comment_blocked = $conn->query("SHOW COLUMNS FROM `users` LIKE 'comment_blocked'");
if ($check_comment_blocked && $check_comment_blocked->num_rows == 0) {
    $conn->query("ALTER TABLE `users` ADD `comment_blocked` TINYINT(1) DEFAULT 0 AFTER `blocked`");
}

// ساخت جدول جوایز و لینک‌های افیلیت
$conn->query("CREATE TABLE IF NOT EXISTS `prizes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `description` TEXT NOT NULL,
    `affiliate_link` VARCHAR(255) NULL,
    `discount_code` VARCHAR(100) NULL,
    `win_probability` DECIMAL(5,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;");

// ارتقای خودکار جدول جوایز جهت تفکیک محصولات محلی فروشگاه و محصولات آنلاین دیگران
$check_col = $conn->query("SHOW COLUMNS FROM `prizes` LIKE 'is_local'");
if ($check_col && $check_col->num_rows == 0) {
    $conn->query("ALTER TABLE `prizes` ADD `is_local` TINYINT(1) DEFAULT 1 AFTER `win_probability`");
}

// ستون «پوچ»: یک ردیف ویژه که قابل حذف نیست، فقط در گردونه نمایش داده می‌شود و درصدش قابل تغییر است
$check_void = $conn->query("SHOW COLUMNS FROM `prizes` LIKE 'is_void'");
if ($check_void && $check_void->num_rows == 0) {
    $conn->query("ALTER TABLE `prizes` ADD `is_void` TINYINT(1) NOT NULL DEFAULT 0");
}

// ساخت جدول برندگان
$conn->query("CREATE TABLE IF NOT EXISTS `winners` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `phone` VARCHAR(15) NOT NULL,
    `name` VARCHAR(100) NULL,
    `prize_name` VARCHAR(255) NOT NULL,
    `won_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `claimed` TINYINT(1) DEFAULT 0,
    `is_fake` TINYINT(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;");

// ساخت جدول تنظیمات
$conn->query("CREATE TABLE IF NOT EXISTS `settings` (
    `key_name` VARCHAR(50) PRIMARY KEY,
    `value_data` VARCHAR(255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;");

$conn->query("CREATE TABLE IF NOT EXISTS `firewall_events` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `event_type` VARCHAR(40) NOT NULL,
    `ip_hash` CHAR(64) NOT NULL,
    `path` VARCHAR(190) NOT NULL DEFAULT '',
    `user_agent` VARCHAR(255) NULL,
    `details` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (`event_type`), INDEX (`created_at`), INDEX (`ip_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;");


// ساخت جدول دیدگاه‌ها محصولات و مطالب
$conn->query("CREATE TABLE IF NOT EXISTS `product_comments` (`id` INT AUTO_INCREMENT PRIMARY KEY, `product_id` INT NOT NULL, `user_phone` VARCHAR(15) NOT NULL, `rating` TINYINT NOT NULL DEFAULT 5, `body` TEXT NOT NULL, `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending', `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX(`product_id`), INDEX(`status`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;");
$conn->query("CREATE TABLE IF NOT EXISTS `post_comments` (`id` INT AUTO_INCREMENT PRIMARY KEY, `post_id` INT NOT NULL, `user_phone` VARCHAR(15) NOT NULL, `rating` TINYINT NOT NULL DEFAULT 5, `body` TEXT NOT NULL, `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending', `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX(`post_id`), INDEX(`status`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;");
$conn->query("INSERT IGNORE INTO settings (key_name,value_data) VALUES ('comments_products_enabled','1'),('comments_posts_enabled','1'),('comments_require_approval','1');");
$conn->query("CREATE TABLE IF NOT EXISTS `comment_votes` (`id` INT AUTO_INCREMENT PRIMARY KEY, `comment_type` ENUM('product','post') NOT NULL, `comment_id` INT NOT NULL, `user_phone` VARCHAR(15) NOT NULL, `vote_type` ENUM('like','dislike') NOT NULL, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY `comment_user_vote` (`comment_type`,`comment_id`,`user_phone`), INDEX(`comment_type`,`comment_id`), INDEX(`user_phone`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;");
$conn->query("INSERT IGNORE INTO settings (key_name,value_data) VALUES ('comments_submit_products_enabled','1'),('comments_submit_posts_enabled','1'),('comments_dislike_products_enabled','1'),('comments_dislike_posts_enabled','1'),('comments_reactions_products_enabled','1'),('comments_reactions_posts_enabled','1');");

// ساخت جدول بازدید صفحات و محصولات و مطالب (روزانه)
$conn->query("CREATE TABLE IF NOT EXISTS `page_views` (
    `page` VARCHAR(60) NOT NULL,
    `view_date` DATE NOT NULL,
    `views` INT DEFAULT 1,
    PRIMARY KEY (`page`,`view_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;");

// ساخت جدول محصولات (فروشگاه)
$conn->query("CREATE TABLE IF NOT EXISTS `products` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `category` VARCHAR(50) NOT NULL DEFAULT 'عمومی',
    `name` VARCHAR(150) NOT NULL,
    `description` TEXT,
    `price` INT NOT NULL DEFAULT 0,
    `image_path` VARCHAR(255) NULL,
    `is_available` TINYINT(1) DEFAULT 1,
    `sort_order` INT DEFAULT 0,
    `shipping_province` VARCHAR(80) NULL,
    `shipping_city` VARCHAR(100) NULL,
    `onsite_product` TINYINT(1) DEFAULT 0,
    `morning_open` VARCHAR(5) NULL,
    `morning_close` VARCHAR(5) NULL,
    `afternoon_open` VARCHAR(5) NULL,
    `afternoon_close` VARCHAR(5) NULL,
    `active_days` VARCHAR(30) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;");
foreach (["shipping_province VARCHAR(80) NULL", "shipping_city VARCHAR(100) NULL", "onsite_product TINYINT(1) DEFAULT 0", "morning_open VARCHAR(5) NULL", "morning_close VARCHAR(5) NULL", "afternoon_open VARCHAR(5) NULL", "afternoon_close VARCHAR(5) NULL", "active_days VARCHAR(30) NULL"] as $product_col) {
    $col_name = preg_replace('/\s.*$/', '', $product_col);
    $has_col = $conn->query("SHOW COLUMNS FROM `products` LIKE '" . $conn->real_escape_string($col_name) . "'");
    if ($has_col && $has_col->num_rows === 0) $conn->query("ALTER TABLE `products` ADD `$col_name` " . substr($product_col, strlen($col_name)));
}

// بررسی محدودیت روز و ساعت فروش محصول با زمان سرور ایران

// برچسب‌های نمایشی روز و ساعت محصول محلی (با اولویت محدودیت محصول و سپس تنظیمات فروشگاه)

// نمایش ساعت به شکل فارسی با دونقطه، مثل ۱۴:۳۰
// داده‌های حباب‌های محصول محلی: محدوده، ساعت‌های صبح/ظهر و وضعیت فعال/غیرفعال در لحظه (ساعت تهران)

// بررسی روز و ساعت کاری کلی فروشگاه با زمان سرور ایران

// ساخت جدول دسته‌بندی‌های فروشگاه
$conn->query("CREATE TABLE IF NOT EXISTS `shop_categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL UNIQUE,
    `sort_order` INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;");

// ساخت جدول سفارشات
$conn->query("CREATE TABLE IF NOT EXISTS `orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_phone` VARCHAR(15) NOT NULL,
    `user_name` VARCHAR(100) NULL,
    `items` JSON NOT NULL,
    `total_price` INT NOT NULL DEFAULT 0,
    `note` TEXT NULL,
    `admin_note` TEXT NULL,
    `status` ENUM('pending','confirmed','shipped','delivered','cancelled') DEFAULT 'pending',
    `payment_authority` VARCHAR(64) NULL,
    `payment_ref_id` VARCHAR(64) NULL,
    `payment_paid_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;");

foreach ([
    "payment_authority VARCHAR(64) NULL",
    "payment_ref_id VARCHAR(64) NULL",
    "payment_paid_at TIMESTAMP NULL"
] as $payment_col) {
    $col_name = explode(' ', $payment_col)[0];
    $has_col = $conn->query("SHOW COLUMNS FROM `orders` LIKE '" . $conn->real_escape_string($col_name) . "'");
    if ($has_col && $has_col->num_rows === 0) {
        $conn->query("ALTER TABLE `orders` ADD `$col_name` " . substr($payment_col, strlen($col_name)));
    }
}

foreach ([
    "recipient_last_name VARCHAR(100) NULL",
    "recipient_phone VARCHAR(15) NULL",
    "recipient_province VARCHAR(80) NULL",
    "recipient_city VARCHAR(80) NULL",
    "recipient_address VARCHAR(255) NULL",
    "recipient_plaque VARCHAR(20) NULL",
    "recipient_unit VARCHAR(20) NULL",
    "recipient_postal_code VARCHAR(20) NULL"
] as $recipient_col) {
    $col_name = preg_replace('/\s.*$/', '', $recipient_col);
    $has_col = $conn->query("SHOW COLUMNS FROM `orders` LIKE '" . $conn->real_escape_string($col_name) . "'");
    if ($has_col && $has_col->num_rows === 0) $conn->query("ALTER TABLE `orders` ADD `$col_name` " . substr($recipient_col, strlen($col_name)));
}

// ارتقای خودکار جدول سفارشات جهت ذخیره یادداشت ادمین
$check_shipped = $conn->query("SHOW COLUMNS FROM `orders` LIKE 'status'");
if ($check_shipped && ($cs_row = $check_shipped->fetch_assoc()) && strpos($cs_row['Type'], "'shipped'") === false) {
    $conn->query("ALTER TABLE `orders` MODIFY `status` ENUM('pending','confirmed','shipped','delivered','cancelled') DEFAULT 'pending'");
}
$check_anote = $conn->query("SHOW COLUMNS FROM `orders` LIKE 'admin_note'");
if ($check_anote && $check_anote->num_rows == 0) {
    $conn->query("ALTER TABLE `orders` ADD `admin_note` TEXT NULL AFTER `note`");
}

// ساخت جدول دسته‌بندی مقالات
// شماره‌ی سفارش‌ها از ۱۰۰۰ شروع می‌شود (فقط یک‌بار، و اگر سفارش‌های قدیمی‌تر از ۱۰۰۰ نباشند)
$order_seq_flag = $conn->query("SELECT value_data FROM settings WHERE key_name = 'orders_number_start_done' LIMIT 1");
if (!$order_seq_flag || $order_seq_flag->num_rows === 0) {
    $order_max = $conn->query("SELECT COALESCE(MAX(id), 0) AS m FROM orders");
    $order_max_id = $order_max ? (int)$order_max->fetch_assoc()['m'] : 0;
    if ($order_max_id < 999) $conn->query("ALTER TABLE `orders` AUTO_INCREMENT = 1000");
    $conn->query("INSERT IGNORE INTO settings (key_name,value_data) VALUES ('orders_number_start_done','1')");
}

// ── کدهای تخفیف ──
$conn->query("CREATE TABLE IF NOT EXISTS `discount_codes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(40) NOT NULL UNIQUE,
    `type` ENUM('amount','percent') NOT NULL DEFAULT 'amount',
    `value` INT NOT NULL DEFAULT 0,
    `max_discount` INT NULL,
    `min_subtotal` INT NOT NULL DEFAULT 0,
    `usage_limit` INT NULL,
    `used_count` INT NOT NULL DEFAULT 0,
    `target_phone` VARCHAR(15) NULL,
    `product_id` INT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;");
$conn->query("INSERT IGNORE INTO settings (key_name,value_data) VALUES ('discount_enabled','0')");
$conn->query("INSERT IGNORE INTO settings (key_name,value_data) VALUES ('blog_views_enabled','1')");
$dc_phone_flag = $conn->query("SELECT value_data FROM settings WHERE key_name = 'discount_phones_multi' LIMIT 1");
if (!$dc_phone_flag || $dc_phone_flag->num_rows === 0) {
    $conn->query("ALTER TABLE `discount_codes` MODIFY `target_phone` TEXT NULL");
    $conn->query("INSERT IGNORE INTO settings (key_name,value_data) VALUES ('discount_phones_multi','1')");
}
$dc_prod_flag = $conn->query("SELECT value_data FROM settings WHERE key_name = 'discount_products_multi' LIMIT 1");
if (!$dc_prod_flag || $dc_prod_flag->num_rows === 0) {
    $conn->query("ALTER TABLE `discount_codes` MODIFY `product_id` TEXT NULL");
    $conn->query("INSERT IGNORE INTO settings (key_name,value_data) VALUES ('discount_products_multi','1')");
}
$dc_link_col = $conn->query("SHOW COLUMNS FROM `discount_codes` LIKE 'link_enabled'");
if ($dc_link_col && $dc_link_col->num_rows === 0) $conn->query("ALTER TABLE `discount_codes` ADD `link_enabled` TINYINT(1) NOT NULL DEFAULT 1");
// مدت اعتبار کد (روز از لحظه‌ی ثبت/ویرایش) و تاریخ انقضای محاسبه‌شده؛ NULL یعنی بدون انقضا
$dc_days_col = $conn->query("SHOW COLUMNS FROM `discount_codes` LIKE 'valid_days'");
if ($dc_days_col && $dc_days_col->num_rows === 0) $conn->query("ALTER TABLE `discount_codes` ADD `valid_days` INT NULL");
$dc_exp_col = $conn->query("SHOW COLUMNS FROM `discount_codes` LIKE 'expires_at'");
if ($dc_exp_col && $dc_exp_col->num_rows === 0) $conn->query("ALTER TABLE `discount_codes` ADD `expires_at` DATETIME NULL");
// موجودی محصول (NULL یعنی محدودیتی ندارد)
$prod_stock_col = $conn->query("SHOW COLUMNS FROM `products` LIKE 'stock_qty'");
if ($prod_stock_col && $prod_stock_col->num_rows === 0) $conn->query("ALTER TABLE `products` ADD `stock_qty` INT NULL");
$conn->query("INSERT IGNORE INTO settings (key_name,value_data) VALUES ('stock_badge_enabled','1'),('stock_badge_threshold','3')");
// نقشه‌ی تحویل (map.ir): تنظیمات پیش‌فرض و ستون‌های موقعیت
$conn->query("INSERT IGNORE INTO settings (key_name,value_data) VALUES ('map_enabled','0'),('map_api_key',''),('map_browser_key',''),('map_default_lat','32.4279'),('map_default_lng','53.6880'),('map_default_zoom','5')");
foreach ([['users','delivery_lat','DECIMAL(10,7) NULL'],['users','delivery_lng','DECIMAL(10,7) NULL'],['orders','delivery_lat','DECIMAL(10,7) NULL'],['orders','delivery_lng','DECIMAL(10,7) NULL'],['orders','delivery_address','VARCHAR(255) NULL']] as $mcol) {
    $mhas = $conn->query("SHOW COLUMNS FROM `" . $mcol[0] . "` LIKE '" . $mcol[1] . "'");
    if ($mhas && $mhas->num_rows === 0) $conn->query("ALTER TABLE `" . $mcol[0] . "` ADD `" . $mcol[1] . "` " . $mcol[2]);
}
foreach ([
    "discount_code VARCHAR(40) NULL",
    "discount_amount INT NOT NULL DEFAULT 0"
] as $disc_col) {
    $col_name = explode(' ', $disc_col)[0];
    $has_col = $conn->query("SHOW COLUMNS FROM `orders` LIKE '" . $conn->real_escape_string($col_name) . "'");
    if ($has_col && $has_col->num_rows === 0) $conn->query("ALTER TABLE `orders` ADD `$col_name` " . substr($disc_col, strlen($col_name)));
}

$conn->query("CREATE TABLE IF NOT EXISTS `product_tags` (`id` INT AUTO_INCREMENT PRIMARY KEY, `name` VARCHAR(50) NOT NULL, `slug` VARCHAR(60) NOT NULL, UNIQUE KEY `slug_unique` (`slug`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;");
$conn->query("CREATE TABLE IF NOT EXISTS `product_tag_relations` (`product_id` INT NOT NULL, `tag_id` INT NOT NULL, PRIMARY KEY (`product_id`, `tag_id`), INDEX(`tag_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;");
$conn->query("CREATE TABLE IF NOT EXISTS `post_categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) UNIQUE NOT NULL,
    `description` TEXT NULL,
    `color` VARCHAR(7) DEFAULT '#ecb534',
    `sort_order` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;");

// ساخت جدول مقالات
$conn->query("CREATE TABLE IF NOT EXISTS `posts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `category_id` INT NULL,
    `title` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) UNIQUE NOT NULL,
    `excerpt` TEXT,
    `body` LONGTEXT NOT NULL,
    `cover_image` VARCHAR(255) NULL,
    `is_published` TINYINT(1) DEFAULT 0,
    `views` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`category_id`) REFERENCES `post_categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;");

// ساخت جدول برچسب‌ها
$conn->query("CREATE TABLE IF NOT EXISTS `post_tags` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL,
    `slug` VARCHAR(50) UNIQUE NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;");

// ساخت جدول رابطه مقاله-برچسب
$conn->query("CREATE TABLE IF NOT EXISTS `post_tag_relations` (
    `post_id` INT NOT NULL,
    `tag_id` INT NOT NULL,
    PRIMARY KEY (`post_id`, `tag_id`),
    FOREIGN KEY (`post_id`) REFERENCES `posts`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`tag_id`) REFERENCES `post_tags`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;");

// ساخت جدول بیت‌های حافظ
$conn->query("CREATE TABLE IF NOT EXISTS `hafez_verses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `verse_m1` TEXT NOT NULL,
    `verse_m2` TEXT NOT NULL,
    `meaning_short` TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;");

// ساخت جدول باز کردن فال
$conn->query("CREATE TABLE IF NOT EXISTS `hafez_opens` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_phone` VARCHAR(15) NOT NULL,
    `verse_id` INT NOT NULL,
    `opened_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;");

// ── نمونه بیت‌های حافظ ──
// —— نمونه بیت‌های حافظ (۱۰۰ بیت از دیتابیس) ——
$hafez_sample = [
    [1, "الا یا ایها الساقی ادر كأسا و ناولها  \\n  که عشق آسان نمود اول ولی افتاد مشکل‌ها", 'در ابتدای کار با دشواری روبه‌رو می‌شوی، اما نگران نباش؛ با صبر و توکل گره‌ها گشوده می‌شود.'],
    [2, "صلاح کار کجا و من خراب کجا  \\n  ببین تفاوت ره از کجاست تا به کجا", 'در تصمیمی که داری دودل نباش؛ راه درست را با مشورت برگزین و به مصلحت خود بیندیش.'],
    [3, "اگر آن ترک شیرازی به دست آرد دل ما را  \\n  به خال هندویش بخشم سمرقند و بخارا را", 'به دل‌بستگی‌های مادی بی‌اعتنا باش؛ آنچه واقعاً دل تو را می‌برد، ارزشش از همه‌ی داشته‌هایت بیشتر است.'],
    [4, "صوفی بیا که آینه صافیست جام را  \\n  تا بنگری صفای می لعل‌فام را", 'نیتت را از هرگونه ریا و دورویی پاک کن تا حقیقت امور و راه درست بر تو نمایان شود.'],
    [5, "دل می‌رود ز دستم صاحب‌دلان خدا را  \\n  دردا که راز پنهان خواهد شد آشکارا", 'عنان صبر را از دست مده و راز دلت را نگه دار؛ مدارا با خلق، کلید پیروزی توست.'],
    [6, "به ملازمان سلطان که رساند این دعا را  \\n  که به شکر پادشاهی ز نظر مران گدا را", 'دست از دعا و طلب خیر برندار؛ به زودی از جانب فردی صاحب‌مقام به تو توجه و یاری می‌رسد.'],
    [7, "رونق عهد شباب است دگر بستان را  \\n  می‌رسد مژده گل بلبل خوش‌الحان را", 'روزگار سختی سپری شده و فصل شادکامی و شکوفایی کارهایت فرا رسیده است.'],
    [8, "ساقی به نور باده برافروز جام ما  \\n  مطرب بگو که کار جهان شد به کام ما", 'بخت و اقبال به تو روی آورده است؛ به زودی کارها کاملاً بر وفق مرادت پیش می‌رود.'],
    [9, "در خرابات مغان نور خدا می‌بینم  \\n  این عجب بین که چه نوری ز کجا می‌بینم", 'از جایی که گمان نمی‌بری کمکی غیبی به تو می‌رسد؛ نیتت خیر است و روشن می‌شوی.'],
    [10, "دوش از مسجد سوی میخانه آمد پیر ما  \\n  چیست یاران طریقت بعد از این تدبیر ما", 'در تغییر مسیر زندگی تعصب نداشته باش؛ به راهنمای دانا اعتماد کن تا به سلامت برسی.'],
    [11, "سینه از آتش دل در غم جانانه بسوخت  \\n  آتشی بود در این خانه که کاشانه بسوخت", 'رنج و محنت‌هایت به پایان رسیده است؛ به زودی پاداش وفاداری و صبوری‌ات را می‌گیری.'],
    [12, "چو بشنوی سخن اهل دل مگو که خطاست  \\n  سخن‌شناس نه‌ای جان من خطا این جاست", 'نصیحت افراد باتجربه را بپذیر و لجاجت مکن تا در تصمیم پیشِ رو متضرر نشوی.'],
    [13, "زلف‌آشفته و خوی‌کرده و خندان‌لب و مست  \\n  پیرهن چاک و غزل‌خوان و صراحی در دست", 'بشارت وصال و شادمانی غیرمنتظره در راه است؛ شانس بزرگی پیش روی توست.'],
    [14, "آن سیه‌چرده که شیرینی عالم با اوست  \\n  چشم میگون لب خندان دل خرم با اوست", 'همدمی موافق و خوش‌طینت در تقدیر توست؛ نیتت مایه‌ی آرامش روح و دل خواهد شد.'],
    [15, "خدا چو صورت ابروی دلگشای تو بست  \\n  گشاد کار من اندر کرشمه‌های تو بست", 'گره از کار فروبسته‌ات باز می‌شود و به مراد قلبی‌ات دست خواهی یافت.'],
    [16, "ما ز یاران چشم یاری داشتیم  \\n  خود غلط بود آنچه می‌پنداشتیم", 'به امید دیگران منشین و فقط به همت خود و لطف خدا تکیه کن تا سربلند شوی.'],
    [17, "کنون که می‌دمد از بوستان نسیم بهشت  \\n  من و شراب فرح‌بخش و یار حورسرشت", 'فرصت شادی و آسایش را غنیمت بشمار؛ گذشته را رها کن و به آینده امیدوار باش.'],
    [18, "گل‌عذاری ز گلستان جهان ما را بس  \\n  زین چمن سایه آن سرو روان ما را بس", 'به آنچه داری قانع باش و زیاده‌خواهی مکن؛ آرامش حقیقی در رضایت دل است.'],
    [19, "دارم امید عاطفتی از جناب دوست  \\n  کردم جنایتی و امیدم به عفو اوست", 'اگر خطایی کرده‌ای برای جبرانش پیش‌قدم شو؛ به زودی دلخوری‌ها و کدورت‌ها برطرف می‌شود.'],
    [20, "مرحبا ای پیک مشتاقان بده پیغام دوست  \\n  تا کنم جان از سر رغبت فدای نام دوست", 'نامه‌ای نویدبخش یا خبری دلگرم‌کننده به تو می‌رسد که امید را در دلت زنده می‌کند.'],
    [21, "ساقی بیار باده که ماه صیام رفت  \\n  در ده قدح که موسم ناموس و نام رفت", 'دوران ریاضت و سختی پایان یافته است؛ اکنون زمان برداشت محصول و آرامش است.'],
    [22, "زاهد ظاهرپرست از حال ما آگاه نیست  \\n  در حق ما هر چه گوید جای هیچ اکراه نیست", 'به ملامت و طعنه حسودان اعتنا مکن؛ مسیر درستی را که در پیش داری استوار ادامه بده.'],
    [23, "بلبلی برگ گلی خوش‌رنگ در منقار داشت  \\n  واندر آن برگ و نوا خوش ناله‌های زار داشت", 'نعمت و فرصتی نصیبت شده؛ از ناشکری بپرهیز و قدر دستاوردهایت را بدان.'],
    [24, "عیب رندان مکن ای زاهد پاکیزه‌سرشت  \\n  که گناه دگران بر تو نخواهند نوشت", 'از تجسس و قضاوت دیگران پرهیز کن و دلت را به کار و پیشرفت خودت مشغول دار.'],
    [25, "ای هدهد صبا به سبا می‌فرستمت  \\n  بنگر که از کجا به کجا می‌فرستمت", 'پیامت به مقصد می‌رسد و صبوری‌ات به بار خواهد نشست؛ گشایشی مسرت‌بخش در پیش است.'],
    [26, "ای غایب از نظر به خدا می‌سپارمت  \\n  جانم بسوختی و به دل دوست دارمت", 'نیتت را به خداوند بسپار؛ پیوند عاطفی تو استوار است و دوری به پایان می‌رسد.'],
    [27, "آن که رخسار تو را رنگ گل و نسرین داد  \\n  صبر و آرام تواند به من مسکین داد", 'به داده‌ها و مقدرات خداوند راضی باش؛ او توانایی حل بزرگ‌ترین مشکلات تو را دارد.'],
    [28, "درخت دوستی بنشان که کام دل به بار آرد  \\n  نهال دشمنی برکن که رنج بی‌شمار آرد", 'با دیگران به مهر و گذشت رفتار کن تا درهای خیر و گشایش به رویت گشوده شود.'],
    [29, "آن کس که به دست جام دارد  \\n  سلطانی جم مدام دارد", 'دلت را به استغنای درون مزین کن؛ با داشتن قناعت، هیچ گزندی از روزگار نخواهی دید.'],
    [30, "دلی که غیب‌نمای است و جام جم دارد  \\n  ز خاتمی که دمی گم شود چه غم دارد", 'از زیان‌های مالی یا روحی کوچک هراسان؛ سرمایه اصلی در خرد و باطن خودت است.'],
    [31, "هر آن که جانب اهل خدا نگه دارد  \\n  خداش در همه حال از بلا نگه دارد", 'نیتت پاک است؛ به سبب اعمال نیکت از هرگونه آسیب و بلا محفوظ می‌مانی.'],
    [32, "خلوت‌گزیده را به تماشا چه حاجت است  \\n  چون کوی دوست هست به صحرا چه حاجت است", 'از غوغای پیرامون دوری کن و به کار خودت بپرداز؛ آرامش در دوری از حاشیه‌هاست.'],
    [33, "بشنو این نکته که خود را ز غم آزاده کنی  \\n  خون خوری گر طلب روزی ننهاده کنی", 'نگران رزق و آینده نباش؛ حرص و طمع را کنار بگذار که مصلحت تو تأمین است.'],
    [34, "سال‌ها دل طلب جام جم از ما می‌کرد  \\n  وان چه خود داشت ز بیگانه تمنا می‌کرد", 'چاره کار را در دیگران مجوی؛ کلید حل مسئله در دست توانمند خود توست.'],
    [35, "در ازل پرتو حسنت ز تجلی دم زد  \\n  عشق پیدا شد و آتش به همه عالم زد", 'عشق و اشتیاق تو شعله‌ور شده و همه‌چیز را به حرکت درمی‌آورد؛ با همین انگیزه مسیر را شروع کن.'],
    [36, "سحر چون خسرو خاور علم بر کوهساران زد  \\n  به دست مرحمت یارم در امیدواران زد", 'تاریکی‌ها فرو نشست و خورشید بختت دمید؛ درهای رحمت به رویت باز شد.'],
    [37, "خوش است خلوت اگر یار یار من باشد  \\n  نه من بسوزم و او شمع انجمن باشد", 'صداقت و یکرنگی شرط رسیدن به مقصود است؛ با اهل وفا همنشین باش.'],
    [38, "نفس باد صبا مشک‌فشان خواهد شد  \\n  عالم پیر دگرباره جوان خواهد شد", 'تحولی بزرگ و دلپذیر در احوالت پدید می‌آید؛ نوید آغاز دورانی پربرکت است.'],
    [39, "روز هجران و شب فرقت یار آخر شد  \\n  زدم این فال و گذشت اختر و کار آخر شد", 'دوران تنهایی و سختی تمام شد؛ کارها به بهترین سرانجام خواهد رسید.'],
    [40, "ستاره‌ای بدرخشید و ماه مجلس شد  \\n  دل رمیده ما را رفیق و مونس شد", 'شخصیتی پرخیر و نیک‌خواه وارد زندگی‌ات می‌شود که مایه آرامش خواهد بود.'],
    [41, "مژده ای دل که دگر باد صبا بازآمد  \\n  هدهد خوش‌خبر از طرف سبا بازآمد", 'پیغامی مسرت‌بخش از مسافری دور یا مقصودی گمشده به زودی دریافت می‌کنی.'],
    [42, "صبا به تهنیت پیر می‌فروش آمد  \\n  که موسم طرب و عیش و نوش و نوش آمد", 'زمان تنگی و عسرت به پایان رسید؛ ایام فراوانی و کامروایی فرا رسیده است.'],
    [43, "سحرم دولت بیدار به بالین آمد  \\n  گفت برخیز که آن خسرو شیرین آمد", 'طالع خفته‌ات بیدار شده است؛ آماده باش که سعادت به سراغت آمده است.'],
    [44, "رسید مژده که ایام غم نخواهد ماند  \\n  چنان نماند و چنین نیز هم نخواهد ماند", 'هیچ غمی پایدار نیست؛ اوضاع به نفع تو دگرگون و گشایش حاصل می‌شود.'],
    [45, "دوش وقت سحر از غصه نجاتم دادند  \\n  واندر آن ظلمت شب آب حیاتم دادند", 'پاداش صبر و شب‌زنده‌داری‌ات را خواهی گرفت؛ نجات و عافیت نزدیک است.'],
    [46, "دوش دیدم که ملائک در میخانه زدند  \\n  گل آدم بسرشتند و به پیمانه زدند", 'قدر جایگاه خودت را بدان؛ رسالت و سرنوشتی ارزشمند در انتظارت است.'],
    [47, "سمن‌بویان غبار غم چو بنشینند بنشانند  \\n  پری‌رویان قرار از دل چو بستیزند بستانند", 'با افراد باصفا و خیراندیش نشست‌وبرخاست کن تا غبار اندوه از دلت زدوده شود.'],
    [48, "دانی که چیست دولت دیدار یار دیدن  \\n  در کوی او گدایی بر خسروی گزیدن", 'برای رسیدن به هدفی ارزشمند باید از برخی خواسته‌های کم‌ارزش بگذری.'],
    [49, "بود آیا که در میکده‌ها بگشایند  \\n  گره از کار فروبسته ما بگشایند", 'دست به دعا بردار و ناامید مشو؛ به زودی قفل کارها با دست غیب باز می‌شود.'],
    [50, "گل در بر و می در کف و معشوق به کام است  \\n  سلطان جهانم به چنین روز غلام است", 'روزگار پیروزی و آرامش فرا رسیده؛ شکرگزار باش و با نشاط روزگار بگذران.'],
    [51, "یاد باد آن که ز ما وقت سفر یاد نکرد  \\n  به وداعی دل غمدیده ما شاد نکرد", 'بی‌وفایی دیگران را با گذشت پاسخ بده؛ خداوند بهترین تکیه‌گاه تو خواهد بود.'],
    [52, "گفتم غم تو دارم گفتا غمت سر آید  \\n  گفتم که ماه من شو گفتا اگر برآید", 'شتاب مکن که هر کاری وقتی دارد؛ این دوران دلتنگی به زودی به پایان می‌رسد.'],
    [53, "بر سر آنم که گر ز دست برآید  \\n  دست به کاری زنم که غصه سر آید", 'با تنبلی و انفعال کاری پیش نمی‌رود؛ آستین همت بالا بزن و اقدام کن.'],
    [54, "زهی خجسته زمانی که یار بازآید  \\n  به کام غمزدگان روزگار بازآید", 'چرخ روزگار به سودت خواهد گشت و ایام وصال و کامیابی تجدید می‌شود.'],
    [55, "مژده ای دل که مسیحا نفسی می‌آید  \\n  که ز انفاس خوشش بوی کسی می‌آید", 'مژده‌ای بزرگ در راه است؛ روحی تازه در کارهای معلق تو دمیده خواهد شد.'],
    [56, "معاشران ز حریف شبانه یاد آرید  \\n  حقوق بندگی مخلصانه یاد آرید", 'عهدشناس و حق‌شناس باش؛ قدردانی از یاران، برکت زندگی‌ات را افزون می‌کند.'],
    [57, "معاشران گره از زلف یار باز کنید  \\n  شبی خوش است بدین قصه‌اش دراز کنید", 'شب خوش است، پس گره‌ها را باز کن و با دوستان همدل خوش بگذران؛ گفت‌وگوی صمیمی گره‌های دلت را می‌گشاید.'],
    [58, "یوسف گم‌گشته بازآید به کنعان غم مخور  \\n  کلبه احزان شود روزی گلستان غم مخور", 'ناامیدی را از خود بران؛ کشتیبان این احوال خداست و روشنی در انتظار توست.'],
    [59, "منم که دیده به دیدار دوست کردم باز  \\n  چه شکر گویمت ای کارساز بنده‌نواز", 'شکر نعمات را به جا آور که درهای توفیق به رویت گشوده شده است.'],
    [60, "دل سراپرده محبت اوست  \\n  دیده آیینه‌دار طلعت اوست", 'قلبت را پاک نگه دار؛ نیت نیکی داری و با صفای باطن به مقصد می‌رسی.'],
    [61, "باغبان گر پنج روزی صحبت گل بایدش  \\n  بر جفای خار هجران صبر بلبل بایدش", 'دستیابی به اهداف بزرگ نیازمند تحمل زحمت‌های کوچک است؛ شکیبا باش.'],
    [62, "خوشا شیراز و وضع بی‌مثالش  \\n  خداوندا نگه دار از زوالش", 'قدر نعمت‌های پیرامونت و سلامت خانواده را بدان؛ عافیت یار توست.'],
    [63, "دوش با من گفت پنهان کاردانی تیزهوش  \\n  وز شما پنهان نشاید کرد سر می‌فروش", 'با آدم‌های امین و خردمند صادق باش؛ اسرارت را فقط با کسی در میان بگذار که واقعاً شایسته‌ی اعتمادش هستی.'],
    [64, "شراب بی‌غش و ساقی خوش دو دام رهند  \\n  که زیرکان جهان از کمندشان نرهند", 'مراقب وسوسه‌ها و فریب‌های ظاهری باش و با بصیرت گام بردار.'],
    [65, "صبا اگر گذری افتدت به کشور دوست  \\n  بیار نفحه‌ای از گیسوی معنبر دوست", 'پیوند قلبی‌ات قطع نخواهد شد؛ به زودی از مقصود خبری خوش خواهی شنید.'],
    [66, "حسنت به اتفاق ملاحت جهان گرفت  \\n  آری به اتفاق جهان می‌توان گرفت", 'با همراهی، همدلی و کار گروهی پیش برو؛ اتحاد رمز موفقیت توست.'],
    [67, "مرا عهدیست با جانان که تا جان در بدن دارم  \\n  هواداران کویش را چو جان خویشتن دارم", 'بر سر قول و پیمانت بمان؛ نیتت پاک است و از بدخواهان آسیبی نخواهی دید.'],
    [68, "دست در حلقه آن زلف دوتا نتوان کرد  \\n  تکیه بر عهد تو و باد صبا نتوان کرد", 'به وعده‌های پوچ اعتماد مکن؛ روی پای خود بایست و واقع‌بین باش.'],
    [69, "در خرابات مغان گر گذر افتد بازم  \\n  حاصل خرقه و سجاده روان دربازم", 'از قید تعلقات بیهوده رها شو؛ صداقت و سادگی تو را به رستگاری می‌رساند.'],
    [70, "ما نگوییم بد و میل به ناحق نکنیم  \\n  جامه کس سیه و دلق خود ازرق نکنیم", 'پاک‌دست و راست‌کردار بمان؛ پاداش قلب سلیم خود را به زودی خواهی دید.'],
    [71, "بیا تا گل برافشانیم و می در ساغر اندازیم  \\n  فلک را سقف بشکافیم و طرحی نو دراندازیم", 'وقت آغاز دوباره و تصمیم‌های شجاعانه است؛ طرحی نو درانداز و تردید مکن.'],
    [72, "ساقی بیا که یار ز رخ پرده برگرفت  \\n  کار چراغ‌خلوتیان باز درگرفت", 'حقیقت آشکار می‌شود و موانع کنار می‌روند؛ به زودی پیروزی را جشن می‌گیری.'],
    [73, "در دیر مغان آمد یارم قدحی در دست  \\n  مست از می و میخواران از نرگس مستش مست", 'دیداری غیرمنتظره و سرشار از شوق در پیش داری؛ غم از دلت رخت برمی‌بندد.'],
    [74, "مژده وصل تو کو کز سر جان برخیزم  \\n  طایر قدسم و از دام جهان برخیزم", 'همتت بلند است؛ از بند تعلقات کوچک رها شو تا به اوج مقصود برسی.'],
    [75, "شاه شمشادقدان خسرو شیرین‌دهنان  \\n  که به مژگان شکند قلب همه صف‌شکنان", 'رقیبان و موانع توان مقابله با تو را ندارند؛ پیروزی در این رقابت با توست.'],
    [76, "ساقیا آمدن عید مبارک بادت  \\n  وان مواعید که دادی مرواد از یادت", 'وفای به عهد نزدیک است؛ مژده‌ای فرخنده و مبارک در روزهای آتی دریافت می‌کنی.'],
    [77, "صوفی ار باده به اندازه خورد نوشش باد  \\n  ور نه اندیشه این کار فراموشش باد", 'در هر کاری میانه‌روی را حفظ کن؛ افراط و تفریط مایه پشیمانی خواهد شد.'],
    [78, "سحرم هاتف میخانه به دولت‌خواهی  \\n  گفت بازآی که دیرینه این درگاهی", 'به اصل و ریشه خود بازگرد؛ درهای رحمت به روی تو گشوده است.'],
    [79, "بیا که رایت منصور پادشاه رسید  \\n  نوید فتح و بشارت به مهر و ماه رسید", 'فتح و پیروزی در کار است؛ بشارت موفقیت در پروژه‌ای مهم به تو می‌رسد.'],
    [80, "ای در رخ تو پیدا انوار پادشاهی  \\n  در فکرت تو پنهان صد حکمت الهی", 'استعداد فراوانی داری؛ به دانش و توانمندی خود اعتماد کن و پیش برو.'],
    [81, "ای پادشه خوبان داد از غم تنهایی  \\n  دل بی تو به جان آمد وقت است که بازآیی", 'دوران تنهایی و غربت به سر آمده است؛ شادی و وصال به درگاهت می‌آید.'],
    [82, "بیا و کشتی ما در شط شراب انداز  \\n  خروش و ولوله در جان شیخ و شاب انداز", 'شجاعت به خرج بده و از تغییرات مثبت نترس؛ تحولی پرانرژی در راه است.'],
    [83, "به مژگان سیه کردی هزاران رخنه در دینم  \\n  بیا کز چشم بیمارت هزاران درد برچینم", 'برای هدفت فداکاری لازم است؛ پاداش این وفاداری و محبت را خواهی دید.'],
    [84, "ز در درآ و شبستان ما منور کن  \\n  هوای مجلس روحانیان معطر کن", 'ورود شخص یا فرصتی نیکو خانه‌ات را روشن می‌کند؛ مقدمش را گرامی بدار.'],
    [85, "یارم چو قدح به دست گیرد  \\n  بازار بتان شکست گیرد", 'با آشکار شدن شایستگی‌هایت، رقبا میدان را به تو واگذار خواهد کرد.'],
    [86, "صوفی گلی بچین و مرقع به خار بخش  \\n  وین رسم زهد خشک به ساغرگسار بخش", 'تعصبات بیهوده را کنار بگذار و از لحظات عمر برای شاد کردن خود و دیگران بهره ببر.'],
    [87, "دیدی ای دل که غم عشق دگربار چه کرد  \\n  چون بشد دلبر و با یار وفادار چه کرد", 'تجارب گذشته چراغ راه آینده است؛ احتیاط کن اما هرگز امیدت را از دست مده.'],
    [88, "شهریست پرظریفان و از هر طرف نگاری  \\n  یاران صلای عشق است گر می‌کنید کاری", 'فرصت‌های متعدد پیش روی توست؛ یکی را برگزین و با اراده پیگیری کن.'],
    [89, "هر که شد محرم دل در حرم یار بماند  \\n  وان که این کار ندانست در انکار بماند", 'رازدار و امانت‌دار باش تا در زمره معتمدان و خاصان درآیی.'],
    [90, "بی مهر رخت روز مرا نور نمانده‌ست  \\n  وز عمر مرا جز شب دیجور نمانده‌ست", 'صبوری کن که پس از این شب تاریک، پگاه روشن و پرامید فرامی‌رسد.'],
    [91, "گر تیغ بارد در کوی آن ماه  \\n  گردن نهادیم الحکم لله", 'تسلیم تقدیر خیر الهی باش؛ در برابر موانع پایدار بمان تا رستگار شوی.'],
    [92, "رو بر رهش نهادم و بر من گذر نکرد  \\n  صد لطف چشم داشتم و یک نظر نکرد", 'زود ناامید نشو؛ با اصرار و پایداری بر هدف، بالاخره توجه مطلوب را جلب می‌کنی.'],
    [93, "ز کوی یار می‌آید نسیم باد نوروزی  \\n  از این باد ار مدد خواهی چراغ دل برافروزی", 'فرصتی طلایی مهیا شده است؛ از این نسیم رحمت برای جهش زندگی‌ات کمک بگیر.'],
    [94, "کی شعر تر انگیزد خاطر که حزین باشد  \\n  یک نکته در این معنی گفتیم و همین باشد", 'ابتدا آرامش روح خود را به دست بیاور؛ با دلی شاداب به موفقیت‌های بزرگ می‌رسی.'],
    [95, "اگر به مذهب تو خون عاشق است مباح  \\n  صلاح ما همه آن است کان تو راست صلاح", 'کارها را با توکل و تسلیم به صلاح مقدر بسپار تا بهترین نتیجه حاصل شود.'],
    [96, "دل و دینم شد و دلبر به ملامت برخاست  \\n  گفت با ما منشین کز تو سلامت برخاست", 'در مسیر هدف حرف دیگران را نادیده بگیر؛ استقامت نشان بده تا به مقصود برسی.'],
    [97, "طالع اگر مدد دهد دامنش آورم به کف  \\n  گر بکشم زهی طرب ور بکشد زهی شرف", 'با توکل بر خدا و عزم جزم حرکت کن؛ بخت و تدبیر تو را به سرمنزل می‌رساند.'],
    [98, "آن شب قدری که گویند اهل خلوت امشب است  \\n  یا رب این تأثیر دولت در کدامین کوکب است", 'زمان استجابت دعا و تصمیم‌های سرنوشت‌ساز است؛ این لحظه را از دست مده.'],
    [99, "فاش می‌گویم و از گفته خود دلشادم  \\n  بنده عشقم و از هر دو جهان آزادم", 'با سربلندی و آزادگی مسیرت را طی کن؛ آرامش واقعی در رهایی از ترس‌هاست.'],
    [100, "سحر با باد می‌گفتم حدیث آرزومندی  \\n  خطاب آمد که واثق شو به الطاف خداوندی", 'دلت را به مهربانی و بخشش پروردگار گرم دار؛ دعایت شنیده شده و پاسخ در راه است.'],
];
// is_active_migration — ستون فعال/غیرفعال برای جوایز و ابیات
foreach (['prizes', 'hafez_verses'] as $_mig_tbl) {
    $_mig_col = $conn->query("SHOW COLUMNS FROM `" . $_mig_tbl . "` LIKE 'is_active'");
    if (!$_mig_col || $_mig_col->num_rows === 0) {
        $conn->query("ALTER TABLE `" . $_mig_tbl . "` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1");
    }
}

// verse_m1/m2_migration — جدول قدیمی تک‌ستونه (verse_text) به دو ستونه
foreach (['verse_m1', 'verse_m2'] as $_mc) {
    $_chk = $conn->query("SHOW COLUMNS FROM `hafez_verses` LIKE '" . $_mc . "'");
    if (!$_chk || $_chk->num_rows === 0) {
        $conn->query("ALTER TABLE `hafez_verses` ADD COLUMN `" . $_mc . "` TEXT NULL");
    }
}
$_has_vt = $conn->query("SHOW COLUMNS FROM `hafez_verses` LIKE 'verse_text'");
if ($_has_vt && $_has_vt->num_rows > 0) {
    do {
        $_mig = $conn->query("SELECT id, verse_text FROM hafez_verses WHERE (verse_m1 IS NULL OR verse_m1 = '') AND verse_text IS NOT NULL AND verse_text <> '' LIMIT 100");
        if ($_mig && $_mig->num_rows > 0) {
            while ($_r = $_mig->fetch_assoc()) {
                $_p = split_verse_text($_r['verse_text']);
                if ($_p[0] === '' && $_p[1] === '') { $_p[0] = trim($_r['verse_text']); }
                $_up = $conn->prepare("UPDATE hafez_verses SET verse_m1 = ?, verse_m2 = ? WHERE id = ?");
                $_up->bind_param("ssi", $_p[0], $_p[1], $_r['id']);
                $_up->execute();
            }
        }
    } while ($_mig && $_mig->num_rows > 0);
    $conn->query("ALTER TABLE `hafez_verses` DROP COLUMN `verse_text`");
}
$_m1nn = $conn->query("SHOW COLUMNS FROM `hafez_verses` LIKE 'verse_m1'")->fetch_assoc();
if ($_m1nn && stripos($_m1nn['Null'] ?? '', 'YES') !== false) {
    $conn->query("ALTER TABLE `hafez_verses` MODIFY `verse_m1` TEXT NOT NULL");
}
$_m2nn = $conn->query("SHOW COLUMNS FROM `hafez_verses` LIKE 'verse_m2'")->fetch_assoc();
if ($_m2nn && stripos($_m2nn['Null'] ?? '', 'YES') !== false) {
    $conn->query("ALTER TABLE `hafez_verses` MODIFY `verse_m2` TEXT NOT NULL");
}
// حذف ستون شماره غزل — جایگزین: id
$_has_gn = $conn->query("SHOW COLUMNS FROM `hafez_verses` LIKE 'ghazal_number'");
if ($_has_gn && $_has_gn->num_rows > 0) {
    $conn->query("ALTER TABLE `hafez_verses` DROP COLUMN `ghazal_number`");
}

$h_check = $conn->query("SELECT COUNT(*) AS cnt FROM hafez_verses")->fetch_assoc()['cnt'];
if (getenv('SITAK_SEED_DEMO_DATA') === '1' && $h_check < 5) {
    foreach ($hafez_sample as $hv) {
        $_sm = preg_split('/\s*\\\\n\s*|\s*\n\s*/u', $hv[1], 2);
        $_m1 = trim($_sm[0] ?? '');
        $_m2 = trim($_sm[1] ?? '');
        $st = $conn->prepare("INSERT IGNORE INTO hafez_verses (verse_m1, verse_m2, meaning_short) VALUES (?, ?, ?)");
        $st->bind_param("sss", $_m1, $_m2, $hv[2]);
        $st->execute();
    }
}

 // ── نمونه محصولات ──
 $products_sample = [
     ['نوشیدنی‌های گرم', 'نوشیدنی گرم ۱', 'نوشیدنی کلاسیک با طعم قوی و اروماتیک', 45000, 1, 1],
     ['نوشیدنی‌های گرم', 'نوشیدنی گرم ۲', 'نوشیدنی سبک با آب دم‌گرفته', 40000, 1, 2],
     ['نوشیدنی‌های گرم', 'نوشیدنی گرم ۳', 'نوشیدنی با شیر دم‌کرده و فوم شیر', 55000, 1, 3],
     ['نوشیدنی‌های گرم', 'نوشیدنی گرم ۴', 'نوشیدنی با شیر دم‌کرده زیاد', 60000, 1, 4],
     ['نوشیدنی‌های گرم', 'نوشیدنی گرم ۵', 'نوشیدنی با شیر، شکلات و کرم', 65000, 1, 5],
     ['نوشیدنی‌های سرد', 'نوشیدنی سرد ۱', 'نوشیدنی سرد با یخ و شیر', 55000, 1, 1],
     ['نوشیدنی‌های سرد', 'نوشیدنی سرد ۲', 'نوشیدنی دمی سرد ۱۸ ساعته', 60000, 1, 2],
     ['دسرها', 'تیرامیسو', 'دسر کلاسیک ایتالیایی با ماسکارپنه و کاکائو', 85000, 1, 1],
     ['دسرها', 'چیز کیک', 'نیویورک چیز کیک با سس توت فرنگی', 75000, 1, 2],
     ['دسرها', 'کیک شکلاتی', 'کیک شکلاتی بلژیکی با گاناش', 70000, 1, 3],
     ['صبحانه', 'املت سبزیجات', 'املت با فلفل، پیاز، مارچوبا و پنیر', 50000, 1, 1],
     ['صبحانه', 'توست پنیری', 'توست با پنیر چدار، سس مخصوص و سبزیجات', 40000, 1, 2],
 ];
 $p_check = $conn->query("SELECT COUNT(*) AS cnt FROM products")->fetch_assoc()['cnt'];
 if (getenv('SITAK_SEED_DEMO_DATA') === '1' && $p_check < 5) {
     foreach ($products_sample as $ps) {
         $st = $conn->prepare("INSERT IGNORE INTO products (category, name, description, price, is_available, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
         $st->bind_param("sssiis", $ps[0], $ps[1], $ps[2], $ps[3], $ps[4], $ps[5]);
         $st->execute();
     }
 }

 // ── seed دسته‌بندی‌های فروشگاه از محصولات موجود ──
 $sc_check = $conn->query("SELECT COUNT(*) AS cnt FROM shop_categories")->fetch_assoc()['cnt'];
 if (getenv('SITAK_SEED_DEMO_DATA') === '1' && $sc_check == 0) {
     $res = $conn->query("SELECT DISTINCT category FROM products WHERE category <> '' ORDER BY category");
     while ($r = $res->fetch_assoc()) {
         $st = $conn->prepare("INSERT IGNORE INTO shop_categories (name, sort_order) VALUES (?, 0)");
         $st->bind_param("s", $r['category']); $st->execute();
     }
 }

 // ── نمونه دسته‌بندی مقالات ──
 $cats_sample = [
     ['داستان‌ها', 'stories', 'داستان‌های جذاب از زندگی روزمره', '#ecb534', 1],
     ['راهنما', 'guide', 'راهنماهای خرید و استفاده از محصولات', '#0a84ff', 2],
     ['سبک زندگی', 'lifestyle', 'مقالات سبک زندگی و سلامت', '#30d158', 3],
 ];
 $c_check = $conn->query("SELECT COUNT(*) AS cnt FROM post_categories")->fetch_assoc()['cnt'];
 if (getenv('SITAK_SEED_DEMO_DATA') === '1' && $c_check < 2) {
     foreach ($cats_sample as $cs) {
         $st = $conn->prepare("INSERT IGNORE INTO post_categories (name, slug, description, color, sort_order) VALUES (?, ?, ?, ?, ?)");
         $st->bind_param("ssssi", $cs[0], $cs[1], $cs[2], $cs[3], $cs[4]);
         $st->execute();
     }
 }

 // ── نمونه مقالات ──
 $posts_sample = [
     [1, 'داستان روز اول', 'sitak-first-day', 'داستان افتتاح و اولین روزها', '<p>ما در یک صبح آفتابی افتتاح شد...</p><p>از لحظه اول، بوی تازه‌ی محصولات فضای فروشگاه را پر کرد.</p><h2>شروع ماجراجویی</h2><p>تیم فروشگاه‌شن با عشق و دلیری این مسیر را آغاز کرد.</p>', 1, 0],
 ];
 $ps_check = $conn->query("SELECT COUNT(*) AS cnt FROM posts")->fetch_assoc()['cnt'];
 if (getenv('SITAK_SEED_DEMO_DATA') === '1' && $ps_check < 3) {
     foreach ($posts_sample as $ps) {
         $st = $conn->prepare("INSERT IGNORE INTO posts (category_id, title, slug, excerpt, body, is_published, views) VALUES (?, ?, ?, ?, ?, ?, ?)");
         $st->bind_param("issssis", $ps[0], $ps[1], $ps[2], $ps[3], $ps[4], $ps[5], $ps[6]);
         $st->execute();
     }
 }

 // مقادیر اولیه پیش‌فرض دیتابیس (شامل تنظیمات جدید کارت وفاداری و اطلاعیه)
$sitak_default_admin_hash = $conn->real_escape_string(sitak_password_hash(bin2hex(random_bytes(24))));
$conn->query("INSERT IGNORE INTO `settings` (`key_name`, `value_data`) VALUES ('reset_hour', '21'), ('reset_hour_home_enabled', '1'), ('reset_hour_chance_enabled', '1'), ('admin_password', '$sitak_default_admin_hash')");
$conn->query("INSERT IGNORE INTO `settings` (`key_name`, `value_data`) VALUES ('loyalty_enabled', '1'), ('loyalty_dice_count', '2');");
$conn->query("INSERT IGNORE INTO `settings` (`key_name`, `value_data`) VALUES ('loyalty_online_order', '0');");
$conn->query("INSERT IGNORE INTO `settings` (`key_name`, `value_data`) VALUES ('wheel_enabled', '1'), ('wheel_home_enabled', '1');");
$conn->query("INSERT INTO `prizes` (`description`, `affiliate_link`, `discount_code`, `win_probability`, `is_local`, `is_void`) SELECT 'پوچ', '', '', 95.00, 0, 1 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `prizes` WHERE `is_void` = 1)");
$conn->query("INSERT IGNORE INTO `settings` (`key_name`, `value_data`) VALUES ('contact_enabled', '1');");
$conn->query("INSERT IGNORE INTO `settings` (`key_name`, `value_data`) VALUES ('contact_instagram', 'https://www.instagram.com/sitak.ir/');");
$conn->query("INSERT IGNORE INTO `settings` (`key_name`, `value_data`) VALUES ('contact_telegram', 'https://t.me/sitak_ir');");
$conn->query("INSERT IGNORE INTO `settings` (`key_name`, `value_data`) VALUES ('contact_youtube', 'https://youtube.com/@sitak');");
$conn->query("INSERT IGNORE INTO `settings` (`key_name`, `value_data`) VALUES ('contact_phone', '');");
$conn->query("INSERT IGNORE INTO `settings` (`key_name`, `value_data`) VALUES ('contact_address', '');");
$conn->query("INSERT IGNORE INTO `settings` (`key_name`, `value_data`) VALUES ('default_theme', 'auto');");
$conn->query("INSERT IGNORE INTO `settings` (`key_name`, `value_data`) VALUES ('licenses_enabled', '1'), ('license1_img', ''), ('license1_link', ''), ('license1_name', ''), ('license1_embed', ''), ('license2_img', ''), ('license2_link', ''), ('license2_name', ''), ('license2_embed', '');");

// ارتقای خودکار ستون value_data برای ذخیره کدهای embed بلند مجوزها
$check_vd = $conn->query("SHOW COLUMNS FROM `settings` LIKE 'value_data'");
if ($check_vd && $check_vd->num_rows > 0) {
    $vd_type = $check_vd->fetch_assoc()['Type'] ?? '';
    if (stripos($vd_type, 'varchar') !== false) {
        $conn->query("ALTER TABLE `settings` MODIFY `value_data` MEDIUMTEXT NULL");
    }
}

// تنظیمات پاپ‌آپ اطلاعیه سایت
$conn->query("INSERT IGNORE INTO `settings` (`key_name`, `value_data`) VALUES ('announcement_enabled', '0');");
$conn->query("INSERT IGNORE INTO `settings` (`key_name`, `value_data`) VALUES ('announcement_message', '');");

// —— تنظیمات نمایش مطالب ( وبلاگ ) ——
$conn->query("INSERT IGNORE INTO `settings` (`key_name`, `value_data`) VALUES ('blog_enabled', '1'), ('home_blog_enabled', '1'), ('home_blog_type', 'latest');");

// —— تنظیمات نمایش فروشگاه ——
$conn->query("INSERT IGNORE INTO `settings` (`key_name`, `value_data`) VALUES ('shop_enabled', '1'), ('home_shop_enabled', '1'), ('home_shop_type', 'best');");
$conn->query("INSERT IGNORE INTO settings (key_name,value_data) VALUES ('store_active_days',''),('store_morning_open',''),('store_morning_close',''),('store_afternoon_open',''),('store_afternoon_close',''),('store_closed_day_message','امروز فروشگاه تعطیل است و امکان ثبت سفارش وجود ندارد.'),('store_closed_time_message','در حال حاضر خارج از ساعت کاری فروشگاه هستیم و امکان ثبت سفارش وجود ندارد.')");
$conn->query("INSERT IGNORE INTO settings (key_name,value_data) VALUES ('store_shipping_province',''),('store_shipping_city','')");
$conn->query("INSERT IGNORE INTO `settings` (`key_name`, `value_data`) VALUES ('zarinpal_enabled', '0'), ('zarinpal_merchant_id', ''), ('zarinpal_sandbox', '1'), ('zarinpal_title', 'پرداخت امن زرین‌پال');");
$conn->query("INSERT IGNORE INTO `settings` (`key_name`, `value_data`) VALUES
('sms_enabled', '0'), ('sms_api_key', ''), ('sms_line_number', ''),
('sms_sandbox', '1'),
('sms_otp_template_id', ''), ('sms_order_template_id', ''),
('sms_status_confirmed_template_id', ''), ('sms_status_delivered_template_id', ''),
('sms_status_cancelled_template_id', ''), ('sms_notify_login', '1'), ('sms_notify_admin_order', '0'), ('sms_admin_phone', ''), ('sms_admin_order_template_id', ''), ('sms_admin_order_params', ''),
('sms_notify_order', '1'), ('sms_notify_status', '1'),
('sms_resend_limit_message', 'در حال حاضر، امکان درخواست دوباره کد وجود ندارد، لطفاً مدتی دیگر تلاش کنید.');");

$conn->query("INSERT IGNORE INTO settings (key_name,value_data) VALUES ('custom_emoji','🌐')");
// بنر هدر: پیش‌فرض روشن و با متن پیش‌فرض
$conn->query("INSERT IGNORE INTO settings (key_name,value_data) VALUES ('hero_enabled','1'), ('hero_title','جفت ۶ بیار، مهمون ما باش!'), ('hero_description','تاس بنداز و شانس دریافت هدایای شگفت‌انگیز رو امتحان کن.')");
$conn->query("INSERT IGNORE INTO settings (key_name,value_data) VALUES ('show_header_emoji','1')");

// —— تنظیمات نمایش شانس (تاس/فال/تب شانس) ——
$conn->query("INSERT IGNORE INTO `settings` (`key_name`, `value_data`) VALUES ('chance_enabled', '1'), ('dice_enabled', '1'), ('dice_home_enabled', '1'), ('hafez_home_enabled', '1'), ('chance_title', 'جفت ۶ بیار، مهمون ما باش!'), ('chance_description', 'تاس بنداز و شانس دریافت هدایای شگفت‌انگیز رو امتحان کن.'), ('hero_title', 'جفت ۶ بیار، مهمون ما باش!'), ('hero_description', 'تاس بنداز و شانس دریافت هدایای شگفت‌انگیز رو امتحان کن.'), ('hero_enabled', '1'), ('blog_page_title', 'وبلاگ'), ('blog_page_description', 'مقاله‌ها، داستان‌ها و معرفی محصولات ما'), ('loyalty_page_title', 'کارت وفاداری شما'), ('loyalty_page_description', 'هر خرید حضوری = 1 امتیاز\nبا شش کردن تاس، مهمان ما باش'), ('shop_page_title', 'منو'), ('shop_page_description', 'محصولات تازه‌ی ما را ببینید و سفارش دهید'), ('orders_page_title', 'سفارشات من'), ('orders_page_description', ''), ('site_name', 'سای‌تک'), ('site_slogan', 'یک وبسایت تک'), ('upload_limit_enabled', '0'), ('upload_max_mb', '100'), ('reset_hour_enabled', '1');");

$conn->query("INSERT IGNORE INTO settings (key_name,value_data) VALUES ('reset_hour_home_enabled','1'),('reset_hour_chance_enabled','1')");

// نشانی سفارشی لاگین پنل ادمین (خالی = رفتار پیش‌فرض admin.php بدون کلید)
$conn->query("INSERT IGNORE INTO `settings` (`key_name`, `value_data`) VALUES ('admin_login_slug', '');");
$conn->query("INSERT IGNORE INTO `settings` (`key_name`, `value_data`) VALUES ('referral_enabled', '1'), ('referral_hint', 'با ثبت شماره معرف، هم شما و هم معرفتان یک شانس اضافه دریافت می‌کنید.'), ('referral_inviter_rolls', '1'), ('referral_invitee_rolls', '1');");

// ── اسکریپت‌های سفارشی هدر (مثل گوگل‌آنالیتیکس، سرچ‌کنسول، کلاریتی) ──
$conn->query("CREATE TABLE IF NOT EXISTS `custom_scripts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(60) NOT NULL,
    `code` MEDIUMTEXT NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;");

// —— جدول پیش‌شماره‌های برندگان فیک ——
$conn->query("CREATE TABLE IF NOT EXISTS `fake_prefixes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `prefix` VARCHAR(10) NOT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    UNIQUE KEY `unique_prefix` (`prefix`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;");

// —— تنظیمات کنترل لیست برندگان ——
$conn->query("INSERT IGNORE INTO `settings` (`key_name`, `value_data`) VALUES ('winners_list_enabled', '1'), ('winners_home_enabled', '1'), ('winners_fake_enabled', '1'), ('fake_interval_hours_min', '2'), ('fake_interval_hours_max', '6'), ('fake_winner_count', '1');");

// اجرای migrationهای شماره‌دار پس از آماده‌سازی ساختار پایه
};
