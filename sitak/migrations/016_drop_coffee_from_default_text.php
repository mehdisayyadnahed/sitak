<?php
// متن پیش‌فرض بنر و گردونه: عبارت «قهوه‌های خوش‌طعم و» از مقادیر ذخیره‌شده‌ی قبلی حذف می‌شود.
return static function (mysqli $conn): void {
    $st = $conn->prepare("UPDATE `settings` SET `value_data` = REPLACE(`value_data`, 'قهوه‌های خوش‌طعم و ', '') WHERE `value_data` LIKE '%قهوه‌های خوش‌طعم و %'");
    if (!$st || !$st->execute()) throw new RuntimeException($conn->error);
};
