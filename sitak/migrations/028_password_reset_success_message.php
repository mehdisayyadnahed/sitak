<?php
// متن پیام پس از ثبت درخواست بازیابی گذرواژه (قابل ویرایش در تنظیمات بخش ثبت‌نام و ورود).
return static function (mysqli $conn): void {
    $st = $conn->prepare("INSERT IGNORE INTO `settings` (`key_name`, `value_data`) VALUES ('password_reset_success_message', ?)");
    $msg = 'درخواست بازیابی گذرواژه شما ثبت شد. مدیر سایت به‌زودی به شما اطلاع می‌دهد.';
    $st->bind_param('s', $msg);
    if (!$st->execute()) throw new RuntimeException($conn->error);
};
