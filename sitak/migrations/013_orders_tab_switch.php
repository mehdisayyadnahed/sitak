<?php
// تب سفارشات جدا از تب منو شد. مقدار قبلی (که هر دو را با هم کنترل می‌کرد) برای تب سفارشات کپی می‌شود تا رفتار سایت عوض نشود.
return static function (mysqli $conn): void {
    $st = $conn->prepare("SELECT value_data FROM `settings` WHERE `key_name` = 'shop_enabled' LIMIT 1");
    if (!$st || !$st->execute()) throw new RuntimeException($conn->error);
    $row = $st->get_result()->fetch_assoc();
    $val = ($row && $row['value_data'] === '0') ? '0' : '1';
    $ins = $conn->prepare("INSERT IGNORE INTO `settings` (`key_name`, `value_data`) VALUES ('orders_tab_enabled', ?)");
    $ins->bind_param("s", $val);
    if (!$ins->execute()) throw new RuntimeException($conn->error);
};
