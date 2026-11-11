<?php
/* ═══════════════════════════════════════════════════════════
   webpush.php — اعلان مرورگری مدیر (Web Push) بدون وابستگی خارجی
   - کلیدهای VAPID یک‌بار تولید و در settings ذخیره می‌شوند
   - رمزنگاری بار طبق RFC 8291 (aes128gcm) و امضای JWT طبق RFC 8292 (ES256)
   - رویدادها در جدول admin_events ثبت می‌شوند تا صفحه‌های باز پنل هم
     با polls کوتاه، اعلان فوری (به‌همراه صدا) بگیرند.
   ═══════════════════════════════════════════════════════════ */

function sitak_b64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}
function sitak_b64url_decode($data) {
    $pad = strlen($data) % 4;
    if ($pad) $data .= str_repeat('=', 4 - $pad);
    return base64_decode(strtr($data, '-_', '+/'));
}

// کلیدهای VAPID (تولید خودکار در اولین نیاز)
function sitak_vapid_keys($conn) {
    $pub = (string)getSetting($conn, 'vapid_public_key', '');
    $priv = (string)getSetting($conn, 'vapid_private_key', '');
    if ($pub !== '' && $priv !== '') return [$pub, $priv];
    if (!function_exists('openssl_pkey_new')) return ['', ''];
    $res = @openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    if (!$res) return ['', ''];
    $privPem = '';
    if (!@openssl_pkey_export($res, $privPem)) return ['', ''];
    $details = openssl_pkey_get_details($res);
    if (!isset($details['ec']['x'], $details['ec']['y'])) return ['', ''];
    $pubRaw = "\x04" . str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT) . str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT);
    $pub = sitak_b64url_encode($pubRaw);
    foreach (['vapid_public_key' => $pub, 'vapid_private_key' => $privPem] as $k => $v) {
        $st = $conn->prepare("INSERT INTO settings (key_name, value_data) VALUES (?, ?) ON DUPLICATE KEY UPDATE value_data = VALUES(value_data)");
        $st->bind_param('ss', $k, $v); $st->execute();
    }
    return [$pub, $privPem];
}

// امضای DER → r||s خام (۶۴ بایت) برای ES256
function sitak_der_sig_to_raw($der) {
    $hex = bin2hex($der);
    if (substr($hex, 0, 2) !== '30') return str_pad($der, 64, "\0");
    $pos = 4; $vals = [];
    for ($i = 0; $i < 2; $i++) {
        if (substr($hex, $pos, 2) !== '02') break;
        $pos += 2;
        $len = hexdec(substr($hex, $pos, 2)) * 2; $pos += 2;
        $v = substr($hex, $pos, $len); $pos += $len;
        if (strlen($v) > 64) $v = substr($v, -64);
        $vals[] = str_pad($v, 64, '0', STR_PAD_LEFT);
    }
    return count($vals) === 2 ? hex2bin($vals[0] . $vals[1]) : str_pad($der, 64, "\0");
}

function sitak_vapid_jwt($privPem, $audience, $subject) {
    $header = sitak_b64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
    $payload = sitak_b64url_encode(json_encode(['aud' => $audience, 'exp' => time() + 43200, 'sub' => $subject]));
    $signingInput = $header . '.' . $payload;
    $key = openssl_pkey_get_private($privPem);
    if (!$key) return '';
    $sig = '';
    if (!openssl_sign($signingInput, $sig, $key, OPENSSL_ALGO_SHA256)) return '';
    return $signingInput . '.' . sitak_b64url_encode(sitak_der_sig_to_raw($sig));
}

// ساخت PEM کلید عمومی EC از نقطه‌ی فشرده‌نشده (۶۵ بایت)
function sitak_ec_public_pem($point) {
    $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $point;
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
}

// ارسال یک پیام push به یک اشتراک؛ کد وضعیت HTTP برمی‌گرداند (0 = خطای محلی)
function sitak_webpush_send($conn, $endpoint, $p256dh, $auth, $payload) {
    [$vapidPub, $vapidPriv] = sitak_vapid_keys($conn);
    if ($vapidPub === '' || $vapidPriv === '' || !function_exists('curl_init') || !function_exists('hash_hkdf')) return 0;
    $uaPublic = sitak_b64url_decode($p256dh);
    $authSecret = sitak_b64url_decode($auth);
    if (strlen($uaPublic) !== 65 || strlen($authSecret) !== 16) return 0;
    $eph = @openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    if (!$eph) return 0;
    $ephDetails = openssl_pkey_get_details($eph);
    $ephPublic = "\x04" . str_pad($ephDetails['ec']['x'], 32, "\0", STR_PAD_LEFT) . str_pad($ephDetails['ec']['y'], 32, "\0", STR_PAD_LEFT);
    $shared = @openssl_pkey_derive(sitak_ec_public_pem($uaPublic), $eph, 32);
    if ($shared === false) return 0;
    $salt = random_bytes(16);
    $prk = hash_hkdf('sha256', $shared, 32, "WebPush: info\0" . $uaPublic . $ephPublic, $authSecret);
    $cek = hash_hkdf('sha256', $prk, 16, "Content-Encoding: aes128gcm\0", $salt);
    $nonce = hash_hkdf('sha256', $prk, 12, "Content-Encoding: nonce\0", $salt);
    $tag = '';
    $cipher = openssl_encrypt($payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
    if ($cipher === false) return 0;
    $body = $salt . pack('N', 4096) . chr(65) . $ephPublic . $cipher . $tag;
    $parts = parse_url($endpoint);
    if (!$parts || empty($parts['host']) || empty($parts['scheme'])) return 0;
    $origin = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
    $site_host = preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $site_host = preg_replace('/^www\./', '', $site_host);
    $jwt = sitak_vapid_jwt($vapidPriv, $origin, 'mailto:admin@' . ($site_host !== '' ? $site_host : 'localhost'));
    if ($jwt === '') return 0;
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 6,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/octet-stream',
            'Content-Encoding: aes128gcm',
            'Content-Length: ' . strlen($body),
            'TTL: 3600',
            'Authorization: vapid t=' . $jwt . ', k=' . $vapidPub,
        ],
    ]);
    @curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code;
}

// ثبت رویداد برای مدیر + پخش اعلان مرورگری (طبق سوئیچ‌های تنظیمات)
function sitak_notify_admin($conn, $type, $title, $body, $url = 'admin.php') {
    if (getSetting($conn, 'admin_notify_enabled', '1') !== '1') return;
    $switches = ['orders' => 'admin_notify_orders', 'winners' => 'admin_notify_winners', 'users' => 'admin_notify_users', 'resets' => 'admin_notify_resets', 'payments' => 'admin_notify_payments'];
    if (isset($switches[$type]) && getSetting($conn, $switches[$type], '1') !== '1') return;
    sitak_notify_emit($conn, $type, $title, $body, $url);
}

// اعلان آزمایشی (از بخش تنظیمات) — بدون دروازه‌های نوع، برای سنجش مسیر اعلان
function sitak_notify_admin_test($conn) {
    if (getSetting($conn, 'admin_notify_enabled', '1') !== '1') return false;
    sitak_notify_emit($conn, 'test', 'اعلان آزمایشی 🔔', 'اگر این پیام را می‌بینی، مسیر اعلان‌های درگاه درست کار می‌کند.', 'admin-settings.php');
    return true;
}

// هسته‌ی مشترک: درج رویداد + هرس + ارسال push به اشتراک‌ها
function sitak_notify_emit($conn, $type, $title, $body, $url = 'admin.php') {
    $title = mb_substr((string)$title, 0, 120, 'UTF-8');
    $body = mb_substr((string)$body, 0, 255, 'UTF-8');
    $url = mb_substr((string)$url, 0, 120, 'UTF-8');
    $st = $conn->prepare("INSERT INTO admin_events (type, title, body, url) VALUES (?, ?, ?, ?)");
    if ($st) { $st->bind_param('ssss', $type, $title, $body, $url); $st->execute(); }
    // هرس سبک: فقط ~۳۰۰ رویداد آخر نگه داشته می‌شود
    $conn->query("DELETE FROM admin_events WHERE id < (SELECT cut FROM (SELECT COALESCE(MAX(id), 0) - 300 AS cut FROM admin_events) t)");
    // ارسال push به همه‌ی اشتراک‌های ثبت‌شده (معمولاً مرورگر خود مدیر)
    $subs = $conn->query("SELECT endpoint_hash, endpoint, p256dh, auth FROM push_subscriptions LIMIT 20");
    if (!$subs) return;
    $icon = (string)getSetting($conn, 'custom_favicon', '');
    if ($icon === '') $icon = (string)getSetting($conn, 'custom_logo', '');
    $payload = json_encode(['title' => $title, 'body' => $body, 'url' => $url, 'icon' => $icon, 'tag' => 'sitak-' . $type], JSON_UNESCAPED_UNICODE);
    while ($s = $subs->fetch_assoc()) {
        $code = sitak_webpush_send($conn, $s['endpoint'], $s['p256dh'], $s['auth'], $payload);
        if ($code === 404 || $code === 410) {
            $del = $conn->prepare("DELETE FROM push_subscriptions WHERE endpoint_hash = ?");
            if ($del) { $del->bind_param('s', $s['endpoint_hash']); $del->execute(); }
        }
    }
}
