<?php
// متن‌های کارت وفاداری: اگر خالی ذخیره شده باشند، مقدار پیش‌فرض گذاشته می‌شود تا روی صفحات خالی نماند.
return static function (mysqli $conn): void {
    $defaults = [
        'loyalty_card_hint' => "تاس رو به شش تبدیل کن و از ما جایزه بگیر",
        'loyalty_page_description' => "هر خرید حضوری = 1 امتیاز\nبا شش کردن تاس، مهمان ما باش",
    ];
    foreach ($defaults as $key => $val) {
        $st = $conn->prepare("UPDATE `settings` SET `value_data` = ? WHERE `key_name` = ? AND TRIM(`value_data`) = ''");
        $st->bind_param('ss', $val, $key);
        if (!$st->execute()) throw new RuntimeException($conn->error);
    }
};
