<?php
// رنگ هر جایزه روی قاچ گردونه: ستون color + تخصیص رنگ تصادفی به جوایز موجود
return static function (mysqli $conn): void {
    $has = $conn->query("SHOW COLUMNS FROM `prizes` LIKE 'color'");
    if ($has && $has->num_rows === 0) {
        if (!$conn->query("ALTER TABLE `prizes` ADD `color` VARCHAR(7) NULL")) throw new RuntimeException($conn->error);
    }
    $palette = ['#e8a33d', '#d9534f', '#5b7fd1', '#3aa88f', '#9b6bd0', '#e0729a', '#4fa3c7', '#f0b35a'];
    $res = $conn->query("SELECT id FROM `prizes` WHERE `is_void` = 0 AND (`color` IS NULL OR `color` = '')");
    if (!$res) throw new RuntimeException($conn->error);
    while ($row = $res->fetch_assoc()) {
        $c = $palette[array_rand($palette)];
        $st = $conn->prepare("UPDATE `prizes` SET `color` = ? WHERE `id` = ?");
        $id = (int)$row['id'];
        $st->bind_param("si", $c, $id);
        if (!$st->execute()) throw new RuntimeException($st->error);
    }
};
