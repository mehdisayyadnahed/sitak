<?php
// توکن‌های «مرا به خاطر بسپار» برای ورود ادمین. فقط هش توکن ذخیره می‌شود.
return static function (mysqli $conn): void {
    $sql = "CREATE TABLE IF NOT EXISTS `admin_remember_tokens` (
        `token_hash` CHAR(64) NOT NULL PRIMARY KEY,
        `created_at` DATETIME NOT NULL,
        `expires_at` DATETIME NOT NULL,
        `last_used_at` DATETIME NULL,
        INDEX `idx_admin_remember_expires` (`expires_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci";
    if (!$conn->query($sql)) throw new RuntimeException($conn->error);
};
