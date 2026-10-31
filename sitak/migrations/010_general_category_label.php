<?php
// برچسب دسته‌بندی پیش‌فرض فروشگاه از «general» به «عمومی» تغییر می‌کند؛ محصولات و دسته‌های موجود هم به‌روز می‌شوند.
return static function (mysqli $conn): void {
    $st = $conn->prepare("UPDATE IGNORE `shop_categories` SET `name` = 'عمومی' WHERE `name` = 'general'");
    if (!$st || !$st->execute()) throw new RuntimeException($conn->error);
    $st = $conn->prepare("UPDATE `products` SET `category` = 'عمومی' WHERE `category` = 'general'");
    if (!$st || !$st->execute()) throw new RuntimeException($conn->error);
};
