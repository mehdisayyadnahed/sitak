<?php
// پرداخت کارت‌به‌کارت: تنظیمات (فعال‌سازی، نام صاحب حساب، شماره کارت، شبا، مدت قفل سفارش)،
// ستون‌های سفارش (روش پرداخت، زمان انقضای قفل، اطلاعات واریز کاربر، وضعیت بررسی، flag رزرو موجودی)
// و اطلاعات پیش‌فرض واریزکننده در جدول کاربران.
return static function (mysqli $conn): void {
    $settings = [
        'card_payment_enabled' => '0',
        'card_owner_name'      => '',
        'card_number'          => '',
        'card_iban'            => '',
        'card_lock_minutes'    => '30',
    ];
    $st = $conn->prepare("INSERT IGNORE INTO `settings` (`key_name`, `value_data`) VALUES (?, ?)");
    foreach ($settings as $k => $v) {
        $st->bind_param('ss', $k, $v);
        if (!$st->execute()) throw new RuntimeException($conn->error);
    }

    $add_col = static function (string $table, string $col, string $def) use ($conn): void {
        $chk = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE '" . $conn->real_escape_string($col) . "'");
        if ($chk && $chk->num_rows === 0) {
            if (!$conn->query("ALTER TABLE `{$table}` ADD `{$col}` {$def}")) {
                throw new RuntimeException($conn->error);
            }
        }
    };

    // ── ستون‌های جدول سفارش‌ها ──
    $add_col('orders', 'payment_method',    "VARCHAR(16) NULL DEFAULT NULL AFTER `status`");
    $add_col('orders', 'stock_reserved',    "TINYINT(1) NOT NULL DEFAULT 0 AFTER `payment_method`");
    $add_col('orders', 'card_lock_expires', "DATETIME NULL DEFAULT NULL AFTER `stock_reserved`");
    $add_col('orders', 'card_payer_name',   "VARCHAR(120) NULL DEFAULT NULL AFTER `card_lock_expires`");
    $add_col('orders', 'card_payer_card',   "VARCHAR(32) NULL DEFAULT NULL AFTER `card_payer_name`");
    $add_col('orders', 'card_payer_iban',   "VARCHAR(40) NULL DEFAULT NULL AFTER `card_payer_card`");
    $add_col('orders', 'card_paid_at',      "DATETIME NULL DEFAULT NULL AFTER `card_payer_iban`");
    $add_col('orders', 'card_submitted_at', "DATETIME NULL DEFAULT NULL AFTER `card_paid_at`");
    $add_col('orders', 'card_status',       "VARCHAR(16) NULL DEFAULT NULL AFTER `card_submitted_at`");

    // ── اطلاعات پیش‌فرض واریزکننده در پروفایل کاربر ──
    $add_col('users', 'payer_name', "VARCHAR(120) NULL DEFAULT NULL");
    $add_col('users', 'payer_card', "VARCHAR(32) NULL DEFAULT NULL");
    $add_col('users', 'payer_iban', "VARCHAR(40) NULL DEFAULT NULL");

    // ایندکس سبک برای بررسی سفارش‌های کارت‌به‌کارتِ در انتظار انقضا
    $idx = $conn->query("SHOW INDEX FROM `orders` WHERE Key_name = 'card_expire_idx'");
    if ($idx && $idx->num_rows === 0) {
        $conn->query("ALTER TABLE `orders` ADD INDEX `card_expire_idx` (`status`, `payment_method`, `card_lock_expires`)");
    }
};
