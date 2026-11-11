<?php
require_once 'config.php';
require_once 'zarinpal.php';
require_once 'smsir.php';
require_once __DIR__ . '/webpush.php';

$order_id = (int)($_GET['order'] ?? 0);
$authority = trim((string)($_GET['Authority'] ?? ''));
$status = strtoupper((string)($_GET['Status'] ?? ''));
$fail = function (string $code) {
    header('Location: orders.php?payment=' . ($code === 'cancelled' ? 'cancelled' : 'failed'));
    exit;
};
if ($order_id < 1 || $authority === '' || $status !== 'OK') $fail($status !== 'OK' ? 'cancelled' : 'failed');

$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $order_id); $stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
if (!$order || !hash_equals((string)$order['payment_authority'], $authority)) $fail('failed');
if ($order['status'] === 'confirmed') { header('Location: orders.php?payment=success'); exit; }

try {
    $gateway = new SiTakZarinPal(
        getSetting($conn, 'zarinpal_merchant_id', ''),
        getSetting($conn, 'zarinpal_sandbox', '1') === '1'
    );
    $result = $gateway->verify($authority, (int)$order['total_price']);
    $ref = (string)($result['ref_id'] ?? '');
    $up = $conn->prepare("UPDATE orders SET status='confirmed', payment_ref_id=?, payment_paid_at=NOW() WHERE id=? AND status='pending'");
    $up->bind_param("si", $ref, $order_id); $up->execute();
    if ($up->affected_rows > 0) {
        sitak_stock_consume($conn, $order['items']);
        // flag رزرو موجودی: تا با لغو سفارش، موجودی دوباره آزاد نشود و کسر دوباره هم رخ ندهد
        $fl = $conn->prepare("UPDATE orders SET stock_reserved=1, payment_method='online' WHERE id=?");
        $fl->bind_param('i', $order_id); $fl->execute();
        sitak_notify_admin($conn, 'payments', 'پرداخت آنلاین انجام شد ✅',
            mask_phone((string)$order['user_phone']) . ' سفارش #' . $order_id . ' را پرداخت کرد — ' . number_format((int)$order['total_price']) . ' تومان',
            'admin.php?sec=orders');
    }
    if ($up->affected_rows > 0 && getSetting($conn, 'sms_notify_status', '1') === '1') {
        sitak_sms_send($order['user_phone'], 'sms_status_confirmed_template_id', [
            'OrderId'=>$order_id, 'OrderID'=>$order_id, 'Total'=>number_format((int)$order['total_price'])
        ]);
    }
    if (getSetting($conn, 'loyalty_enabled', '1') === '1' && getSetting($conn, 'loyalty_online_order', '0') === '1') {
        $lv = $conn->prepare("UPDATE users SET visits = visits + 1 WHERE phone = ?");
        $lv->bind_param("s", $order['user_phone']); $lv->execute();
    }
    header('Location: orders.php?payment=success'); exit;
} catch (Throwable $e) {
    error_log('[SiTak ZarinPal verify] ' . $e->getMessage());
    $fail('failed');
}
