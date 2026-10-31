<?php
/* ═══════════════════════════════════════════════════════════
   admin_common.php — احراز هویت، هندلرها و اجزای مشترک پنل ادمین
   هر صفحه قبل از require، متغیر $admin_self را ست می‌کند.
   ═══════════════════════════════════════════════════════════ */
require_once 'config.php';
require_once __DIR__ . '/emergency_core.php';
require_once __DIR__ . '/webpush.php';
$admin_self = $admin_self ?? 'admin.php';

if (isset($_POST['admin_login'])) {
    $password = $_POST['password'] ?? '';
    $tkey = 'admin:' . ($_SERVER['REMOTE_ADDR'] ?? '');
    $blocked = sitak_throttle_blocked($tkey);
    $pass_res = $conn->query("SELECT value_data FROM settings WHERE key_name = 'admin_password'");
    $db_pass = $pass_res->fetch_assoc()['value_data'] ?? '';
    if (!$blocked && sitak_password_matches($password, $db_pass)) {
        sitak_throttle_clear($tkey);
        $_SESSION['sitak_admin'] = true;
        // Upgrade old plaintext credentials transparently after a successful login.
        if ((password_get_info($db_pass)['algo'] ?? 0) === 0) {
            $upgrade = $conn->prepare("UPDATE settings SET value_data=? WHERE key_name='admin_password'");
            $hashed = sitak_password_hash($password); $upgrade->bind_param('s', $hashed); $upgrade->execute();
        }
        session_regenerate_id(true);
        sitak_admin_remember_issue($conn);
    } else {
        if (!$blocked) sitak_throttle_fail($tkey);
        $login_error = $blocked ? "تعداد تلاش‌ها زیاد بود. ۱۵ دقیقه بعد دوباره امتحان کنید." : "گذرواژه اشتباه است.";
    }
}
if (isset($_GET['logout'])) { sitak_admin_remember_revoke($conn); unset($_SESSION['sitak_admin']); header('Location: '.$admin_self); exit; }

$is_authenticated = $_SESSION['sitak_admin'] ?? false;

// لغو خودکار سفارش‌های کارت‌به‌کارت منقضی‌شده (آزادسازی موجودی) هنگام باز شدن هر صفحه‌ی پنل
if ($is_authenticated) sitak_expire_card_orders($conn);

if ($is_authenticated) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['admin_login']) && !sitak_csrf_valid($_POST['csrf_token'] ?? null)) {
        http_response_code(403); exit('درخواست نامعتبر است. صفحه را دوباره بارگذاری کنید.');
    }
    $csrf = htmlspecialchars(sitak_csrf_token(), ENT_QUOTES, 'UTF-8');
    ob_start(function ($html) use ($csrf) {
        if (stripos($html, 'application/json') !== false) return $html;
        return preg_replace_callback('/<form\\b([^>]*)>/i', function ($m) use ($csrf) {
            if (stripos($m[1], 'method="get"') !== false || stripos($m[1], "method='get'") !== false) return $m[0];
            return $m[0] . '<input type="hidden" name="csrf_token" value="' . $csrf . '">';
        }, $html);
    });
}

// ── پیشوند صفحات پنل ادمین (بر اساس نشانی سفارشی) ──
$admin_login_slug = trim(getSetting($conn, 'admin_login_slug', ''));
$admin_prefix = $admin_login_slug !== '' ? $admin_login_slug : 'admin';
if (!function_exists('admin_url')) {
    function admin_url($page = 'home') {
        global $admin_prefix;
        return ($page === 'home') ? $admin_prefix . '.php' : $admin_prefix . '-' . $page . '.php';
    }
}

// ── همگام‌سازی صفحه‌ی اضطراری (هش گذرواژه داخل فایل خودش، نه در دیتابیس) ──
if ($is_authenticated) { sitak_emergency_sync($conn, $admin_prefix); }

// ── گیت نشانی لاگین سفارشی ──
// اگر نشانی سفارشی تنظیم شده باشد، دسترسی مستقیم به فایل‌های admin-*.php مسدود است.
// فایل‌های پروکسی (مثل pamah-settings.php) از داخل خودشان فایل canonical را require می‌کنند؛
// در آن حالت $admin_self نام پروکسی است و از این روی اجرای آن‌ها مجاز می‌ماند.
$__this_base = basename($_SERVER['PHP_SELF'] ?? '');
$__admin_files = ['admin.php','admin-settings.php','admin-chance.php','admin-blog.php','admin-shop.php','admin-stats.php','admin-upload.php','admin-comments.php'];
$__allowed_self = false;
if ($admin_login_slug !== '') {
    foreach (['', '-settings', '-chance', '-blog', '-shop', '-stats', '-upload', '-comments'] as $__sfx) {
        if (basename($admin_self) === $admin_login_slug . $__sfx . '.php') { $__allowed_self = true; break; }
    }
}
if ($admin_login_slug !== '' && in_array($__this_base, $__admin_files, true) && !$__allowed_self) {
    http_response_code(404);
    $nf = __DIR__ . '/404.php';
    if (file_exists($nf)) { include $nf; }
    else { echo '404 — صفحه پیدا نشد.'; }
    exit;
}

// گوشه‌های گرد فاوآیکون (PNG و JPG): تصویر مربع می‌شود، لبه‌ها شفاف و گرد می‌شوند و به PNG ذخیره می‌شود.
function sitak_round_favicon($src_path, $dest_path) {
    if (!function_exists('imagecreatefromstring') || !function_exists('imagepng') || !function_exists('imagecreatetruecolor')) return false;
    $data = @file_get_contents($src_path);
    $img = ($data !== false) ? @imagecreatefromstring($data) : false;
    if (!$img) return false;
    $w = imagesx($img); $h = imagesy($img);
    $side = min($w, $h);
    if ($side < 1) { imagedestroy($img); return false; }
    $size = min($side, 512);
    $out = imagecreatetruecolor($size, $size);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    $transparent = imagecolorallocatealpha($out, 0, 0, 0, 127);
    imagefill($out, 0, 0, $transparent);
    imagecopyresampled($out, $img, 0, 0, (int)(($w - $side) / 2), (int)(($h - $side) / 2), $size, $size, $side, $side);
    imagedestroy($img);
    // همان نسبت گوشه‌ی آیکون هدر (۱۰px از ۳۶px)
    $r = (int)round($size * (10 / 36));
    for ($x = 0; $x < $r; $x++) {
        for ($y = 0; $y < $r; $y++) {
            $dx = $r - $x - 0.5; $dy = $r - $y - 0.5;
            if ($dx * $dx + $dy * $dy > $r * $r) {
                imagesetpixel($out, $x, $y, $transparent);
                imagesetpixel($out, $size - 1 - $x, $y, $transparent);
                imagesetpixel($out, $x, $size - 1 - $y, $transparent);
                imagesetpixel($out, $size - 1 - $x, $size - 1 - $y, $transparent);
            }
        }
    }
    $ok = imagepng($out, $dest_path);
    imagedestroy($out);
    return (bool)$ok;
}

// ── بارگذاری لوگو و فاوآیکون ──
if ($is_authenticated && (isset($_POST['upload_assets']) || isset($_POST['delete_favicon']) || isset($_POST['delete_logo']) || isset($_POST['save_custom_emoji']))) {
    if (!file_exists('uploads')) { @mkdir('uploads', 0755, true); }
    if (isset($_FILES['favicon']) && $_FILES['favicon']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['favicon'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['png', 'jpg', 'jpeg', 'gif', 'ico', 'svg', 'webp'];
        if (in_array($ext, $allowed) && sitak_safe_image_upload($file, $allowed)) {
            $old_fav = getSetting($conn, 'custom_favicon', '');
            if (!empty($old_fav) && file_exists($old_fav)) { @unlink($old_fav); }
            // تصاویر رستری با گوشه‌های گرد ذخیره می‌شوند (PNG شفاف)؛ اگر GD نبود یا پردازش شکست خورد، فایل اصلی ذخیره می‌شود.
            $rounded_path = 'uploads/favicon_' . time() . '.png';
            if (in_array($ext, ['png', 'jpg', 'jpeg'], true) && sitak_round_favicon($file['tmp_name'], $rounded_path)) {
                $new_path = $rounded_path;
                $saved = true;
            } else {
                $new_path = 'uploads/favicon_' . time() . '.' . $ext;
                $saved = move_uploaded_file($file['tmp_name'], $new_path);
            }
            if ($saved) {
                $stmt = $conn->prepare("INSERT INTO settings (key_name, value_data) VALUES ('custom_favicon', ?) ON DUPLICATE KEY UPDATE value_data = ?");
                $stmt->bind_param("ss", $new_path, $new_path); $stmt->execute();
            }
        }
    }
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['logo'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp'];
        if (in_array($ext, $allowed) && sitak_safe_image_upload($file, $allowed)) {
            $old_logo = getSetting($conn, 'custom_logo', '');
            if (!empty($old_logo) && file_exists($old_logo)) { @unlink($old_logo); }
            $new_path = 'uploads/logo_' . time() . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $new_path)) {
                $stmt = $conn->prepare("INSERT INTO settings (key_name, value_data) VALUES ('custom_logo', ?) ON DUPLICATE KEY UPDATE value_data = ?");
                $stmt->bind_param("ss", $new_path, $new_path); $stmt->execute();
            }
        }
    }
    if (isset($_POST['delete_favicon'])) {
        $old_fav = getSetting($conn, 'custom_favicon', '');
        if (!empty($old_fav) && file_exists($old_fav)) { @unlink($old_fav); }
        $stmt = $conn->prepare("DELETE FROM settings WHERE key_name = 'custom_favicon'"); $stmt->execute();
    }
    // ایموجی سفارشی: هم با دکمه‌ی اختصاصی قدیمی و هم با دکمه‌ی ذخیره‌ی یکپارچه‌ی بخش ۲ (upload_assets) ذخیره می‌شود
    if (isset($_POST['save_custom_emoji']) || (isset($_POST['upload_assets']) && isset($_POST['custom_emoji']))) {
        $emoji = trim($_POST['custom_emoji'] ?? '🌐');
        if ($emoji === '') $emoji = '🌐';
        $show_emoji = isset($_POST['show_header_emoji']) ? '1' : '0';
        $st_emoji_flag = $conn->prepare("INSERT INTO settings (key_name, value_data) VALUES ('show_header_emoji', ?) ON DUPLICATE KEY UPDATE value_data = ?");
        $st_emoji_flag->bind_param("ss", $show_emoji, $show_emoji); $st_emoji_flag->execute();
        $emoji = mb_substr($emoji, 0, 8, 'UTF-8');
        $stmt = $conn->prepare("INSERT INTO settings (key_name, value_data) VALUES ('custom_emoji', ?) ON DUPLICATE KEY UPDATE value_data = ?");
        $stmt->bind_param("ss", $emoji, $emoji); $stmt->execute();
    }
    if (isset($_POST['delete_logo'])) {
        $old_logo = getSetting($conn, 'custom_logo', '');
        if (!empty($old_logo) && file_exists($old_logo)) { @unlink($old_logo); }
        $stmt = $conn->prepare("DELETE FROM settings WHERE key_name = 'custom_logo'"); $stmt->execute();
    }
    header('Location: '.$admin_self); exit;
}

// ── مجوزها ──
if ($is_authenticated && (isset($_POST['save_licenses']) || isset($_POST['delete_license1']) || isset($_POST['delete_license2']))) {
    if (!file_exists('uploads')) { @mkdir('uploads', 0755, true); }
    $lic = [
        'license1_img' => getSetting($conn, 'license1_img', ''),
        'license2_img' => getSetting($conn, 'license2_img', ''),
    ];
    if (isset($_FILES['license1_img']) && $_FILES['license1_img']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['license1_img'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['png','jpg','jpeg','gif','svg','webp'];
        if (in_array($ext, $allowed) && sitak_safe_image_upload($file, $allowed)) {
            if (!empty($lic['license1_img']) && file_exists($lic['license1_img'])) { @unlink($lic['license1_img']); }
            $new_path = 'uploads/license1_' . time() . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $new_path)) {
                $lic['license1_img'] = $new_path;
                $st = $conn->prepare("INSERT INTO settings (key_name, value_data) VALUES ('license1_img', ?) ON DUPLICATE KEY UPDATE value_data = ?");
                $st->bind_param("ss", $new_path, $new_path); $st->execute();
            }
        }
    }
    if (isset($_FILES['license2_img']) && $_FILES['license2_img']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['license2_img'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['png','jpg','jpeg','gif','svg','webp'];
        if (in_array($ext, $allowed) && sitak_safe_image_upload($file, $allowed)) {
            if (!empty($lic['license2_img']) && file_exists($lic['license2_img'])) { @unlink($lic['license2_img']); }
            $new_path = 'uploads/license2_' . time() . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $new_path)) {
                $lic['license2_img'] = $new_path;
                $st = $conn->prepare("INSERT INTO settings (key_name, value_data) VALUES ('license2_img', ?) ON DUPLICATE KEY UPDATE value_data = ?");
                $st->bind_param("ss", $new_path, $new_path); $st->execute();
            }
        }
    }
    if (isset($_POST['delete_license1'])) {
        if (!empty($lic['license1_img']) && file_exists($lic['license1_img'])) { @unlink($lic['license1_img']); }
        $st = $conn->prepare("DELETE FROM settings WHERE key_name = 'license1_img'"); $st->execute();
    }
    if (isset($_POST['delete_license2'])) {
        if (!empty($lic['license2_img']) && file_exists($lic['license2_img'])) { @unlink($lic['license2_img']); }
        $st = $conn->prepare("DELETE FROM settings WHERE key_name = 'license2_img'"); $st->execute();
    }
    $le = isset($_POST['licenses_enabled']) ? '1' : '0';
    $st = $conn->prepare("INSERT INTO settings (key_name, value_data) VALUES ('licenses_enabled', ?) ON DUPLICATE KEY UPDATE value_data = ?");
    $st->bind_param("ss", $le, $le); $st->execute();
    $l1 = htmlspecialchars(trim($_POST['license1_link'] ?? ''), ENT_QUOTES, 'UTF-8');
    $st = $conn->prepare("INSERT INTO settings (key_name, value_data) VALUES ('license1_link', ?) ON DUPLICATE KEY UPDATE value_data = ?");
    $st->bind_param("ss", $l1, $l1); $st->execute();
    $l1n = htmlspecialchars(strip_tags(trim($_POST['license1_name'] ?? '')), ENT_QUOTES, 'UTF-8');
    if (mb_strlen($l1n, 'UTF-8') > 60) $l1n = mb_substr($l1n, 0, 60, 'UTF-8');
    $st = $conn->prepare("INSERT INTO settings (key_name, value_data) VALUES ('license1_name', ?) ON DUPLICATE KEY UPDATE value_data = ?");
    $st->bind_param("ss", $l1n, $l1n); $st->execute();
    $l2 = htmlspecialchars(trim($_POST['license2_link'] ?? ''), ENT_QUOTES, 'UTF-8');
    $st = $conn->prepare("INSERT INTO settings (key_name, value_data) VALUES ('license2_link', ?) ON DUPLICATE KEY UPDATE value_data = ?");
    $st->bind_param("ss", $l2, $l2); $st->execute();
    $l2n = htmlspecialchars(strip_tags(trim($_POST['license2_name'] ?? '')), ENT_QUOTES, 'UTF-8');
    if (mb_strlen($l2n, 'UTF-8') > 60) $l2n = mb_substr($l2n, 0, 60, 'UTF-8');
    $st = $conn->prepare("INSERT INTO settings (key_name, value_data) VALUES ('license2_name', ?) ON DUPLICATE KEY UPDATE value_data = ?");
    $st->bind_param("ss", $l2n, $l2n); $st->execute();
    $l1e = trim($_POST['license1_embed'] ?? '');
    $st = $conn->prepare("INSERT INTO settings (key_name, value_data) VALUES ('license1_embed', ?) ON DUPLICATE KEY UPDATE value_data = ?");
    $st->bind_param("ss", $l1e, $l1e); $st->execute();
    $l2e = trim($_POST['license2_embed'] ?? '');
    $st = $conn->prepare("INSERT INTO settings (key_name, value_data) VALUES ('license2_embed', ?) ON DUPLICATE KEY UPDATE value_data = ?");
    $st->bind_param("ss", $l2e, $l2e); $st->execute();
    header('Location: '.$admin_self); exit;
}

// ── اسکریپت‌های سفارشی هدر ──
if ($is_authenticated && isset($_POST['save_script'])) {
    $sid = intval($_POST['script_id'] ?? 0);
    $title = htmlspecialchars(strip_tags(trim($_POST['script_title'] ?? '')), ENT_QUOTES, 'UTF-8');
    $code = trim($_POST['script_code'] ?? '');
    if ($title === '' || $code === '') { header('Location: '.$admin_self); exit; }
    if (mb_strlen($title, 'UTF-8') > 60) { $title = mb_substr($title, 0, 60, 'UTF-8'); }
    if ($sid > 0) {
        $st = $conn->prepare("UPDATE custom_scripts SET title=?, code=? WHERE id=?");
        $st->bind_param("ssi", $title, $code, $sid);
    } else {
        $st = $conn->prepare("INSERT INTO custom_scripts (title, code) VALUES (?, ?)");
        $st->bind_param("ss", $title, $code);
    }
    $st->execute();
    header('Location: '.$admin_self); exit;
}
if ($is_authenticated && isset($_GET['delete_script'])) {
    if (!sitak_csrf_valid($_GET['csrf'] ?? '')) { http_response_code(403); exit('درخواست نامعتبر است.'); }
    $sid = intval($_GET['delete_script']);
    $st = $conn->prepare("DELETE FROM custom_scripts WHERE id=?");
    $st->bind_param("i", $sid); $st->execute();
    header('Location: '.$admin_self); exit;
}
if ($is_authenticated && isset($_GET['toggle_script'])) {
    if (!sitak_csrf_valid($_GET['csrf'] ?? '')) { http_response_code(403); exit('درخواست نامعتبر است.'); }
    $sid = intval($_GET['toggle_script']);
    $st = $conn->prepare("UPDATE custom_scripts SET is_active = 1 - is_active WHERE id=?");
    $st->bind_param("i", $sid); $st->execute();
    header('Location: '.$admin_self); exit;
}
if ($is_authenticated && isset($_GET['script_json'])) {
    $sid = intval($_GET['script_json']);
    $st = $conn->prepare("SELECT id,title,code FROM custom_scripts WHERE id=?");
    $st->bind_param("i", $sid); $st->execute();
    $s = $st->get_result()->fetch_assoc();
    if (!$s) { if (ob_get_length()) { ob_clean(); } echo json_encode(['success'=>false,'message'=>'اسکریپت یافت نشد']); exit; }
    if (ob_get_length()) { ob_clean(); }
    echo json_encode(['success'=>true,'script'=>$s]); exit;
}

// ── اکسپورت CSV ──
if ($is_authenticated && isset($_GET['export'])) {
    $export_type = $_GET['export'];
    header('Content-Type: text/csv; charset=utf-8');
    if ($export_type === 'winners') {
        header('Content-Disposition: attachment; filename="sitak_winners.csv"');
        $output = fopen('php://output', 'w'); fputs($output, "\xEF\xBB\xBF");
        fputcsv($output, ['شناسه','شماره تلفن','نام','جایزه','زمان ثبت برد','تحویل شده']);
        $res = $conn->query("SELECT * FROM winners WHERE is_fake = 0 ORDER BY won_at DESC");
        while($r = $res->fetch_assoc()) { fputcsv($output, [$r['id'],$r['phone'],$r['name'] ?: 'مهمان',$r['prize_name'],jdatetime_fa($r['won_at']),$r['claimed'] ? 'بله' : 'خیر']); }
        fclose($output); exit;
    }
    if ($export_type === 'users') {
        header('Content-Disposition: attachment; filename="sitak_users.csv"');
        $output = fopen('php://output', 'w'); fputs($output, "\xEF\xBB\xBF");
        fputcsv($output, ['شناسه','شماره تلفن','نام','معرف','مراجعات','رفرال‌ها','زمان ثبت‌نام']);
        $res = $conn->query("SELECT u.*, (SELECT COUNT(*) FROM users r WHERE r.referral_code = u.phone) as total_referrals FROM users u ORDER BY created_at DESC");
        while($r = $res->fetch_assoc()) { fputcsv($output, [$r['id'],$r['phone'],$r['name'] ?: 'بدون نام',$r['referral_code'] ?: 'ندارد',$r['visits'],$r['total_referrals'],jdatetime_fa($r['created_at'])]); }
        fclose($output); exit;
    }
    if ($export_type === 'orders') {
        header('Content-Disposition: attachment; filename="sitak_orders.csv"');
        $output = fopen('php://output', 'w'); fputs($output, "\xEF\xBB\xBF");
        fputcsv($output, ['شناسه','شماره تلفن','نام مشتری','آیتم‌ها','مبلغ کل (تومان)','وضعیت','یادداشت کاربر','یادداشت ادمین','زمان ثبت']);
        $res = $conn->query("SELECT * FROM orders ORDER BY created_at DESC");
        while($r = $res->fetch_assoc()) {
            $items_str = '';
            $items = json_decode($r['items'], true);
            if (is_array($items)) { foreach ($items as $it) { $items_str .= ($it['name'] ?? '') . ' × ' . ($it['qty'] ?? 1) . '؛ '; } }
            $status_fa = ['pending'=>'در انتظار','confirmed'=>'تأیید شده','shipped'=>'ارسال شده','delivered'=>'تحویل شده','cancelled'=>'لغو شده'];
            fputcsv($output, [$r['id'],$r['user_phone'],$r['user_name'] ?: 'مهمان',trim($items_str),$r['total_price'],$status_fa[$r['status']] ?? $r['status'],$r['note'],$r['admin_note'],jdatetime_fa($r['created_at'])]);
        }
        fclose($output); exit;
    }
}

// ── ذخیره جایزه ──
if ($is_authenticated && isset($_POST['add_prize'])) {
    $prize_id = intval($_POST['prize_id'] ?? 0);
    $desc = htmlspecialchars(strip_tags(trim($_POST['desc'] ?? '')), ENT_QUOTES, 'UTF-8');
    $link = htmlspecialchars(trim($_POST['link'] ?? ''), ENT_QUOTES, 'UTF-8');
    $discount = htmlspecialchars(strip_tags(trim($_POST['discount'] ?? '')), ENT_QUOTES, 'UTF-8');
    $probability = floatval($_POST['probability'] ?? 0);
    $is_local = isset($_POST['is_local']) ? 1 : 0;
    $is_instant = isset($_POST['is_instant']) ? 1 : 0;
    // تعداد و مهلت: خالی = بدون محدودیت؛ ۰ = تمام‌شده
    $qty_raw = trim((string)($_POST['qty_limit'] ?? ''));
    $qty_limit = $qty_raw === '' ? null : max(0, (int)$qty_raw);
    $days_raw = trim((string)($_POST['time_days'] ?? ''));
    $time_days = $days_raw === '' ? null : max(0, (int)$days_raw);
    // مهلت از لحظه‌ی ثبت یا ویرایش جایزه (ساعت تهران) شروع می‌شود
    $time_started = $time_days !== null ? (new DateTime('now', new DateTimeZone('Asia/Tehran')))->format('Y-m-d H:i:s') : null;
    $prize_palette = ['#e8a33d', '#d9534f', '#5b7fd1', '#3aa88f', '#9b6bd0', '#e0729a', '#4fa3c7', '#f0b35a'];
    $chosen_color = strtolower(trim((string)($_POST['color'] ?? '')));
    if (!in_array($chosen_color, $prize_palette, true)) $chosen_color = '';
    // رنگ خودکار: چرخشی بر اساس نشانگر prize_color_next (نه تصادفی)
    $next_prize_color = function () use ($conn, $prize_palette) {
        $nx = (int)getSetting($conn, 'prize_color_next', '0');
        $c = $prize_palette[$nx % count($prize_palette)];
        $nv = (string)($nx + 1);
        $st = $conn->prepare("INSERT INTO settings (key_name, value_data) VALUES ('prize_color_next', ?) ON DUPLICATE KEY UPDATE value_data = VALUES(value_data)");
        $st->bind_param("s", $nv); $st->execute();
        return $c;
    };
    if (!empty($desc)) {
        if ($prize_id > 0) {
            if ($chosen_color === '') {
                $cur = $conn->query("SELECT color FROM prizes WHERE id = " . (int)$prize_id)->fetch_assoc();
                $chosen_color = in_array(strtolower((string)($cur['color'] ?? '')), $prize_palette, true) ? strtolower($cur['color']) : $next_prize_color();
            }
            $prize_active = isset($_POST['prize_active']) ? 1 : 0;
            // مهلت فقط وقتی ریست می‌شود که تعداد روزها تغییر کند؛ ویرایش نام/درصد/… شمارنده را ریست نمی‌کند
            $cur_t = $conn->query("SELECT time_limit_days, time_started_at FROM prizes WHERE id = " . (int)$prize_id)->fetch_assoc();
            if ($time_days !== null && $cur_t && (int)$cur_t['time_limit_days'] === $time_days && !empty($cur_t['time_started_at'])) {
                $time_started = $cur_t['time_started_at'];
            }
            $stmt = $conn->prepare("UPDATE prizes SET description=?, affiliate_link=?, discount_code=?, win_probability=?, is_local=?, is_instant=?, color=?, is_active=?, qty_limit=?, time_limit_days=?, time_started_at=? WHERE id=?");
            $stmt->bind_param("sssdiisiiisi", $desc, $link, $discount, $probability, $is_local, $is_instant, $chosen_color, $prize_active, $qty_limit, $time_days, $time_started, $prize_id);
        } else {
            if ($chosen_color === '') $chosen_color = $next_prize_color();
            $prize_active = isset($_POST['prize_active']) ? 1 : 0;
            $stmt = $conn->prepare("INSERT INTO prizes (description, affiliate_link, discount_code, win_probability, is_local, is_instant, color, is_active, qty_limit, time_limit_days, time_started_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param("sssdiisiiis", $desc, $link, $discount, $probability, $is_local, $is_instant, $chosen_color, $prize_active, $qty_limit, $time_days, $time_started);
        }
        $stmt->execute(); header('Location: '.$admin_self); exit;
    }
}
// ── پیام‌های نتیجه‌ی تاس و گردونه ──
if ($is_authenticated && isset($_POST['save_result_msgs'])) {
    // متن‌ها از بدنه‌ی خام خوانده می‌شوند تا ارقام فارسی (مثل ۶) توسط config.php به لاتین تبدیل نشوند
    parse_str((string)file_get_contents('php://input'), $raw_msgs);
    if (empty($raw_msgs)) $raw_msgs = $_POST; // همان fallback برای پیام‌های تاس و گردونه
    foreach (array_keys(sitak_result_msg_defaults()) as $mk) {
        $mv = trim((string)($raw_msgs[$mk] ?? ''));
        $ms = $conn->prepare("INSERT INTO settings (key_name, value_data) VALUES (?, ?) ON DUPLICATE KEY UPDATE value_data = VALUES(value_data)");
        $ms->bind_param("ss", $mk, $mv); $ms->execute();
    }
    header('Location: '.$admin_self); exit;
}
if ($is_authenticated && isset($_GET['delete_prize'])) {
    if (!sitak_csrf_valid($_GET['csrf'] ?? '')) { http_response_code(403); exit('درخواست نامعتبر است.'); }
    $id = intval($_GET['delete_prize']);
    $del_stmt = $conn->prepare("DELETE FROM prizes WHERE id = ? AND is_void = 0");
    $del_stmt->bind_param("i", $id); $del_stmt->execute();
    header('Location: '.$admin_self); exit;
}

// ── تنظیمات فایروال ──
if ($is_authenticated && isset($_POST['save_firewall_settings'])) {
    $vals = [
        'firewall_enabled' => isset($_POST['firewall_enabled']) ? '1' : '0',
        'firewall_honeypot_enabled' => isset($_POST['firewall_honeypot_enabled']) ? '1' : '0',
        'firewall_logging_enabled' => isset($_POST['firewall_logging_enabled']) ? '1' : '0',
        'firewall_rate_limit_enabled' => isset($_POST['firewall_rate_limit_enabled']) ? '1' : '0',
        'firewall_rate_limit_window' => (string)max(10, min(3600, (int)($_POST['firewall_rate_limit_window'] ?? 60))),
        'firewall_rate_limit_max' => (string)max(1, min(100, (int)($_POST['firewall_rate_limit_max'] ?? 5))),
        'firewall_block_threshold' => (string)max(1, min(100, (int)($_POST['firewall_block_threshold'] ?? 10))),
        'firewall_log_retention_days' => (string)max(1, min(365, (int)($_POST['firewall_log_retention_days'] ?? 30))),
    ];
    foreach ($vals as $key => $value) {
        $st = $conn->prepare("INSERT INTO settings (key_name,value_data) VALUES (?,?) ON DUPLICATE KEY UPDATE value_data=VALUES(value_data)");
        $st->bind_param('ss', $key, $value); $st->execute();
    }
    $days = (int)$vals['firewall_log_retention_days'];
    $conn->query("DELETE FROM firewall_events WHERE created_at < DATE_SUB(NOW(), INTERVAL {$days} DAY)");
    header('Location: '.$admin_self); exit;
}

// بازنشانی تنظیمات فایروال به مقادیر پیش‌فرض
if ($is_authenticated && isset($_POST['reset_firewall_settings'])) {
    $defaults = [
        'firewall_enabled' => '1',
        'firewall_honeypot_enabled' => '1',
        'firewall_logging_enabled' => '1',
        'firewall_rate_limit_enabled' => '1',
        'firewall_rate_limit_window' => '60',
        'firewall_rate_limit_max' => '5',
        'firewall_block_threshold' => '10',
        'firewall_log_retention_days' => '30',
    ];
    foreach ($defaults as $key => $value) {
        $st = $conn->prepare("INSERT INTO settings (key_name,value_data) VALUES (?,?) ON DUPLICATE KEY UPDATE value_data=VALUES(value_data)");
        $st->bind_param('ss', $key, $value); $st->execute();
    }
    header('Location: '.$admin_self); exit;
}

if ($is_authenticated && isset($_POST['admin_set_user_password'])) {
    header('Content-Type: application/json; charset=utf-8');
    $phone = trim(sitak_normalize_digits((string)($_POST['phone'] ?? '')));
    $pw = sitak_normalize_digits(trim((string)($_POST['password'] ?? '')));
    $pw2 = sitak_normalize_digits(trim((string)($_POST['password2'] ?? '')));
    $digits = sitak_pw_digits($conn);
    if (!preg_match('/^09[0-9]{9}$/', $phone)) { echo json_encode(['success'=>false,'message'=>'شماره کاربر نامعتبر است.']); exit; }
    if (!sitak_pw_valid($pw, $digits)) { echo json_encode(['success'=>false,'message'=>'گذرواژه باید ' . pn($digits) . ' رقمی باشد.']); exit; }
    if ($pw !== $pw2) { echo json_encode(['success'=>false,'message'=>'دو گذرواژه وارد شده یکسان نیستند.']); exit; }
    $hash = password_hash($pw, PASSWORD_DEFAULT);
    $ex = $conn->prepare("SELECT login_password FROM users WHERE phone = ?");
    $ex->bind_param('s', $phone); $ex->execute();
    $exRow = $ex->get_result()->fetch_assoc();
    if (!$exRow) { echo json_encode(['success'=>false,'message'=>'کاربر پیدا نشد.']); exit; }
    if (!empty($exRow['login_password']) && password_verify($pw, $exRow['login_password'])) { echo json_encode(['success'=>false,'message'=>'گذرواژه جدید نمی‌تواند با گذرواژه قبلی یکسان باشد.']); exit; }
    $up = $conn->prepare("UPDATE users SET login_password = ? WHERE phone = ?");
    $up->bind_param('ss', $hash, $phone); $up->execute();
    $done = $conn->prepare("UPDATE password_reset_requests SET status = 'done', done_at = NOW() WHERE user_phone = ? AND status = 'pending'");
    $done->bind_param('s', $phone); $done->execute();
    echo json_encode(['success'=>true,'message'=>'گذرواژه کاربر ثبت شد ✅','phone'=>$phone]); exit;
}
// ── ساخت کاربر جدید توسط مدیر (وقتی ثبت‌نام بسته است) ──
if ($is_authenticated && isset($_POST['admin_create_user'])) {
    header('Content-Type: application/json; charset=utf-8');
    $phone = trim(sitak_normalize_digits((string)($_POST['phone'] ?? '')));
    $pw = sitak_normalize_digits(trim((string)($_POST['password'] ?? '')));
    $pw2 = sitak_normalize_digits(trim((string)($_POST['password2'] ?? '')));
    $digits = sitak_pw_digits($conn);
    if (!preg_match('/^09[0-9]{9}$/', $phone)) { echo json_encode(['success'=>false,'message'=>'شماره موبایل نامعتبر است. فرمت صحیح: 09123456789']); exit; }
    if (!sitak_pw_valid($pw, $digits)) { echo json_encode(['success'=>false,'message'=>'گذرواژه باید ' . pn($digits) . ' رقمی باشد.']); exit; }
    if ($pw !== $pw2) { echo json_encode(['success'=>false,'message'=>'دو گذرواژه وارد شده یکسان نیستند.']); exit; }
    $ex = $conn->prepare("SELECT id FROM users WHERE phone = ?");
    $ex->bind_param('s', $phone); $ex->execute();
    if ($ex->get_result()->num_rows > 0) { echo json_encode(['success'=>false,'message'=>'این شماره قبلاً ثبت شده است.']); exit; }
    $hash = password_hash($pw, PASSWORD_DEFAULT);
    $ins = $conn->prepare("INSERT INTO users (phone, name, address, referral_code, referral_rolls, login_password) VALUES (?, '', '', NULL, 0, ?)");
    $ins->bind_param('ss', $phone, $hash);
    if (!$ins->execute()) { echo json_encode(['success'=>false,'message'=>'ساخت کاربر ناموفق بود.']); exit; }
    echo json_encode(['success'=>true,'message'=>'کاربر جدید ساخته شد ✅','phone'=>$phone]); exit;
}
if ($is_authenticated && isset($_POST['save_comment_settings'])) {
    foreach(['comments_products_enabled'=>isset($_POST['comments_products_enabled'])?'1':'0','comments_posts_enabled'=>isset($_POST['comments_posts_enabled'])?'1':'0','comments_require_approval'=>isset($_POST['comments_require_approval'])?'1':'0','comments_submit_products_enabled'=>isset($_POST['comments_submit_products_enabled'])?'1':'0','comments_submit_posts_enabled'=>isset($_POST['comments_submit_posts_enabled'])?'1':'0','comments_dislike_products_enabled'=>isset($_POST['comments_dislike_products_enabled'])?'1':'0','comments_dislike_posts_enabled'=>isset($_POST['comments_dislike_posts_enabled'])?'1':'0','comments_reactions_products_enabled'=>isset($_POST['comments_reactions_products_enabled'])?'1':'0','comments_reactions_posts_enabled'=>isset($_POST['comments_reactions_posts_enabled'])?'1':'0'] as $k=>$v){$st=$conn->prepare("INSERT INTO settings(key_name,value_data) VALUES(?,?) ON DUPLICATE KEY UPDATE value_data=VALUES(value_data)");$st->bind_param('ss',$k,$v);$st->execute();}
    header('Location: '.$admin_self); exit;
}
if ($is_authenticated && isset($_POST['comment_action'])) {
    $type=$_POST['comment_type']==='product'?'product':'post'; $table=$type==='product'?'product_comments':'post_comments'; $id=(int)$_POST['comment_id']; $act=$_POST['comment_action'];
    if($act==='edit'){ $body=trim($_POST['comment_body']??'');$rating=max(1,min(5,(int)($_POST['comment_rating']??5)));$st=$conn->prepare("UPDATE `$table` SET body=?,rating=? WHERE id=?");$st->bind_param('sii',$body,$rating,$id);$st->execute(); }
    elseif(in_array($act,['approved','rejected','pending'],true)){ $st=$conn->prepare("UPDATE `$table` SET status=? WHERE id=?");$st->bind_param('si',$act,$id);$st->execute(); }
    header('Location: '.admin_url('comments')); exit;
}

// ── ساعت ریست ──
if ($is_authenticated && isset($_POST['save_settings'])) {
    $reset_hour = intval($_POST['reset_hour'] ?? 21);
    $reset_hour_enabled = isset($_POST['reset_hour_enabled']) ? '1' : '0';
    $reset_hour_home_enabled = isset($_POST['reset_hour_home_enabled']) ? '1' : '0';
    $reset_hour_chance_enabled = isset($_POST['reset_hour_chance_enabled']) ? '1' : '0';
    $st1 = $conn->prepare("INSERT INTO settings (key_name, value_data) VALUES ('reset_hour', ?) ON DUPLICATE KEY UPDATE value_data = ?");
    $st1->bind_param("ss", $reset_hour, $reset_hour); $st1->execute();
    $st2 = $conn->prepare("INSERT INTO settings (key_name, value_data) VALUES ('reset_hour_enabled', ?) ON DUPLICATE KEY UPDATE value_data = ?");
    $st2->bind_param("ss", $reset_hour_enabled, $reset_hour_enabled); $st2->execute();
    foreach (['reset_hour_home_enabled'=>$reset_hour_home_enabled, 'reset_hour_chance_enabled'=>$reset_hour_chance_enabled] as $rk=>$rv) { $st=$conn->prepare("INSERT INTO settings (key_name,value_data) VALUES (?,?) ON DUPLICATE KEY UPDATE value_data=VALUES(value_data)"); $st->bind_param('ss',$rk,$rv); $st->execute(); }
    header('Location: '.$admin_self); exit;
}

// ── تنظیمات نقشه‌ی تحویل (map.ir) ──
if ($is_authenticated && isset($_POST['save_map_settings'])) {
    $mlat = sitak_normalize_digits(trim((string)($_POST['map_default_lat'] ?? '')));
    $mlng = sitak_normalize_digits(trim((string)($_POST['map_default_lng'] ?? '')));
    $mzoom = sitak_normalize_digits(trim((string)($_POST['map_default_zoom'] ?? '')));
    $mvals = [
        'map_enabled' => isset($_POST['map_enabled']) ? '1' : '0',
        'map_api_key' => trim((string)($_POST['map_api_key'] ?? '')),
        'map_browser_key' => trim((string)($_POST['map_browser_key'] ?? '')),
    ];
    // اگر نقشه‌ای جابه‌جا نشده باشد، محل و زوم قبلی حفظ می‌شود
    if ($mlat !== '' && abs((float)$mlat) <= 90 && $mlng !== '' && abs((float)$mlng) <= 180) {
        $mvals['map_default_lat'] = (string)round((float)$mlat, 6);
        $mvals['map_default_lng'] = (string)round((float)$mlng, 6);
    }
    if ($mzoom !== '') $mvals['map_default_zoom'] = (string)max(1, min(18, (int)$mzoom));
    $msave = $conn->prepare("INSERT INTO settings (key_name,value_data) VALUES (?,?) ON DUPLICATE KEY UPDATE value_data=VALUES(value_data)");
    foreach ($mvals as $mk => $mv) { $msave->bind_param('ss', $mk, $mv); $msave->execute(); }
    header('Location: '.$admin_self); exit;
}

// ── تنظیمات روش‌ها و درگاه پرداخت (زرین‌پال + کارت‌به‌کارت) ──
if ($is_authenticated && isset($_POST['save_zarinpal_settings'])) {
    $enabled = isset($_POST['zarinpal_enabled']) ? '1' : '0';
    $sandbox = isset($_POST['zarinpal_sandbox']) ? '1' : '0';
    $merchant = trim($_POST['zarinpal_merchant_id'] ?? '');
    $title = trim($_POST['zarinpal_title'] ?? 'پرداخت امن زرین‌پال');
    $merchant = trim($merchant, "{} \t\n\r\0\x0B");
    // ── فیلدهای کارت‌به‌کارت ──
    $card_enabled = isset($_POST['card_payment_enabled']) ? '1' : '0';
    $card_owner = mb_substr(trim(sitak_clean_name($_POST['card_owner_name'] ?? '')), 0, 120, 'UTF-8');
    $card_number = substr(preg_replace('/\D/', '', sitak_digits_latin($_POST['card_number'] ?? '')), 0, 19);
    $card_iban = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', sitak_digits_latin($_POST['card_iban'] ?? '')));
    if ($card_iban !== '' && !preg_match('/^[A-Z]{2}\d{5,32}$/', $card_iban)) $card_iban = '';
    $card_lock = (int)sitak_digits_latin(trim((string)($_POST['card_lock_minutes'] ?? '30')));
    if ($card_lock < 5) $card_lock = 30;
    if ($card_lock > 1440) $card_lock = 1440;
    $card_popup_text = trim((string)($_POST['card_popup_text'] ?? ''));
    if ($card_popup_text === '') $card_popup_text = 'مبلغ را به یکی از شماره‌های زیر واریز کن، سپس اطلاعات واریز را در صفحه‌ی سفارش‌ها ثبت کن؛ اگر تا پایان مهلت ثبت نکنی، سفارش بطور خودکار لغو می‌شود.';
    $card_popup_text = mb_substr($card_popup_text, 0, 600, 'UTF-8');
    $pending_method_enabled = isset($_POST['pending_method_enabled']) ? '1' : '0';
    if ($enabled === '1' && !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $merchant)) {
        $payment_error = 'مرچنت‌کد زرین‌پال باید UUID کامل ۳۶ کاراکتری باشد.';
    } elseif ($card_enabled === '1' && $card_number === '' && $card_iban === '') {
        $payment_error = 'برای فعال‌سازی کارت‌به‌کارت، حداقل شماره کارت یا شماره شبای خودت را وارد کن.';
    } else {
        foreach ([
            'zarinpal_enabled' => $enabled,
            'zarinpal_sandbox' => $sandbox,
            'zarinpal_merchant_id' => $merchant,
            'zarinpal_title' => mb_substr($title, 0, 100),
            'card_payment_enabled' => $card_enabled,
            'card_owner_name' => $card_owner,
            'card_number' => $card_number,
            'card_iban' => $card_iban,
            'card_lock_minutes' => (string)$card_lock,
            'card_popup_text' => $card_popup_text,
            'pending_method_enabled' => $pending_method_enabled,
        ] as $key => $value) {
            $st = $conn->prepare("INSERT INTO settings (key_name, value_data) VALUES (?, ?) ON DUPLICATE KEY UPDATE value_data = VALUES(value_data)");
            $st->bind_param("ss", $key, $value); $st->execute();
        }
        header('Location: '.$admin_self); exit;
    }
}

// ── اعلان‌های مدیر (سوئیچ کلی + تفکیک بخش‌های خانه) ──
if ($is_authenticated && isset($_POST['save_admin_notify'])) {
    $vals = [
        'admin_notify_enabled' => isset($_POST['admin_notify_enabled']) ? '1' : '0',
        'admin_notify_orders'  => isset($_POST['admin_notify_orders']) ? '1' : '0',
        'admin_notify_winners' => isset($_POST['admin_notify_winners']) ? '1' : '0',
        'admin_notify_users'   => isset($_POST['admin_notify_users']) ? '1' : '0',
        'admin_notify_resets'  => isset($_POST['admin_notify_resets']) ? '1' : '0',
        'admin_notify_payments' => isset($_POST['admin_notify_payments']) ? '1' : '0',
        'admin_notify_sound'   => isset($_POST['admin_notify_sound']) ? '1' : '0',
        'admin_notify_display' => in_array((string)($_POST['admin_notify_display'] ?? 'popup'), ['popup', 'toast', 'none'], true) ? (string)$_POST['admin_notify_display'] : 'popup',
    ];
    foreach ($vals as $key => $value) {
        $st = $conn->prepare("INSERT INTO settings (key_name, value_data) VALUES (?, ?) ON DUPLICATE KEY UPDATE value_data = VALUES(value_data)");
        $st->bind_param("ss", $key, $value); $st->execute();
    }
    header('Location: '.$admin_self); exit;
}

// ── اعلان آزمایشی (سنجش مسیر اعلان از بخش تنظیمات) ──
if ($is_authenticated && isset($_POST['test_notify'])) {
    $ok = sitak_notify_admin_test($conn);
    header('Location: ' . $admin_self . '?notify_test=' . ($ok ? '1' : '0')); exit;
}

// ── ثبت اشتراک Web Push مرورگر مدیر ──
if ($is_authenticated && isset($_POST['push_subscribe'])) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');
    $sub = json_decode((string)($_POST['subscription'] ?? ''), true);
    $endpoint = is_array($sub) ? trim((string)($sub['endpoint'] ?? '')) : '';
    $p256dh = is_array($sub) ? trim((string)($sub['keys']['p256dh'] ?? '')) : '';
    $auth = is_array($sub) ? trim((string)($sub['keys']['auth'] ?? '')) : '';
    if (!preg_match('#^https://#', $endpoint) || $p256dh === '' || $auth === '') {
        echo json_encode(['success' => false, 'message' => 'اطلاعات اشتراک نامعتبر است.'], JSON_UNESCAPED_UNICODE); exit;
    }
    $hash = md5($endpoint);
    $st = $conn->prepare("INSERT INTO push_subscriptions (endpoint_hash, endpoint, p256dh, auth) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE endpoint = VALUES(endpoint), p256dh = VALUES(p256dh), auth = VALUES(auth)");
    $st->bind_param('ssss', $hash, $endpoint, $p256dh, $auth);
    $ok = $st->execute();
    echo json_encode(['success' => (bool)$ok], JSON_UNESCAPED_UNICODE); exit;
}

// ── لغو اشتراک Web Push (مثلاً از همان مرورگر) ──
if ($is_authenticated && isset($_POST['push_unsubscribe'])) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');
    $sub = json_decode((string)($_POST['subscription'] ?? ''), true);
    $endpoint = is_array($sub) ? trim((string)($sub['endpoint'] ?? '')) : '';
    if ($endpoint !== '') {
        $hash = md5($endpoint);
        $st = $conn->prepare("DELETE FROM push_subscriptions WHERE endpoint_hash = ?");
        $st->bind_param('s', $hash); $st->execute();
    }
    echo json_encode(['success' => true]); exit;
}

// ── رویدادهای جدید مدیر برای polls صفحه‌های باز پنل ──
if ($is_authenticated && isset($_GET['action']) && $_GET['action'] === 'admin_events_since') {
    while (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');
    if (getSetting($conn, 'admin_notify_enabled', '1') !== '1') {
        echo json_encode(['success' => true, 'last_id' => (int)($_GET['after'] ?? 0), 'events' => []]); exit;
    }
    if (isset($_GET['mark'])) {
        // فقط آخرین شناسه را برمی‌گرداند (خط پایه برای بازدید اول، بدون پخش اعلان)
        $mx = $conn->query("SELECT COALESCE(MAX(id), 0) AS m FROM admin_events");
        echo json_encode(['success' => true, 'last_id' => (int)($mx ? $mx->fetch_assoc()['m'] : 0), 'events' => []]); exit;
    }
    $after = max(0, (int)($_GET['after'] ?? 0));
    $res = $conn->query("SELECT id, type, title, body, url FROM admin_events WHERE id > $after ORDER BY id ASC LIMIT 25");
    $events = []; $last = $after;
    if ($res) { while ($r = $res->fetch_assoc()) { $events[] = $r; $last = (int)$r['id']; } }
    echo json_encode(['success' => true, 'last_id' => $last, 'events' => $events], JSON_UNESCAPED_UNICODE); exit;
}

if ($is_authenticated && isset($_POST['save_sms_settings'])) {
    $vals = [
        'sms_enabled' => isset($_POST['sms_enabled']) ? '1' : '0',
        'sms_sandbox' => isset($_POST['sms_sandbox']) ? '1' : '0',
        'sms_otp_digits' => (string)max(4, min(6, (int)($_POST['sms_otp_digits'] ?? 6))),
        'sms_api_key' => trim($_POST['sms_api_key'] ?? ''),
        'sms_line_number' => trim($_POST['sms_line_number'] ?? ''),
        'sms_otp_template_id' => trim($_POST['sms_otp_template_id'] ?? ''),
        'sms_order_template_id' => trim($_POST['sms_order_template_id'] ?? ''),
        'sms_status_confirmed_template_id' => trim($_POST['sms_status_confirmed_template_id'] ?? ''),
        'sms_status_delivered_template_id' => trim($_POST['sms_status_delivered_template_id'] ?? ''),
        'sms_status_cancelled_template_id' => trim($_POST['sms_status_cancelled_template_id'] ?? ''),
        'sms_notify_login' => isset($_POST['sms_notify_login']) ? '1' : '0',
        'sms_notify_order' => isset($_POST['sms_notify_order']) ? '1' : '0',
        'sms_notify_status' => isset($_POST['sms_notify_status']) ? '1' : '0',
        'sms_notify_admin_order' => isset($_POST['sms_notify_admin_order']) ? '1' : '0',
        'sms_admin_phone' => trim(sitak_normalize_digits((string)($_POST['sms_admin_phone'] ?? ''))),
        'sms_admin_order_template_id' => trim((string)($_POST['sms_admin_order_template_id'] ?? '')),
        'sms_admin_order_params' => implode(',', array_values(array_intersect((array)($_POST['sms_admin_params'] ?? []), ['OrderId','Name','Phone','City','Total']))),
        'sms_password_login_enabled' => isset($_POST['sms_password_login_enabled']) ? '1' : '0',
        'sms_password_digits' => (string)max(4, min(8, (int)($_POST['sms_password_digits'] ?? 6))),
        'register_closed' => isset($_POST['register_closed']) ? '1' : '0',
        'register_closed_message' => trim((string)($_POST['register_closed_message'] ?? '')),
        'forgot_password_message' => trim((string)($_POST['forgot_password_message'] ?? '')),
        'password_reset_success_message' => trim((string)($_POST['password_reset_success_message'] ?? '')),
        'sms_resend_limit_message' => trim($_POST['sms_resend_limit_message'] ?? 'اکنون امکان درخواست مجدد کد وجود ندارد، لطفاً مدتی دیگر تلاش کنید.'),
    ];
    foreach ($vals as $key => $value) {
        $st = $conn->prepare("INSERT INTO settings (key_name, value_data) VALUES (?, ?) ON DUPLICATE KEY UPDATE value_data=VALUES(value_data)");
        $st->bind_param("ss", $key, $value); $st->execute();
    }
    header('Location: '.$admin_self); exit;
}

if ($is_authenticated && isset($_POST['save_referral_settings'])) {
    $vals = [
        'referral_enabled' => isset($_POST['referral_enabled']) ? '1' : '0',
        'referral_hint' => mb_substr(trim($_POST['referral_hint'] ?? ''), 0, 255),
        'referral_inviter_rolls' => (string)max(0, min(99, (int)($_POST['referral_inviter_rolls'] ?? 1))),
        'referral_invitee_rolls' => (string)max(0, min(99, (int)($_POST['referral_invitee_rolls'] ?? 1))),
    ];
    foreach ($vals as $key => $value) {
        $st = $conn->prepare("INSERT INTO settings (key_name, value_data) VALUES (?, ?) ON DUPLICATE KEY UPDATE value_data=VALUES(value_data)");
        $st->bind_param("ss", $key, $value); $st->execute();
    }
    header('Location: '.$admin_self); exit;
}

// ── تنظیمات بارگذاری فایل ──
if ($is_authenticated && isset($_POST['save_upload_settings'])) {
    $enabled = isset($_POST['upload_limit_enabled']) ? '1' : '0';
    $max_val = floatval($_POST['upload_max_value'] ?? 100);
    $unit = $_POST['upload_max_unit'] ?? 'MB';
    if ($unit === 'GB') {
        $max_val = $max_val * 1024; // تبدیل به مگابایت
    }
    $max_mb = max(1, round($max_val, 2));
    
    foreach (['upload_limit_enabled' => $enabled, 'upload_max_mb' => $max_mb] as $k => $v) {
        $s = $conn->prepare("INSERT INTO settings (key_name, value_data) VALUES (?, ?) ON DUPLICATE KEY UPDATE value_data = ?");
        $s->bind_param("sss", $k, $v, $v); $s->execute();
    }
    header('Location: '.$admin_self); exit;
}

// ── ارتباط با ما + کپی‌رایت فوتر (یکپارچه در بخش ۴) ──
if ($is_authenticated && isset($_POST['save_contact_settings'])) {
    $vals = [
        'contact_enabled'   => isset($_POST['contact_enabled']) ? '1' : '0',
        'contact_instagram' => trim($_POST['contact_instagram'] ?? ''),
        'contact_telegram'  => trim($_POST['contact_telegram'] ?? ''),
        'contact_youtube'   => trim($_POST['contact_youtube'] ?? ''),
        'contact_phone'     => trim($_POST['contact_phone'] ?? ''),
        'contact_address'   => trim($_POST['contact_address'] ?? ''),
    ];
    foreach ($vals as $k => $v) {
        $s = $conn->prepare("UPDATE settings SET value_data = ? WHERE key_name = ?");
        $s->bind_param("ss", $v, $k); $s->execute();
    }
    // کپی‌رایت فوتر از بخش مستقل به اینجا منتقل شده است
    if (array_key_exists('footer_copyright', $_POST)) {
        foreach ([
            'footer_copyright' => trim((string)($_POST['footer_copyright'] ?? '')),
            'footer_copyright_enabled' => isset($_POST['footer_copyright_enabled']) ? '1' : '0',
        ] as $k => $v) {
            $s = $conn->prepare("INSERT INTO settings (key_name, value_data) VALUES (?, ?) ON DUPLICATE KEY UPDATE value_data = VALUES(value_data)");
            $s->bind_param("ss", $k, $v); $s->execute();
        }
    }
    header('Location: '.$admin_self); exit;
}

// ── متن بنر زیر هدر (Hero) ──
if ($is_authenticated && isset($_POST['save_hero_settings'])) {
    $vals = [
        'hero_enabled'     => isset($_POST['hero_enabled']) ? '1' : '0',
        'hero_title'       => trim($_POST['hero_title'] ?? '') !== '' ? trim($_POST['hero_title']) : 'جفت ۶ بیار، مهمون ما باش!',
        'hero_description' => trim($_POST['hero_description'] ?? '') !== '' ? trim($_POST['hero_description']) : 'تاس بنداز و شانس دریافت هدایای شگفت‌انگیز رو امتحان کن.',
        'site_name'        => sitak_clean_name($_POST['site_name'] ?? 'سای‌تک'),
        'site_slogan'      => trim($_POST['site_slogan'] ?? 'یک وبسایت تک'),
    ];
    foreach ($vals as $k => $v) {
        $s = $conn->prepare("INSERT INTO settings (key_name, value_data) VALUES (?, ?) ON DUPLICATE KEY UPDATE value_data = ?");
        $s->bind_param("sss", $k, $v, $v); $s->execute();
    }
    header('Location: '.$admin_self); exit;
}

// ── وفاداری ──
if ($is_authenticated && isset($_POST['save_loyalty_settings'])) {
    $loyalty_enabled = isset($_POST['loyalty_enabled']) ? '1' : '0';
    $loyalty_dice_count = intval($_POST['loyalty_dice_count'] ?? 2);
    $stmt1 = $conn->prepare("UPDATE settings SET value_data = ? WHERE key_name = 'loyalty_enabled'");
    $stmt1->bind_param("s", $loyalty_enabled); $stmt1->execute();
    $stmt2 = $conn->prepare("UPDATE settings SET value_data = ? WHERE key_name = 'loyalty_dice_count'");
    $stmt2->bind_param("s", $loyalty_dice_count); $stmt2->execute();
    $loyalty_online_order = isset($_POST['loyalty_online_order']) ? '1' : '0';
    $stmt3 = $conn->prepare("UPDATE settings SET value_data = ? WHERE key_name = 'loyalty_online_order'");
    $stmt3->bind_param("s", $loyalty_online_order); $stmt3->execute();
    // متن‌ها از بدنه‌ی خام خوانده می‌شوند تا ارقام فارسی (مثل ۱) حفظ شوند
    parse_str((string)file_get_contents('php://input'), $raw_loy);
    if (empty($raw_loy)) $raw_loy = $_POST; // اگر بدنه‌ی خام در دسترس نبود، از $_POST استفاده می‌شود تا متن‌ها خالی ذخیره نشوند
    foreach (['loyalty_page_title'=>trim($_POST['loyalty_page_title'] ?? ''), 'loyalty_page_description'=>trim($raw_loy['loyalty_page_description'] ?? ''), 'loyalty_card_hint'=>trim($raw_loy['loyalty_card_hint'] ?? ''), 'loyalty_complete_msg'=>trim($raw_loy['loyalty_complete_msg'] ?? '')] as $lk=>$lv) { $st=$conn->prepare("INSERT INTO settings (key_name,value_data) VALUES (?,?) ON DUPLICATE KEY UPDATE value_data=VALUES(value_data)"); $st->bind_param('ss',$lk,$lv); $st->execute(); }
    header('Location: '.$admin_self); exit;
}

// ── اطلاعیه سایت ──
if ($is_authenticated && isset($_POST['save_announcement_settings'])) {
    $announcement_enabled = isset($_POST['announcement_enabled']) ? '1' : '0';
    $announcement_message = trim($_POST['announcement_message'] ?? '');
    $stmt1 = $conn->prepare("UPDATE settings SET value_data = ? WHERE key_name = 'announcement_enabled'");
    $stmt1->bind_param("s", $announcement_enabled); $stmt1->execute();
    $stmt2 = $conn->prepare("UPDATE settings SET value_data = ? WHERE key_name = 'announcement_message'");
    $stmt2->bind_param("s", $announcement_message); $stmt2->execute();
    header('Location: '.$admin_self); exit;
}

// ── تم پیش‌فرض ──
if ($is_authenticated && isset($_POST['save_theme_settings'])) {
    $default_theme = in_array($_POST['default_theme'] ?? '', ['light', 'dark', 'auto']) ? $_POST['default_theme'] : 'auto';
    $stmt = $conn->prepare("UPDATE settings SET value_data = ? WHERE key_name = 'default_theme'");
    $stmt->bind_param("s", $default_theme); $stmt->execute();
    header('Location: '.$admin_self); exit;
}

// ── تنظیمات نمایش مطالب ──
if ($is_authenticated && isset($_POST['save_blog_settings'])) {
    $blog_enabled       = isset($_POST['blog_enabled']) ? '1' : '0';
    $blog_views_enabled = isset($_POST['blog_views_enabled']) ? '1' : '0';
    $home_blog_enabled  = isset($_POST['home_blog_enabled']) ? '1' : '0';
    $home_blog_count_post = (string)max(1, min(50, (int)preg_replace('/[^0-9]/', '', (string)($_POST['home_blog_count'] ?? '4'))));
    $show_post_ratings_post = isset($_POST['show_post_ratings']) ? '1' : '0';
    $home_blog_type     = in_array($_POST['home_blog_type'] ?? '', ['latest', 'most_viewed']) ? $_POST['home_blog_type'] : 'latest';
    foreach (['blog_enabled'=>$blog_enabled, 'blog_views_enabled'=>$blog_views_enabled, 'home_blog_enabled'=>$home_blog_enabled, 'home_blog_count'=>$home_blog_count_post, 'show_post_ratings'=>$show_post_ratings_post, 'home_blog_type'=>$home_blog_type, 'blog_page_title'=>trim($_POST['blog_page_title'] ?? ''), 'blog_page_description'=>trim($_POST['blog_page_description'] ?? '')] as $bk=>$bv) {
        $s = $conn->prepare("UPDATE settings SET value_data = ? WHERE key_name = ?");
        $s->bind_param("ss", $bv, $bk); $s->execute();
    }
    header('Location: '.$admin_self); exit;
}

// ── محدوده، روزها و ساعات کاری فروشگاه: مستقل از تنظیمات عنوان/نمایش ──
if ($is_authenticated && isset($_POST['save_store_schedule'])) {
    $values = [
        'store_shipping_province'=>trim($_POST['store_shipping_province'] ?? ''),
        'store_shipping_city'=>trim($_POST['store_shipping_city'] ?? ''),
        'store_active_days'=>implode(',', array_values(array_unique(array_map('intval', $_POST['store_active_days'] ?? [])))),
        'store_morning_open'=>trim($_POST['store_morning_open'] ?? ''),
        'store_morning_close'=>trim($_POST['store_morning_close'] ?? ''),
        'store_afternoon_open'=>trim($_POST['store_afternoon_open'] ?? ''),
        'store_afternoon_close'=>trim($_POST['store_afternoon_close'] ?? ''),
        'store_closed_day_message'=>trim($_POST['store_closed_day_message'] ?? ''),
        'store_closed_time_message'=>trim($_POST['store_closed_time_message'] ?? '')
    ];
    if ($values['store_shipping_province'] === '') $values['store_shipping_city'] = '';
    if ($values['store_closed_day_message'] === '') $values['store_closed_day_message'] = 'امروز فروشگاه تعطیل است و امکان ثبت سفارش وجود ندارد.';
    if ($values['store_closed_time_message'] === '') $values['store_closed_time_message'] = 'در حال حاضر خارج از ساعت کاری فروشگاه هستیم و امکان ثبت سفارش وجود ندارد.';
    $save = $conn->prepare("INSERT INTO settings (key_name,value_data) VALUES (?,?) ON DUPLICATE KEY UPDATE value_data=VALUES(value_data)");
    foreach ($values as $key=>$value) { $save->bind_param('ss', $key, $value); if (!$save->execute()) { error_log('[SiTak settings] '.$save->error); http_response_code(500); exit('ذخیره تنظیمات انجام نشد. دوباره تلاش کنید.'); } }
    header('Location: '.$admin_self); exit;
}

// ── کدهای تخفیف: فعال/غیرفعال کردن کل قابلیت ──
if ($is_authenticated && isset($_POST['save_discount_enabled'])) {
    $dval = isset($_POST['discount_enabled']) ? '1' : '0';
    $dst = $conn->prepare("INSERT INTO settings (key_name,value_data) VALUES ('discount_enabled',?) ON DUPLICATE KEY UPDATE value_data=VALUES(value_data)");
    $dst->bind_param('s', $dval); $dst->execute();
    header('Location: '.$admin_self.'?sec=discounts'); exit;
}
// ── کدهای تخفیف: ثبت یا ویرایش یک کد ──
if ($is_authenticated && isset($_POST['save_discount_code'])) {
    $did = (int)($_POST['dc_id'] ?? 0);
    $dnum = function ($k) { return max(0, (int)preg_replace('/[^0-9]/', '', sitak_normalize_digits((string)($_POST[$k] ?? '0')))); };
    $dcode = strtoupper(preg_replace('/\s+/', '', trim(sitak_normalize_digits((string)($_POST['dc_code'] ?? '')))));
    $dtype = (($_POST['dc_type'] ?? 'amount') === 'percent') ? 'percent' : 'amount';
    $dvalue = $dnum('dc_value'); $dmax = $dnum('dc_max'); $dmin = $dnum('dc_min'); $dlimit = $dnum('dc_limit');
    $dpids = [];
    foreach ((array)($_POST['dc_product'] ?? []) as $dpx) { $dpx = (int)$dpx; if ($dpx > 0 && !in_array($dpx, $dpids, true)) $dpids[] = $dpx; }
    // شماره‌ها را می‌شود با ویرگول (، یا ,) یا فاصله یا خط جدید از هم جدا کرد
    $dphones = [];
    foreach (preg_split('/[\s,،;؛]+/u', sitak_normalize_digits((string)($_POST['dc_phone'] ?? '')), -1, PREG_SPLIT_NO_EMPTY) as $dpn) {
        $dpn = trim($dpn);
        if (!in_array($dpn, $dphones, true)) $dphones[] = $dpn;
    }
    $dphone = implode(',', $dphones);
    $dactive = isset($_POST['dc_active']) ? 1 : 0;
    $dlink = isset($_POST['dc_link']) ? 1 : 0;
    // مدت اعتبار به روز: از لحظه‌ی ثبت/ویرایش حساب می‌شود؛ ۰ یعنی بدون انقضا
    $ddays = $dnum('dc_days');
    $ddaysv = $ddays > 0 ? $ddays : null;
    // تاریخ انقضا فقط وقتی از نو حساب می‌شود که مدت اعتبار تغییر کند؛ ویرایش کد یا مبلغ، شمارنده را ریست نمی‌کند
    $dexp = null;
    if ($ddays > 0) {
        $dcur = $did > 0 ? $conn->query("SELECT valid_days, expires_at FROM discount_codes WHERE id = " . (int)$did)->fetch_assoc() : null;
        if ($dcur && (int)$dcur['valid_days'] === $ddays && !empty($dcur['expires_at'])) $dexp = $dcur['expires_at'];
        else $dexp = date('Y-m-d H:i:s', time() + $ddays * 86400);
    }
    $derr = '';
    if (!preg_match('/^[A-Z0-9_\-]{3,40}$/', $dcode)) $derr = 'کد تخفیف باید ۳ تا ۴۰ کاراکتر (حروف انگلیسی، عدد، - یا _) باشد.';
    elseif ($dvalue <= 0) $derr = 'مقدار تخفیف باید بیشتر از صفر باشد.';
    elseif ($dtype === 'percent' && $dvalue > 100) $derr = 'درصد تخفیف نمی‌تواند بیشتر از ۱۰۰ باشد.';
    elseif (count(array_filter($dphones, function ($x) { return !preg_match('/^09[0-9]{9}$/', $x); })) > 0) $derr = 'یکی از شماره‌ها معتبر نیست. هر شماره باید ۱۱ رقمی و با ۰۹ شروع شود.';
    if ($derr === '') {
        $dup = $conn->prepare("SELECT id FROM discount_codes WHERE code = ? AND id <> ? LIMIT 1");
        $dup->bind_param('si', $dcode, $did); $dup->execute();
        if ($dup->get_result()->num_rows > 0) $derr = 'این کد تخفیف قبلاً ثبت شده است.';
    }
    if ($derr !== '') { header('Location: '.$admin_self.'?sec=discounts&dc_err='.urlencode($derr)); exit; }
    $dmaxv = ($dtype === 'percent' && $dmax > 0) ? $dmax : null;
    $dlimitv = $dlimit > 0 ? $dlimit : null;
    $dphonev = $dphone !== '' ? $dphone : null;
    $dpidv = $dpids ? implode(',', $dpids) : null;
    if ($did > 0) {
        $dq = $conn->prepare("UPDATE discount_codes SET code=?, type=?, value=?, max_discount=?, min_subtotal=?, usage_limit=?, target_phone=?, product_id=?, is_active=?, link_enabled=?, valid_days=?, expires_at=? WHERE id=?");
        $dq->bind_param('ssiiiissiiisi', $dcode, $dtype, $dvalue, $dmaxv, $dmin, $dlimitv, $dphonev, $dpidv, $dactive, $dlink, $ddaysv, $dexp, $did);
    } else {
        $dq = $conn->prepare("INSERT INTO discount_codes (code, type, value, max_discount, min_subtotal, usage_limit, target_phone, product_id, is_active, link_enabled, valid_days, expires_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
        $dq->bind_param('ssiiiissiiis', $dcode, $dtype, $dvalue, $dmaxv, $dmin, $dlimitv, $dphonev, $dpidv, $dactive, $dlink, $ddaysv, $dexp);
    }
    $dq->execute();
    header('Location: '.$admin_self.'?sec=discounts'); exit;
}
// ── کدهای تخفیف: حذف یک کد ──
if ($is_authenticated && isset($_POST['delete_discount_code'])) {
    $xid = (int)($_POST['dc_id'] ?? 0);
    $xq = $conn->prepare("DELETE FROM discount_codes WHERE id = ?");
    $xq->bind_param('i', $xid); $xq->execute();
    header('Location: '.$admin_self.'?sec=discounts'); exit;
}
// ── کدهای تخفیف: فعال/غیرفعال کردن یک کد ──
if ($is_authenticated && isset($_POST['toggle_discount_code'])) {
    $tid = (int)($_POST['dc_id'] ?? 0);
    $tq = $conn->prepare("UPDATE discount_codes SET is_active = 1 - is_active WHERE id = ?");
    $tq->bind_param('i', $tid); $tq->execute();
    header('Location: '.$admin_self.'?sec=discounts'); exit;
}

// ── تنظیمات نمایش فروشگاه ──
// ── ترتیب نمایش کارت‌های خانه و بخش شانس ──
if ($is_authenticated && isset($_POST['save_card_order'])) {
    $scope = ($_POST['card_scope'] ?? '') === 'home' ? 'home' : 'chance';
    $card_vals = [];
    foreach (array_keys(sitak_card_keys($scope)) as $ck) {
        $card_vals[$ck] = max(1, min(99, (int)preg_replace('/[^0-9]/', '', (string)($_POST['order_' . $ck] ?? '1'))));
    }
    if (count(array_unique($card_vals)) !== count($card_vals)) { header('Location: '.$admin_self); exit; }
    foreach ($card_vals as $ck => $cv) {
        $cs = $conn->prepare("INSERT INTO settings (key_name,value_data) VALUES (?,?) ON DUPLICATE KEY UPDATE value_data=VALUES(value_data)");
        $ckey = 'card_order_' . $scope . '_' . $ck; $cstr = (string)$cv;
        $cs->bind_param('ss', $ckey, $cstr); $cs->execute();
    }
    header('Location: '.$admin_self); exit;
}

if ($is_authenticated && isset($_POST['save_shipping_settings'])) {
    $ship_num = function ($k) { return (string)max(0, (int)preg_replace('/[^0-9]/', '', (string)($_POST[$k] ?? '0'))); };
    $values = [
        'shipping_general_fee'      => $ship_num('shipping_general_fee'),
        'shipping_general_free_min' => $ship_num('shipping_general_free_min'),
        'shipping_scope_fee'        => $ship_num('shipping_scope_fee'),
        'shipping_scope_min'        => $ship_num('shipping_scope_min'),
        'shipping_scope_free_min'   => $ship_num('shipping_scope_free_min'),
        'shipping_general_encourage_pct' => (string)min(100, (int)$ship_num('shipping_general_encourage_pct')),
        'shipping_scope_encourage_pct'   => (string)min(100, (int)$ship_num('shipping_scope_encourage_pct')),
    ];
    $save = $conn->prepare("INSERT INTO settings (key_name,value_data) VALUES (?,?) ON DUPLICATE KEY UPDATE value_data=VALUES(value_data)");
    foreach ($values as $key=>$value) { $save->bind_param('ss', $key, $value); if (!$save->execute()) { error_log('[SiTak settings] '.$save->error); http_response_code(500); exit('ذخیره تنظیمات انجام نشد. دوباره تلاش کنید.'); } }
    header('Location: '.$admin_self); exit;
}

if ($is_authenticated && isset($_POST['save_shop_settings'])) {
    $values = [
        'shop_enabled'=>isset($_POST['shop_enabled']) ? '1' : '0',
        'orders_tab_enabled'=>isset($_POST['orders_tab_enabled']) ? '1' : '0',
        'home_shop_enabled'=>isset($_POST['home_shop_enabled']) ? '1' : '0',
        'home_shop_count'=>(string)max(1, min(50, (int)preg_replace('/[^0-9]/', '', (string)($_POST['home_shop_count'] ?? '4')))),
        'home_shop_type'=>in_array($_POST['home_shop_type'] ?? '', ['newest','best'], true) ? $_POST['home_shop_type'] : 'best',
        'shop_page_title'=>trim($_POST['shop_page_title'] ?? ''),
        'shop_page_description'=>trim($_POST['shop_page_description'] ?? ''),
        'orders_page_title'=>trim($_POST['orders_page_title'] ?? ''),
        'orders_page_description'=>trim($_POST['orders_page_description'] ?? ''),
        'show_product_ratings'=>isset($_POST['show_product_ratings']) ? '1' : '0',
        'stock_badge_enabled'=>isset($_POST['stock_badge_enabled']) ? '1' : '0',
        'stock_badge_threshold'=>(string)max(0, min(9999, (int)preg_replace('/[^0-9]/', '', sitak_normalize_digits((string)($_POST['stock_badge_threshold'] ?? '3'))))),
    ];
    $save = $conn->prepare("INSERT INTO settings (key_name,value_data) VALUES (?,?) ON DUPLICATE KEY UPDATE value_data=VALUES(value_data)");
    foreach ($values as $key=>$value) { $save->bind_param('ss', $key, $value); if (!$save->execute()) { error_log('[SiTak settings] '.$save->error); http_response_code(500); exit('ذخیره تنظیمات انجام نشد. دوباره تلاش کنید.'); } }
    header('Location: '.$admin_self); exit;
}

// ── تنظیمات نمایش شانس (تاس/فال/تب شانس) ──
if ($is_authenticated && isset($_POST['save_chance_settings'])) {
    $wheel_enabled_post = isset($_POST['wheel_enabled']) ? '1' : '0';
    $wheel_home_post = isset($_POST['wheel_home_enabled']) ? '1' : '0';
    $wst = $conn->prepare("UPDATE settings SET value_data = ? WHERE key_name = 'wheel_enabled'");
    $wst->bind_param("s", $wheel_enabled_post); $wst->execute();
    $wst2 = $conn->prepare("UPDATE settings SET value_data = ? WHERE key_name = 'wheel_home_enabled'");
    $wst2->bind_param("s", $wheel_home_post); $wst2->execute();
    $chance_enabled      = isset($_POST['chance_enabled']) ? '1' : '0';
    $dice_enabled        = isset($_POST['dice_enabled']) ? '1' : '0';
    $dice_home_enabled   = isset($_POST['dice_home_enabled']) ? '1' : '0';
    $hafez_enabled       = isset($_POST['hafez_enabled']) ? '1' : '0';
    $hafez_home_enabled  = isset($_POST['hafez_home_enabled']) ? '1' : '0';
    $daily_roll_count_post = (string)max(0, min(50, (int)preg_replace('/[^0-9]/', '', (string)($_POST['daily_roll_count'] ?? '1'))));
    foreach (['daily_roll_count'=>$daily_roll_count_post, 'chance_enabled'=>$chance_enabled, 'dice_enabled'=>$dice_enabled, 'dice_home_enabled'=>$dice_home_enabled, 'hafez_enabled'=>$hafez_enabled, 'hafez_home_enabled'=>$hafez_home_enabled, 'chance_title'=>trim($_POST['chance_title'] ?? ''), 'chance_description'=>trim($_POST['chance_description'] ?? '')] as $ck=>$cv) {
        $s = $conn->prepare("UPDATE settings SET value_data = ? WHERE key_name = ?");
        $s->bind_param("ss", $cv, $ck); $s->execute();
    }
    $intention_post = trim((string)($_POST['intention_msg'] ?? ''));
    $is_st = $conn->prepare("INSERT INTO settings (key_name, value_data) VALUES ('intention_msg', ?) ON DUPLICATE KEY UPDATE value_data = VALUES(value_data)");
    $is_st->bind_param("s", $intention_post); $is_st->execute();
    header('Location: '.$admin_self); exit;
}

// ── نشانی و گذرواژه پنل ادمین ──
if ($is_authenticated && isset($_POST['save_admin_security'])) {
    $admin_login_slug_raw = trim($_POST['admin_login_slug'] ?? '');
    // فقط حروف کوچک، اعداد، خط تیره و آندرلاین؛ ۳ تا ۴۰ کاراکتر
    if ($admin_login_slug_raw !== '' && !preg_match('/^[a-z0-9_-]{3,40}$/', $admin_login_slug_raw)) {
        $pass_error = 'نشانی لاگین باید ۳ تا ۴۰ کاراکتر و فقط شامل a-z، ۰-۹، - و _ باشد.';
    } else {
        $slug = strtolower($admin_login_slug_raw);
        $old_slug = trim(getSetting($conn, 'admin_login_slug', ''));
        // صفحه‌ی اضطراری هم نام نشانی را دنبال می‌کند: فایل قبلی حذف می‌شود
        $__em_old = $old_slug !== '' ? $old_slug : 'admin';
        $__em_new = $slug !== '' ? $slug : 'admin';
        if ($__em_old !== $__em_new) { @unlink(__DIR__ . '/' . $__em_old . '-emergency.php'); }
        // حذف فایل نشانی قبلی
        if ($old_slug !== '' && $old_slug !== $slug) {
            $old_file = __DIR__ . '/' . $old_slug . '.php';
            if (file_exists($old_file)) { @unlink($old_file); }
        }
        // ساخت فایل نشانی جدید (یا حذف اگر خالی شده)
        if ($slug !== '' && $slug !== $old_slug) {
            $old_suffixes_rm = ['', '-settings', '-chance', '-blog', '-shop', '-stats', '-upload', '-comments'];
            if ($old_slug !== '') {
                foreach ($old_suffixes_rm as $sfx) {
                    $old_f_rm = __DIR__ . '/' . $old_slug . $sfx . '.php';
                    if (file_exists($old_f_rm)) { @unlink($old_f_rm); }
                }
            }
            $suffixes = ['', '-settings', '-chance', '-blog', '-shop', '-stats', '-upload', '-comments'];
            $bases   = ['admin.php', 'admin-settings.php', 'admin-chance.php', 'admin-blog.php', 'admin-shop.php', 'admin-stats.php', 'admin-upload.php', 'admin-comments.php'];
            foreach ($suffixes as $i => $sfx) {
                $src  = $bases[$i];
                $dest = ($slug . $sfx . '.php');
                $content = "<?php\n\$admin_self = " . var_export($dest, true) . ";\nrequire " . var_export($src, true) . ";\n";
                if (@file_put_contents(__DIR__ . '/' . $dest, $content) === false) {
                    $pass_error = 'خطا در ایجاد فایل نشانی. مجوز نوشتن در پوشه پروژه را بررسی کنید.';
                    break;
                }
            }
            if (empty($pass_error)) $pass_success = 'نشانی با موفقیت ذخیره شد. از این پس پنل با نشانی جدید باز می‌شود: ' . $slug . '.php';
        } elseif ($slug === '' && $old_slug !== '') {
            $old_suffixes = ['', '-settings', '-chance', '-blog', '-shop', '-stats', '-upload', '-comments'];
            foreach ($old_suffixes as $sfx) {
                $old_f2 = __DIR__ . '/' . $old_slug . $sfx . '.php';
                if (file_exists($old_f2)) { @unlink($old_f2); }
            }
            // فایل‌های نشانی قدیمی حذف شدند — این موفقیت است.
            $pass_success = 'نشانی با موفقیت ذخیره شد.';
        }
        if (empty($pass_error)) {
            $st = $conn->prepare("INSERT INTO settings (key_name, value_data) VALUES ('admin_login_slug', ?) ON DUPLICATE KEY UPDATE value_data=?");
            $st->bind_param("ss", $slug, $slug);
            $st->execute();
            $admin_login_slug = $slug;
            $admin_prefix = $slug !== '' ? $slug : 'admin';
            sitak_emergency_sync($conn, $admin_prefix);
            // گذرواژه (اختیاری)
            $current = $_POST['current_pass'] ?? '';
            $new_pass = $_POST['new_pass'] ?? '';
            $confirm_pass = $_POST['confirm_pass'] ?? '';
            $pass_msg = '';
            if ($new_pass !== '' || $confirm_pass !== '' || $current !== '') {
                $db_pass2 = getSetting($conn, 'admin_password', '');
                if (!sitak_password_matches($current, $db_pass2)) { $pass_error = "گذرواژه فعلی اشتباه است."; }
                else {
                    $perrs = [];
                    if (strlen($new_pass) < 8) $perrs[] = 'گذرواژه جدید باید حداقل ۸ کاراکتر باشد.';
                    if (!preg_match('/[0-9]/', $new_pass)) $perrs[] = 'گذرواژه جدید فاقد رقم (number) است.';
                    if (!preg_match('/[a-z]/', $new_pass)) $perrs[] = 'گذرواژه جدید فاقد حرف کوچک انگلیسی (lowercase) است.';
                    if (!preg_match('/[A-Z]/', $new_pass)) $perrs[] = 'گذرواژه جدید فاقد حرف بزرگ انگلیسی (uppercase) است.';
                    if (!preg_match('/[^A-Za-z0-9]/', $new_pass)) $perrs[] = 'گذرواژه جدید فاقد نماد (symbol مثل !@#$) است.';
                    if (preg_match('/[^ -~]/', $new_pass)) $perrs[] = 'گذرواژه جدید فقط باید شامل کاراکترهای انگلیسی باشد.';
                    if ($new_pass !== $confirm_pass) $perrs[] = 'مقدار «گذرواژه جدید» و «تکرار گذرواژه جدید» یکسان نیستند.';
                    if ($perrs) { $pass_error = implode(' | ', $perrs); }
                    else {
                        $st2 = $conn->prepare("UPDATE settings SET value_data = ? WHERE key_name = 'admin_password'");
                        $new_hash = sitak_password_hash($new_pass);
                        $st2->bind_param("s", $new_hash); $st2->execute();
            sitak_emergency_sync($conn, $admin_prefix);
                        sitak_admin_remember_revoke_all($conn); sitak_admin_remember_issue($conn);
                        $pass_msg = "گذرواژه با موفقیت تغییر کرد.";
                    }
                }
            }
            if (empty($pass_error)) {
                $pass_success = $pass_msg !== '' ? ('نشانی ذخیره شد. ' . $pass_msg) : 'نشانی با موفقیت ذخیره شد.';
            }
            // ریدایرکت به نشانی جدید همین صفحه (اگر نشانی عوض شده) — بعد از ذخیره کامل
            if ($slug !== $old_slug) {
                $__keys = ['' => 'home', '-settings' => 'settings', '-chance' => 'chance', '-blog' => 'blog', '-shop' => 'shop', '-stats' => 'stats'];
                $cur = basename($admin_self);
                $__goto_key = null;
                foreach ($__keys as $__sfx => $__k) {
                    if ($cur === 'admin' . $__sfx . '.php' || $cur === $old_slug . $__sfx . '.php') { $__goto_key = $__k; break; }
                }
                if ($__goto_key !== null) {
                    $__dest = ($slug !== '' ? $slug : 'admin') . ($__goto_key === 'home' ? '' : '-' . $__goto_key) . '.php';
                    if ($__dest !== $cur && file_exists(__DIR__ . '/' . $__dest)) {
                        if (!empty($pass_error)) { $_SESSION['sitak_slug_err'] = $pass_error; }
                        else { $_SESSION['sitak_slug_ok'] = $pass_success; }
                        header('Location: ' . $__dest);
                        exit;
                    }
                }
            }
        }
    }
}

// ── تغییر گذرواژه (سازگار با فرم قدیمی change_admin_pass) ──
foreach (['_ok','_err'] as $__sk) {
    $__k = 'sitak_slug' . $__sk;
    if (isset($_SESSION[$__k])) {
        if ($__sk === '_ok' && empty($pass_success)) $pass_success = $_SESSION[$__k];
        if ($__sk === '_err' && empty($pass_error)) $pass_error = $_SESSION[$__k];
        unset($_SESSION[$__k]);
    }
}
if (isset($_SESSION['sitak_slug_msg']) && empty($pass_success) && empty($pass_error)) {
    $pass_success = $_SESSION['sitak_slug_msg'];
    unset($_SESSION['sitak_slug_msg']);
}
$pass_error = $pass_error ?? ''; $pass_success = $pass_success ?? '';
if ($is_authenticated && isset($_POST['change_admin_pass'])) {
    $current = $_POST['current_pass'] ?? '';
    $new_pass = $_POST['new_pass'] ?? '';
    $confirm_pass = $_POST['confirm_pass'] ?? '';
    $db_pass = getSetting($conn, 'admin_password', '');
    if (!sitak_password_matches($current, $db_pass)) { $pass_error = "گذرواژه فعلی اشتباه است."; }
    else {
        $perrs = [];
        if (strlen($new_pass) < 8) $perrs[] = 'گذرواژه جدید باید حداقل ۸ کاراکتر باشد.';
        if (!preg_match('/[0-9]/', $new_pass)) $perrs[] = 'گذرواژه جدید فاقد رقم (number) است.';
        if (!preg_match('/[a-z]/', $new_pass)) $perrs[] = 'گذرواژه جدید فاقد حرف کوچک انگلیسی (lowercase) است.';
        if (!preg_match('/[A-Z]/', $new_pass)) $perrs[] = 'گذرواژه جدید فاقد حرف بزرگ انگلیسی (uppercase) است.';
        if (!preg_match('/[^A-Za-z0-9]/', $new_pass)) $perrs[] = 'گذرواژه جدید فاقد نماد (symbol مثل !@#$) است.';
        if (preg_match('/[^ -~]/', $new_pass)) $perrs[] = 'گذرواژه جدید فقط باید شامل کاراکترهای انگلیسی باشد.';
        if ($new_pass !== $confirm_pass) $perrs[] = 'مقدار «گذرواژه جدید» و «تکرار گذرواژه جدید» یکسان نیستند.';
        if ($perrs) { $pass_error = implode(' | ', $perrs); }
        else {
            $stmt = $conn->prepare("UPDATE settings SET value_data = ? WHERE key_name = 'admin_password'");
            $new_hash = sitak_password_hash($new_pass);
            $stmt->bind_param("s", $new_hash); $stmt->execute();
            sitak_emergency_sync($conn, $admin_prefix);
            sitak_admin_remember_revoke_all($conn); sitak_admin_remember_issue($conn);
            $pass_success = "گذرواژه با موفقیت تغییر کرد.";
        }
    }
}

// ── کنترل لیست برندگان ──
if ($is_authenticated && isset($_POST['save_winners_control'])) {
    $winners_list_enabled = isset($_POST['winners_list_enabled']) ? '1' : '0';
    $winners_home_enabled = isset($_POST['winners_home_enabled']) ? '1' : '0';
    $winners_fake_enabled = isset($_POST['winners_fake_enabled']) ? '1' : '0';
    $fake_interval_hours_min = max(1, min(48, intval($_POST['fake_interval_hours_min'] ?? 2)));
    $fake_interval_hours_max = max(1, min(48, intval($_POST['fake_interval_hours_max'] ?? 6)));
    if ($fake_interval_hours_min > $fake_interval_hours_max) { $tmp = $fake_interval_hours_min; $fake_interval_hours_min = $fake_interval_hours_max; $fake_interval_hours_max = $tmp; }
    $fake_winner_count = max(1, intval($_POST['fake_winner_count'] ?? 1));
    $winners_display_count = max(1, min(50, intval($_POST['winners_display_count'] ?? 5)));
    $vals = ['winners_list_enabled' => $winners_list_enabled, 'winners_home_enabled' => $winners_home_enabled, 'winners_fake_enabled' => $winners_fake_enabled, 'fake_interval_hours_min' => (string)$fake_interval_hours_min, 'fake_interval_hours_max' => (string)$fake_interval_hours_max, 'fake_winner_count' => (string)$fake_winner_count, 'winners_display_count' => (string)$winners_display_count];
    foreach ($vals as $k => $v) {
        $s = $conn->prepare("INSERT INTO settings (key_name, value_data) VALUES (?, ?) ON DUPLICATE KEY UPDATE value_data=?");
        $s->bind_param("sss", $k, $v, $v); $s->execute();
    }
    header('Location: '.$admin_self); exit;
}
if ($is_authenticated && isset($_POST['add_prefix'])) {
    $prefix = trim($_POST['prefix'] ?? '');
    if (!preg_match('/^09[0-9]{2}$/', $prefix)) { if (ob_get_length()) { ob_clean(); } echo json_encode(['success'=>false,'message'=>'پیش‌شماره نامعتبر — فقط ۴ رقم با 09 (مثل 0912) مورد قبول است.']); exit; }
    $dup = $conn->prepare("SELECT id FROM fake_prefixes WHERE prefix = ?");
    $dup->bind_param("s", $prefix); $dup->execute();
    if ($dup->get_result()->num_rows > 0) { if (ob_get_length()) { ob_clean(); } echo json_encode(['success'=>false,'message'=>'این پیش‌شماره قبلاً ثبت شده است.']); exit; }
    $st = $conn->prepare("INSERT INTO fake_prefixes (prefix) VALUES (?)");
    $st->bind_param("s", $prefix); $st->execute();
    header('Location: '.$admin_self); exit;
}
if ($is_authenticated && isset($_POST['edit_prefix'])) {
    $pid = intval($_POST['prefix_id'] ?? 0);
    $prefix = trim($_POST['prefix'] ?? '');
    if ($pid <= 0 || !preg_match('/^09[0-9]{2}$/', $prefix)) { if (ob_get_length()) { ob_clean(); } echo json_encode(['success'=>false,'message'=>'پیش‌شماره نامعتبر — فقط ۴ رقم با 09 مورد قبول است.']); exit; }
    $st = $conn->prepare("UPDATE fake_prefixes SET prefix=? WHERE id=?");
    $st->bind_param("si", $prefix, $pid); $st->execute();
    header('Location: '.$admin_self); exit;
}
if ($is_authenticated && isset($_GET['delete_prefix'])) {
    if (!sitak_csrf_valid($_GET['csrf'] ?? '')) { http_response_code(403); exit('درخواست نامعتبر است.'); }
    $pid = intval($_GET['delete_prefix']);
    $del = $conn->prepare("DELETE FROM fake_prefixes WHERE id = ?");
    $del->bind_param("i", $pid); $del->execute();
    header('Location: '.$admin_self); exit;
}
if ($is_authenticated && isset($_GET['toggle_prize_active'])) {
    if (!sitak_csrf_valid($_GET['csrf'] ?? '')) { http_response_code(403); exit('درخواست نامعتبر است.'); }
    $pid = intval($_GET['toggle_prize_active']);
    $st = $conn->prepare("UPDATE prizes SET is_active = 1 - is_active WHERE id = ?");
    $st->bind_param("i", $pid); $st->execute();
    header('Location: ' . $admin_self); exit;
}
if ($is_authenticated && isset($_GET['toggle_hafez_active'])) {
    if (!sitak_csrf_valid($_GET['csrf'] ?? '')) { http_response_code(403); exit('درخواست نامعتبر است.'); }
    $hid = intval($_GET['toggle_hafez_active']);
    $st = $conn->prepare("UPDATE hafez_verses SET is_active = 1 - is_active WHERE id = ?");
    $st->bind_param("i", $hid); $st->execute();
    header('Location: ' . $admin_self); exit;
}

if ($is_authenticated && isset($_GET['toggle_prefix'])) {
    if (!sitak_csrf_valid($_GET['csrf'] ?? '')) { http_response_code(403); exit('درخواست نامعتبر است.'); }
    $pid = intval($_GET['toggle_prefix']);
    $st = $conn->prepare("UPDATE fake_prefixes SET is_active = 1 - is_active WHERE id = ?");
    $st->bind_param("i", $pid); $st->execute();
    header('Location: '.$admin_self); exit;
}

// ── آپدیت کاربر (مراجعات، شانس، نام) ──
if ($is_authenticated && isset($_POST['update_user_stats'])) {
    $phone = trim($_POST['user_phone'] ?? '');
    $visits = intval($_POST['new_visits'] ?? 0);
    $rolls = intval($_POST['new_rolls'] ?? 0);
    $name = htmlspecialchars(strip_tags(trim($_POST['new_name'] ?? '')), ENT_QUOTES, 'UTF-8');
    if (mb_strlen($name, 'UTF-8') > 30) { if (ob_get_length()) { ob_clean(); } echo json_encode(['success'=>false,'message'=>'نام حداکثر ۳۰ کاراکتر است.']); exit; }
    if (!preg_match('/^09[0-9]{9}$/', $phone)) { if (ob_get_length()) { ob_clean(); } echo json_encode(['success'=>false,'message'=>'شماره معتبر نیست.']); exit; }
    $stmt = $conn->prepare("UPDATE users SET visits = ?, referral_rolls = ?, name = ? WHERE phone = ?");
    $stmt->bind_param("iiss", $visits, $rolls, $name, $phone); $stmt->execute();
    if (ob_get_length()) { ob_clean(); }
    echo json_encode(['success'=>true]); exit;
}

// ── ریست شمارنده‌ی مراجعات کارت وفاداری ──
if ($is_authenticated && isset($_POST['reset_user_visits'])) {
    if (ob_get_length()) { ob_clean(); }
    if (!sitak_csrf_valid($_POST['csrf_token'] ?? '')) { echo json_encode(['success'=>false,'message'=>'درخواست نامعتبر است.']); exit; }
    $phone = trim($_POST['user_phone'] ?? '');
    if (!preg_match('/^09[0-9]{9}$/', $phone)) { echo json_encode(['success'=>false,'message'=>'شماره معتبر نیست.']); exit; }
    $rs = $conn->prepare("UPDATE users SET visits = 0 WHERE phone = ?");
    $rs->bind_param("s", $phone); $rs->execute();
    echo json_encode(['success'=>true]); exit;
}

// ── حذف کاربر + تمام داده‌های او ──
if ($is_authenticated && isset($_GET['delete_user'])) {
    if (!sitak_csrf_valid($_GET['csrf'] ?? '')) { http_response_code(403); exit('درخواست نامعتبر است.'); }
    $phone = trim($_GET['delete_user']);
    if (!preg_match('/^09[0-9]{9}$/', $phone)) { if (ob_get_length()) { ob_clean(); } echo json_encode(['success'=>false,'message'=>'شماره معتبر نیست.']); exit; }
    $d1 = $conn->prepare("DELETE FROM winners WHERE phone = ?"); $d1->bind_param("s", $phone); $d1->execute();
    $d2 = $conn->prepare("DELETE FROM hafez_opens WHERE user_phone = ?"); $d2->bind_param("s", $phone); $d2->execute();
    $d3 = $conn->prepare("DELETE FROM orders WHERE user_phone = ?"); $d3->bind_param("s", $phone); $d3->execute();
    $d4 = $conn->prepare("DELETE FROM users WHERE phone = ?"); $d4->bind_param("s", $phone); $d4->execute();
    if (ob_get_length()) { ob_clean(); }
    echo json_encode(['success'=>true]); exit;
}

// ── مسدود/رفع مسدودی کاربر ──
if ($is_authenticated && isset($_GET['toggle_comment_block'])) {
    if (!sitak_csrf_valid($_GET['csrf'] ?? '')) { http_response_code(403); exit('درخواست نامعتبر است.'); }
    $phone = trim($_GET['toggle_comment_block']);
    if (!preg_match('/^09[0-9]{9}$/', $phone)) { if (ob_get_length()) { ob_clean(); } echo json_encode(['success'=>false,'message'=>'شماره معتبر نیست.']); exit; }
    $stmt = $conn->prepare("UPDATE users SET comment_blocked = 1 - comment_blocked WHERE phone = ?");
    $stmt->bind_param('s',$phone); $stmt->execute();
    $q=$conn->prepare("SELECT comment_blocked FROM users WHERE phone=?"); $q->bind_param('s',$phone); $q->execute(); $new_state=(int)($q->get_result()->fetch_assoc()['comment_blocked']??0);
    if (ob_get_length()) { ob_clean(); } echo json_encode(['success'=>true,'comment_blocked'=>$new_state]); exit;
}

if ($is_authenticated && isset($_GET['toggle_block'])) {
    if (!sitak_csrf_valid($_GET['csrf'] ?? '')) { http_response_code(403); exit('درخواست نامعتبر است.'); }
    $phone = trim($_GET['toggle_block']);
    if (!preg_match('/^09[0-9]{9}$/', $phone)) { if (ob_get_length()) { ob_clean(); } echo json_encode(['success'=>false,'message'=>'شماره معتبر نیست.']); exit; }
    $stmt = $conn->prepare("UPDATE users SET blocked = 1 - blocked WHERE phone = ?");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $st_state = $conn->prepare("SELECT blocked FROM users WHERE phone = ?");
    $st_state->bind_param("s", $phone); $st_state->execute();
    $new_state = $st_state->get_result()->fetch_assoc()['blocked'] ?? null;
    if (ob_get_length()) { ob_clean(); }
    echo json_encode(['success'=>true, 'blocked' => $new_state]); exit;
}

// ── تحویل جایزه ──
if ($is_authenticated && isset($_GET['toggle_claimed'])) {
    if (!sitak_csrf_valid($_GET['csrf'] ?? '')) { http_response_code(403); exit('درخواست نامعتبر است.'); }
    $wid = intval($_GET['toggle_claimed']);
    $ts = $conn->prepare("UPDATE winners SET claimed = 1 - claimed WHERE id = ?");
    $ts->bind_param("i", $wid); $ts->execute();
    if (ob_get_length()) { ob_clean(); }
    echo json_encode(['success'=>true]); exit;
}

// ── جزئیات کاربر ──
if ($is_authenticated && isset($_GET['user_details'])) {
    $phone = trim($_GET['user_details']);
    if (!preg_match('/^09[0-9]{9}$/', $phone)) { if (ob_get_length()) { ob_clean(); } echo json_encode(['success'=>false,'message'=>'شماره معتبر نیست.']); exit; }
    $stmt = $conn->prepare("SELECT * FROM users WHERE phone = ?");
    $stmt->bind_param("s", $phone); $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    if (!$user) { if (ob_get_length()) { ob_clean(); } echo json_encode(['success'=>false,'message'=>'کاربری پیدا نشد.']); exit; }
    $user['created_at_relative'] = time_ago($user['created_at']);
    $ws = $conn->prepare("SELECT id,prize_name,won_at,claimed FROM winners WHERE phone=? AND is_fake=0 ORDER BY won_at DESC");
    $ws->bind_param("s",$phone);$ws->execute();$wr=$ws->get_result();$wins=[];
    while($w=$wr->fetch_assoc()){$w['won_at_relative']=time_ago($w['won_at']);$wins[]=$w;}
    $rs = $conn->prepare("SELECT phone,name,created_at FROM users WHERE referral_code=? ORDER BY created_at DESC");
    $rs->bind_param("s",$phone);$rs->execute();$rr=$rs->get_result();$refs=[];
    while($r=$rr->fetch_assoc()){$r['created_at_relative']=time_ago($r['created_at']);$refs[]=$r;}

    $os = $conn->prepare("SELECT id,total_price,status,created_at,items,note,admin_note,payment_method,card_submitted_at FROM orders WHERE user_phone=? ORDER BY created_at DESC LIMIT 10");
    $os->bind_param("s",$phone);$os->execute();$orr=$os->get_result();$orders=[];
    while($o=$orr->fetch_assoc()){$o['created_at_relative']=time_ago($o['created_at']);$orders[]=$o;}
    $ag = $conn->prepare("SELECT COUNT(*) c, COALESCE(SUM(total_price),0) s FROM orders WHERE user_phone=?");
    $ag->bind_param("s",$phone);$ag->execute();
    $a = $ag->get_result()->fetch_assoc();

    if (ob_get_length()) { ob_clean(); }

    echo json_encode(['success'=>true,'user'=>$user,'wins'=>$wins,'referrals'=>$refs,'orders'=>$orders,'orders_count'=>intval($a['c']),'orders_sum'=>intval($a['s'])]); exit;
}

// ── خواندن تنظیمات ──
$reset_hour         = getSetting($conn, 'reset_hour', '21');
$reset_hour_enabled = getSetting($conn, 'reset_hour_enabled', '1');
$reset_hour_home_enabled = getSetting($conn, 'reset_hour_home_enabled', '1');
$reset_hour_chance_enabled = getSetting($conn, 'reset_hour_chance_enabled', '1');
$site_name          = getSetting($conn, 'site_name', 'سای‌تک');
$site_slogan        = getSetting($conn, 'site_slogan', 'یک وبسایت تک');
$loyalty_enabled    = getSetting($conn, 'loyalty_enabled', '1');
$loyalty_dice_count = intval(getSetting($conn, 'loyalty_dice_count', '2'));
$loyalty_online_order = getSetting($conn, 'loyalty_online_order', '0');
$contact_enabled    = getSetting($conn, 'contact_enabled', '1');
$contact_instagram  = getSetting($conn, 'contact_instagram', '');
$contact_telegram   = getSetting($conn, 'contact_telegram', '');
$contact_youtube    = getSetting($conn, 'contact_youtube', '');
$contact_phone      = getSetting($conn, 'contact_phone', '');
$contact_address    = getSetting($conn, 'contact_address', '');
$announcement_enabled = getSetting($conn, 'announcement_enabled', '0');
$announcement_message = getSetting($conn, 'announcement_message', '');
$admin_login_slug = $admin_login_slug ?? trim(getSetting($conn, 'admin_login_slug', ''));
$default_theme      = getSetting($conn, 'default_theme', 'auto');
$custom_favicon     = getSetting($conn, 'custom_favicon', '');
$custom_logo        = getSetting($conn, 'custom_logo', '');
$custom_emoji        = getSetting($conn, 'custom_emoji', '🌐');
$hafez_enabled      = getSetting($conn, 'hafez_enabled', '1');
$licenses_enabled   = getSetting($conn, 'licenses_enabled', '1');
$license1_img       = getSetting($conn, 'license1_img', '');
$license1_link      = getSetting($conn, 'license1_link', '');
$license1_name      = getSetting($conn, 'license1_name', '');
$license1_embed     = getSetting($conn, 'license1_embed', '');
$license2_img       = getSetting($conn, 'license2_img', '');
$license2_link      = getSetting($conn, 'license2_link', '');
$license2_name      = getSetting($conn, 'license2_name', '');
$license2_embed     = getSetting($conn, 'license2_embed', '');
$winners_list_enabled = getSetting($conn, 'winners_list_enabled', '1');
$winners_home_enabled = getSetting($conn, 'winners_home_enabled', '1');
$winners_fake_enabled = getSetting($conn, 'winners_fake_enabled', '1');
$fake_interval_hours_min = getSetting($conn, 'fake_interval_hours_min', '2');
$fake_interval_hours_max = getSetting($conn, 'fake_interval_hours_max', '6');
$fake_winner_count    = getSetting($conn, 'fake_winner_count', '1');
$winners_display_count = getSetting($conn, 'winners_display_count', '5');
$blog_enabled         = getSetting($conn, 'blog_enabled', '1');
$home_blog_enabled    = getSetting($conn, 'home_blog_enabled', '1');
$show_post_ratings_admin = getSetting($conn, 'show_post_ratings', '1');
$home_blog_count_admin = getSetting($conn, 'home_blog_count', '4');
$home_shop_count_admin = getSetting($conn, 'home_shop_count', '4');
$home_blog_type       = getSetting($conn, 'home_blog_type', 'latest');
$shop_enabled         = getSetting($conn, 'shop_enabled', '1');
$orders_tab_enabled = getSetting($conn, 'orders_tab_enabled', '1');
$home_shop_enabled    = getSetting($conn, 'home_shop_enabled', '1');
$show_product_ratings_admin = getSetting($conn, 'show_product_ratings', '1');
$home_shop_type       = getSetting($conn, 'home_shop_type', 'best');
$store_shipping_province=getSetting($conn,'store_shipping_province',''); $store_shipping_city=getSetting($conn,'store_shipping_city','');
$store_active_days = getSetting($conn,'store_active_days','');
$store_morning_open = getSetting($conn,'store_morning_open',''); $store_morning_close = getSetting($conn,'store_morning_close','');
$store_afternoon_open = getSetting($conn,'store_afternoon_open',''); $store_afternoon_close = getSetting($conn,'store_afternoon_close','');
$store_closed_day_message = trim(getSetting($conn,'store_closed_day_message','')) ?: 'امروز فروشگاه تعطیل است و امکان ثبت سفارش وجود ندارد.';
$store_closed_time_message = trim(getSetting($conn,'store_closed_time_message','')) ?: 'در حال حاضر خارج از ساعت کاری فروشگاه هستیم و امکان ثبت سفارش وجود ندارد.';
$chance_enabled       = getSetting($conn, 'chance_enabled', '1');
$dice_enabled         = getSetting($conn, 'dice_enabled', '1');
$daily_roll_count_admin = getSetting($conn, 'daily_roll_count', '1');
$dice_home_enabled    = getSetting($conn, 'dice_home_enabled', '1');
$home_hafez_enabled   = getSetting($conn, 'hafez_home_enabled', '1');
$hero_title           = getSetting($conn, 'hero_title', 'جفت ۶ بیار، مهمون ما باش!');
$hero_description     = getSetting($conn, 'hero_description', 'تاس بنداز و شانس دریافت هدایای شگفت‌انگیز رو امتحان کن.');
$hero_enabled         = getSetting($conn, 'hero_enabled', '1');
$footer_copyright     = getSetting($conn, 'footer_copyright', '');
$footer_copyright_enabled = getSetting($conn, 'footer_copyright_enabled', '1');
$chance_title          = getSetting($conn, 'chance_title', 'جفت ۶ بیار، مهمون ما باش!');
$chance_description    = getSetting($conn, 'chance_description', 'تاس‌ها رو بنداز و شانس دریافت هدایای شگفت‌انگیز رو امتحان کن.');
$blog_page_title       = getSetting($conn, 'blog_page_title', 'وبلاگ');
$blog_page_description = getSetting($conn, 'blog_page_description', 'مقاله‌ها، داستان‌ها و معرفی محصولات ما');
$loyalty_page_title       = getSetting($conn, 'loyalty_page_title', 'کارت وفاداری شما');
$loyalty_page_description = getSetting($conn, 'loyalty_page_description', "هر خرید حضوری = 1 امتیاز\nبا شش کردن تاس، مهمان ما باش");
$loyalty_complete_msg = getSetting($conn, 'loyalty_complete_msg', "🎉 تاس تکمیل شد! این صفحه رو به ما نشون بده تا هدیه‌ت رو بگیری!");
$loyalty_card_hint = getSetting($conn, 'loyalty_card_hint', "تاس رو به شش تبدیل کن و از ما جایزه بگیر");
$shop_page_title       = trim(getSetting($conn, 'shop_page_title', '')) ?: 'منو';
$shop_page_description = trim(getSetting($conn, 'shop_page_description', '')) ?: 'محصولات تازه‌ی ما را ببینید و سفارش دهید';
$orders_page_title       = trim(getSetting($conn, 'orders_page_title', '')) ?: 'سفارشات من';
$orders_page_description = getSetting($conn, 'orders_page_description', '');
$comments_products_enabled=getSetting($conn,'comments_products_enabled','1');$comments_posts_enabled=getSetting($conn,'comments_posts_enabled','1');$comments_require_approval=getSetting($conn,'comments_require_approval','1');$comments_submit_products_enabled=getSetting($conn,'comments_submit_products_enabled','1');$comments_submit_posts_enabled=getSetting($conn,'comments_submit_posts_enabled','1');$comments_dislike_products_enabled=getSetting($conn,'comments_dislike_products_enabled','1');$comments_dislike_posts_enabled=getSetting($conn,'comments_dislike_posts_enabled','1');$comments_reactions_products_enabled=getSetting($conn,'comments_reactions_products_enabled','1');$comments_reactions_posts_enabled=getSetting($conn,'comments_reactions_posts_enabled','1');
$firewall_enabled = getSetting($conn, 'firewall_enabled', '1');
$firewall_honeypot_enabled = getSetting($conn, 'firewall_honeypot_enabled', '1');
$firewall_logging_enabled = getSetting($conn, 'firewall_logging_enabled', '1');
$firewall_rate_limit_enabled = getSetting($conn, 'firewall_rate_limit_enabled', '1');
$firewall_rate_limit_window = getSetting($conn, 'firewall_rate_limit_window', '60');
$firewall_rate_limit_max = getSetting($conn, 'firewall_rate_limit_max', '5');
$firewall_block_threshold = getSetting($conn, 'firewall_block_threshold', '10');
$firewall_log_retention_days = getSetting($conn, 'firewall_log_retention_days', '30');
$firewall_stats_total = (int)($conn->query("SELECT COUNT(*) c FROM firewall_events")->fetch_assoc()['c'] ?? 0);
$firewall_stats_24h = (int)($conn->query("SELECT COUNT(*) c FROM firewall_events WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetch_assoc()['c'] ?? 0);
$firewall_stats_honeypot = (int)($conn->query("SELECT COUNT(*) c FROM firewall_events WHERE event_type='honeypot'")->fetch_assoc()['c'] ?? 0);
$firewall_stats_rate = (int)($conn->query("SELECT COUNT(*) c FROM firewall_events WHERE event_type='rate_limit'")->fetch_assoc()['c'] ?? 0);

// ── Order Details (JSON) ──
if ($is_authenticated && isset($_GET['action']) && $_GET['action'] === 'order_details') {
    while (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');
    $oid = intval($_GET['order_id'] ?? 0);
    if ($oid <= 0) { echo json_encode(['success' => false, 'message' => 'شماره سفارش نامعتبر'], JSON_UNESCAPED_UNICODE); exit; }
    $ost = $conn->prepare("SELECT * FROM orders WHERE id = ?");
    $ost->bind_param("i", $oid); $ost->execute();
    $order = $ost->get_result()->fetch_assoc();
    if (!$order) { echo json_encode(['success' => false, 'message' => 'سفارش یافت نشد'], JSON_UNESCAPED_UNICODE); exit; }
    $uaddr = '';
    $as = $conn->prepare("SELECT address FROM users WHERE phone = ?");
    $as->bind_param("s", $order['user_phone']); $as->execute();
    $urow = $as->get_result()->fetch_assoc();
    if ($urow && !empty($urow['address'])) { $uaddr = $urow['address']; }
    // تاریخ‌های شمسی برای بررسی واریز کارت‌به‌کارت در مودال جزئیات
    if (($order['payment_method'] ?? '') === 'card') {
        $order['card_paid_at_fa'] = !empty($order['card_paid_at']) ? jdatetime_fa($order['card_paid_at']) : '';
        $order['card_submitted_at_fa'] = !empty($order['card_submitted_at']) ? jdatetime_fa($order['card_submitted_at']) : '';
        $order['card_lock_expires_fa'] = !empty($order['card_lock_expires']) ? jdatetime_fa($order['card_lock_expires']) : '';
    }
    echo json_encode(['success' => true, 'order' => $order, 'user_address' => $uaddr], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    exit;
}

// ── ذخیره یادداشت ادمین برای سفارش ──
if ($is_authenticated && isset($_POST['save_order_note'])) {
    $oid = intval($_POST['order_id'] ?? 0);
    $anote = htmlspecialchars(strip_tags(trim($_POST['admin_note'] ?? '')), ENT_QUOTES, 'UTF-8');
    if ($oid <= 0) { if (ob_get_length()) { ob_clean(); } echo json_encode(['success' => false, 'message' => 'شماره سفارش نامعتبر']); exit; }
    $st = $conn->prepare("UPDATE orders SET admin_note = ? WHERE id = ?");
    $st->bind_param("si", $anote, $oid); $st->execute();
    if (ob_get_length()) { ob_clean(); }
    echo json_encode(['success' => true]); exit;
}

// ── حذف سفارش ──
if ($is_authenticated && isset($_GET['delete_order'])) {
    if (!sitak_csrf_valid($_GET['csrf'] ?? '')) { http_response_code(403); exit('درخواست نامعتبر است.'); }
    $oid = intval($_GET['delete_order']);
    if ($oid <= 0) { if (ob_get_length()) { ob_clean(); } echo json_encode(['success' => false, 'message' => 'شماره سفارش نامعتبر']); exit; }
    $del = $conn->prepare("DELETE FROM orders WHERE id = ?");
    $del->bind_param("i", $oid); $del->execute();
    if (ob_get_length()) { ob_clean(); }
    echo json_encode(['success' => true]); exit;
}

// ── دسته‌بندی‌های فروشگاه ──
if ($is_authenticated && isset($_POST['save_shop_cat'])) {
    $scid = intval($_POST['shop_cat_id'] ?? 0);
    $name = htmlspecialchars(strip_tags(trim($_POST['shop_cat_name'] ?? '')), ENT_QUOTES, 'UTF-8');
    $sort = intval($_POST['shop_cat_sort'] ?? 0);
    if (empty($name) || mb_strlen($name, 'UTF-8') > 50) { if (ob_get_length()) { ob_clean(); } echo json_encode(['success'=>false,'message'=>'نام دسته الزامی است (حداکثر ۵۰ کاراکتر).']); exit; }
    $dup = $conn->prepare("SELECT id FROM shop_categories WHERE name = ? AND id <> ?");
    $dup->bind_param("si", $name, $scid); $dup->execute();
    if ($dup->get_result()->num_rows > 0) { if (ob_get_length()) { ob_clean(); } echo json_encode(['success'=>false,'message'=>'این نام قبلاً ثبت شده است.']); exit; }
    if ($scid > 0) {
        $old = $conn->prepare("SELECT name FROM shop_categories WHERE id = ?");
        $old->bind_param("i", $scid); $old->execute();
        $old_row = $old->get_result()->fetch_assoc();
        $st = $conn->prepare("UPDATE shop_categories SET name=?, sort_order=? WHERE id=?");
        $st->bind_param("sii", $name, $sort, $scid); $st->execute();
        if ($old_row && $old_row['name'] !== $name) {
            $up = $conn->prepare("UPDATE products SET category=? WHERE category=?");
            $up->bind_param("ss", $name, $old_row['name']); $up->execute();
        }
    } else {
        $st = $conn->prepare("INSERT INTO shop_categories (name, sort_order) VALUES (?, ?)");
        $st->bind_param("si", $name, $sort); $st->execute();
    }
    header('Location: '.$admin_self); exit;
}
if ($is_authenticated && isset($_GET['delete_shop_cat'])) {
    if (!sitak_csrf_valid($_GET['csrf'] ?? '')) { http_response_code(403); exit('درخواست نامعتبر است.'); }
    $scid = intval($_GET['delete_shop_cat']);
    $cnt = $conn->prepare("SELECT COUNT(*) AS c FROM products WHERE category = (SELECT name FROM shop_categories WHERE id = ?)");
    $cnt->bind_param("i", $scid); $cnt->execute();
    if ($cnt->get_result()->fetch_assoc()['c'] > 0) { if (ob_get_length()) { ob_clean(); } echo json_encode(['success'=>false,'message'=>'این دسته‌بندی دارای محصول است. ابتدا محصولات آن را جابه‌جا کنید.']); exit; }
    $del = $conn->prepare("DELETE FROM shop_categories WHERE id = ?");
    $del->bind_param("i", $scid); $del->execute();
    if (ob_get_length()) { ob_clean(); }
    echo json_encode(['success'=>true]); exit;
}

// ── Product CRUD ──
    if ($is_authenticated && isset($_POST['delete_product_image'])) {
    $pid = intval($_POST['delete_product_image']);
    if ($pid > 0) {
        $old = getSetting($conn, "product_img_$pid", '');
        if ($old && file_exists($old)) @unlink($old);
        $cur = $conn->query("SELECT image_path FROM products WHERE id=$pid")->fetch_assoc()['image_path'] ?? '';
        if ($cur && $cur !== $old && file_exists($cur)) @unlink($cur);
        $conn->query("DELETE FROM settings WHERE key_name='product_img_$pid'");
        $conn->query("UPDATE products SET image_path=NULL WHERE id=$pid");
    }
    header('Location: '.admin_url('shop').'?edit='.$pid); exit;
}
if ($is_authenticated && isset($_POST['save_product'])) {
        $pid = intval($_POST['product_id'] ?? 0);
        $cat = trim($_POST['product_cat'] ?? 'عمومی');
        $name = trim(strip_tags($_POST['product_name'] ?? ''));
        $desc = sitak_clean_rich($_POST['product_desc'] ?? '');
        $price = intval($_POST['product_price'] ?? 0);
        $available = isset($_POST['product_available']) ? 1 : 0;
        $onsite_product = isset($_POST['product_onsite']) ? 1 : 0;
        $sort = intval($_POST['product_sort'] ?? 0);
        $stock_raw = sitak_normalize_digits(trim((string)($_POST['product_stock'] ?? '')));
        $stock_val = $stock_raw === '' ? null : max(0, (int)preg_replace('/[^0-9]/', '', $stock_raw));
        $shipping_province = trim($_POST['shipping_province'] ?? '');
        $shipping_city = trim($_POST['shipping_city'] ?? '');
        if ($shipping_province === '') $shipping_city = '';
        $morning_open=trim($_POST['morning_open']??''); $morning_close=trim($_POST['morning_close']??''); $afternoon_open=trim($_POST['afternoon_open']??''); $afternoon_close=trim($_POST['afternoon_close']??''); $active_days=implode(',',array_map('intval',$_POST['active_days']??[]));
        $delete_img = isset($_POST['delete_product_image']) ? 1 : 0;
        if (!empty($name) && $price > 0) {
            if (!file_exists('uploads')) { @mkdir('uploads', 0755, true); }
            // بارگذاری تصویر
            $img = null; $img_err = false;
            if (isset($_FILES['product_img']) && $_FILES['product_img']['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($_FILES['product_img']['error'] !== UPLOAD_ERR_OK) { $img_err = true; }
                else {
                    $file = $_FILES['product_img'];
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $allowed = ['png','jpg','jpeg','gif','webp'];
                    if (in_array($ext, $allowed) && sitak_safe_image_upload($file, $allowed)) {
                        if ($pid > 0) {
                            $old = getSetting($conn, "product_img_$pid", '');
                            if ($old && file_exists($old)) @unlink($old);
                        }
                        $new = 'uploads/product_' . time() . '.' . $ext;
                        if (move_uploaded_file($file['tmp_name'], $new)) { $img = $new; }
                    } else { $img_err = true; }
                }
            }
            // حذف تصویر اگر تیک خورده باشد
            if ($delete_img && $pid > 0 && !$img) {
                $old = getSetting($conn, "product_img_$pid", '');
                if ($old && file_exists($old)) @unlink($old);
                $conn->query("DELETE FROM settings WHERE key_name = 'product_img_$pid'");
            }
            $final_img = $delete_img && !$img ? '' : null;
            if ($pid > 0) {
                if ($img) {
                    $pkey = "product_img_$pid";
                    $st = $conn->prepare("UPDATE products SET category=?, name=?, description=?, price=?, image_path=?, is_available=?, sort_order=?, onsite_product=?, shipping_province=?, shipping_city=?, morning_open=?, morning_close=?, afternoon_open=?, afternoon_close=?, active_days=? WHERE id=?");
                    $st->bind_param("sssisiiisssssssi", $cat, $name, $desc, $price, $img, $available, $sort, $onsite_product, $shipping_province, $shipping_city, $morning_open, $morning_close, $afternoon_open, $afternoon_close, $active_days, $pid); $st->execute();
                    $st2 = $conn->prepare("INSERT INTO settings (key_name, value_data) VALUES (?, ?) ON DUPLICATE KEY UPDATE value_data=?");
                    $st2->bind_param("sss", $pkey, $img, $img); $st2->execute();
                } elseif ($delete_img) {
                    $st = $conn->prepare("UPDATE products SET category=?, name=?, description=?, price=?, image_path=?, is_available=?, sort_order=?, onsite_product=?, shipping_province=?, shipping_city=?, morning_open=?, morning_close=?, afternoon_open=?, afternoon_close=?, active_days=? WHERE id=?");
                    $st->bind_param("sssisiiisssssssi", $cat, $name, $desc, $price, $final_img, $available, $sort, $onsite_product, $shipping_province, $shipping_city, $morning_open, $morning_close, $afternoon_open, $afternoon_close, $active_days, $pid); $st->execute();
                } else {
                    $st = $conn->prepare("UPDATE products SET category=?, name=?, description=?, price=?, is_available=?, sort_order=?, onsite_product=?, shipping_province=?, shipping_city=?, morning_open=?, morning_close=?, afternoon_open=?, afternoon_close=?, active_days=? WHERE id=?");
                    $st->bind_param("sssiiiisssssssi", $cat, $name, $desc, $price, $available, $sort, $onsite_product, $shipping_province, $shipping_city, $morning_open, $morning_close, $afternoon_open, $afternoon_close, $active_days, $pid); $st->execute();
                }
            } else {
                $st = $conn->prepare("INSERT INTO products (category, name, description, price, image_path, is_available, sort_order, onsite_product, shipping_province, shipping_city, morning_open, morning_close, afternoon_open, afternoon_close, active_days) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $imgv = $img ?: '';
                $st->bind_param("sssisiiisssssss", $cat, $name, $desc, $price, $imgv, $available, $sort, $onsite_product, $shipping_province, $shipping_city, $morning_open, $morning_close, $afternoon_open, $afternoon_close, $active_days);
                $st->execute();
                $new_id = $conn->insert_id;
                if ($img) {
                    $pkey = "product_img_$new_id";
                    $st2 = $conn->prepare("INSERT INTO settings (key_name, value_data) VALUES (?, ?) ON DUPLICATE KEY UPDATE value_data=?");
                    $st2->bind_param("sss", $pkey, $img, $img); $st2->execute();
                }
            }
            $stock_pid = $pid > 0 ? $pid : (int)$conn->insert_id;
            $stk = $conn->prepare("UPDATE products SET stock_qty = ? WHERE id = ?");
            $stk->bind_param('ii', $stock_val, $stock_pid); $stk->execute();
            sitak_save_product_tags($conn, $pid > 0 ? $pid : (int)$conn->insert_id, $_POST['product_tags'] ?? '');
            header('Location: '.admin_url('shop').($img_err?'?imgerr=1':'')); exit;
        }
    }
if ($is_authenticated && isset($_GET['delete_product'])) {
    if (!sitak_csrf_valid($_GET['csrf'] ?? '')) { http_response_code(403); exit('درخواست نامعتبر است.'); }
    $pid = intval($_GET['delete_product']);
    $conn->query("DELETE FROM products WHERE id=$pid");
    $old = getSetting($conn, "product_img_$pid", '');
    if ($old && file_exists($old)) @unlink($old);
    $conn->query("DELETE FROM settings WHERE key_name='product_img_$pid'");
    header('Location: '.$admin_self); exit;
}

// ── Category CRUD ──
if ($is_authenticated && isset($_POST['save_category'])) {
    $cid = intval($_POST['cat_id'] ?? 0);
    $name = htmlspecialchars(strip_tags(trim($_POST['cat_name'] ?? '')), ENT_QUOTES, 'UTF-8');
    $slug = trim($_POST['cat_slug'] ?? '');
    $desc = htmlspecialchars(strip_tags(trim($_POST['cat_desc'] ?? '')), ENT_QUOTES, 'UTF-8');
    $color = '#ecb534'; // همه‌ی دسته‌ها رنگ پیش‌فرض دارند
    $sort = intval($_POST['cat_sort'] ?? 0);
    if (empty($slug)) $slug = strtolower(preg_replace('/\s+/', '-', $name));
    if (!empty($name)) {
        if ($cid > 0) {
            $st = $conn->prepare("UPDATE post_categories SET name=?, slug=?, description=?, color=?, sort_order=? WHERE id=?");
            $st->bind_param("ssssii", $name, $slug, $desc, $color, $sort, $cid);
        } else {
            $st = $conn->prepare("INSERT INTO post_categories (name, slug, description, color, sort_order) VALUES (?, ?, ?, ?, ?)");
            $st->bind_param("ssssi", $name, $slug, $desc, $color, $sort);
        }
        $st->execute();
        header('Location: '.$admin_self); exit;
    }
}
if ($is_authenticated && isset($_GET['delete_category'])) {
    if (!sitak_csrf_valid($_GET['csrf'] ?? '')) { http_response_code(403); exit('درخواست نامعتبر است.'); }
    $cid = intval($_GET['delete_category']);
    $conn->query("DELETE FROM post_categories WHERE id=$cid");
    header('Location: '.$admin_self); exit;
}

// ── Post CRUD ──
if ($is_authenticated && isset($_POST['delete_post_cover'])) {
    $pid = intval($_POST['delete_post_cover']);
    if ($pid > 0) {
        $cur = $conn->query("SELECT cover_image FROM posts WHERE id=$pid")->fetch_assoc()['cover_image'] ?? '';
        if ($cur && file_exists($cur)) @unlink($cur);
        $conn->query("UPDATE posts SET cover_image=NULL WHERE id=$pid");
    }
    header('Location: '.admin_url('blog').'?edit='.$pid); exit;
}
if ($is_authenticated && isset($_POST['save_post'])) {
    $pid = intval($_POST['post_id'] ?? 0);
    $cat_id = !empty($_POST['post_cat']) ? intval($_POST['post_cat']) : null;
    $title = trim(strip_tags($_POST['post_title'] ?? ''));
    $slug = trim($_POST['post_slug'] ?? '');
    $excerpt = trim(strip_tags($_POST['post_excerpt'] ?? ''));
    $body = sitak_clean_rich($_POST['post_body'] ?? '');
    $published = isset($_POST['post_published']) ? 1 : 0;
    $tags_input = trim($_POST['post_tags'] ?? '');
    if (!empty($slug)) { $slug = strtolower(preg_replace('/[^a-z0-9\-]+/', '', $slug)); }
    else { $slug = strtolower(preg_replace('/\s+/', '-', $title)); }
    $slug = substr($slug, 0, 200);
    if (!empty($title) && !empty($body)) {
        if ($pid > 0) {
            $st = $conn->prepare("UPDATE posts SET category_id=?, title=?, slug=?, excerpt=?, body=?, is_published=? WHERE id=?");
            $st->bind_param("issssii", $cat_id, $title, $slug, $excerpt, $body, $published, $pid);
        } else {
            $st = $conn->prepare("INSERT INTO posts (category_id, title, slug, excerpt, body, is_published) VALUES (?, ?, ?, ?, ?, ?)");
            $st->bind_param("issssi", $cat_id, $title, $slug, $excerpt, $body, $published);
        }
        $st->execute();
        $saved_id = $pid > 0 ? $pid : $conn->insert_id;
        if (isset($_FILES['post_cover']) && $_FILES['post_cover']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['post_cover'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['png','jpg','jpeg','gif','webp'];
            if (in_array($ext, $allowed) && sitak_safe_image_upload($file, $allowed)) {
                $old = $conn->query("SELECT cover_image FROM posts WHERE id=$saved_id")->fetch_assoc()['cover_image'] ?? '';
                if ($old && file_exists($old)) @unlink($old);
                $new = 'uploads/post_' . time() . '.' . $ext;
                if (move_uploaded_file($file['tmp_name'], $new)) {
                    $st = $conn->prepare("UPDATE posts SET cover_image=? WHERE id=?");
                    $st->bind_param("si", $new, $saved_id); $st->execute();
                }
            }
        }
        $conn->query("DELETE FROM post_tag_relations WHERE post_id=$saved_id");
        if (!empty($tags_input)) {
            $tags = array_filter(array_map('trim', preg_split('/[,،]/u', $tags_input)), 'strlen');
            foreach ($tags as $tag) {
                $tag = htmlspecialchars(strip_tags($tag), ENT_QUOTES, 'UTF-8');
                $t_slug = strtolower(preg_replace('/\s+/', '-', $tag));
                $st_tag = $conn->prepare("SELECT id FROM post_tags WHERE slug = ? LIMIT 1");
                $st_tag->bind_param("s", $t_slug); $st_tag->execute();
                $t_row = $st_tag->get_result()->fetch_assoc();
                if ($t_row) { $tid = (int)$t_row['id']; }
                else { $t_ins = $conn->prepare("INSERT INTO post_tags (name, slug) VALUES (?, ?)"); $t_ins->bind_param("ss", $tag, $t_slug); $t_ins->execute(); $tid = $conn->insert_id; }
                $st_rel = $conn->prepare("INSERT IGNORE INTO post_tag_relations (post_id, tag_id) VALUES (?, ?)");
                $st_rel->bind_param("ii", $saved_id, $tid); $st_rel->execute();
            }
        }
        header('Location: '.$admin_self); exit;
    }
}
if ($is_authenticated && isset($_GET['delete_post'])) {
    if (!sitak_csrf_valid($_GET['csrf'] ?? '')) { http_response_code(403); exit('درخواست نامعتبر است.'); }
    $pid = intval($_GET['delete_post']);
    $cov = $conn->query("SELECT cover_image FROM posts WHERE id=$pid")->fetch_assoc()['cover_image'] ?? '';
    if ($cov && file_exists($cov)) @unlink($cov);
    $conn->query("DELETE FROM posts WHERE id=$pid");
    $conn->query("DELETE FROM post_tag_relations WHERE post_id=$pid");
    header('Location: '.$admin_self); exit;
}

// ── Hafez Verse CRUD ──
if ($is_authenticated && isset($_POST['save_hafez'])) {
    $hid = intval($_POST['hafez_id'] ?? 0);
    $m1 = trim($_POST['hafez_m1'] ?? '');
    $m2 = trim($_POST['hafez_m2'] ?? '');
    $meaning = htmlspecialchars(strip_tags(trim($_POST['hafez_meaning'] ?? '')), ENT_QUOTES, 'UTF-8');
    if ($m1 === '' || $m2 === '' || $meaning === '') { header('Location: '.$admin_self); exit; }
    $hafez_active = isset($_POST['hafez_active']) ? 1 : 0;
    if ($hid > 0) {
        $st = $conn->prepare("UPDATE hafez_verses SET verse_m1=?, verse_m2=?, meaning_short=?, is_active=? WHERE id=?");
        $st->bind_param("sssii", $m1, $m2, $meaning, $hafez_active, $hid);
    } else {
        $st = $conn->prepare("INSERT INTO hafez_verses (verse_m1, verse_m2, meaning_short, is_active) VALUES (?, ?, ?, ?)");
        $st->bind_param("sssi", $m1, $m2, $meaning, $hafez_active);
    }
    $st->execute();
    header('Location: '.$admin_self); exit;
}
if ($is_authenticated && isset($_GET['delete_hafez'])) {
    if (!sitak_csrf_valid($_GET['csrf'] ?? '')) { http_response_code(403); exit('درخواست نامعتبر است.'); }
    $hid = intval($_GET['delete_hafez']);
    $conn->query("DELETE FROM hafez_verses WHERE id=$hid");
    header('Location: '.$admin_self); exit;
}

// ── Order Status Update ──
if ($is_authenticated && isset($_GET['update_order'])) {
    $oid = intval($_GET['update_order']);
    $status = $_GET['status'] ?? 'pending';
    $allowed = ['pending','confirmed','shipped','delivered','cancelled'];
    if (in_array($status, $allowed)) {
        require_once 'smsir.php';
        $old = $conn->prepare("SELECT id, user_phone, total_price, status, items, stock_reserved FROM orders WHERE id=? LIMIT 1");
        $old->bind_param("i", $oid); $old->execute(); $order_before = $old->get_result()->fetch_assoc();
        $st = $conn->prepare("UPDATE orders SET status=? WHERE id=?");
        $st->bind_param("si", $status, $oid); $st->execute();
        // برای سفارش‌هایی که بدون درگاه ثبت می‌شوند: با تأیید ادمین موجودی کسر می‌شود.
        // سفارش‌های کارت‌به‌کارت/درگاه که موجودی‌شان از قبل رزرو (کسر) شده، دوباره کسر نمی‌شوند.
        if ($status === 'confirmed' && $order_before && $order_before['status'] !== 'confirmed' && (int)($order_before['stock_reserved'] ?? 0) !== 1) sitak_stock_consume($conn, $order_before['items']);
        // با لغو سفارش، موجودی رزرو شده آزاد می‌شود
        if ($status === 'cancelled' && $order_before && $order_before['status'] !== 'cancelled') sitak_stock_unreserve($conn, $order_before);
        $statusTemplates = ['confirmed'=>'sms_status_confirmed_template_id', 'delivered'=>'sms_status_delivered_template_id', 'cancelled'=>'sms_status_cancelled_template_id'];
        if ($order_before && isset($statusTemplates[$status]) && getSetting($conn, 'sms_notify_status', '1') === '1') {
            sitak_sms_send($order_before['user_phone'], $statusTemplates[$status], [
                'OrderId'=>$oid, 'OrderID'=>$oid, 'Status'=>$status,
                'Total'=>number_format((int)$order_before['total_price'])
            ]);
        }
    }
    header('Location: '.$admin_self); exit;
}

// ── کارت‌به‌کارت: تأیید یا رد اطلاعات واریز توسط مدیر ──
if ($is_authenticated && isset($_POST['card_decision'])) {
    require_once 'smsir.php';
    $oid = (int)($_POST['order_id'] ?? 0);
    $decision = (($_POST['decision'] ?? '') === 'approve') ? 'approve' : 'reject';
    $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $oid); $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    if ($order && ($order['payment_method'] ?? '') === 'card' && $order['status'] === 'pending' && !empty($order['card_submitted_at'])) {
        if ($decision === 'approve') {
            $up = $conn->prepare("UPDATE orders SET status='confirmed', card_status='approved', payment_paid_at=NOW() WHERE id=? AND status='pending'");
            $up->bind_param('i', $oid); $up->execute();
            if ($up->affected_rows > 0) {
                // اگر به هر دلیلی موجودی این سفارش رزرو نشده بود، هنگام تأیید کسر می‌شود
                if ((int)($order['stock_reserved'] ?? 0) !== 1) {
                    sitak_stock_consume($conn, $order['items']);
                    $conn->query("UPDATE orders SET stock_reserved=1 WHERE id=" . $oid);
                }
                // وفاداری: خرید کارت‌به‌کارت تأییدشده هم مثل خرید آنلاین ۱ امتیاز می‌گیرد
                if (getSetting($conn, 'loyalty_enabled', '1') === '1' && getSetting($conn, 'loyalty_online_order', '0') === '1') {
                    $lv = $conn->prepare("UPDATE users SET visits = visits + 1 WHERE phone = ?");
                    $lv->bind_param('s', $order['user_phone']); $lv->execute();
                }
                if (getSetting($conn, 'sms_notify_status', '1') === '1') {
                    sitak_sms_send($order['user_phone'], 'sms_status_confirmed_template_id', [
                        'OrderId' => $oid, 'OrderID' => $oid, 'Status' => 'confirmed',
                        'Total' => number_format((int)$order['total_price'])
                    ]);
                }
            }
        } else {
            $up = $conn->prepare("UPDATE orders SET status='cancelled', card_status='rejected' WHERE id=? AND status='pending'");
            $up->bind_param('i', $oid); $up->execute();
            if ($up->affected_rows > 0) {
                sitak_stock_unreserve($conn, $order);
                if (getSetting($conn, 'sms_notify_status', '1') === '1') {
                    sitak_sms_send($order['user_phone'], 'sms_status_cancelled_template_id', [
                        'OrderId' => $oid, 'OrderID' => $oid, 'Status' => 'cancelled',
                        'Total' => number_format((int)$order['total_price'])
                    ]);
                }
            }
        }
    }
    header('Location: '.$admin_self); exit;
}

/* ═══════════════════════════════════════════════════════════
   اجزای مشترک UI
   ═══════════════════════════════════════════════════════════ */
function admin_page_start($title){
    global $custom_favicon, $default_theme, $site_name;
    $dt = htmlspecialchars($default_theme, ENT_QUOTES, 'UTF-8');
    echo '<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="'.$dt.'">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="theme-color" content="#ffffff" id="metaTheme">
<meta name="robots" content="noindex, nofollow">
'.theme_head_script('sitak_admin_theme', $default_theme).'
'.($custom_favicon ? '<link rel="icon" href="'.htmlspecialchars($custom_favicon).'">' : '').'
<title>'.htmlspecialchars($title, ENT_QUOTES, 'UTF-8').' | '.htmlspecialchars($site_name ?? 'سای‌تک', ENT_QUOTES, 'UTF-8').'</title>
<link href="assets/fonts/vazirmatn.css" rel="stylesheet">
<style>
:root, [data-theme="light"] {
  --tbl-line:rgba(0,0,0,.12);
  --gold:#ecb534;--gold-dark:#c49625;--gold-glow:rgba(236,181,52,.12);--gold-subtle:rgba(236,181,52,.06);
  --bg:#f5f5f7;--surface:#ffffff;--card:#ffffff;--card-el:#f0eff2;--toast-bg:#ffffff;
  --border:rgba(255,255,255,.08);--border-gold:rgba(236,181,52,.22);
  --text:#1d1d1f;--text2:#6e6e73;--text3:#aeaeb2;
  --header-bg:rgba(255,255,255,.82);
  --green:#30d158;--red:#ff453a;--blue:#0a84ff;
  --r:18px;--rs:12px;--rss:10px;
  --safe-t:env(safe-area-inset-top,0px);--safe-b:env(safe-area-inset-bottom,0px);
  --shadow-sm:0 1px 3px rgba(0,0,0,.06);--shadow-md:0 4px 14px rgba(0,0,0,.06);
  --font:\'Vazirmatn\',-apple-system,BlinkMacSystemFont,sans-serif;
}
[data-theme="dark"] {
  --tbl-line:rgba(255,255,255,.08);
  --bg:#0a0a0c;--surface:#161618;--card:#1c1c1e;--card-el:#2c2c2e;--toast-bg:#2c2c2e;
  --border:rgba(255,255,255,.08);--border-gold:rgba(236,181,52,.15);
  --text:#f0ece4;--text2:#9e9888;--text3:#5e5a52;
  --header-bg:rgba(10,10,12,.88);
  --gold-glow:rgba(236,181,52,.12);--gold-subtle:rgba(236,181,52,.07);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent}
html{font-size:15px;-webkit-text-size-adjust:100%;overscroll-behavior:none}
body{font-family:var(--font);background:var(--bg);color:var(--text);min-height:100dvh;overflow-x:hidden;-webkit-font-smoothing:antialiased;padding-top:var(--safe-t);transition:background .3s,color .3s;font-feature-settings:"ss01"}
body::before{content:\'\';position:fixed;top:-30%;right:-15%;width:400px;height:400px;background:radial-gradient(circle,var(--gold-glow),transparent 70%);pointer-events:none;z-index:0}

.login-wrap{min-height:100dvh;display:flex;align-items:center;justify-content:center;padding:20px;position:relative;z-index:1}
.login-card{background:var(--card);border:1px solid var(--border-gold);border-radius:24px;padding:32px 24px;width:100%;max-width:380px;text-align:center;box-shadow:var(--shadow-md)}
.login-card h2{font-size:1.2rem;font-weight:700;color:var(--gold-dark);margin-bottom:6px}
.login-card .sub{font-size:.78rem;color:var(--text3);margin-bottom:20px}
.login-card .err{color:var(--red);font-size:.78rem;margin-bottom:12px}
.fi{width:100%;padding:13px 14px;background:var(--bg);border:1.5px solid var(--border);border-radius:var(--rss);color:var(--text);font-family:var(--font);font-size:.92rem;outline:none;transition:border-color .2s,background .3s;margin-bottom:12px}
.fi::placeholder{color:var(--text3)}.fi:focus{border-color:var(--gold)}
.btn-p{width:100%;padding:14px;background:linear-gradient(135deg,var(--gold),var(--gold-dark));color:#fff;border:none;border-radius:var(--rss);font-family:var(--font);font-size:.92rem;font-weight:700;cursor:pointer;transition:all .12s;}
.btn-p:active{}

.app-bar{position:sticky;top:0;z-index:50;display:flex;align-items:center;justify-content:space-between;padding:10px 16px;min-height:60px;background:var(--header-bg);backdrop-filter:blur(28px) saturate(180%);-webkit-backdrop-filter:blur(28px) saturate(180%);border-bottom:1px solid var(--border);transition:background .35s;border-bottom-left-radius:16px;border-bottom-right-radius:16px}
.ab-left{display:flex;align-items:center;gap:10px}
.ab-icon{width:36px;height:36px;background:linear-gradient(135deg,var(--gold),var(--gold-dark));border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1rem}
.ab-title{font-size:.95rem;font-weight:700}
.ab-right{display:flex;gap:8px;align-items:center}
.btn-sm{padding:8px 14px;border-radius:8px;font-family:var(--font);font-size:.75rem;font-weight:600;cursor:pointer;border:none;text-decoration:none;display:inline-flex;align-items:center;gap:4px;min-height:36px}
.btn-blue{background:var(--blue);color:#fff}.btn-red{background:var(--red);color:#fff}
.act-toggle{background:#ffd60a;color:#1c1c1e;text-decoration:none;font-size:.68rem}
.act-neutral{background:var(--card-el);color:var(--text2);border:1px solid var(--border);font-size:.68rem}
.tbl-act-status{font-size:1rem}
.btn-3d-red{border-radius:var(--rss);;transition:all .12s}
.btn-3d-red:active{}
.header-icon-btn{position:relative;width:36px;height:36px;border-radius:50%;border:1px solid var(--border);background:var(--card-el);display:flex;align-items:center;justify-content:center;font-size:.95rem;color:var(--text);text-decoration:none;cursor:pointer;transition:all .2s;flex-shrink:0}.header-icon-btn:active{transform:scale(.9)}
@media(max-width:400px){.ab-title{font-size:.82rem}.btn-sm{padding:6px 10px;font-size:.7rem}}

.container{position:relative;z-index:1;max-width:680px;margin:0 auto;padding:14px 14px 96px}
.sec-title{font-size:1rem;font-weight:700;margin:22px 0 10px;color:var(--text);display:flex;align-items:center;gap:8px}
.acc-hdr{background:var(--card);border:1px solid var(--border-gold);padding:13px 16px;border-radius:var(--rs);cursor:pointer;display:flex;justify-content:space-between;align-items:center;margin-top:18px;transition:background .2s,border-color .3s;user-select:none;box-shadow:var(--shadow-sm)}
.acc-hdr:hover{background:var(--card-el)}
.acc-hdr h3{font-size:.9rem;font-weight:700;color:var(--gold-dark);margin:0}
.acc-icon{color:var(--text3);font-size:1rem}
.acc-body{display:none;background:var(--card);border:1px solid var(--border-gold);border-top:none;border-radius:0 0 var(--rs) var(--rs);padding:16px;margin-bottom:14px;box-shadow:var(--shadow-sm);transition:background .3s,border-color .3s}
.acc-body.open{display:block}
.acc-hdr.open{border-bottom-left-radius:0;border-bottom-right-radius:0}

.fg{display:flex;flex-direction:column;margin-bottom:10px}
.fg label{font-size:.75rem;font-weight:600;color:var(--text2);margin-bottom:4px}
.fg input,.fg select{padding:11px 12px;background:var(--bg);border:1.5px solid var(--border);border-radius:var(--rss);color:var(--text);font-family:var(--font);font-size:.85rem;outline:none;transition:border-color .2s,background .3s}
.fg input:focus,.fg select:focus{border-color:var(--gold)}
.fg textarea{padding:11px 12px;background:var(--bg);border:1.5px solid var(--border);border-radius:var(--rss);color:var(--text);font-family:var(--font);font-size:.85rem;outline:none;transition:border-color .2s,background .3s;resize:vertical;min-height:80px;width:100%}
.pw-eye{position:absolute;left:6px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text3);line-height:1;padding:8px;display:flex;align-items:center;justify-content:center;min-width:38px;min-height:38px;z-index:2;opacity:.6;transition:opacity .2s,color .2s;pointer-events:auto}
.pw-eye:hover{opacity:1;color:var(--text)}
.pw-field{padding-left:48px!important}
.pw-eye svg{display:block;width:18px;height:18px;flex-shrink:0}
.fg textarea:focus{border-color:var(--gold)}
.fg select{cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'12\' height=\'12\' viewBox=\'0 0 12 12\'%3E%3Cpath d=\'M6 8L1 3h10z\' fill=\'%236e6e73\'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:left 12px center}
.f-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px}

.toggle-row{display:flex;align-items:center;gap:8px;margin-bottom:12px}
.toggle{position:relative;width:44px;height:24px;flex-shrink:0}
.toggle input{display:none}
.toggle .slider{position:absolute;inset:0;background:#d1d1d6;border-radius:12px;cursor:pointer;transition:background .25s}
.toggle .slider::before{content:\'\';position:absolute;width:18px;height:18px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:transform .25s;box-shadow:0 1px 3px rgba(0,0,0,.15)}
.toggle input:checked+.slider{background:var(--gold)}
.toggle input:checked+.slider::before{transform:translateX(20px)}
.toggle-label{font-size:.82rem;font-weight:500;cursor:pointer}

.tbl-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch;margin-top:12px;border-radius:var(--rs);border:1px solid var(--tbl-line);background:var(--card);transition:background .3s,border-color .3s}
table{width:100%;border-collapse:collapse;min-width:500px}
th{padding:10px 12px;background:var(--card-el);font-size:.72rem;font-weight:700;color:var(--text2);text-align:center;cursor:pointer;white-space:nowrap;border-bottom:1px solid var(--tbl-line);transition:background .3s,color .3s,border-color .3s}
td{padding:10px 12px;font-size:.78rem;border-bottom:1px solid var(--tbl-line);white-space:nowrap;text-align:center}
tr:last-child td{border:none}
tr:hover td{background:rgba(236,181,52,.03)}

.tick{cursor:pointer;font-size:.85rem;font-weight:700;border:2px solid #d1d1d6;padding:3px 10px;border-radius:8px;display:inline-block;transition:all .2s;color:#d1d1d6;background:var(--bg)}
.tick.on{color:#fff;background:var(--green);border-color:var(--green)}

.modal-ov{display:none;position:fixed;inset:0;background:rgba(0,0,0,.3);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);z-index:100;align-items:center;justify-content:center;padding:16px}
.modal-ov.open{display:flex}
.modal-body{background:var(--surface);border:1px solid var(--border-gold);border-radius:22px;width:100%;max-width:540px;max-height:82vh;position:relative;box-shadow:0 20px 60px rgba(0,0,0,.1);overflow:hidden;display:flex;flex-direction:column;padding:0}
.modal-scroll{flex:1;min-height:0;overflow-y:auto;padding:22px;-webkit-overflow-scrolling:touch;scrollbar-width:thin;scrollbar-color:var(--border) transparent}
.modal-scroll::-webkit-scrollbar{width:8px}
.modal-scroll::-webkit-scrollbar-track{background:transparent}
.modal-scroll::-webkit-scrollbar-thumb{background:var(--border);border-radius:999px}
.close-x{position:absolute;top:12px;left:12px;border:none;background:none;font-size:1.3rem;cursor:pointer;color:var(--text3);z-index:2}
.modal-body h3,.modal-scroll h3{color:var(--gold-dark);font-size:1rem;font-weight:700;margin-bottom:14px;padding-bottom:10px;border-bottom:1px solid var(--border)}

.stat-box{background:var(--bg);border:1px solid var(--border);border-radius:var(--rss);padding:14px;margin-bottom:18px}
.stat-box h4{color:var(--gold-dark);font-size:.85rem;margin-bottom:10px}
.stat-row{display:flex;gap:8px;align-items:center}
.stat-input{text-align:center;flex:1;min-width:54px;font-weight:700;border:1px solid var(--border);padding:5px;border-radius:8px;background:var(--card);color:var(--text);font-family:var(--font);font-size:.85rem;-moz-appearance:textfield;appearance:textfield}
.stat-input::-webkit-outer-spin-button,.stat-input::-webkit-inner-spin-button{-webkit-appearance:none;margin:0}
.stat-row .stat-input{text-align:center!important;direction:ltr;unicode-bidi:plaintext}
.stat-row .stat-input:focus{text-align:center!important}
.btn-h{padding:3px 10px;font-size:.82rem;border-radius:6px;cursor:pointer;font-family:var(--font);font-weight:700;border:none}
.btn-h.minus{background:var(--red);color:#fff}.btn-h.plus{background:var(--gold);color:#fff}

.win-card{background:var(--bg);border:1px solid var(--border);border-radius:var(--rss);padding:10px 12px;margin-bottom:7px;display:flex;justify-content:space-between;align-items:center;font-size:.82rem}
.win-card .tick{font-size:.72rem;padding:3px 8px;min-width:100px;text-align:center}

.modal-acc-hdr{background:var(--bg);border:1px solid var(--border);border-radius:var(--rss);padding:10px 12px;cursor:pointer;display:flex;justify-content:space-between;align-items:center;user-select:none;font-size:.85rem;font-weight:700;color:var(--gold-dark);transition:background .2s}
.modal-acc-hdr:hover{background:var(--card-el)}
.modal-acc-hdr.open{border-bottom-left-radius:0;border-bottom-right-radius:0}
.modal-acc-icon{color:var(--text3);font-size:.95rem;font-weight:700}
.modal-acc-body{display:none;background:var(--bg);border:1px solid var(--border);border-top:none;border-radius:0 0 var(--rss) var(--rss);padding:14px;margin:0 0 14px}
.modal-acc-body.open{display:block}
/* در تم روشن، متغیر --border سفید نیمه‌شفاف است و روی پس‌زمینه‌ی روشن دیده نمی‌شود؛ فقط داخل پنجره‌ی مدیریت همسفر تیره‌تر می‌شود */
[data-theme="light"] #userMgmtBox,[data-theme="light"] #modalContent{--border:rgba(0,0,0,.16)}
.order-row{font-size:.78rem;padding:6px 8px;background:var(--bg);border-radius:6px;border:1px solid var(--border);margin-bottom:5px}
.order-row-head{display:flex;justify-content:space-between;gap:8px;align-items:center}
.order-items{display:none;margin-top:6px;padding-top:6px;border-top:1px dashed var(--border)}
.order-items.open{display:block}
.order-item-line{font-size:.75rem;padding:4px 6px;background:var(--surface);border-radius:4px;margin-bottom:3px;border:1px solid var(--border)}
.btn-xs{padding:3px 8px;font-size:.68rem;border-radius:6px;cursor:pointer;font-family:var(--font);font-weight:600;border:1px solid var(--border);background:var(--card);color:var(--text2);min-height:28px;white-space:nowrap}
.btn-xs:hover{background:var(--card-el);color:var(--gold-dark)}

.search-row{display:flex;gap:8px;margin-bottom:20px}
.search-row .fi{margin-bottom:0;height:46px;padding:0 14px;box-sizing:border-box;flex:1}
.search-row .btn-p{width:auto;height:46px;padding:0 20px;margin-bottom:0;display:inline-flex;align-items:center;justify-content:center;box-sizing:border-box}

.msg-ok{background:rgba(48,209,88,.08);color:#1a8a3f;padding:10px 14px;border-radius:var(--rss);font-size:.82rem;font-weight:600;margin-bottom:12px;border:1px solid rgba(48,209,88,.2)}
.msg-err{background:rgba(255,69,58,.06);color:#d70015;padding:10px 14px;border-radius:var(--rss);font-size:.82rem;font-weight:600;margin-bottom:12px;border:1px solid rgba(255,69,58,.15)}

.toast{position:fixed;bottom:80px;left:50%;transform:translateX(-50%) translateY(100px);background:linear-gradient(135deg,var(--gold,#ecb534),var(--gold-dark,#c49625));border:none;color:#fff;padding:12px 20px;border-radius:14px;font-size:.82rem;z-index:500;opacity:0;transition:all .35s cubic-bezier(.34,1.56,.64,1);max-width:min(88%,420px);text-align:center;box-shadow:var(--shadow-md)}
.toast.show{transform:translateX(-50%) translateY(0);opacity:1}

.is-invalid{border-color:#ff453a!important;box-shadow:0 0 0 2px rgba(255,69,58,.35)!important}
[data-theme="light"] .app-bar{border-bottom-color:rgba(0,0,0,.12)}
[data-theme="light"] .admin-nav{border-top-color:rgba(0,0,0,.12)}
.admin-nav{position:fixed;bottom:0;left:0;right:0;z-index:90;background:var(--surface);border-top:1px solid var(--border);display:flex;justify-content:space-around;padding:8px 0;padding-bottom:calc(8px + var(--safe-b));box-shadow:0 -4px 20px rgba(0,0,0,.08);border-top-left-radius:16px;border-top-right-radius:16px}
.admin-nav a{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;color:var(--text2);text-decoration:none;font-size:.62rem;min-width:56px;min-height:44px;padding:4px 0;transition:color .2s;white-space:nowrap}
.admin-nav a.active{color:var(--gold-dark)}
.admin-nav svg{width:22px;height:22px;transition:transform .2s}
.admin-nav a.active svg{color:var(--gold-dark);transform:scale(1.1)}
@media(min-width:481px){
  html{scrollbar-gutter:stable}
  html,body{max-width:480px;margin:0 auto}
  body{box-shadow:0 0 0 1px var(--border),0 0 50px rgba(0,0,0,.1)}
  .admin-nav{left:50%;right:auto;transform:translateX(-50%);width:480px}
  .modal-ov{left:50%;right:auto;transform:translateX(-50%);width:480px}
  .modal-body{max-width:100%}
}
</style>
</head>
<body>';
}

function admin_login_screen($login_error = null){
    global $custom_favicon, $default_theme, $site_name;
    $dt = htmlspecialchars($default_theme ?? 'auto', ENT_QUOTES, 'UTF-8');
    echo '<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="'.$dt.'">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<meta name="theme-color" content="#ffffff" id="metaTheme">
'.theme_head_script('sitak_admin_theme', $default_theme ?? 'auto').'
'.(!empty($custom_favicon) ? '<link rel="icon" href="'.htmlspecialchars($custom_favicon, ENT_QUOTES, 'UTF-8').'">' : '').'
<title>ورود | '.htmlspecialchars($site_name ?? 'سای‌تک', ENT_QUOTES, 'UTF-8').'</title>
<link href="assets/fonts/vazirmatn.css" rel="stylesheet">
<style>
:root,[data-theme="light"]{--tbl-line:rgba(0,0,0,.12);--gold:#ecb534;--gold-dark:#c49625;--bg:#f5f5f7;--surface:#fff;--card:#fff;--card-el:#f0eff2;--border:rgba(255,255,255,.08);--border-gold:rgba(236,181,52,.22);--text:#1d1d1f;--text3:#aeaeb2;--red:#ff453a;--rss:10px;--font:"Vazirmatn",-apple-system,BlinkMacSystemFont,sans-serif}
[data-theme="dark"]{--tbl-line:rgba(255,255,255,.08);--bg:#0a0a0c;--surface:#161618;--card:#1c1c1e;--card-el:#2c2c2e;--toast-bg:#2c2c2e;--border:rgba(255,255,255,.08);--border-gold:rgba(236,181,52,.15);--text:#f0ece4;--text3:#5e5a52;--red:#f87171}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:var(--font);background:var(--bg);color:var(--text);min-height:100dvh;-webkit-font-smoothing:antialiased}
.login-wrap{min-height:100dvh;display:flex;align-items:center;justify-content:center;padding:20px}
.login-card{background:var(--card);border:1px solid var(--border-gold);border-radius:24px;padding:32px 24px;width:100%;max-width:380px;text-align:center;box-shadow:0 4px 14px rgba(0,0,0,.06)}
.login-card h2{font-size:1.2rem;font-weight:700;color:var(--gold-dark);margin-bottom:6px}
.login-card .sub{font-size:.78rem;color:var(--text3);margin-bottom:20px}
.login-card .err{color:var(--red);font-size:.78rem;margin-bottom:12px}
.fi{width:100%;padding:13px 14px;background:var(--bg);border:1.5px solid var(--border);border-radius:var(--rss);color:var(--text);font-family:var(--font);font-size:.92rem;outline:none;margin-bottom:12px}
.fi::placeholder{color:var(--text3)}.fi:focus{border-color:var(--gold)}
.btn-p{width:100%;padding:14px;background:linear-gradient(135deg,var(--gold),var(--gold-dark));color:#fff;border:none;border-radius:var(--rss);font-family:var(--font);font-size:.92rem;font-weight:700;cursor:pointer;}
.btn-p:active{}
@media(min-width:481px){html,body{max-width:480px;margin:0 auto}body{box-shadow:0 0 0 1px var(--border),0 0 50px rgba(0,0,0,.1)}}
</style>
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <h2>ورود به درگاه مدیریت</h2>
    <p class="sub">'.htmlspecialchars($site_name ?? 'سای‌تک', ENT_QUOTES, 'UTF-8').' — مدیریت</p>
    '.(isset($login_error) ? '<p class="err">'.htmlspecialchars($login_error, ENT_QUOTES, 'UTF-8').'</p>' : '').'
    <form method="post">
      <div style="position:relative">
        <input type="password" class="fi" id="adminLoginPass" name="password" placeholder="گذرواژه ادمین" required style="padding-left:44px">
        <button type="button" onclick="adminLoginTogglePw()" aria-label="نمایش گذرواژه" style="position:absolute;left:0;top:0;height:calc(100% - 12px);background:none;border:none;cursor:pointer;color:#86868b;padding:0 12px;display:flex;align-items:center;justify-content:center">
          <svg id="pwEye" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
          <svg id="pwOff" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 19c-6.5 0-10-7-10-7a18.5 18.5 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c6.5 0 10 7 10 7a18.5 18.5 0 0 1-2.16 3.19M1 1l22 22"/></svg>
        </button>
      </div>
      <script>function adminLoginTogglePw(){var i=document.getElementById("adminLoginPass");var t=i.type==="text";i.type=t?"password":"text";document.getElementById("pwEye").style.display=t?"":"none";document.getElementById("pwOff").style.display=t?"none":"";}</script>
      <button type="submit" name="admin_login" class="btn-p">ورود</button>
    </form>
  </div>
</div>
</body>
</html>';
}

// ── صفحه‌بندی لیست‌های پنل: تعداد در صفحه (۱۰/۲۰/۵۰/۱۰۰، پیش‌فرض ۱۰) و شماره‌ی صفحه ──
function admin_list_page($key) {
    $pp = (int)($_GET[$key.'_pp'] ?? 10);
    if (!in_array($pp, [10, 20, 50, 100], true)) $pp = 10;
    $pg = max(1, (int)($_GET[$key.'_pg'] ?? 1));
    return [$pp, $pg];
}
function admin_pager($key, $sec, $total, $pp, $pg) {
    global $admin_self;
    $e = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
    $pages = max(1, (int)ceil($total / $pp));
    $pg = min($pg, $pages);
    $base = $_GET; unset($base[$key.'_pg'], $base[$key.'_pp']);
    if ($sec !== null && $sec !== '') $base['sec'] = $sec;
    $script = $admin_self ?? 'admin.php';
    $url = function ($p, $size) use ($base, $key, $script) {
        $q = $base; $q[$key.'_pg'] = $p; $q[$key.'_pp'] = $size;
        return $script.'?'.http_build_query($q);
    };
    $from = $total ? ($pg - 1) * $pp + 1 : 0;
    $to = min($total, $pg * $pp);
    $list = [];
    for ($i = 1; $i <= $pages; $i++) { if ($i == 1 || $i == $pages || abs($i - $pg) <= 1) $list[] = $i; }
    $h = '<nav class="pager" aria-label="صفحه‌بندی">';
    $h .= '<div class="pg-info">'.pn($from).'–'.pn($to).' از '.pn($total).' مورد</div>';
    $h .= '<div class="pg-nav">';
    $h .= $pg > 1 ? '<a class="pg-btn pg-arrow" href="'.$e($url($pg - 1, $pp)).'" title="صفحه‌ی قبلی">‹ قبلی</a>' : '<span class="pg-btn pg-arrow pg-dis">‹ قبلی</span>';
    $prev = null;
    foreach ($list as $p) {
        if ($prev !== null && $p - $prev > 1) $h .= '<span class="pg-dots">…</span>';
        $h .= $p === $pg ? '<span class="pg-btn pg-cur" aria-current="page">'.pn($p).'</span>' : '<a class="pg-btn" href="'.$e($url($p, $pp)).'">'.pn($p).'</a>';
        $prev = $p;
    }
    $h .= $pg < $pages ? '<a class="pg-btn pg-arrow" href="'.$e($url($pg + 1, $pp)).'" title="صفحه‌ی بعدی">بعدی ›</a>' : '<span class="pg-btn pg-arrow pg-dis">بعدی ›</span>';
    $h .= '</div>';
    $h .= '<label class="pg-size">هر صفحه <select onchange="location.href=this.value">';
    foreach ([10, 20, 50, 100] as $opt) $h .= '<option value="'.$e($url(1, $opt)).'"'.($opt === $pp ? ' selected' : '').'>'.pn($opt).'</option>';
    $h .= '</select></label>';
    $h .= '</nav>';
    return $h;
}

function admin_header(){
    global $custom_logo, $admin_self, $site_name, $conn, $custom_emoji;
    $ab_emoji_html = (getSetting($conn, 'show_header_emoji', '1') === '1') ? '<span style="margin-left:6px">'.htmlspecialchars($custom_emoji ?? '🌐', ENT_QUOTES, 'UTF-8').'</span>' : '';
    echo '<header class="app-bar">
<div class="ab-left">
  '.($custom_logo ? '<img src="'.htmlspecialchars($custom_logo).'" style="height:36px; max-width:120px; object-fit:contain; border-radius:10px;">' : '<div class="ab-icon">🌐</div>').'
  <span class="ab-title">درگاه مدیریت '.htmlspecialchars($site_name ?? 'سای‌تک', ENT_QUOTES, 'UTF-8').'</span>'.$ab_emoji_html.'
</div>
<div class="ab-right">
  '.(getSetting($conn, 'admin_notify_enabled', '1') === '1'
      ? '<button class="header-icon-btn" id="adminBellBtn" onclick="adminNotifyBell()" title="اعلان‌های مدیر"><span id="adminBellIcon">🔔</span><span id="adminBellDot" style="display:none;position:absolute;top:5px;left:5px;width:8px;height:8px;border-radius:50%;background:#30d158;border:1.5px solid var(--card-el)"></span></button>'
      : '<button class="header-icon-btn" id="adminBellBtn" onclick="adminNotifyBell()" title="اعلان‌های مدیر — از بخش «اعلان‌های مدیر» تنظیمات روشن می‌شود" style="opacity:.55"><span id="adminBellIcon">🔕</span><span id="adminBellDot" style="display:none;position:absolute;top:5px;left:5px;width:8px;height:8px;border-radius:50%;background:#30d158;border:1.5px solid var(--card-el)"></span></button>').'
  <button class="header-icon-btn" onclick="toggleAdminTheme()" aria-label="تغییر تم"><span id="adminThemeIcon">🌙</span></button>
  <a href="index.php" target="_blank" class="header-icon-btn" title="سایت">🌐</a>
  <a href="'.$admin_self.'?logout=true" onclick="return adminConfirmEl(this,\'آیا می‌خواهید از درگاه مدیریت خارج شوید؟\',\'🚪 خروج از درگاه مدیریت\',false,\'بله، خارج شوم\')" class="header-icon-btn" title="خروج" style="color:#ff453a"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></a>
</div>
</header>';
    admin_logout_sheet();
    admin_notify_ui();
    echo '<style>
.pager{display:flex;flex-wrap:wrap;gap:10px 14px;align-items:center;justify-content:space-between;margin:0;padding:10px 12px;background:var(--card-el);border:1px solid var(--border);border-top:1px solid var(--border);border-radius:0 0 var(--rs) var(--rs);font-size:.76rem;color:var(--text2);transition:background .3s,border-color .3s}
.tbl-wrap:has(+ .pager){margin-bottom:0;border-bottom:none;border-radius:var(--rs) var(--rs) 0 0}
.pg-info{white-space:nowrap;font-weight:600}
.pg-nav{display:flex;flex-wrap:wrap;gap:6px;align-items:center;justify-content:center}
.pg-btn{display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:34px;padding:0 10px;border-radius:10px;border:1px solid var(--border);background:var(--card);color:var(--text);text-decoration:none;font-weight:600;transition:background .2s,border-color .2s}
a.pg-btn:hover{border-color:var(--gold);background:var(--gold-subtle)}
.pg-arrow{min-width:0}
.pg-cur{background:linear-gradient(135deg,var(--gold),var(--gold-dark));color:#fff;border-color:transparent;box-shadow:0 2px 8px rgba(236,181,52,.25)}
.pg-dis{opacity:.4;cursor:default}
.pg-dots{color:var(--text3);padding:0 4px}
.pg-size{display:inline-flex;align-items:center;gap:8px;white-space:nowrap}
.pg-size select{height:34px;padding:0 10px;border-radius:10px;border:1px solid var(--border);background:var(--card);color:var(--text);font-family:inherit;font-size:.76rem}
@media (max-width:560px){.pager{justify-content:center;text-align:center}.pg-info,.pg-size{width:100%;justify-content:center}}
</style>';
}

// ── اعلان‌های مدیر: polls رویدادهای جدید + بوق + فعال‌سازی Web Push ──
function admin_notify_ui(){
    global $conn;
    $an_master = getSetting($conn, 'admin_notify_enabled', '1') === '1';
    $an_sound = getSetting($conn, 'admin_notify_sound', '1') === '1';
    $an_display = (string)getSetting($conn, 'admin_notify_display', 'popup');
    if (!in_array($an_display, ['popup', 'toast', 'none'], true)) $an_display = 'popup';
    [$vapid_pub] = sitak_vapid_keys($conn);
    $cfg = json_encode([
        'vapid' => (string)$vapid_pub,
        'csrf'  => sitak_csrf_token(),
        'master' => $an_master ? '1' : '0',
        'sound' => $an_sound ? '1' : '0',
        'display' => $an_display,
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
    echo '
<style>
.an-list{display:flex;flex-direction:column;gap:8px;max-height:46vh;overflow-y:auto;margin-bottom:16px;-webkit-overflow-scrolling:touch}
.an-item{background:var(--card-el,#f0eff2);border:1px solid var(--border,rgba(0,0,0,.06));border-radius:12px;padding:10px 12px}
.an-item b{display:block;font-size:.85rem;color:var(--text,#1d1d1f);margin-bottom:3px;line-height:1.7}
.an-item span{font-size:.75rem;color:var(--text2,#6e6e73);line-height:1.7;display:block}
.an-item a{display:inline-block;margin-top:6px;font-size:.72rem;font-weight:700;color:var(--gold-dark,#c49625);text-decoration:none}
.an-empty{font-size:.8rem;color:var(--text3,#aeaeb2);text-align:center;padding:10px 0}
.an-mg-btns{display:flex;flex-direction:column;gap:10px}
.an-mg-btns .adm-lo-ok,.an-mg-btns .adm-lo-cancel{width:100%;display:flex;align-items:center;justify-content:center;padding:14px;border-radius:12px;font-family:var(--font);font-size:.92rem;font-weight:700;cursor:pointer;border:none;text-decoration:none;box-sizing:border-box}
</style>
<div class="adm-lo-ov" id="anOv"></div>
<div class="adm-lo-sheet" id="anSheet">
  <div class="adm-lo-handle"></div>
  <div class="adm-lo-title">🔔 اعلان‌های درگاه</div>
  <div class="an-list" id="anList"></div>
  <div class="adm-lo-btns"><button type="button" class="adm-lo-ok" onclick="anClose()">بستن</button></div>
</div>
<div class="adm-lo-ov" id="anMgOv"></div>
<div class="adm-lo-sheet" id="anMgSheet">
  <div class="adm-lo-handle"></div>
  <div class="adm-lo-title">🔔 مدیریت اعلان‌ها</div>
  <p class="adm-lo-text" id="anMgText" style="margin-bottom:14px"></p>
  <div class="an-mg-btns">
    <button type="button" class="adm-lo-ok" id="anMgEnable" onclick="adminNotifySetup()">فعال‌سازی اعلان مرورگر</button>
    <button type="button" class="adm-lo-cancel" id="anMgDisable" onclick="adminNotifyDisable()">غیرفعال کردن اعلان مرورگر</button>
    <button type="button" class="adm-lo-cancel" id="anMgRecent" onclick="anShowRecent()">مشاهده‌ی اعلان‌های اخیر</button>
    <a href="admin-settings.php" class="adm-lo-cancel" id="anMgSettings">تنظیمات اعلان‌ها ⚙️</a>
    <button type="button" class="adm-lo-cancel" onclick="anMgClose()">بستن</button>
  </div>
</div>
<script>
var ADM_NOTIFY_CFG = ' . $cfg . ';
var anEvents = [];
function anOpen(){var o=document.getElementById("anOv"),s=document.getElementById("anSheet");if(o)o.classList.add("open");if(s)s.classList.add("open")}
function anClose(){var o=document.getElementById("anOv"),s=document.getElementById("anSheet");if(o)o.classList.remove("open");if(s)s.classList.remove("open")}
function anRender(){
  var list=document.getElementById("anList");if(!list)return;
  list.textContent="";
  if(!anEvents.length){var p=document.createElement("div");p.className="an-empty";p.textContent="اعلان جدیدی وجود ندارد";list.appendChild(p);return}
  anEvents.forEach(function(ev){
    var d=document.createElement("div");d.className="an-item";
    var b=document.createElement("b");b.textContent=(ev.time?ev.time+" — ":"")+ev.title;d.appendChild(b);
    if(ev.body){var sp=document.createElement("span");sp.textContent=ev.body;d.appendChild(sp)}
    if(ev.url){var a=document.createElement("a");a.href=ev.url;a.textContent="مشاهده ←";d.appendChild(a)}
    list.appendChild(d);
  });
}
function anPush(ev){
  try{ev.time=new Date().toLocaleTimeString("fa-IR",{hour:"2-digit",minute:"2-digit"})}catch(e){ev.time=""}
  anEvents.unshift(ev);if(anEvents.length>30)anEvents.pop();
  anRender();anOpen();
}
function anShowRecent(){
  anMgClose();
  fetch(location.pathname+"?action=admin_events_since&after=0",{cache:"no-store",credentials:"same-origin"})
    .then(function(r){return r.json()})
    .then(function(d){
      if(d&&d.success&&d.events&&d.events.length){
        anEvents=d.events.slice().reverse().map(function(ev){ev.time="";return ev});
      }else{
        anEvents=[];
      }
      anRender();anOpen();
    }).catch(function(){anRender();anOpen()});
}
function anMgClose(){var o=document.getElementById("anMgOv"),s=document.getElementById("anMgSheet");if(o)o.classList.remove("open");if(s)s.classList.remove("open")}
function anMgShow(state){
  var t=document.getElementById("anMgText"),en=document.getElementById("anMgEnable"),dis=document.getElementById("anMgDisable");
  var texts={
    off:"اعلان‌ها از بخش «۱۱. اعلان‌های مدیر» در تنظیمات خاموش است. ابتدا کلید اصلی را روشن کن.",
    unsupported:"این مرورگر از اعلان پشتیبانی نمی‌کند. اعلان‌های داخل پنل (صدا و پیام) همچنان کار می‌کنند.",
    nohttps:"اعلان مرورگری فقط روی HTTPS کار می‌کند. اعلان‌های داخل پنل فعال هستند.",
    denied:"مجوز اعلان در این مرورگر رد شده است. برای فعال‌سازی دوباره، از تنظیمات مرورگر اجازه‌ی اعلان این سایت را باز کن.",
    on:"اعلان مرورگر فعال است — حتی با بسته بودن پنل، رویدادهای جدید اطلاع داده می‌شود."
  };
  if(t)t.textContent=texts[state]||"";
  if(en)en.style.display=(state==="on")?"none":"flex";
  if(dis)dis.style.display=(state==="on")?"flex":"none";
  var o=document.getElementById("anMgOv"),s=document.getElementById("anMgSheet");
  if(o)o.classList.add("open");if(s)s.classList.add("open");
}
function adminNotifyBell(){
  if(ADM_NOTIFY_CFG.master!=="1"){anMgShow("off");return}
  if(!("serviceWorker" in navigator)||!("PushManager" in window)||!("Notification" in window)){anMgShow("unsupported");return}
  if(location.protocol!=="https:"&&location.hostname!=="localhost"&&location.hostname!=="127.0.0.1"){anMgShow("nohttps");return}
  if(Notification.permission==="denied"){anMgShow("denied");return}
  navigator.serviceWorker.getRegistration().then(function(reg){
    if(reg&&reg.pushManager)return reg.pushManager.getSubscription();
  }).then(function(sub){
    if(sub)anMgShow("on");else adminNotifySetup();
  }).catch(function(){adminNotifySetup()});
}
async function adminNotifyDisable(){
  try{
    var reg=await navigator.serviceWorker.getRegistration();
    if(reg&&reg.pushManager){
      var sub=await reg.pushManager.getSubscription();
      if(sub){
        var fd=new FormData();
        fd.append("push_unsubscribe","1");
        fd.append("subscription",JSON.stringify({endpoint:sub.endpoint}));
        fd.append("csrf_token",ADM_NOTIFY_CFG.csrf);
        await fetch(location.pathname,{method:"POST",body:fd,credentials:"same-origin"});
        try{await sub.unsubscribe()}catch(e){}
      }
    }
    if(typeof showToast==="function")showToast("اعلان مرورگر غیرفعال شد");
    anMgClose();adminBellRefresh();
  }catch(e){if(typeof showToast==="function")showToast("غیرفعال‌سازی ناموفق بود")}
}
(function(){
  var LS_KEY="sitak_admin_last_event";
  if(!document.getElementById("toast")){
    try{var _td=document.createElement("div");_td.className="toast";_td.id="toast";(document.body||document.documentElement).appendChild(_td)}catch(e){}
  }
  if(typeof window.showToast!=="function"){
    window.showToast=function(m){var t=document.getElementById("toast");if(!t)return;t.textContent=m;t.classList.add("show");clearTimeout(t._timer);t._timer=setTimeout(function(){t.classList.remove("show")},3500)};
  }
  if(ADM_NOTIFY_CFG.master!=="1"){anRender();return}
  var ctx=null;
  function lastId(){try{return parseInt(localStorage.getItem(LS_KEY)||"0",10)||0}catch(e){return 0}}
  function setLastId(v){try{localStorage.setItem(LS_KEY,String(v))}catch(e){}}
  function ensureAudio(){
    if(ctx){if(ctx.state==="suspended")ctx.resume();return ctx}
    try{var C=window.AudioContext||window.webkitAudioContext;if(!C)return null;ctx=new C();if(ctx.state==="suspended")ctx.resume();return ctx}catch(e){return null}
  }
  document.addEventListener("pointerdown",ensureAudio,{once:true});
  function beep(){
    if(ADM_NOTIFY_CFG.sound!=="1")return;
    var c=ensureAudio();if(!c)return;
    try{
      var t=c.currentTime;
      function tone(f,st,dur){
        var o=c.createOscillator(),g=c.createGain();
        o.type="sine";o.frequency.value=f;
        g.gain.setValueAtTime(0.0001,t+st);
        g.gain.exponentialRampToValueAtTime(0.22,t+st+0.02);
        g.gain.exponentialRampToValueAtTime(0.0001,t+st+dur);
        o.connect(g);g.connect(c.destination);
        o.start(t+st);o.stop(t+st+dur+0.05);
      }
      tone(880,0,0.16);tone(1174.7,0.18,0.24);
    }catch(e){}
  }
  function eventsUrl(after,mark){
    return location.pathname+"?action=admin_events_since&after="+encodeURIComponent(after)+(mark?"&mark=1":"");
  }
  function announce(ev){
    var mode=ADM_NOTIFY_CFG.display;
    if(mode==="popup")anPush(ev);
    else if(mode==="toast")showToast("🔔 "+ev.title+(ev.body?" — "+ev.body:""));
  }
  function poll(markOnly){
    fetch(eventsUrl(markOnly?0:lastId(),markOnly),{cache:"no-store",credentials:"same-origin"})
      .then(function(r){return r.json()})
      .then(function(d){
        if(!d||!d.success)return;
        var prev=lastId();
        if(typeof d.last_id==="number"&&d.last_id>prev)setLastId(d.last_id);
        if(markOnly)return;
        var fresh=(d.events||[]).filter(function(ev){return +ev.id>prev});
        if(fresh.length){
          beep();
          fresh.slice(-3).forEach(announce);
        }
      }).catch(function(){});
  }
  anRender();
  if(!localStorage.getItem(LS_KEY)){
    poll(true);
    setTimeout(function(){poll(false)},4000);
  }else{
    setTimeout(function(){poll(false)},1500);
  }
  setInterval(function(){poll(false)},25000);
  window.adminNotifyPoll=function(){poll(false)};
})();
function urlB64ToUint8(b64){
  var pad="=".repeat((4-b64.length%4)%4);
  var s=(b64+pad).replace(/-/g,"+").replace(/_/g,"/");
  var raw=atob(s);
  var arr=new Uint8Array(raw.length);
  for(var i=0;i<raw.length;i++)arr[i]=raw.charCodeAt(i);
  return arr;
}
function adminBellRefresh(){
  var icon=document.getElementById("adminBellIcon"),dot=document.getElementById("adminBellDot"),btn=document.getElementById("adminBellBtn");
  if(!icon||!dot||!btn)return;
  function set(emoji,muted,hasDot){icon.textContent=emoji;btn.style.opacity=muted?".55":"";dot.style.display=hasDot?"block":"none"}
  if(ADM_NOTIFY_CFG.master!=="1"){set("🔕",true,false);return}
  if(!("serviceWorker" in navigator)||!("PushManager" in window)||!("Notification" in window)){set("🔔",false,false);return}
  if(Notification.permission==="denied"){set("🔕",false,false);return}
  try{
    navigator.serviceWorker.getRegistration().then(function(reg){
      if(reg&&reg.pushManager)return reg.pushManager.getSubscription();
    }).then(function(sub){set("🔔",false,!!sub)}).catch(function(){set("🔔",false,false)});
  }catch(e){set("🔔",false,false)}
}
async function adminNotifySetup(){
  if(!("serviceWorker" in navigator)||!("PushManager" in window)||!("Notification" in window)){
    anMgShow("unsupported");return;
  }
  if(location.protocol!=="https:"&&location.hostname!=="localhost"&&location.hostname!=="127.0.0.1"){
    anMgShow("nohttps");return;
  }
  try{
    var perm=await Notification.requestPermission();
    if(perm!=="granted"){if(perm==="denied")anMgShow("denied");else if(typeof showToast==="function")showToast("اجازه‌ی اعلان داده نشد");adminBellRefresh();return}
    if(!ADM_NOTIFY_CFG.vapid){if(typeof showToast==="function")showToast("کلید اعلان روی سرور ساخته نشد؛ دوباره تلاش کنید");return}
    var reg=await navigator.serviceWorker.register("sw.js");
    await navigator.serviceWorker.ready;
    var sub=await reg.pushManager.subscribe({userVisibleOnly:true,applicationServerKey:urlB64ToUint8(ADM_NOTIFY_CFG.vapid)});
    var fd=new FormData();
    fd.append("push_subscribe","1");
    fd.append("subscription",JSON.stringify(sub));
    fd.append("csrf_token",ADM_NOTIFY_CFG.csrf);
    var r=await fetch(location.pathname,{method:"POST",body:fd,credentials:"same-origin"});
    var d=await r.json();
    if(typeof showToast==="function")showToast(d&&d.success?"اعلان‌های مرورگر فعال شد 🔔 حتی با بسته بودن پنل":((d&&d.message)||"ثبت اعلان ناموفق بود"));
    anMgClose();
    adminBellRefresh();
  }catch(e){
    if(typeof showToast==="function")showToast("فعال‌سازی اعلان مرورگر ناموفق بود");
  }
}
adminBellRefresh();
</script>';
}

function admin_logout_sheet(){
    echo <<<'HTML'
<style>
.adm-lo-ov{position:fixed;inset:0;background:var(--overlay-bg,rgba(0,0,0,.3));backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);z-index:100;opacity:0;pointer-events:none;transition:opacity .3s}
.adm-lo-ov.open{opacity:1;pointer-events:auto}
.adm-lo-sheet{position:fixed;bottom:0;left:0;right:0;z-index:101;width:auto;max-width:100vw;box-sizing:border-box;overflow-x:hidden;background:var(--surface);border-radius:22px 22px 0 0;padding:0 18px 18px;padding-bottom:calc(18px + env(safe-area-inset-bottom,0px));max-height:82vh;overflow-y:auto;transform:translateY(100%);transition:transform .38s cubic-bezier(.32,.72,0,1),background .35s;-webkit-overflow-scrolling:touch;box-shadow:var(--sheet-shadow,0 -10px 40px rgba(0,0,0,.08))}
.adm-lo-sheet.open{transform:translateY(0)}
.adm-lo-sheet:not(.open){box-shadow:none}
.adm-lo-handle{width:34px;height:4px;background:#d1d1d6;border-radius:4px;margin:10px auto 18px}
.adm-lo-title{font-size:1.05rem;font-weight:700;text-align:center;margin-bottom:16px;color:var(--text)}
.adm-lo-text{font-size:.85rem;color:var(--text2);text-align:center;margin-bottom:18px;line-height:1.8;white-space:pre-line}
.adm-lo-sheet .fg{margin-bottom:13px}
.adm-lo-sheet textarea.fi{resize:vertical;min-height:110px}
.adm-lo-btns{display:flex;gap:10px}
.adm-lo-btns button{flex:1;display:flex;align-items:center;justify-content:center;padding:14px;border-radius:var(--rss);font-family:var(--font);font-size:.92rem;font-weight:700;cursor:pointer;border:none}
.adm-lo-cancel{background:var(--card-el);color:var(--text2)}
.adm-lo-ok{background:linear-gradient(135deg,var(--gold),var(--gold-dark));color:#fff}
.adm-lo-danger{background:#ff453a;color:#fff}
@media(min-width:481px){
  .adm-lo-ov{inset:auto;position:fixed;top:0;left:50%;transform:translateX(-50%);width:480px;height:100vh}
  .adm-lo-sheet{left:50%;right:auto;transform:translate(-50%,100%);width:480px;max-width:calc(100vw - 24px)}
  .adm-lo-sheet.open{transform:translate(-50%,0)}
}
</style>
<div class="adm-lo-ov" id="admSheetOv"></div>
<div class="adm-lo-sheet" id="admSheet" role="dialog" aria-modal="true">
  <div class="adm-lo-handle"></div>
  <div class="adm-lo-title" id="admSheetTitle"></div>
  <p class="adm-lo-text" id="admSheetMsg"></p>
  <div id="admSheetInputWrap" style="display:none">
    <input type="text" class="fi" id="admSheetInput" style="margin-bottom:14px">
    <textarea class="fi" id="admSheetTextarea" rows="5" style="display:none;margin-bottom:14px"></textarea>
  </div>
  <div class="adm-lo-btns">
    <button type="button" class="adm-lo-cancel" id="admSheetCancel">انصراف</button>
    <button type="button" class="adm-lo-ok" id="admSheetOk">تأیید</button>
  </div>
</div>
<script>
(function(){
var ov=document.getElementById('admSheetOv'),sh=document.getElementById('admSheet');
var ttl=document.getElementById('admSheetTitle'),msg=document.getElementById('admSheetMsg');
var wrap=document.getElementById('admSheetInputWrap'),inp=document.getElementById('admSheetInput'),ta=document.getElementById('admSheetTextarea');
var bCancel=document.getElementById('admSheetCancel'),bOk=document.getElementById('admSheetOk');
document.body.appendChild(ov);document.body.appendChild(sh);
var resolver=null,inputMode=false,frame=null;
function showSheet(){ov.classList.add('open');sh.classList.add('open');}
function hideSheet(){ov.classList.remove('open');sh.classList.remove('open');}
function settle(v){var r=resolver;resolver=null;hideSheet();if(r)r(v);}
function activeField(){return ta.style.display==='none'?inp:ta;}
bOk.onclick=function(){settle(inputMode?activeField().value:true);};
bCancel.onclick=function(){settle(inputMode?null:false);};
inp.addEventListener('keydown',function(e){if(e.key==='Enter'){e.preventDefault();bOk.onclick();}});
ov.onclick=function(){if(resolver){bCancel.onclick();}else if(frame){window.admCloseFrame(frame.id);}};
document.addEventListener('keydown',function(e){
  if(e.key!=='Escape')return;
  if(resolver){bCancel.onclick();}else if(frame){window.admCloseFrame(frame.id);}
});
window.adminConfirm=function(o){
  o=(typeof o==='string')?{message:o}:(o||{});
  return new Promise(function(res){
    resolver=res;inputMode=false;
    ttl.textContent=o.title||'تأیید';
    msg.textContent=o.message||'';msg.style.display=o.message?'':'none';
    wrap.style.display='none';
    bCancel.style.display=o.alertOnly?'none':'';
    bCancel.textContent=o.cancelText||'انصراف';
    bOk.textContent=o.okText||'تأیید';
    bOk.className='adm-lo-ok';
    showSheet();
  });
};
window.adminAlert=function(message,title){
  return window.adminConfirm({message:message,title:title||'توجه',alertOnly:true,okText:'باشه'});
};
window.adminPrompt=function(o){
  o=o||{};
  return new Promise(function(res){
    resolver=res;inputMode=true;
    ttl.textContent=o.title||'ورود اطلاعات';
    msg.textContent=o.message||'';msg.style.display=o.message?'':'none';
    wrap.style.display='';
    inp.style.display=o.multiline?'none':'';
    ta.style.display=o.multiline?'':'none';
    var field=activeField();
    field.value=o.defaultValue||'';field.placeholder=o.placeholder||'';
    bCancel.style.display='';bCancel.textContent='انصراف';
    bOk.textContent=o.okText||'تأیید';
    bOk.className='adm-lo-ok';
    showSheet();
    setTimeout(function(){field.focus();},360);
  });
};
window.adminConfirmEl=function(el,message,title,danger,okText){
  window.adminConfirm({message:message,title:title||'تأیید',danger:danger!==false,okText:okText||'بله، ادامه بده'}).then(function(ok){
    if(!ok)return;
    if(el.tagName==='A'){location.href=el.href;}
    else if(el.form){el.form.requestSubmit(el);}
  });
  return false;
};
window.adminConfirmForm=function(form,message,title){
  if(form.__admOk){form.__admOk=false;return true;}
  window.adminConfirm({message:message,title:title||'تأیید',danger:true,okText:'بله، ادامه بده'}).then(function(ok){
    if(ok){form.__admOk=true;form.requestSubmit();}
  });
  return false;
};
window.admOpenFrame=function(id){frame=document.getElementById(id);ov.classList.add('open');frame.classList.add('open');};
window.admCloseFrame=function(id){var el=document.getElementById(id);if(el)el.classList.remove('open');ov.classList.remove('open');frame=null;};
})();
</script>
HTML;
}

function admin_footer_nav($active){
    $items = [
        'home'   => [admin_url('home'), 'خانه', '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>'],
        'upload' => [admin_url('upload'), 'بارگذاری', '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>'],
        'settings' => [admin_url('settings'), 'تنظیمات', '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>'],
        'chance' => [admin_url('chance'), 'شانس', '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><path d="M8.5 8.5h.01"/><path d="M15.5 8.5h.01"/><path d="M12 12h.01"/><path d="M8.5 15.5h.01"/><path d="M15.5 15.5h.01"/></svg>'],
        'shop'   => [admin_url('shop'), 'فروشگاه', '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>'],
        'blog'   => [admin_url('blog'), 'مطالب', '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>'],
        'comments' => [admin_url('comments'), 'دیدگاه‌ها', '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 11.5a8 8 0 0 1-8 8H6l-4 3v-5.5a8 8 0 1 1 18-5.5Z"/><path d="M8 11h.01M12 11h.01M16 11h.01"/></svg>'],
        'stats'  => [admin_url('stats'), 'آمار', '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="6" y1="20" x2="6" y2="14"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="18" y1="20" x2="18" y2="10"/></svg>'],
    ];
    echo '<nav class="admin-nav">';
    foreach ($items as $key => $it) {
        echo '<a href="'.$it[0].'"'.($active === $key ? ' class="active"' : '').'>'.$it[2].'<span>'.$it[1].'</span></a>';
    }
    echo '</nav>';
}

function admin_common_js(){
    global $default_theme;
    echo 'const adminDefaultTheme = \''.htmlspecialchars($default_theme, ENT_QUOTES, 'UTF-8').'\';
(function(){
  const t = (window.__R || (v => v))(localStorage.getItem(\'sitak_admin_theme\') || adminDefaultTheme);
  document.documentElement.setAttribute(\'data-theme\', t);
  updateAdminThemeIcon(t);
})();
function toggleAdminTheme(){
  const cur = document.documentElement.getAttribute(\'data-theme\');
  const next = cur === \'dark\' ? \'light\' : \'dark\';
  document.documentElement.setAttribute(\'data-theme\', next);
  localStorage.setItem(\'sitak_admin_theme\', next);
  updateAdminThemeIcon(next);
}
function updateAdminThemeIcon(t){
  const icon = document.getElementById(\'adminThemeIcon\');
  if (icon) icon.textContent = t === \'dark\' ? \'☀️\' : \'🌙\';
  var mt=document.getElementById(\'metaTheme\');if(mt)mt.setAttribute(\'content\',t===\'dark\'?\'#0a0a0c\':\'#ffffff\');
}
function toggleAcc(el){
  const body=el.nextElementSibling,icon=el.querySelector(\'.acc-icon\');
  const isOpen=body.classList.toggle(\'open\');
  el.classList.toggle(\'open\', isOpen);
  icon.textContent=isOpen?\'−\':\'+\';
}
function toggleModalAcc(el){
  const body=el.nextElementSibling,icon=el.querySelector(\'.modal-acc-icon\');
  const isOpen=body.classList.toggle(\'open\');
  el.classList.toggle(\'open\',isOpen);
  icon.textContent=isOpen?\'−\':\'+\';
}
function stepNum(id,off){
  const el=document.getElementById(id)||document.querySelector(\'[name="\'+id+\'\"]\');
  if(!el) return;
  let v=parseFloat(String(el.value).replace(/[۰-۹]/g,d=>\'۰۱۲۳۴۵۶۷۸۹\'.indexOf(d))); if(isNaN(v)) v=0; v+=off;
  const mn=parseFloat(el.getAttribute(\'min\')),mx=parseFloat(el.getAttribute(\'max\'));
  if(!isNaN(mn)&&v<mn) v=mn; if(!isNaN(mx)&&v>mx) v=mx;
  el.value=String(Math.round(v*100)/100).replace(/\d/g,d=>\'۰۱۲۳۴۵۶۷۸۹\'[d]);
}
function toggleOrderItems(btn,oid){
  const box=document.getElementById(\'oi-\'+oid);
  const isOpen=box.classList.toggle(\'open\');
  btn.textContent=isOpen?\'بستن ✕\':\'جزئیات 🔍\';
}
function esc(s){if(!s)return\'\';return s.replace(/&/g,\'&amp;\').replace(/</g,\'&lt;\').replace(/>/g,\'&gt;\').replace(/"/g,\'&quot;\').replace(/\'/g,\'&#039;\')}
function showToast(m){const t=document.getElementById(\'toast\');t.textContent=m;t.classList.add(\'show\');clearTimeout(t._timer);t._timer=setTimeout(()=>t.classList.remove(\'show\'),3000)}';
}
