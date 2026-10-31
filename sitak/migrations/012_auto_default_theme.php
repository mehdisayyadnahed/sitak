<?php
// تم پیش‌فرض سایت «خودکار» می‌شود؛ فقط اگر هنوز همان مقدار قبلی «روشن» باشد تغییر می‌کند.
return static function (mysqli $conn): void {
    $st = $conn->prepare("UPDATE `settings` SET `value_data` = 'auto' WHERE `key_name` = 'default_theme' AND `value_data` = 'light'");
    if (!$st || !$st->execute()) throw new RuntimeException($conn->error);
};
