<?php
return static function (mysqli $conn): void {
    $settings = [
        'sms_enabled' => '0',
        'sms_api_key' => '',
        'sms_line_number' => '',
        'sms_sandbox' => '1',
        'sms_otp_template_id' => '',
        'sms_order_template_id' => '',
        'sms_status_confirmed_template_id' => '',
        'sms_status_delivered_template_id' => '',
        'sms_status_cancelled_template_id' => '',
        'sms_notify_login' => '1',
        'sms_notify_order' => '1',
        'sms_notify_status' => '1',
        'sms_resend_limit_message' => 'اکنون امکان درخواست مجدد کد وجود ندارد، لطفاً مدتی دیگر تلاش کنید.',
    ];
    $stmt = $conn->prepare("INSERT IGNORE INTO settings (key_name, value_data) VALUES (?, ?)");
    foreach ($settings as $key => $value) {
        $stmt->bind_param("ss", $key, $value);
        if (!$stmt->execute()) throw new RuntimeException($stmt->error);
    }
};
