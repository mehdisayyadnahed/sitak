<?php
// متن و سوئیچ بنر هدر صفحه‌ی اصلی: اگر عنوان یا توضیحات خالی ذخیره شده باشد، مقدار پیش‌فرض گذاشته می‌شود و بنر فعال می‌شود.
return static function (mysqli $conn): void {
    $title = 'جفت ۶ بیار، مهمون ما باش!';
    $desc  = 'تاس بنداز و شانس دریافت هدایای شگفت‌انگیز رو امتحان کن.';
    $steps = [
        ["INSERT IGNORE INTO `settings` (`key_name`, `value_data`) VALUES ('hero_title', ?), ('hero_description', ?), ('hero_enabled', '1')", 'ss', [$title, $desc]],
        ["UPDATE `settings` SET `value_data` = ? WHERE `key_name` = 'hero_title' AND TRIM(`value_data`) = ''", 's', [$title]],
        ["UPDATE `settings` SET `value_data` = ? WHERE `key_name` = 'hero_description' AND TRIM(`value_data`) = ''", 's', [$desc]],
        ["UPDATE `settings` SET `value_data` = '1' WHERE `key_name` = 'hero_enabled'", '', []],
    ];
    foreach ($steps as [$sql, $types, $args]) {
        $st = $conn->prepare($sql);
        if (!$st) throw new RuntimeException($conn->error);
        if ($types !== '') $st->bind_param($types, ...$args);
        if (!$st->execute()) throw new RuntimeException($conn->error);
    }
};
