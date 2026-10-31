<?php
return static function (mysqli $conn): void {
    $check = $conn->query("SHOW COLUMNS FROM `orders` LIKE 'shipping_fee'");
    if ($check && $check->num_rows === 0 && !$conn->query("ALTER TABLE `orders` ADD `shipping_fee` INT NOT NULL DEFAULT 0")) {
        throw new RuntimeException($conn->error);
    }
};
