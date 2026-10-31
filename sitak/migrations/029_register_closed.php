<?php
// بستن ثبت‌نام: فقط مدیر بتواند کاربر جدید بسازد (سوئیچ + متن پیام پاپ‌آپ «تماس با مدیر»).
return static function (mysqli $conn): void {
    $conn->query("INSERT IGNORE INTO `settings` (`key_name`, `value_data`) VALUES ('register_closed', '0')");
    $st = $conn->prepare("INSERT IGNORE INTO `settings` (`key_name`, `value_data`) VALUES ('register_closed_message', ?)");
    $msg = "ثبت‌نام در سایت فعلاً فقط توسط مدیر انجام می‌شود.\nبرای ساخت حساب کاربری، لطفاً با مدیر سایت تماس بگیرید.";
    $st->bind_param('s', $msg);
    if (!$st->execute()) throw new RuntimeException($conn->error);
};
