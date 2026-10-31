<?php
// ورود و ثبت‌نام با گذرواژه اختصاصی: ستون گذرواژه کاربر و تنظیمات پیش‌فرض.
return static function (mysqli $conn): void {
    $col = $conn->query("SHOW COLUMNS FROM `users` LIKE 'login_password'");
    if ($col && $col->num_rows === 0) {
        if (!$conn->query("ALTER TABLE `users` ADD COLUMN `login_password` VARCHAR(255) NULL DEFAULT NULL")) throw new RuntimeException($conn->error);
    }
    $conn->query("INSERT IGNORE INTO `settings` (`key_name`, `value_data`) VALUES ('sms_password_login_enabled', '0'), ('sms_password_digits', '6'), ('forgot_password_message', 'برای بازیابی گذرواژه ورود، لطفاً با مدیر سایت تماس بگیرید.')");
};
