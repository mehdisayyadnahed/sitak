<?php
// 032: تنظیمات صدای اعلان، حالت نمایش پیام (پاپ‌آپ/toast/خاموش) و نوع «پرداخت‌ها»
return function ($conn) {
    $defaults = [
        'admin_notify_payments' => '1',
        'admin_notify_sound'    => '1',
        'admin_notify_display'  => 'popup',
    ];
    $st = $conn->prepare("INSERT IGNORE INTO settings (key_name, value_data) VALUES (?, ?)");
    foreach ($defaults as $k => $v) {
        $st->bind_param('ss', $k, $v);
        $st->execute();
    }
};
