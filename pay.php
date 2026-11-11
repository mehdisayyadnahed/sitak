<?php
require_once 'config.php';
require_once 'zarinpal.php';

$phone = ($_SESSION['sitak_user'] ?? '');
$order_id = (int)($_GET['order'] ?? 0);
if (!preg_match('/^09[0-9]{9}$/', $phone) || $order_id < 1) {
    header('Location: orders.php'); exit;
}
$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_phone = ? LIMIT 1");
$stmt->bind_param("is", $order_id, $phone); $stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
if (!$order || $order['status'] !== 'pending' || ($order['payment_method'] ?? '') === 'card' || getSetting($conn, 'zarinpal_enabled', '0') !== '1') {
    header('Location: orders.php'); exit;
}
try {
    $base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
        . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
        . rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/pay.php')), '/');
    $gateway = new SiTakZarinPal(
        getSetting($conn, 'zarinpal_merchant_id', ''),
        getSetting($conn, 'zarinpal_sandbox', '1') === '1'
    );
    $authority = $gateway->request((int)$order['total_price'], $base . '/zarinpal-callback.php?order=' . $order_id, 'سفارش شماره ' . $order_id, $phone);
    $up = $conn->prepare("UPDATE orders SET payment_authority = ? WHERE id = ?");
    $up->bind_param("si", $authority, $order_id); $up->execute();
    header('Location: ' . $gateway->startUrl($authority)); exit;
} catch (Throwable $e) {
    header('Location: orders.php?payment=error'); exit;
}
