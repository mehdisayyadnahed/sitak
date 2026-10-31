<?php
// جایزه‌ی پیش‌فرض «قهوه دبل اسپرسو رایگان» دیگر ساخته نمی‌شود.
// اگر روی سایت‌های قبلی هنوز دست‌نخورده باقی مانده، فقط غیرفعال می‌شود (حذف نمی‌شود تا برنده‌های قبلی به هم نریزند).
return static function (mysqli $conn): void {
    $st = $conn->prepare("UPDATE `prizes` SET `is_active` = 0 WHERE `id` = 1 AND `description` = 'قهوه دبل اسپرسو رایگان' AND `affiliate_link` = '' AND `discount_code` = ''");
    if (!$st || !$st->execute()) throw new RuntimeException($conn->error);
};
