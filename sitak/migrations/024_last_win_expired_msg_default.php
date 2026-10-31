<?php
// پیام کارت آخرین برد: اگر مقدار ذخیره‌شده همان پیش‌فرض قبلی باشد، متن جدید گذاشته می‌شود.
return static function (mysqli $conn): void {
    $old = "🍂 فرصت تحویل تمام شد";
    $new = "🍂 فرصت دریافت تمام شد.";
    $st = $conn->prepare("UPDATE `settings` SET `value_data` = ? WHERE `key_name` = 'result_last_expired_msg' AND `value_data` = ?");
    $st->bind_param('ss', $new, $old);
    if (!$st->execute()) throw new RuntimeException($conn->error);
};
