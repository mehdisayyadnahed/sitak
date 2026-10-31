<?php
// اگر سایت هنوز نصب نشده (نه install.lock هست و نه db-config.php)، کاربر به نصب‌کننده هدایت می‌شود.
// برای سایت‌های نصب‌شده‌ی قبلی یک بار فایل خالی install.lock را در ریشه بساز.
if (PHP_SAPI !== 'cli' && basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'install.php'
    && !file_exists(__DIR__ . '/install.lock') && !file_exists(__DIR__ . '/db-config.php')) {
    header('Location: ' . rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/') . '/install.php');
    exit;
}

// config.php
mysqli_report(MYSQLI_REPORT_OFF);
// Normalize every user-provided digit at the application boundary.
// Storage, validation and calculations always use ASCII digits.

// Security helpers shared by the public site and admin panel.
function sitak_password_matches($plain, $stored) {
    if ($stored === '') return false;
    $info = password_get_info($stored);
    return ($info['algo'] ?? 0) !== 0 ? password_verify($plain, $stored) : hash_equals((string)$stored, (string)$plain);
}
function sitak_password_hash($plain) { return password_hash($plain, PASSWORD_DEFAULT); }
// شرایط گذرواژه ادمین: حداقل ۸ کاراکتر، شامل حرف بزرگ و کوچک انگلیسی، عدد و کاراکتر خاص
function sitak_password_errors($pw) {
    $e = [];
    if (strlen($pw) < 8) $e[] = 'حداقل ۸ کاراکتر';
    if (!preg_match('/[a-z]/', $pw)) $e[] = 'حرف کوچک انگلیسی';
    if (!preg_match('/[A-Z]/', $pw)) $e[] = 'حرف بزرگ انگلیسی';
    if (!preg_match('/[0-9]/', $pw)) $e[] = 'عدد';
    if (!preg_match('/[^A-Za-z0-9]/', $pw)) $e[] = 'کاراکتر خاص (مثل !@#$)';
    if (preg_match('/[^ -~]/', $pw)) $e[] = 'فقط حروف و نمادهای انگلیسی';
    return $e;
}
// محدودیت تلاش ورود: بعد از چند تلاش اشتباه، مدتی قفل می‌شود (بر اساس IP)
function sitak_throttle_file($key) { return sys_get_temp_dir() . '/sitak_thr_' . hash('sha256', $key) . '.json'; }
function sitak_throttle_state($key) {
    $d = @json_decode((string)@file_get_contents(sitak_throttle_file($key)), true);
    return is_array($d) ? $d : ['n' => 0, 'last' => 0];
}
function sitak_throttle_blocked($key, $max = 5, $lock = 900) {
    $d = sitak_throttle_state($key);
    return ($d['n'] >= $max) && (time() - $d['last'] < $lock);
}
function sitak_throttle_fail($key, $lock = 900) {
    $d = sitak_throttle_state($key);
    if (time() - $d['last'] >= $lock) $d['n'] = 0;
    $d['n']++; $d['last'] = time();
    @file_put_contents(sitak_throttle_file($key), json_encode($d));
}
function sitak_throttle_clear($key) { @unlink(sitak_throttle_file($key)); }
function sitak_csrf_token() {
    if (empty($_SESSION['sitak_csrf'])) $_SESSION['sitak_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['sitak_csrf'];
}
function sitak_csrf_valid($token) {
    return is_string($token) && !empty($_SESSION['sitak_csrf']) && hash_equals($_SESSION['sitak_csrf'], $token);
}
function sitak_safe_image_upload($file, $allowed_exts) {
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return false;
    $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_exts, true) || !is_uploaded_file($file['tmp_name'])) return false;
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : '';
    if ($finfo) finfo_close($finfo);
    $mime_exts = ['image/jpeg'=>['jpg','jpeg'], 'image/png'=>['png'], 'image/gif'=>['gif'], 'image/webp'=>['webp'], 'image/x-icon'=>['ico'], 'image/vnd.microsoft.icon'=>['ico']];
    if (!isset($mime_exts[$mime]) || !in_array($ext, $mime_exts[$mime], true)) return false;
    return @getimagesize($file['tmp_name']) !== false;
}

function sitak_normalize_digits($value) {
    if (is_array($value)) {
        foreach ($value as $key => $item) $value[$key] = sitak_normalize_digits($item);
        return $value;
    }
    if (!is_string($value)) return $value;
    return strtr($value, [
        '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
        '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9'
    ]);
}

function sitak_localize_output($html) {
    if (stripos($html, '</body>') === false || stripos($html, 'application/json') !== false) return $html;
    $tag = '<style>.bubble,.badge,.chip{box-sizing:border-box;display:inline-flex;align-items:center;justify-content:center;vertical-align:middle;}input[inputmode="numeric"],input[type="tel"]{direction:ltr;unicode-bidi:plaintext;}button:not(:disabled),[class*="btn"]:not(:disabled),.name-save,.close-x,a.btn{transition:filter .18s ease,transform .12s ease,box-shadow .2s ease;-webkit-tap-highlight-color:transparent}button:not(:disabled):hover,[class*="btn"]:not(:disabled):hover,.name-save:hover,.close-x:hover,a.btn:hover{filter:brightness(1.07)}button:not(:disabled):active,[class*="btn"]:not(:disabled):active,.name-save:active,.close-x:active,a.btn:active{filter:brightness(.9);transform:scale(.97)}.btn-p:not(:disabled):hover,.conf-btn-ok:hover,.name-save:hover,.checkout-btn:not(:disabled):hover,.login-btn:hover,a.btn:hover{box-shadow:0 6px 16px rgba(196,150,37,.28)}button:focus-visible,a.btn:focus-visible{outline:2px solid var(--gold,#ecb534);outline-offset:2px}</style>';
    return preg_replace('/<\\/body>/i', $tag . '</body>', $html, 1);
}

ob_start('sitak_localize_output');
if (session_status() === PHP_SESSION_NONE) {
    $sitak_secure_cookie = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'secure' => $sitak_secure_cookie,
        'httponly' => true, 'samesite' => 'Lax'
    ]);
    session_start();
}

// Accept Persian, Arabic and Latin digits from every request, but keep backend data ASCII.
$_GET = sitak_normalize_digits($_GET);
$_POST = sitak_normalize_digits($_POST);
$_COOKIE = sitak_normalize_digits($_COOKIE);
$_REQUEST = sitak_normalize_digits($_REQUEST);

// ۱. تنظیم دقیق منطقه زمانی روی تهران برای مفسر PHP
date_default_timezone_set('Asia/Tehran');

$db_host = 'localhost';
$db_user = 'root'; // نام کاربری دیتابیس خود را وارد کنید
$db_pass = '';     // گذرواژه عبور دیتابیس خود را وارد کنید
$db_name = getenv('SITAK_DB_NAME') ?: 'sitak'; // نام دیتابیس؛ برای نصب جدید می‌توانی متغیر محیطی SITAK_DB_NAME را ست کنی
if (file_exists(__DIR__ . '/db-config.php')) {
    $__dbc = require __DIR__ . '/db-config.php';
    $db_host = $__dbc['host']; $db_user = $__dbc['user']; $db_pass = $__dbc['pass']; $db_name = $__dbc['name'];
}

$conn = new mysqli($db_host, $db_user, $db_pass);

if ($conn->connect_error) {
    die("اتصال به پایگاه‌داده برقرار نشد: " . $conn->connect_error);
}
// اتصال با utf8mb4 تا ایموجی‌ها (کاراکترهای ۴ بایتی) بدون خطای بی‌صدا ذخیره شوند
$conn->set_charset('utf8mb4');

// ساخت پایگاه داده در صورت عدم وجود
$conn->query("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_persian_ci");
$conn->select_db($db_name);

// ۲. هماهنگ‌سازی خودکار منطقه زمانی ماریا‌دی‌بی با پی‌اچ‌پی
$now = new DateTime();
$mins = $now->getOffset() / 60;
$sgn = ($mins < 0 ? -1 : 1);
$mins = abs($mins);
$hrs = floor($mins / 60);
$mins -= $hrs * 60;
$offset = sprintf('%+03d:%02d', $hrs*$sgn, $mins);
$conn->query("SET time_zone='$offset';");

require_once __DIR__ . '/migrations.php';
// ── ورود ماندگار (مرا به خاطر بسپار) ──
// کوکی فقط یک توکن تصادفی است؛ شماره تلفن و هیچ اطلاعات حساس دیگری داخل کوکی نیست.
// در دیتابیس فقط هش توکن (SHA-256) نگه داشته می‌شود و هر توکن ۳۰ روز اعتبار دارد.
define('SITAK_REMEMBER_COOKIE', 'sitak_remember');
define('SITAK_REMEMBER_DAYS', 30);
define('SITAK_REMEMBER_MAX_DAYS', 90); // سقف مطلق: بعد از این مدت از اولین ورود، حتی با فعالیت هم باید دوباره وارد شد
function sitak_remember_cookie_opts($expires) {
    return [
        'expires' => $expires, 'path' => '/', 'httponly' => true,
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'samesite' => 'Lax'
    ];
}
function sitak_remember_issue($conn, $phone) {
    $token = bin2hex(random_bytes(32));
    $now = date('Y-m-d H:i:s');
    $exp = date('Y-m-d H:i:s', time() + SITAK_REMEMBER_DAYS * 86400);
    $hash = hash('sha256', $token);
    $conn->query("DELETE FROM user_remember_tokens WHERE expires_at < '" . $conn->real_escape_string($now) . "'");
    $st = $conn->prepare("INSERT INTO user_remember_tokens (token_hash, phone, created_at, expires_at, last_used_at) VALUES (?, ?, ?, ?, ?)");
    $st->bind_param("sssss", $hash, $phone, $now, $exp, $now);
    $st->execute();
    setcookie(SITAK_REMEMBER_COOKIE, $token, sitak_remember_cookie_opts(time() + SITAK_REMEMBER_DAYS * 86400));
    $_COOKIE[SITAK_REMEMBER_COOKIE] = $token;
}
function sitak_login_session($conn, $phone) {
    $_SESSION['sitak_user'] = $phone;
    session_regenerate_id(true);
    sitak_remember_issue($conn, $phone);
}
function sitak_remember_revoke($conn) {
    $token = (string)($_COOKIE[SITAK_REMEMBER_COOKIE] ?? '');
    if (preg_match('/^[a-f0-9]{64}$/', $token)) {
        $hash = hash('sha256', $token);
        $st = $conn->prepare("DELETE FROM user_remember_tokens WHERE token_hash = ?");
        $st->bind_param("s", $hash); $st->execute();
    }
    setcookie(SITAK_REMEMBER_COOKIE, '', sitak_remember_cookie_opts(time() - 3600));
    unset($_COOKIE[SITAK_REMEMBER_COOKIE]);
}
function sitak_remember_restore($conn) {
    $token = (string)($_COOKIE[SITAK_REMEMBER_COOKIE] ?? '');
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) return;
    $hash = hash('sha256', $token);
    $now = time();
    $nowS = date('Y-m-d H:i:s', $now);
    $capS = date('Y-m-d H:i:s', $now - SITAK_REMEMBER_MAX_DAYS * 86400);
    try {
        $st = $conn->prepare("SELECT t.phone, t.created_at, t.last_used_at FROM user_remember_tokens t JOIN users u ON u.phone = t.phone
            WHERE t.token_hash = ? AND t.expires_at > ? AND t.created_at > ? AND u.blocked = 0 LIMIT 1");
        $st->bind_param("sss", $hash, $nowS, $capS); $st->execute();
        $row = $st->get_result()->fetch_assoc();
    } catch (Throwable $e) {
        error_log('[SiTak remember restore] ' . $e->getMessage());
        return;
    }
    if (!$row) {
        setcookie(SITAK_REMEMBER_COOKIE, '', sitak_remember_cookie_opts(time() - 3600));
        unset($_COOKIE[SITAK_REMEMBER_COOKIE]);
        return;
    }
    if (empty($_SESSION['sitak_user'])) {
        $_SESSION['sitak_user'] = $row['phone'];
        session_regenerate_id(true);
    }
    // تمدید کشویی: حداکثر یک‌بار در روز تمدید می‌شود تا دیتابیس هر درخواست بازنویسی نشود.
    $last = $row['last_used_at'] ? strtotime($row['last_used_at']) : 0;
    if ($now - $last < 86400) return;
    $created = strtotime($row['created_at']);
    $newExp = min($now + SITAK_REMEMBER_DAYS * 86400, $created + SITAK_REMEMBER_MAX_DAYS * 86400);
    $expS = date('Y-m-d H:i:s', $newExp);
    $up = $conn->prepare("UPDATE user_remember_tokens SET expires_at = ?, last_used_at = ? WHERE token_hash = ?");
    $up->bind_param("sss", $expS, $nowS, $hash); $up->execute();
    setcookie(SITAK_REMEMBER_COOKIE, $token, sitak_remember_cookie_opts($newExp));
}
if (PHP_SAPI !== 'cli') sitak_remember_restore($conn);

// ── ورود ماندگار ادمین (۷ روز) ──
// کوکی فقط یک توکن تصادفی است؛ در دیتابیس فقط هشِ آن ذخیره می‌شود. با تغییر گذرواژه همه‌ی ورودهای ماندگار باطل می‌شوند.
define('SITAK_ADMIN_REMEMBER_COOKIE', 'sitak_admin_remember');
define('SITAK_ADMIN_REMEMBER_DAYS', 7);
define('SITAK_ADMIN_REMEMBER_MAX_DAYS', 30);
function sitak_admin_cookie_opts($expires) {
    return [
        'expires' => $expires, 'path' => '/', 'httponly' => true,
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'samesite' => 'Lax'
    ];
}
function sitak_admin_remember_issue($conn) {
    $token = bin2hex(random_bytes(32));
    $now = date('Y-m-d H:i:s');
    $exp = date('Y-m-d H:i:s', time() + SITAK_ADMIN_REMEMBER_DAYS * 86400);
    $hash = hash('sha256', $token);
    $conn->query("DELETE FROM admin_remember_tokens WHERE expires_at < '" . $conn->real_escape_string($now) . "'");
    $st = $conn->prepare("INSERT INTO admin_remember_tokens (token_hash, created_at, expires_at, last_used_at) VALUES (?, ?, ?, ?)");
    $st->bind_param("ssss", $hash, $now, $exp, $now);
    $st->execute();
    setcookie(SITAK_ADMIN_REMEMBER_COOKIE, $token, sitak_admin_cookie_opts(time() + SITAK_ADMIN_REMEMBER_DAYS * 86400));
    $_COOKIE[SITAK_ADMIN_REMEMBER_COOKIE] = $token;
}
function sitak_admin_remember_revoke($conn) {
    $token = (string)($_COOKIE[SITAK_ADMIN_REMEMBER_COOKIE] ?? '');
    if (preg_match('/^[a-f0-9]{64}$/', $token)) {
        $hash = hash('sha256', $token);
        $st = $conn->prepare("DELETE FROM admin_remember_tokens WHERE token_hash = ?");
        $st->bind_param("s", $hash); $st->execute();
    }
    setcookie(SITAK_ADMIN_REMEMBER_COOKIE, '', sitak_admin_cookie_opts(time() - 3600));
    unset($_COOKIE[SITAK_ADMIN_REMEMBER_COOKIE]);
}
function sitak_admin_remember_revoke_all($conn) {
    $conn->query("DELETE FROM admin_remember_tokens");
}
function sitak_admin_remember_restore($conn) {
    $token = (string)($_COOKIE[SITAK_ADMIN_REMEMBER_COOKIE] ?? '');
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) return;
    $hash = hash('sha256', $token);
    $now = time();
    $nowS = date('Y-m-d H:i:s', $now);
    $capS = date('Y-m-d H:i:s', $now - SITAK_ADMIN_REMEMBER_MAX_DAYS * 86400);
    try {
        $st = $conn->prepare("SELECT created_at, last_used_at FROM admin_remember_tokens WHERE token_hash = ? AND expires_at > ? AND created_at > ? LIMIT 1");
        $st->bind_param("sss", $hash, $nowS, $capS); $st->execute();
        $row = $st->get_result()->fetch_assoc();
    } catch (Throwable $e) {
        error_log('[SiTak admin remember] ' . $e->getMessage());
        return;
    }
    if (!$row) {
        setcookie(SITAK_ADMIN_REMEMBER_COOKIE, '', sitak_admin_cookie_opts(time() - 3600));
        unset($_COOKIE[SITAK_ADMIN_REMEMBER_COOKIE]);
        return;
    }
    if (empty($_SESSION['sitak_admin'])) {
        $_SESSION['sitak_admin'] = true;
        session_regenerate_id(true);
    }
    $last = $row['last_used_at'] ? strtotime($row['last_used_at']) : 0;
    if ($now - $last < 86400) return;
    $created = strtotime($row['created_at']);
    $newExp = min($now + SITAK_ADMIN_REMEMBER_DAYS * 86400, $created + SITAK_ADMIN_REMEMBER_MAX_DAYS * 86400);
    $expS = date('Y-m-d H:i:s', $newExp);
    $up = $conn->prepare("UPDATE admin_remember_tokens SET expires_at = ?, last_used_at = ? WHERE token_hash = ?");
    $up->bind_param("sss", $expS, $nowS, $hash); $up->execute();
    setcookie(SITAK_ADMIN_REMEMBER_COOKIE, $token, sitak_admin_cookie_opts($newExp));
}
if (PHP_SAPI !== 'cli') sitak_admin_remember_restore($conn);

// —— seed پیش‌شماره‌های پیش‌فرض ——
$fp_check = $conn->query("SELECT COUNT(*) c FROM fake_prefixes")->fetch_assoc()['c'];
if ($fp_check == 0) {
    $default_prefixes = ['0912','0919','0935','0936','0937','0901','0902','0921','0990','0913','0914','0915','0932','0996','0994','0993','0992','0991','0910','0930','0933','0938','0939','0900','0903','0904','0905','0920','0922','0923','0998','0999'];
    foreach ($default_prefixes as $pfx) {
        $st = $conn->prepare("INSERT IGNORE INTO fake_prefixes (prefix) VALUES (?)");
        $st->bind_param("s", $pfx);
        $st->execute();
    }
}


function split_verse_text($raw) {
    $raw = trim((string)$raw);
    $raw = preg_replace('/\s*\\\\n\s*/u', "\n", $raw);
    $raw = str_replace(["\r\n", "\r"], "\n", $raw);
    $p = explode("\n", $raw, 2);
    if (count($p) === 1) {
        $p2 = preg_split('/\s+—\s+| {3,}|\t+/u', $raw, 2);
        if (count($p2) === 2) $p = $p2;
    }
    return [trim($p[0] ?? ''), trim($p[1] ?? '')];
}
function verse_parts($row) {
    return [trim($row['verse_m1'] ?? ''), trim($row['verse_m2'] ?? '')];
}
function verse_display_text($row) {
    [$a, $b] = verse_parts($row);
    if ($a !== '' && $b !== '') return $a . ' - ' . $b;
    return $a !== '' ? $a : $b;
}
// ── خواندن تنظیمات (دسترسی سراسری) ──
// ── گردونه شانس: قاچ‌ها از جوایز شانس (احتمال هر جایزه) + پوچ برای مابقی ──
// هر قاچ مساوی است؛ درصدها (واحد = ۰.۰۱٪ یعنی ۱۰۰۰۰ واحد = ۱۰۰٪) از همین لیست می‌آیند.
function sitak_wheel_build($conn) {
    $palette = ['#e8a33d', '#d9534f', '#5b7fd1', '#3aa88f', '#9b6bd0', '#e0729a', '#4fa3c7', '#f0b35a'];
    $rows = [];
    $now_wheel = new DateTime('now', new DateTimeZone('Asia/Tehran'));
    $res = $conn->query("SELECT id, description, discount_code, affiliate_link, is_local, is_instant, win_probability, is_void, color, qty_limit, qty_used, time_limit_days, time_started_at FROM prizes WHERE is_active = 1 AND win_probability > 0 ORDER BY is_void ASC, id ASC");
    while ($res && ($p = $res->fetch_assoc())) {
        $units = (int)round(floatval($p['win_probability']) * 100);
        if ($units <= 0) continue;
        // جایزه‌ای که سهمیه یا مهلتش تمام شده، از گردونه حذف می‌شود (درصدها خودکار بازتوزیع می‌شوند)
        $lim = sitak_prize_limits($p, $now_wheel);
        if (!$lim['available']) continue;
        $rows[] = ['prize' => intval($p['is_void']) === 1 ? null : $p, 'label' => $p['description'], 'units' => $units, 'badges' => $lim['badges']];
    }
    $raw_sum = 0;
    foreach ($rows as $r) $raw_sum += $r['units'];
    if ($raw_sum <= 0) return ['segments' => [], 'over' => false, 'sum' => 0];
    // جمع همه‌ی درصدها (جوایز + پوچ) باید ۱۰۰٪ باشد؛ اگر نبود، به نسبت تبدیل می‌شود
    $normalized = $raw_sum !== 10000;
    if ($normalized) {
        $total = 0;
        foreach ($rows as $k => $r) {
            $rows[$k]['units'] = max(1, (int)floor($r['units'] * 10000 / $raw_sum));
            $total += $rows[$k]['units'];
        }
        $big = 0;
        foreach ($rows as $k => $r) if ($r['units'] > $rows[$big]['units']) $big = $k;
        $rows[$big]['units'] += 10000 - $total;
    }
    $segments = [];
    $ci = 0;
    foreach ($rows as $r) {
        if ($r['prize'] === null) {
            $color = '#5a5a5e';
        } else {
            $pc = strtolower((string)($r['prize']['color'] ?? ''));
            $color = in_array($pc, $palette, true) ? $pc : $palette[$ci % count($palette)];
            $ci++;
        }
        $segments[] = ['label' => $r['label'], 'units' => $r['units'], 'color' => $color, 'prize' => $r['prize'], 'badges' => $r['badges'] ?? []];
    }
    return ['segments' => $segments, 'over' => $normalized, 'sum' => $raw_sum];
}

// ── محدودیت جایزه: تعداد و مهلت روزانه (ساعت تهران) ──
// روزهای باقیمانده‌ی مهلت: null = بدون مهلت، 0 = تمام شده
function sitak_prize_days_left(array $p, ?DateTime $now = null): ?int {
    if (($p['time_limit_days'] ?? null) === null || $p['time_limit_days'] === '' || empty($p['time_started_at'])) return null;
    $tz = new DateTimeZone('Asia/Tehran');
    $now = $now ?: new DateTime('now', $tz);
    $ends = new DateTime((string)$p['time_started_at'], $tz);
    $ends->modify('+' . (int)$p['time_limit_days'] . ' days');
    $secs = $ends->getTimestamp() - $now->getTimestamp();
    return $secs <= 0 ? 0 : (int)ceil($secs / 86400);
}
// قابل برد بودن جایزه + حباب‌های نمایشی (تعداد و/یا روز باقیمانده)
function sitak_prize_limits(array $p, ?DateTime $now = null): array {
    $out = ['available' => true, 'badges' => []];
    if (!empty($p['is_void'])) return $out;
    $now = $now ?: new DateTime('now', new DateTimeZone('Asia/Tehran'));
    if (isset($p['qty_limit']) && $p['qty_limit'] !== null && $p['qty_limit'] !== '') {
        $left = max(0, (int)$p['qty_limit'] - (int)($p['qty_used'] ?? 0));
        if ($left <= 0) $out['available'] = false;
        $out['badges'][] = pn($left) . ' عدد باقیمانده';
    }
    $days = sitak_prize_days_left($p, $now);
    if ($days !== null) {
        if ($days <= 0) $out['available'] = false;
        else $out['badges'][] = pn($days) . ' روز باقیمانده';
    }
    return $out;
}
// ثبت یک برد واقعی روی سهمیه‌ی جایزه؛ false یعنی سهمیه همین لحظه تمام شده
function sitak_prize_claim($conn, array $prize): bool {
    if (!isset($prize['qty_limit']) || $prize['qty_limit'] === null || $prize['qty_limit'] === '') return true;
    $id = (int)$prize['id'];
    $conn->query("UPDATE prizes SET qty_used = qty_used + 1 WHERE id = $id AND qty_used < qty_limit");
    return $conn->affected_rows === 1;
}

// ── متادیتای اشتراک‌گذاری (Open Graph / Twitter) و فاوآیکون؛ همه‌چیز از تنظیمات و داده‌ها خوانده می‌شود ──
function sitak_base_url() {
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}
function sitak_abs_url($path) {
    $path = trim((string)$path);
    if ($path === '') return '';
    if (preg_match('#^https?://#i', $path)) return $path;
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return sitak_base_url() . $dir . '/' . ltrim($path, '/');
}
function sitak_plain_text($html, $limit = 200) {
    $t = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string)$html), ENT_QUOTES, 'UTF-8')));
    return mb_strlen($t, 'UTF-8') > $limit ? mb_substr($t, 0, $limit, 'UTF-8') . '…' : $t;
}
// اسکریپت جایگزینی تصویر خراب با ایموجی: هر <img data-fallback-emoji> که لود نشود، با ایموجی تنظیمات جایگزین می‌شود.
function sitak_img_fallback_script() {
    echo <<<'HTML'
<script>
(function(){
  function swap(img){
    if(!img||!img.parentNode) return;
    var d=document.createElement('div');
    d.className='prod-img';
    d.setAttribute('style','display:flex;align-items:center;justify-content:center;font-size:2.2rem'+(img.classList.contains('prod-cover-full')?';min-height:200px':''));
    d.textContent=img.getAttribute('data-fallback-emoji')||'';
    img.parentNode.replaceChild(d,img);
  }
  document.addEventListener('error',function(e){
    var t=e.target;
    if(t&&t.tagName==='IMG'&&t.hasAttribute('data-fallback-emoji')) swap(t);
  },true);
  document.addEventListener('DOMContentLoaded',function(){
    document.querySelectorAll('img[data-fallback-emoji]').forEach(function(img){
      if(img.complete&&img.naturalWidth===0) swap(img);
    });
  });
})();
</script>
HTML;
}
function sitak_head_meta($conn, $o = []) {
    $e = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
    $site = getSetting($conn, 'site_name', 'سای‌تک');
    $slogan = getSetting($conn, 'site_slogan', '');
    $logo = getSetting($conn, 'custom_logo', '');
    $custom_fav = getSetting($conn, 'custom_favicon', '');
    $title = !empty($o['title']) ? sitak_plain_text($o['title'], 120) : $site;
    $desc = sitak_plain_text(!empty($o['description']) ? $o['description'] : $slogan);
    $img = sitak_abs_url(!empty($o['image']) ? $o['image'] : $logo);
    $type = $o['type'] ?? 'website';
    $url = sitak_base_url() . ($_SERVER['REQUEST_URI'] ?? '/');
    $out = '';
    // فاوآیکون: بارگذاری ادمین، یا در غیر این صورت ایموجی کره‌ی زمین
    if ($custom_fav !== '') {
        // فایل فاوآیکون کوچک است: مستقیم به‌صورت data URI داخل صفحه گذاشته می‌شود تا
        // به درخواست جداگانه‌ی شبکه وابسته نباشد (در صفحه‌ی 404 مرورگر اغلب آیکون را از پاسخ خطا نمی‌گیرد).
        // فایل‌های بزرگ‌تر با آدرس مطلق لینک می‌شوند.
        $fav_path = __DIR__ . '/' . ltrim($custom_fav, '/');
        $fav_size = is_readable($fav_path) ? (int)@filesize($fav_path) : 0;
        $fav_ext  = strtolower(pathinfo($fav_path, PATHINFO_EXTENSION));
        $fav_mimes = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif',
                      'webp' => 'image/webp', 'svg' => 'image/svg+xml', 'ico' => 'image/x-icon'];
        if ($fav_size > 0 && $fav_size <= 262144 && isset($fav_mimes[$fav_ext])) {
            $fav_href = 'data:' . $fav_mimes[$fav_ext] . ';base64,' . base64_encode(file_get_contents($fav_path));
        } else {
            $fav_ver = @filemtime($fav_path) ?: time();
            $fav_href = sitak_abs_url($custom_fav) . '?v=' . $fav_ver;
        }
        $out .= '<link rel="icon" href="' . $e($fav_href) . '">' . "\n";
    } else {
        $svg = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🌐</text></svg>";
        $out .= '<link rel="icon" href="data:image/svg+xml,' . rawurlencode($svg) . '" type="image/svg+xml">' . "\n";
    }
    $out .= '<meta name="description" content="' . $e($desc) . '">' . "\n";
    $out .= '<meta property="og:site_name" content="' . $e($site) . '">' . "\n";
    $out .= '<meta property="og:locale" content="fa_IR">' . "\n";
    $out .= '<meta property="og:type" content="' . $e($type) . '">' . "\n";
    $out .= '<meta property="og:title" content="' . $e($title) . '">' . "\n";
    $out .= '<meta property="og:description" content="' . $e($desc) . '">' . "\n";
    $out .= '<meta property="og:url" content="' . $e($url) . '">' . "\n";
    if ($img !== '') $out .= '<meta property="og:image" content="' . $e($img) . '">' . "\n";
    $out .= '<meta name="twitter:card" content="' . ($img !== '' ? 'summary_large_image' : 'summary') . '">' . "\n";
    $out .= '<meta name="twitter:title" content="' . $e($title) . '">' . "\n";
    $out .= '<meta name="twitter:description" content="' . $e($desc) . '">' . "\n";
    if ($img !== '') $out .= '<meta name="twitter:image" content="' . $e($img) . '">' . "\n";
    // توکن CSRF درخواست‌های کاربر (ajax.php) — کاملاً داخلی و خودمیزبان
    $csrf_tok = sitak_csrf_token();
    $out .= '<meta name="sitak-csrf" content="' . $e($csrf_tok) . '">' . "\n";
    $out .= '<script>window.SITAK_CSRF=' . json_encode($csrf_tok) . ';window.scsrf=function(b){try{if(b&&b.append)b.append("csrf_token",window.SITAK_CSRF)}catch(e){}return b};</script>' . "\n";
    return $out;
}

// کسر موجودی بعد از پرداخت موفق (فقط محصولاتی که موجودی مشخص دارند)
function sitak_stock_consume($conn, $items_json) {
    $items = json_decode((string)$items_json, true);
    if (!is_array($items)) return;
    $st = $conn->prepare("UPDATE products SET stock_qty = GREATEST(stock_qty - ?, 0) WHERE id = ? AND stock_qty IS NOT NULL");
    foreach ($items as $it) {
        $q = (int)($it['qty'] ?? 0); $pid = (int)($it['product_id'] ?? 0);
        if ($q <= 0 || $pid <= 0) continue;
        $st->bind_param('ii', $q, $pid); $st->execute();
    }
}

// ── پرداخت کارت‌به‌کارت ──
// شماره کارت را ۴ رقم ۴ رقم با خط تیره جدا می‌کند
function sitak_format_card_number($v) {
    $d = sitak_digits_latin(preg_replace('/\D/u', '', (string)$v));
    if ($d === '') return '';
    return implode('-', str_split($d, 4));
}
// شبا را بزرگ، بدون فاصله و ۴ کاراکتر ۴ کاراکتر با خط تیره مرتب می‌کند
function sitak_format_iban($v) {
    $s = strtoupper(sitak_digits_latin(preg_replace('/[^A-Za-z0-9]/u', '', (string)$v)));
    if ($s === '') return '';
    return implode('-', str_split($s, 4));
}
// ارقام فارسی/عربی را به لاتین تبدیل می‌کند
function sitak_digits_latin($s) {
    return str_replace(
        ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹','٠','١','٢','٣','٤','٥','٦','٧','٨','٩'],
        ['0','1','2','3','4','5','6','7','8','9','0','1','2','3','4','5','6','7','8','9'],
        (string)$s
    );
}
// تنظیمات کارت‌به‌کارت به‌صورت یکجا (با نرمال‌سازی شماره‌ها و مدت قفل)
function sitak_card_payment_info($conn) {
    $card = preg_replace('/\D/', '', sitak_digits_latin((string)getSetting($conn, 'card_number', '')));
    $iban = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', sitak_digits_latin((string)getSetting($conn, 'card_iban', ''))));
    $minutes = (int)getSetting($conn, 'card_lock_minutes', '30');
    if ($minutes < 5) $minutes = 30;
    if ($minutes > 1440) $minutes = 1440;
    return [
        'enabled'        => getSetting($conn, 'card_payment_enabled', '0') === '1',
        'owner'          => trim((string)getSetting($conn, 'card_owner_name', '')),
        'card'           => $card,
        'card_formatted' => sitak_format_card_number($card),
        'iban'           => $iban,
        'iban_formatted' => sitak_format_iban($iban),
        'lock_minutes'   => $minutes,
        'ready'          => getSetting($conn, 'card_payment_enabled', '0') === '1' && ($card !== '' || $iban !== ''),
    ];
}
// موجودی رزرو شده‌ی یک سفارش را برمی‌گرداند (فقط اگر flag رزرو روشن باشد)
function sitak_stock_unreserve($conn, $order) {
    if (!$order || (int)($order['stock_reserved'] ?? 0) !== 1) return;
    $items = json_decode((string)($order['items'] ?? '[]'), true);
    if (is_array($items)) {
        $st = $conn->prepare("UPDATE products SET stock_qty = stock_qty + ? WHERE id = ? AND stock_qty IS NOT NULL");
        foreach ($items as $it) {
            $q = (int)($it['qty'] ?? 0); $pid = (int)($it['product_id'] ?? 0);
            if ($q <= 0 || $pid <= 0) continue;
            $st->bind_param('ii', $q, $pid); $st->execute();
        }
    }
    $cl = $conn->prepare("UPDATE orders SET stock_reserved = 0 WHERE id = ?");
    $oid = (int)$order['id'];
    $cl->bind_param('i', $oid); $cl->execute();
}
// سفارش‌های کارت‌به‌کارتی که در مهلت مقرر اطلاعات واریز ثبت نشده → خودکار لغو و موجودی آزاد می‌شود.
// مقایسه با زمان PHP (Asia/Tehran) انجام می‌شود تا با منطقه‌ی زمانی MySQL تفاوت نکند.
function sitak_expire_card_orders($conn) {
    $now = date('Y-m-d H:i:s');
    $res = $conn->query("SELECT id, items, stock_reserved FROM orders
        WHERE status = 'pending' AND payment_method = 'card'
          AND card_submitted_at IS NULL AND card_lock_expires IS NOT NULL
          AND card_lock_expires < '" . $conn->real_escape_string($now) . "' LIMIT 50");
    if (!$res) return;
    while ($row = $res->fetch_assoc()) {
        $up = $conn->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ? AND status = 'pending' AND card_submitted_at IS NULL");
        $oid = (int)$row['id'];
        $up->bind_param('i', $oid); $up->execute();
        if ($up->affected_rows > 0) sitak_stock_unreserve($conn, $row);
    }
}
// برچسب و کلاس نمایشی وضعیت سفارش — برای سفارش کارت‌به‌کارتِ پرداخت‌نشده حالت «در انتظار پرداخت» دارد
function sitak_order_status_view($order) {
    $labels = ['pending' => 'در انتظار تأیید ⏳', 'confirmed' => 'در حال آماده سازی ⚙️', 'shipped' => 'ارسال شده 🚚', 'delivered' => 'تحویل شده 🎉', 'cancelled' => 'لغو شده ❌'];
    $classes = ['pending' => 'os-pending', 'confirmed' => 'os-confirmed', 'shipped' => 'os-shipped', 'delivered' => 'os-delivered', 'cancelled' => 'os-cancelled'];
    $st = (string)($order['status'] ?? 'pending');
    if ($st === 'pending' && ($order['payment_method'] ?? '') === 'card' && empty($order['card_submitted_at'])) {
        return ['label' => 'در انتظار پرداخت 💳', 'class' => 'os-awaiting'];
    }
    return ['label' => $labels[$st] ?? $st, 'class' => $classes[$st] ?? 'os-pending'];
}
// آدرس‌یابی معکوس از map.ir با کلید سرور (کلید به مرورگر ارسال نمی‌شود)
function sitak_map_reverse($conn, $lat, $lng) {
    $key = getSetting($conn, 'map_api_key', '');
    if ($key === '') return ['ok' => false, 'message' => 'کلید نقشه تنظیم نشده است.'];
    $url = 'https://map.ir/reverse/?lat=' . rawurlencode((string)$lat) . '&lon=' . rawurlencode((string)$lng);
    $ctx = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 6, 'header' => "x-api-key: " . $key . "\r\nAccept: application/json\r\n"]]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) return ['ok' => false, 'message' => 'سرویس آدرس‌یابی در دسترس نیست.'];
    $j = json_decode($raw, true);
    $addr = is_array($j) ? trim((string)($j['address_compact'] ?? $j['address'] ?? '')) : '';
    if ($addr === '') return ['ok' => false, 'message' => 'برای این نقطه آدرسی پیدا نشد.'];
    return ['ok' => true, 'address' => mb_substr($addr, 0, 250, 'UTF-8')];
}
function sitak_clean_name($v) {
    return trim(preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{FE00}-\x{FE0F}\x{200D}]/u', '', (string)$v));
}
// اسم سایت + ایموجی سفارشی (در صورت روشن بودن سوئیچ) برای هدر
function sitak_brand_html($name) {
    global $conn;
    $n = sitak_clean_name($name);
    if ($n === '') $n = 'سای‌تک';
    $html = htmlspecialchars($n, ENT_QUOTES, 'UTF-8');
    if (isset($conn) && getSetting($conn, 'show_header_emoji', '1') === '1') {
        $em = trim((string)getSetting($conn, 'custom_emoji', '🌐'));
        if ($em !== '') $html .= ' ' . htmlspecialchars($em, ENT_QUOTES, 'UTF-8');
    }
    return $html;
}
function getSetting($conn, $key, $def = '') {
    $r = $conn->prepare("SELECT value_data FROM settings WHERE key_name = ?");
    $r->bind_param("s", $key); $r->execute();
    $row = $r->get_result()->fetch_assoc();
    return $row ? $row['value_data'] : $def;
}

// ── پیام‌های نتیجه‌ی تاس و گردونه (قابل ویرایش در پنل شانس، بخش ۳) ──
// پیام نیت کردن قبل از فال (قابل ویرایش در بخش ۷ پنل شانس)
function sitak_intention_msg_default() {
    return "قبل از دریافت فال، اول نیت کن.\nبعدش دکمه زیر رو بزن تا فال بگیری.";
}
function sitak_intention_msg($conn) {
    $v = trim((string)getSetting($conn, 'intention_msg', ''));
    return $v !== '' ? $v : sitak_intention_msg_default();
}

function sitak_result_msg_defaults() {
    return [
        'result_lose_msg' => "متأسفانه این‌بار شانس باهات یار نبود.\nدفعه بعد حتماً شانست رو امتحان کن!",
        'result_win_local_msg' => "📍 این هدیه در محل فروشگاه برای شما آماده میشود.\nبرای تحویل به کارمند فروشگاه مراجعه کنید.",
        'result_win_instant_msg' => "📍 این هدیه در محدوده‌ی شهر، در محل شما تحویل داده میشود.\nجهت هماهنگی برای تحویل، با کارمند فروشگاه تماس حاصل کنید.",
        'result_win_external_msg' => "🎟 این هدیه بصورت کد تخفیف است.\nمی‌توانید با مراجعه به ... برای خرید از آن استفاده کنید.",
        'result_last_expired_msg' => "🍂 فرصت دریافت تمام شد",
        'result_last_delivered_msg' => "✓ این هدیه تحویل داده شد",
    ];
}
function sitak_result_msgs($conn) {
    $out = [];
    foreach (sitak_result_msg_defaults() as $k => $def) $out[$k] = getSetting($conn, $k, $def);
    return $out;
}
// نوع تحویل جایزه: instant (در محل) / local (با حضور) / external (خارجی)
function sitak_prize_delivery_type($prize) {
    if (!empty($prize['is_instant'])) return 'instant';
    if (!empty($prize['is_local'])) return 'local';
    return 'external';
}
// خروجی JS: متغیر RESULT_MSGS و تابع‌های کمکی بنر نتیجه (داخل تگ script صفحه صدا زده می‌شود)
function sitak_result_js($conn) {
    $flags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
    echo 'var RESULT_MSGS=' . json_encode(sitak_result_msgs($conn), $flags) . ';';
    echo <<<'JS'
function resultEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;').replace(/\n/g,'<br>')}
function resultTypeMsg(p){
  if(!p) return '';
  var t=p.is_instant==1?'instant':(p.is_local==1?'local':'external');
  return RESULT_MSGS['result_win_'+t+'_msg']||'';
}
function resultNoteHtml(p){
  var m=resultTypeMsg(p);
  return m?'<p style="font-size:.72rem;color:var(--text2);margin-top:12px;line-height:1.6">'+resultEsc(m)+'</p>':'';
}
JS;
}

// ── محدودیت‌های سمت سرور برای کد پیامکی (ذخیره در دیتابیس، نه session) ──
function sitak_guard_row($conn, $k) {
    $st = $conn->prepare("SELECT fails, locked_until FROM sms_guard WHERE k = ?");
    $st->bind_param("s", $k); $st->execute();
    $r = $st->get_result()->fetch_assoc();
    return $r ? ['fails' => (int)$r['fails'], 'locked_until' => (int)$r['locked_until']] : ['fails' => 0, 'locked_until' => 0];
}
function sitak_guard_save($conn, $k, $fails, $locked_until) {
    $now = time();
    $st = $conn->prepare("INSERT INTO sms_guard (k, fails, locked_until, updated_at) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE fails = VALUES(fails), locked_until = VALUES(locked_until), updated_at = VALUES(updated_at)");
    $st->bind_param("siii", $k, $fails, $locked_until, $now); $st->execute();
}
function sitak_guard_locked($conn, $k) {
    return sitak_guard_row($conn, $k)['locked_until'] > time();
}
// قفل تصادفی بین ۱۵ تا ۳۰ دقیقه برای هر بار قفل شدن
function sitak_guard_lock_random($conn, $k) {
    $until = time() + random_int(900, 1800);
    sitak_guard_save($conn, $k, 0, $until);
    return $until;
}
// تعداد رقم‌های کد پیامکی (۴، ۵ یا ۶) از تنظیمات پنل پیامکی
// گذرواژه ورود اختصاصی (حالت جایگزین کد پیامکی): تعداد رقم بین ۴ تا ۸
function sitak_pw_digits($conn) {
    $d = (int)getSetting($conn, 'sms_password_digits', '6');
    return ($d >= 4 && $d <= 8) ? $d : 6;
}
function sitak_pw_valid($pw, $digits) {
    return (bool)preg_match('/^[0-9]{' . (int)$digits . '}$/', (string)$pw);
}
function sitak_otp_digits($conn) {
    $d = (int)getSetting($conn, 'sms_otp_digits', '6');
    return ($d >= 4 && $d <= 6) ? $d : 6;
}
// تولید کد تأیید: در sandbox کد ثابت به همان طول، وگرنه کد تصادفی با همان تعداد رقم
function sitak_generate_otp($conn, $sandbox) {
    $d = sitak_otp_digits($conn);
    if ($sandbox) return substr('123456', 0, $d);
    return str_pad((string)random_int(0, (int)pow(10, $d) - 1), $d, '0', STR_PAD_LEFT);
}
// ── بستن ثبت‌نام: فقط مدیر کاربر جدید می‌سازد (سوئیچ در بخش ۸ تنظیمات) ──
function sitak_register_closed($conn) {
    return getSetting($conn, 'register_closed', '0') === '1';
}
// پیام «ثبت‌نام بسته است» (قابل ویرایش در بخش ۸ تنظیمات)
function sitak_register_closed_msg_default() {
    return "ثبت‌نام در سایت فعلاً فقط توسط مدیر انجام می‌شود.\nبرای ساخت حساب کاربری، لطفاً با مدیر سایت تماس بگیرید.";
}
function sitak_register_closed_msg($conn) {
    $v = trim((string)getSetting($conn, 'register_closed_message', ''));
    return $v !== '' ? $v : sitak_register_closed_msg_default();
}
function sitak_guard_code_key($phone) { return 'code:' . hash('sha256', (string)$phone); }
function sitak_firewall_ip_hash() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return hash('sha256', (string)$ip);
}
function sitak_firewall_log($conn, $event_type, $path = '', $details = '') {
    try {
        $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250);
        $ip = sitak_firewall_ip_hash();
        $st = $conn->prepare("INSERT INTO firewall_events (event_type, ip_hash, path, user_agent, details) VALUES (?, ?, ?, ?, ?)");
        if (!$st) return;
        $st->bind_param('sssss', $event_type, $ip, substr((string)$path, 0, 190), $ua ?: null, $details ? substr((string)$details, 0, 250) : null);
        $st->execute();
    } catch (Throwable $e) {
        // لاگ‌نویسی خطا نباید باعث شکست برنامه شود
    }
}
function sitak_guard_fix_key() { return 'fix:' . sitak_firewall_ip_hash(); }

// ── اسکریپت‌های سفارشی فعال برای تزریق در <head> صفحات کاربری ──
// ── کدهای تخفیف: اعتبارسنجی و محاسبه (سمت سرور) ──
// خروجی: ['ok'=>bool,'message'=>string,'amount'=>int,'code'=>string,'id'=>int]
function sitak_discount_apply($conn, $raw_code, $items, $subtotal, $phone) {
    $fail = function ($m) { return ['ok' => false, 'message' => $m, 'amount' => 0, 'code' => '', 'id' => 0]; };
    if (getSetting($conn, 'discount_enabled', '0') !== '1') return $fail('کد تخفیف در حال حاضر فعال نیست.');
    $code = strtoupper(trim(sitak_normalize_digits((string)$raw_code)));
    if ($code === '') return $fail('کد تخفیف را وارد کنید.');
    $st = $conn->prepare("SELECT * FROM discount_codes WHERE code = ? LIMIT 1");
    $st->bind_param('s', $code); $st->execute();
    $d = $st->get_result()->fetch_assoc();
    if (!$d) return $fail('کد تخفیف معتبر نیست.');
    if ((int)$d['is_active'] !== 1) return $fail('این کد تخفیف فعال نیست.');
    if (!empty($d['expires_at']) && strtotime($d['expires_at']) < time()) return $fail('مهلت استفاده از این کد تخفیف تمام شده است.');
    if ($d['usage_limit'] !== null && (int)$d['used_count'] >= (int)$d['usage_limit']) return $fail('ظرفیت استفاده از این کد تمام شده است.');
    if (!empty($d['target_phone']) && !in_array((string)$phone, array_map('trim', explode(',', (string)$d['target_phone'])), true)) return $fail('این کد تخفیف برای حساب شما فعال نیست.');
    $eligible = 0;
    foreach ((array)$items as $it) {
        if ($d['product_id'] !== null && $d['product_id'] !== '' && !in_array((int)$it['product_id'], array_map('intval', explode(',', (string)$d['product_id'])), true)) continue;
        $eligible += (int)$it['price'] * (int)$it['qty'];
    }
    if ($eligible <= 0) return $fail('این کد برای محصولات سبد شما قابل استفاده نیست.');
    if ((int)$d['min_subtotal'] > 0 && (int)$subtotal < (int)$d['min_subtotal']) {
        return $fail('برای استفاده از این کد حداقل خرید ' . pn(number_format((int)$d['min_subtotal'])) . ' تومان لازم است.');
    }
    if ($d['type'] === 'percent') {
        $amount = intdiv($eligible * (int)$d['value'], 100);
        if ($d['max_discount'] !== null && (int)$d['max_discount'] > 0) $amount = min($amount, (int)$d['max_discount']);
    } else {
        $amount = (int)$d['value'];
    }
    $amount = max(0, min($amount, $eligible));
    if ($amount <= 0) return $fail('این کد برای سبد شما تخفیفی ندارد.');
    return ['ok' => true, 'message' => '', 'amount' => $amount, 'code' => $d['code'], 'id' => (int)$d['id']];
}
// مصرف یک‌باره‌ی کد؛ اگر ظرفیت تمام شده باشد false برمی‌گرداند.
function sitak_discount_consume($conn, $id) {
    $st = $conn->prepare("UPDATE discount_codes SET used_count = used_count + 1 WHERE id = ? AND (usage_limit IS NULL OR used_count < usage_limit)");
    $st->bind_param('i', $id); $st->execute();
    return $st->affected_rows === 1;
}

// ── هزینه و محدودیت ارسال (مبالغ به تومان؛ ۰ یعنی غیرفعال) ──
function sitak_shipping_settings($conn) {
    $int = function ($k) use ($conn) { return max(0, (int)getSetting($conn, $k, '0')); };
    return [
        'general_fee'      => $int('shipping_general_fee'),
        'general_free_min' => $int('shipping_general_free_min'),
        'scope_province'   => (string)getSetting($conn, 'store_shipping_province', ''),
        'scope_city'       => (string)getSetting($conn, 'store_shipping_city', ''),
        'scope_fee'        => $int('shipping_scope_fee'),
        'scope_min'        => $int('shipping_scope_min'),
        'scope_free_min'   => $int('shipping_scope_free_min'),
        'general_encourage_pct' => min(100, $int('shipping_general_encourage_pct')),
        'scope_encourage_pct'   => min(100, $int('shipping_scope_encourage_pct')),
    ];
}

// ── ترتیب نمایش کارت‌های صفحه‌ی اصلی (home) و بخش شانس (chance) ──
function sitak_card_keys($scope) {
    if ($scope === 'home') {
        return ['dice'=>'تاس و جایزه','wheel'=>'گردونه شانس','lastwin'=>'آخرین برد شما','loyalty'=>'کارت وفاداری','hafez'=>'فال حافظ','winners'=>'آخرین برندگان','shop'=>'محصولات (پرفروش/تازه‌ترین)','blog'=>'مطالب (بلاگ)'];
    }
    return ['dice'=>'تاس و جایزه','wheel'=>'گردونه شانس','lastwin'=>'آخرین برد شما','hafez'=>'فال حافظ','winners'=>'آخرین برندگان'];
}
function sitak_card_order($conn, $scope) {
    $items = [];
    foreach (array_keys(sitak_card_keys($scope)) as $i => $k) {
        $items[] = ['k' => $k, 'p' => (int)getSetting($conn, 'card_order_' . $scope . '_' . $k, (string)($i + 1)), 'i' => $i];
    }
    usort($items, function ($a, $b) { return ($a['p'] <=> $b['p']) ?: ($a['i'] <=> $b['i']); });
    return array_column($items, 'k');
}



// ── پاک‌سازی HTML ادیتور: فاصله‌ی &nbsp; به فاصله‌ی عادی، حذف اسکریپت و خصوصیت‌های on*، و فقط تگ‌های مجاز ──
function sitak_clean_rich($html) {
    $html = str_ireplace(['&nbsp;', '&#160;'], ' ', (string)$html);
    $html = preg_replace('#<(script|style)[^>]*>.*?</\\1>#is', '', $html);
    $html = preg_replace('/\s+on\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
    $html = preg_replace('/(href|src)\s*=\s*(["\'])\s*javascript:[^"\']*\2/i', '$1="#"', $html);
    $allowed = '<p><br><b><strong><i><em><u><s><ul><ol><li><h1><h2><h3><h4><blockquote><a><img><iframe><div><span><figure><figcaption><table><thead><tbody><tr><td><th><hr><code><pre>';
    return trim(strip_tags($html, $allowed));
}

// ── برچسب (تگ) محصولات ──
function sitak_product_tags($conn, $pid) {
    $out = [];
    $st = $conn->prepare("SELECT t.name, t.slug FROM product_tags t JOIN product_tag_relations r ON r.tag_id = t.id WHERE r.product_id = ? ORDER BY t.name");
    $st->bind_param("i", $pid); $st->execute();
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) $out[] = $row;
    return $out;
}
function sitak_save_product_tags($conn, $pid, $input) {
    $pid = (int)$pid;
    if ($pid < 1) return;
    $del = $conn->prepare("DELETE FROM product_tag_relations WHERE product_id = ?");
    $del->bind_param("i", $pid); $del->execute();
    $names = array_unique(array_filter(array_map(function ($t) { return trim(strip_tags($t)); }, preg_split('/[,،]/u', (string)$input))));
    foreach ($names as $name) {
        $name = mb_substr($name, 0, 50);
        $slug = trim(preg_replace('/[^\p{L}\p{N}]+/u', '-', mb_strtolower($name)), '-');
        if ($slug === '') continue;
        $slug = mb_substr($slug, 0, 60);
        $find = $conn->prepare("SELECT id FROM product_tags WHERE slug = ? LIMIT 1");
        $find->bind_param("s", $slug); $find->execute();
        $fr = $find->get_result()->fetch_assoc();
        if ($fr) { $tid = (int)$fr['id']; }
        else {
            $ins = $conn->prepare("INSERT INTO product_tags (name, slug) VALUES (?, ?)");
            $ins->bind_param("ss", $name, $slug); $ins->execute();
            $tid = (int)$conn->insert_id;
        }
        $rel = $conn->prepare("INSERT IGNORE INTO product_tag_relations (product_id, tag_id) VALUES (?, ?)");
        $rel->bind_param("ii", $pid, $tid); $rel->execute();
    }
}

// ── میانگین امتیاز محصولات (فقط نظرات تأییدشده) ──
function sitak_rating_text($avg) {
    return pn(str_replace('.', '٫', number_format((float)$avg, 1, '.', '')));
}
function sitak_post_ratings($conn, $ids) {
    $ids = array_values(array_filter(array_map('intval', $ids)));
    $out = [];
    if (!$ids) return $out;
    $list = implode(',', $ids);
    $res = $conn->query("SELECT post_id, AVG(rating) AS avg_rating, COUNT(*) AS cnt FROM post_comments WHERE status='approved' AND post_id IN ($list) GROUP BY post_id");
    if ($res) while ($r = $res->fetch_assoc()) { $out[(int)$r['post_id']] = ['avg' => (float)$r['avg_rating'], 'count' => (int)$r['cnt']]; }
    return $out;
}
function sitak_product_ratings($conn, $ids) {
    $ids = array_values(array_filter(array_map('intval', $ids)));
    $out = [];
    if (!$ids) return $out;
    $list = implode(',', $ids);
    $res = $conn->query("SELECT product_id, AVG(rating) AS avg_rating, COUNT(*) AS cnt FROM product_comments WHERE status='approved' AND product_id IN ($list) GROUP BY product_id");
    if ($res) while ($r = $res->fetch_assoc()) { $out[(int)$r['product_id']] = ['avg' => (float)$r['avg_rating'], 'count' => (int)$r['cnt']]; }
    return $out;
}

// هزینه‌ی ارسال و کنترل حداقل خرید را بر اساس جمع اقلام و نشانی گیرنده محاسبه می‌کند.
function sitak_shipping_quote($conn, $subtotal, $province, $city) {
    $s = sitak_shipping_settings($conn);
    $subtotal = (int)$subtotal;
    $in_scope = $s['scope_province'] !== '' && $province === $s['scope_province']
        && ($s['scope_city'] === '' || $city === $s['scope_city']);
    if ($in_scope) {
        if ($s['scope_min'] > 0 && $subtotal < $s['scope_min']) {
            return ['ok' => false, 'fee' => 0, 'free' => false, 'scope' => true, 'min' => $s['scope_min'],
                'message' => 'حداقل مقدار خرید برای ارسال به این محدوده ' . pn(number_format($s['scope_min'])) . ' تومان است.'];
        }
        $free = $s['scope_free_min'] > 0 && $subtotal >= $s['scope_free_min'];
        return ['ok' => true, 'fee' => $free ? 0 : $s['scope_fee'], 'free' => $free, 'scope' => true, 'min' => 0, 'message' => ''];
    }
    $free = $s['general_free_min'] > 0 && $subtotal >= $s['general_free_min'];
    return ['ok' => true, 'fee' => $free ? 0 : $s['general_fee'], 'free' => $free, 'scope' => false, 'min' => 0, 'message' => ''];
}

function custom_head_scripts($conn) {
    $out = '';
    $res = @$conn->query("SELECT code FROM custom_scripts WHERE is_active = 1 ORDER BY id ASC");
    if ($res) { while ($r = $res->fetch_assoc()) { $out .= $r['code'] . "\n"; } }
    return $out;
}

// ── تم پیش‌فرض سایت (light | dark | auto) ──
// 'auto' یعنی تم مرورگر/سیستم‌عامل کاربر (prefers-color-scheme).
// اسکریپت زیر قبل از رندر صفحه اجرا می‌شود تا فلش تم رخ ندهد.
// اگر کاربر قبلاً تمی را دستی انتخاب کرده باشد، همان برنده است.
function theme_head_script($storage_key = 'sitak_theme', $default = 'light', $color_dark = '#0a0a0c', $color_light = '#ffffff') {
    $k = json_encode($storage_key);
    $d = json_encode($default);
    $cd = json_encode($color_dark);
    $cl = json_encode($color_light);
    return '<script>(function(){'
        .'function R(v){return v==="auto"?(matchMedia("(prefers-color-scheme: dark)").matches?"dark":"light"):v}'
        .'try{var t=R(localStorage.getItem(' . $k . ')||' . $d . ');'
        . 'document.documentElement.setAttribute("data-theme",t);'
        . 'var m=document.getElementById("metaTheme");if(m)m.setAttribute("content",t==="dark"?' . $cd . ':' . $cl . ');'
        . 'window.__R=R;window.__T=t;}catch(e){}})();</script>';
}

// رزولو کردن تم در JS (همان منطق بالا، برای استفاده در کدهای صفحه)
function theme_js_resolver_fn() {
    return 'function __rt(v){return v==="auto"?(matchMedia("(prefers-color-scheme: dark)").matches?"dark":"light"):v}';
}

// ── ثبت بازدید صفحه/محصول/مطلب ──
function track_view($conn, $page_key) {
    $st = $conn->prepare("INSERT INTO page_views (page, view_date, views) VALUES (?, CURDATE(), 1) ON DUPLICATE KEY UPDATE views = views + 1");
    $st->bind_param("s", $page_key);
    $st->execute();
}

// ── تبدیل اعداد انگلیسی به فارسی ──
function pn($str) {
    return strtr((string)$str, [
        '0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹',
        '٠'=>'۰','١'=>'۱','٢'=>'۲','٣'=>'۳','٤'=>'۴','٥'=>'۵','٦'=>'۶','٧'=>'۷','٨'=>'۸','٩'=>'۹'
    ]);
}

// ── تبدیل میلادی به شمسی ──
function gregorian_to_jalali($gy, $gm, $gd) {
    $g_d_m = [0,31,59,90,120,151,181,212,243,273,304,334];
    $jy = ($gy <= 1600) ? 0 : 979;
    $gy -= ($gy <= 1600) ? 621 : 1600;
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = (365*$gy) + ((int)(($gy2+3)/4)) - ((int)(($gy2+99)/100)) + ((int)(($gy2+399)/400)) - 80 + $gd + $g_d_m[$gm-1];
    $jy += 33*((int)($days/12053));
    $days %= 12053;
    $jy += 4*((int)($days/1461));
    $days %= 1461;
    $jy += (int)(($days-1)/365);
    if ($days > 365) $days = ($days-1)%365;
    $jm = ($days < 186) ? 1+(int)($days/31) : 7+(int)(($days-186)/30);
    $jd = 1+(($days < 186) ? ($days%31) : (($days-186)%30));
    return [$jy,$jm,$jd];
}
function jdate_fa($datetime) {
    $ts = is_string($datetime) ? strtotime($datetime) : $datetime;
    if (!$ts) return '';
    list($jy,$jm,$jd) = gregorian_to_jalali((int)date('Y',$ts),(int)date('n',$ts),(int)date('j',$ts));
    return pn($jy.'/'.str_pad($jm,2,'0',STR_PAD_LEFT).'/'.str_pad($jd,2,'0',STR_PAD_LEFT));
}

// ── تبدیل تاریخ و ساعت میلادی به شمسی (با ساعت) ──
function jdatetime_fa($datetime) {
    $ts = is_string($datetime) ? strtotime($datetime) : $datetime;
    if (!$ts) return '';
    return jdate_fa($ts) . ' — ' . pn(date('H:i', $ts));
}

// ── تبدیل شمسی به میلادی ──
function jalali_to_gregorian($jy, $jm, $jd) {
    $jy += 1595;
    $days = -355668 + (365 * $jy) + (((int)($jy / 33)) * 8) + (((($jy % 33) + 3) / 4)) + $jd + (($jm < 7) ? ($jm - 1) * 31 : (($jm - 7) * 30) + 186);
    $gy = 400 * ((int)($days / 146097));
    $days %= 146097;
    if ($days > 36524) {
        $gy += 100 * ((int)(--$days / 36524));
        $days %= 36524;
        if ($days >= 365) $days++;
    }
    $gy += 4 * ((int)($days / 1461));
    $days %= 1461;
    if ($days > 365) { $gy += (int)(($days - 1) / 365); $days = ($days - 1) % 365; }
    $gd = $days + 1;
    $g_m = [31, ((($gy % 4 == 0 && $gy % 100 != 0) || ($gy % 400 == 0)) ? 29 : 28), 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    $gm = 0;
    while ($gm < 12 && $gd > $g_m[$gm]) { $gd -= $g_m[$gm]; $gm++; }
    return [$gy, $gm + 1, $gd];
}

// ── پارس ورودی تاریخ شمسی کاربر (مثل 1404/07/05 یا ۱۴۰۴/۰۷/۰۵) و تبدیل به میلادی ──
function jalali_input_to_greg($s) {
    $s = str_replace(['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'], ['0','1','2','3','4','5','6','7','8','9'], trim((string)$s));
    if (!preg_match('/^(\d{4})[\/\-\.](\d{1,2})[\/\-\.](\d{1,2})$/', $s, $m)) return '';
    if ((int)$m[2] < 1 || (int)$m[2] > 12 || (int)$m[3] < 1 || (int)$m[3] > 31) return '';
    list($gy, $gm, $gd) = jalali_to_gregorian((int)$m[1], (int)$m[2], (int)$m[3]);
    if (!checkdate($gm, $gd, $gy)) return '';
    return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
}

// تابع به دست آوردن زمان شروع بازه روزانه فعلی
function get_cycle_start_time($reset_hour) {
    $now = new DateTime();
    $today_reset = new DateTime();
    $today_reset->setTime((int)$reset_hour, 0, 0);
    if ($now < $today_reset) {
        $today_reset->modify('-1 day');
    }
    return $today_reset;
}

// تابع تبدیل تاریخ میلادی به زمان‌های نسبی چند دقیقه / روز پیش
function time_ago($datetime) {
    if (!$datetime) return 'نامشخص';
    $time = strtotime($datetime);
    $diff = time() - $time;

    if ($diff < 1) {
        return 'همین الان';
    }

    $tokens = array (
        31536000 => 'سال',
        2592000 => 'ماه',
        604800 => 'هفته',
        86400 => 'روز',
        3600 => 'ساعت',
        60 => 'دقیقه',
        1 => 'ثانیه'
    );

    foreach ($tokens as $unit => $text) {
        if ($diff < $unit) continue;
        $numberOfUnits = floor($diff / $unit);
        return $numberOfUnits . ' ' . $text . ' پیش';
    }
    return 'همین الان';
}

// بررسی دسترسی کاربر و تعداد شانس‌های او
function get_user_rolls_info($conn, $phone) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE phone = ?");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->fetch_assoc();
    if (!$user) return null;

    $reset_hour_res = $conn->query("SELECT value_data FROM settings WHERE key_name = 'reset_hour'");
    $reset_hour = $reset_hour_res->fetch_assoc()['value_data'] ?? '21';
    $r_en_res = $conn->query("SELECT value_data FROM settings WHERE key_name = 'reset_hour_enabled'");
    $reset_hour_enabled = $r_en_res->fetch_assoc()['value_data'] ?? '1';

    $cycle_start = get_cycle_start_time($reset_hour);

    // تعداد شانس روزانه (پیش‌فرض ۱) و تعداد مصرف‌شده در همین چرخه
    $daily_limit_res = $conn->query("SELECT value_data FROM settings WHERE key_name = 'daily_roll_count'");
    $daily_limit = max(0, min(50, (int)($daily_limit_res ? ($daily_limit_res->fetch_assoc()['value_data'] ?? '1') : '1')));
    $used_stored = (int)($user['daily_rolls_used'] ?? 0);
    $used_today = 0;

    if ($user['last_daily_roll']) {
        if ($reset_hour_enabled === '0') {
            $used_today = $used_stored; // ریست روزانه غیرفعال است، شمارش ادامه دارد
        } else {
            $last_roll_time = new DateTime($user['last_daily_roll']);
            if ($last_roll_time >= $cycle_start) {
                $used_today = $used_stored; // همین چرخه
            }
        }
    }

    $daily_left = max(0, $daily_limit - $used_today);
    $has_daily_roll = $daily_left > 0;
    $total_rolls = $daily_left + $user['referral_rolls'];
    return [
        'user' => $user,
        'has_daily_roll' => $has_daily_roll,
        'daily_used_today' => $used_today,
        'referral_rolls' => $user['referral_rolls'],
        'total_rolls' => $total_rolls
    ];
}

// تولید کدهای گرافیکی زیبای وکتور برای تاس‌های وفاداری با پالت رنگی جدید طلایی #ecb534
function get_dice_svg($value, $theme = 'empty') {
    $bg = '#FAF6F0';
    $dotColor = '#1a1a1a';
    $stroke = 'none';
    $dash = 'none';

    if ($theme === 'earned') {
        $bg = 'url(#earnedGrad)';
        $dotColor = '#FAF6F0';
    } elseif ($theme === 'in-progress') {
        $bg = 'url(#progressGrad)';
        $dotColor = '#1a1a1a';
    } else { // empty
        $bg = '#FDFBF7';
        $dotColor = '#E2D3BB';
        $stroke = '#ecb534';
        $dash = '4,4';
    }

    $dots = [];
    switch ($value) {
        case 1: $dots = [[30, 30]]; break;
        case 2: $dots = [[15, 15], [45, 45]]; break;
        case 3: $dots = [[15, 15], [30, 30], [45, 45]]; break;
        case 4: $dots = [[15, 15], [15, 45], [45, 15], [45, 45]]; break;
        case 5: $dots = [[15, 15], [15, 45], [30, 30], [45, 15], [45, 45]]; break;
        case 6: $dots = [[15, 15], [15, 30], [15, 45], [45, 15], [45, 30], [45, 45]]; break;
    }

    $svg = '<svg width="60" height="60" viewBox="0 0 60 60" style="display:block; overflow:visible;">';
    $svg .= '<defs>';
    $svg .= '  <linearGradient id="earnedGrad" x1="0%" y1="0%" x2="100%" y2="100%">';
    $svg .= '    <stop offset="0%" stop-color="#f5c758"/>';
    $svg .= '    <stop offset="100%" stop-color="#ecb534"/>';
    $svg .= '  </linearGradient>';
    $svg .= '  <linearGradient id="progressGrad" x1="0%" y1="0%" x2="100%" y2="100%">';
    $svg .= '    <stop offset="0%" stop-color="#FFF3D1"/>';
    $svg .= '    <stop offset="100%" stop-color="#f2dfa7"/>';
    $svg .= '  </linearGradient>';
    $svg .= '  <filter id="cozyShadow" x="-10%" y="-10%" width="130%" height="130%">';
    $svg .= '    <feDropShadow dx="0" dy="3" stdDeviation="2" flood-opacity="0.12"/>';
    $svg .= '  </filter>';
    $svg .= '</defs>';

    $strokeAttr = ($stroke !== 'none') ? "stroke=\"$stroke\" stroke-width=\"1.5\" stroke-dasharray=\"$dash\"" : 'stroke="none"';
    $filterAttr = ($theme !== 'empty') ? 'filter="url(#cozyShadow)"' : '';
    $svg .= "<rect x=\"1\" y=\"1\" width=\"58\" height=\"58\" rx=\"14\" fill=\"$bg\" $strokeAttr $filterAttr />";

    if ($theme !== 'empty') {
        $svg .= '<rect x="3" y="3" width="54" height="20" rx="10" fill="#ffffff" opacity="0.1" />';
    }

    foreach ($dots as $dot) {
        $r = ($value == 1) ? 7 : 5;
        $color = ($value == 1 && $theme !== 'earned') ? '#E76F51' : $dotColor;
        $svg .= "<circle cx=\"{$dot[0]}\" cy=\"{$dot[1]}\" r=\"$r\" fill=\"$color\" />";
    }

    $svg .= '</svg>';
    return $svg;
}

// تولید کدهای HTML مربوط به تاس‌های مراجعات کارت وفاداری کاربر
function get_loyalty_dice_html($visits, $target_dice) {
    $html = '';
    $remaining = $visits;
    $persian = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];

    for ($i = 1; $i <= $target_dice; $i++) {
        if ($remaining >= 6) {
            $html .= '<div class="loyalty-die earned">' . get_dice_svg(6, 'earned') . '<span class="die-label">۶/۶</span></div>';
            $remaining -= 6;
        } elseif ($remaining > 0) {
            $pNum = str_replace(range(0,9), $persian, $remaining);
            $html .= '<div class="loyalty-die in-progress">' . get_dice_svg($remaining, 'in-progress') . '<span class="die-label">' . $pNum . '/۶</span></div>';
            $remaining = 0;
        } else {
            $html .= '<div class="loyalty-die empty">' . get_dice_svg(1, 'empty') . '<span class="die-label">۰/۶</span></div>';
        }
    }
    return $html;
}

// فرمت ماسک کردن شماره تلفن برای امنیت و حفظ حریم خصوصی
function mask_phone($phone) {
    if (strlen($phone) < 11) return $phone;
    return substr($phone, 0, 4) . '***' . substr($phone, -4);
}

// سیستم شبیه‌سازی هوشمند برندگان فیک — قابل کنترل از پنل ادمین
function trigger_fake_winner($conn) {
    $fake_enabled = getSetting($conn, 'winners_fake_enabled', '1');
    if ($fake_enabled !== '1') return;

    $min_hours = max(1, min(48, intval(getSetting($conn, 'fake_interval_hours_min', '2'))));
    $max_hours = max(1, min(48, intval(getSetting($conn, 'fake_interval_hours_max', '6'))));
    if ($min_hours > $max_hours) { $tmp = $min_hours; $min_hours = $max_hours; $max_hours = $tmp; }
    $winner_count = max(1, intval(getSetting($conn, 'fake_winner_count', '1')));

    $now = new DateTime();
    // زمان دور بعدی در تنظیمات ذخیره می‌شود تا بازه‌ی رندوم هر بار از نو قرعه‌کشی نشود.
    $next_raw = getSetting($conn, 'fake_next_at', '');
    $next_ts = ($next_raw !== '') ? strtotime($next_raw) : false;
    if ($next_ts !== false && $now->getTimestamp() < $next_ts) {
        sitak_prune_fake_winners($conn);
        return;
    }
    $interval_secs = rand($min_hours, $max_hours) * 3600;
    $next_at = date('Y-m-d H:i:s', $now->getTimestamp() + $interval_secs);
    $sv = $conn->prepare("INSERT INTO settings (key_name, value_data) VALUES ('fake_next_at', ?) ON DUPLICATE KEY UPDATE value_data = ?");
    $sv->bind_param("ss", $next_at, $next_at); $sv->execute();

    if (true) {
        $prefixes = [];
        $pr = $conn->query("SELECT prefix FROM fake_prefixes WHERE is_active = 1");
        if ($pr && $pr->num_rows > 0) {
            while ($row = $pr->fetch_assoc()) {
                $prefixes[] = $row['prefix'];
            }
        }
        if (empty($prefixes)) {
            $prefixes = ['0912', '0919', '0935', '0936', '0937'];
        }

        // جوایزی که تعداد دارند (qty_limit) هرگز برای برنده‌ی ساختگی استفاده نمی‌شوند.
        // اگر ادمین هیچ جایزه‌ی فعالی وارد نکرده، هیچ برنده‌ی جعلی هم ساخته نمی‌شود.
        // سوئیچ «فعال در گردونه» اینجا تأثیری ندارد؛ هر جایزه‌ی غیر پوچ با توضیحات می‌تواند برای برنده‌ی ساختگی استفاده شود.
        $prize_pool = [];
        $prizes_res = $conn->query("SELECT description FROM prizes WHERE is_void = 0 AND TRIM(description) <> '' AND qty_limit IS NULL");
        while ($prizes_res && $pr_row = $prizes_res->fetch_assoc()) { $prize_pool[] = $pr_row['description']; }
        if (empty($prize_pool)) { sitak_prune_fake_winners($conn); return; }

        for ($i = 0; $i < $winner_count; $i++) {
            // هر برنده‌ی ساختگی یک جایزه‌ی تصادفی از بین همه‌ی جوایز فعال‌شدنی می‌گیرد
            $prize_name = $prize_pool[array_rand($prize_pool)];
            $prefix = $prefixes[array_rand($prefixes)];
            $middle = str_pad(rand(0, 999), 3, '0', STR_PAD_LEFT);
            $end = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
            $fake_phone = $prefix . $middle . $end;

            $random_past = rand(1, max(61, $interval_secs - 60));
            $fake_time = clone $now;
            $fake_time->modify("-$random_past seconds");

            $stmt = $conn->prepare("INSERT INTO winners (phone, name, prize_name, won_at, is_fake) VALUES (?, NULL, ?, ?, 1)");
            $formatted_time = $fake_time->format('Y-m-d H:i:s');
            $stmt->bind_param("sss", $fake_phone, $prize_name, $formatted_time);
            $stmt->execute();
        }
    }
    sitak_prune_fake_winners($conn);
}

// فقط به اندازه‌ی «تعداد نمایش برندگان» آخرین برندگان ساختگی را نگه می‌دارد و بقیه‌ی قدیمی‌ها پاک می‌شوند.
function sitak_prune_fake_winners($conn) {
    $keep = max(1, min(50, (int)getSetting($conn, 'winners_display_count', '5')));
    $conn->query("DELETE FROM winners WHERE is_fake = 1 AND id NOT IN (SELECT id FROM (SELECT id FROM winners WHERE is_fake = 1 ORDER BY won_at DESC, id DESC LIMIT " . $keep . ") AS keep_rows)");
}
