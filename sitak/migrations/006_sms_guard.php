<?php
return static function (mysqli $conn): void {
    $sql = "CREATE TABLE IF NOT EXISTS `sms_guard` (
        `k` VARCHAR(100) NOT NULL PRIMARY KEY,
        `fails` INT NOT NULL DEFAULT 0,
        `locked_until` INT NOT NULL DEFAULT 0,
        `updated_at` INT NOT NULL DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if (!$conn->query($sql)) throw new RuntimeException($conn->error);
};
