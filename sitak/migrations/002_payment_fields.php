<?php
return static function (mysqli $conn): void {
    $columns = [
        "payment_authority VARCHAR(64) NULL",
        "payment_ref_id VARCHAR(64) NULL",
        "payment_paid_at TIMESTAMP NULL",
        "recipient_last_name VARCHAR(100) NULL",
        "recipient_phone VARCHAR(15) NULL",
        "recipient_province VARCHAR(80) NULL",
        "recipient_city VARCHAR(80) NULL",
        "recipient_address VARCHAR(255) NULL",
        "recipient_plaque VARCHAR(20) NULL",
        "recipient_unit VARCHAR(20) NULL",
        "recipient_postal_code VARCHAR(20) NULL",
    ];
    foreach ($columns as $definition) {
        $name = preg_replace('/\s.*$/', '', $definition);
        $check = $conn->query("SHOW COLUMNS FROM `orders` LIKE '" . $conn->real_escape_string($name) . "'");
        if ($check && $check->num_rows === 0 && !$conn->query("ALTER TABLE `orders` ADD `$name` " . substr($definition, strlen($name)))) {
            throw new RuntimeException($conn->error);
        }
    }
};
