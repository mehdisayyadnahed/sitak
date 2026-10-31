<?php
// پیام تکمیل کارت وفاداری: اگر مقدار قبلی همان پیش‌فرض قدیمی یا خالی باشد، متن جدید گذاشته می‌شود.
return static function (mysqli $conn): void {
    $old = "🎉 تاس تکمیل شد!\nاین صفحه رو به ما نشون بده تا جایزه‌ات رو بگیری!";
    $new = "🎉 تاس تکمیل شد! این صفحه رو به ما نشون بده تا هدیه‌ت رو بگیری!";
    $st = $conn->prepare("UPDATE `settings` SET `value_data` = ? WHERE `key_name` = 'loyalty_complete_msg' AND (`value_data` = ? OR TRIM(`value_data`) = '')");
    $st->bind_param('ss', $new, $old);
    if (!$st->execute()) throw new RuntimeException($conn->error);
};
