<?php
return static function (mysqli $conn): void {
    $old = $conn->query("SHOW COLUMNS FROM `products` LIKE 'local_product'");
    if (!$old || $old->num_rows === 0) return;
    $new = $conn->query("SHOW COLUMNS FROM `products` LIKE 'onsite_product'");
    if ($new && $new->num_rows === 0) {
        $def = $old->fetch_assoc();
        $null = ($def['Null'] ?? 'YES') === 'YES' ? 'NULL' : 'NOT NULL';
        $default = $def['Default'] === null ? '' : " DEFAULT '" . $conn->real_escape_string((string)$def['Default']) . "'";
        if (!$conn->query("ALTER TABLE `products` CHANGE `local_product` `onsite_product` " . $def['Type'] . " " . $null . $default)) throw new RuntimeException($conn->error);
        return;
    }
    // ستون جدید از قبل ساخته شده: داده‌ها منتقل و ستون قدیمی حذف می‌شود
    if (!$conn->query("UPDATE `products` SET `onsite_product` = `local_product`")) throw new RuntimeException($conn->error);
    if (!$conn->query("ALTER TABLE `products` DROP COLUMN `local_product`")) throw new RuntimeException($conn->error);
};
