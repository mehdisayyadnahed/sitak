<?php
// رنگ جوایز به‌صورت چرخشی (ترتیب id) تخصیص داده می‌شود؛ نشانگر رنگ بعدی در settings نگه داشته می‌شود
return static function (mysqli $conn): void {
    $palette = ['#e8a33d', '#d9534f', '#5b7fd1', '#3aa88f', '#9b6bd0', '#e0729a', '#4fa3c7', '#f0b35a'];
    $res = $conn->query("SELECT id FROM `prizes` WHERE `is_void` = 0 ORDER BY `id` ASC");
    if (!$res) throw new RuntimeException($conn->error);
    $i = 0;
    while ($row = $res->fetch_assoc()) {
        $c = $palette[$i % count($palette)];
        $id = (int)$row['id'];
        $st = $conn->prepare("UPDATE `prizes` SET `color` = ? WHERE `id` = ?");
        $st->bind_param("si", $c, $id);
        if (!$st->execute()) throw new RuntimeException($st->error);
        $i++;
    }
    $next = (string)$i;
    $st = $conn->prepare("INSERT INTO `settings` (`key_name`, `value_data`) VALUES ('prize_color_next', ?) ON DUPLICATE KEY UPDATE `value_data` = VALUES(`value_data`)");
    $st->bind_param("s", $next);
    if (!$st->execute()) throw new RuntimeException($st->error);
};
