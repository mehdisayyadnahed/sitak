<?php
// محدودیت جایزه: تعداد (qty_limit + شمارنده‌ی بردهای واقعی qty_used) و مهلت روزانه (time_limit_days از time_started_at)
return static function (mysqli $conn): void {
    $cols = [
        'qty_limit'       => "ADD `qty_limit` INT NULL DEFAULT NULL",
        'qty_used'        => "ADD `qty_used` INT NOT NULL DEFAULT 0",
        'time_limit_days' => "ADD `time_limit_days` INT NULL DEFAULT NULL",
        'time_started_at' => "ADD `time_started_at` DATETIME NULL DEFAULT NULL",
    ];
    foreach ($cols as $name => $def) {
        $check = $conn->query("SHOW COLUMNS FROM `prizes` LIKE '$name'");
        if ($check && $check->num_rows === 0 && !$conn->query("ALTER TABLE `prizes` $def")) {
            throw new RuntimeException($conn->error);
        }
    }
};
