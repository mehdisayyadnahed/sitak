<?php
require_once 'config.php';
$shop_enabled = getSetting($conn, 'shop_enabled', '1');
$orders_tab_enabled = getSetting($conn, 'orders_tab_enabled', '1');
$custom_emoji = getSetting($conn, 'custom_emoji', '🌐');
if ($shop_enabled !== '1') { header('Location: index.php'); exit; }
$site_name    = getSetting($conn, 'site_name', 'سای‌تک');
$site_slogan  = getSetting($conn, 'site_slogan', 'یک وبسایت تک');
track_view($conn, 'shop');

$captcha_n1 = rand(1, 10);
$captcha_n2 = rand(1, 10);
$_SESSION['captcha_answer'] = $captcha_n1 + $captcha_n2;

// ── دریافت دسته‌بندی‌ها و محصولات ──
$categories = [];
$cat_res = $conn->query("SELECT sc.`name` FROM `shop_categories` sc WHERE EXISTS (SELECT 1 FROM `products` p WHERE p.`category` = sc.`name` AND p.`is_available`=1) ORDER BY sc.`sort_order`, sc.`id`");
while ($row = $cat_res->fetch_assoc()) { $categories[] = $row['name']; }

$products = [];
$prod_res = $conn->query("SELECT * FROM `products` WHERE `is_available`=1 ORDER BY `sort_order`, `name`");
while ($row = $prod_res->fetch_assoc()) { $products[] = $row; }
$tag_view_name = '';
$filter_tag = $_GET['tag'] ?? '';
if ($filter_tag !== '') {
    $tg = $conn->prepare("SELECT id, name FROM product_tags WHERE slug = ? LIMIT 1");
    $tg->bind_param("s", $filter_tag); $tg->execute();
    $tgr = $tg->get_result()->fetch_assoc();
    if ($tgr) {
        $tag_view_name = $tgr['name'];
        $tagged = [];
        $tr = $conn->prepare("SELECT product_id FROM product_tag_relations WHERE tag_id = ?");
        $tr->bind_param("i", $tgr['id']); $tr->execute();
        $trr = $tr->get_result();
        while ($x = $trr->fetch_assoc()) $tagged[(int)$x['product_id']] = true;
        $products = array_values(array_filter($products, function ($p) use ($tagged) { return isset($tagged[(int)$p['id']]); }));
    }
}
$show_product_ratings = getSetting($conn, 'show_product_ratings', '1') === '1';
$product_ratings = $show_product_ratings ? sitak_product_ratings($conn, array_column($products, 'id')) : [];

// ── اطلاعات کاربر ──
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

$default_theme   = getSetting($conn, 'default_theme', 'auto');
$custom_favicon  = getSetting($conn, 'custom_favicon', '');
$custom_logo     = getSetting($conn, 'custom_logo', '');
$loyalty_enabled = getSetting($conn, 'loyalty_enabled', '1');
$chance_enabled = getSetting($conn, 'chance_enabled', '1');
$blog_enabled = getSetting($conn, 'blog_enabled', '1');
$shop_enabled = getSetting($conn, 'shop_enabled', '1');
$orders_tab_enabled = getSetting($conn, 'orders_tab_enabled', '1');
$shop_page_title = getSetting($conn, 'shop_page_title', 'منو');
$shop_page_description = getSetting($conn, 'shop_page_description', 'محصولات تازه‌ی ما را ببینید و سفارش دهید');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="<?= htmlspecialchars($default_theme, ENT_QUOTES, 'UTF-8') ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<meta name="theme-color" content="#ffffff" id="metaTheme">
<?= theme_head_script('sitak_theme', $default_theme) ?>
<?= sitak_head_meta($conn, ['title' => $shop_page_title, 'description' => $shop_page_description]) ?>
<title>منو | <?= htmlspecialchars($site_name ?? 'سای‌تک', ENT_QUOTES, 'UTF-8') ?></title>
<link href="assets/fonts/vazirmatn.css" rel="stylesheet">
<style>
:root,[data-theme="light"]{--bg:#f5f5f7;--surface:#fff;--card:#fff;--card-el:#f0eff2;--border:rgba(255,255,255,.08);--border-gold:rgba(236,181,52,.22);--text:#1d1d1f;--text2:#6e6e73;--text3:#aeaeb2;--header-bg:rgba(255,255,255,.82);--shadow-sm:0 1px 3px rgba(0,0,0,.06);--shadow-md:0 4px 14px rgba(0,0,0,.06);--sheet-shadow:0 -10px 40px rgba(0,0,0,.08);--overlay-bg:rgba(0,0,0,.3);--toast-bg:#fff;--input-bg:#f0eff2;--green:#30d158;--green-text:#1a8a3f;--red:#ff453a;--red-bg:rgba(255,69,58,.06);--gold:#ecb534;--gold-dark:#c49625;--gold-glow:rgba(236,181,52,.12);--gold-subtle:rgba(236,181,52,.07);--r:20px;--rs:14px;--rss:10px;--safe-t:env(safe-area-inset-top,0px);--safe-b:env(safe-area-inset-bottom,0px);--font:'Vazirmatn',-apple-system,BlinkMacSystemFont,sans-serif}
[data-theme="dark"]{--bg:#0a0a0c;--surface:#161618;--card:#1c1c1e;--card-el:#2c2c2e;--border:rgba(255,255,255,.08);--border-gold:rgba(236,181,52,.15);--text:#f0ece4;--text2:#9e9888;--text3:#5e5a52;--header-bg:rgba(10,10,12,.88);--shadow-sm:0 1px 3px rgba(0,0,0,.3);--sheet-shadow:0 -10px 40px rgba(0,0,0,.35);--overlay-bg:rgba(0,0,0,.5);--toast-bg:#2c2c2e;--input-bg:#2c2c2e;--green:#34d399;--green-text:#34d399;--red:#f87171;--red-bg:rgba(248,113,113,.08)}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent}
html{font-size:16px;-webkit-text-size-adjust:100%;overscroll-behavior:none}
body{font-family:var(--font);background:var(--bg);color:var(--text);min-height:100dvh;overflow-x:hidden;-webkit-font-smoothing:antialiased;padding-top:var(--safe-t);transition:background .35s,color .35s}
body::before{content:'';position:fixed;top:-40%;right:-20%;width:500px;height:500px;background:radial-gradient(circle,var(--gold-glow),transparent 70%);pointer-events:none;z-index:0;transition:background .35s}

/* App Bar */
.app-bar{position:sticky;top:0;z-index:50;display:flex;align-items:center;justify-content:space-between;padding:10px 16px;min-height:60px;background:var(--header-bg);backdrop-filter:blur(28px) saturate(180%);-webkit-backdrop-filter:blur(28px) saturate(180%);border-bottom:1px solid var(--border);transition:background .35s;border-bottom-left-radius:16px;border-bottom-right-radius:16px}
.app-brand{display:flex;align-items:center;gap:10px}
.brand-icon{width:36px;height:36px;background:linear-gradient(135deg,var(--gold),var(--gold-dark));border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0}
.brand-text h1{font-size:1.05rem;font-weight:700;line-height:1.2}
.brand-text p{font-size:.73rem;color:var(--text3);font-weight:300}
.user-area{display:flex;align-items:center;gap:6px}
.header-icon-btn{position:relative;width:36px;height:36px;border-radius:50%;border:1px solid var(--border);background:var(--card-el);display:flex;align-items:center;justify-content:center;font-size:.95rem;color:var(--text);text-decoration:none;cursor:pointer;transition:all .2s;flex-shrink:0}.header-icon-btn:active{transform:scale(.9)}
.roll-count{position:absolute;top:-4px;left:-4px;min-width:18px;height:18px;background:var(--gold);color:#fff;font-size:.65rem;font-weight:700;border-radius:9px;display:flex;align-items:center;justify-content:center;padding:0 4px}
.avatar{width:30px;height:30px;border-radius:50%;background:var(--card-el);display:flex;align-items:center;justify-content:center;font-size:.7rem;color:var(--gold-dark);border:1.5px solid var(--gold);font-weight:700}
.theme-btn{width:32px;height:32px;border-radius:50%;border:1px solid var(--border);background:var(--card-el);display:flex;align-items:center;justify-content:center;font-size:.85rem;cursor:pointer;transition:all .2s}.theme-btn:active{transform:scale(.9);background:var(--gold-subtle)}
.logout-link{font-size:.7rem;color:var(--red);text-decoration:none;font-weight:600;padding:4px 8px;border-radius:8px;background:var(--red-bg);transition:all .2s}

/* Content */
.content{position:relative;z-index:1;padding:12px 14px 100px;max-width:480px;margin:0 auto}
.hero{text-align:center;padding:28px 12px 20px}.hero h2{font-size:1.35rem;font-weight:900;color:var(--gold-dark);margin-bottom:8px;line-height:1.6}.hero p{font-size:.82rem;color:var(--text2);line-height:1.7;max-width:340px;margin:0 auto 12px}

/* Category Tabs */
.cat-tabs{display:flex;gap:8px;overflow-x:auto;padding:4px 4px 12px;scrollbar-width:none}.cat-tabs::-webkit-scrollbar{display:none}
.cat-tab{border:1px solid var(--border-gold);min-height:36px;display:inline-flex;align-items:center;justify-content:center;padding:8px 16px;background:var(--card-el);border:1px solid var(--border);border-radius:20px;font-size:.8rem;font-weight:600;color:var(--text2);white-space:nowrap;cursor:pointer;transition:all .2s;flex-shrink:0;font-family:var(--font);vertical-align:middle}
.cat-tab.active{background:var(--gold);color:#fff;border-color:var(--gold)}

/* Product Cards */
.prod-section{margin-bottom:20px}.prod-section.hidden{display:none}
.sec-title{font-size:.9rem;font-weight:700;color:var(--gold-dark);margin-bottom:12px;padding:0 4px}
.prod-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.prod-card{cursor:pointer;background:var(--card);border:1px solid rgba(139,90,43,.22);border-radius:var(--r);overflow:hidden;transition:transform .2s;box-shadow:var(--shadow-sm);display:flex;flex-direction:column}
.prod-card:active{transform:scale(.98)}
.prod-img{width:100%;height:120px;object-fit:cover;background:var(--card-el);display:block}
.prod-info{padding:10px 12px 6px}
.prod-card a .prod-info{padding-bottom:2px}
.prod-card > .prod-info{flex:1;display:flex;flex-direction:column;padding-top:2px}
.prod-name{font-size:.88rem;font-weight:700;margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;line-height:1.3}
.prod-shipping-badge{display:inline-flex;align-items:center;justify-content:center;padding:2px 8px;border-radius:10px;min-height:24px;line-height:17px;font-size:.62rem;font-weight:700;background:#e8f5e9;border:1px solid #81c784;color:#2e7d32;margin-bottom:6px;vertical-align:middle}.prod-desc{font-size:.7rem;color:var(--text3);line-height:1.4;margin:6px 0 6px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.prod-foot{display:flex;align-items:center;justify-content:space-between;margin-top:auto;gap:6px}.prod-foot-actions{display:flex;align-items:center;gap:6px;min-width:0}.prod-foot-actions .prod-shipping-badge{margin:0;max-width:120px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.prod-price{font-size:.88rem;font-weight:800;color:var(--gold-dark)}
.add-btn{width:32px;height:32px;border-radius:50%;background:var(--gold);color:#fff;border:none;display:flex;align-items:center;justify-content:center;font-size:1.1rem;cursor:pointer;transition:all .15s;box-shadow:0 2px 6px rgba(236,181,52,.2);font-family:var(--font)}
.add-btn:active{transform:scale(.9);background:var(--gold-dark)}
.add-btn.done{background:var(--green);animation:pop .3s}
@keyframes pop{50%{transform:scale(1.2)}}

/* Bottom Sheet */
.overlay{position:fixed;inset:0;background:var(--overlay-bg);backdrop-filter:blur(8px);z-index:100;opacity:0;pointer-events:none;transition:opacity .3s}.overlay.open{opacity:1;pointer-events:auto}
.sheet{position:fixed;bottom:0;left:0;right:0;z-index:101;background:var(--surface);border-radius:22px 22px 0 0;padding:0 18px 18px;padding-bottom:calc(18px + var(--safe-b));max-height:82vh;overflow-y:auto;transform:translateY(100%);transition:transform .38s cubic-bezier(.32,.72,0,1);box-shadow:var(--sheet-shadow)}.sheet.open{transform:translateY(0)}
.handle{width:34px;height:4px;background:#d1d1d6;border-radius:4px;margin:10px auto 18px}
.sheet-hdr{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px}
.sheet-title{font-size:1.05rem;font-weight:700}
.close-x{width:32px;height:32px;border-radius:50%;border:1px solid var(--border);background:var(--card-el);display:flex;align-items:center;justify-content:center;font-size:1rem;cursor:pointer;color:var(--text2)}
.close-x:active{background:var(--red-bg);color:var(--red)}

/* Result Overlay */
.result-ov{position:fixed;inset:0;z-index:200;display:none;align-items:center;justify-content:center;padding:18px;background:rgba(0,0,0,.45);backdrop-filter:blur(16px)}.result-ov.open{display:flex}
.res-card{background:var(--surface);border:1.5px solid var(--green);border-radius:26px;padding:30px 22px;max-width:370px;width:100%;text-align:center;animation:slideUp .4s cubic-bezier(.34,1.56,.64,1);box-shadow:0 10px 40px rgba(0,0,0,.15)}
@keyframes slideUp{from{transform:translateY(30px);opacity:0}to{transform:translateY(0);opacity:1}}
.res-icon{font-size:2.8rem;margin-bottom:10px;animation:float 2s ease-in-out infinite}
@keyframes float{50%{transform:translateY(-7px)}}
.res-card h3{font-size:1.15rem;font-weight:900;margin-bottom:6px;color:var(--green-text)}
.res-desc{font-size:.82rem;color:var(--text2);line-height:1.7;margin-bottom:16px}
.res-info{background:var(--bg);border:1px solid var(--border);border-radius:var(--rss);padding:14px;margin-bottom:16px;text-align:right}
.res-row{display:flex;justify-content:space-between;font-size:.8rem;margin-bottom:4px}.res-row:last-child{margin:0}.res-row span:first-child{color:var(--text2)}.res-row span:last-child{font-weight:700}
.btn-ok{width:100%;padding:14px;background:linear-gradient(135deg,var(--gold),var(--gold-dark));color:#fff;border:none;border-radius:var(--rss);font-family:var(--font);font-size:.92rem;font-weight:700;cursor:pointer;;transition:all .12s}.btn-ok:active{}

/* Toast */
.toast{position:fixed;bottom:80px;left:50%;transform:translateX(-50%) translateY(100px);background:linear-gradient(135deg,var(--gold,#ecb534),var(--gold-dark,#c49625));border:none;color:#fff;padding:13px 22px;border-radius:16px;font-size:.82rem;font-weight:500;z-index:500;opacity:0;transition:all .35s cubic-bezier(.34,1.56,.64,1);max-width:min(88%,420px);text-align:center;box-shadow:0 4px 20px rgba(0,0,0,.12);white-space:pre-line}.toast.show{transform:translateX(-50%) translateY(0);opacity:1}

/* Bottom Navigation */
.bottom-nav{position:fixed;bottom:0;left:0;right:0;z-index:100;background:var(--surface);border-top:1px solid var(--border);display:flex;justify-content:space-around;padding:8px 0;padding-bottom:calc(8px + var(--safe-b));border-top-left-radius:16px;border-top-right-radius:16px}.bottom-nav a{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;color:var(--text2);text-decoration:none;font-size:.6rem;min-width:44px;min-height:44px;padding:4px 0;transition:color .2s;white-space:nowrap}.bottom-nav a.active{color:var(--gold-dark)}.bottom-nav svg{width:22px;height:22px;transition:transform .2s}.bottom-nav a.active svg{color:var(--gold-dark);transform:scale(1.1)}@media(max-width:400px){.bottom-nav a{font-size:.55rem;min-width:38px;padding:2px 0}}

@media(max-width:400px){.prod-grid{grid-template-columns:1fr}}
.hidden{display:none!important}

/* ── Register Sheet ── */
.overlay{position:fixed;inset:0;background:rgba(0,0,0,.3);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);z-index:100;opacity:0;pointer-events:none;transition:opacity .3s}
.overlay.open{opacity:1;pointer-events:auto}
.sheet{position:fixed;bottom:0;left:0;right:0;z-index:101;background:var(--surface);border-radius:22px 22px 0 0;padding:0 18px 18px;padding-bottom:calc(18px + var(--safe-b));max-height:82vh;overflow-y:auto;transform:translateY(100%);transition:transform .38s cubic-bezier(.32,.72,0,1),background .35s;-webkit-overflow-scrolling:touch;box-shadow:0 -10px 40px rgba(0,0,0,.08)}
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

@media(min-width:481px){
  html{scrollbar-gutter:stable}
  html,body{max-width:480px;margin:0 auto}
  body{box-shadow:0 0 0 1px var(--border),0 0 50px rgba(0,0,0,.1)}
  .bottom-nav,.admin-nav{left:50%;right:auto;transform:translateX(-50%);width:480px}
  body::before{left:50%;right:auto;transform:translateX(-50%);width:480px;height:480px;top:-20%}
}
</style>
<?= custom_head_scripts($conn) ?>
</head>
<body>
<?php sitak_img_fallback_script(); ?>

<!-- ═══ App Bar ═══ -->
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

<!-- Content -->
<main class="content">
  <section class="hero"><h2><?= htmlspecialchars($shop_page_title, ENT_QUOTES, 'UTF-8') ?></h2><p><?= nl2br(htmlspecialchars($shop_page_description, ENT_QUOTES, 'UTF-8')) ?></p></section>

  <!-- Tabs -->
  <div class="cat-tabs">
    <button class="cat-tab active" onclick="filterCat('all',this)">همه</button>
    <?php foreach ($categories as $c): ?>
      <button class="cat-tab" data-cat="<?= htmlspecialchars($c, ENT_QUOTES, 'UTF-8') ?>" onclick="filterCat('<?= htmlspecialchars($c, ENT_QUOTES, 'UTF-8') ?>',this)"><?= htmlspecialchars($c, ENT_QUOTES, 'UTF-8') ?></button>
    <?php endforeach; ?>
  </div>

  <!-- Products -->
  <?php
  $all_cats = array_unique(array_map(function($p){return $p['category'];}, $products));
  foreach ($all_cats as $cat):
    $items = array_filter($products, function($p) use ($cat){return $p['category'] === $cat;});
  ?>
  <section class="prod-section" data-cat="<?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>">
    <h3 class="sec-title"><?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?></h3>
    <div class="prod-grid">
      <?php foreach ($items as $p): $localBadge = !empty($p['onsite_product']) ? sitak_onsite_product_badges($p) : null; ?>
      <article class="prod-card" data-id="<?= (int)$p['id'] ?>" onclick="if(!event.target.closest('.add-btn'))location.href='product.php?id=<?= (int)$p['id'] ?>'">
        <a href="product.php?id=<?= (int)$p['id'] ?>" style="display:block;text-decoration:none;color:inherit">
        <?php if (!empty($p['image_path'])): ?>
          <img src="<?= htmlspecialchars($p['image_path'], ENT_QUOTES, 'UTF-8') ?>" alt="" class="prod-img" data-fallback-emoji="<?= htmlspecialchars($custom_emoji, ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
        <?php else: ?>
          <div class="prod-img" style="display:flex;align-items:center;justify-content:center;font-size:2.2rem"><?= htmlspecialchars($custom_emoji, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <div class="prod-info">
          <div style="display:flex;justify-content:space-between;align-items:center;gap:6px;min-width:0"><div class="prod-name" style="flex:1;min-width:0"><?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?></div><?php if (isset($product_ratings[(int)$p['id']])): ?><span style="flex-shrink:0;font-size:.72rem;font-weight:700;color:var(--gold-dark);white-space:nowrap">★ <?= sitak_rating_text($product_ratings[(int)$p['id']]['avg']) ?></span><?php endif; ?></div>
        </div>
        </a>
        <div class="prod-info">
          <?php if ($p['description']): ?><div class="prod-desc"><?= htmlspecialchars(strip_tags($p['description']), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
          <div class="prod-foot">
            <span class="prod-price"><?= pn(number_format($p['price'])) ?> <small style="font-size:.65rem;font-weight:400">تومان</small></span>
            <div class="prod-foot-actions">
              <?php if ($localBadge && $localBadge['scope'] !== ''): ?><span class="prod-shipping-badge">در <?= htmlspecialchars($localBadge['scope'], ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
            <button class="add-btn" data-stock="<?= $p['stock_qty'] === null ? '' : (int)$p['stock_qty'] ?>" data-id="<?= (int)$p['id'] ?>" data-name="<?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?>" data-price="<?= (int)$p['price'] ?>" data-img="<?= htmlspecialchars($p['image_path'] ?? '', ENT_QUOTES, 'UTF-8') ?>" data-ship-province="<?= htmlspecialchars($p['shipping_province'] ?? '', ENT_QUOTES, 'UTF-8') ?>" data-ship-city="<?= htmlspecialchars($p['shipping_city'] ?? '', ENT_QUOTES, 'UTF-8') ?>" data-local="<?= !empty($p['onsite_product']) ? '1' : '0' ?>" onclick="addToCart(this)">+</button>
            </div>
          </div>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endforeach; ?>

  <?php if (empty($products)): ?>
    <div style="text-align:center;padding:60px 20px;color:var(--text3)">
      <div style="font-size:3rem;margin-bottom:12px"><?= htmlspecialchars($custom_emoji, ENT_QUOTES, 'UTF-8') ?></div>
      <p>هنوز محصولی اضافه نشده است</p>
    </div>
  <?php endif; ?>
</main>

<!-- Cart Sheet -->
<?php include 'cart_sheet.php'; ?>

<!-- Sheet: Register -->
<?php include 'auth_sheet.php'; ?>

<!-- Toast -->
<div class="toast" id="toast"></div>

<script>
var USER_PHONE = '<?= addslashes($phone) ?>';
var DEFAULT_THEME = '<?= $default_theme ?>';

/* ── Utility ── */
function pn(n){return String(n).replace(/\d/g,function(d){return '۰۱۲۳۴۵۶۷۸۹'[d]})}
function esc(s){if(!s)return'';return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;')}
function showToast(m){var t=document.getElementById('toast');t.textContent=m;t.classList.add('show');clearTimeout(t._timer);t._timer=setTimeout(function(){t.classList.remove('show')},3000)}

/* ── Theme ── */
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

/* ── Category Filter ── */
function filterCat(cat,btn){
  document.querySelectorAll('.cat-tab').forEach(function(b){b.classList.remove('active')});
  btn.classList.add('active');
  document.querySelectorAll('.prod-section').forEach(function(s){
    s.classList.toggle('hidden', cat!=='all' && s.getAttribute('data-cat')!==cat);
  });
}

(function(){
  var qc=new URLSearchParams(location.search).get('cat');
  if(!qc) return;
  var qb=null;
  document.querySelectorAll('.cat-tab[data-cat]').forEach(function(b){ if(b.getAttribute('data-cat')===qc) qb=b; });
  if(qb) filterCat(qc,qb);
})();

/* ── Sheets ── */
function openSheet(t){document.getElementById(t+'Ov').classList.add('open');document.getElementById(t+'Sheet').classList.add('open')}
function closeSheet(t){document.getElementById(t+'Ov').classList.remove('open');document.getElementById(t+'Sheet').classList.remove('open');if(t==='cart'){var b=document.getElementById('cartMessage');if(b){b.classList.add('hidden');b.classList.add('msg-hide');}}}

/* addToCart/persist/rmFromCart/chgQty/cartTotal/cartCount/renderCart/submitOrder — در cart_sheet.php */

// Bottom nav active state
document.querySelectorAll('.bottom-nav a').forEach(function(a){
  if(a.pathname===window.location.pathname){a.classList.add('active')}else{a.classList.remove('active')}
});
</script>

<!-- Bottom Navigation -->
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
(function(){
  document.querySelectorAll('.cat-tabs').forEach(function(scroller){
    scroller.style.cursor='grab';
    scroller.style.userSelect='none';
    scroller.addEventListener('wheel',function(e){
      if(Math.abs(e.deltaY)>Math.abs(e.deltaX)){e.preventDefault();scroller.scrollLeft+=e.deltaY;}
    },{passive:false});
    var down=false,startX=0,startScroll=0,moved=false;
    scroller.addEventListener('pointerdown',function(e){
      if(e.pointerType==='mouse' && e.button!==0)return;
      down=true;moved=false;startX=e.clientX;startScroll=scroller.scrollLeft;scroller.style.cursor='grabbing';
    });
    scroller.addEventListener('pointermove',function(e){
      if(!down)return;var dx=e.clientX-startX;if(Math.abs(dx)>4){moved=true;if(!scroller.hasPointerCapture(e.pointerId))try{scroller.setPointerCapture(e.pointerId)}catch(x){}}scroller.scrollLeft=startScroll-dx;
    });
    function release(e){if(!down)return;down=false;scroller.style.cursor='grab';if(e.pointerId!=null)try{scroller.releasePointerCapture(e.pointerId)}catch(x){} if(moved){scroller.dataset.dragged='1';setTimeout(function(){delete scroller.dataset.dragged},80)}}
    scroller.addEventListener('pointerup',release);scroller.addEventListener('pointercancel',release);
    scroller.addEventListener('click',function(e){if(scroller.dataset.dragged==='1'){e.preventDefault();e.stopPropagation();}},true);
  });
})();
</script>
</body>
  <script>
  /* openSheet/closeSheet/refreshCaptcha/doRegister — در auth_sheet.php */
  </script>
  </html>
