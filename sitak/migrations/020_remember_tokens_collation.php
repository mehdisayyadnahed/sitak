<?php
// جدول توکن‌ها باید همان collation جدول users را داشته باشد، وگرنه مقایسه‌ی phone خطا می‌دهد.
return static function (mysqli $conn): void {
    if (!$conn->query("ALTER TABLE `user_remember_tokens` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_persian_ci")) {
        throw new RuntimeException($conn->error);
    }
};
