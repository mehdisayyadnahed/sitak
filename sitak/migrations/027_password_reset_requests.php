<?php
// درخواست‌های بازنشانی گذرواژه ورود که کاربر از پاپ‌آپ فراموشی گذرواژه ثبت می‌کند.
return static function (mysqli $conn): void {
    $sql = "CREATE TABLE IF NOT EXISTS `password_reset_requests` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_phone` VARCHAR(15) NOT NULL,
        `status` VARCHAR(10) NOT NULL DEFAULT 'pending',
        `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        `done_at` TIMESTAMP NULL DEFAULT NULL,
        KEY `idx_reset_phone` (`user_phone`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if (!$conn->query($sql)) throw new RuntimeException($conn->error);
};
