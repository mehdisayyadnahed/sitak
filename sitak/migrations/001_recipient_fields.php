<?php
return static function (mysqli $conn): void {
    $columns = [
        "last_name VARCHAR(100) NULL AFTER `name`",
        "province VARCHAR(80) NULL AFTER `address`",
        "city VARCHAR(80) NULL AFTER `province`",
        "plaque VARCHAR(20) NULL AFTER `city`",
        "unit VARCHAR(20) NULL AFTER `plaque`",
        "postal_code VARCHAR(20) NULL AFTER `unit`",
        "extra_notes TEXT NULL AFTER `postal_code`",
    ];
    foreach ($columns as $definition) {
        $name = preg_replace('/\s.*$/', '', $definition);
        $check = $conn->query("SHOW COLUMNS FROM `users` LIKE '" . $conn->real_escape_string($name) . "'");
        if ($check && $check->num_rows === 0 && !$conn->query("ALTER TABLE `users` ADD `$name` " . substr($definition, strlen($name)))) {
            throw new RuntimeException($conn->error);
        }
    }
};
