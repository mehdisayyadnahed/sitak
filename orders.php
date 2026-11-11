<?php
require_once 'config.php';
$custom_emoji = getSetting($conn, 'custom_emoji', '🌐');

// کارت‌به‌کارت: لغو خودکار سفارش‌هایی که مهلت ثبت اطلاعات واریزشان تمام شده + تنظیمات پرداخت
sitak_expire_card_orders($conn);
$card_pay = sitak_card_payment_info($conn);

$shop_enabled = getSetting($conn, 'shop_enabled', '1');
$orders_tab_enabled = getSetting($conn, 'orders_tab_enabled', '1');
$orders_tab_enabled = getSetting($conn, 'orders_tab_enabled', '1');
if ($orders_tab_enabled !== '1') { header('Location: index.php'); exit; }

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

$default_theme  = getSetting($conn, 'default_theme', 'auto');
$custom_favicon = getSetting($conn, 'custom_favicon', '');
$custom_logo    = getSetting($conn, 'custom_logo', '');
$loyalty_enabled = getSetting($conn, 'loyalty_enabled', '1');
$site_name       = getSetting($conn, 'site_name', 'سای‌تک');
$site_slogan     = getSetting($conn, 'site_slogan', 'یک وبسایت تک');
$chance_enabled = getSetting($conn, 'chance_enabled', '1');
$blog_enabled = getSetting($conn, 'blog_enabled', '1');
$shop_enabled = getSetting($conn, 'shop_enabled', '1');
$orders_tab_enabled = getSetting($conn, 'orders_tab_enabled', '1');
$orders_page_title = getSetting($conn, 'orders_page_title', 'سفارشات من');
$orders_page_description = getSetting($conn, 'orders_page_description', '');

// دریافت سفارشات کاربر
$orders = [];
if (!empty($phone)) {
    $os = $conn->prepare("SELECT * FROM orders WHERE user_phone = ? ORDER BY created_at DESC");
    $os->bind_param("s", $phone);
    $os->execute();
    $res = $os->get_result();
    while ($row = $res->fetch_assoc()) {
        $row['items_parsed'] = json_decode($row['items'], true);
        $orders[] = $row;
    }
}

// کپچا برای شیت ورود/ثبت‌نام
$captcha_n1 = rand(1, 10);
$captcha_n2 = rand(1, 10);
$_SESSION['captcha_answer'] = $captcha_n1 + $captcha_n2;
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="<?= htmlspecialchars($default_theme, ENT_QUOTES, 'UTF-8') ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<meta name="theme-color" content="#ffffff" id="metaTheme">
<?= theme_head_script('sitak_theme', $default_theme) ?>
<?= sitak_head_meta($conn, ['title' => 'سفارشات']) ?>
<title>سفارشات | <?= htmlspecialchars($site_name ?? 'سای‌تک', ENT_QUOTES, 'UTF-8') ?></title>
<link href="assets/fonts/vazirmatn.css" rel="stylesheet">
<style>
:root,[data-theme="light"]{--bg:#f5f5f7;--surface:#fff;--card:#fff;--card-el:#f0eff2;--border:rgba(255,255,255,.08);--border-gold:rgba(236,181,52,.22);--text:#1d1d1f;--text2:#6e6e73;--text3:#aeaeb2;--header-bg:rgba(255,255,255,.82);--shadow-sm:0 1px 3px rgba(0,0,0,.06);--shadow-md:0 4px 14px rgba(0,0,0,.06);--sheet-shadow:0 -10px 40px rgba(0,0,0,.08);--overlay-bg:rgba(0,0,0,.3);--toast-bg:#fff;--input-bg:#f0eff2;--green:#30d158;--green-text:#1a8a3f;--green-bg:rgba(48,209,88,.07);--red:#ff453a;--red-bg:rgba(255,69,58,.06);--gold:#ecb534;--gold-dark:#c49625;--gold-subtle:rgba(236,181,52,.07);--gold-glow:rgba(236,181,52,.12);--blue:#0a84ff;--blue-bg:rgba(10,132,255,.06);--r:20px;--rs:14px;--rss:10px;--safe-t:env(safe-area-inset-top,0px);--safe-b:env(safe-area-inset-bottom,0px);--font:'Vazirmatn',-apple-system,BlinkMacSystemFont,sans-serif}
[data-theme="dark"]{--bg:#0a0a0c;--surface:#161618;--card:#1c1c1e;--card-el:#2c2c2e;--border:rgba(255,255,255,.08);--border-gold:rgba(236,181,52,.15);--text:#f0ece4;--text2:#9e9888;--text3:#5e5a52;--header-bg:rgba(10,10,12,.88);--shadow-sm:0 1px 3px rgba(0,0,0,.3);--shadow-md:0 4px 14px rgba(0,0,0,.3);--sheet-shadow:0 -10px 40px rgba(0,0,0,.35);--overlay-bg:rgba(0,0,0,.5);--toast-bg:#2c2c2e;--input-bg:#2c2c2e;--green:#34d399;--green-text:#34d399;--green-bg:rgba(52,211,153,.08);--red:#f87171;--red-bg:rgba(248,113,113,.08);--blue:#60a5fa;--blue-bg:rgba(96,165,250,.08)}
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

.content{position:relative;z-index:1;padding:12px 14px 100px;max-width:480px;margin:0 auto}
.hero{text-align:center;padding:28px 12px 20px}.hero h2{font-size:1.35rem;font-weight:900;color:var(--gold-dark);margin-bottom:8px;line-height:1.6}.hero p{font-size:.82rem;color:var(--text2);line-height:1.7;max-width:340px;margin:0 auto 12px}

.order-card{background:var(--card);border:1px solid var(--border-gold);border-radius:var(--r);padding:16px;margin-bottom:12px;box-shadow:var(--shadow-sm);transition:border-color .2s,background .35s}
.order-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px}
.order-id{font-size:.78rem;font-weight:700;color:var(--gold-dark)}
.order-date{font-size:.68rem;color:var(--text3)}
.order-status{border:1px solid currentColor;min-height:28px;display:inline-flex;align-items:center;gap:4px;font-size:.72rem;font-weight:600;padding:4px 10px;border-radius:8px;vertical-align:middle}
.os-pending{background:var(--gold-subtle);color:var(--gold-dark)}
.os-confirmed{background:var(--blue-bg);color:var(--blue)}
.os-shipped{background:var(--blue-bg);color:var(--blue)}
.os-delivered{background:var(--green-bg);color:var(--green-text)}
.os-cancelled{background:var(--red-bg);color:var(--red)}
.os-awaiting{background:var(--blue-bg);color:var(--blue)}
.order-dd{width:100%}
.order-code{font-size:.7rem;color:var(--text3);margin-top:3px;font-weight:600}
.order-sum-items{font-size:.8rem;color:var(--text2);margin:8px 0 4px;line-height:1.7}
.order-sum{list-style:none;cursor:pointer}
.order-sum::-webkit-details-marker{display:none}
.order-chev{display:inline-block;transition:transform .2s;margin-inline-start:4px;color:var(--text3)}
details[open] .order-chev{transform:rotate(180deg)}
.order-body{margin-top:12px;padding-top:10px;border-top:1px dashed var(--border)}
.od-title{font-size:.78rem;font-weight:700;color:var(--gold-dark);margin:12px 0 6px}
.od-row{display:flex;justify-content:space-between;gap:12px;font-size:.78rem;padding:4px 0;color:var(--text2)}
.od-row span:last-child{color:var(--text);text-align:left}
.od-total{font-weight:700;color:var(--text);border-top:1px solid var(--border);margin-top:6px;padding-top:8px}
.order-items{margin:10px 0;padding:10px 0;border-top:1px solid var(--border);border-bottom:1px solid var(--border)}
.order-item{display:flex;justify-content:space-between;align-items:center;padding:5px 0;font-size:.82rem}
.order-item .oi-name{flex:1;font-weight:500}
.order-item .oi-qty{font-size:.72rem;color:var(--text3);margin:0 8px}
.order-item .oi-price{font-weight:700;color:var(--gold-dark);white-space:nowrap}
.order-foot{display:flex;justify-content:space-between;align-items:center;margin-top:10px}
.order-total{font-size:1rem;font-weight:800;color:var(--gold-dark)}
.order-note{font-size:.72rem;color:var(--text2);margin-top:8px;line-height:1.6}
.empty-state{text-align:center;padding:60px 20px;color:var(--text3);font-size:.88rem}
.login-prompt{text-align:center;padding:60px 20px}
.login-prompt p{font-size:.82rem;color:var(--text2);margin-bottom:20px}
.login-btn{display:inline-flex;align-items:center;gap:6px;padding:12px 28px;background:linear-gradient(135deg,var(--gold),var(--gold-dark));color:#fff;border:none;border-radius:var(--rss);font-family:var(--font);font-size:.88rem;font-weight:700;cursor:pointer;text-decoration:none;}
.fi{width:100%;padding:13px 14px;background:var(--bg);border:1.5px solid var(--border);border-radius:var(--rss);color:var(--text);font-family:var(--font);font-size:.92rem;outline:none;transition:border-color .2s,background .3s;margin-bottom:12px}
.fi::placeholder{color:var(--text3)}.fi:focus{border-color:var(--gold)}
.name-row{display:flex;gap:8px;margin-top:14px}
.name-row:first-of-type{margin-top:0}
.name-row .fi,.name-row select{margin-bottom:0}
.profile-field{display:flex;flex-direction:column;gap:5px;margin-top:14px}
.profile-section-title{font-size:.82rem;font-weight:700;color:var(--gold-dark);margin:16px 0 10px;padding-bottom:7px;border-bottom:1px solid var(--border)}
.profile-fields{display:grid;grid-template-columns:1fr;gap:12px}
.profile-field{margin-top:0;min-width:0}
.profile-field.full{grid-column:auto}
.profile-field label{font-size:.75rem;font-weight:600;color:var(--text2);line-height:1.5}
.profile-field label em{font-size:.65rem;color:var(--gold-dark);font-style:normal;font-weight:500}
.profile-field .fi{margin-bottom:0;width:100%;min-height:46px}.profile-field .fi.profile-invalid{border-color:var(--red,#ff453a)!important;box-shadow:0 0 0 2px rgba(255,69,58,.12)}
.profile-field textarea.fi{min-height:70px;padding-top:11px}
.profile-phone{background:var(--card-el)!important;color:var(--text2);cursor:not-allowed}
.profile-fields #plaqueInput,.profile-fields #unitInput,.profile-fields #postalInput{direction:ltr;text-align:left}
.profile-actions{margin-top:16px}
.name-row .fi{margin-bottom:0;flex:1;height:46px;padding:0 14px;box-sizing:border-box}
.name-save{height:46px;padding:0 20px;background:linear-gradient(135deg,var(--gold),var(--gold-dark));color:#fff;border:none;border-radius:var(--rss);font-family:var(--font);font-size:.82rem;font-weight:700;cursor:pointer;;flex-shrink:0;transition:transform .1s,box-shadow .1s}
.name-save:active{}
.name-hint{font-size:.68rem;color:var(--text3);margin-top:8px;line-height:1.6}

/* ── Register Sheet ── */
.overlay{position:fixed;inset:0;background:var(--overlay-bg);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);z-index:100;opacity:0;pointer-events:none;transition:opacity .3s}
.overlay.open{opacity:1;pointer-events:auto}
.sheet{position:fixed;bottom:0;left:0;right:0;z-index:101;background:var(--surface);border-radius:22px 22px 0 0;padding:0 18px 18px;padding-bottom:calc(18px + var(--safe-b));max-height:82vh;overflow-y:auto;transform:translateY(100%);transition:transform .38s cubic-bezier(.32,.72,0,1),background .35s;-webkit-overflow-scrolling:touch;box-shadow:var(--sheet-shadow)}
.sheet.open{transform:translateY(0)}
.handle{width:34px;height:4px;background:#d1d1d6;border-radius:4px;margin:10px auto 18px}
.sheet-title{font-size:1.05rem;font-weight:700;text-align:center;margin-bottom:16px}
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

.bottom-nav{position:fixed;bottom:0;left:0;right:0;z-index:100;background:var(--surface);border-top:1px solid var(--border);display:flex;justify-content:space-around;padding:8px 0;padding-bottom:calc(8px + var(--safe-b));border-top-left-radius:16px;border-top-right-radius:16px}.bottom-nav a{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;color:var(--text2);text-decoration:none;font-size:.6rem;min-width:44px;min-height:44px;padding:4px 0;transition:color .2s;white-space:nowrap}.bottom-nav a.active{color:var(--gold-dark)}.bottom-nav svg{width:22px;height:22px;transition:transform .2s}.bottom-nav a.active svg{color:var(--gold-dark);transform:scale(1.1)}@media(min-width:481px){
  html{scrollbar-gutter:stable}
  html,body{max-width:480px;margin:0 auto}
  body{box-shadow:0 0 0 1px var(--border),0 0 50px rgba(0,0,0,.1)}
  .bottom-nav,.admin-nav{left:50%;right:auto;transform:translateX(-50%);width:480px}
  body::before{left:50%;right:auto;transform:translateX(-50%);width:480px;height:480px;top:-20%}
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
      <div class="brand-icon">🛍️</div>
    <?php endif; ?>
    <div class="brand-text">
      <h1><?= sitak_brand_html($site_name ?? '') ?></h1>
      <p><?= htmlspecialchars($site_slogan ?? 'یک وبسایت تک', ENT_QUOTES, 'UTF-8') ?></p>
    </div>
  </div>
  <div class="user-area" id="userArea">
    <button class="header-icon-btn" onclick="toggleTheme()" aria-label="تغییر تم"><span id="themeIcon">🌙</span></button>
    <?php if ($user_info): ?>
      <a href="chance.php" class="header-icon-btn" title="شانس روزانه">🎲<span class="roll-count"><?= pn($user_info['total_rolls']) ?></span></a>
      <?php if (($shop_enabled ?? '1') === '1'): ?>
      <a href="#" onclick="openSheet('cart');return false" class="header-icon-btn" title="سبد خرید">🛒<span class="roll-count hidden" id="cartCount"></span></a>
      <?php endif; ?>
      <?php if (getSetting($conn, 'sms_password_login_enabled', '0') === '1'): ?><a href="#" onclick="openSheet('pwChange');return false" class="header-icon-btn" title="تغییر گذرواژه ورود">🔑</a><?php endif; ?>
      <a href="index.php?logout_user=true" onclick="openSheet('logout');return false" class="header-icon-btn" title="خروج" style="color:#ff453a"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></a>
    <?php else: ?>
      <a href="#" onclick="openSheet('reg');return false" class="header-icon-btn" title="ورود" style="font-size:.7rem;font-weight:700">ورود</a>
    <?php endif; ?>
  </div>
</header>

<main class="content">
  <section class="hero"><h2>📦 <?= htmlspecialchars($orders_page_title, ENT_QUOTES, 'UTF-8') ?></h2><?php if ($orders_page_description !== ''): ?><p><?= nl2br(htmlspecialchars($orders_page_description, ENT_QUOTES, 'UTF-8')) ?></p><?php endif; ?></section>

  <?php
$pm_key = getSetting($conn, 'map_browser_key', '');
$pm_on = getSetting($conn, 'map_enabled', '0') === '1' && $pm_key !== '';
$pm_lat = null; $pm_lng = null;
if (!empty($phone)) { $pmq = $conn->prepare("SELECT delivery_lat, delivery_lng FROM users WHERE phone=? LIMIT 1"); $pmq->bind_param('s', $phone); $pmq->execute(); $pmr = $pmq->get_result()->fetch_assoc(); if ($pmr && $pmr['delivery_lat'] !== null && $pmr['delivery_lng'] !== null) { $pm_lat = (float)$pmr['delivery_lat']; $pm_lng = (float)$pmr['delivery_lng']; } }
$disc_on = getSetting($conn, 'discount_enabled', '0') === '1';
$pm_def_lat = (float)getSetting($conn, 'map_default_lat', '32.4279'); $pm_def_lng = (float)getSetting($conn, 'map_default_lng', '53.6880'); $pm_def_zoom = (int)getSetting($conn, 'map_default_zoom', '5');
?>
<style>
/* ── کارت‌به‌کارت: تایمر، فرم ثبت اطلاعات واریز و تقویم شمسی ── */
.cp-chip{display:inline-flex;align-items:center;gap:4px;background:var(--gold-subtle,rgba(236,181,52,.1));border:1px solid var(--border-gold,rgba(236,181,52,.3));color:var(--gold-dark,#c49625);border-radius:999px;padding:2px 9px;font-size:.66rem;font-weight:700;margin-right:6px;vertical-align:middle;direction:ltr;white-space:nowrap}
.cp-chip b{font-weight:800;letter-spacing:.5px}
.cp-chip.cp-dead{background:var(--red-bg,rgba(255,69,58,.08));border-color:rgba(255,69,58,.3);color:var(--red,#ff453a)}
.cp-chip.cp-done{background:rgba(52,199,89,.08);border-color:rgba(52,199,89,.35);color:#248a3d;direction:rtl}
.cardpay-box{margin-top:10px;background:var(--gold-subtle,rgba(236,181,52,.06));border:1.5px solid var(--border-gold,rgba(236,181,52,.25));border-radius:var(--rss,12px);padding:14px}
.cardpay-title{font-size:.8rem;font-weight:800;color:var(--gold-dark,#c49625);margin-bottom:8px}
.cardpay-hint{font-size:.7rem;color:var(--text2);line-height:1.8;margin-bottom:10px}
.cp-profile-grid .profile-field label{font-size:.72rem}
.cp-dest{background:var(--card-el,#fff);border:1px solid var(--border);border-radius:10px;padding:9px 12px;margin-bottom:8px;display:flex;align-items:center;justify-content:space-between;gap:8px;font-size:.76rem;color:var(--text2)}
.cp-dest .cpd-num{font-weight:700;direction:ltr;letter-spacing:.5px;color:var(--text);display:inline-block}
.cp-mini-copy{padding:5px 10px;border-radius:8px;border:1px solid var(--border-gold,rgba(236,181,52,.35));background:var(--gold-subtle,rgba(236,181,52,.08));color:var(--gold-dark,#c49625);font-size:.66rem;font-weight:700;cursor:pointer;font-family:var(--font);flex-shrink:0}
.cp-submit{margin-top:12px;width:100%;padding:12px;background:linear-gradient(135deg,var(--gold,#ecb534),var(--gold-dark,#c49625));color:#fff;border:none;border-radius:var(--rss,10px);font-family:var(--font);font-size:.88rem;font-weight:700;cursor:pointer}
.cp-submit:disabled{opacity:.55;cursor:not-allowed}
.cp-datetime{display:flex;gap:6px;align-items:stretch}
.cp-datetime .fi{flex:1;min-width:0;margin-bottom:0;height:48px;min-height:48px;padding:0 14px}
.jdp-cal{border:1px solid var(--border);background:var(--card-el,#f0eff2);border-radius:10px;width:48px;min-width:48px;height:48px;cursor:pointer;font-size:.95rem;flex-shrink:0;color:var(--text);display:flex;align-items:center;justify-content:center;box-sizing:border-box;font-family:var(--font)}
.jdp-pop{position:absolute;z-index:600;background:var(--card,#fff);border:1.5px solid var(--border-gold,rgba(236,181,52,.4));border-radius:14px;box-shadow:0 10px 30px rgba(0,0,0,.18);padding:10px;width:264px;max-width:calc(100vw - 20px);direction:rtl;font-family:var(--font);box-sizing:border-box}
.jdp-hdr{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;font-weight:700;font-size:.82rem;color:var(--gold-dark,#c49625)}
.jdp-hdr button{border:1px solid var(--border);background:var(--card-el,#f0eff2);border-radius:8px;width:28px;height:28px;cursor:pointer;font-size:.8rem;color:var(--text);font-family:var(--font)}
.jdp-wd,.jdp-days{display:grid;grid-template-columns:repeat(7,1fr);gap:3px;text-align:center}
.jdp-wd span{font-size:.62rem;color:var(--text3);padding:3px 0}
.jdp-day{border:none;background:transparent;border-radius:8px;padding:6px 0;font-size:.75rem;cursor:pointer;font-family:var(--font);color:var(--text)}
.jdp-day:hover{background:var(--gold-subtle,rgba(236,181,52,.1))}
.jdp-today{border:1px solid var(--border-gold,rgba(236,181,52,.5));font-weight:700}
.jdp-foot{display:flex;justify-content:space-between;margin-top:8px}
.jdp-foot button{border:none;background:var(--gold-subtle,rgba(236,181,52,.1));color:var(--gold-dark,#c49625);border-radius:8px;padding:6px 12px;font-size:.7rem;font-weight:700;cursor:pointer;font-family:var(--font)}
.jdp-pop.jtp-pop{width:252px}
.jtp-grid{display:grid;grid-template-columns:repeat(6,1fr);gap:4px;margin-top:6px}
.jtp-cell{border:none;background:transparent;border-radius:8px;padding:7px 0;font-size:.75rem;cursor:pointer;font-family:var(--font);color:var(--text)}
.jtp-cell:hover{background:var(--gold-subtle,rgba(236,181,52,.1))}
.jtp-cell.jtp-sel{border:1px solid var(--border-gold,rgba(236,181,52,.5));font-weight:700;color:var(--gold-dark,#c49625);background:var(--gold-subtle,rgba(236,181,52,.1))}
.jdp-hdr .jtp-title{flex:1;text-align:center}
</style>
<?php if (!empty($phone)): ?>
  <details class="order-dd order-card" style="margin-bottom:12px">
    <summary class="order-sum">
      <div class="order-head" style="margin-bottom:0">
        <span style="font-size:.82rem;font-weight:700;color:var(--gold-dark)">👤 مشخصات و نشانی پیشفرض دریافت</span>
        <span class="order-chev">▾</span>
      </div>
    </summary>
    <div class="order-body">
    <div class="profile-section-title">اطلاعات هویتی</div>
    <div class="profile-fields">
      <div class="profile-field"><label>نام <em>(ضروری)</em></label><input type="text" class="fi" id="firstNameInput" maxlength="40" value="<?= htmlspecialchars($user_info['user']['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="نام"></div>
      <div class="profile-field"><label>نام خانوادگی <em>(ضروری)</em></label><input type="text" class="fi" id="lastNameInput" maxlength="60" value="<?= htmlspecialchars($user_info['user']['last_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="نام خانوادگی"></div>
      <div class="profile-field full"><label>شماره تماس</label><input type="tel" class="fi profile-phone" id="phoneInput" value="<?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?>" readonly dir="ltr" style="text-align:left"></div>
    </div>
    <div class="profile-section-title">نشانی شما</div>
    <div class="profile-fields">
      <div class="profile-field"><label>استان <em>(ضروری)</em></label><select class="fi" id="provinceInput"><option value="">انتخاب استان</option></select></div>
      <div class="profile-field"><label>شهر <em>(ضروری)</em></label><select class="fi" id="cityInput" disabled><option value="">ابتدا استان را انتخاب کنید</option></select></div>
      <div class="profile-field full"><label>نشانی <em>(ضروری)</em></label><textarea class="fi" id="addrInput" maxlength="255" placeholder="نشانی کامل"><?= htmlspecialchars($user_info['user']['address'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea></div>
      <div class="profile-field"><label>پلاک <em>(ضروری)</em></label><input type="text" class="fi" id="plaqueInput" value="<?= htmlspecialchars($user_info['user']['plaque'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="مثلاً ۱۲"></div>
      <div class="profile-field"><label>واحد <em>(ضروری)</em></label><input type="text" class="fi" id="unitInput" value="<?= htmlspecialchars($user_info['user']['unit'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="مثلاً ۳"></div>
      <div class="profile-field full"><label>کد پستی <em>(اختیاری)</em></label><input type="text" class="fi" id="postalInput" value="<?= htmlspecialchars($user_info['user']['postal_code'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="کد پستی ۱۰ رقمی" inputmode="numeric"></div>
      <div class="profile-field full"><label>توضیحات تکمیلی <em>(اختیاری)</em></label><textarea class="fi" id="extraInput" maxlength="500" placeholder="مثلاً زنگ واحد خراب است"><?= htmlspecialchars($user_info['user']['extra_notes'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea></div>
    </div>
<?php if ($pm_on): ?>
    <div class="profile-section-title">موقعیت روی نقشه <em style="font-style:normal;font-weight:400">(اختیاری)</em></div>
    <div style="margin:0 0 12px">
      <style>#profMap,#profMap .mapboxgl-canvas-container,#profMap canvas{border-radius:14px}</style>
      <div style="position:relative;height:220px;border-radius:14px;overflow:hidden;isolation:isolate;border:1px solid var(--border)">
        <div id="profMap" style="width:100%;height:100%"></div>
        <div style="position:absolute;left:50%;top:50%;transform:translate(-50%,-100%);pointer-events:none;font-size:28px;z-index:5">📍</div>
      </div>
      <button type="button" class="name-save" style="width:100%;margin-top:8px" onclick="profMapPick()">ثبت این موقعیت</button>
      <div class="conf-hint" id="profMapAddr" style="margin-top:6px"><?= $pm_lat !== null ? 'موقعیت ذخیره شده است' : 'موقعیت هنوز ثبت نشده است' ?></div>
      <input type="hidden" id="profLat" value="<?= $pm_lat !== null ? $pm_lat : '' ?>">
      <input type="hidden" id="profLng" value="<?= $pm_lng !== null ? $pm_lng : '' ?>">
    </div>
    <script>var PROF_MAP={key:<?= json_encode($pm_key, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT) ?>,lat:<?= $pm_lat !== null ? $pm_lat : 'null' ?>,lng:<?= $pm_lng !== null ? $pm_lng : 'null' ?>,defLat:<?= $pm_def_lat ?>,defLng:<?= $pm_def_lng ?>,defZoom:<?= $pm_def_zoom ?>};</script>
<?php endif; ?>
    <div class="profile-section-title">💳 اطلاعات پیش‌فرض واریزکننده (کارت‌به‌کارت)</div>
    <div class="profile-fields">
      <div class="profile-field full"><label>نام و نام خانوادگی صاحب حساب <em>(اختیاری)</em></label><input type="text" class="fi" id="payerNameInput" maxlength="120" value="<?= htmlspecialchars($user_info['user']['payer_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="مثلاً: علی محمدی"></div>
      <div class="profile-field"><label>شماره کارت <em>(اختیاری)</em></label><input type="text" class="fi" id="payerCardInput" dir="ltr" style="text-align:left" inputmode="numeric" maxlength="19" oninput="cpFmtCard(this)" value="<?= htmlspecialchars(sitak_format_card_number($user_info['user']['payer_card'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="xxxx-xxxx-xxxx-xxxx"></div>
      <div class="profile-field"><label>شماره شبا <em>(اختیاری)</em></label><input type="text" class="fi" id="payerIbanInput" dir="ltr" style="text-align:left" maxlength="32" oninput="cpFmtIban(this)" value="<?= htmlspecialchars(sitak_format_iban($user_info['user']['payer_iban'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="IR00-0000-..."></div>
    </div>
    <div class="name-hint" style="margin-bottom:12px">پس از اولین خرید کارت‌به‌کارت، اطلاعات واریز تو به‌صورت خودکار اینجا ذخیره می‌شود و در فرم سفارش‌های بعدی از قبل پر می‌شود؛ هر زمان خواستی می‌توانی ویرایشش کنی.</div>
    <div class="profile-actions"><button type="button" class="name-save" onclick="saveProfile()" style="width:100%">ذخیره اطلاعات</button></div>
    <div class="name-hint">نام و نشانی برای هماهنگی سفارش‌ها استفاده می‌شود.</div>
    </div>
  </details>
  <hr style="border:0;border-top:1px solid var(--border);margin:4px 0 18px">
  <?php endif; ?>

  <?php if (empty($phone)): ?>
    <div class="login-prompt">
      <div style="font-size:3rem;margin-bottom:12px">📦</div>
      <p>برای دیدن سفارشات خود ابتدا وارد شوید</p>
      <button type="button" class="login-btn" onclick="openSheet('reg')">ورود / ثبت‌نام</button>
    </div>
  <?php elseif (empty($orders)): ?>
    <div class="empty-state">
      <div style="font-size:3rem;margin-bottom:12px">📋</div>
      <p>هنوز سفارشی ثبت نکرده‌اید</p>
      <a href="shop.php" style="display:inline-block;margin-top:16px;color:var(--gold-dark);text-decoration:none;font-weight:600">مشاهده منو <?= htmlspecialchars($custom_emoji, ENT_QUOTES, 'UTF-8') ?></a>
    </div>
  <?php else: ?>
    <?php foreach ($orders as $o): ?>
    <?php
      $items = $o['items_parsed'] ?? [];
      $sv = sitak_order_status_view($o);
      $s_label = $sv['label'];
      $s_class = $sv['class'];
    ?>
    <div class="order-card">
      <details class="order-dd">
        <summary class="order-sum">
          <div class="order-head">
            <div>
              <div class="order-code">کد سفارش: <?= htmlspecialchars(pn($o['id']), ENT_QUOTES, 'UTF-8') ?>
                <?php if (($o['payment_method'] ?? '') === 'card' && ($o['status'] ?? '') === 'pending'): ?>
                  <?php if (empty($o['card_submitted_at']) && !empty($o['card_lock_expires'])): ?>
                    <span class="cp-chip" data-expires="<?= (int)strtotime($o['card_lock_expires']) ?>" title="مهلت واریز و ثبت اطلاعات">⏳ <b>--:--</b></span>
                  <?php elseif (!empty($o['card_submitted_at'])): ?>
                    <span class="cp-chip cp-done">✅ واریز ثبت شد — در انتظار تأیید مدیر</span>
                  <?php endif; ?>
                <?php endif; ?>
              </div>
              <div class="order-date"><?= pn(date('H:i', strtotime($o['created_at']))) ?> · <?= jdate_fa($o['created_at']) ?></div>
            </div>
            <span class="order-status <?= $s_class ?>"><?= $s_label ?></span>
          </div>
          <?php
            $sum_parts = [];
            foreach ($items as $it) { $sum_parts[] = htmlspecialchars(($it['name'] ?? '') . ' ×' . pn($it['qty'] ?? 1), ENT_QUOTES, 'UTF-8'); }
            $shown = array_slice($sum_parts, 0, 3);
            $more = count($sum_parts) - count($shown);
          ?>
          <div class="order-sum-items"><?= implode('، ', $shown) ?><?= $more > 0 ? ' و ' . pn($more) . ' مورد دیگر' : '' ?></div>
          <div class="order-foot">
            <span style="font-size:.78rem;color:var(--text2)">جمع کل</span>
            <span class="order-total"><?= pn(number_format($o['total_price'])) ?> تومان <span class="order-chev">▾</span></span>
          </div>
        </summary>
        <div class="order-body">
          <div class="od-title">🧾 جزئیات سفارش</div>
          <div class="order-items">
            <?php $items_sum = 0; foreach ($items as $it): $line = (int)($it['price'] ?? 0) * (int)($it['qty'] ?? 1); $items_sum += $line; ?>
            <div class="order-item">
              <span class="oi-name"><?= htmlspecialchars($it['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
              <span class="oi-qty">×<?= pn($it['qty'] ?? 1) ?></span>
              <span class="oi-price"><?= pn(number_format($line)) ?> تومان</span>
            </div>
            <?php endforeach; ?>
          </div>
          <div class="od-row"><span>جمع اقلام</span><span><?= pn(number_format($items_sum)) ?> تومان</span></div>
          <div class="od-row"><span>هزینه‌ی ارسال</span><span><?= ((int)($o['shipping_fee'] ?? 0)) > 0 ? pn(number_format((int)$o['shipping_fee'])).' تومان' : 'رایگان' ?></span></div>
          <?php if ($disc_on): ?>
          <div class="od-row"><span>تخفیف<?= !empty($o['discount_code']) ? ' ('.htmlspecialchars($o['discount_code'], ENT_QUOTES, 'UTF-8').')' : '' ?></span><span><?= (int)($o['discount_amount'] ?? 0) > 0 ? '-'.pn(number_format((int)$o['discount_amount'])).' تومان' : '—' ?></span></div>
          <?php endif; ?>
          <div class="od-row od-total"><span>مجموع پرداختی</span><span><?= pn(number_format($o['total_price'])) ?> تومان</span></div>

          <div class="od-title">📍 تحویل گیرنده</div>
          <div class="od-row"><span>نام</span><span><?= htmlspecialchars(trim(($o['user_name'] ?? '').' '.($o['recipient_last_name'] ?? '')), ENT_QUOTES, 'UTF-8') ?></span></div>
          <div class="od-row"><span>شماره تماس</span><span dir="ltr"><?= htmlspecialchars(pn($o['recipient_phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span></div>
          <div class="od-row"><span>نشانی</span><span><?= htmlspecialchars(trim(($o['recipient_province'] ?? '').'، '.($o['recipient_city'] ?? '').'، '.($o['recipient_address'] ?? '')), ENT_QUOTES, 'UTF-8') ?></span></div>
          <div class="od-row"><span>پلاک / واحد</span><span><?= htmlspecialchars(pn(($o['recipient_plaque'] ?? '').' / '.($o['recipient_unit'] ?? '')), ENT_QUOTES, 'UTF-8') ?></span></div>
          <?php if (!empty($o['recipient_postal_code'])): ?>
          <div class="od-row"><span>کد پستی</span><span dir="ltr"><?= htmlspecialchars(pn($o['recipient_postal_code']), ENT_QUOTES, 'UTF-8') ?></span></div>
          <?php endif; ?>

          <?php if ($pm_on && $o['delivery_lat'] !== null && $o['delivery_lat'] !== ''): ?>
          <div class="od-row"><span>نشانی روی نقشه</span><span><?= htmlspecialchars($o['delivery_address'] ?? '', ENT_QUOTES, 'UTF-8') ?></span></div>
          <div class="ord-map" data-lat="<?= (float)$o['delivery_lat'] ?>" data-lng="<?= (float)$o['delivery_lng'] ?>" data-id="<?= (int)$o['id'] ?>" style="height:200px;border-radius:14px;overflow:hidden;isolation:isolate;border:1px solid var(--border);margin:8px 0"></div>
          <style>.ord-map,.ord-map .mapboxgl-canvas-container,.ord-map canvas{border-radius:14px}</style>
          <?php endif; ?>

          <div class="od-title">🕒 زمان و پرداخت</div>
          <div class="od-row"><span>ثبت سفارش</span><span><?= jdatetime_fa($o['created_at']) ?></span></div>
          <?php $is_card_order = ($o['payment_method'] ?? '') === 'card'; ?>
          <div class="od-row"><span>روش پرداخت</span><span><?= $is_card_order ? 'کارت‌به‌کارت 💳' : (!empty($o['payment_ref_id']) || !empty($o['payment_authority']) ? 'درگاه آنلاین' : 'پرداخت پس از هماهنگی') ?></span></div>
          <?php if ($is_card_order): ?>
            <div class="od-row"><span>وضعیت پرداخت</span><span><?= !empty($o['payment_paid_at']) ? 'پرداخت تأیید شده ('.jdatetime_fa($o['payment_paid_at']).')' : (!empty($o['card_submitted_at']) ? 'در انتظار تأیید واریز توسط مدیر' : 'پرداخت نشده') ?></span></div>
            <?php if (!empty($o['card_submitted_at'])): ?>
            <div class="od-row"><span>واریزکننده</span><span><?= htmlspecialchars($o['card_payer_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></span></div>
            <?php if (!empty($o['card_payer_card'])): ?>
            <div class="od-row"><span>کارت واریز</span><span dir="ltr"><?= htmlspecialchars(pn(sitak_format_card_number($o['card_payer_card'])), ENT_QUOTES, 'UTF-8') ?></span></div>
            <?php endif; ?>
            <?php if (!empty($o['card_payer_iban'])): ?>
            <div class="od-row"><span>شبای واریز</span><span dir="ltr" style="font-size:.72rem"><?= htmlspecialchars(pn(sitak_format_iban($o['card_payer_iban'])), ENT_QUOTES, 'UTF-8') ?></span></div>
            <?php endif; ?>
            <?php if (!empty($o['card_paid_at'])): ?>
            <div class="od-row"><span>زمان تراکنش</span><span><?= jdatetime_fa($o['card_paid_at']) ?></span></div>
            <?php endif; ?>
            <div class="od-row"><span>وضعیت بررسی</span><span><?= ['submitted' => 'ثبت شد — در انتظار تأیید مدیر ⏳', 'approved' => 'تأیید شد ✅', 'rejected' => 'رد شد ❌'][$o['card_status'] ?? ''] ?? '—' ?></span></div>
            <?php endif; ?>
          <?php else: ?>
            <div class="od-row"><span>وضعیت پرداخت</span><span><?= !empty($o['payment_paid_at']) ? 'پرداخت شده ('.jdatetime_fa($o['payment_paid_at']).')' : 'پرداخت نشده' ?></span></div>
            <?php if (!empty($o['payment_ref_id'])): ?>
            <div class="od-row"><span>کد پیگیری</span><span dir="ltr"><?= htmlspecialchars(pn($o['payment_ref_id']), ENT_QUOTES, 'UTF-8') ?></span></div>
            <?php endif; ?>
          <?php endif; ?>

          <?php if (!empty($o['note'])): ?>
            <div class="order-note">📝 یادداشت خریدار: <?= htmlspecialchars($o['note'], ENT_QUOTES, 'UTF-8') ?></div>
          <?php endif; ?>
          <?php if (!empty($o['admin_note'])): ?>
            <div style="margin-top:10px;background:var(--gold-subtle);border:1px solid var(--border-gold);border-radius:var(--rss);padding:10px 12px">
              <div style="font-size:.7rem;font-weight:700;color:var(--gold-dark);margin-bottom:4px">📢 پیام از فروشنده:</div>
              <div style="font-size:.78rem;color:var(--text);line-height:1.7"><?= htmlspecialchars($o['admin_note'], ENT_QUOTES, 'UTF-8') ?></div>
            </div>
          <?php endif; ?>
<?php
          $rc_items = [];
          foreach ($items as $it) { $rc_items[] = ['name' => (string)($it['name'] ?? ''), 'qty' => pn($it['qty'] ?? 1), 'price' => pn(number_format((int)($it['price'] ?? 0) * (int)($it['qty'] ?? 1))) . ' تومان']; }
          $rc_data = [
            'id' => pn($o['id']),
            'date' => jdatetime_fa($o['created_at']),
            'status' => $s_label,
            'items' => $rc_items,
            'items_sum' => pn(number_format(array_sum(array_map(fn($x) => (int)($x['price'] ?? 0) * (int)($x['qty'] ?? 1), $items)))) . ' تومان',
            'discount' => ($disc_on && (int)($o['discount_amount'] ?? 0) > 0) ? '-' . pn(number_format((int)$o['discount_amount'])) . ' تومان' . (!empty($o['discount_code']) ? ' (' . $o['discount_code'] . ')' : '') : '—',
            'shipping' => ((int)($o['shipping_fee'] ?? 0)) > 0 ? pn(number_format((int)$o['shipping_fee'])) . ' تومان' : 'رایگان',
            'total' => pn(number_format($o['total_price'])) . ' تومان',
            'name' => trim(($o['user_name'] ?? '') . ' ' . ($o['recipient_last_name'] ?? '')),
            'phone' => pn($o['recipient_phone'] ?? ''),
            'address' => trim(($o['recipient_province'] ?? '') . '، ' . ($o['recipient_city'] ?? '') . '، ' . ($o['recipient_address'] ?? '') . ' پلاک ' . pn($o['recipient_plaque'] ?? '') . ' واحد ' . pn($o['recipient_unit'] ?? '')),
            'note' => (string)($o['note'] ?? ''), 'admin_note' => (string)($o['admin_note'] ?? ''),
            'map_lat' => ($pm_on && $o['delivery_lat'] !== null) ? (float)$o['delivery_lat'] : null,
            'map_lng' => ($pm_on && $o['delivery_lng'] !== null) ? (float)$o['delivery_lng'] : null,
            'map_address' => ($pm_on && $o['delivery_lat'] !== null) ? (string)($o['delivery_address'] ?? '') : '',
            'paid' => !empty($o['payment_paid_at']) ? 'پرداخت شده' : 'پرداخت نشده',
            'card' => $is_card_order ? [
                'payer_name' => (string)($o['card_payer_name'] ?? ''),
                'payer_card' => !empty($o['card_payer_card']) ? pn(sitak_format_card_number($o['card_payer_card'])) : '',
                'payer_iban' => !empty($o['card_payer_iban']) ? pn(sitak_format_iban($o['card_payer_iban'])) : '',
                'paid_at'    => !empty($o['card_paid_at']) ? jdatetime_fa($o['card_paid_at']) : '',
                'owner'      => (string)$card_pay['owner'],
                'owner_card' => $card_pay['card_formatted'] !== '' ? pn($card_pay['card_formatted']) : '',
                'owner_iban' => $card_pay['iban_formatted'] !== '' ? pn($card_pay['iban_formatted']) : '',
            ] : null,
          ];
          ?>
          <button type="button" class="login-btn" onclick="downloadReceipt(this)" data-receipt="<?= htmlspecialchars(json_encode($rc_data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8') ?>" style="margin-top:12px;padding:9px 14px;font-size:.74rem;width:100%;justify-content:center">دریافت رسید تصویری 🧾</button>
        </div>
      </details>
      <?php $oid = (int)$o['id']; ?>
      <?php if (($o['payment_method'] ?? '') === 'card' && ($o['status'] ?? '') === 'pending' && empty($o['card_submitted_at'])): ?>
        <div class="cardpay-box">
          <div class="cardpay-title">💳 واریز کارت‌به‌کارت و ثبت اطلاعات</div>
          <div class="cardpay-hint">
            مبلغ <b><?= pn(number_format((int)$o['total_price'])) ?> تومان</b> را به شماره‌های زیر واریز کن، سپس مشخصات تراکنش را در فرم زیر ثبت کن تا مدیر سفارش را تأیید کند.
            <?php if (!empty($o['card_lock_expires'])): ?>تا <b><?= pn(date('H:i', strtotime($o['card_lock_expires']))) ?> (<?= jdate_fa($o['card_lock_expires']) ?>)</b> مهلت داری؛ بعد از آن سفارش بطور خودکار لغو می‌شود.<?php endif; ?>
          </div>
          <?php if ($card_pay['owner'] !== ''): ?>
          <div class="cp-dest"><span>به نام: <b style="color:var(--text)"><?= htmlspecialchars($card_pay['owner'], ENT_QUOTES, 'UTF-8') ?></b></span></div>
          <?php endif; ?>
          <?php if ($card_pay['card_formatted'] !== ''): ?>
          <div class="cp-dest"><span>شماره کارت: <span class="cpd-num"><?= pn($card_pay['card_formatted']) ?></span></span><button type="button" class="cp-mini-copy" onclick="cpCopyText('<?= $card_pay['card'] ?>',this)">کپی 📋</button></div>
          <?php endif; ?>
          <?php if ($card_pay['iban_formatted'] !== ''): ?>
          <div class="cp-dest"><span>شماره شبا: <span class="cpd-num" style="font-size:.68rem"><?= pn($card_pay['iban_formatted']) ?></span></span><button type="button" class="cp-mini-copy" onclick="cpCopyText('<?= $card_pay['iban'] ?>',this)">کپی 📋</button></div>
          <?php endif; ?>
          <div class="profile-section-title" style="margin-top:14px">مشخصات تراکنش</div>
          <div class="profile-fields cp-profile-grid">
            <div class="profile-field"><label>نام و نام خانوادگی صاحب حساب <em>(ضروری)</em></label>
              <input type="text" class="fi" id="cpName<?= $oid ?>" maxlength="120" placeholder="نام واریزکننده" value="<?= htmlspecialchars($user_info['user']['payer_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div>
            <div class="profile-field"><label>شماره کارت واریز</label>
              <input type="text" class="fi" id="cpCard<?= $oid ?>" dir="ltr" style="text-align:left" inputmode="numeric" maxlength="19" placeholder="xxxx-xxxx-xxxx-xxxx" oninput="cpFmtCard(this)" value="<?= htmlspecialchars(sitak_format_card_number($user_info['user']['payer_card'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></div>
            <div class="profile-field"><label>شبای واریز</label>
              <input type="text" class="fi" id="cpIban<?= $oid ?>" dir="ltr" style="text-align:left" maxlength="32" placeholder="IR00-0000-..." oninput="cpFmtIban(this)" value="<?= htmlspecialchars(sitak_format_iban($user_info['user']['payer_iban'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></div>
            <div class="profile-field"><label>تاریخ تراکنش <em>(ضروری)</em></label>
              <div class="cp-datetime">
                <input type="text" class="fi jdp-open" id="cpDate<?= $oid ?>" readonly placeholder="۱۴۰۴/۰۸/۰۵" value="<?= jdate_fa(date('Y-m-d H:i:s')) ?>" onclick="jdpOpen(this)" style="cursor:pointer;text-align:center">
                <button type="button" class="jdp-cal" title="تقویم" onclick="jdpOpen(document.getElementById('cpDate<?= $oid ?>'))">📅</button>
              </div></div>
            <div class="profile-field"><label>ساعت تقریبی تراکنش <em>(ضروری)</em></label>
              <div class="cp-datetime">
                <input type="text" class="fi" id="cpTime<?= $oid ?>" readonly inputmode="numeric" placeholder="۰۰:۰۰" value="<?= pn(date('H:i')) ?>" onclick="jtpOpen(this)" style="cursor:pointer;text-align:center">
                <button type="button" class="jdp-cal" title="انتخاب ساعت" onclick="jtpOpen(document.getElementById('cpTime<?= $oid ?>'))">🕒</button>
              </div></div>
          </div>
          <p class="name-hint" style="margin-top:10px">ثبت حداقل یکی از «شماره کارت» یا «شبا» ضروری است. پس از ثبت، مدیر اطلاعات را با تراکنش‌های بانکی خود مقایسه و سفارش را تأیید یا رد می‌کند.</p>
          <button type="button" class="name-save" style="width:100%;height:48px;margin-top:12px;font-size:.88rem" onclick="submitCardPay(<?= $oid ?>,this)">ثبت اطلاعات واریز ✅</button>
        </div>
      <?php elseif (($o['payment_method'] ?? '') === 'card' && ($o['status'] ?? '') === 'pending' && !empty($o['card_submitted_at'])): ?>
        <div class="cardpay-box" style="text-align:center">
          <div class="cardpay-title">✅ اطلاعات واریز ثبت شد</div>
          <div class="cardpay-hint" style="margin-bottom:0">اطلاعات واریز در انتظار بررسی و تأیید مدیر است. جزئیات ثبت‌شده در بخش «🕒 زمان و پرداخت» همین کارت نمایش داده می‌شود.</div>
        </div>
      <?php endif; ?>
      <?php if (($o['status'] ?? '') === 'pending' && ($o['payment_method'] ?? '') !== 'card' && getSetting($conn, 'zarinpal_enabled', '0') === '1'): ?>
        <a href="pay.php?order=<?= $oid ?>" class="login-btn" style="margin-top:10px;padding:9px 14px;font-size:.74rem;width:100%;justify-content:center">پرداخت سفارش</a>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</main>

<?php include 'auth_sheet.php'; ?>

<div class="toast" id="toast"></div>

<nav class="bottom-nav">
  <a href="index.php">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
    <span>خانه</span>
  </a>
  <?php if (($chance_enabled ?? '1') === '1'): ?>
  <a href="chance.php">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><path d="M8.5 8.5h.01"/><path d="M15.5 8.5h.01"/><path d="M12 12h.01"/><path d="M8.5 15.5h.01"/><path d="M15.5 15.5h.01"/></svg>
    <span>شانس</span>
  </a>
  <?php endif; ?>
  <?php if ($loyalty_enabled === '1'): ?>
  <a href="loyalty.php">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
    <span>وفاداری</span>
  </a>
  <?php endif; ?>
  <?php if (($blog_enabled ?? '1') === '1'): ?>
  <a href="blog.php">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
    <span>مطالب</span>
  </a>
  <?php endif; ?>
  <?php if (($shop_enabled ?? '1') === '1'): ?>
  <a href="shop.php">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
    <span>منو</span>
  </a>
  <?php endif; ?>
  <?php if (($orders_tab_enabled ?? '1') === '1'): ?>
  <a href="orders.php" class="active">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="8" y="2" width="8" height="4" rx="1"/><rect x="4" y="4" width="16" height="18" rx="2"/><path d="M9 14l2 2 4-4"/></svg>
    <span>سفارشات</span>
  </a>
  <?php endif; ?>
</nav>

<script>
var DEFAULT_THEME='<?= $default_theme ?>';
(function(){var s=(window.__R||function(v){return v})(localStorage.getItem('sitak_theme')||DEFAULT_THEME);document.documentElement.setAttribute('data-theme',s);updThemeIcon(s)})();

// بررسی لاگ‌اوت اجباری (کاربر مسدود شده)
<?php if ($force_logout_popup): ?>
(function(){
  var t=document.createElement('div');
t.className='toast';
      t.style.cssText='position:fixed;bottom:90px;left:50%;transform:translateX(-50%);background:linear-gradient(135deg,var(--gold,#ecb534),var(--gold-dark,#c49625));border:none;color:#fff;padding:13px 22px;border-radius:16px;font-size:.82rem;font-weight:500;z-index:500;opacity:0;transition:all .35s cubic-bezier(.34,1.56,.64,1);max-width:min(88%,420px);text-align:center;box-shadow:var(--shadow-lg);white-space:pre-line';
  t.textContent='شما از حساب‌کاربری خارج شدید';
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

function toggleTheme(){var c=document.documentElement.getAttribute('data-theme');var n=c==='dark'?'light':'dark';document.documentElement.setAttribute('data-theme',n);localStorage.setItem('sitak_theme',n);updThemeIcon(n);var mc=document.getElementById('metaTheme');if(mc)mc.setAttribute('content',n==='dark'?'#0a0a0c':'#fff')}
function updThemeIcon(t){document.getElementById('themeIcon').textContent=t==='dark'?'☀️':'🌙'}
function pn(n){return String(n).replace(/\d/g,function(d){return '۰۱۲۳۴۵۶۷۸۹'[d]})}
function showToast(m){var t=document.getElementById('toast');t.textContent=m;t.classList.add('show');clearTimeout(t._timer);t._timer=setTimeout(function(){t.classList.remove('show')},3000)}
(function(){
  var q=new URLSearchParams(location.search).get('payment');
  if(!q) return;
  var msgs={
    success:'پرداخت با موفقیت انجام شد ✅ سفارش شما ثبت شد.',
    cancelled:'پرداخت لغو شد. سفارش شما هنوز پرداخت نشده است.',
    failed:'پرداخت انجام نشد. اگر مبلغی از حسابتان کسر شده، با پشتیبانی تماس بگیرید.'
  };
  showToast(msgs[q]||msgs.failed);
  try{history.replaceState(null,'',location.pathname)}catch(e){}
})();
/* openSheet/closeSheet/refreshCaptcha/doRegister — در auth_sheet.php */
var orderCities={"آذربایجان شرقی":["تبریز","مراغه","مرند","میانه","اهر","بناب","سراب","شبستر","آذرشهر","عجب‌شیر","ملکان","بستان‌آباد","اسکو","هریس","کلیبر","جلفا","ورزقان","هوراند","خداآفرین","چاراویماق","سهند","سردرود"],"آذربایجان غربی":["ارومیه","خوی","بوکان","مهاباد","میاندوآب","سلماس","پیرانشهر","نقده","تکاب","ماکو","سردشت","شاهین‌دژ","اشنویه","قره‌ضیاءالدین","شوط","سیه‌چشمه","پلدشت","چایپاره","میرآباد"],"اردبیل":["اردبیل","پارس‌آباد","مشگین‌شهر","خلخال","گرمی","نمین","بیله‌سوار","اصلاندوز","کوثر","سرعین","انگوت","جعفرآباد","هشتجین","آبی‌بیگلو"],"اصفهان":["اصفهان","کاشان","خمینی‌شهر","نجف‌آباد","شاهین‌شهر","شهرضا","فولادشهر","تیران","مبارکه","آران و بیدگل","زرین‌شهر","گلپایگان","فریدن","فلاورجان","دهاقان","نطنز","سمیرم","نائین","اردستان","خوانسار","شاهپورآباد","خوروبیابانک"],"البرز":["کرج","فردیس","کمال‌شهر","نظرآباد","محمدشهر","ماهدشت","مشکین‌دشت","هشتگرد","چهارباغ","اشتهارد","گرمدره","طالقان","کوهسار","تنکمان","آسارا"],"ایلام":["ایلام","دهلران","ایوان","آبدانان","دره‌شهر","مهران","سرابله","ارکواز","آسمان‌آباد","چوار","بدره","پهله","مورموری","زرنه","لومار"],"بوشهر":["بوشهر","برازجان","بندر گناوه","بندر کنگان","خورموج","عسلویه","جم","بندر دیلم","بندر دیر","چغادک","آباد","نخل تقی","دلوار","کاکی","وحدتیه","شبانکاره","اهرم"],"تهران":["تهران","اسلامشهر","شهریار","قدس","ملارد","صالحیه","ورامین","قرچک","پاکدشت","ری","دماوند","بومهن","پردیس","باقرشهر","پیشوا","رباط‌کریم","نسیم‌شهر","اندیشه","صباشهر","وحیدیه","فیروزکوه","لواسان","آبسرد","فشم","کهریزک"],"چهارمحال و بختیاری":["شهرکرد","بروجن","لردگان","فرخ‌شهر","فارسان","هفشجان","سامان","جونقان","کیار","بن","سورشجان","بلداجی","اردل","نقنه","کوهرنگ","فرودین"],"خراسان جنوبی":["بیرجند","قائن","طبس","فردوس","نهبندان","بشرویه","سرایان","سربیشه","اسلامیه","حاجی‌آباد","خوسف","آرین‌شهر","اسدیه","سه قلعه"],"خراسان رضوی":["مشهد","نیشابور","سبزوار","تربت حیدریه","کاشمر","قوچان","تربت جام","تایباد","چناران","سرخس","گناباد","فریمان","گلبهار","درگز","خواف","بردسکن","طرقبه","شاندیز","خلیل‌آباد","مه ولات","کلات","فیروزه"],"خراسان شمالی":["بجنورد","شیروان","اسفراین","آشخانه","جاجرم","فاروج","گرمه","راز","ایور","درق","شوقان","صفی‌آباد"],"خوزستان":["اهواز","دزفول","آبادان","بندر ماهشهر","خرمشهر","شوشتر","مسجدسلیمان","اندیمشک","بهبهان","ایذه","شوش","شادگان","رامهرمز","امیدیه","سوسنگرد","بندر امام خمینی","باغ‌ملک","هندیجان","لالی","هویزه","گتوند","آغاجاری","حمیدیه"],"زنجان":["زنجان","ابهر","خرمدره","قیدار","هیدج","صائین‌قلعه","آب‌بر","زرین‌آباد","ماهنشان","سلطانیه","سهرورد","سجاس","گرماب"],"سمنان":["سمنان","شاهرود","دامغان","گرمسار","مهدی‌شهر","سرخه","ایوانکی","بسطام","آرادان","مجن","کلاته خیج","شهمیرزاد","میامی"],"سیستان و بلوچستان":["زاهدان","زابل","ایرانشهر","چابهار","سراوان","خاش","کنارک","جالق","نیک‌شهر","پیشین","سوران","زهک","فنوج","مهرستان","دلگان","بمپور","قصرقند","راسک","میرجاوه","هیرمند"],"فارس":["شیراز","مرودشت","جهرم","فسا","کازرون","صدرا","داراب","فیروزآباد","لار","آباده","نورآباد","نی‌ریز","اقلید","استهبان","گراش","زرقان","کوار","لامرد","خرامه","مهر","فراشبند","صفاشهر","قیر و کارزین"],"قزوین":["قزوین","الوند","محمدیه","تاکستان","آبیک","بوئین‌زهرا","شریفیه","اقبالیه","بیدستان","محمودآباد نمونه","اسفرورین","ضیاءآباد","آوج"],"قم":["قم","قنوات","جعفریه","کهک","دستجرد","سلفچگان"],"کردستان":["سنندج","سقز","مریوان","بانه","قروه","کامیاران","بیجار","دیواندره","دهگلان","کانی‌دینار","سروآباد","یاسوکند","زرینه"],"کرمان":["کرمان","سیرجان","رفسنجان","جیرفت","بم","زرند","کهنوج","شهر بابک","بافت","بردسیر","عنبرآباد","راور","محمدآباد","نجف‌شهر","ماهان","منوجان","انار","رودبار جنوب","فهرج","کوهبنان","قلعه گنج"],"کرمانشاه":["کرمانشاه","اسلام‌آباد غرب","جوانرود","کنگاور","سرپل ذهاب","سنقر","هرسین","صحنه","پاوه","گیلانغرب","روانسر","قصر شیرین","تازه‌آباد","کرند غرب","سرمست","باینگان"],"کهگیلویه و بویراحمد":["یاسوج","دوگنبدان","دهدشت","چرام","لیکک","سی‌سخت","لنده","باشت","مارگون","پاتاوه","دیشموک","قلعه رئیسی"],"گلستان":["گرگان","گنبد کاووس","علی‌آباد کتول","بندر ترکمن","کلاله","آزادشهر","آق‌قلا","کردکوی","مینوشت","گالیکش","بندر گز","فاضل‌آباد","گمش‌تپه","رامیان","خان‌ببین","مراوه‌تپه"],"گیلان":["رشت","بندر انزلی","لاهیجان","لنگرود","تالش","آستارا","صومعه‌سرا","آستانه اشرفیه","رودسر","فومن","خمام","سیاهکل","رضوانشهر","ماسال","منجیل","رودبار","کلاچای","لشت نشا","کوچصفهان","املش"],"لرستان":["خرم‌آباد","بروجرد","دورود","کوهدشت","الیگودرز","نورآباد","الشتر","پلدختر","ازنا","کوهنانی","سپیددشت","زاغه","سراب دوره","معمولان"],"مازندران":["ساری","بابل","آمل","قائم‌شهر","بهشهر","چالوس","نکا","بابلسر","تنکابن","نوشهر","فریدونکنار","رامسر","جویبار","محمودآباد","امیرکلا","نور","گلوگاه","سرخرود","عباس‌آباد","کلاردشت","زیرآب","پل سفید"],"مرکزی":["اراک","ساوه","خمین","محلات","دلیجان","زرندیه","شازند","مهاجران","تفرش","میلاجرد","کمیجان","آشتیان","فرمهین","خنداب","نراق"],"هرمزگان":["بندرعباس","میناب","دهبارز","بندر لنگه","قشم","کیش","حاجی‌آباد","کنگ","بندر خمیر","پارسیان","جاسک","بندر چارک","بستک","سوزا","ابوموسی","بندر سیریک"],"همدان":["همدان","ملایر","نهاوند","اسدآباد","تویسرکان","بهار","کبودرآهنگ","لالجین","فامنین","رزن","مریانج","جورقان","قروه درجزین"],"یزد":["یزد","میبد","اردکان","بافق","مهریز","ابرکوه","اشکذر","هرات","زارچ","شاهدیه","تفت","حمیدیا","مروست","بهاباد","عقدا"]};
var provinceInput=document.getElementById('provinceInput'),cityInput=document.getElementById('cityInput');
if(provinceInput){
  Object.keys(orderCities).forEach(function(p){var o=document.createElement('option');o.value=p;o.textContent=p;provinceInput.appendChild(o)});
  provinceInput.value=<?= json_encode($user_info['user']['province'] ?? '', JSON_UNESCAPED_UNICODE) ?>;
  function fillOrderCities(){cityInput.innerHTML='<option value="">شهر</option>'; (orderCities[provinceInput.value]||[]).forEach(function(c){var o=document.createElement('option');o.value=c;o.textContent=c;cityInput.appendChild(o)});cityInput.disabled=!orderCities[provinceInput.value];cityInput.value=<?= json_encode($user_info['user']['city'] ?? '', JSON_UNESCAPED_UNICODE) ?>}
  provinceInput.onchange=fillOrderCities;fillOrderCities();
}
function markProfileInvalid(id, invalid){var el=document.getElementById(id);if(el)el.classList.toggle('profile-invalid',!!invalid)}
function clearProfileInvalid(){document.querySelectorAll('.profile-field .profile-invalid').forEach(function(el){el.classList.remove('profile-invalid')})}
document.querySelectorAll('.profile-field .fi').forEach(function(el){el.addEventListener('input',function(){el.classList.remove('profile-invalid')});el.addEventListener('change',function(){el.classList.remove('profile-invalid')})});
function profileLatinDigits(value){return String(value).replace(/[۰-۹]/g,function(d){return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d)}).replace(/[٠-٩]/g,function(d){return '٠١٢٣٤٥٦٧٨٩'.indexOf(d)})}

var profMapObj=null;
function profMapInit(){
  if(profMapObj||!document.getElementById('profMap'))return;
  var el=document.getElementById('profMap'); if(el.offsetWidth===0)return;
  SiteMap.loadSdk(function(){
    if(profMapObj)return;
    var has=PROF_MAP.lat!==null&&PROF_MAP.lng!==null;
    profMapObj=SiteMap.create({container:'profMap',key:PROF_MAP.key,lat:has?PROF_MAP.lat:PROF_MAP.defLat,lng:has?PROF_MAP.lng:PROF_MAP.defLng,zoom:has?16:PROF_MAP.defZoom});
  });
}
function profMapPick(){
  if(!profMapObj||!profMapObj.getCenter){showToast('نقشه هنوز بارگذاری نشده است');return}
  var c=profMapObj.getCenter();
  document.getElementById('profLat').value=c.lat; document.getElementById('profLng').value=c.lng;
  var t=document.getElementById('profMapAddr'); t.textContent='در حال دریافت نشانی...';
  var fd=new FormData();fd.append('action','map_reverse');fd.append('lat',c.lat);fd.append('lng',c.lng);
  fetch('ajax.php',{method:'POST',body:scsrf(fd)}).then(function(r){return r.json()}).then(function(d){t.textContent=d.success?'📍 '+d.address:(d.message||'نشانی پیدا نشد')}).catch(function(){t.textContent='ارتباط با سرور برقرار نشد'});
}
(function(){var pe=document.getElementById('profMap');if(pe&&pe.closest('details')){pe.closest('details').addEventListener('toggle',function(){if(this.open)setTimeout(profMapInit,50)});}})();
function saveProfile(){
  var first=document.getElementById('firstNameInput').value.trim(),last=document.getElementById('lastNameInput').value.trim(),addr=document.getElementById('addrInput').value.trim(),province=provinceInput.value,city=cityInput.value,plaque=document.getElementById('plaqueInput').value.trim(),unit=document.getElementById('unitInput').value.trim(),postal=profileLatinDigits(document.getElementById('postalInput').value.trim()),extra=document.getElementById('extraInput').value.trim();
  clearProfileInvalid();
  [['firstNameInput',!!first],['lastNameInput',!!last],['provinceInput',!!province],['cityInput',!!city],['addrInput',!!addr],['plaqueInput',!!plaque],['unitInput',!!unit]].forEach(function(pair){markProfileInvalid(pair[0],!pair[1])});
  if(postal && !/^[0-9۰-۹]{10}$/.test(postal)){markProfileInvalid('postalInput',true);showToast('کد پستی باید ۱۰ رقم باشد');return}
  if(!first||!last||!province||!city||!addr||!plaque||!unit){showToast('همه فیلدهای ضروری را تکمیل کنید');return}
  var fd=new FormData();fd.append('action','update_profile');fd.append('save_profile','1');fd.append('first_name',first);fd.append('last_name',last);fd.append('address',addr);fd.append('province',province);fd.append('city',city);fd.append('plaque',plaque);if(document.getElementById('profLat')){fd.append('delivery_lat',document.getElementById('profLat').value);fd.append('delivery_lng',document.getElementById('profLng').value);}fd.append('unit',unit);fd.append('postal_code',postal);fd.append('extra_notes',extra);
  if(document.getElementById('payerNameInput')){
    fd.append('payer_name',document.getElementById('payerNameInput').value.trim());
    fd.append('payer_card',profileLatinDigits(document.getElementById('payerCardInput').value.replace(/[\s\-]/g,'')));
    fd.append('payer_iban',profileLatinDigits(document.getElementById('payerIbanInput').value.replace(/[\s\-]/g,'')).toUpperCase());
  }
  fetch('ajax.php',{method:'POST',body:scsrf(fd)}).then(function(r){return r.json()}).then(function(d){
    if(d.success){showToast('اطلاعات شما بروزرسانی شد ✅')}
    else{showToast(d.message||'خطا در بروزرسانی')}
  }).catch(function(){showToast('خطا در ارتباط با سرور')});
}
document.querySelectorAll('.bottom-nav a').forEach(function(a){
  if(a.pathname===window.location.pathname){a.classList.add('active')}else{a.classList.remove('active')}
});
</script>
<script>
/* ══ کارت‌به‌کارت: تقویم شمسی، تایمر مهلت، فرمت شماره‌ها، کپی و ثبت اطلاعات واریز ══ */
/* الگوریتم تبدیل، دقیقاً همان توابع config.php سمت سرور است */
function j2g(jy,jm,jd){
  jy+=1595;
  var days=-355668+(365*jy)+(Math.floor(jy/33)*8)+Math.floor(((jy%33)+3)/4)+jd+((jm<7)?(jm-1)*31:((jm-7)*30)+186);
  var gy=400*Math.floor(days/146097);
  days%=146097;
  if(days>36524){gy+=100*Math.floor(--days/36524);days%=36524;if(days>=365)days++}
  gy+=4*Math.floor(days/1461);
  days%=1461;
  if(days>365){gy+=Math.floor((days-1)/365);days=(days-1)%365}
  var gd=days+1;
  var sal_a=[0,31,((gy%4===0&&gy%100!==0)||(gy%400===0))?29:28,31,30,31,30,31,31,30,31,30,31];
  var gm;
  for(gm=1;gm<=12;gm++){if(gd<=sal_a[gm])break;gd-=sal_a[gm]}
  return [gy,gm,gd];
}
function g2j(gy,gm,gd){
  var g_d_m=[0,31,59,90,120,151,181,212,243,273,304,334];
  var jy=(gy<=1600)?0:979;
  gy-=(gy<=1600)?621:1600;
  var gy2=(gm>2)?(gy+1):gy;
  var days=(365*gy)+Math.floor((gy2+3)/4)-Math.floor((gy2+99)/100)+Math.floor((gy2+399)/400)-80+gd+g_d_m[gm-1];
  jy+=33*Math.floor(days/12053);
  days%=12053;
  jy+=4*Math.floor(days/1461);
  days%=1461;
  jy+=Math.floor((days-1)/365);
  if(days>365)days=(days-1)%365;
  var jm=(days<186)?1+Math.floor(days/31):7+Math.floor((days-186)/30);
  var jd=1+((days<186)?(days%31):((days-186)%30));
  return [jy,jm,jd];
}
function jMonthLen(jy,jm){
  var d1=j2g(jy,jm,1),d2=(jm===12)?j2g(jy+1,1,1):j2g(jy,jm+1,1);
  return Math.round((Date.UTC(d2[0],d2[1]-1,d2[2])-Date.UTC(d1[0],d1[1]-1,d1[2]))/86400000);
}
function jdpParse(v){var m=/^(\d{4})[\/\-\.](\d{1,2})[\/\-\.](\d{1,2})$/.exec(profileLatinDigits(String(v||'').trim()));return m?[+m[1],+m[2],+m[3]]:null}
var jdpState=null;
function jdpClose(){var p=document.getElementById('jdpPop');if(p)p.remove();jdpState=null;document.removeEventListener('mousedown',jdpOutside,true)}
function jdpOutside(e){var p=document.getElementById('jdpPop');if(p&&!p.contains(e.target)&&!(e.target.closest&&e.target.closest('.cp-datetime')))jdpClose()}
function jdpOpen(inp){
  if(jdpState&&jdpState.input===inp){jdpClose();return}
  jdpClose();
  var v=jdpParse(inp.value);
  var now=new Date();var tj=g2j(now.getFullYear(),now.getMonth()+1,now.getDate());
  jdpState={input:inp,jy:v?v[0]:tj[0],jm:v?v[1]:tj[1]};
  var pop=document.createElement('div');pop.className='jdp-pop';pop.id='jdpPop';
  document.body.appendChild(pop);
  jdpRender();
  var r=inp.getBoundingClientRect();
  var top=r.bottom+window.scrollY+6;
  var left=r.left+window.scrollX;
  var maxL=window.scrollX+document.documentElement.clientWidth-pop.offsetWidth-8;
  if(left>maxL)left=Math.max(window.scrollX+8,maxL);
  pop.style.top=top+'px';pop.style.left=left+'px';
  setTimeout(function(){document.addEventListener('mousedown',jdpOutside,true)},0);
}
function jdpRender(){
  var s=jdpState,pop=document.getElementById('jdpPop');
  if(!s||!pop)return;
  var len=jMonthLen(s.jy,s.jm);
  var f=j2g(s.jy,s.jm,1);
  var wd=new Date(f[0],f[1]-1,f[2]).getDay();
  var off=(wd+1)%7;
  var n=new Date();var tJ=g2j(n.getFullYear(),n.getMonth()+1,n.getDate());
  var months=['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
  var html='<div class="jdp-hdr"><button type="button" onclick="jdpNav(1)">‹</button><div>'+months[s.jm-1]+' '+pn(s.jy)+'</div><button type="button" onclick="jdpNav(-1)">›</button></div>';
  html+='<div class="jdp-wd">'+['ش','ی','د','س','چ','پ','ج'].map(function(x){return '<span>'+x+'</span>'}).join('')+'</div><div class="jdp-days">';
  for(var i=0;i<off;i++)html+='<span></span>';
  for(var d=1;d<=len;d++){
    var isT=(d===tJ[2]&&s.jm===tJ[1]&&s.jy===tJ[0]);
    html+='<button type="button" class="jdp-day'+(isT?' jdp-today':'')+'" onclick="jdpPick('+d+')">'+pn(d)+'</button>';
  }
  html+='</div><div class="jdp-foot"><button type="button" onclick="jdpToday()">امروز</button><button type="button" onclick="jdpClose()">بستن</button></div>';
  pop.innerHTML=html;
}
function jdpNav(dir){if(!jdpState)return;jdpState.jm+=dir;if(jdpState.jm>12){jdpState.jm=1;jdpState.jy++}if(jdpState.jm<1){jdpState.jm=12;jdpState.jy--}jdpRender()}
function jdpSet(v){if(jdpState&&jdpState.input)jdpState.input.value=v}
function jdpPick(d){if(!jdpState)return;jdpSet(jdpState.jy+'/'+('0'+jdpState.jm).slice(-2)+'/'+('0'+d).slice(-2));jdpClose()}
function jdpToday(){var n=new Date();var j=g2j(n.getFullYear(),n.getMonth()+1,n.getDate());jdpSet(j[0]+'/'+('0'+j[1]).slice(-2)+'/'+('0'+j[2]).slice(-2));jdpClose()}
/* ── انتخابگر ساعت (۲۴ ساعته با ارقام فارسی، همان زبان بصری تقویم) ── */
var jtpState=null;
function jtpClose(){var p=document.getElementById('jtpPop');if(p)p.remove();jtpState=null;document.removeEventListener('mousedown',jtpOutside,true)}
function jtpOutside(e){var p=document.getElementById('jtpPop');if(p&&!p.contains(e.target)&&!(e.target.closest&&e.target.closest('.cp-datetime')))jtpClose()}
function jtpParse(v){var m=/^(\d{1,2}):(\d{2})$/.exec(profileLatinDigits(String(v||'').trim()));if(!m)return null;var h=+m[1],mi=+m[2];return (h>=0&&h<=23&&mi>=0&&mi<=59)?[h,mi]:null}
function jtpOpen(inp){
  if(jtpState&&jtpState.input===inp){jtpClose();return}
  jtpClose();jdpClose();
  var v=jtpParse(inp.value);var n=new Date();
  jtpState={input:inp,step:'h',h:v?v[0]:n.getHours(),m:v?v[1]:n.getMinutes()};
  var pop=document.createElement('div');pop.className='jdp-pop jtp-pop';pop.id='jtpPop';
  document.body.appendChild(pop);
  jtpRender();
  var r=inp.getBoundingClientRect();
  var top=r.bottom+window.scrollY+6;
  var left=r.left+window.scrollX;
  var maxL=window.scrollX+document.documentElement.clientWidth-pop.offsetWidth-8;
  if(left>maxL)left=Math.max(window.scrollX+8,maxL);
  pop.style.top=top+'px';pop.style.left=left+'px';
  setTimeout(function(){document.addEventListener('mousedown',jtpOutside,true)},0);
}
function jtpRender(){
  var s=jtpState,pop=document.getElementById('jtpPop');
  if(!s||!pop)return;
  var html='';
  if(s.step==='h'){
    html+='<div class="jdp-hdr"><span style="width:28px"></span><div class="jtp-title">ساعت را انتخاب کن</div><span style="width:28px"></span></div><div class="jtp-grid">';
    for(var h=0;h<24;h++)html+='<button type="button" class="jtp-cell'+(h===s.h?' jtp-sel':'')+'" onclick="jtpPickH('+h+')">'+pn(('0'+h).slice(-2))+'</button>';
    html+='</div>';
  }else{
    html+='<div class="jdp-hdr"><button type="button" title="بازگشت" onclick="jtpBack()">›</button><div class="jtp-title">دقیقه — ساعت '+pn(('0'+s.h).slice(-2))+'</div><span style="width:28px"></span></div><div class="jtp-grid">';
    for(var m=0;m<60;m+=5)html+='<button type="button" class="jtp-cell'+(m===s.m?' jtp-sel':'')+'" onclick="jtpPickM('+m+')">'+pn(('0'+m).slice(-2))+'</button>';
    html+='</div>';
  }
  html+='<div class="jdp-foot"><button type="button" onclick="jtpNow()">الان</button><button type="button" onclick="jtpClose()">بستن</button></div>';
  pop.innerHTML=html;
}
function jtpBack(){if(jtpState){jtpState.step='h';jtpRender()}}
function jtpPickH(h){if(!jtpState)return;jtpState.h=h;jtpState.step='m';jtpRender()}
function jtpPickM(m){if(!jtpState)return;jtpState.m=m;jtpSet();jtpClose()}
function jtpNow(){var n=new Date();if(jtpState){jtpState.h=n.getHours();jtpState.m=n.getMinutes();jtpSet()}jtpClose()}
function jtpSet(){if(jtpState&&jtpState.input)jtpState.input.value=pn(('0'+jtpState.h).slice(-2)+':'+('0'+jtpState.m).slice(-2))}
/* فرمت زنده‌ی شماره کارت و شبا (۴ رقم ۴ رقم با خط تیره) */
function cpFmtCard(el){var d=profileLatinDigits(el.value.replace(/\D/g,'')).slice(0,16);el.value=d.replace(/(\d{4})(?=\d)/g,'$1-')}
function cpFmtIban(el){var v=profileLatinDigits(el.value.replace(/[^A-Za-z0-9]/g,'')).toUpperCase().slice(0,26);el.value=v.replace(/(.{4})(?=.)/g,'$1-')}
/* کپی متن با فالبک برای مرورگرهای قدیمی */
function cpCopyText(txt,btn){
  function done(){var t=btn.textContent;btn.textContent='✓ کپی شد';showToast('کپی شد 📋');setTimeout(function(){btn.textContent=t},1500)}
  function fb(){var ta=document.createElement('textarea');ta.value=txt;ta.style.position='fixed';ta.style.opacity='0';document.body.appendChild(ta);ta.select();try{document.execCommand('copy');done()}catch(e){showToast('کپی نشد؛ شماره را دستی انتخاب کن')}ta.remove()}
  if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(txt).then(done).catch(fb)}else fb();
}
/* تایمر مهلت واریز در گوشه‌ی کارت سفارش */
var _cpReloaded=false;
function cpChipsTick(){
  document.querySelectorAll('.cp-chip[data-expires]').forEach(function(ch){
    var left=+ch.dataset.expires-Math.floor(Date.now()/1000);
    var b=ch.querySelector('b');
    if(left<=0){
      if(!ch.classList.contains('cp-dead')){
        ch.classList.add('cp-dead');
        if(b)b.textContent='۰۰:۰۰';
        if(!_cpReloaded){_cpReloaded=true;setTimeout(function(){location.reload()},2200)}
      }
      return;
    }
    var m=Math.floor(left/60),s=left%60;
    if(b)b.textContent=pn((m<10?'0':'')+m+':'+(s<10?'0':'')+s);
  });
}
cpChipsTick();setInterval(cpChipsTick,1000);
/* ثبت اطلاعات واریز کارت‌به‌کارت */
function submitCardPay(oid,btn){
  var name=document.getElementById('cpName'+oid).value.trim();
  var card=profileLatinDigits(document.getElementById('cpCard'+oid).value.replace(/[\s\-]/g,''));
  var iban=profileLatinDigits(document.getElementById('cpIban'+oid).value.replace(/[\s\-]/g,'')).toUpperCase();
  var date=document.getElementById('cpDate'+oid).value.trim();
  var time=profileLatinDigits(document.getElementById('cpTime'+oid).value.trim());
  var tm=/^(\d{1,2}):(\d{2})$/.exec(time);
  time=tm?(('0'+tm[1]).slice(-2)+':'+tm[2]):'';
  if(name.length<3){showToast('نام و نام خانوادگی صاحب حساب را کامل وارد کن');return}
  if(!card&&!iban){showToast('شماره کارت یا شبای واریز را وارد کن');return}
  if(card&&!/^\d{16}$/.test(card)){showToast('شماره کارت باید ۱۶ رقم باشد');return}
  if(iban&&!/^IR\d{24}$/.test(iban)){showToast('شماره شبا باید با IR شروع شود و ۲۶ کاراکتر باشد');return}
  if(!jdpParse(date)){showToast('تاریخ تراکنش را از تقویم انتخاب کن');return}
  if(!/^([01]?\d|2[0-3]):[0-5]\d$/.test(time)){showToast('ساعت تراکنش را از انتخابگر ساعت 🕒 انتخاب کن');return}
  btn.disabled=true;btn.textContent='در حال ثبت...';
  var fd=new FormData();fd.append('action','submit_card_payment');fd.append('order_id',oid);
  fd.append('payer_name',name);fd.append('payer_card',card);fd.append('payer_iban',iban);fd.append('pay_date',date);fd.append('pay_time',time);
  fetch('ajax.php',{method:'POST',body:scsrf(fd)}).then(function(r){return r.json()}).then(function(d){
    if(d.success){showToast('اطلاعات واریز ثبت شد ✅ منتظر تأیید مدیر باش');setTimeout(function(){location.reload()},1300)}
    else{btn.disabled=false;btn.textContent='ثبت اطلاعات واریز ✅';showToast(d.message||'خطا در ثبت اطلاعات')}
  }).catch(function(){btn.disabled=false;btn.textContent='ثبت اطلاعات واریز ✅';showToast('خطا در ارتباط با سرور')});
}
</script>
<?php include 'cart_sheet.php'; ?>
<script>
try{var _ock='sitak_cart_'+(_cuser||'_guest');var _oc=JSON.parse(sessionStorage.getItem(_ock)||'[]');if(_oc&&_oc.length>0)setTimeout(function(){if(typeof openSheet==='function')openSheet('cart')},600)}catch(e){}
</script>
<script>
function receiptNowText(){
  try{
    var f=new Intl.DateTimeFormat('fa-IR-u-ca-persian',{year:'numeric',month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit',hourCycle:'h23',timeZone:'Asia/Tehran'});
    var p={};f.formatToParts(new Date()).forEach(function(x){p[x.type]=x.value});
    return p.year+'/'+p.month+'/'+p.day+' — '+p.hour+':'+p.minute;
  }catch(e){return ''}
}
function downloadReceipt(btn){
  var d=JSON.parse(btn.getAttribute('data-receipt'));
  d.received=receiptNowText();
  var fontReady=(document.fonts&&document.fonts.load)?document.fonts.load('16px Vazirmatn'):Promise.resolve();
  var mapP=(d.map_lat!=null&&d.map_address)?SiteMap.snapshot({key:ORD_MAP_KEY,lat:+d.map_lat,lng:+d.map_lng,zoom:16}):Promise.resolve(null);
  Promise.all([fontReady,mapP]).then(function(rs){
    var mapImgEl=rs[1];
    var W=640,P=36,LH=30,FONT="'Vazirmatn', Tahoma, sans-serif";
    var C={bg:'#fffdf7',ink:'#1e252b',mute:'#6b6f75',gold:'#c49625',line:'#e6dcc3'};
    var cv=document.createElement('canvas');var g=cv.getContext('2d');
    g.font='15px '+FONT;
    function wrap(text,maxW){var words=String(text).split(' '),lines=[],cur='';words.forEach(function(w){var t=cur?cur+' '+w:w;if(g.measureText(t).width>maxW&&cur){lines.push(cur);cur=w}else{cur=t}});if(cur)lines.push(cur);return lines}
    var ops=[];var y=P;
    function add(h){var at=y;y+=h;return at}
    // header
    ops.push(function(){g.fillStyle=C.gold;g.font='bold 26px '+FONT;g.textAlign='center';g.fillText(<?= json_encode(sitak_clean_name($site_name ?? 'سای‌تک'), JSON_UNESCAPED_UNICODE) ?>,W/2,P+26)});
    y=P+50;
    ops.push(function(){g.fillStyle=C.ink;g.font='bold 17px '+FONT;g.textAlign='center';g.fillText('رسید سفارش '+d.id,W/2,P+50+0)});
    y=P+80;
    function row(label,value,bold){var yy=add(LH);ops.push(function(){g.font=(bold?'bold ':'')+'15px '+FONT;g.fillStyle=bold?C.ink:C.mute;g.textAlign='right';g.fillText(label,W-P,yy+20);g.fillStyle=C.ink;g.textAlign='left';g.fillText(value,P,yy+20)})}
    function line(){var yy=add(14);ops.push(function(){g.strokeStyle=C.line;g.beginPath();g.moveTo(P,yy+7);g.lineTo(W-P,yy+7);g.stroke()})}
    function title(t){var yy=add(LH+8);ops.push(function(){g.fillStyle=C.gold;g.font='bold 15px '+FONT;g.textAlign='right';g.fillText(t,W-P,yy+22)})}
    row('زمان دریافت رسید',d.received);
    row('زمان ثبت سفارش',d.date);
    row('وضعیت',d.status);
    line();title('محصولات');
    d.items.forEach(function(it){row(it.name+' × '+it.qty,it.price)});
    line();
    row('جمع اقلام',d.items_sum);
    row('هزینه‌ی ارسال',d.shipping);
    if(d.discount)row('تخفیف',d.discount);
    row('مجموع پرداختی',d.total,true);
    line();title('تحویل گیرنده');
    row('نام',d.name);
    row('شماره تماس',d.phone);
    var addrLines=wrap(d.address,W-2*P-120);
    addrLines.forEach(function(l,i){var yy=add(LH);ops.push(function(){g.font='15px '+FONT;g.fillStyle=C.mute;g.textAlign='right';g.fillText(i===0?'نشانی':'',W-P,yy+20);g.textAlign='left';g.fillText(l,P+0,yy+20)})});
    function tb(label,text){var ls=wrap(text,W-2*P-120);ls.forEach(function(l,i){var yy=add(LH);ops.push(function(){g.font='15px '+FONT;g.fillStyle=C.mute;g.textAlign='right';g.fillText(i===0?label:'',W-P,yy+20);g.textAlign='left';g.fillText(l,P+0,yy+20)})})}
    if(d.note){tb('یادداشت خریدار',d.note)}
    if(d.map_lat!=null&&d.map_address){var mlines=wrap(d.map_address,W-2*P-120);mlines.forEach(function(l,i){var yy=add(LH);ops.push(function(){g.font='15px '+FONT;g.fillStyle=C.mute;g.textAlign='right';g.fillText(i===0?'نشانی روی نقشه':'',W-P,yy+20);g.textAlign='left';g.fillText(l,P+0,yy+20)})});}
    if(mapImgEl){var IH=200;var iy=add(IH+12);ops.push(function(){g.save();rr(P,iy,W-2*P,IH,14);g.clip();g.drawImage(mapImgEl,P,iy,W-2*P,IH);g.restore();var cx=P+(W-2*P)/2,cy=iy+IH/2;g.fillStyle='#e53935';g.beginPath();g.arc(cx,cy-10,9,0,Math.PI*2);g.fill();})}
    if(d.admin_note){tb('یادداشت فروشنده',d.admin_note)}
    line();
    row('وضعیت پرداخت',d.paid);
    if(d.card){
      line();title('💳 پرداخت کارت‌به‌کارت');
      if(d.card.payer_name)row('واریزکننده',d.card.payer_name);
      if(d.card.payer_card)row('کارت واریز',d.card.payer_card);
      if(d.card.payer_iban)row('شبای واریز',d.card.payer_iban);
      if(d.card.paid_at)row('زمان تراکنش',d.card.paid_at);
      if(d.card.owner)row('واریز به نام',d.card.owner);
      if(d.card.owner_card)row('کارت مقصد',d.card.owner_card);
      if(d.card.owner_iban)row('شبای مقصد',d.card.owner_iban);
    }
    var H=y+P;
    cv.width=W*2;cv.height=H*2;g=cv.getContext('2d');g.scale(2,2);g.direction='rtl';
    function rr(x,y,w,h,r){g.beginPath();g.moveTo(x+r,y);g.arcTo(x+w,y,x+w,y+h,r);g.arcTo(x+w,y+h,x,y+h,r);g.arcTo(x,y+h,x,y,r);g.arcTo(x,y,x+w,y,r);g.closePath();}
    rr(0,0,W,H,22);g.fillStyle=C.bg;g.fill();
    rr(10,10,W-20,H-20,16);g.strokeStyle=C.gold;g.lineWidth=2;g.stroke();
    ops.forEach(function(f){g.save();f();g.restore()});
    cv.toBlob(function(blob){
      var a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download='رسید-سفارش-'+d.id+'.png';document.body.appendChild(a);a.click();setTimeout(function(){URL.revokeObjectURL(a.href);a.remove()},1000);
    },'image/png');
  });
}
</script>
<script>var ORD_MAP_KEY=<?= json_encode($pm_key, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
function ordMapInit(el){if(el._m||el.offsetWidth===0)return;SiteMap.loadSdk(function(){if(el._m)return;var lat=+el.dataset.lat,lng=+el.dataset.lng;var m=SiteMap.create({container:el,key:ORD_MAP_KEY,lat:lat,lng:lng,zoom:16});el._m=m;SiteMap.pin(m,lat,lng);});}
document.querySelectorAll('.ord-map').forEach(function(el){var det=el.closest('details');if(det){det.addEventListener('toggle',function(){if(this.open)setTimeout(function(){ordMapInit(el)},50)});}else{setTimeout(function(){ordMapInit(el)},50)}});
</script>
<?php include 'map_shared.php'; ?>
</body>
</html>
