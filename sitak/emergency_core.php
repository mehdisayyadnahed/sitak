<?php
// emergency_core.php: هسته‌ی صفحه‌ی اضطراری سای‌تک.
// این فایل مستقل از پنل ادمین و بدون نیاز به اتصال دیتابیس است، چون دقیقاً برای زمانی است که دیتابیس یا فایل‌های پنل خراب شده‌اند.
if (basename($_SERVER['PHP_SELF'] ?? '') === 'emergency_core.php') { http_response_code(404); exit; }

// توابع محدودیت تلاش: اگر config.php لود نشده باشد (مثل صفحه‌ی اضطراری)، همین نسخه‌ی مستقل استفاده می‌شود.
if (!function_exists('sitak_throttle_file')) { function sitak_throttle_file($key) { return sys_get_temp_dir() . '/sitak_thr_' . hash('sha256', $key) . '.json'; } }
if (!function_exists('sitak_throttle_state')) { function sitak_throttle_state($key) {
    $d = @json_decode((string)@file_get_contents(sitak_throttle_file($key)), true);
    return is_array($d) ? $d : ['n' => 0, 'last' => 0];
} }
if (!function_exists('sitak_throttle_blocked')) { function sitak_throttle_blocked($key, $max = 5, $lock = 900) {
    $d = sitak_throttle_state($key);
    return ($d['n'] >= $max) && (time() - $d['last'] < $lock);
} }
if (!function_exists('sitak_throttle_fail')) { function sitak_throttle_fail($key, $lock = 900) {
    $d = sitak_throttle_state($key);
    if (time() - $d['last'] >= $lock) $d['n'] = 0;
    $d['n']++; $d['last'] = time();
    @file_put_contents(sitak_throttle_file($key), json_encode($d));
} }
if (!function_exists('sitak_throttle_clear')) { function sitak_throttle_clear($key) { @unlink(sitak_throttle_file($key)); } }

// قالب فایل صفحه‌ی اضطراری. هش گذرواژه داخل خود فایل ذخیره می‌شود، نه در دیتابیس.
function sitak_em_template($hash, $name) {
    return "<?php\n"
        . "// صفحه‌ی اضطراری سای‌تک. هش گذرواژه این صفحه در همین فایل است؛ فایل را دستی ویرایش نکنید.\n"
        . "\$emergency_hash = " . var_export($hash, true) . ";\n"
        . "\$emergency_self = " . var_export($name, true) . ";\n"
        . "require __DIR__ . '/emergency_core.php';\n"
        . "sitak_emergency_run(\$emergency_hash, \$emergency_self);\n";
}

// هش گذرواژه ادمین را از دیتابیس می‌خواند و فایل صفحه‌ی اضطراری با نام «{نشانی}-emergency.php» را می‌سازد یا به‌روز می‌کند.
function sitak_emergency_sync($conn, $prefix) {
    try {
        $r = $conn->query("SELECT value_data FROM settings WHERE key_name='admin_password'");
        $hash = $r ? (string)($r->fetch_assoc()['value_data'] ?? '') : '';
    } catch (Throwable $e) {
        return false;
    }
    // اگر گذرواژه هنوز به‌صورت متن ساده است (قبل از اولین ورود)، صبر می‌کنیم تا هش شود.
    if ($hash === '' || (password_get_info($hash)['algo'] ?? 0) === 0) return false;
    $name = $prefix . '-emergency.php';
    $path = __DIR__ . '/' . $name;
    $content = sitak_em_template($hash, $name);
    if (is_file($path) && @file_get_contents($path) === $content) return true;
    return @file_put_contents($path, $content) !== false;
}

// تنظیمات اتصال دیتابیس را مستقیم از config.php می‌خواند تا این صفحه به config.php (که ممکن است خطا بدهد) وابسته نباشد.
function sitak_em_db_config($dir) {
    $cfg = ['host' => 'localhost', 'user' => 'root', 'pass' => '', 'name' => (getenv('SITAK_DB_NAME') ?: 'sitak')];
    // اگر نصب‌کننده db-config.php ساخته باشد، همان منبع معتبر است (config.php هم همان را می‌خواند).
    $installed = $dir . '/db-config.php';
    if (is_file($installed)) {
        $dbc = @include $installed;
        if (is_array($dbc)) {
            foreach (['host', 'user', 'pass', 'name'] as $k) {
                if (isset($dbc[$k])) $cfg[$k] = (string)$dbc[$k];
            }
            return $cfg;
        }
    }
    $src = @file_get_contents($dir . '/config.php');
    if ($src === false) return $cfg;
    foreach (array_keys($cfg) as $k) {
        if (preg_match('/\$db_' . $k . '\s*=\s*\'([^\']*)\'/', $src, $m)) $cfg[$k] = $m[1];
    }
    return $cfg;
}

function sitak_em_sql_statements($sql) {
    $sql = preg_replace('/^--.*$/m', '', $sql);
    $parts = []; $buf = ''; $quote = ''; $len = strlen($sql);
    for ($i = 0; $i < $len; $i++) {
        $c = $sql[$i];
        $buf .= $c;
        if ($quote) {
            if ($c === '\\' && $i + 1 < $len) { $buf .= $sql[++$i]; continue; }
            if ($c === $quote) $quote = '';
        } elseif ($c === "'" || $c === '"' || $c === '`') {
            $quote = $c;
        } elseif ($c === ';') {
            $stmt = trim($buf);
            if ($stmt !== '') $parts[] = $stmt;
            $buf = '';
        }
    }
    if (trim($buf) !== '') $parts[] = trim($buf);
    return $parts;
}

function sitak_em_restore_db($dir, $sql) {
    $c = sitak_em_db_config($dir);
    mysqli_report(MYSQLI_REPORT_OFF);
    $conn = @new mysqli($c['host'], $c['user'], $c['pass']);
    if ($conn->connect_error) throw new Exception('اتصال به دیتابیس برقرار نشد؛ بازیابی دیتابیس انجام نشد.');
    $conn->query("CREATE DATABASE IF NOT EXISTS `" . $c['name'] . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_persian_ci");
    $conn->select_db($c['name']);
    $conn->set_charset('utf8mb4');
    foreach (sitak_em_sql_statements($sql) as $stmt) {
        if (!$conn->query($stmt)) throw new Exception('خطا در بازگردانی دیتابیس: ' . $conn->error);
    }
    $conn->close();
}

function sitak_em_extract($zipFile, $target) {
    $zip = new ZipArchive();
    if ($zip->open($zipFile) !== true) throw new Exception('فایل ZIP معتبر نیست.');
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if ($name === false || strpos($name, '..') !== false || strpos($name, '\\') !== false || strpos($name, '/') === 0) {
            $zip->close();
            throw new Exception('ساختار فایل پشتیبان معتبر نیست.');
        }
    }
    if (!$zip->extractTo($target)) { $zip->close(); throw new Exception('استخراج فایل پشتیبان انجام نشد.'); }
    $zip->close();
}

// در ریشه‌ی سایت، فایل‌های «...-emergency.php» بازنویسی نمی‌شوند تا گذرواژه صفحه‌ی اضطراری فعلی با بازیابی فایل‌ها عوض نشود.
function sitak_em_copy_tree($from, $to, $protectEmergency = false) {
    foreach (@scandir($from) ?: [] as $item) {
        if ($item === '.' || $item === '..') continue;
        $src = $from . '/' . $item; $dst = $to . '/' . $item;
        if (is_dir($src)) {
            if (!is_dir($dst)) @mkdir($dst, 0755, true);
            sitak_em_copy_tree($src, $dst, false);
        } elseif (is_file($src)) {
            if ($protectEmergency && preg_match('/-emergency\.php$/', $item)) continue;
            @copy($src, $dst);
        }
    }
}

function sitak_em_rm_tree($path) {
    if (!is_dir($path)) return;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) { $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname()); }
    @rmdir($path);
}

function sitak_em_restore($dir, $type, $file) {
    if (!in_array($type, ['database', 'files', 'full'], true)) throw new Exception('نوع بازگردانی نامعتبر است.');
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        throw new Exception('فایل پشتیبان انتخاب نشده است.');
    }
    if ($type === 'database') {
        sitak_em_restore_db($dir, file_get_contents($file['tmp_name']));
    } else {
        $tmp = sys_get_temp_dir() . '/sitak_em_restore_' . bin2hex(random_bytes(6));
        @mkdir($tmp, 0755, true);
        try {
            sitak_em_extract($file['tmp_name'], $tmp);
            $root = ($type === 'full' && is_dir($tmp . '/site-files')) ? $tmp . '/site-files' : $tmp;
            if ($type === 'full' && is_file($tmp . '/database.sql')) {
                sitak_em_restore_db($dir, file_get_contents($tmp . '/database.sql'));
            }
            sitak_em_copy_tree($root, $dir, true);
        } finally {
            sitak_em_rm_tree($tmp);
        }
    }
    return 'بازیابی با موفقیت انجام شد ✅';
}

function sitak_em_h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function sitak_em_page($self, $body) {
    echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="robots" content="noindex,nofollow">'
        . '<title>صفحه‌ی اضطراری</title>'
        . '<link href="assets/fonts/vazirmatn.css" rel="stylesheet">'
        . '<style>'
        . 'body{margin:0;font-family:Vazirmatn,Tahoma,sans-serif;background:#f5f5f7;color:#1d1d1f;min-height:100vh;display:flex;align-items:flex-start;justify-content:center;padding:24px 14px;box-sizing:border-box}'
        . '.box{width:100%;max-width:520px;background:#fff;border-radius:18px;padding:22px;box-shadow:0 4px 20px rgba(0,0,0,.08)}'
        . 'h1{font-size:1.1rem;margin:0 0 6px}p{font-size:.82rem;line-height:1.9;color:#6e6e73;margin:0 0 14px}'
        . 'input[type=password],input[type=file]{width:100%;box-sizing:border-box;padding:10px;border:1px solid #d2d2d7;border-radius:10px;font:inherit;margin-bottom:10px;background:#fafafa}'
        . 'button{font:inherit;font-weight:700;border:0;border-radius:10px;padding:10px 16px;background:#ecb534;color:#1d1d1f;cursor:pointer}'
        . '.card{border:1px solid #e5e5ea;border-radius:14px;padding:14px;margin-bottom:12px}.card h2{font-size:.92rem;margin:0 0 4px}.card small{display:block;color:#6e6e73;font-size:.72rem;margin-bottom:10px}'
        . '.ok{background:#e8f8ee;color:#1a8a3f;border-radius:10px;padding:10px;font-size:.8rem;margin-bottom:12px}'
        . '.err{background:#fdecea;color:#c0392b;border-radius:10px;padding:10px;font-size:.8rem;margin-bottom:12px}'
        . '.top{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px}.top a{font-size:.78rem;color:#6e6e73}'
        . '</style></head><body><div class="box">' . $body . '</div></body></html>';
}

function sitak_emergency_run($hash, $self) {
    $dir = __DIR__;
    if (session_status() === PHP_SESSION_NONE) @session_start();

    if (isset($_GET['logout'])) {
        unset($_SESSION['sitak_emergency']);
        header('Location: ' . $self); exit;
    }

    $err = '';
    if (isset($_POST['em_login'])) {
        $tkey = 'emergency:' . ($_SERVER['REMOTE_ADDR'] ?? '');
        if (sitak_throttle_blocked($tkey)) {
            $err = 'تعداد تلاش‌ها زیاد بود. ۱۵ دقیقه بعد دوباره امتحان کنید.';
        } elseif ($hash !== '' && password_verify((string)($_POST['password'] ?? ''), $hash)) {
            sitak_throttle_clear($tkey);
            session_regenerate_id(true);
            $_SESSION['sitak_emergency'] = true;
            header('Location: ' . $self); exit;
        } else {
            sitak_throttle_fail($tkey);
            $err = 'گذرواژه اشتباه است.';
        }
    }

    $authed = !empty($_SESSION['sitak_emergency']);

    if (!$authed) {
        $body = '<h1>صفحه‌ی اضطراری</h1>'
            . '<p>این صفحه فقط برای بازیابی فایل‌های پشتیبان در مواقعی است که پایگاه‌داده یا فایل‌های درگاه مدیریت مشکل دارند. گذرواژه، همان گذرواژه‌ی درگاه مدیریت است.</p>'
            . ($err !== '' ? '<div class="err">' . sitak_em_h($err) . '</div>' : '')
            . '<form method="post" action="' . sitak_em_h($self) . '">'
            . '<div style="position:relative;margin-bottom:10px">'
            . '<input type="password" id="emPass" name="password" placeholder="گذرواژه مدیریت" required autocomplete="current-password" style="display:block;width:100%;box-sizing:border-box;margin:0;padding:10px 44px 10px 10px;border:1px solid #d2d2d7;border-radius:10px;font:inherit;background:#fafafa">'
            . '<button type="button" onclick="emTogglePw()" aria-label="نمایش گذرواژه" style="position:absolute;left:0;top:0;height:100%;width:44px;background:none;border:none;cursor:pointer;color:#86868b;padding:0;display:flex;align-items:center;justify-content:center">'
            . '<svg id="emEye" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>'
            . '<svg id="emOff" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 19c-6.5 0-10-7-10-7a18.5 18.5 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c6.5 0 10 7 10 7a18.5 18.5 0 0 1-2.16 3.19M1 1l22 22"/></svg>'
            . '</button></div>'
            . '<script>function emTogglePw(){var i=document.getElementById("emPass");var t=i.type==="text";i.type=t?"password":"text";document.getElementById("emEye").style.display=t?"":"none";document.getElementById("emOff").style.display=t?"none":"";}</script>'
            . '<button type="submit" name="em_login" value="1">ورود</button></form>';
        sitak_em_page($self, $body);
        return;
    }

    if (empty($_SESSION['sitak_em_csrf'])) $_SESSION['sitak_em_csrf'] = bin2hex(random_bytes(32));
    $csrf = $_SESSION['sitak_em_csrf'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['em_restore'])) {
        if (!hash_equals($csrf, (string)($_POST['csrf_token'] ?? ''))) { http_response_code(403); exit('درخواست نامعتبر است.'); }
        try {
            $msg = sitak_em_restore($dir, (string)($_POST['restore_type'] ?? ''), $_FILES['backup_file'] ?? null);
            $_SESSION['sitak_em_flash'] = ['ok', $msg];
        } catch (Throwable $e) {
            $_SESSION['sitak_em_flash'] = ['err', $e->getMessage()];
        }
        header('Location: ' . $self); exit;
    }

    $flash = $_SESSION['sitak_em_flash'] ?? null;
    unset($_SESSION['sitak_em_flash']);

    $cards = [
        ['database', '🗄️ بازیابی پایگاه داده', 'فایل SQL پشتیبان پایگاه داده', '.sql'],
        ['files', '📁 بازیابی فایل‌ها', 'فایل ZIP پشتیبان فایل‌های سایت', '.zip'],
        ['full', '📦 بازیابی کامل پایگاه داده و فایل‌ها', 'فایل ZIP پشتیبان کامل', '.zip'],
    ];
    $body = '<div class="top"><h1>صفحه‌ی اضطراری</h1><a href="' . sitak_em_h($self) . '?logout=1">خروج</a></div>'
        . '<p>بازیابی، اطلاعات فعلی همان بخش را جایگزین می‌کند. فقط فایل پشتیبانی را انتخاب کن که از همین سایت گرفته شده است.</p>';
    if ($flash) {
        $body .= '<div class="' . ($flash[0] === 'ok' ? 'ok' : 'err') . '">' . sitak_em_h($flash[1]) . '</div>';
    }
    foreach ($cards as [$type, $title, $hint, $ext]) {
        $body .= '<div class="card"><h2>' . sitak_em_h($title) . '</h2><small>' . sitak_em_h($hint) . '</small>'
            . '<form method="post" action="' . sitak_em_h($self) . '" enctype="multipart/form-data" '
            . 'onsubmit="return confirm(\'اطلاعات فعلی این بخش با این فایل جایگزین می‌شود. ادامه می‌دهی؟\')">'
            . '<input type="hidden" name="csrf_token" value="' . sitak_em_h($csrf) . '">'
            . '<input type="hidden" name="em_restore" value="1">'
            . '<input type="hidden" name="restore_type" value="' . sitak_em_h($type) . '">'
            . '<input type="file" name="backup_file" accept="' . sitak_em_h($ext) . '" required>'
            . '<button type="submit">انتخاب و بازیابی</button></form></div>';
    }
    sitak_em_page($self, $body);
}
