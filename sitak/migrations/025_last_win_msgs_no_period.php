<?php
// پیام‌های کارت آخرین برد: حذف نقطه‌ی پایانی از پیش‌فرض‌های قبلی (اگر ذخیره شده باشند).
return static function (mysqli $conn): void {
    $pairs = [
        ['result_last_expired_msg', "🍂 فرصت دریافت تمام شد.", "🍂 فرصت دریافت تمام شد"],
        ['result_last_delivered_msg', "✓ این هدیه تحویل داده شد.", "✓ این هدیه تحویل داده شد"],
    ];
    foreach ($pairs as [$key, $old, $new]) {
        $st = $conn->prepare("UPDATE `settings` SET `value_data` = ? WHERE `key_name` = ? AND `value_data` = ?");
        $st->bind_param('sss', $new, $key, $old);
        if (!$st->execute()) throw new RuntimeException($conn->error);
    }
};
