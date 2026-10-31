<?php
return static function (mysqli $conn): void {
    $check = $conn->query("SHOW COLUMNS FROM `prizes` LIKE 'is_instant'");
    if ($check && $check->num_rows === 0 && !$conn->query("ALTER TABLE `prizes` ADD `is_instant` TINYINT(1) NOT NULL DEFAULT 0")) {
        throw new RuntimeException($conn->error);
    }
};
