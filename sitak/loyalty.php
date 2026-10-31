<?php
require_once 'config.php';
track_view($conn, 'loyalty');

$captcha_n1 = rand(1, 10);
$captcha_n2 = rand(1, 10);
$_SESSION['captcha_answer'] = $captcha_n1 + $captcha_n2;

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

$default_theme = getSetting($conn, 'default_theme', 'auto');
$custom_favicon = getSetting($conn, 'custom_favicon', '');
$custom_logo = getSetting($conn, 'custom_logo', '');
$loyalty_enabled = getSetting($conn, 'loyalty_enabled', '1');
$site_name       = getSetting($conn, 'site_name', 'سای‌تک');
$site_slogan     = getSetting($conn, 'site_slogan', 'یک وبسایت تک');
$chance_enabled = getSetting($conn, 'chance_enabled', '1');
$blog_enabled = getSetting($conn, 'blog_enabled', '1');
$shop_enabled = getSetting($conn, 'shop_enabled', '1');
$orders_tab_enabled = getSetting($conn, 'orders_tab_enabled', '1');
if ($loyalty_enabled !== '1') { header('Location: index.php'); exit; }
$loyalty_dice_count = intval(getSetting($conn, 'loyalty_dice_count', '2'));
$loyalty_page_title = getSetting($conn, 'loyalty_page_title', 'کارت وفاداری شما');
$loyalty_page_description = getSetting($conn, 'loyalty_page_description', "هر خرید حضوری = 1 امتیاز\nبا شش کردن تاس، مهمان ما باش");
$loyalty_complete_msg = getSetting($conn, 'loyalty_complete_msg', "🎉 تاس تکمیل شد! این صفحه رو به ما نشون بده تا هدیه‌ت رو بگیری!");
$loyalty_card_hint = getSetting($conn, 'loyalty_card_hint', "تاس رو به شش تبدیل کن و از ما جایزه بگیر");
$custom_favicon = getSetting($conn, 'custom_favicon', '');
$custom_logo = getSetting($conn, 'custom_logo', '');
$contact_instagram = getSetting($conn, 'contact_instagram', '');
$contact_telegram = getSetting($conn, 'contact_telegram', '');
$contact_youtube = getSetting($conn, 'contact_youtube', '');
$contact_address = getSetting($conn, 'contact_address', '');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="<?= htmlspecialchars($default_theme, ENT_QUOTES, 'UTF-8') ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<meta name="theme-color" content="#ffffff" id="metaTheme">
<?= theme_head_script('sitak_theme', $default_theme) ?>
<?= sitak_head_meta($conn, ['title' => 'وفاداری']) ?>
<title>وفاداری | <?= htmlspecialchars($site_name ?? 'سای‌تک', ENT_QUOTES, 'UTF-8') ?></title>
<link href="assets/fonts/vazirmatn.css" rel="stylesheet">
<style>
:root,[data-theme="light"]{--bg:#f5f5f7;--surface:#fff;--card:#fff;--card-el:#f0eff2;--border:rgba(255,255,255,.08);--border-gold:rgba(236,181,52,.22);--text:#1d1d1f;--text2:#6e6e73;--text3:#aeaeb2;--header-bg:rgba(255,255,255,.82);--shadow-sm:0 1px 3px rgba(0,0,0,.06);--shadow-md:0 4px 14px rgba(0,0,0,.06);--sheet-shadow:0 -10px 40px rgba(0,0,0,.08);--overlay-bg:rgba(0,0,0,.3);--toast-bg:#fff;--input-bg:#f0eff2;--green:#30d158;--green-text:#1a8a3f;--green-bg:rgba(48,209,88,.07);--red:#ff453a;--red-bg:rgba(255,69,58,.06);--gold:#ecb534;--gold-dark:#c49625;--gold-subtle:rgba(236,181,52,.07);--gold-glow:rgba(236,181,52,.12);--r:20px;--rs:14px;--rss:10px;--safe-t:env(safe-area-inset-top,0px);--safe-b:env(safe-area-inset-bottom,0px);--font:'Vazirmatn',-apple-system,BlinkMacSystemFont,sans-serif}
[data-theme="dark"]{--bg:#0a0a0c;--surface:#161618;--card:#1c1c1e;--card-el:#2c2c2e;--border:rgba(255,255,255,.08);--border-gold:rgba(236,181,52,.15);--text:#f0ece4;--text2:#9e9888;--text3:#5e5a52;--header-bg:rgba(10,10,12,.88);--shadow-sm:0 1px 3px rgba(0,0,0,.3);--shadow-md:0 4px 14px rgba(0,0,0,.3);--sheet-shadow:0 -10px 40px rgba(0,0,0,.35);--overlay-bg:rgba(0,0,0,.5);--toast-bg:#2c2c2e;--input-bg:#2c2c2e;--green:#34d399;--green-text:#34d399;--green-bg:rgba(52,211,153,.08);--red:#f87171;--red-bg:rgba(248,113,113,.08)}
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
.avatar{width:30px;height:30px;border-radius:50%;background:var(--card-el);display:flex;align-items:center;justify-content:center;font-size:.7rem;color:var(--gold-dark);border:1.5px solid var(--gold);font-weight:700}
.theme-btn{width:32px;height:32px;border-radius:50%;border:1px solid var(--border);background:var(--card-el);display:flex;align-items:center;justify_content:center;font-size:.85rem;cursor:pointer;transition:all .2s}.theme-btn:active{transform:scale(.9);background:var(--gold-subtle)}
.logout-link{font-size:.7rem;color:var(--red);text-decoration:none;font-weight:600;padding:4px 8px;border-radius:8px;background:var(--red-bg);transition:all .2s}

.content{position:relative;z-index:1;padding:12px 14px 100px;max-width:480px;margin:0 auto}
.hero{text-align:center;padding:28px 12px 20px}.hero h2{font-size:1.35rem;font-weight:900;color:var(--gold-dark);margin-bottom:8px;line-height:1.6}.hero p{font-size:.82rem;color:var(--text2);line-height:1.7;max-width:340px;margin:0 auto 12px}

.loyalty-card{background:var(--card);border:1px solid var(--border-gold);border-radius:var(--r);padding:20px;margin-bottom:14px;box-shadow:var(--shadow-sm)}
.loyalty-header{display:flex;align-items:center;gap:10px;margin-bottom:8px}
.loyalty-header .icon{width:42px;height:42px;background:linear-gradient(135deg,var(--gold),var(--gold-dark));border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;color:#fff}
.loyalty-header h2{font-size:1.1rem;font-weight:700;color:var(--gold-dark)}
.loyalty-sub{font-size:.8rem;color:var(--text2);margin-bottom:16px;line-height:1.6}
.loyalty-tray{display:flex;justify-content:center;gap:10px;margin-bottom:16px;flex-wrap:wrap}
.loyalty-die{display:inline-flex;flex-direction:column;align-items:center;gap:4px;transition:transform .3s cubic-bezier(.34,1.56,.64,1)}
.loyalty-die:hover{transform:scale(1.08)}
.loyalty-die svg{width:44px;height:44px}
.die-label{font-size:.68rem;font-weight:700;color:var(--text3)}
.loyalty-die.earned .die-label{color:var(--gold-dark)}
.loyalty-die.in-progress .die-label{color:var(--gold)}
.loyalty-complete{background:var(--green-bg);border:1px solid var(--green-text);border-radius:var(--rss);padding:14px;font-size:.85rem;font-weight:600;color:var(--green-text);text-align:center;line-height:1.7;margin-top:12px}
.loyalty-hint{font-size:.72rem;color:var(--text3);text-align:center;line-height:1.6}
.win-badge{border:1px solid rgba(48,209,88,.22);min-height:28px;display:inline-flex;align-items:center;gap:5px;background:var(--green-bg);color:var(--green-text);font-size:.72rem;font-weight:700;padding:4px 12px;border-radius:20px;margin-bottom:12px;vertical-align:middle}

/* ── Register Sheet ── */
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
.bottom-nav{position:fixed;bottom:0;left:0;right:0;z-index:100;background:var(--surface);border-top:1px solid var(--border);display:flex;justify-content:space-around;padding:8px 0;padding-bottom:calc(8px + var(--safe-b));border-top-left-radius:16px;border-top-right-radius:16px}.bottom-nav a{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;color:var(--text2);text-decoration:none;font-size:.6rem;min-width:44px;min-height:44px;padding:4px 0;transition:color .2s;white-space:nowrap}.bottom-nav a.active{color:var(--gold-dark)}.bottom-nav svg{width:22px;height:22px;transition:transform .2s}.bottom-nav a.active svg{color:var(--gold-dark);transform:scale(1.1)}
@media(min-width:481px){
  html{scrollbar-gutter:stable}
  html,body{max-width:480px;margin:0 auto}
  body{box-shadow:0 0 0 1px var(--border),0 0 50px rgba(0,0,0,.1)}
  .bottom-nav,.admin-nav{left:50%;right:auto;transform:translateX(-50%);width:480px}
  body::before{left:50%;right:auto;transform:translateX(-50%);width:480px;height:480px;top:-20%}
}
@media(max-width:400px){.bottom-nav a{font-size:.55rem;min-width:38px;padding:2px 0}}

.toast{position:fixed;bottom:80px;left:50%;transform:translateX(-50%) translateY(100px);background:linear-gradient(135deg,var(--gold,#ecb534),var(--gold-dark,#c49625));border:none;color:#fff;padding:13px 22px;border-radius:16px;font-size:.82rem;font-weight:500;z-index:500;opacity:0;transition:all .35s cubic-bezier(.34,1.56,.64,1);max-width:min(88%,420px);text-align:center;box-shadow:var(--shadow-lg);white-space:pre-line}.toast.show{transform:translateX(-50%) translateY(0);opacity:1}
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
  <section class="hero">
    <h2><?= htmlspecialchars($loyalty_page_title, ENT_QUOTES, 'UTF-8') ?></h2>
    <p style="text-align:center"><?= nl2br(htmlspecialchars($loyalty_page_description, ENT_QUOTES, 'UTF-8')) ?></p>
  </section>

  <?php if ($loyalty_enabled === '1' && $user_info):
    $vis = intval($user_info['user']['visits'] ?? 0);
    $target = $loyalty_dice_count * 6;
    $done = ($vis >= $target);
  ?>
  <section class="loyalty-card">
    <!-- <div class="loyalty-header">
      <span class="icon">⭐</span>
      <h2>کارت وفاداری شما</h2>
    </div> -->
    <div class="win-badge"><h4>⭐ کارت وفاداری شما</h4></div>
    <p class="loyalty-sub">مجموع مراجعات حضوری: <strong><?= pn($vis) ?></strong> بار</p>
    <div class="loyalty-tray"><?= get_loyalty_dice_html($vis, $loyalty_dice_count) ?></div>
    <?php if ($done): ?>
      <div class="loyalty-complete"><?= nl2br(htmlspecialchars($loyalty_complete_msg, ENT_QUOTES, 'UTF-8')) ?></div>
    <?php else: ?>
      <p class="loyalty-hint" style="text-align:center"><?= nl2br(htmlspecialchars($loyalty_card_hint, ENT_QUOTES, 'UTF-8')) ?></p>
    <?php endif; ?>
  </section>
  <?php elseif (!$user_info): ?>
  <section class="card card-gold" style="text-align:center;padding:40px 20px">
    <div style="font-size:3rem;margin-bottom:16px">⭐</div>
    <h2 style="font-size:1.1rem;font-weight:700;color:var(--gold-dark);margin-bottom:8px">برای دیدن کارت وفاداری وارد شوید</h2>
    <p style="font-size:.82rem;color:var(--text2);margin-bottom:20px">ابتدا باید وارد حساب خود شوید</p>
    <button type="button" onclick="openSheet('reg')" style="display:inline-flex;align-items:center;gap:6px;padding:12px 24px;background:linear-gradient(135deg,var(--gold),var(--gold-dark));color:#fff;border:none;border-radius:var(--rss);font-family:var(--font);font-size:.88rem;font-weight:700;cursor:pointer;">ورود / ثبت‌نام</button>
  </section>
  <?php else: ?>
  <section class="loyalty-card">
    <div class="loyalty-header">
      <span class="icon">⭐</span>
      <h2>کارت وفاداری شما</h2>
    </div>
    <p class="loyalty-sub">مجموع مراجعات حضوری: <strong><?= pn($vis ?? 0) ?></strong> بار</p>
    <div class="loyalty-tray"><?= get_loyalty_dice_html($vis ?? 0, $loyalty_dice_count) ?></div>
    <p class="loyalty-hint" style="text-align:center"><?= nl2br(htmlspecialchars($loyalty_card_hint, ENT_QUOTES, 'UTF-8')) ?></p>
  </section>
  <?php endif; ?>
</main>

<!-- Bottom Navigation -->
<!-- Sheet: Register -->
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
  <a href="loyalty.php" class="active">
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
  <a href="orders.php">
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
function pn(n){return String(n).replace(/\d/g,function(d){return'۰۱۲۳۴۵۶۷۸۹'[d]})}
document.querySelectorAll('.bottom-nav a').forEach(function(a){
  if(a.pathname===window.location.pathname){a.classList.add('active')}else{a.classList.remove('active')}
});
</script>
  <?php include 'cart_sheet.php'; ?>
</body>
</html>
