<?php
// install.php: نصب اولیه‌ی سایت. بعد از نصب موفق، فایل install.lock ساخته می‌شود و این صفحه غیرفعال می‌شود.
$lock_file = __DIR__ . '/install.lock';
if (file_exists($lock_file)) { http_response_code(404); exit('Not Found'); }

$err = '';
$done = false;
$v = ['site_name' => '', 'db_host' => 'localhost', 'db_user' => 'root', 'db_name' => 'sitak'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $v['site_name'] = trim($_POST['site_name'] ?? '');
    $v['db_host']   = trim($_POST['db_host'] ?? 'localhost');
    $v['db_user']   = trim($_POST['db_user'] ?? 'root');
    $v['db_name']   = trim($_POST['db_name'] ?? 'sitak');
    $db_pass = (string)($_POST['db_pass'] ?? '');
    $pw  = (string)($_POST['admin_password'] ?? '');
    $pw2 = (string)($_POST['admin_password2'] ?? '');

    if (!preg_match('/^[A-Za-z0-9_.]+$/', $v['db_name'])) {
        $err = 'نام دیتابیس فقط می‌تواند شامل حروف انگلیسی، عدد، _ و نقطه باشد.';
    } elseif (($perr = sitak_password_check($pw)) !== '') {
        $err = $perr;
    } elseif ($pw !== $pw2) {
        $err = 'تکرار گذرواژه ادمین با گذرواژه یکی نیست.';
    } else {
        mysqli_report(MYSQLI_REPORT_OFF);
        $test = @new mysqli($v['db_host'], $v['db_user'], $db_pass);
        if ($test->connect_error) {
            $err = 'اتصال به دیتابیس برقرار نشد. اطلاعات اتصال را بررسی کن.';
        } else {
            $test->close();
            $cfg = "<?php\n// تولید شده توسط نصب‌کننده\nreturn " . var_export([
                'host' => $v['db_host'], 'user' => $v['db_user'], 'pass' => $db_pass, 'name' => $v['db_name']
            ], true) . ";\n";
            if (@file_put_contents(__DIR__ . '/db-config.php', $cfg) === false) {
                $err = 'نوشتن فایل تنظیمات دیتابیس ممکن نیست. دسترسی پوشه‌ی سایت را بررسی کن.';
            } else {
                require __DIR__ . '/config.php'; // ساخت دیتابیس و جدول‌ها
                $hash = sitak_password_hash($pw);
                $st = $conn->prepare("INSERT INTO settings (key_name, value_data) VALUES ('admin_password', ?) ON DUPLICATE KEY UPDATE value_data = ?");
                $st->bind_param('ss', $hash, $hash); $st->execute();
                $site = sitak_clean_name($v['site_name']);
                if ($site !== '') {
                    $st = $conn->prepare("INSERT INTO settings (key_name, value_data) VALUES ('site_name', ?) ON DUPLICATE KEY UPDATE value_data = ?");
                    $st->bind_param('ss', $site, $site); $st->execute();
                }
                file_put_contents($lock_file, date('c'));
                $done = true;
            }
        }
    }
}
function sitak_password_check($pw) {
    if ($pw === '') return 'گذرواژه ادمین را وارد کن.';
    $e = [];
    if (strlen($pw) < 8) $e[] = 'گذرواژه ادمین باید حداقل ۸ کاراکتر باشد.';
    if (!preg_match('/[a-z]/', $pw)) $e[] = 'گذرواژه ادمین باید حداقل یک حرف کوچک انگلیسی داشته باشد.';
    if (!preg_match('/[A-Z]/', $pw)) $e[] = 'گذرواژه ادمین باید حداقل یک حرف بزرگ انگلیسی داشته باشد.';
    if (!preg_match('/[0-9]/', $pw)) $e[] = 'گذرواژه ادمین باید حداقل یک عدد داشته باشد.';
    if (!preg_match('/[^A-Za-z0-9]/', $pw)) $e[] = 'گذرواژه ادمین باید حداقل یک کاراکتر خاص (مثل !@#$) داشته باشد.';
    if (preg_match('/[^ -~]/', $pw)) $e[] = 'گذرواژه ادمین فقط می‌تواند شامل حروف، عدد و نمادهای انگلیسی باشد.';
    return implode(' • ', $e);
}
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>نصب سایت</title>
<style>
  *{box-sizing:border-box}
  body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;
       font-family:Vazirmatn,Tahoma,sans-serif;background:linear-gradient(135deg,#f5f5f7,#e9ecf5);color:#1d1d1f}
  .card{width:100%;max-width:520px;background:#fff;border-radius:18px;padding:28px 26px;box-shadow:0 10px 40px rgba(0,0,0,.08)}
  .brand{display:flex;align-items:center;gap:10px;margin-bottom:6px}
  .brand .logo{width:42px;height:42px;border-radius:12px;background:#1d1d1f;color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.3rem}
  h1{font-size:1.15rem;margin:0}
  .sub{color:#86868b;font-size:.82rem;margin:4px 0 20px}
  .steps{display:flex;gap:6px;margin-bottom:22px}
  .steps span{flex:1;height:4px;border-radius:4px;background:#e5e5ea}
  .steps span.on{background:#1d1d1f}
  fieldset{border:0;padding:0;margin:0 0 18px}
  legend{font-weight:700;font-size:.9rem;margin-bottom:10px;padding:0}
  .row{display:grid;grid-template-columns:1fr 1fr;gap:10px}
  @media (max-width:480px){.row{grid-template-columns:1fr}}
  label{display:block;font-size:.8rem;color:#424245;margin:10px 0 5px;font-weight:600}
  .field{position:relative}
  input{width:100%;padding:11px 12px;border:1px solid #d2d2d7;border-radius:10px;font:inherit;font-size:.9rem;background:#fff;transition:border-color .15s,box-shadow .15s}
  input:focus{outline:0;border-color:#0071e3;box-shadow:0 0 0 3px rgba(0,113,227,.15)}
  input[dir=ltr]{text-align:left}
  .eye{position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:0;cursor:pointer;padding:4px;line-height:0;color:#86868b}
  .hint{font-size:.72rem;color:#86868b;margin-top:5px;line-height:1.6}
  .err{background:#fff1f0;border:1px solid #ffccc7;color:#a8071a;padding:11px 13px;border-radius:10px;font-size:.83rem;margin-bottom:16px}
  .ok{background:#f0faf3;border:1px solid #b7e4c7;color:#135c2c;padding:14px;border-radius:12px;font-size:.9rem;line-height:1.8}
  .btn{width:100%;margin-top:6px;padding:13px;border:0;border-radius:12px;background:#1d1d1f;color:#fff;font:inherit;font-weight:700;font-size:.95rem;cursor:pointer}
  .btn:hover{background:#000}
  .btn[disabled]{opacity:.6;cursor:wait}
  .link{display:inline-block;margin-top:14px;color:#0071e3;text-decoration:none;font-weight:700}
  .toast{position:fixed;bottom:22px;left:50%;transform:translateX(-50%) translateY(20px);background:#1d1d1f;color:#fff;padding:12px 18px;border-radius:12px;font-size:.85rem;opacity:0;transition:.25s;pointer-events:none;max-width:92vw;text-align:center;z-index:9}
  .toast.show{opacity:1;transform:translateX(-50%) translateY(0)}
  .foot{margin-top:16px;text-align:center;font-size:.72rem;color:#86868b}
</style>
</head>
<body>
<div class="card">
<?php if ($done): ?>
  <div class="brand"><div class="logo">✅</div><div><h1>نصب با موفقیت انجام شد</h1></div></div>
  <div class="sub">دیتابیس و جدول‌ها ساخته شدند و گذرواژه ادمین ثبت شد.</div>
  <div class="ok">از این به بعد این صفحه غیرفعال است. برای ورود به درگاه مدیریت، گذرواژه‌ای را که همین حالا تعیین کردی استفاده کن.</div>
  <a class="link" href="admin.php">ورود به درگاه مدیریت ←</a>
<?php else: ?>
  <div class="brand"><div class="logo">🌐</div><div><h1>نصب اولیه‌ی سایت</h1></div></div>
  <div class="sub">فقط یک بار اجرا می‌شود. اطلاعات زیر را وارد کن تا سایت آماده شود.</div>
  <div class="steps"><span class="on"></span><span class="on"></span><span class="on"></span></div>

  <?php if ($err !== ''): ?><div class="err" id="errBox">⚠️ <?= $h($err) ?></div><?php endif; ?>

  <form method="post" autocomplete="off" id="installForm">
    <fieldset>
      <legend>۱. اطلاعات دیتابیس</legend>
      <div class="row">
        <div>
          <label for="db_host">آدرس سرور</label>
          <input id="db_host" type="text" name="db_host" value="<?= $h($v['db_host']) ?>" dir="ltr" required>
        </div>
        <div>
          <label for="db_name">نام دیتابیس</label>
          <input id="db_name" type="text" name="db_name" value="<?= $h($v['db_name']) ?>" dir="ltr" required>
        </div>
        <div>
          <label for="db_user">نام کاربری</label>
          <input id="db_user" type="text" name="db_user" value="<?= $h($v['db_user']) ?>" dir="ltr" required>
        </div>
        <div>
          <label for="db_pass">گذرواژه دیتابیس</label>
          <div class="field"><input id="db_pass" type="password" name="db_pass" dir="ltr"><button type="button" class="eye" data-target="db_pass" aria-label="نمایش گذرواژه"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button></div>
        </div>
      </div>
      <div class="hint">اگر دیتابیس وجود نداشته باشد، خودکار ساخته می‌شود.</div>
    </fieldset>

    <fieldset>
      <legend>۲. گذرواژه پنل مدیریت</legend>
      <div class="row">
        <div>
          <label for="admin_password">گذرواژه ادمین</label>
          <div class="field"><input id="admin_password" type="password" name="admin_password" dir="ltr" minlength="8" required><button type="button" class="eye" data-target="admin_password" aria-label="نمایش گذرواژه"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button></div>
        </div>
        <div>
          <label for="admin_password2">تکرار گذرواژه</label>
          <div class="field"><input id="admin_password2" type="password" name="admin_password2" dir="ltr" minlength="8" required><button type="button" class="eye" data-target="admin_password2" aria-label="نمایش گذرواژه"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button></div>
        </div>
      </div>
      <div class="hint">گذرواژه باید حداقل ۸ کاراکتر باشد و شامل حرف بزرگ و کوچک انگلیسی، عدد و یک کاراکتر خاص (مثل !@#$) باشد. این گذرواژه را جایی امن نگه دار.</div>
    </fieldset>

    <fieldset>
      <legend>۳. اسم سایت</legend>
      <label for="site_name">اسم سایت</label>
      <input id="site_name" type="text" name="site_name" value="<?= $h($v['site_name']) ?>" placeholder="خالی بماند: سای‌تک">
      <div class="hint">بدون ایموجی. بعداً هم از پنل قابل تغییره.</div>
    </fieldset>

    <button class="btn" type="submit" id="submitBtn">نصب سایت</button>
  </form>
  <div class="foot">بعد از نصب موفق، این صفحه خودکار بسته می‌شود.</div>
<?php endif; ?>
</div>
<script>
  document.querySelectorAll('.eye').forEach(function(b){
    b.addEventListener('click', function(){
      var i = document.getElementById(b.dataset.target);
      i.type = i.type === 'password' ? 'text' : 'password';
      b.innerHTML = i.type === 'password' ? '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>' : '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
    });
  });
  // اعتبارسنجی قبل از ارسال: همان قوانین سمت سرور، ولی بدون رفتن به سرور
  function sitakPwErrors(pw){
    if (pw === '') return ['گذرواژه ادمین را وارد کن.'];
    var e = [];
    if (pw.length < 8) e.push('گذرواژه ادمین باید حداقل ۸ کاراکتر باشد.');
    if (!/[a-z]/.test(pw)) e.push('گذرواژه ادمین باید حداقل یک حرف کوچک انگلیسی داشته باشد.');
    if (!/[A-Z]/.test(pw)) e.push('گذرواژه ادمین باید حداقل یک حرف بزرگ انگلیسی داشته باشد.');
    if (!/[0-9]/.test(pw)) e.push('گذرواژه ادمین باید حداقل یک عدد داشته باشد.');
    if (!/[^A-Za-z0-9]/.test(pw)) e.push('گذرواژه ادمین باید حداقل یک کاراکتر خاص (مثل !@#$) داشته باشد.');
    if (/[^ -~]/.test(pw)) e.push('گذرواژه ادمین فقط می‌تواند شامل حروف، عدد و نمادهای انگلیسی باشد.');
    return e;
  }
  var f = document.getElementById('installForm');
  if (f) f.addEventListener('submit', function(ev){
    var msgs = [];
    var dbn = document.getElementById('db_name').value.trim();
    if (!/^[A-Za-z0-9_.]+$/.test(dbn)) msgs.push('نام دیتابیس فقط می‌تواند شامل حروف انگلیسی، عدد، _ و نقطه باشد.');
    var pw = document.getElementById('admin_password').value;
    var pe = sitakPwErrors(pw);
    if (pe.length) msgs.push(pe.join(' • '));
    else if (pw !== document.getElementById('admin_password2').value) msgs.push('تکرار گذرواژه ادمین با گذرواژه یکی نیست.');
    var box = document.getElementById('jsErr');
    if (msgs.length) {
      ev.preventDefault();
      if (!box) { box = document.createElement('div'); box.id = 'jsErr'; box.className = 'err'; f.parentNode.insertBefore(box, f); }
      box.textContent = '⚠️ ' + msgs.join(' | ');
      box.style.display = '';
      box.scrollIntoView({behavior: 'smooth', block: 'center'});
      return;
    }
    if (box) box.style.display = 'none';
    var b = document.getElementById('submitBtn');
    b.disabled = true; b.textContent = 'در حال نصب…';
  });
</script>
<div class="toast" id="toast"></div>
<script>
  var eb = document.getElementById('errBox');
  if (eb) { var t = document.getElementById('toast'); t.textContent = eb.textContent.replace('⚠️','').trim(); t.classList.add('show'); setTimeout(function(){ t.classList.remove('show'); }, 4000); }
</script>
</body>
</html>
