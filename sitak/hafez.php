<?php
require_once 'config.php';
$site_name = getSetting($conn, 'site_name', 'سای‌تک');

$captcha_n1 = rand(1, 10);
$captcha_n2 = rand(1, 10);
$_SESSION['captcha_answer'] = $captcha_n1 + $captcha_n2;

$hafez_enabled = getSetting($conn, 'hafez_enabled', '1');
if ($hafez_enabled !== '1') { header('Location: index.php'); exit; }
track_view($conn, 'hafez');

$default_theme  = getSetting($conn, 'default_theme', 'auto');
$custom_favicon = getSetting($conn, 'custom_favicon', '');
$custom_logo    = getSetting($conn, 'custom_logo', '');
$loyalty_enabled = getSetting($conn, 'loyalty_enabled', '1');
$chance_enabled = getSetting($conn, 'chance_enabled', '1');
$blog_enabled = getSetting($conn, 'blog_enabled', '1');
$shop_enabled = getSetting($conn, 'shop_enabled', '1');
$orders_tab_enabled = getSetting($conn, 'orders_tab_enabled', '1');

$phone = ($_SESSION['sitak_user'] ?? '');
$user_info = null;
$force_logout_popup = false;
if (!empty($phone)) {
    $user_info = get_user_rolls_info($conn, $phone);
    if ($user_info && isset($user_info['user']['blocked']) && $user_info['user']['blocked'] == 1) {
        unset($_SESSION['sitak_user']);
        $user_info = null;
        $force_logout_popup = true;
    }
}

// â”€â”€ ط¨ط±ط±ط³غŒ ظپط§ظ„ ط§ظ…ط±ظˆط² â”€â”€
$today = date('Y-m-d');
$already_opened = false;
$today_verse = null;
$user_todays_verse_id = 0;

if (!empty($phone)) {
    // ط¨ط±ط±ط³غŒ ط¢غŒط§ ط§ظ…ط±ظˆط² ظپط§ظ„ ط¨ط§ط² ع©ط±ط¯ظ‡
    $check = $conn->prepare("SELECT hv.* FROM hafez_opens ho JOIN hafez_verses hv ON ho.verse_id = hv.id WHERE ho.user_phone = ? AND DATE(ho.opened_at) = ?");
    $check->bind_param("ss", $phone, $today);
    $check->execute();
    $today_verse = $check->get_result()->fetch_assoc();
    if ($today_verse) {
        $already_opened = true;
        $user_todays_verse_id = $today_verse['id'];
    }
}

// â”€â”€ طھط¹ط¯ط§ط¯ ع©ظ„ ط¨غŒطھâ€Œظ‡ط§ â”€â”€
$total_verses = $conn->query("SELECT COUNT(*) AS cnt FROM hafez_verses WHERE is_active = 1")->fetch_assoc()['cnt'] ?? 0;

// â”€â”€ ط¢ظ…ط§ط± ظپط§ظ„â€Œظ‡ط§غŒ ط¨ط§ط² ط´ط¯ظ‡ طھظˆط³ط· ع©ط§ط±ط¨ط± â”€â”€
$total_opens = 0;
if (!empty($phone)) {
    $st_opens = $conn->prepare("SELECT COUNT(*) AS cnt FROM hafez_opens WHERE user_phone = ?");
    $st_opens->bind_param("s", $phone); $st_opens->execute();
    $total_opens = $st_opens->get_result()->fetch_assoc()['cnt'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="<?= htmlspecialchars($default_theme, ENT_QUOTES, 'UTF-8') ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<meta name="theme-color" content="#ffffff" id="metaTheme">
<?= theme_head_script('sitak_theme', $default_theme) ?>
<?= sitak_head_meta($conn, ['title' => 'فال حافظ']) ?>
<title>فال حافظ | <?= htmlspecialchars($site_name ?? 'سای‌تک', ENT_QUOTES, 'UTF-8') ?></title>
<link href="assets/fonts/vazirmatn.css" rel="stylesheet">
<style>
:root,[data-theme="light"]{--bg:#f5f5f7;--surface:#fff;--card:#fff;--card-el:#f0eff2;--input-bg:#f0eff2;--border:rgba(255,255,255,.08);--border-gold:rgba(236,181,52,.22);--text:#1d1d1f;--text2:#6e6e73;--text3:#aeaeb2;--header-bg:rgba(255,255,255,.82);--shadow-sm:0 1px 3px rgba(0,0,0,.06);--shadow-md:0 4px 14px rgba(0,0,0,.06);--red:#ff453a;--red-bg:rgba(255,69,58,.06);--gold:#ecb534;--gold-dark:#c49625;--gold-light:#f5d076;--gold-subtle:rgba(236,181,52,.07);--gold-glow:rgba(236,181,52,.12);--green:#30d158;--green-text:#1a8a3f;--green-bg:rgba(48,209,88,.07);--r:20px;--rs:14px;--rss:10px;--safe-t:env(safe-area-inset-top,0px);--safe-b:env(safe-area-inset-bottom,0px);--font:'Vazirmatn',-apple-system,BlinkMacSystemFont,sans-serif}
[data-theme="dark"]{--bg:#0a0a0c;--surface:#161618;--card:#1c1c1e;--card-el:#2c2c2e;--input-bg:#2c2c2e;--border:rgba(255,255,255,.08);--border-gold:rgba(236,181,52,.15);--text:#f0ece4;--text2:#9e9888;--text3:#5e5a52;--header-bg:rgba(10,10,12,.88);--shadow-sm:0 1px 3px rgba(0,0,0,.3);--red:#f87171;--red-bg:rgba(248,113,113,.08);--gold:#ecb534;--gold-dark:#c49625;--green:#34d399;--green-text:#34d399;--green-bg:rgba(52,211,153,.08)}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent}
html{font-size:16px;-webkit-text-size-adjust:100%;overscroll-behavior:none}
body{font-family:var(--font);background:var(--bg);color:var(--text);min-height:100dvh;overflow-x:hidden;-webkit-font-smoothing:antialiased;padding-top:var(--safe-t);transition:background .35s,color .35s}
body::before{content:'';position:fixed;top:-40%;right:-20%;width:500px;height:500px;background:radial-gradient(circle,var(--gold-glow),transparent 70%);pointer-events:none;z-index:0;transition:background .35s}

.app-bar{position:sticky;top:0;z-index:50;display:flex;align-items:center;justify-content:space-between;padding:10px 16px;min-height:60px;background:var(--header-bg);backdrop-filter:blur(28px) saturate(180%);-webkit-backdrop-filter:blur(28px) saturate(180%);border-bottom:1px solid var(--border);transition:background .35s;border-bottom-left-radius:16px;border-bottom-right-radius:16px}
.app-brand{display:flex;align-items:center;gap:10px}
.brand-icon{width:36px;height:36px;background:linear-gradient(135deg,var(--gold),var(--gold-dark));border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0}
.brand-text h1{font-size:1.05rem;font-weight:700;line-height:1.2}
.brand-text p{font-size:.73rem;color:var(--text3);font-weight:300}
.user-area{display:flex;align-items:center;gap:6px}
.header-icon-btn{position:relative;width:36px;height:36px;border-radius:50%;border:1px solid var(--border);background:var(--card-el);display:flex;align-items:center;justify-content:center;font-size:.95rem;color:var(--text);text-decoration:none;cursor:pointer;transition:all .2s;flex-shrink:0}.header-icon-btn:active{transform:scale(.9)}
.roll-count{position:absolute;top:-4px;left:-4px;min-width:18px;height:18px;background:var(--gold);color:#fff;font-size:.65rem;font-weight:700;border-radius:9px;display:flex;align-items:center;justify-content:center;padding:0 4px}
.avatar{width:30px;height:30px;border-radius:50%;background:var(--card-el);display:flex;align-items:center;justify_content:center;font-size:.7rem;color:var(--gold-dark);border:1.5px solid var(--gold);font-weight:700}
.theme-btn{width:32px;height:32px;border-radius:50%;border:1px solid var(--border);background:var(--card-el);display:flex;align-items:center;justify-content:center;font-size:.85rem;cursor:pointer;transition:all .2s}.theme-btn:active{transform:scale(.9);background:var(--gold-subtle)}
.logout-link{font-size:.7rem;color:var(--red);text-decoration:none;font-weight:600;padding:4px 8px;border-radius:8px;background:var(--red-bg);transition:all .2s}

.content{position:relative;z-index:1;padding:12px 14px 100px;max-width:480px;margin:0 auto}

/* Hero */
.hafez-hero{text-align:center;padding:28px 12px 20px}
.hafez-hero .icon{font-size:4rem;margin-bottom:12px;animation:float 3s ease-in-out infinite}
@keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-10px)}}
.hafez-hero h2{font-size:1.35rem;font-weight:900;color:var(--gold-dark);margin-bottom:8px;line-height:1.6}
.hafez-hero p{font-size:.82rem;color:var(--text2);line-height:1.7;max-width:340px;margin:0 auto}

/* Stats */
.stats-row{display:flex;gap:10px;margin-bottom:20px}
.stat-box{flex:1;background:var(--card);border:1px solid var(--border);border-radius:var(--rs);padding:14px;text-align:center}
.stat-num{font-size:1.3rem;font-weight:900;color:var(--gold-dark)}
.stat-lbl{font-size:.7rem;color:var(--text3);margin-top:4px}

/* Verse Card */
.verse-card{background:var(--card);border:2px solid var(--border-gold);border-radius:var(--r);padding:24px 20px;margin-bottom:20px;text-align:center;position:relative;overflow:hidden;box-shadow:0 4px 20px var(--gold-glow);transition:background .35s}
.verse-card::before{content:'';position:absolute;top:-50%;left:-50%;width:200%;height:200%;background:radial-gradient(circle,var(--gold-glow),transparent 50%);pointer-events:none;opacity:.5}
.verse-card .quran-icon{font-size:2rem;margin-bottom:12px;position:relative;z-index:1}
.verse-text{font-size:1.1rem;font-weight:600;line-height:2;color:var(--text);position:relative;z-index:1;margin-bottom:16px;font-style:italic}
.verse-num{font-size:.72rem;color:var(--text3);position:relative;z-index:1}
.meaning-box{background:var(--gold-subtle);border:1px solid var(--border-gold);border-radius:var(--rss);padding:16px;margin-top:16px;position:relative;z-index:1}
.meaning-box h4{font-size:.85rem;font-weight:700;color:var(--gold-dark);margin-bottom:8px}
.meaning-box p{font-size:.88rem;line-height:1.9;color:var(--text2)}

/* Open Button */
.open-btn{width:100%;padding:16px;background:linear-gradient(135deg,var(--gold),var(--gold-dark));color:#fff;border:none;border-radius:var(--rs);font-family:var(--font);font-size:1.05rem;font-weight:700;cursor:pointer;transition:all .12s;box-shadow:0 6px 18px rgba(236,181,52,.15);display:flex;align-items:center;justify-content:center;gap:8px}
.open-btn:active{}
.open-btn:disabled{opacity:.5;cursor:not-allowed;transform:none;box-shadow:none}

/* Already opened message */
.already-msg{background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:20px;text-align:center;margin-bottom:20px}
.already-msg p{font-size:.85rem;color:var(--text2);line-height:1.7}
.already-msg .icon{font-size:2.5rem;margin-bottom:10px}

/* Not logged in */
.login-card{background:var(--card);border:1px solid var(--border-gold);border-radius:var(--r);padding:24px;text-align:center;margin-bottom:20px}
.login-card h3{font-size:1.05rem;font-weight:700;color:var(--gold-dark);margin-bottom:8px}
.login-card p{font-size:.82rem;color:var(--text2);line-height:1.7;margin-bottom:16px}
.login-card .btn{display:inline-flex;align-items:center;gap:6px;padding:12px 24px;background:linear-gradient(135deg,var(--gold),var(--gold-dark));color:#fff;border:none;border-radius:var(--rss);font-family:var(--font);font-size:.88rem;font-weight:700;cursor:pointer;text-decoration:none;;transition:all .12s}
.login-card .btn:active{}

/* Result overlay */
.result-ov{position:fixed;inset:0;z-index:200;display:none;align-items:center;justify-content:center;padding:18px;background:rgba(0,0,0,.45);backdrop-filter:blur(16px)}.result-ov.open{display:flex}
.res-card{background:var(--surface);border:1.5px solid var(--border-gold);border-radius:26px;padding:30px 22px;max-width:370px;width:100%;text-align:center;animation:slideUp .4s cubic-bezier(.34,1.56,.64,1);box-shadow:0 10px 40px rgba(0,0,0,.15)}
@keyframes slideUp{from{transform:translateY(30px);opacity:0}to{transform:translateY(0);opacity:1}}
.res-icon{font-size:2.8rem;margin-bottom:10px;animation:float 2s ease-in-out infinite}
.res-card h3{font-size:1.2rem;font-weight:900;margin-bottom:7px;color:var(--gold-dark)}
.res-desc{font-size:.82rem;color:var(--text2);line-height:1.7;margin-bottom:18px}
.btn-close{width:100%;padding:14px;background:linear-gradient(135deg,var(--gold),var(--gold-dark));color:#fff;border:none;border-radius:var(--rss);font-family:var(--font);font-size:.92rem;font-weight:700;cursor:pointer;;transition:all .12s}
.btn-close:active{}

/* Confetti */
.confetti-box{position:fixed;inset:0;pointer-events:none;z-index:250;overflow:hidden}
@media(min-width:481px){
  .confetti-box{left:50%;right:auto;transform:translateX(-50%);width:480px}
  body::before{left:50%;right:auto;transform:translateX(-50%);width:480px;height:480px;top:-20%}
}
.confetti{position:absolute;top:-20px;border-radius:2px;animation:cFall linear forwards}
@keyframes cFall{0%{transform:translateY(0) rotate(0) scale(1);opacity:1}80%{opacity:1}100%{transform:translateY(100vh) rotate(720deg) scale(.4);opacity:0}}

/* Empty state */
.empty-state{text-align:center;padding:40px;color:var(--text3)}
/* â”€â”€ Register Sheet â”€â”€ */
.overlay{position:fixed;inset:0;background:rgba(0,0,0,.3);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);z-index:100;opacity:0;pointer-events:none;transition:opacity .3s}
.overlay.open{opacity:1;pointer-events:auto}
.sheet{position:fixed;bottom:0;left:0;right:0;z-index:101;background:var(--surface);border-radius:22px 22px 0 0;padding:0 18px 18px;padding-bottom:calc(18px + var(--safe-b));max-height:82vh;overflow-y:auto;transform:translateY(100%);transition:transform .38s cubic-bezier(.32,.72,0,1),background .35s;-webkit-overflow-scrolling:touch;box-shadow:0 -10px 40px rgba(0,0,0,.08)}
.sheet.open{transform:translateY(0)}
.handle{width:34px;height:4px;background:#d1d1d6;border-radius:4px;margin:10px auto 18px}
.sheet-title{font-size:1.05rem;font-weight:700;text-align:center;margin-bottom:16px}
.fi{width:100%;padding:13px 14px;background:var(--bg);border:1.5px solid var(--border);border-radius:var(--rss);color:var(--text);font-family:var(--font);font-size:.92rem;outline:none;transition:border-color .2s,background .3s;margin-bottom:12px}
.fi::placeholder{color:var(--text3)}.fi:focus{border-color:var(--gold)}
.fg{margin-bottom:13px}
.fg label{display:block;font-size:.75rem;font-weight:600;color:var(--text2);margin-bottom:5px}
.fh{font-size:.65rem;color:var(--text3);margin-top:3px}
.btn-p{width:100%;padding:14px;background:linear-gradient(135deg,var(--gold),var(--gold-dark));color:#fff;border:none;border-radius:var(--rss);font-family:var(--font);font-size:.92rem;font-weight:700;cursor:pointer;transition:all .12s;}
.btn-p:active{}
.captcha-block{margin-bottom:14px}
input[type=number]::-webkit-outer-spin-button,input[type=number]::-webkit-inner-spin-button{-webkit-appearance:none;margin:0}
input[type=number]{-moz-appearance:textfield;appearance:textfield}
.captcha-block label{display:block;font-size:.75rem;font-weight:600;color:var(--text2);margin-bottom:5px}
.captcha-row{display:flex;align-items:center;gap:10px;background:var(--bg);border:1.5px solid var(--border);border-radius:var(--rss);padding:12px 14px;margin-bottom:10px;transition:border-color .2s,background .35s}
.captcha-q{font-size:1.15rem;font-weight:700;color:var(--gold-dark);flex:1;text-align:center;letter-spacing:2px;direction:ltr}
.captcha-refresh{width:38px;height:38px;border-radius:50%;border:1px solid var(--border);background:var(--card-el);display:flex;align-items:center;justify-content:center;font-size:1.1rem;cursor:pointer;transition:all .2s;flex-shrink:0}
.captcha-row{position:relative;justify-content:center}.captcha-q{flex:0 1 auto}.captcha-refresh{position:absolute;left:10px;top:50%;margin-top:-19px}
.captcha-refresh:active{background:var(--gold-subtle);border-color:var(--gold);transform:rotate(180deg)}
.toast{position:fixed;bottom:90px;left:50%;transform:translateX(-50%) translateY(100px);background:linear-gradient(135deg,var(--gold,#ecb534),var(--gold-dark,#c49625));border:none;color:#fff;padding:12px 20px;border-radius:14px;font-size:.82rem;z-index:300;opacity:0;transition:all .35s cubic-bezier(.34,1.56,.64,1);max-width:min(88%,420px);text-align:center;box-shadow:var(--shadow-md);white-space:pre-line}
.toast.show{transform:translateX(-50%) translateY(0);opacity:1}
.bottom-nav{position:fixed;bottom:0;left:0;right:0;z-index:100;background:var(--surface);border-top:1px solid var(--border);display:flex;justify-content:space-around;padding:8px 0;padding-bottom:calc(8px + var(--safe-b));border-top-left-radius:16px;border-top-right-radius:16px}.bottom-nav a{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;color:var(--text2);text-decoration:none;font-size:.6rem;min-width:44px;min-height:44px;padding:4px 0;transition:color .2s;white-space:nowrap}.bottom-nav a.active{color:var(--gold-dark)}.bottom-nav svg{width:22px;height:22px;transition:transform .2s}.bottom-nav a.active svg{color:var(--gold-dark);transform:scale(1.1)}@media(min-width:481px){
  html{scrollbar-gutter:stable}
  html,body{max-width:480px;margin:0 auto}
  body{box-shadow:0 0 0 1px var(--border),0 0 50px rgba(0,0,0,.1)}
  .bottom-nav,.admin-nav{left:50%;right:auto;transform:translateX(-50%);width:480px}
}
@media(max-width:400px){.bottom-nav a{font-size:.55rem;min-width:38px;padding:2px 0}}
</style>
<?= custom_head_scripts($conn) ?>
</head>
<body>

<header class="app-bar">
  <div class="app-brand">
    <?php if ($custom_logo): ?>
      <img src="<?= htmlspecialchars($custom_logo, ENT_QUOTES, 'UTF-8') ?>" alt="Logo" style="height:36px; max-width:120px; object-fit:contain; border-radius:10px;">
    <?php else: ?>
      <div class="brand-icon">ًں›چï¸ڈ</div>
    <?php endif; ?>
    <div class="brand-text">
      <h1>ع©ط§ظپظ‡â€Œط´ظ† âک•ï¸ڈ</h1>
      <p>ط³ط§غŒع©ظ„ ع©ط§ظپظ‡â€ŒغŒ طµظ…غŒظ…غŒ ط´ظ…ط§</p>
    </div>
  </div>
  <div class="user-area" id="userArea">
    <button class="header-icon-btn" onclick="toggleTheme()" aria-label="طھط؛غŒغŒط± طھظ…"><span id="themeIcon">ًںŒ™</span></button>
    <?php if ($user_info): ?>
      <a href="chance.php" class="header-icon-btn" title="طھط§ط³ ط±ظˆط²ط§ظ†ظ‡">ًںژ²<span class="roll-count"><?= pn($user_info['total_rolls']) ?></span></a>
      <?php if (($shop_enabled ?? '1') === '1'): ?>
      <a href="#" onclick="openSheet('cart');return false" class="header-icon-btn" title="ط³ط¨ط¯ ط®ط±غŒط¯">ًں›’<span class="roll-count hidden" id="cartCount"></span></a>
      <?php endif; ?>
      <?php if (getSetting($conn, 'sms_password_login_enabled', '0') === '1'): ?><a href="#" onclick="openSheet('pwChange');return false" class="header-icon-btn" title="تغییر گذرواژه ورود">🔑</a><?php endif; ?>
      <a href="index.php?logout_user=true" onclick="openSheet('logout');return false" class="header-icon-btn" title="ط®ط±ظˆط¬" style="color:#ff453a"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></a>
    <?php else: ?>
      <a href="#" onclick="openSheet('reg');return false" class="header-icon-btn" title="ظˆط±ظˆط¯" style="font-size:.7rem;font-weight:700">ظˆط±ظˆط¯</a>
    <?php endif; ?>
  </div>
</header>

<main class="content">
  <section class="hafez-hero">
    <div class="icon">ًں“–</div>
    <h2>ظپط§ظ„ ط­ط§ظپط¸</h2>
    <p>ظ‡ط± ط±ظˆط² غŒع© ط¨غŒطھ ط§ط² ط¯غŒظˆط§ظ† ط­ط§ظپط¸ ط¨ط®ظˆط§ظ†غŒط¯ ظˆ ظ…ط¹ظ†ط§غŒ ط¢ظ† ط±ط§ ط¯ط±غŒط§ط¨غŒط¯</p>
  </section>

  <!-- Stats -->
  <div class="stats-row">
    <div class="stat-box">
      <div class="stat-num"><?= pn($total_verses) ?></div>
      <div class="stat-lbl">ط¨غŒطھ ط¯ط± ط¯غŒظˆط§ظ†</div>
    </div>
    <div class="stat-box">
      <div class="stat-num"><?= pn($total_opens) ?></div>
      <div class="stat-lbl">ظپط§ظ„â€Œظ‡ط§غŒ ط´ظ…ط§</div>
    </div>
  </div>

  <?php if (empty($phone)): ?>
    <!-- Not logged in -->
    <div class="login-card">
      <div style="font-size:2.5rem;margin-bottom:10px">ًں”’</div>
      <h3>ط¨ط±ط§غŒ ظپط§ظ„ ط­ط§ظپط¸ ظˆط§ط±ط¯ ط´ظˆغŒط¯</h3>
      <p>ط§ط¨طھط¯ط§ ط¨ط§غŒط¯ ظˆط§ط±ط¯ ط­ط³ط§ط¨ ط®ظˆط¯ ط´ظˆغŒط¯ طھط§ ط¨طھظˆط§ظ†غŒط¯ ظپط§ظ„ ط±ظˆط²ط§ظ†ظ‡ ط®ظˆط¯ ط±ط§ ط¯ط±غŒط§ظپطھ ع©ظ†غŒط¯</p>
      <button type="button" class="btn" onclick="openSheet('reg')">ورود / ثبت‌نام</button>
    </div>

  <?php elseif ($already_opened && $today_verse): ?>
    <!-- Already opened today -->
    <div class="verse-card">
      <div class="quran-icon">ًں“–</div>
      <div class="verse-text"><?= nl2br(htmlspecialchars(implode("\n", verse_parts($today_verse)), ENT_QUOTES, 'UTF-8')) ?></div>
      <div class="meaning-box">
        <h4>ًں”چ طھظپط³غŒط± ط¨غŒطھ</h4>
        <p><?= nl2br(htmlspecialchars($today_verse['meaning_short'], ENT_QUOTES, 'UTF-8')) ?></p>
      </div>
    </div>

    <div class="already-msg">
      <div class="icon">âک•</div>
      <p>ظپط§ظ„ ط§ظ…ط±ظˆط² ط´ظ…ط§ ظ‚ط¨ظ„ط§ظ‹ ط«ط¨طھ ط´ط¯ظ‡ ط§ط³طھ.<br>ظپط±ط¯ط§ ط¯ظˆط¨ط§ط±ظ‡ ظ…ط±ط§ط¬ط¹ظ‡ ع©ظ†غŒط¯!</p>
    </div>

  <?php elseif ($total_verses == 0): ?>
    <div class="empty-state">
      <div style="font-size:3rem;margin-bottom:12px">ًں“–</div>
      <p>ظ‡ظ†ظˆط² ط¨غŒطھغŒ ط¨ظ‡ ط¯غŒظˆط§ظ† ط­ط§ظپط¸ ط§ط¶ط§ظپظ‡ ظ†ط´ط¯ظ‡ ط§ط³طھ</p>
    </div>

  <?php else: ?>
    <!-- Open fortune -->
    <div id="openSection">
      <button class="open-btn" id="openBtn" onclick="openFortune()">ًں“– ظپط§ظ„ ط­ط§ظپط¸ ط§ظ…ط±ظˆط² ط±ط§ ط¨ع¯غŒط±غŒط¯</button>
      <p style="font-size:.7rem;color:var(--text3);text-align:center;margin-top:10px">ظ‡ط± ط±ظˆط² ظپظ‚ط· غŒع© ط¨ط§ط± ط§ظ…ع©ط§ظ† ط¨ط§ط² ع©ط±ط¯ظ† ظپط§ظ„ ط¯ط§ط±غŒط¯</p>
    </div>

    <!-- Verse card (hidden initially) -->
    <div id="verseSection" style="display:none">
      <div class="verse-card">
        <div class="quran-icon">ًں“–</div>
        <div class="verse-text" id="verseText"></div>
        <div class="verse-num" id="verseNum"></div>
        <div class="meaning-box">
          <h4>ًں”چ طھظپط³غŒط± ط¨غŒطھ</h4>
          <p id="meaningText"></p>
        </div>
      </div>
    </div>
  <?php endif; ?>
</main>

<!-- Result Overlay -->
<div class="result-ov" id="resultOv">
  <div class="res-card" id="resCard"></div>
</div>

<!-- Sheet: Intention (نیت فال) -->
<div class="overlay" id="intentionOv" onclick="closeSheet('intention')"></div>
<div class="sheet" id="intentionSheet">
  <div class="handle"></div>
  <div class="sheet-title">🤲 نیت کن</div>
  <p style="font-size:.85rem;color:var(--text2);text-align:center;line-height:1.8;margin-bottom:18px"><?= nl2br(htmlspecialchars(sitak_intention_msg($conn), ENT_QUOTES, 'UTF-8')) ?></p>
  <button class="btn-p" onclick="confirmIntention()">نیت کردم 🤲</button>
</div>

<!-- Confetti -->
<div class="confetti-box" id="confettiBox"></div>

<script>
var DEFAULT_THEME='<?= $default_theme ?>';
var USER_PHONE='<?= addslashes($phone) ?>';
var IS_OPENED=<?= $already_opened ? 'true' : 'false' ?>;

(function(){var s=(window.__R||function(v){return v})(localStorage.getItem('sitak_theme')||DEFAULT_THEME);document.documentElement.setAttribute('data-theme',s);updThemeIcon(s)})();

// ط¨ط±ط±ط³غŒ ظ„ط§ع¯â€Œط§ظˆطھ ط§ط¬ط¨ط§ط±غŒ (ع©ط§ط±ط¨ط± ظ…ط³ط¯ظˆط¯ ط´ط¯ظ‡)
<?php if ($force_logout_popup): ?>
(function(){
  var t=document.createElement('div');
  t.className='toast';
  t.style.cssText='position:fixed;bottom:90px;left:50%;transform:translateX(-50%);background:linear-gradient(135deg,var(--gold,#ecb534),var(--gold-dark,#c49625));border:none;color:#fff;padding:13px 22px;border-radius:16px;font-size:.82rem;font-weight:500;z-index:500;opacity:0;transition:all .35s cubic-bezier(.34,1.56,.64,1);max-width:min(88%,420px);text-align:center;box-shadow:var(--shadow-lg);white-space:pre-line';
  t.textContent='ط´ظ…ط§ ط§ط² ط­ط³ط§ط¨â€Œع©ط§ط±ط¨ط±غŒ ط®ط§ط±ط¬ ط´ط¯غŒط¯';
  document.body.appendChild(t);
  void t.offsetWidth;
  t.style.opacity='1';
  t.style.transform='translateX(-50%) translateY(0)';
  setTimeout(function(){
    t.style.opacity='0';
    t.style.transform='translateX(-50%) translateY(100px)';
    setTimeout(function(){t.remove()},350);
  },3000);
})();
<?php endif; ?>


const BLOCKED_MSG = 'متاسفانه در حال حاضر امکان انجام این فعالیت وجود ندارد.\nلطفاً در زمانی دیگر مجدداً تلاش کنید.';


function showBlockedAndLogout(){
  fetch('ajax.php?action=logout_user',{credentials:'same-origin'});
  var t=document.createElement('div');
  t.className='toast';
  t.style.cssText='position:fixed;bottom:90px;left:50%;transform:translateX(-50%);background:linear-gradient(135deg,var(--gold,#ecb534),var(--gold-dark,#c49625));border:none;color:#fff;padding:13px 22px;border-radius:16px;font-size:.82rem;font-weight:500;z-index:500;opacity:0;transition:all .35s cubic-bezier(.34,1.56,.64,1);max-width:min(88%,420px);text-align:center;box-shadow:var(--shadow-lg);white-space:pre-line';
  t.textContent = BLOCKED_MSG;
  document.body.appendChild(t);
  void t.offsetWidth;
  t.style.opacity='1';t.style.transform='translateX(-50%) translateY(0)';
  setTimeout(function(){
    t.style.opacity='0';t.style.transform='translateX(-50%) translateY(100px)';
    setTimeout(function(){t.remove();location.reload()},350);
  },3000);
}

function logoutAndShowBlocked(){
  showBlockedAndLogout();
}

/* â”€â”€ Open Fortune â”€â”€ */
function confirmIntention(){
  closeSheet('intention');
  _doOpenFortune();
}
function openFortune(){
  if(IS_OPENED){return}
  openSheet('intention');
}
function _doOpenFortune(){
  if(IS_OPENED){return}
  var btn=document.getElementById('openBtn');
  btn.disabled=true;btn.textContent='در حال باز کردن...';
  fetch('ajax.php?action=hafez_open',{method:'GET'})
    .then(function(r){return r.json()})
    .then(function(d){
      if(d.blocked === true){
        showBlockedAndLogout();
        return;
      }
      if(d.success){
        IS_OPENED=true;
        document.getElementById('openSection').style.display='none';
        document.getElementById('verseSection').style.display='block';
        document.getElementById('verseText').innerHTML=esc((d.verse.verse_m1||'')+'\n'+(d.verse.verse_m2||'')).replace(/\n/g,'<br>');
        document.getElementById('meaningText').innerHTML=esc(d.verse.meaning_short).replace(/\n/g,'<br>');
        showResult(d.verse);
        spawnConfetti();
      }else{
        showToast(d.message||'خطا در دریافت فال');
        btn.disabled=false;btn.textContent='📖 فال حافظ امروز را بگیرید';
      }
    })
    .catch(function(){showToast('خطا در ارتباط با سرور');btn.disabled=false;btn.textContent='📖 فال حافظ امروز را بگیرید';});
}

/* â”€â”€ Result Overlay â”€â”€ */
function showResult(verse){
  var card=document.getElementById('resCard');
  card.innerHTML='<div class="res-icon">✨</div><h3>فال حافظ شما</h3><div class="res-desc" style="margin-top:12px;font-style:italic;line-height:2">'+esc((verse.verse_m1||'')+'\n'+(verse.verse_m2||'')).replace(/\n/g,'<br>')+'</div><button class="btn-close" onclick="closeResult()">خواندم ✨</button>';
  document.getElementById('resultOv').classList.add('open');
}
function closeResult(){document.getElementById('resultOv').classList.remove('open')}

/* â”€â”€ Confetti â”€â”€ */
function spawnConfetti(){
  var box=document.getElementById('confettiBox');
  var colors=['#ecb534','#f5d076','#c49625','#30d158','#34d399','#ff453a','#0a84ff','#ff9f0a'];
for(var i=0;i<50;i++){
    var el=document.createElement('div');
    el.className='confetti';
    el.style.left=Math.random()*100+'%';
    el.style.width=(4+Math.random()*6)+'px';
    el.style.height=(8+Math.random()*14)+'px';
    el.style.background=colors[Math.floor(Math.random()*colors.length)];
    el.style.animationDuration=(2+Math.random()*2)+'s';
    el.style.animationDelay=(Math.random()*0.6)+'s';
    box.appendChild(el);
  }
  setTimeout(function(){box.innerHTML=''},4000);
}
</script>

<!-- Bottom Navigation -->
<!-- Sheet: Register -->
<?php include 'auth_sheet.php'; ?>
<div class="toast" id="toast"></div>

<nav class="bottom-nav">
  <a href="index.php">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
    <span>ط®ط§ظ†ظ‡</span>
  </a>
  <a href="chance.php">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><path d="M8.5 8.5h.01"/><path d="M15.5 8.5h.01"/><path d="M12 12h.01"/><path d="M8.5 15.5h.01"/><path d="M15.5 15.5h.01"/></svg>
    <span>ط´ط§ظ†ط³</span>
  </a>
  <?php if ($loyalty_enabled === '1'): ?>
  <a href="loyalty.php">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
    <span>ظˆظپط§ط¯ط§ط±غŒ</span>
  </a>
  <?php endif; ?>
  <?php if (($blog_enabled ?? '1') === '1'): ?>
  <a href="blog.php">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
    <span>ط¨ظ„ط§ع¯</span>
  </a>
  <?php endif; ?>
  <?php if (($shop_enabled ?? '1') === '1'): ?>
  <a href="shop.php">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
    <span>ظ…ظ†ظˆ</span>
  </a>
  <?php endif; ?>
  <?php if (($orders_tab_enabled ?? '1') === '1'): ?>
  <a href="orders.php">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="8" y="2" width="8" height="4" rx="1"/><rect x="4" y="4" width="16" height="18" rx="2"/><path d="M9 14l2 2 4-4"/></svg>
    <span>ط³ظپط§ط±ط´</span>
  </a>
  <?php endif; ?>
</nav>

<script>
var DEFAULT_THEME='<?= $default_theme ?>';
(function(){var s=(window.__R||function(v){return v})(localStorage.getItem('sitak_theme')||DEFAULT_THEME);document.documentElement.setAttribute('data-theme',s);updThemeIcon(s)})();
function toggleTheme(){var c=document.documentElement.getAttribute('data-theme');var n=c==='dark'?'light':'dark';document.documentElement.setAttribute('data-theme',n);localStorage.setItem('sitak_theme',n);updThemeIcon(n);var mc=document.getElementById('metaTheme');if(mc)mc.setAttribute('content',n==='dark'?'#0a0a0c':'#fff')}
function updThemeIcon(t){document.getElementById('themeIcon').textContent=t==='dark'?'âک€ï¸ڈ':'ًںŒ™'}
function pn(n){return String(n).replace(/\d/g,function(d){return 'غ°غ±غ²غ³غ´غµغ¶غ·غ¸غ¹'[d]})}
function esc(s){if(!s)return'';return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')}
document.querySelectorAll('.bottom-nav a').forEach(function(a){
  if(a.pathname===window.location.pathname){a.classList.add('active')}else{a.classList.remove('active')}
});
</script>
  <?php include 'cart_sheet.php'; ?>
</body>
  <script>
  /* openSheet/closeSheet/refreshCaptcha/doRegister â€” ط¯ط± auth_sheet.php */
  </script>
  </html>
