<?php
// اعلان‌های مدیر: سوئیچ‌های اعلان، متن پاپ‌آپ کارت‌به‌کارت، سوئیچ «پرداخت پس از هماهنگی»،
// جدول رویدادهای مدیر (برای polls و سابقه) و جدول اشتراک‌های Web Push.
return static function (mysqli $conn): void {
    $popup_default = "مبلغ را به یکی از شماره‌های زیر واریز کن، سپس اطلاعات واریز را در صفحه‌ی سفارش‌ها ثبت کن؛ اگر تا پایان مهلت ثبت نکنی، سفارش بطور خودکار لغو می‌شود.";
    $settings = [
        'admin_notify_enabled'  => '1',
        'admin_notify_orders'   => '1',
        'admin_notify_winners'  => '1',
        'admin_notify_users'    => '1',
        'admin_notify_resets'   => '1',
        'pending_method_enabled' => '0',
        'card_popup_text'       => $popup_default,
    ];
    $st = $conn->prepare("INSERT IGNORE INTO `settings` (`key_name`, `value_data`) VALUES (?, ?)");
    foreach ($settings as $k => $v) {
        $st->bind_param('ss', $k, $v);
        if (!$st->execute()) throw new RuntimeException($conn->error);
    }

    $conn->query("CREATE TABLE IF NOT EXISTS `admin_events` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `type` VARCHAR(20) NOT NULL DEFAULT 'orders',
        `title` VARCHAR(150) NOT NULL,
        `body` VARCHAR(255) NULL,
        `url` VARCHAR(150) NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX (`id`),
        INDEX (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS `push_subscriptions` (
        `endpoint_hash` CHAR(32) PRIMARY KEY,
        `endpoint` TEXT NOT NULL,
        `p256dh` VARCHAR(255) NOT NULL,
        `auth` VARCHAR(255) NOT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci");
};
