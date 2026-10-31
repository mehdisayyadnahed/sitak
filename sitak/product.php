<?php
require_once 'config.php';

$shop_enabled = getSetting($conn, 'shop_enabled', '1');
$orders_tab_enabled = getSetting($conn, 'orders_tab_enabled', '1');
if ($shop_enabled !== '1') { header('Location: index.php'); exit; }
$site_name    = getSetting($conn, 'site_name', 'سای‌تک');
$site_slogan  = getSetting($conn, 'site_slogan', 'یک وبسایت تک');
$custom_emoji = getSetting($conn, 'custom_emoji', '🌐');

$pid = intval($_GET['id'] ?? 0);
$product = null;
if ($pid > 0) {
    $ps = $conn->prepare("SELECT * FROM products WHERE id = ? AND is_available = 1 LIMIT 1");
    $ps->bind_param("i", $pid); $ps->execute();
    $product = $ps->get_result()->fetch_assoc();
}
if (!$product) { header('Location: shop.php'); exit; }
track_view($conn, 'product:' . $pid);
$product_tags = sitak_product_tags($conn, $pid);
$show_product_ratings = getSetting($conn, 'show_product_ratings', '1') === '1';
$product_rating = $show_product_ratings ? (sitak_product_ratings($conn, [$pid])[$pid] ?? null) : null;

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
$chance_enabled = getSetting($conn, 'chance_enabled', '1');
$blog_enabled = getSetting($conn, 'blog_enabled', '1');
$shop_enabled = getSetting($conn, 'shop_enabled', '1');
$orders_tab_enabled = getSetting($conn, 'orders_tab_enabled', '1');

$captcha_n1 = rand(1, 10);
$captcha_n2 = rand(1, 10);
$_SESSION['captcha_answer'] = $captcha_n1 + $captcha_n2;

$related = $conn->prepare("SELECT * FROM products WHERE category = ? AND id != ? AND is_available = 1 ORDER BY sort_order, name LIMIT 4");
$rel_cat = $product['category'];
$rel_id = $product['id'];
$related->bind_param("si", $rel_cat, $rel_id);
$related->execute();
$related_res = $related->get_result();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="<?= htmlspecialchars($default_theme, ENT_QUOTES, 'UTF-8') ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<meta name="theme-color" content="#ffffff" id="metaTheme">
<?= theme_head_script('sitak_theme', $default_theme) ?>
<?= sitak_head_meta($conn, ['title' => $product['name'], 'description' => $product['description'] ?? '', 'image' => $product['image_path'] ?? '', 'type' => 'product']) ?>
<title><?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?> | <?= htmlspecialchars($site_name ?? 'سای‌تک', ENT_QUOTES, 'UTF-8') ?></title>
<link href="assets/fonts/vazirmatn.css" rel="stylesheet">
<style>
:root,[data-theme="light"]{--bg:#f5f5f7;--surface:#fff;--card:#fff;--card-el:#f0eff2;--input-bg:#f0eff2;--border:rgba(0,0,0,.07);--border-gold:rgba(236,181,52,.22);--text:#1d1d1f;--text2:#6e6e73;--text3:#aeaeb2;--header-bg:rgba(255,255,255,.82);--shadow-sm:0 1px 3px rgba(0,0,0,.06);--shadow-md:0 4px 14px rgba(0,0,0,.06);--red:#ff453a;--red-bg:rgba(255,69,58,.06);--gold:#ecb534;--gold-dark:#c49625;--gold-subtle:rgba(236,181,52,.07);--r:20px;--rs:14px;--rss:10px;--safe-t:env(safe-area-inset-top,0px);--safe-b:env(safe-area-inset-bottom,0px);--font:'Vazirmatn',-apple-system,BlinkMacSystemFont,sans-serif}
[data-theme="dark"]{--bg:#0a0a0c;--surface:#161618;--card:#1c1c1e;--card-el:#2c2c2e;--input-bg:#2c2c2e;--border:rgba(255,255,255,.08);--border-gold:rgba(236,181,52,.15);--text:#f0ece4;--text2:#9e9888;--text3:#5e5a52;--header-bg:rgba(10,10,12,.88);--shadow-sm:0 1px 3px rgba(0,0,0,.3);--red:#f87171;--red-bg:rgba(248,113,113,.08)}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent}
html{font-size:16px;-webkit-text-size-adjust:100%;overscroll-behavior:none}
body{font-family:var(--font);background:var(--bg);color:var(--text);min-height:100dvh;overflow-x:hidden;-webkit-font-smoothing:antialiased;padding-top:var(--safe-t);transition:background .35s,color .35s}
body::before{content:'';position:fixed;top:-40%;right:-20%;width:500px;height:500px;background:radial-gradient(circle,var(--gold-subtle),transparent 70%);pointer-events:none;z-index:0;transition:background .35s}

.app-bar{position:sticky;top:0;z-index:50;display:flex;align-items:center;justify-content:space-between;padding:10px 16px;min-height:60px;background:var(--header-bg);backdrop-filter:blur(28px) saturate(180%);-webkit-backdrop-filter:blur(28px) saturate(180%);border-bottom:1px solid var(--border);transition:background .35s;border-bottom-left-radius:16px;border-bottom-right-radius:16px}
.app-brand{display:flex;align-items:center;gap:10px}
.brand-icon{width:36px;height:36px;background:linear-gradient(135deg,var(--gold),var(--gold-dark));border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0}
.brand-text h1{font-size:1.05rem;font-weight:700;line-height:1.2}
.brand-text p{font-size:.73rem;color:var(--text3);font-weight:300}
.user-area{display:flex;align-items:center;gap:6px}
.header-icon-btn{position:relative;width:36px;height:36px;border-radius:50%;border:1px solid var(--border);background:var(--card-el);display:flex;align-items:center;justify-content:center;font-size:.95rem;color:var(--text);text-decoration:none;cursor:pointer;transition:all .2s;flex-shrink:0}.header-icon-btn:active{transform:scale(.9)}
.roll-count{position:absolute;top:-4px;left:-4px;min-width:18px;height:18px;background:var(--gold);color:#fff;font-size:.65rem;font-weight:700;border-radius:9px;display:flex;align-items:center;justify-content:center;padding:0 4px}
.hidden{display:none!important}
.toast{position:fixed;bottom:90px;left:50%;transform:translateX(-50%) translateY(100px);background:linear-gradient(135deg,var(--gold,#ecb534),var(--gold-dark,#c49625));color:#fff;border:none;padding:13px 22px;border-radius:16px;font-size:.82rem;font-weight:500;z-index:500;opacity:0;transition:all .35s cubic-bezier(.34,1.56,.64,1);width:max-content;max-width:min(420px,calc(100vw - 24px));text-align:center;box-shadow:0 6px 20px rgba(0,0,0,.2);white-space:pre-line}.toast.show{transform:translateX(-50%) translateY(0);opacity:1}

.content{position:relative;z-index:1;padding:12px 14px 10px;max-width:600px;margin:0 auto}

.prod-cover-full{width:100%;height:240px;object-fit:cover;border-radius:var(--r);margin-bottom:20px;background:var(--card-el)}
.prod-hdr{margin-bottom:16px}
.prod-pills{display:flex;flex-wrap:wrap;align-items:center;gap:6px;margin-bottom:10px}.prod-pills .prod-cat-badge{margin:0}.prod-pill{display:inline-flex;align-items:center;min-height:24px;line-height:17px;padding:2px 8px;border-radius:10px;font-size:.64rem;font-weight:700;vertical-align:middle;white-space:nowrap}.pill-green{background:#e8f5e9;color:#2e7d32;border:1px solid #81c784}.pill-blue{background:#e8f3ff;color:#1769aa;border:1px solid #64b5f6}.pill-red{background:#fdecea;color:#c62828;border:1px solid #e57373}
.shipping-badge{min-height:24px;line-height:17px;display:inline-flex;align-items:center;justify-content:center;padding:2px 8px;border-radius:10px;font-size:.64rem;font-weight:700;margin:0 4px 10px 0;background:#e8f5e9;border:0;color:#2e7d32;vertical-align:middle}.prod-cat-badge{border:1px solid var(--gold);min-height:24px;line-height:17px;display:inline-flex;align-items:center;justify-content:center;padding:2px 8px;border-radius:10px;font-size:.64rem;font-weight:700;margin-bottom:10px;background:var(--gold-subtle);color:var(--gold-dark);vertical-align:middle}
.prod-title-full{font-size:1.3rem;font-weight:900;line-height:1.7;margin-bottom:10px}
.prod-price-full{font-size:1.25rem;font-weight:900;color:var(--gold-dark)}
.prod-price-full small{font-size:.7rem;font-weight:400;color:var(--text3)}
.prod-desc-full{font-size:.95rem;line-height:2;color:var(--text);margin-bottom:20px;overflow-wrap:anywhere;word-break:break-word}
.prod-desc-full ul,.prod-desc-full ol{padding-right:1.4em;padding-left:0;margin:0 0 12px}
.prod-desc-full li{margin-bottom:4px}
.prod-desc-full table{width:100%;max-width:100%;display:block;overflow-x:auto;border-collapse:collapse;margin:10px 0}
.prod-desc-full td,.prod-desc-full th{border:1px solid var(--border);padding:6px 8px}
.prod-desc-full img{max-width:100%;height:auto;border-radius:var(--r);display:block}
.prod-desc-full blockquote{margin:12px 0;padding:10px 14px;border-right:4px solid var(--gold);background:var(--card-el);border-radius:var(--rs);color:var(--text2)}
.prod-desc-full a{color:#a8781a;font-weight:600;text-decoration:underline;text-underline-offset:3px}
[data-theme="dark"] .prod-desc-full a{color:#ecb534}
@media (prefers-color-scheme: dark){[data-theme="auto"] .prod-desc-full a{color:#ecb534}}
.prod-desc-full:empty{display:none}

.prod-add{display:flex;align-items:center;gap:12px;background:var(--card);border:1px solid var(--border-gold);border-radius:var(--r);padding:14px;margin-bottom:26px;box-shadow:var(--shadow-sm)}
#prodAddArea{min-width:132px;display:flex;justify-content:center;align-items:center;flex-shrink:0}
.prod-add-info{flex:1;font-size:.78rem;color:var(--text2);line-height:1.6}
.add-big{padding:8px 16px;background:linear-gradient(135deg,var(--gold),var(--gold-dark));color:#fff;border:none;border-radius:var(--rs);font-family:var(--font);font-size:.8rem;font-weight:700;cursor:pointer;;transition:all .12s;white-space:nowrap}
.add-big:active{}

/* Related */
.related-section{display:block;width:100%;box-sizing:border-box;margin:10px 0 0;padding-top:22px;border-top:1px solid var(--border)}
.related-section h3{font-size:1rem;font-weight:700;margin-bottom:16px;color:var(--gold-dark)}
.related-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.related-card{cursor:pointer;background:var(--card);border:1px solid rgba(139,90,43,.22);border-radius:var(--r);overflow:hidden;transition:transform .2s;box-shadow:var(--shadow-sm);display:flex;flex-direction:column}
.related-card:active{transform:scale(.98)}
.related-card .prod-img{width:100%;height:120px;object-fit:cover;background:var(--card-el);display:block}
.related-card .prod-info{padding:10px 12px 6px}
.related-card > .prod-info{flex:1;display:flex;flex-direction:column;padding-top:2px}
.related-card .prod-name{font-size:.88rem;font-weight:700;margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;line-height:1.3}
.related-card .prod-desc{font-size:.7rem;color:var(--text3);line-height:1.4;margin:6px 0;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.related-card .prod-foot{display:flex;align-items:center;justify-content:space-between;margin-top:auto;gap:6px}
.related-card .prod-price{font-size:.88rem;font-weight:800;color:var(--gold-dark)}
.related-card .prod-foot-actions{display:flex;align-items:center;gap:6px;min-width:0}
.related-card .prod-shipping-badge{display:inline-flex;align-items:center;justify-content:center;padding:2px 8px;border-radius:10px;min-height:24px;line-height:17px;font-size:.62rem;font-weight:700;background:#e8f5e9;border:0;color:#2e7d32;margin:0;max-width:120px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;vertical-align:middle}
.related-card .add-btn{width:32px;height:32px;min-width:32px;padding:0;border-radius:50%;background:var(--gold);color:#fff;border:none;display:flex;align-items:center;justify-content:center;font-size:1.1rem;line-height:1;cursor:pointer;transition:all .15s;box-shadow:0 2px 6px rgba(236,181,52,.2);font-family:var(--font);flex-shrink:0}
.related-card .add-btn:active{transform:scale(.9);background:var(--gold-dark)}
.related-card .add-btn.done{background:var(--green);animation:pop .3s}
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
.captcha-q{font-size:1.15rem;font-weight:700;color:var(--gold-dark);flex:1;text-align:center;direction:ltr}
.captcha-refresh{width:38px;height:38px;border-radius:50%;border:1px solid var(--border);background:var(--card-el);display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .2s;flex-shrink:0}
.captcha-row{position:relative;justify-content:center}.captcha-q{flex:0 1 auto}.captcha-refresh{position:absolute;left:10px;top:50%;margin-top:-19px}
.captcha-refresh:active{background:var(--gold-subtle);border-color:var(--gold);transform:rotate(180deg)}
.captcha-refresh svg{display:block}
.bottom-nav{position:fixed;bottom:0;left:0;right:0;z-index:100;background:var(--surface);border-top:1px solid var(--border);display:flex;justify-content:space-around;padding:8px 0;padding-bottom:calc(8px + var(--safe-b));border-top-left-radius:16px;border-top-right-radius:16px}.bottom-nav a{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;color:var(--text2);text-decoration:none;font-size:.6rem;min-width:44px;min-height:44px;padding:4px 0;transition:color .2s;white-space:nowrap}.bottom-nav a.active{color:var(--gold-dark)}.bottom-nav svg{width:22px;height:22px;transition:transform .2s}.bottom-nav a.active svg{color:var(--gold-dark);transform:scale(1.1)}@media(min-width:481px){
  html{scrollbar-gutter:stable}
  html,body{max-width:480px;margin:0 auto}
  body{box-shadow:0 0 0 1px var(--border),0 0 50px rgba(0,0,0,.1)}
  .bottom-nav,.admin-nav{left:50%;right:auto;transform:translateX(-50%);width:480px}
  body::before{left:50%;right:auto;transform:translateX(-50%);width:480px;height:480px;top:-20%}
}
@media(max-width:400px){.bottom-nav a{font-size:.55rem;min-width:38px;padding:2px 0}}
.comments-section-wrap{width:100%;box-sizing:border-box;padding-bottom:0}.comments-heading{width:calc(100% - 28px);max-width:none;box-sizing:border-box;margin:22px 14px 16px;padding:0 4px;text-align:right;font-size:1rem;font-weight:700;color:var(--gold-dark);direction:rtl}.comments-box{display:block;width:calc(100% - 28px);max-width:none;box-sizing:border-box;margin:0 14px;padding:18px;background:var(--surface);border:1px solid var(--border);border-radius:var(--r);box-shadow:none}.comments-box h3{font-size:1rem;margin:0 0 16px;text-align:center;color:var(--gold-dark)}.comment-item{padding:10px 0;border-bottom:1px solid var(--border);font-size:.8rem;line-height:1.8}.comment-meta{display:flex;align-items:center;justify-content:space-between;direction:ltr;min-height:32px;margin:0 0 10px;padding-top:0}.comment-item small{display:block;color:var(--text3);direction:ltr}.comment-author{display:block;color:var(--text2);direction:ltr;font-size:.9rem;font-weight:600}.comment-stars{display:block!important;width:max-content;direction:ltr!important;unicode-bidi:bidi-override!important;writing-mode:horizontal-tb!important;text-align:left!important;white-space:nowrap;letter-spacing:3px;font-size:1.05rem;line-height:1}.comment-stars .star-filled{color:#f2bd2f}.comment-stars .star-empty{color:#8f7225;opacity:.58}.comment-stars .star-filled{grid-column:auto}.comment-stars .star-empty{grid-column:auto}.comment-stars .star-filled{color:#f2bd2f;text-shadow:0 1px 2px rgba(242,189,47,.35)}.comment-stars .star-empty{color:#8f7225;text-shadow:0 1px 2px rgba(143,114,37,.2)}.star-picker button{color:var(--gold);letter-spacing:2px}.comment-rating-row{display:flex;align-items:center;justify-content:center;gap:8px;flex-wrap:wrap;margin-top:18px;margin-bottom:8px}.comment-rating-label{font-size:.75rem;color:var(--text2);font-weight:600}.comment-form textarea{width:100%;min-height:64px;margin:8px 0;padding:13px 14px;border:1.5px solid var(--border);border-radius:var(--rss);background:var(--input-bg);color:var(--text);font-family:var(--font);font-size:.92rem;outline:none;transition:border-color .2s,background .3s}.comment-form textarea:focus,.comment-captcha input:focus{border-color:var(--gold);box-shadow:none}.star-picker button{border:0;background:transparent;color:var(--gold);font-size:1.3rem;padding:2px;line-height:1;cursor:pointer;cursor:pointer;opacity:.35}.star-picker button.on,.star-picker button.hover-on,.star-picker button:hover{opacity:1}.comment-captcha{display:flex;flex-direction:column;align-items:stretch;gap:5px;margin-bottom:14px}.comment-captcha-label{display:block;font-size:.75rem;font-weight:600;color:var(--text2);margin-bottom:5px}.comment-captcha input,.comment-captcha-answer{display:block!important;text-align:center!important;direction:ltr!important;width:100%;min-width:0;padding:13px 14px;border:1.5px solid var(--border);border-radius:var(--rss);background:var(--input-bg);color:var(--text);font-family:var(--font);font-size:.92rem;outline:none;transition:border-color .2s,background .3s}.comment-login-hint{font-size:.78rem;color:var(--text3)}.comment-message{min-height:24px;margin:12px 0 2px;padding:9px 12px;border-radius:10px;text-align:center;direction:rtl;font-size:.8rem;font-weight:600;line-height:1.7;display:flex;align-items:center;justify-content:center}.comment-message:empty{display:none}.comment-message.error{color:#ff9b9b;background:rgba(190,55,55,.14);border:1px solid rgba(255,110,110,.3)}.comment-message.success{color:#b9edb9;background:rgba(65,160,80,.14);border:1px solid rgba(105,210,115,.3)}.comment-votes{display:flex;align-items:center;gap:8px;margin-top:10px;direction:ltr}.comment-vote{border:1px solid var(--border);background:var(--card-el);color:var(--text2);border-radius:999px;padding:4px 10px;font-family:var(--font);font-size:.78rem;cursor:pointer;transition:all .18s}.comment-vote:hover,.comment-vote.active{border-color:var(--gold);color:var(--gold-dark);background:var(--gold-subtle)}.comment-vote span{display:inline-block;min-width:1ch;margin-left:3px;font-family:var(--font)!important;direction:rtl}.comment-captcha-answer{font-family:var(--font)!important;font-variant-numeric:normal;font-feature-settings:normal}.page-bottom-spacer{height:calc(86px + var(--safe-b));width:100%;flex:none}
</style>
<?= custom_head_scripts($conn) ?>
</head>
<body>
<?php sitak_img_fallback_script(); ?>

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
  <?php if (!empty($product['image_path'])): ?>
    <img src="<?= htmlspecialchars($product['image_path'], ENT_QUOTES, 'UTF-8') ?>" alt="" class="prod-cover-full" data-fallback-emoji="<?= htmlspecialchars($custom_emoji, ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
  <?php endif; ?>

  <?php $localBadge = !empty($product['onsite_product']) ? sitak_onsite_product_badges($product) : null; ?>
  <div class="prod-hdr">
    <div class="prod-pills">
      <a href="shop.php?cat=<?= urlencode($product['category']) ?>" class="prod-cat-badge" style="text-decoration:none"><?= htmlspecialchars($product['category'], ENT_QUOTES, 'UTF-8') ?></a>
      <?php $stk_left = $product['stock_qty'] ?? null; if ($stk_left !== null && getSetting($conn, 'stock_badge_enabled', '1') === '1' && (int)$stk_left <= (int)getSetting($conn, 'stock_badge_threshold', '3')): ?><span class="prod-pill pill-red"><?= (int)$stk_left > 0 ? pn((int)$stk_left).' عدد باقی‌مانده' : 'ناموجود' ?></span><?php endif; ?>
      <?php if ($localBadge && $localBadge['scope'] !== ''): ?><span class="prod-pill pill-green">در <?= htmlspecialchars($localBadge['scope'], ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
      <?php if ($localBadge && $localBadge['hours'] !== ''): ?><span class="prod-pill pill-blue"><?= htmlspecialchars($localBadge['hours'], ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
      <?php if ($localBadge): ?><span class="prod-pill <?= $localBadge['open'] ? 'pill-green' : 'pill-red' ?>"><?= $localBadge['open'] ? 'ثبت سفارش باز' : 'ثبت سفارش بسته' ?></span><?php endif; ?>
    </div>
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px">
      <h1 class="prod-title-full" style="flex:1;min-width:0"><?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?></h1>
      <?php if ($product_rating): ?><span style="flex-shrink:0;font-size:.9rem;font-weight:700;color:var(--gold-dark);white-space:nowrap;padding-top:4px">★ <?= sitak_rating_text($product_rating['avg']) ?> <small style="font-weight:500;color:var(--text3)">(<?= pn(number_format($product_rating['count'])) ?>)</small></span><?php endif; ?>
    </div>
    <div class="prod-price-full"><?= pn(number_format($product['price'])) ?> <small>تومان</small></div>
  </div>

  <?php if (!empty($product['description'])): ?>
  <div class="prod-desc-full"><?= $product['description'] ?></div>
  <?php endif; ?>
  <?php if (!empty($product_tags)): ?>
  <div style="display:flex;flex-wrap:wrap;gap:6px;padding:0 14px 12px">
    <?php foreach ($product_tags as $pt): ?>
      <a href="shop.php?tag=<?= rawurlencode($pt['slug']) ?>" style="padding:3px 10px;background:var(--card-el);border:1px solid var(--border);border-radius:10px;font-size:.7rem;color:var(--text2);text-decoration:none">#<?= htmlspecialchars($pt['name'], ENT_QUOTES, 'UTF-8') ?></a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="prod-add">
    <div class="prod-add-info">این محصول را به سبد خرید اضافه کنید و از منوی ما لذت ببرید</div>
    <div id="prodAddArea" style="flex-shrink:0"></div>
  </div>

  <?php if ($related_res->num_rows > 0): ?>
  <div class="related-section">
    <h3>🌐 محصولات مرتبط</h3>
    <div class="related-grid">
      <?php while ($r = $related_res->fetch_assoc()): ?>
      <article class="related-card" data-id="<?= (int)$r['id'] ?>" onclick="if(!event.target.closest('.add-btn'))location.href='product.php?id=<?= (int)$r['id'] ?>'">
        <a href="product.php?id=<?= (int)$r['id'] ?>" style="display:block;text-decoration:none;color:inherit">
        <?php if (!empty($r['image_path'])): ?>
          <img src="<?= htmlspecialchars($r['image_path'], ENT_QUOTES, 'UTF-8') ?>" alt="" class="prod-img" data-fallback-emoji="<?= htmlspecialchars($custom_emoji, ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
        <?php else: ?>
          <div class="prod-img" style="display:flex;align-items:center;justify-content:center;font-size:2.2rem"><?= htmlspecialchars($custom_emoji, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <div class="prod-info">
          <div class="prod-name"><?= htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        </a>
        <div class="prod-info">
          <?php if (!empty($r['description'])): ?><div class="prod-desc"><?= htmlspecialchars($r['description'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
          <div class="prod-foot">
            <span class="prod-price"><?= pn(number_format($r['price'])) ?> <small style="font-size:.65rem;font-weight:400">تومان</small></span>
            <div class="prod-foot-actions">
              <?php if (!empty($r['shipping_city']) || !empty($r['shipping_province'])): ?><span class="prod-shipping-badge">در <?= htmlspecialchars($r['shipping_city'] ?: $r['shipping_province'], ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
              <button class="add-btn" data-stock="<?= $r['stock_qty'] === null ? '' : (int)$r['stock_qty'] ?>" data-id="<?= (int)$r['id'] ?>" data-name="<?= htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8') ?>" data-price="<?= (int)$r['price'] ?>" data-img="<?= htmlspecialchars($r['image_path'] ?? '', ENT_QUOTES, 'UTF-8') ?>" data-ship-province="<?= htmlspecialchars($r['shipping_province'] ?? '', ENT_QUOTES, 'UTF-8') ?>" data-ship-city="<?= htmlspecialchars($r['shipping_city'] ?? '', ENT_QUOTES, 'UTF-8') ?>" data-local="<?= !empty($r['onsite_product']) ? '1' : '0' ?>" onclick="addToCartRel(this)" title="افزودن به سبد">+</button>
            </div>
          </div>
        </div>
      </article>
      <?php endwhile; ?>
    </div>
  </div>
  <?php endif; ?>
</main>

<?php $comment_type='product';$comment_target_id=$pid;include 'comments.php'; ?>
<div class="page-bottom-spacer" aria-hidden="true"></div>
<!-- Sheet: Cart -->
<?php include 'cart_sheet.php'; ?>

<!-- Sheet: Register -->
<?php include 'auth_sheet.php'; ?>

<!-- Toast -->
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
  <a href="shop.php" class="active">
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
var USER_PHONE='<?= addslashes($phone) ?>';
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
function openSheet(t){document.getElementById(t+'Ov').classList.add('open');document.getElementById(t+'Sheet').classList.add('open')}
function closeSheet(t){document.getElementById(t+'Ov').classList.remove('open');document.getElementById(t+'Sheet').classList.remove('open');if(t==='cart'){var b=document.getElementById('cartMessage');if(b){b.classList.add('hidden');b.classList.add('msg-hide');}}}

/* ── تعداد این محصول در سبد ── */
var PID=<?= (int)$product['id'] ?>;
var PSTOCK=<?= $product['stock_qty'] === null ? 'null' : (int)$product['stock_qty'] ?>;
var PNAME=<?= json_encode($product['name'], JSON_UNESCAPED_UNICODE) ?>;
var PPRICE=<?= (int)$product['price'] ?>;
var PIMG=<?= json_encode($product['image_path'] ?? '', JSON_UNESCAPED_UNICODE) ?>;
var PSHIP_PROVINCE=<?= json_encode($product['shipping_province'] ?? '', JSON_UNESCAPED_UNICODE) ?>;var PSHIP_CITY=<?= json_encode($product['shipping_city'] ?? '', JSON_UNESCAPED_UNICODE) ?>;
var PLOCAL=<?= !empty($product['onsite_product']) ? 'true' : 'false' ?>;

function renderProdAdd(){
  var area=document.getElementById('prodAddArea');
  var it=cart.find(function(i){return i.id===PID});
  if(it){
    area.innerHTML='<div style="display:flex;align-items:center;gap:8px"><button class="qty-btn" onclick="chgProdQty(-1)">−</button><span class="qty-val">'+pn(it.qty)+'</span><button class="qty-btn" onclick="chgProdQty(1)">+</button></div>';
  }else{
    area.innerHTML='<button class="add-big" style="width:100%" onclick="addProdToCart()">افزودن به سبد +</button>';
  }
}
function updateProductQtyUI(){if(typeof renderProdAdd==='function')renderProdAdd()}
function addProdToCart(){
  {var _p0=cart.find(function(i){return i.id===PID});if(_stockLimitHit({name:PNAME,stock:PSTOCK},(_p0?_p0.qty:0)+1))return;}
  _addProductGuard({id:PID,name:PNAME,price:PPRICE,img:PIMG,shipping_province:PSHIP_PROVINCE,shipping_city:PSHIP_CITY,onsite_product:PLOCAL},function(){var ex=cart.find(function(i){return i.id===PID});if(ex)ex.qty++;else cart.push({stock:PSTOCK,id:PID,name:PNAME,price:PPRICE,img:PIMG,shipping_province:PSHIP_PROVINCE,shipping_city:PSHIP_CITY,onsite_product:PLOCAL,qty:1});persist();_cmsg(PNAME+' به سبد اضافه شد');openSheet('cart');});
}
function chgProdQty(d){
  var it=cart.find(function(i){return i.id===PID});
  if(!it)return;
  if(d>0&&_stockLimitHit({name:PNAME,stock:PSTOCK},it.qty+1))return;
  if(d>0){_addProductGuard({id:PID,name:PNAME,price:PPRICE,img:PIMG,shipping_province:PSHIP_PROVINCE,shipping_city:PSHIP_CITY,onsite_product:PLOCAL},function(){it.qty+=d;persist();_cmsg('یک '+PNAME+' به سبد اضافه شد');openSheet('cart')});return;}
  it.qty+=d;
  if(it.qty<1){
    cart=cart.filter(function(i){return i.id!==PID});
    persist();
    _cmsg(PNAME+' از سبد حذف شد');
  }else{
    persist();
    if(d>0){_cmsg('یک '+PNAME+' به سبد اضافه شد')}
    else{_cmsg('یک '+PNAME+' از سبد کم شد')}
  }
  openSheet('cart');
}
function addToCartRel(btn){var data={stock:(btn.dataset.stock===undefined||btn.dataset.stock==="")?null:+btn.dataset.stock,id:+btn.dataset.id,name:btn.dataset.name,price:+btn.dataset.price,img:btn.dataset.img||'',shipping_province:btn.dataset.shipProvince||'',shipping_city:btn.dataset.shipCity||'',onsite_product:btn.dataset.local==='1'};{var _e1=cart.find(function(i){return i.id===data.id});if(_stockLimitHit(data,(_e1?_e1.qty:0)+1))return;}
_addProductGuard(data,function(){var ex=cart.find(function(i){return i.id===data.id});if(ex)ex.qty++;else cart.push({stock:data.stock,id:data.id,name:data.name,price:data.price,img:data.img,shipping_province:data.shipping_province,shipping_city:data.shipping_city,onsite_product:data.onsite_product,qty:1});persist();btn.classList.add('done');btn.textContent='✓';setTimeout(function(){btn.classList.remove('done');btn.textContent='+'},400);_cmsg(data.name+' به سبد اضافه شد');openSheet('cart')})}
renderProdAdd();
document.querySelectorAll('.bottom-nav a').forEach(function(a){
  if(a.pathname===window.location.pathname){a.classList.add('active')}else{a.classList.remove('active')}
});
</script>
</body>
</html>
