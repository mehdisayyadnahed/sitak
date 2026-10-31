<?php
require_once 'config.php';
$blog_views_on = getSetting($conn, 'blog_views_enabled', '1') === '1';
trigger_fake_winner($conn);
track_view($conn, 'home');

$phone = ($_SESSION['sitak_user'] ?? '');
$user_info = null;
$last_won = null;
$last_won_prize_details = null;

if (isset($_GET['logout_user'])) {
    sitak_remember_revoke($conn);
    unset($_SESSION['sitak_user']);
    $phone = '';
    header('Location: index.php?logged_out=1');
    exit;
}
// logged_out فقط نشان‌دهنده‌ی نتیجه‌ی خروج قبلی است؛
// اگر کاربر در همین URL دوباره وارد شد، کوکی جدید باید پذیرفته شود.

$reset_hour         = getSetting($conn, 'reset_hour', '21');
$reset_hour_enabled = getSetting($conn, 'reset_hour_enabled', '1');
$reset_hour_home_enabled = getSetting($conn, 'reset_hour_home_enabled', '1');
$winners_list_enabled = getSetting($conn, 'winners_list_enabled', '1');
$winners_home_enabled = getSetting($conn, 'winners_home_enabled', '1');
$winners_fake_enabled = getSetting($conn, 'winners_fake_enabled', '1');
$winners_display_count = max(1, min(50, (int)getSetting($conn, 'winners_display_count', '5')));
$loyalty_enabled  = getSetting($conn, 'loyalty_enabled', '1');

// محاسبه شروع سیکل روزانه (برای فال حافظ و تاس)
$now = new DateTime();
$cycle_start = new DateTime();
$cycle_start->setTime((int)$reset_hour, 0, 0);
if ($now < $cycle_start) {
    $cycle_start->modify('-1 day');
}
$cycle_start_str = $cycle_start->format('Y-m-d H:i:s');
$loyalty_dice_count = intval(getSetting($conn, 'loyalty_dice_count', '2'));
$contact_enabled   = getSetting($conn, 'contact_enabled', '1');
$contact_instagram = getSetting($conn, 'contact_instagram', '');
$contact_telegram  = getSetting($conn, 'contact_telegram', '');
$contact_youtube   = getSetting($conn, 'contact_youtube', '');
$contact_phone     = getSetting($conn, 'contact_phone', '');
$loyalty_enabled   = getSetting($conn, 'loyalty_enabled', '1');
$licenses_enabled  = getSetting($conn, 'licenses_enabled', '1');
$license1_img      = getSetting($conn, 'license1_img', '');
$license1_link     = getSetting($conn, 'license1_link', '');
$license1_name     = getSetting($conn, 'license1_name', '');
$license1_embed    = getSetting($conn, 'license1_embed', '');
$license2_img      = getSetting($conn, 'license2_img', '');
$license2_link     = getSetting($conn, 'license2_link', '');
$license2_name     = getSetting($conn, 'license2_name', '');
$license2_embed    = getSetting($conn, 'license2_embed', '');
$contact_address   = getSetting($conn, 'contact_address', '');
$announcement_enabled = getSetting($conn, 'announcement_enabled', '0');
$announcement_message = getSetting($conn, 'announcement_message', '');
$default_theme     = getSetting($conn, 'default_theme', 'auto');
$custom_favicon    = getSetting($conn, 'custom_favicon', '');
$custom_logo       = getSetting($conn, 'custom_logo', '');
$blog_enabled      = getSetting($conn, 'blog_enabled', '1');
$home_blog_enabled = getSetting($conn, 'home_blog_enabled', '1');
$home_blog_type    = getSetting($conn, 'home_blog_type', 'latest');
$shop_enabled      = getSetting($conn, 'shop_enabled', '1');
$orders_tab_enabled = getSetting($conn, 'orders_tab_enabled', '1');
$home_shop_enabled = getSetting($conn, 'home_shop_enabled', '1');
$home_shop_type    = getSetting($conn, 'home_shop_type', 'best');
$chance_enabled    = getSetting($conn, 'chance_enabled', '1');
$dice_enabled      = getSetting($conn, 'dice_enabled', '1');
$dice_home_enabled = getSetting($conn, 'dice_home_enabled', '1');
$wheel_enabled = getSetting($conn, 'wheel_enabled', '1');
$wheel_home_enabled = getSetting($conn, 'wheel_home_enabled', '1');
require_once 'wheel_shared.php';
$wheel_home_prep = sitak_wheel_prepare($conn, $wheel_enabled === '1' && $wheel_home_enabled === '1');
$site_name         = getSetting($conn, 'site_name', 'سای‌تک');
$custom_emoji = getSetting($conn, 'custom_emoji', '🌐');
$site_slogan       = getSetting($conn, 'site_slogan', 'یک وبسایت تک');
$hafez_enabled     = getSetting($conn, 'hafez_enabled', '1');
$home_hafez_enabled = getSetting($conn, 'hafez_home_enabled', '1');
$hero_title        = getSetting($conn, 'hero_title', 'جفت ۶ بیار، مهمون ما باش!');
$footer_copyright = getSetting($conn, 'footer_copyright', '');
$footer_copyright_enabled = getSetting($conn, 'footer_copyright_enabled', '1');
$hero_description  = getSetting($conn, 'hero_description', 'تاس بنداز و شانس دریافت هدایای شگفت‌انگیز رو امتحان کن.');
$hero_enabled      = getSetting($conn, 'hero_enabled', '1');

$has_any_contact = $contact_enabled === '1' && ($contact_instagram || $contact_telegram || $contact_youtube || $contact_phone || $contact_address);

// ── محصولات صفحه اصلی (تازه‌ترین | پرفروش‌ترین) ──
$best = [];
$home_shop_count = max(1, min(50, (int)getSetting($conn, 'home_shop_count', '4')));
$home_blog_count = max(1, min(50, (int)getSetting($conn, 'home_blog_count', '4')));
$shopOn = ($shop_enabled === '1' && $home_shop_enabled === '1');
if ($shopOn && ($home_shop_type ?? 'best') === 'newest') {
    $nbr = $conn->query("SELECT id, name, description, price, image_path, shipping_province, shipping_city, morning_open, morning_close, afternoon_open, afternoon_close, active_days, onsite_product FROM products WHERE is_available=1 ORDER BY id DESC LIMIT " . $home_shop_count);
    while ($nbr && ($r = $nbr->fetch_assoc())) $best[] = $r;
} elseif ($shopOn) {
    $bs_res = $conn->query("SELECT items FROM orders WHERE status IN ('confirmed','shipped','delivered')");
    if ($bs_res) {
        $tmp = [];
        while ($row = $bs_res->fetch_assoc()) {
            $items = json_decode($row['items'], true);
            if (!is_array($items)) continue;
            foreach ($items as $it) {
                $pid = intval($it['id'] ?? 0);
                if (!$pid) continue;
                $tmp[$pid] = ($tmp[$pid] ?? 0) + intval($it['qty'] ?? 1);
            }
        }
        if ($tmp) {
            arsort($tmp);
            $ids = array_slice(array_keys($tmp), 0, $home_shop_count);
            $id_list = implode(',', array_map('intval', $ids));
            $bp_res = $conn->query("SELECT id, name, description, price, image_path, shipping_province, shipping_city, morning_open, morning_close, afternoon_open, afternoon_close, active_days, onsite_product FROM products WHERE id IN ($id_list) AND is_available=1");
            $by_id = [];
            while ($bp_res && ($r = $bp_res->fetch_assoc())) $by_id[$r['id']] = $r;
            foreach ($ids as $pid) {
                if (isset($by_id[$pid])) {
                    $by_id[$pid]['qty'] = $tmp[$pid];
                    $best[] = $by_id[$pid];
                }
            }
        }
    }
    if (count($best) < $home_shop_count) {
        $need = $home_shop_count - count($best);
        $have = array_column($best, 'id') ?: [0];
        $have_list = implode(',', array_map('intval', $have));
        $fb = $conn->query("SELECT id, name, description, price, image_path, shipping_province, shipping_city, morning_open, morning_close, afternoon_open, afternoon_close, active_days, onsite_product FROM products WHERE is_available=1 AND id NOT IN ($have_list) ORDER BY id DESC LIMIT $need");
        while ($fb && ($r = $fb->fetch_assoc())) $best[] = $r;
    }
}

if (!empty($phone)) {
    $user_info = get_user_rolls_info($conn, $phone);
    
    // بررسی مسدود بودن کاربر — در صورت مسدود بودن، کookie حذف و کاربر لاگ‌اوت می‌شود
    if ($user_info && isset($user_info['user']['blocked']) && $user_info['user']['blocked'] == 1) {
        unset($_SESSION['sitak_user']);
        $user_info = null;
        $force_logout_popup = true;
    } else {
        $force_logout_popup = false;
    }
    
    $stmt = $conn->prepare("SELECT * FROM winners WHERE phone = ? AND is_fake = 0 ORDER BY won_at DESC LIMIT 1");
    $stmt->bind_param("s", $phone); $stmt->execute();
    $last_won = $stmt->get_result()->fetch_assoc();
    if ($last_won) {
        $p_stmt = $conn->prepare("SELECT * FROM prizes WHERE is_active = 1 AND description = ? LIMIT 1");
        $p_stmt->bind_param("s", $last_won['prize_name']); $p_stmt->execute();
        $last_won_prize_details = $p_stmt->get_result()->fetch_assoc();
    }
}

$prizes = [];
$prizes_res = $conn->query("SELECT * FROM prizes WHERE is_active = 1 AND is_void = 0");
while ($row = $prizes_res->fetch_assoc()) {
    $row['_lim'] = sitak_prize_limits($row);
    if ($row['_lim']['available']) $prizes[] = $row;
}

// ── تولید کپچا برای فرم ثبت‌نام ──
$captcha_n1 = rand(1, 10);
$captcha_n2 = rand(1, 10);
$_SESSION['captcha_answer'] = $captcha_n1 + $captcha_n2;
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="<?= htmlspecialchars($default_theme, ENT_QUOTES, 'UTF-8') ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="theme-color" content="#ffffff" id="metaTheme">
<?= theme_head_script('sitak_theme', $default_theme) ?>
<?= sitak_head_meta($conn, []) ?>
<title><?= htmlspecialchars($site_name ?? 'سای‌تک', ENT_QUOTES, 'UTF-8') ?></title>
<link href="assets/fonts/vazirmatn.css" rel="stylesheet">
<style>
/* ═══════════════════════════════════════════
   1. THEMES
   ═══════════════════════════════════════════ */
:root,[data-theme="light"]{
  --bg:#f5f5f7;--surface:#ffffff;--card:#ffffff;--card-el:#f0eff2;
  --border:rgba(255,255,255,.08);--border-gold:rgba(236,181,52,.22);
  --text:#1d1d1f;--text2:#6e6e73;--text3:#aeaeb2;
  --header-bg:rgba(255,255,255,.82);--bar-bg:rgba(255,255,255,.92);
  --shadow-sm:0 1px 3px rgba(0,0,0,.06);--shadow-md:0 4px 14px rgba(0,0,0,.06);--shadow-lg:0 10px 30px rgba(0,0,0,.08);
  --sheet-shadow:0 -10px 40px rgba(0,0,0,.08);--overlay-bg:rgba(0,0,0,.3);
  --toast-bg:#ffffff;--modal-overlay:rgba(0,0,0,.3);
  --dice-tray-bg:#f0eff2;--input-bg:#f0eff2;
  --green-text:#1a8a3f;--green-bg:rgba(48,209,88,.07);--red-bg:rgba(255,69,58,.06);--blue-bg:rgba(10,132,255,.06);
}
[data-theme="dark"]{
  --bg:#0a0a0c;--surface:#161618;--card:#1c1c1e;--card-el:#2c2c2e;
  --border:rgba(255,255,255,.08);--border-gold:rgba(236,181,52,.15);
  --text:#f0ece4;--text2:#9e9888;--text3:#5e5a52;
  --header-bg:rgba(10,10,12,.88);--bar-bg:rgba(22,22,24,.92);
  --shadow-sm:0 1px 3px rgba(0,0,0,.3);--shadow-md:0 4px 14px rgba(0,0,0,.3);--shadow-lg:0 10px 30px rgba(0,0,0,.45);
  --sheet-shadow:0 -10px 40px rgba(0,0,0,.35);--overlay-bg:rgba(0,0,0,.5);
  --toast-bg:#2c2c2e;--modal-overlay:rgba(0,0,0,.55);
  --dice-tray-bg:#1c1c1e;--input-bg:#2c2c2e;
  --green-text:#34d399;--green-bg:rgba(52,211,153,.08);--red-bg:rgba(248,113,113,.08);--blue-bg:rgba(96,165,250,.08);
}
:root{
  --gold:#ecb534;--gold-dark:#c49625;--gold-light:#f5d076;
  --gold-glow:rgba(236,181,52,.12);--gold-subtle:rgba(236,181,52,.07);
  --green:#30d158;--red:#ff453a;--blue:#0a84ff;
  --r:20px;--rs:14px;--rss:10px;
  --safe-t:env(safe-area-inset-top,0px);--safe-b:env(safe-area-inset-bottom,0px);
  --font:'Vazirmatn',-apple-system,BlinkMacSystemFont,sans-serif;
}

/* ═══════════════════════════════════════════
   2. RESET
   ═══════════════════════════════════════════ */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent}
html{font-size:16px;-webkit-text-size-adjust:100%;overscroll-behavior:none}
body{font-family:var(--font);background:var(--bg);color:var(--text);min-height:100dvh;overflow-x:hidden;-webkit-font-smoothing:antialiased;padding-top:var(--safe-t);transition:background .35s,color .35s;font-feature-settings:"ss01"}
body::before{content:'';position:fixed;top:-40%;right:-20%;width:500px;height:500px;background:radial-gradient(circle,var(--gold-glow),transparent 70%);pointer-events:none;z-index:0;transition:background .35s}

/* ═══════════════════════════════════════════
   3. APP BAR
   ═══════════════════════════════════════════ */
.app-bar{position:sticky;top:0;z-index:50;display:flex;align-items:center;justify-content:space-between;padding:10px 16px;min-height:60px;background:var(--header-bg);backdrop-filter:blur(28px) saturate(180%);-webkit-backdrop-filter:blur(28px) saturate(180%);border-bottom:1px solid var(--border);transition:background .35s;border-bottom-left-radius:16px;border-bottom-right-radius:16px}
.app-brand{display:flex;align-items:center;gap:10px}
.brand-icon{width:36px;height:36px;background:linear-gradient(135deg,var(--gold),var(--gold-dark));border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0}
.brand-text h1{font-size:1.05rem;font-weight:700;line-height:1.2}
.brand-text p{font-size:.73rem;color:var(--text3);font-weight:300}
.user-area{display:flex;align-items:center;gap:6px}
.header-icon-btn{position:relative;width:36px;height:36px;border-radius:50%;border:1px solid var(--border);background:var(--card-el);display:flex;align-items:center;justify-content:center;font-size:.95rem;color:var(--text);text-decoration:none;cursor:pointer;transition:all .2s;flex-shrink:0}.header-icon-btn:active{transform:scale(.9)}
.roll-count{position:absolute;top:-4px;left:-4px;min-width:18px;height:18px;background:var(--gold);color:#fff;font-size:.65rem;font-weight:700;border-radius:9px;display:flex;align-items:center;justify-content:center;padding:0 4px}
.avatar{width:30px;height:30px;border-radius:50%;background:var(--card-el);display:flex;align-items:center;justify-content:center;font-size:.7rem;color:var(--gold-dark);border:1.5px solid var(--gold);font-weight:700}
.theme-btn{width:32px;height:32px;border-radius:50%;border:1px solid var(--border);background:var(--card-el);display:flex;align-items:center;justify-content:center;font-size:.85rem;cursor:pointer;transition:all .2s}
.theme-btn:active{transform:scale(.9);background:var(--gold-subtle)}
.logout-link{font-size:.7rem;color:var(--red);text-decoration:none;font-weight:600;padding:4px 8px;border-radius:8px;background:var(--red-bg);transition:all .2s}

/* ═══════════════════════════════════════════
   4. CONTACT BAR
   ═══════════════════════════════════════════ */
.contact-bar{position:relative;z-index:0;margin-top:-16px;display:flex;flex-direction:column;gap:10px;padding:28px 16px 12px;background:var(--bar-bg);border-bottom:1px solid var(--border);transition:background .35s;align-items:center;justify-content:center;border-bottom-left-radius:16px;border-bottom-right-radius:16px}
.c-socials{display:flex;flex-wrap:wrap;gap:8px;justify-content:center;align-items:center}
.c-address{display:flex;justify-content:center;width:100%}
.c-chip{border:1px solid var(--border-gold);min-height:28px;display:inline-flex;align-items:center;gap:5px;padding:6px 13px;background:var(--bg);border:1px solid var(--border);border-radius:20px;font-size:.72rem;font-weight:600;color:var(--text2);text-decoration:none;white-space:nowrap;flex-shrink:0;transition:all .2s;vertical-align:middle}
.c-chip:active{background:var(--gold-subtle);border-color:var(--border-gold);color:var(--gold-dark)}
.c-chip.c-addr{cursor:default;white-space:normal;line-height:1.4;max-width:100%}
.gh-logo{fill:#181717}
[data-theme="dark"] .gh-logo{fill:#f5f5f5}

/* ═══════════════════════════════════════════
   5. CONTENT
   ═══════════════════════════════════════════ */
.content{position:relative;z-index:1;padding:12px 14px 100px;max-width:480px;margin:0 auto}

/* ═══════════════════════════════════════════
   6. HERO
   ═══════════════════════════════════════════ */
.hero{text-align:center;padding:28px 12px 20px}
.hero h2{font-size:1.35rem;font-weight:900;color:var(--gold-dark);margin-bottom:8px;line-height:1.6}
.hero p{font-size:.82rem;color:var(--text2);line-height:1.7;max-width:340px;margin:0 auto 12px}
.reset-chip{border:1px solid var(--border-gold);min-height:28px;display:inline-flex;align-items:center;gap:5px;background:var(--card);border:1px solid var(--border);box-shadow:var(--shadow-sm);padding:5px 14px;border-radius:20px;font-size:.72rem;color:var(--text2);vertical-align:middle}

/* ═══════════════════════════════════════════
   7. CARD
   ═══════════════════════════════════════════ */
.card{background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:18px;margin-bottom:14px;position:relative;overflow:hidden;transition:border-color .2s,background .35s;box-shadow:var(--shadow-sm)}
.card-gold{border-color:var(--border-gold);background:linear-gradient(150deg,var(--card),rgba(236,181,52,.03))}

/* ═══════════════════════════════════════════
   8. PRIZE TARGET
   ═══════════════════════════════════════════ */
.prize-card{cursor:pointer;display:flex;align-items:center;gap:12px;transition:transform .15s,box-shadow .2s}
.prize-card:active{transform:scale(.97);box-shadow:var(--shadow-md)}
.prize-icon{width:46px;height:46px;min-width:46px;background:var(--gold-subtle);border:1px solid var(--border-gold);border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.2rem}
.prize-info{flex:1;min-width:0}
.prize-info .lbl{font-size:.8rem;color:var(--text3);margin-bottom:1px}
.prize-info .val{font-size:.8rem;font-weight:700;transition:color .2s}
.blog-item{display:flex;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid var(--border);text-decoration:none;color:inherit}
.blog-item:last-child{border-bottom:none;padding-bottom:2px}
.add-btn{width:32px;height:32px;border-radius:50%;background:var(--gold);color:#fff;border:none;display:flex;align-items:center;justify-content:center;font-size:1.1rem;cursor:pointer;transition:all .15s;box-shadow:0 2px 6px rgba(236,181,52,.2);font-family:var(--font);flex-shrink:0}
.add-btn:active{transform:scale(.9);background:var(--gold-dark)} .add-btn.done{background:var(--green);animation:pop .3s} @keyframes pop{50%{transform:scale(1.2)}}
.blog-thumb{width:52px;height:52px;border-radius:10px;object-fit:cover;flex-shrink:0;background:var(--card-el);display:flex;align-items:center;justify-content:center;font-size:1.2rem}
.blog-t{font-size:.82rem;font-weight:600;line-height:1.5;margin-bottom:2px}
.blog-d{font-size:.68rem;color:var(--text3)}
.blog-more{font-size:.72rem;color:var(--gold-dark);text-decoration:none;font-weight:600}
.prize-info .val.empty{color:var(--text3);font-weight:400}
.step-tag{position:absolute;top:10px;left:10px;background:var(--gold);color:#fff;font-size:.6rem;font-weight:700;padding:3px 9px;border-radius:20px}
.chev{color:var(--text3);font-size:1.3rem;font-weight:300}

@keyframes shake {
  0%, 100% { transform: translateX(0); }
  20%, 60% { transform: translateX(-6px); }
  40%, 80% { transform: translateX(6px); }
}

/* ═══════════════════════════════════════════
   9. DICE — 2.8s animation
   ═══════════════════════════════════════════ */
.card.dice-section{position:relative;background:var(--surface);border:1px solid var(--border-gold);border-radius:var(--r);padding:22px 16px 18px;margin-bottom:14px;text-align:center;box-shadow:var(--shadow-sm);transition:background .35s}
.dice-section .prize-card{margin-bottom:16px;text-align:right}
.dice-tray{background:var(--dice-tray-bg);border:2px solid var(--border);border-radius:18px;padding:22px;display:flex;justify-content:center;align-items:center;gap:22px;margin-bottom:18px;position:relative;transition:border-color .4s,background .35s}
.dice-tray::after{content:'';position:absolute;inset:0;border-radius:16px;background:radial-gradient(ellipse at center,var(--gold-glow),transparent);opacity:0;transition:opacity .5s;pointer-events:none}
.dice-tray.winning{border-color:var(--gold)}.dice-tray.winning::after{opacity:1}
.die{width:78px;height:78px;transition:filter .3s}
.die svg{width:100%;height:100%;display:block;transition:filter .3s}
.die.animating{animation:diceThrow 2.8s ease-out forwards}
.die:nth-child(2).animating{animation-delay:.09s}
.die.winner svg{filter:drop-shadow(0 0 14px rgba(236,181,52,.3)) drop-shadow(0 4px 6px rgba(0,0,0,.1))}

@keyframes diceThrow{
  0%{transform:translateY(0) rotate(0) scale(1)}
  7%{transform:translateY(-52px) rotate(-100deg) scale(1.14)}
  16%{transform:translateY(7px) rotate(-220deg) scale(.93)}
  24%{transform:translateY(-36px) rotate(-340deg) scale(1.08)}
  33%{transform:translateY(5px) rotate(-440deg) scale(.96)}
  41%{transform:translateY(-22px) rotate(-520deg) scale(1.04)}
  50%{transform:translateY(3px) rotate(-590deg) scale(.98)}
  58%{transform:translateY(-13px) rotate(-640deg) scale(1.01)}
  66%{transform:translateY(2px) rotate(-680deg) scale(.99)}
  74%{transform:translateY(-7px) rotate(-710deg) scale(1)}
  82%{transform:translateY(1px) rotate(-725deg)}
  90%{transform:translateY(-3px) rotate(-735deg)}
  100%{transform:translateY(0) rotate(-720deg)}
}

/* ═══════════════════════════════════════════
   10. ROLL BUTTON
   ═══════════════════════════════════════════ */
.roll-btn{width:100%;padding:15px 22px;background:linear-gradient(135deg,var(--gold),var(--gold-dark));color:#fff;border:none;border-radius:var(--rs);font-family:var(--font);font-size:1rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:all .12s;box-shadow:0 6px 18px rgba(236,181,52,.15);position:relative;overflow:hidden}
.roll-btn:active{box-shadow:0 2px 6px rgba(236,181,52,.1)}
.roll-btn:disabled{opacity:.55;cursor:not-allowed;transform:none}
.roll-btn.shake{animation:shake 0.6s ease-in-out}

/* ═══════════════════════════════════════════
   11. LOYALTY
   ═══════════════════════════════════════════ */
.loy-header{display:flex;align-items:center;gap:8px;margin-bottom:4px}
.loy-header h3{font-size:.95rem;font-weight:700;color:var(--gold-dark)}
.loy-sub{font-size:.75rem;color:var(--text2);margin-bottom:14px;line-height:1.7}
.loy-tray{display:flex;justify-content:center;gap:10px;margin-bottom:10px;flex-wrap:wrap}
.loyalty-die{display:inline-flex;flex-direction:column;align-items:center;gap:4px;transition:transform .3s cubic-bezier(.34,1.56,.64,1)}
.loyalty-die:hover{transform:scale(1.08)}
.loyalty-die svg{width:44px;height:44px}
.die-label{font-size:.68rem;font-weight:700;color:var(--text3)}
.loyalty-die.earned .die-label{color:var(--gold-dark)}
.loyalty-die.in-progress .die-label{color:var(--gold)}
.loy-complete{background:var(--green-bg);border:1px solid var(--green-text);border-radius:var(--rss);padding:12px;font-size:.82rem;font-weight:600;color:var(--green-text);text-align:center;line-height:1.7;margin-top:10px}
.loy-hint{font-size:.72rem;color:var(--text3);text-align:center;line-height:1.6}

/* ═══════════════════════════════════════════
   12. LAST WIN
   ═══════════════════════════════════════════ */
.lw-card{border-color:rgba(48,209,88,.15);background:linear-gradient(150deg,var(--card),rgba(48,209,88,.02))}
.win-badge{border:1px solid rgba(48,209,88,.22);min-height:28px;display:inline-flex;align-items:center;gap:5px;background:var(--green-bg);color:var(--green-text);font-size:.72rem;font-weight:700;padding:4px 12px;border-radius:20px;margin-bottom:12px;vertical-align:middle}

/* Hafez book animation */
.book-stage{position:relative;width:220px;height:150px;margin:0 auto;perspective:800px;display:flex;align-items:center;justify-content:center}
.book3d{position:relative;width:180px;height:228px;transform-style:preserve-3d;animation:b3Idle 3.2s ease-in-out infinite}
@keyframes b3Idle{0%,100%{transform:translateY(0) scale(.5)}50%{transform:translateY(-10px) scale(.5)}}
.b3-page1{position:absolute;top:0;right:0;bottom:0;left:0;transform:translateZ(18px);border-radius:0 20px 20px 0;background:#5e3a18;box-shadow:inset 0 0 20px rgba(0,0,0,.06);backface-visibility:hidden;overflow:hidden}
.book3d.open-zoom .b3-page1,.book3d.open-done .b3-page1{left:-1px}
.pg-content{position:absolute;inset:0;padding:12px 0 0;overflow:hidden;text-align:center;display:flex;flex-direction:column;border-radius:0 20px 20px 0;background:#fdf6e3}
.bc-in .pg-content{border-radius:20px 0 0 20px;left:0;right:2px}
/* صفحه‌ی داخلی تقریباً کاملاً سفید است؛ فقط یک لبه‌ی باریک تیره کنار جلد می‌ماند */
.b3-page1 .pg-content{top:0;right:0;bottom:0;left:2px;border-radius:0 20px 20px 0}
.pg-title{font-size:.9rem;font-weight:700;color:#7a4a20;margin-bottom:6px;padding:0 10px}
.pg-rules{flex:1;min-height:0;overflow:hidden;background-image:repeating-linear-gradient(to bottom,transparent 0 17px,rgba(93,64,28,.18) 17px 19px,transparent 19px 24px)}
.pg-text{font-size:.9rem;line-height:24px;padding:0 10px;color:#3d2b1f;font-style:italic}
.pg-sep{line-height:24px;font-size:.75rem;letter-spacing:4px;color:rgba(122,74,32,.55)}
.pg-num{font-size:.85rem;line-height:24px;color:#8a6b3a}
/* صفحه‌ی کاغذی همان لحظه‌ی باز شدن سفید دیده می‌شود؛ فقط متن کم‌کم ظاهر می‌شود */
.pg-fade{opacity:1}
.pg-fade>*{opacity:0;transition:opacity 1s ease}
.pg-fade>.pg-rules{opacity:1}
.pg-fade .pg-rules .pg-text{opacity:0;transition:opacity 1s ease}
.pg-fade.show>*{opacity:1}
.pg-fade.show .pg-rules .pg-text{opacity:1}
.b3-page1::after{content:'';position:absolute;inset:0;border-radius:inherit;background:linear-gradient(to right,rgba(61,43,31,.42),rgba(61,43,31,.14) 55%,transparent);opacity:0;pointer-events:none}
.book3d.open-zoom .b3-page1::after{animation:coverShadow 1.35s linear .8s forwards}
.bc-in::after{content:'';position:absolute;inset:0;border-radius:inherit;background:linear-gradient(to left,rgba(61,43,31,.42),rgba(61,43,31,.14) 55%,transparent);opacity:0;pointer-events:none}
.book3d.open-zoom .bc-in::after{animation:coverShadow 1.35s linear .8s forwards}
@keyframes coverShadow{0%{opacity:0}14%{opacity:.6}42%{opacity:.9}75%{opacity:.3}100%{opacity:0}}
.b3-cover{position:absolute;inset:0;transform-origin:left center;transform-style:preserve-3d;transform:translateZ(20px);transition:transform 1.6s cubic-bezier(.45,.05,.25,1),box-shadow .9s ease;border-radius:0 20px 20px 0;background:linear-gradient(135deg,#8b5a2b,#5e3a18);box-shadow:0 12px 32px rgba(0,0,0,.25)}
.book3d.open-zoom .b3-cover{animation:coverOpen 1.35s cubic-bezier(.6,.05,.3,1) .8s forwards;box-shadow:none}
@keyframes coverOpen{0%{transform:translateZ(20px) rotateY(0)}16%{transform:translateZ(20px) rotateY(-14deg)}60%{transform:translateZ(20px) rotateY(-146deg)}84%{transform:translateZ(20px) rotateY(-187deg)}100%{transform:translateZ(18px) rotateY(-180deg)}}
.bc-out,.bc-in{position:absolute;inset:0;backface-visibility:hidden;border-radius:inherit;display:flex;align-items:center;justify-content:center;font-size:4.4rem}
.bc-out{background:linear-gradient(135deg,#8b5a2b,#5e3a18);box-shadow:inset 0 0 0 4px rgba(0,0,0,.22)}
.bc-out::before{content:'';position:absolute;left:10px;top:14px;bottom:14px;width:4px;background:rgba(0,0,0,.25);border-radius:4px}
.bc-cover-title{display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;gap:4px;font-size:2rem;font-weight:800;line-height:1.25;color:#f3e3bd;text-shadow:0 2px 6px rgba(0,0,0,.45)}
.bc-in{transform:rotateY(180deg);border-radius:20px 0 0 20px;background:#fdf6e3;box-shadow:inset 0 0 20px rgba(0,0,0,.06);backface-visibility:hidden;overflow:hidden;outline:1px solid transparent}
.b3-page1::before{content:'';position:absolute;top:0;bottom:0;left:0;width:14px;background:linear-gradient(to right,#5e3a18 0,#5e3a18 1px,rgba(61,43,31,.45) 1px,rgba(61,43,31,.45) 3px,rgba(61,43,31,.2) 5px,rgba(61,43,31,.07) 9px,transparent 14px);pointer-events:none;z-index:1}
.bc-in::before{content:'';position:absolute;top:0;bottom:0;right:0;width:14px;background:linear-gradient(to left,#5e3a18 0,#5e3a18 1px,rgba(61,43,31,.45) 1px,rgba(61,43,31,.45) 3px,rgba(61,43,31,.2) 5px,rgba(61,43,31,.07) 9px,transparent 14px);pointer-events:none;z-index:1}
.book3d.open-zoom{animation:openZoom 2.2s cubic-bezier(.4,0,.2,1) forwards}
@keyframes openZoom{0%{transform:translateY(0) scale(.5)}20%{transform:translateY(-14px) scale(.58)}36%{transform:translateY(-18px) scale(.63)}68%{transform:translate(40px,-4px) scale(.76)}100%{transform:translate(72px,0) scale(.8)}}
.book3d.open-done{animation:none;transform:translate(72px,0) scale(.8)}
.book3d.open-done .b3-cover{animation:none;transform:translateZ(18px) rotateY(-180deg);box-shadow:none}
.dice-tray.spread-mode{min-height:215px;transition:min-height .8s ease,border-color .4s,background .35s}
.glow-burst{position:absolute;inset:0;background:radial-gradient(circle,var(--gold-glow),transparent 65%);opacity:0;pointer-events:none;transition:opacity .6s}
.glow-burst.flash{opacity:1;animation:glowFade 1.2s ease-out forwards}
@keyframes glowFade{0%{opacity:0;transform:scale(.6)}40%{opacity:1}100%{opacity:0;transform:scale(1.6)}}
.win-prize{font-size:1.05rem;font-weight:700;padding:13px;background:var(--bg);border:1.5px solid var(--border);border-radius:var(--rss);text-align:center;margin-bottom:10px;transition:background .35s}
.win-meta{font-size:.75rem;color:var(--text2);margin-bottom:8px;display:flex;align-items:center;gap:5px}
.win-status{border:1px solid currentColor;min-height:28px;display:inline-flex;align-items:center;gap:4px;font-size:.78rem;font-weight:600;padding:6px 12px;border-radius:8px;vertical-align:middle}
.win-status{display:flex;width:100%;box-sizing:border-box;justify-content:center;align-items:center;text-align:center;padding:12px;font-size:.82rem;line-height:1.7;border-radius:var(--rss)}
.ws-ok{background:var(--green-bg);color:var(--green-text)}
.ws-pend{background:var(--gold-subtle);color:var(--gold-dark)}
.ws-exp{background:var(--red-bg);color:var(--red)}
.ws-on{background:var(--blue-bg);color:var(--blue)}
.code-box{margin-top:12px}
.code-lbl{font-size:.72rem;font-weight:600;color:var(--text2);margin-bottom:5px;display:block}
.code-row{display:flex;align-items:center;justify-content:space-between;background:var(--bg);border:1.5px dashed var(--border-gold);border-radius:var(--rss);padding:11px 14px;cursor:pointer;transition:all .2s}
.code-row:active{background:var(--gold-subtle);border-color:var(--gold)}
.code-val{font-size:1.05rem;font-weight:700;letter-spacing:2px;color:var(--gold-dark);direction:ltr}
.code-hint{font-size:.65rem;color:var(--text3)}
.code-row.copied{border-color:var(--green);background:var(--green-bg)}.code-row.copied .code-val{color:var(--green-text)}.code-row.copied .code-hint{color:var(--green-text)}
.aff-btn{display:flex;align-items:center;justify-content:center;gap:7px;width:100%;padding:12px;background:var(--bg);border:1.5px solid var(--border);border-radius:var(--rss);color:var(--text);font-family:var(--font);font-size:.82rem;font-weight:600;text-decoration:none;margin-top:10px;transition:all .2s}
.aff-btn:active{background:var(--gold-subtle);border-color:var(--gold)}
.aff-btn-gold{width:100%;padding:15px 22px;background:linear-gradient(135deg,var(--gold),var(--gold-dark));color:#fff;border:none;border-radius:var(--rs);font-family:var(--font);font-size:1rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:all .12s;box-shadow:0 6px 18px rgba(236,181,52,.15);text-decoration:none;margin-top:10px}
.aff-btn-gold:active{box-shadow:0 2px 6px rgba(236,181,52,.1)}

/* ═══════════════════════════════════════════
   13. WINNERS LIST
   ═══════════════════════════════════════════ */
.win-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
.win-head h3{font-size:.95rem;font-weight:700}
.live{font-size:.80rem;color:var(--text);display:flex;align-items:center;gap:4px}
.live-dot{width:6px;height:6px;background:var(--green);border-radius:50%;animation:pulse 2s infinite}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.3}}
.w-item{display:flex;align-items:center;gap:10px;padding:11px 0;border-bottom:1px solid rgba(128,128,128,.28)}.w-item:last-child{border:none}
.w-av{width:34px;height:34px;min-width:34px;border-radius:50%;background:var(--card-el);display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:700;color:var(--text2);border:1px solid var(--border)}
.w-info{flex:1;min-width:0}.w-name{font-size:.8rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.w-time{font-size:.68rem;color:var(--text3)}
.w-prize{font-size:.75rem;font-weight:600;color:var(--gold-dark);text-align:left;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:110px}

/* ═══════════════════════════════════════════
   14. BOTTOM SHEET
   ═══════════════════════════════════════════ */
.overlay{position:fixed;inset:0;background:var(--overlay-bg);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);z-index:100;opacity:0;pointer-events:none;transition:opacity .3s}
.overlay.open{opacity:1;pointer-events:auto}
.sheet{position:fixed;bottom:0;left:0;right:0;z-index:101;background:var(--surface);border-radius:22px 22px 0 0;padding:0 18px 18px;padding-bottom:calc(18px + var(--safe-b));max-height:82vh;overflow-y:auto;transform:translateY(100%);transition:transform .38s cubic-bezier(.32,.72,0,1),background .35s;-webkit-overflow-scrolling:touch;box-shadow:var(--sheet-shadow)}
.sheet.open{transform:translateY(0)}
.handle{width:34px;height:4px;background:#d1d1d6;border-radius:4px;margin:10px auto 18px}
[data-theme="dark"] .handle{background:#4a4a4e}
.sheet-title{font-size:1.05rem;font-weight:700;text-align:center;margin-bottom:16px}
.pr-opt{display:flex;align-items:center;gap:12px;padding:13px 14px;background:var(--bg);border:1.5px solid var(--border);border-radius:var(--rss);margin-bottom:9px;cursor:pointer;transition:all .2s}
.pr-opt:active,.pr-opt.sel{border-color:var(--gold);background:var(--gold-subtle)}
.pr-radio{width:20px;height:20px;min-width:20px;border-radius:50%;border:2px solid #d1d1d6;display:flex;align-items:center;justify-content:center;transition:all .2s}
[data-theme="dark"] .pr-radio{border-color:#5e5a52}
.pr-opt.sel .pr-radio{border-color:var(--gold)}.pr-opt.sel .pr-radio::after{content:'';width:10px;height:10px;border-radius:50%;background:var(--gold)}
.pr-text{font-size:.85rem;font-weight:500;flex:1}

/* ═══════════════════════════════════════════
   15. FORMS & BUTTONS
   ═══════════════════════════════════════════ */
.fg{margin-bottom:13px}
.fg label{display:block;font-size:.75rem;font-weight:600;color:var(--text2);margin-bottom:5px}
.fi{width:100%;padding:13px 14px;background:var(--input-bg);border:1.5px solid var(--border);border-radius:var(--rss);color:var(--text);font-family:var(--font);font-size:.92rem;outline:none;transition:border-color .2s,background .35s}
.fi::placeholder{color:var(--text3)}.fi:focus{border-color:var(--gold)}
.fh{font-size:.65rem;color:var(--text3);margin-top:3px}
.btn-p{width:100%;padding:14px;background:linear-gradient(135deg,var(--gold),var(--gold-dark));color:#fff;border:none;border-radius:var(--rss);font-family:var(--font);font-size:.92rem;font-weight:700;cursor:pointer;transition:all .12s;}
.btn-p:active{}
.btn-g{width:100%;padding:11px;background:0;border:0;color:var(--text2);font-family:var(--font);font-size:.8rem;cursor:pointer;margin-top:6px}

/* ═══════════════════════════════════════════
   16. RESULT OVERLAY
   ═══════════════════════════════════════════ */
.result-ov{position:fixed;inset:0;z-index:200;display:none;align-items:center;justify-content:center;padding:18px;background:var(--modal-overlay);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px)}
.result-ov.open{display:flex}
.result-card{background:var(--surface);border:1.5px solid var(--border);border-radius:26px;padding:30px 22px;max-width:370px;width:100%;text-align:center;position:relative;overflow:hidden;animation:slideUp .4s cubic-bezier(.34,1.56,.64,1);box-shadow:var(--shadow-lg);transition:background .35s}
@keyframes slideUp{from{transform:translateY(30px);opacity:0}to{transform:translateY(0);opacity:1}}
.result-card.win{border-color:var(--gold)}
.result-card.win::before{content:'';position:absolute;top:-50%;left:-50%;width:200%;height:200%;background:radial-gradient(circle,var(--gold-glow),transparent 50%);pointer-events:none;animation:glow 3s ease-in-out infinite alternate}
@keyframes glow{0%{transform:translate(0,0)}100%{transform:translate(4%,4%)}}
.r-icon{font-size:2.8rem;margin-bottom:10px;animation:float 2s ease-in-out infinite}
@keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-7px)}}
.r-badge{border:1px solid rgba(236,181,52,.55);min-height:28px;display:inline-flex;align-items:center;justify-content:center;background:var(--gold);color:#fff;font-size:.75rem;font-weight:700;padding:3px 11px;border-radius:16px;margin-bottom:12px;vertical-align:middle}
.result-card h3{font-size:1.2rem;font-weight:900;margin-bottom:7px}
.result-card.win h3{color:var(--gold-dark)}
.r-desc{font-size:.82rem;color:var(--text2);line-height:1.7;margin-bottom:18px}
.r-prize{font-size:1rem;font-weight:700;color:var(--gold-dark);padding:13px;background:var(--gold-subtle);border:1.5px solid var(--border-gold);border-radius:var(--rss);margin-bottom:18px}

/* ═══════════════════════════════════════════
   17. CONFETTI
   ═══════════════════════════════════════════ */
.confetti-box{position:fixed;inset:0;pointer-events:none;z-index:250;overflow:hidden}
@media(min-width:481px){
  .confetti-box{left:50%;right:auto;transform:translateX(-50%);width:480px}
}
.confetti{position:absolute;top:-20px;border-radius:2px;animation:cFall linear forwards}
@keyframes cFall{0%{transform:translateY(0) rotate(0) scale(1);opacity:1}80%{opacity:1}100%{transform:translateY(100vh) rotate(720deg) scale(.4);opacity:0}}

/* ═══════════════════════════════════════════
   18. SURPRISE & TOAST & FOOTER
   ═══════════════════════════════════════════ */
.surprise-ov{position:fixed;inset:0;z-index:300;display:none;flex-direction:column;align-items:center;justify-content:center;padding:22px;background:var(--overlay-bg);backdrop-filter:blur(30px)}
.surprise-ov.open{display:flex}
.toast{pointer-events:none;position:fixed;bottom:28px;left:50%;transform:translateX(-50%) translateY(100px);background:linear-gradient(135deg,var(--gold,#ecb534),var(--gold-dark,#c49625));border:none;color:#fff;padding:13px 22px;border-radius:16px;font-size:.82rem;font-weight:500;z-index:500;opacity:0;transition:all .35s cubic-bezier(.34,1.56,.64,1);max-width:min(88%,420px);text-align:center;box-shadow:var(--shadow-lg);white-space:pre-line}.toast.show{transform:translateX(-50%) translateY(0);opacity:1}
.app-footer{text-align:center;padding:28px 16px 20px;border-top:1px solid var(--border);margin-top:16px;background:var(--bg);transition:background .35s}
.app-footer p{font-size:.72rem;color:var(--text3);margin-bottom:10px}
.f-links{display:flex;justify-content:center;gap:18px;margin:10px 0}
.f-links a{font-size:.75rem;color:var(--text2);text-decoration:none;transition:color .2s}.f-links a:hover{color:var(--gold-dark)}
.hidden{display:none!important}
.bottom-nav{position:fixed;bottom:0;left:0;right:0;z-index:100;background:var(--surface);border-top:1px solid var(--border);display:flex;justify-content:space-around;padding:8px 0;padding-bottom:calc(8px + var(--safe-b));border-top-left-radius:16px;border-top-right-radius:16px}.bottom-nav a{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;color:var(--text2);text-decoration:none;font-size:.6rem;min-width:44px;min-height:44px;padding:4px 0;transition:color .2s;white-space:nowrap}.bottom-nav a.active{color:var(--gold-dark)}.bottom-nav svg{width:22px;height:22px;transition:transform .2s}.bottom-nav a.active svg{color:var(--gold-dark);transform:scale(1.1)}@media(max-width:400px){.bottom-nav a{font-size:.55rem;min-width:38px;padding:2px 0}}

/* ═══════════════════════════════════════════
   19. ANNOUNCEMENT POPUP
   ═══════════════════════════════════════════ */
.ann-body{font-size:.9rem;line-height:1.9;color:var(--text);margin-bottom:20px;white-space:pre-line;text-align:right;direction:rtl;max-height:50vh;overflow-y:auto}

/* ═══════════════════════════════════════════
   20. CAPTCHA
   ═══════════════════════════════════════════ */
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
/* ── کارت‌های محصول و مطلب در صفحه‌ی اصلی (هم‌شکل لیست منو و مطالب) ── */
.prod-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.prod-card{cursor:pointer;background:var(--card);border:1px solid rgba(139,90,43,.22);border-radius:var(--r);overflow:hidden;transition:transform .2s;box-shadow:var(--shadow-sm);display:flex;flex-direction:column}
.prod-card:active{transform:scale(.98)}
.prod-img{width:100%;height:120px;object-fit:cover;background:var(--card-el);display:block}
.prod-info{padding:10px 12px 6px}
.prod-card a .prod-info{padding-bottom:2px}
.prod-card > .prod-info{flex:1;display:flex;flex-direction:column;padding-top:2px}
.prod-name{font-size:.88rem;font-weight:700;margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;line-height:1.3}
.prod-shipping-badge{display:inline-flex;align-items:center;justify-content:center;padding:2px 8px;border-radius:10px;min-height:24px;line-height:17px;font-size:.62rem;font-weight:700;background:#e8f5e9;border:0;color:#2e7d32;margin-bottom:6px;vertical-align:middle}.prod-desc{font-size:.7rem;color:var(--text3);line-height:1.4;margin:6px 0 6px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.prod-foot{display:flex;align-items:center;justify-content:space-between;margin-top:auto;gap:6px}.prod-foot-actions{display:flex;align-items:center;gap:6px;min-width:0}.prod-foot-actions .prod-shipping-badge{margin:0;max-width:120px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.prod-price{font-size:.88rem;font-weight:800;color:var(--gold-dark)}
.post-list{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.post-card{background:var(--card);border:1px solid var(--border-gold);border-radius:var(--r);overflow:hidden;transition:transform .2s;box-shadow:var(--shadow-sm);text-decoration:none;color:inherit;display:flex;flex-direction:column}
.post-card:active{transform:scale(.98)}
.post-cover{width:100%;height:120px;object-fit:cover;background:var(--card-el);display:block}.post-cover.miss{display:flex;align-items:center;justify-content:center;font-size:2rem;color:var(--gold-dark);line-height:1}
.post-body{padding:10px 12px 6px;flex:1;display:flex;flex-direction:column}
.post-title{font-size:.88rem;font-weight:700;margin-bottom:6px;line-height:1.5}
.post-excerpt{font-size:.7rem;color:var(--text3);line-height:1.4;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;margin:6px 0}
.post-meta{display:flex;align-items:center;gap:10px;font-size:.7rem;color:var(--text3);margin-top:auto;padding-top:6px}
@media(max-width:400px){.prod-grid,.post-list{grid-template-columns:1fr}}
section.card .prod-img,section.card .post-cover{height:96px}
section.card .prod-name,section.card .post-title{font-size:.8rem}
section.card .prod-price{font-size:.8rem}
section.card .prod-desc,section.card .post-excerpt{font-size:.65rem}
section.card .post-meta{font-size:.6rem;gap:4px;padding-top:4px;white-space:nowrap}
section.card .post-meta > span{min-width:0;overflow:hidden;text-overflow:ellipsis}
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

<!-- ═══ Contact Bar ═══ -->
<?php if ($has_any_contact): ?>
<nav class="contact-bar">
  <div class="c-socials">
    <?php if ($contact_instagram): ?><a href="<?= htmlspecialchars($contact_instagram, ENT_QUOTES, 'UTF-8') ?>" target="_blank" class="c-chip">📸 اینستاگرام</a><?php endif; ?>
    <?php if ($contact_telegram): ?><a href="<?= htmlspecialchars($contact_telegram, ENT_QUOTES, 'UTF-8') ?>" target="_blank" class="c-chip">📢 تلگرام</a><?php endif; ?>
    <?php if ($contact_youtube): ?><a href="<?= htmlspecialchars($contact_youtube, ENT_QUOTES, 'UTF-8') ?>" target="_blank" class="c-chip">🎥 یوتیوب</a><?php endif; ?>
    <?php if ($contact_phone): ?><a href="tel:<?= htmlspecialchars($contact_phone, ENT_QUOTES, 'UTF-8') ?>" class="c-chip">📞 <?= htmlspecialchars($contact_phone, ENT_QUOTES, 'UTF-8') ?></a><?php endif; ?>
  </div>
  <?php if ($contact_address): ?>
  <div class="c-address">
    <span class="c-chip c-addr">📍 <?= htmlspecialchars($contact_address, ENT_QUOTES, 'UTF-8') ?></span>
  </div>
  <?php endif; ?>
</nav>
<?php endif; ?>

<!-- ═══ Content ═══ -->
<main class="content">
  <?php if ($hero_enabled === '1' || ($reset_hour_home_enabled ?? '1') === '1'): ?>
  <section class="hero">
    <?php if ($hero_enabled === '1' && $hero_title !== ''): ?><h2><?= htmlspecialchars($hero_title, ENT_QUOTES, 'UTF-8') ?></h2><?php endif; ?>
    <?php if ($hero_enabled === '1' && $hero_description !== ''): ?><p><?= htmlspecialchars($hero_description, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <?php if (($reset_hour_home_enabled ?? '1') === '1'): ?>
    <div class="reset-chip">🕐 بازنشانی شانس در ساعت <strong><?= pn($reset_hour) ?>:۰۰</strong></div>
    <?php endif; ?>
  </section>
  <?php endif; ?>

<?php if (!empty($__cb)) { $__cards[$__cb] = ob_get_clean(); } $__cards = $__cards ?? []; $__cb = 'dice'; ob_start(); ?>
  <?php if ($dice_enabled === '1' && $dice_home_enabled === '1'): ?>
  <section class="card dice-section" style="text-align:center;padding-top:60px">
    <div class="win-badge" style="position:absolute;top:18px;right:18px;margin:0">🎲 تاس روزانه</div>
    <section class="card card-gold prize-card" id="prizeCard" onclick="openSheet('prize')">
      <div class="prize-icon">🎁</div>
      <div class="prize-info">
        <span class="lbl">جایزه مورد نظرت رو انتخاب کن</span>
        <span class="val empty" id="selPrizeText"></span>
      </div>
      <span class="chev">‹</span>
    </section>
    <div class="dice-tray" id="diceTray">
      <div class="die" id="die1"></div>
      <div class="die" id="die2"></div>
    </div>
    <button class="roll-btn" id="rollBtn" onclick="handleRoll()"<?php if($user_info&&intval($user_info['total_rolls'])<=0):?> style="width:100%;opacity:.5;cursor:default"<?php endif;?>><span id="rollText"><?= ($user_info&&intval($user_info['total_rolls'])<=0)?'تاس‌ها انداخته شد ✅':'تاس بنداز 🎲' ?></span></button>
  </section>
  <?php endif; ?>

<?php if (!empty($__cb)) { $__cards[$__cb] = ob_get_clean(); } $__cards = $__cards ?? []; $__cb = 'wheel'; ob_start(); ?>
  <?php if ($wheel_home_prep['segments']): ?>
  <?php sitak_wheel_section($wheel_home_prep['segments'], $wheel_home_prep['bg'], $user_info && intval($user_info['total_rolls']) <= 0); ?>
  <?php endif; ?>

<?php if (!empty($__cb)) { $__cards[$__cb] = ob_get_clean(); } $__cards = $__cards ?? []; $__cb = 'lastwin'; ob_start(); ?>
  <?php if ($user_info && $last_won):
    $is_local = isset($last_won_prize_details['is_local']) ? intval($last_won_prize_details['is_local']) : 1;
    $cs = get_cycle_start_time($reset_hour);
    $wt = new DateTime($last_won['won_at']);
    $exp = ($wt < $cs);
  ?>
  <section class="card lw-card" style="border-color:var(--border-gold)">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
      <div class="win-badge" style="margin-bottom:0"><h4>🎉 آخرین برد شما</h4></div>
      <span class="win-meta" style="margin:0">📅 <?= pn(time_ago($last_won['won_at'])) ?></span>
    </div>
    <div class="win-prize"><?= htmlspecialchars($last_won['prize_name'], ENT_QUOTES, 'UTF-8') ?></div>
    <?php $lw_type = sitak_prize_delivery_type($last_won_prize_details ?: []); $lw_all = sitak_result_msgs($conn); $lw_text = $lw_all['result_win_'.$lw_type.'_msg']; ?>
    <?php if ($lw_type !== 'external'): ?>
      <?php if ($last_won['claimed']): ?><div class="win-status ws-ok"><span style="display:block;width:100%;text-align:center;line-height:1.7"><?= nl2br(htmlspecialchars($lw_all['result_last_delivered_msg'], ENT_QUOTES, 'UTF-8')) ?></span></div>
      <?php elseif ($exp): ?><div class="win-status ws-exp"><span style="display:block;width:100%;text-align:center;line-height:1.7"><?= nl2br(htmlspecialchars($lw_all['result_last_expired_msg'], ENT_QUOTES, 'UTF-8')) ?></span></div>
      <?php else: ?><div class="win-status ws-pend"><span style="display:block;width:100%;text-align:center;line-height:1.7"><?= nl2br(htmlspecialchars($lw_text, ENT_QUOTES, 'UTF-8')) ?></span></div><?php endif; ?>
    <?php elseif ($lw_text !== ''): ?><div class="win-status ws-pend"><span style="display:block;width:100%;text-align:center;line-height:1.7"><?= nl2br(htmlspecialchars($lw_text, ENT_QUOTES, 'UTF-8')) ?></span></div><?php endif; ?>
    <?php if ($last_won_prize_details): ?>
      <?php if (!empty($last_won_prize_details['discount_code'])): ?>
        <div class="code-box"><span class="code-lbl">کد تخفیف (برای کپی کلیک کن):</span><div class="code-row" onclick="copyCode(this)"><span class="code-val"><?= htmlspecialchars($last_won_prize_details['discount_code'], ENT_QUOTES, 'UTF-8') ?></span><span class="code-hint">کپی</span></div></div>
      <?php endif; ?>
      <?php if (!empty($last_won_prize_details['affiliate_link'])): ?>
        <a href="<?= htmlspecialchars($last_won_prize_details['affiliate_link'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" class="aff-btn-gold">مشاهده محصول 🔗</a>
      <?php endif; ?>
    <?php endif; ?>
  </section>
  <?php endif; ?>

<?php if (!empty($__cb)) { $__cards[$__cb] = ob_get_clean(); } $__cards = $__cards ?? []; $__cb = 'loyalty'; ob_start(); ?>
  <?php if ($loyalty_enabled === '1' && $user_info):
    $vis = intval($user_info['user']['visits'] ?? 0);
    $target = $loyalty_dice_count * 6;
    $done = ($vis >= $target);
    $loy_complete_txt = getSetting($conn, 'loyalty_complete_msg', "🎉 تاس تکمیل شد! این صفحه رو به ما نشون بده تا هدیه‌ت رو بگیری!");
    if ($done && $user_info && $last_won) {
      $lw_loy_type = sitak_prize_delivery_type($last_won_prize_details ?: []);
      $lw_loy_all = sitak_result_msgs($conn);
      if ($lw_loy_type !== 'external') {
        if ($last_won['claimed']) $loy_complete_txt = $lw_loy_all['result_last_delivered_msg'];
        elseif (!empty($exp)) $loy_complete_txt = $lw_loy_all['result_last_expired_msg'];
      }
    }
  ?>
  <section class="card card-gold">
    <div class="win-badge"><h4>⭐ کارت وفاداری شما</h4></div>
    <p class="loy-sub">مجموع مراجعات حضوری: <strong><?= pn($vis) ?></strong> بار</p>
    <div class="loy-tray"><?= get_loyalty_dice_html($vis, $loyalty_dice_count) ?></div>
    <?php if ($done): ?>
      <div class="loy-complete"><?= nl2br(htmlspecialchars($loy_complete_txt, ENT_QUOTES, 'UTF-8')) ?></div>
    <?php else: ?>
      <p class="loy-hint"><?= nl2br(htmlspecialchars(getSetting($conn, 'loyalty_card_hint', "تاس رو به شش تبدیل کن و از ما جایزه بگیر"), ENT_QUOTES, 'UTF-8')) ?></p>
    <?php endif; ?>
  </section>
  <?php endif; ?>

<?php if (!empty($__cb)) { $__cards[$__cb] = ob_get_clean(); } $__cards = $__cards ?? []; $__cb = 'hafez'; ob_start(); ?>
  <!-- Hafez Inline Box — like Dice Section -->
  <?php if ($hafez_enabled === '1' && $home_hafez_enabled === '1'): ?>
  <section class="card card-gold" style="margin-bottom:14px" id="hafezSection">
    <div class="win-badge"><h4>📖 فال حافظ</h4></div>

    <?php
      $hafez_box_already = false;
      $hafez_box_verse = null;
      if ($user_info) {
        $hz_check = $conn->prepare("SELECT hv.* FROM hafez_opens ho JOIN hafez_verses hv ON ho.verse_id = hv.id WHERE ho.user_phone = ? AND ho.opened_at >= ?");
        $hz_check->bind_param("ss", $phone, $cycle_start_str);
        $hz_check->execute();
        $hafez_box_verse = $hz_check->get_result()->fetch_assoc();
        $hafez_box_already = !!$hafez_box_verse;
      }
    ?>

    <?php if ($hafez_box_already && $hafez_box_verse): ?>
      <!-- قبلاً فال گرفته — نمایش کتاب بازشده -->
      <div class="dice-tray spread-mode" style="margin-bottom:18px;display:flex;align-items:center;justify-content:center;min-height:178px">
        <div class="book-stage">
          <div class="book3d open-done">
            <div class="b3-page1"><div class="pg-content">
                <div class="pg-title">📜 شعر فال</div>
                <div class="pg-rules"><div class="pg-text"><?= preg_replace('/\n+/', '<div class="pg-sep">***</div>', htmlspecialchars(implode("\n", verse_parts($hafez_box_verse)), ENT_QUOTES, 'UTF-8')) ?></div></div>
              </div></div>
<div class="b3-cover"><div class="bc-out"><div class="bc-cover-title"><span>فال</span><span>حافظ</span><span>📖</span></div></div><div class="bc-in"><div class="pg-content">
              <div class="pg-title">💡 تفسیر فال</div>
              <div class="pg-rules"><div class="pg-text"><?= nl2br(htmlspecialchars($hafez_box_verse['meaning_short'], ENT_QUOTES, 'UTF-8')) ?></div></div>
            </div></div></div>
          </div>
        </div>
      </div>
      <button class="roll-btn" id="hafezBtn" data-done="1" style="width:100%;opacity:.5;cursor:default">
        <span id="hafezBtnText">فال دریافت شد ✅</span>
      </button>
    <?php else: ?>
      <div class="dice-tray" id="hafezTray" style="margin-bottom:18px;display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:178px;gap:10px">
        <div class="book-stage" id="hafezStage">
          <div class="glow-burst" id="hafezGlow"></div>
          <div class="book3d" id="hafezBook">
            <div class="b3-page1"><div class="pg-content pg-fade" id="pgVerse"></div></div>
<div class="b3-cover"><div class="bc-out"><div class="bc-cover-title"><span>فال</span><span>حافظ</span><span>📖</span></div></div><div class="bc-in"><div class="pg-content pg-fade" id="pgTafsir"></div></div></div>
          </div>
        </div>
        <p id="hafezHint" style="text-align:center;font-size:.8rem;color:var(--text3)">برای دریافت فال امروز، دکمه زیر را بزنید</p>
      </div>

      <button class="roll-btn" id="hafezBtn" onclick="handleHafez()" style="width:100%">
        <span id="hafezBtnText">دریافت فال امروز 📖</span>
      </button>
    <?php endif; ?>
  </section>
  <?php endif; ?>

<?php if (!empty($__cb)) { $__cards[$__cb] = ob_get_clean(); } $__cards = $__cards ?? []; $__cb = 'winners'; ob_start(); ?>
  <?php if (($winners_list_enabled ?? '1') === '1' && ($winners_home_enabled ?? '1') === '1'): ?>
  <!-- Winners -->
  <section class="card card-gold">
    <div class="win-head"><span class="win-badge" style="margin-bottom:0"><h4>🏆 آخرین برندگان</h4></span><span class="live"><span class="live-dot"></span>زنده</span></div>
    <div id="winnersList">
    <?php $wr=$conn->query("SELECT * FROM winners ORDER BY won_at DESC LIMIT " . (int)$winners_display_count); if (($winners_fake_enabled ?? '1') !== '1') { $wr=$conn->query("SELECT * FROM winners WHERE is_fake = 0 ORDER BY won_at DESC LIMIT " . (int)$winners_display_count); } while($w=$wr->fetch_assoc()):
      $ph=htmlspecialchars($w['phone'],ENT_QUOTES,'UTF-8');
      $mask=pn(substr($ph,0,4)).'***'.pn(substr($ph,-4));
      $ts=pn(time_ago($w['won_at']));
      $pnv=htmlspecialchars($w['prize_name'],ENT_QUOTES,'UTF-8');
    ?>
      <div class="w-item"><div class="w-info"><div class="w-name"><span dir="ltr"><?=$mask?></span></div><div class="w-time"><?=$ts?></div></div><div class="w-prize"><?=$pnv?></div></div>
    <?php endwhile; ?>
    </div>
  </section>
  <?php endif; ?>

<?php if (!empty($__cb)) { $__cards[$__cb] = ob_get_clean(); } $__cards = $__cards ?? []; $__cb = 'shop'; ob_start(); ?>
  <!-- Best Sellers -->
  <?php if(!empty($best)): ?>
  <section class="card card-gold">
    <div class="win-head"><span class="win-badge" style="margin-bottom:0"><h4><?= ($home_shop_type ?? 'best')==='newest' ? '🆕 تازه‌ترین محصولات' : '🔥 پرفروش‌ها' ?></h4></span><a href="shop.php" class="blog-more">همه ‹</a></div>
    <?php
      $home_ratings = (($show_product_ratings_home = getSetting($conn, 'show_product_ratings', '1') === '1') && $best) ? sitak_product_ratings($conn, array_column($best, 'id')) : [];
    ?>
    <div class="prod-grid">
    <?php foreach($best as $b): $localBadge = !empty($b['onsite_product']) ? sitak_onsite_product_badges($b) : null; ?>
      <article class="prod-card" data-id="<?= (int)$b['id'] ?>" onclick="if(!event.target.closest('.add-btn'))location.href='product.php?id=<?= (int)$b['id'] ?>'">
        <a href="product.php?id=<?= (int)$b['id'] ?>" style="display:block;text-decoration:none;color:inherit">
        <?php if(!empty($b['image_path'])): ?>
          <img src="<?= htmlspecialchars($b['image_path'],ENT_QUOTES,'UTF-8') ?>" alt="" class="prod-img" data-fallback-emoji="<?= htmlspecialchars($custom_emoji, ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
        <?php else: ?>
          <div class="prod-img" style="display:flex;align-items:center;justify-content:center;font-size:2.2rem"><?= htmlspecialchars($custom_emoji, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <div class="prod-info">
          <div style="display:flex;justify-content:space-between;align-items:center;gap:6px;min-width:0"><div class="prod-name" style="flex:1;min-width:0"><?= htmlspecialchars($b['name'],ENT_QUOTES,'UTF-8') ?></div><?php if (isset($home_ratings[(int)$b['id']])): ?><span style="flex-shrink:0;font-size:.72rem;font-weight:700;color:var(--gold-dark);white-space:nowrap">★ <?= sitak_rating_text($home_ratings[(int)$b['id']]['avg']) ?></span><?php endif; ?></div>
        </div>
        </a>
        <div class="prod-info">
          <?php if (!empty($b['description'])): ?><div class="prod-desc"><?= htmlspecialchars(strip_tags($b['description']), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
          <div class="prod-foot">
            <span class="prod-price"><?= pn(number_format($b['price'])) ?> <small style="font-size:.65rem;font-weight:400">تومان</small></span>
            <div class="prod-foot-actions">
              <?php if ($localBadge && $localBadge['scope'] !== ''): ?><span class="prod-shipping-badge">در <?= htmlspecialchars($localBadge['scope'], ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
              <button class="add-btn" data-id="<?= (int)$b['id'] ?>" data-name="<?= htmlspecialchars($b['name'], ENT_QUOTES, 'UTF-8') ?>" data-price="<?= (int)$b['price'] ?>" data-img="<?= htmlspecialchars($b['image_path'] ?? '', ENT_QUOTES, 'UTF-8') ?>" data-local="<?= !empty($b['onsite_product']) ? '1' : '0' ?>" data-ship-province="<?= htmlspecialchars($b['shipping_province'] ?? '', ENT_QUOTES, 'UTF-8') ?>" data-ship-city="<?= htmlspecialchars($b['shipping_city'] ?? '', ENT_QUOTES, 'UTF-8') ?>" onclick="event.preventDefault();event.stopPropagation();addToCart(this)">+</button>
            </div>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

<?php if (!empty($__cb)) { $__cards[$__cb] = ob_get_clean(); } $__cards = $__cards ?? []; $__cb = 'blog'; ob_start(); ?>
  <!-- Latest Blog -->
  <?php
    if ($home_blog_enabled === '1' && $blog_enabled === '1'):
      $homeBlogOrder = ($home_blog_type === 'most_viewed') ? 'p.views' : 'p.created_at';
      $homeBlogTitle = ($home_blog_type === 'most_viewed') ? '🔥 محبوب‌ترین مطالب' : '📝 تازه‌های بلاگ';
      $bp=$conn->query("SELECT p.id, p.title, p.slug, p.excerpt, p.cover_image, p.created_at, p.views FROM posts p WHERE p.is_published=1 ORDER BY $homeBlogOrder DESC LIMIT $home_blog_count");
      $home_posts=[]; while($bp && ($rp=$bp->fetch_assoc())) $home_posts[]=$rp;
      foreach ($home_posts as $hi => $hp) {
        $home_posts[$hi]['tags'] = [];
        $htags = $conn->query("SELECT t.name FROM post_tags t JOIN post_tag_relations ptr ON t.id = ptr.tag_id WHERE ptr.post_id = " . (int)$hp['id']);
        while ($htags && ($ht = $htags->fetch_assoc())) $home_posts[$hi]['tags'][] = $ht;
      }
      $home_post_ratings = (getSetting($conn, 'show_post_ratings', '1') === '1' && $home_posts) ? sitak_post_ratings($conn, array_column($home_posts, 'id')) : [];
      if($home_posts):
  ?>
  <section class="card card-gold">
    <div class="win-head"><span class="win-badge" style="margin-bottom:0"><h4><?= $homeBlogTitle ?></h4></span><a href="blog.php" class="blog-more">همه ‹</a></div>
    <div class="post-list">
    <?php foreach($home_posts as $b): ?>
    <a href="post.php?slug=<?= htmlspecialchars($b['slug'],ENT_QUOTES,'UTF-8') ?>" class="post-card">
      <?php if(!empty($b['cover_image'])): ?>
        <img src="<?= htmlspecialchars($b['cover_image'],ENT_QUOTES,'UTF-8') ?>" alt="" class="post-cover" loading="lazy">
      <?php else: ?>
        <div class="post-cover miss">📝</div>
      <?php endif; ?>
      <div class="post-body">
        <h3 class="post-title"><?= htmlspecialchars($b['title'],ENT_QUOTES,'UTF-8') ?></h3>
        <?php if (!empty($b['excerpt'])): ?>
          <p class="post-excerpt"><?= htmlspecialchars($b['excerpt'],ENT_QUOTES,'UTF-8') ?></p>
        <?php endif; ?>
        <div class="post-meta" style="display:grid;grid-template-columns:1fr auto 1fr;align-items:center;gap:10px">
          <span style="justify-self:start">📅 <?= jdate_fa($b['created_at']) ?></span>
          <span style="justify-self:center<?= $blog_views_on ? '' : ';visibility:hidden' ?>">👁 <?= pn($b['views'] ?? 0) ?></span>
          <?php if (isset($home_post_ratings[(int)$b['id']])): ?><span style="justify-self:end;font-weight:700;color:var(--gold-dark)">★ <?= sitak_rating_text($home_post_ratings[(int)$b['id']]['avg']) ?></span><?php else: ?><span></span><?php endif; ?>
        </div>
      </div>
    </a>
    <?php endforeach; ?>
    </div>
  </section>
  <?php endif; endif; ?>

<?php if (!empty($__cb)) { $__cards[$__cb] = ob_get_clean(); } $__cb = null; foreach (sitak_card_order($conn, 'home') as $__k) { echo $__cards[$__k] ?? ''; } ?>
  <footer class="app-footer">
    <div class="f-links">
      <?php if($contact_instagram):?><a href="<?=htmlspecialchars($contact_instagram,ENT_QUOTES,'UTF-8')?>" target="_blank">اینستاگرام</a><?php endif;?>
      <?php if($contact_telegram):?><a href="<?=htmlspecialchars($contact_telegram,ENT_QUOTES,'UTF-8')?>" target="_blank">تلگرام</a><?php endif;?>
      <?php if($contact_youtube):?><a href="<?=htmlspecialchars($contact_youtube,ENT_QUOTES,'UTF-8')?>" target="_blank">یوتیوب</a><?php endif;?>
    </div>
    <?php if($contact_phone):?><p style="margin:10px 0;font-size:.78rem;text-align:center"><a href="tel:<?=htmlspecialchars($contact_phone,ENT_QUOTES,'UTF-8')?>" style="color:var(--text2);text-decoration:none;transition:color .2s" onmouseover="this.style.color='var(--gold-dark)'" onmouseout="this.style.color='var(--text2)'">📞 <?= htmlspecialchars($contact_phone, ENT_QUOTES, 'UTF-8') ?></a></p><?php endif;?>
    <?php if ($contact_address): ?>
    <p style="margin:10px 0;font-size:.78rem;color:var(--text2);text-align:center;line-height:1.7">📍 <?= htmlspecialchars($contact_address, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <?php if ($footer_copyright_enabled === '1' && $footer_copyright !== ''): ?>
    <p style="margin:10px 0;font-size:.78rem;color:var(--text2);text-align:center;line-height:1.7"><?= htmlspecialchars($footer_copyright, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
  </footer>

  <?php
  $lic1_on = !empty($license1_img) || !empty($license1_embed);
  $lic2_on = !empty($license2_img) || !empty($license2_embed);
  $lic_only_one = $lic1_on XOR $lic2_on;
  $lic1_title = $license1_name !== '' ? $license1_name : 'مجوز ۱';
  $lic2_title = $license2_name !== '' ? $license2_name : 'مجوز ۲';
  ?>
  <?php if ($licenses_enabled === '1' && ($lic1_on || $lic2_on)): ?>
  <div style="display:flex;justify-content:center;align-items:flex-end;gap:24px;flex-wrap:wrap;padding:10px 14px 20px<?= $lic_only_one ? ';width:100%' : '' ?>">
    <?php if ($lic1_on): ?>
      <?php if (!empty($license1_embed)): ?>
        <?= $license1_embed ?>
      <?php else: ?>
        <?php if (!empty($license1_link)): ?><a href="<?= htmlspecialchars($license1_link, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" referrerpolicy="origin" title="<?= htmlspecialchars($lic1_title, ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?>
        <img src="<?= htmlspecialchars($license1_img, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($lic1_title, ENT_QUOTES, 'UTF-8') ?>" style="height:150px;width:auto;max-width:220px;object-fit:contain;background:#fff;border-radius:8px;border:1px solid var(--border);padding:4px;box-sizing:border-box">
        <?php if (!empty($license1_link)): ?></a><?php endif; ?>
      <?php endif; ?>
    <?php endif; ?>
    <?php if ($lic2_on): ?>
      <?php if (!empty($license2_embed)): ?>
        <?= $license2_embed ?>
      <?php else: ?>
        <?php if (!empty($license2_link)): ?><a href="<?= htmlspecialchars($license2_link, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" referrerpolicy="origin" title="<?= htmlspecialchars($lic2_title, ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?>
        <img src="<?= htmlspecialchars($license2_img, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($lic2_title, ENT_QUOTES, 'UTF-8') ?>" style="height:150px;width:auto;max-width:220px;object-fit:contain;background:#fff;border-radius:8px;border:1px solid var(--border);padding:4px;box-sizing:border-box">
        <?php if (!empty($license2_link)): ?></a><?php endif; ?>
      <?php endif; ?>
    <?php endif; ?>
  </div>
  <?php endif; ?>
  <div class="dev-credit" style="text-align:center;font-size:.8rem;color:var(--text2);line-height:2;padding:6px 14px 22px">
    <div>توسعه و پشتیبانی توسط «<a href="https://mehdisayyadnahed.ir/" target="_blank" rel="noopener" style="color:var(--gold-dark);font-weight:700;text-decoration:none">مهدی صیادناهد</a>»</div>
    <div>ارسال پیشنهاد بهبود و توسعه‌ی این نرم‌افزار در <a href="https://github.com/mehdisayyadnahed/sitak" target="_blank" rel="noopener" style="color:var(--gold-dark);font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:4px;vertical-align:middle">Github<svg viewBox="0 0 16 16" width="16" height="16" aria-hidden="true" class="gh-logo" style="flex-shrink:0"><path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"/></svg></a></div>
  </div>
</main>

<!-- Sheet: Prize -->
<div class="overlay" id="prizeOv" onclick="closeSheet('prize')"></div>
<div class="sheet" id="prizeSheet">
  <div class="handle"></div>
  <div class="sheet-title">🎁 جایزه مورد نظرت رو انتخاب کن</div>
  <?php foreach ($prizes as $p): ?>
  <div class="pr-opt" data-id="<?= (int)$p['id'] ?>" data-name="<?= htmlspecialchars($p['description'], ENT_QUOTES, 'UTF-8') ?>" onclick="pickAndConfirm(this)">
    <div class="pr-radio"></div><span class="pr-text"><?= htmlspecialchars($p['description'], ENT_QUOTES, 'UTF-8') ?></span><?php foreach ($p['_lim']['badges'] as $bd): ?><span style="flex-shrink:0;font-size:.64rem;font-weight:700;padding:2px 8px;border-radius:10px;background:var(--gold-subtle,#fff4d6);color:var(--gold-dark);border:1px solid var(--border-gold);white-space:nowrap"><?= htmlspecialchars($bd, ENT_QUOTES, 'UTF-8') ?></span><?php endforeach; ?>
  </div>
  <?php endforeach; ?>
  <button class="btn-p" onclick="confirmPrizeAndRoll()" style="margin-top:10px">انتخاب جایزه 🎲</button>
</div>

<!-- Sheet: Register -->
<?php include 'auth_sheet.php'; ?>

<!-- Result Overlay -->
<div class="result-ov" id="resultOv" onclick="if(event.target===this)closeResult()">
  <div class="result-card" id="resultCard"></div>
</div>

<!-- Sheet: Intention (نیت فال) -->
<div class="overlay" id="intentionOv" onclick="closeSheet('intention')"></div>
<div class="sheet" id="intentionSheet">
  <div class="handle"></div>
  <div class="sheet-title">🤲 نیت کن</div>
  <p style="font-size:.85rem;color:var(--text2);text-align:center;line-height:1.8;margin-bottom:18px"><?= nl2br(htmlspecialchars(sitak_intention_msg($conn), ENT_QUOTES, 'UTF-8')) ?></p>
  <button class="btn-p" onclick="confirmIntention()">نیت کردم 🤲</button>
</div>

<!-- Announcement Popup -->
<?php if ($announcement_enabled === '1' && !empty($announcement_message)): ?>
<div class="surprise-ov" id="annPopup">
  <div class="result-card" style="max-width:360px">
    <div class="r-icon"><?= htmlspecialchars($custom_emoji, ENT_QUOTES, 'UTF-8') ?></div>
    <h3 style="color:var(--gold-dark)">به <?= htmlspecialchars($site_name ?? 'سای‌تک', ENT_QUOTES, 'UTF-8') ?> خوش آمدید!</h3>
    <div class="ann-body"><?= nl2br(htmlspecialchars($announcement_message, ENT_QUOTES, 'UTF-8')) ?></div>
    <button class="btn-p" onclick="closeAnnouncement()">متوجه شدم</button>
  </div>
</div>
<?php endif; ?>

<!-- Bottom Navigation — ترتیب: خانه · شانس · وفاداری · بلاگ · منو · سفارش -->
<nav class="bottom-nav">
  <a href="index.php" class="active">
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
  <a href="orders.php">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="8" y="2" width="8" height="4" rx="1"/><rect x="4" y="4" width="16" height="18" rx="2"/><path d="M9 14l2 2 4-4"/></svg>
    <span>سفارشات</span>
  </a>
  <?php endif; ?>
</nav>
<!-- Toast -->
<div class="toast" id="toast"></div>

<script>
/* ═══════════════════════════════════════════════════════════
   UTILITY
   ═══════════════════════════════════════════════════════════ */
var USER_PHONE = '<?= addslashes($phone) ?>';
var DEFAULT_THEME = '<?= $default_theme ?>';
var selectedPrizeId = null;
var isRolling = false;
var USER_ROLLS = <?= $user_info ? intval($user_info['total_rolls']) : 0 ?>;
var ROLL_CYCLE_START = '<?= addslashes($cycle_start_str) ?>';
var loginFrom = null; // 'roll' or 'hafez'

function escHtml(s){if(!s)return'';return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;')}

function toPersianNum(n){
  return String(n).replace(/\d/g,function(d){return'۰۱۲۳۴۵۶۷۸۹'[d]});
}

function showToast(msg){
  var t=document.getElementById('toast');
  t.textContent=msg;
  t.classList.add('show');
  clearTimeout(t._timer);
  t._timer=setTimeout(function(){t.classList.remove('show')},3000);
}

/* ═══════════════════════════════════════════════════════════
   THEME
   ═══════════════════════════════════════════════════════════ */
(function(){
  var t=(window.__R||function(v){return v})(localStorage.getItem('sitak_theme')||DEFAULT_THEME);
  document.documentElement.setAttribute('data-theme',t);
  updateThemeIcon(t);
})();

// بررسی لاگ‌اوت اجباری (کاربر مسدود شده)
<?php if (!empty($force_logout_popup)): ?>
(function(){
  var t=document.createElement('div');
  t.className='toast';
  t.style.cssText='position:fixed;bottom:90px;left:50%;transform:translateX(-50%);background:linear-gradient(135deg,var(--gold,#ecb534),var(--gold-dark,#c49625));border:none;color:#fff;padding:13px 22px;border-radius:16px;font-size:.82rem;font-weight:500;z-index:500;opacity:0;transition:all .35s cubic-bezier(.34,1.56,.64,1);max-width:min(88%,420px);text-align:center;box-shadow:var(--shadow-lg)';
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

function toggleTheme(){
  var cur=document.documentElement.getAttribute('data-theme');
  var next=cur==='dark'?'light':'dark';
  document.documentElement.setAttribute('data-theme',next);
  localStorage.setItem('sitak_theme',next);
  updateThemeIcon(next);
  var mc=document.getElementById('metaTheme');
  if(mc)mc.setAttribute('content',next==='dark'?'#0a0a0c':'#ffffff');
}

function updateThemeIcon(t){
  var ic=document.getElementById('themeIcon');
  if(ic)ic.textContent=t==='dark'?'☀️':'🌙';
}

/* ═══════════════════════════════════════════════════════════
   BOTTOM SHEETS
   ═══════════════════════════════════════════════════════════ */
/* openSheet/closeSheet/refreshCaptcha/doRegister — در auth_sheet.php */

/* ═══════════════════════════════════════════════════════════
   CAPTCHA
   ═══════════════════════════════════════════════════════════ */

/* ═══════════════════════════════════════════════════════════
   PRIZE SELECTION
   ═══════════════════════════════════════════════════════════ */
function pickAndConfirm(el){
  document.querySelectorAll('#prizeSheet .pr-opt').forEach(function(o){o.classList.remove('sel')});
  el.classList.add('sel');
  selectedPrizeId=el.getAttribute('data-id');
  var name=el.getAttribute('data-name');
  var txt=document.getElementById('selPrizeText');
  txt.textContent=name;
  txt.classList.remove('empty');
}
function confirmPrizeAndRoll(){
  if(!selectedPrizeId){showToast('لطفاً یک جایزه انتخاب کنید');return}
  closeSheet('prize');
  handleRoll();
}

/* ═══════════════════════════════════════════════════════════
   DICE SVG — تاس چوبی (بدون برد) یا طلایی (با برد)
   ═══════════════════════════════════════════════════════════ */
function rollDiceSVG(val, dieIdx, isWin){
  var dots={1:[[30,30]],2:[[15,15],[45,45]],3:[[15,15],[30,30],[45,45]],
            4:[[15,15],[15,45],[45,15],[45,45]],5:[[15,15],[15,45],[30,30],[45,15],[45,45]],
            6:[[15,15],[15,30],[15,45],[45,15],[45,30],[45,45]]};
  var d=dots[val]||dots[1];
  var circles='';
  d.forEach(function(p){
    var r=(val===1)?7:5;
    var color=isWin?'#ffffff':'#3D2B1F';
    circles+='<circle cx="'+p[0]+'" cy="'+p[1]+'" r="'+r+'" fill="'+color+'"/>';
  });

  if(isWin){
    return '<svg viewBox="0 0 60 60" style="display:block">'+
      '<defs>'+
      '<linearGradient id="wGrad'+dieIdx+'" x1="0%" y1="0%" x2="100%" y2="100%">'+
      '<stop offset="0%" stop-color="#f5c758"/><stop offset="100%" stop-color="#ecb534"/></linearGradient>'+
      '<filter id="wSh'+dieIdx+'" x="-10%" y="-10%" width="130%" height="130%">'+
      '<feDropShadow dx="0" dy="3" stdDeviation="2" flood-opacity="0.15"/></filter></defs>'+
      '<rect x="1" y="1" width="58" height="58" rx="14" fill="url(#wGrad'+dieIdx+')" filter="url(#wSh'+dieIdx+')"/>'+
      '<rect x="3" y="3" width="54" height="20" rx="10" fill="#ffffff" opacity="0.1"/>'+
      circles+'</svg>';
  }else{
    return '<svg viewBox="0 0 60 60" style="display:block">'+
      '<defs>'+
      '<filter id="dSh'+dieIdx+'" x="-10%" y="-10%" width="130%" height="130%">'+
      '<feDropShadow dx="0" dy="2" stdDeviation="1.5" flood-opacity="0.08"/></filter></defs>'+
      '<rect x="1" y="1" width="58" height="58" rx="14" fill="#E8D5B5" filter="url(#dSh'+dieIdx+')"/>'+
      '<rect x="3" y="3" width="54" height="20" rx="10" fill="#ffffff" opacity="0.12"/>'+
      circles+'</svg>';
  }
}

function initDice(){
  var d1e=document.getElementById('die1'),d2e=document.getElementById('die2');
  if(!d1e||!d2e)return;
  if(loadLastRoll())return;
  var d1v=Math.floor(Math.random()*6)+1,d2v=Math.floor(Math.random()*6)+1;
  d1e.innerHTML=rollDiceSVG(d1v,1,false);
  d2e.innerHTML=rollDiceSVG(d2v,2,false);
}
function saveLastRoll(d1,d2,win){try{localStorage.setItem('sitak_lastroll',JSON.stringify({d1:d1,d2:d2,win:win?1:0,c:ROLL_CYCLE_START,p:USER_PHONE}))}catch(e){}}
function loadLastRoll(){try{var s=JSON.parse(localStorage.getItem('sitak_lastroll')||'null');if(!s||s.c!==ROLL_CYCLE_START||s.p!==USER_PHONE)return false;var d1=document.getElementById('die1'),d2=document.getElementById('die2'),tray=document.getElementById('diceTray');if(!d1||!d2||!tray)return false;d1.innerHTML=rollDiceSVG(s.d1,1,!!s.win);d2.innerHTML=rollDiceSVG(s.d2,2,!!s.win);if(s.win){d1.classList.add('winner');d2.classList.add('winner');tray.classList.add('winning')}return true}catch(e){return false}}
function updateRollBtn(){var btn=document.getElementById('rollBtn'),txt=document.getElementById('rollText');if(!btn||!txt)return;if(USER_PHONE&&USER_ROLLS<=0){btn.disabled=false;btn.style.opacity='.5';btn.style.cursor='default';txt.textContent='تاس‌ها انداخته شد ✅'}else{btn.disabled=false;btn.style.opacity='';btn.style.cursor='';txt.textContent='تاس بنداز 🎲'}}

/* ═══════════════════════════════════════════════════════════
   ROLL — جریان اصلی: لاگین → انتخاب جایزه → انداختن تاس
   ═══════════════════════════════════════════════════════════ */
function handleRoll(){
  if(isRolling) return;

  // گام ۱: آیا کاربر لاگین است؟
  if(!USER_PHONE){
    loginFrom='roll';
    openSheet('reg');
    return;
  }

  // گام ۱.۵: آیا شانس تاس انداختن دارد؟
  if(USER_ROLLS <= 0){
    showToast('شانس تاس انداختن ندارید! تا ریست بعدی صبر کنید یا دوستان را دعوت کنید 🎲');
    return;
  }

  // گام ۲: آیا جایزه انتخاب شده؟
  if(!selectedPrizeId){
    var prizeCard=document.getElementById('prizeCard');
    prizeCard.style.animation='shake 0.5s ease';
    prizeCard.addEventListener('animationend',function handler(){
      prizeCard.style.animation='';
      prizeCard.removeEventListener('animationend',handler);
    });
    openSheet('prize');
    return;
  }

  // گام ۳: اجرای تاس
  doRoll();
}

/* ═══════════════════════════════════════════════════════════
   HAFEZ — مشابه تاس: لاگین → فال
   ═══════════════════════════════════════════════════════════ */
/* صدای ورق خوردن: سُرخوردن کاغذ + فرود */

var isHafezing=false;

/* ═══ Sound engine (Web Audio API) ═══ */
/* ───── صداهای سنتزی (Web Audio، بدون فایل) ───── */
var _ac=null,_noiseBuf=null,_bus=null;
function getAC(){
  if(!_ac){
    _ac=new(window.AudioContext||window.webkitAudioContext)();
    var sr=_ac.sampleRate;
    _noiseBuf=_ac.createBuffer(1,Math.floor(sr*0.4),sr);
    var d=_noiseBuf.getChannelData(0);for(var i=0;i<d.length;i++)d[i]=Math.random()*2-1;
    // بازتاب خفیف اتاق برای اینکه صدا روی میز واقعی‌تر باشد
    var rl=Math.floor(sr*0.45),room=_ac.createBuffer(2,rl,sr);
    for(var ch=0;ch<2;ch++){var rd=room.getChannelData(ch);for(var j=0;j<rl;j++)rd[j]=(Math.random()*2-1)*Math.pow(1-j/rl,3)}
    var conv=_ac.createConvolver();conv.buffer=room;
    var wet=_ac.createGain();wet.gain.value=0.18;
    _bus=_ac.createGain();_bus.gain.value=0.9;
    _bus.connect(_ac.destination);
    _bus.connect(conv);conv.connect(wet);wet.connect(_ac.destination);
  }
  if(_ac.state==='suspended')_ac.resume();
  return _ac;
}
// خروجی با پن تصادفی کم (برای اینکه صداها از یک نقطه نیایند)
function _panOut(c,spread){
  if(!c.createStereoPanner)return _bus;
  var p=c.createStereoPanner();p.pan.value=(Math.random()*2-1)*spread;p.connect(_bus);return p;
}
/* تیک خشک: تکان تاس‌ها در دست */
function _tick(t,vol,freq){
  var c=_ac,out=_panOut(c,0.5);
  var n=c.createBufferSource();n.buffer=_noiseBuf;n.playbackRate.value=1+Math.random()*0.4;
  var hp=c.createBiquadFilter();hp.type='highpass';hp.frequency.value=freq;
  var g=c.createGain();
  g.gain.setValueAtTime(vol,t);g.gain.exponentialRampToValueAtTime(vol*0.02,t+0.02);
  n.connect(hp);hp.connect(g);g.connect(out);n.start(t);n.stop(t+0.03);
}
/* ضربه‌ی تاس روی میز چوبی: کلیک اولیه + دو مود رزونانس + کمی ترکش */
function _knock(t,vol,f){
  var c=_ac,out=_panOut(c,0.4);
  var n=c.createBufferSource();n.buffer=_noiseBuf;n.playbackRate.value=1+Math.random()*0.3;
  var hp=c.createBiquadFilter();hp.type='highpass';hp.frequency.value=2600;
  var ng=c.createGain();ng.gain.setValueAtTime(vol*0.9,t);ng.gain.exponentialRampToValueAtTime(0.001,t+0.014);
  n.connect(hp);hp.connect(ng);ng.connect(out);n.start(t);n.stop(t+0.02);
  var modes=[[f,0.55,0.13],[f*1.52,0.3,0.075],[f*2.31,0.15,0.04]];
  modes.forEach(function(m){
    var o=c.createOscillator();o.type='sine';
    o.frequency.setValueAtTime(m[0]*1.05,t);o.frequency.exponentialRampToValueAtTime(m[0],t+0.025);
    var g=c.createGain();g.gain.setValueAtTime(vol*m[1],t);g.gain.exponentialRampToValueAtTime(0.0001,t+m[2]);
    o.connect(g);g.connect(out);o.start(t);o.stop(t+m[2]+0.02);
  });
}
/* زنگ کوچک با سه partial (برای برد و توقف گردونه) */
function _bell(t,f,vol,dur){
  var c=_ac,out=_panOut(c,0.3);
  [[1,1,dur],[2.76,0.28,dur*0.55],[5.4,0.09,dur*0.25]].forEach(function(p){
    var o=c.createOscillator();o.type='sine';o.frequency.value=f*p[0];
    var g=c.createGain();g.gain.setValueAtTime(0.0001,t);
    g.gain.exponentialRampToValueAtTime(vol*p[1],t+0.008);
    g.gain.exponentialRampToValueAtTime(0.0001,t+p[2]);
    o.connect(g);g.connect(out);o.start(t);o.stop(t+p[2]+0.05);
  });
}
/* میخ گردونه: کلیک کوتاه هنگام رد شدن از هر میخ */
function _peg(t,vol){
  var c=_ac,out=_panOut(c,0.25);
  var n=c.createBufferSource();n.buffer=_noiseBuf;n.playbackRate.value=1.2;
  var hp=c.createBiquadFilter();hp.type='highpass';hp.frequency.value=3200;
  var g=c.createGain();g.gain.setValueAtTime(vol,t);g.gain.exponentialRampToValueAtTime(0.001,t+0.012);
  n.connect(hp);hp.connect(g);g.connect(out);n.start(t);n.stop(t+0.02);
  var o=c.createOscillator();o.type='triangle';o.frequency.value=1900+Math.random()*350;
  var og=c.createGain();og.gain.setValueAtTime(vol*0.5,t);og.gain.exponentialRampToValueAtTime(0.001,t+0.03);
  o.connect(og);og.connect(out);o.start(t);o.stop(t+0.035);
}

/* صدای تاس انداختن: تکان، ضربه‌های کاهشی روی میز و سرخوردن */
function playDiceRollSound(){
  try{
    var c=getAC(),t=c.currentTime+0.02,i;
    for(i=0;i<14;i++){_tick(t,0.045+Math.random()*0.045,1800+Math.random()*2200);t+=0.024+Math.random()*0.036}
    t+=0.12;
    var vol=0.42,gap=0.11;
    for(i=0;i<7;i++){
      _knock(t,vol*(0.9+Math.random()*0.2),380+Math.random()*260);
      _knock(t+0.011+Math.random()*0.01,vol*0.5,640+Math.random()*240);
      t+=gap;gap*=1.28;vol*=0.55;
    }
    var sw=c.createBufferSource();sw.buffer=_noiseBuf;sw.playbackRate.value=0.35;
    var lp=c.createBiquadFilter();lp.type='lowpass';lp.frequency.setValueAtTime(900,t);
    lp.frequency.exponentialRampToValueAtTime(200,t+0.2);
    var sg=c.createGain();sg.gain.setValueAtTime(0.05,t);sg.gain.exponentialRampToValueAtTime(0.001,t+0.2);
    sw.connect(lp);lp.connect(sg);sg.connect(_bus);sw.start(t);sw.stop(t+0.21);
  }catch(e){}
}
/* صدای برد: آرپژ زنگ صعودی و یک آکورد نهایی */
function playDiceWinSound(){
  try{
    var c=getAC(),t0=c.currentTime+0.04;
    [523.25,659.25,783.99,1046.5].forEach(function(f,i){_bell(t0+i*0.1,f,0.2,0.7)});
    var tf=t0+0.46;
    [1046.5,1318.5,1568].forEach(function(f){_bell(tf,f,0.13,1.3)});
  }catch(e){}
}
/* صدای باخت: دو نت پایین و گرفته */
function playDiceLoseSound(){
  try{
    var c=getAC(),t0=c.currentTime+0.05;
    [196,155.56].forEach(function(f,i){
      var t=t0+i*0.26,out=_panOut(c,0.2);
      var o=c.createOscillator();o.type='triangle';o.frequency.setValueAtTime(f,t);o.frequency.exponentialRampToValueAtTime(f*0.97,t+0.4);
      var lp=c.createBiquadFilter();lp.type='lowpass';lp.frequency.setValueAtTime(900,t);lp.frequency.exponentialRampToValueAtTime(260,t+0.4);
      var g=c.createGain();g.gain.setValueAtTime(0.0001,t);g.gain.exponentialRampToValueAtTime(0.22,t+0.03);g.gain.exponentialRampToValueAtTime(0.0001,t+0.45);
      o.connect(lp);lp.connect(g);g.connect(out);o.start(t);o.stop(t+0.5);
    });
  }catch(e){}
}
/* صدای ورق خوردن کتاب: سُرخوردن کاغذ + ترق‌های ریز + فرود */
function playPageTurnSound(){
  try{
    var c=getAC(),t=c.currentTime+0.02;
    var n=c.createBufferSource();n.buffer=_noiseBuf;n.loop=true;n.playbackRate.value=0.9;
    var bp=c.createBiquadFilter();bp.type='bandpass';bp.Q.value=0.9;
    bp.frequency.setValueAtTime(1500,t);bp.frequency.exponentialRampToValueAtTime(550,t+0.75);
    var g=c.createGain();
    g.gain.setValueAtTime(0.0001,t);
    g.gain.exponentialRampToValueAtTime(0.12,t+0.16);
    g.gain.setValueAtTime(0.12,t+0.5);
    g.gain.exponentialRampToValueAtTime(0.0001,t+0.8);
    n.connect(bp);bp.connect(g);g.connect(c.destination);n.start(t);n.stop(t+0.83);
    var th=c.createBufferSource();th.buffer=_noiseBuf;th.playbackRate.value=0.5;
    var lp=c.createBiquadFilter();lp.type='lowpass';lp.frequency.value=500;
    var tg=c.createGain();
    var tt=t+0.72;
    tg.gain.setValueAtTime(0.07,tt);tg.gain.exponentialRampToValueAtTime(0.001,tt+0.14);
    th.connect(lp);lp.connect(tg);tg.connect(c.destination);th.start(tt);th.stop(tt+0.15);
  }catch(e){}
}
/* صدای چرخش گردونه: میخ‌های پشت‌سرهم که هرچه گردونه کند می‌شود آهسته‌تر و آرام‌تر می‌شوند */
function playWheelSpinSound(){
  try{
    var c=getAC(),t=c.currentTime+0.02,total=2.8,el=0,iv=0.03;
    while(el<total-0.05){
      var prog=el/total;
      _peg(t,0.2*(1-prog*0.7));
      t+=iv;el+=iv;
      iv=0.03+0.24*Math.pow(prog,1.8);
    }
  }catch(e){}
}
/* توقف گردونه: ضربه‌ی چوب + زنگ کوتاه */
function playWheelStopSound(){
  try{
    var c=getAC(),t=c.currentTime+0.02;
    _knock(t,0.3,520);
    _bell(t+0.05,1318.5,0.08,0.6);
  }catch(e){}
}

var isHafezing=false;
function handleHafez(){
  if(isHafezing) return;
  if(!USER_PHONE){ loginFrom='hafez'; openSheet('reg'); return; }
  if(document.getElementById('hafezBtn').dataset.done==='1'){showToast('فال امروزت رو قبلاً گرفتی، فردا دوباره سر بزن 📖');return}
  openSheet('intention');
}
function confirmIntention(){
  closeSheet('intention');
  doHafez();
}
function doHafez(){
  isHafezing=true;
  var btn=document.getElementById('hafezBtn');
  btn.disabled=true;
  document.getElementById('hafezBtnText').textContent='در حال دریافت...';
  var hint=document.getElementById('hafezHint');
  if(hint){hint.textContent='در حال آماده شدن فال...';hint.style.display=''}
  var tray=document.getElementById('hafezTray');
  tray.style.display='flex';
  var book=document.getElementById('hafezBook');
  var fetchP=fetch('ajax.php?action=hafez_open').then(function(r){return r.json()});
  var delayP=new Promise(function(res){setTimeout(res,800)});
  Promise.all([fetchP,delayP]).then(function(arr){
    var d=arr[0];
    if(d.success||d.verse){
      var v=d.verse;
      var pv=document.getElementById('pgVerse'),pt=document.getElementById('pgTafsir');
      if(pv)pv.innerHTML='<div class="pg-title">📜 شعر فال</div><div class="pg-rules"><div class="pg-text">'+escHtml((v.verse_m1||'')+'\n'+(v.verse_m2||'')).replace(/\n+/g,'<div class="pg-sep">***</div>')+'</div></div>';
      if(pt)pt.innerHTML='<div class="pg-title">💡 تفسیر فال</div><div class="pg-rules"><div class="pg-text">'+escHtml(v.meaning_short).replace(/\n/g,'<br>')+'</div></div>';
      if(book)book.classList.add('open-zoom');
      tray.classList.add('spread-mode');
      if(hint)hint.style.display='none';
      setTimeout(function(){var glow=document.getElementById('hafezGlow');if(glow){glow.classList.remove('flash');void glow.offsetWidth;glow.classList.add('flash')}},750);
      setTimeout(function(){playPageTurnSound()},800);
      setTimeout(function(){if(pv)pv.classList.add('show')},1500);
      setTimeout(function(){if(pt)pt.classList.add('show')},2400);
      setTimeout(function(){if(book){book.classList.remove('open-zoom');book.classList.add('open-done')}},2400);
      document.getElementById('hafezBtnText').textContent='فال دریافت شد ✅';
      btn.style.opacity='.5';btn.style.cursor='default';btn.disabled=false;btn.dataset.done='1';
      if(d.success){setTimeout(function(){spawnConfetti();showToast('فال شما دریافت شد ✨')},3500)}
      else if(d.message){setTimeout(function(){showToast(d.message)},3500)}
    }else{
      if(book){book.classList.remove('open-zoom');book.classList.remove('open-done')}
      tray.classList.remove('spread-mode');
      document.getElementById('hafezBtnText').textContent='📖 دریافت این دوره';
      if(hint){hint.textContent='برای دریافت فال امروز، دکمه زیر را بزنید';hint.style.display=''}
      if(d.message) showToast(d.message);
      btn.disabled=false;
    }
    isHafezing=false;
  }).catch(function(){
    isHafezing=false;
    if(book){book.classList.remove('open-zoom');book.classList.remove('open-done')}
    tray.classList.remove('spread-mode');
    document.getElementById('hafezBtnText').textContent='📖 دریافت این دوره';
    if(hint){hint.textContent='برای دریافت فال امروز، دکمه زیر را بزنید';hint.style.display=''}
    btn.disabled=false;
    showToast('خطا در ارتباط با سرور');
  });
}

// پیام دوخطی برای کاربر مسدود شده
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

function doRoll(){
  isRolling=true;
  var btn=document.getElementById('rollBtn');
  btn.disabled=true;
  var d1=document.getElementById('die1');
  var d2=document.getElementById('die2');
  var tray=document.getElementById('diceTray');

  d1.classList.remove('winner');d2.classList.remove('winner');
  tray.classList.remove('winning');
  d1.classList.add('animating');d2.classList.add('animating');
  playDiceRollSound();

  // تغییر مداوم اعداد تاس‌ها در حین انیمیشن
  var animInterval=setInterval(function(){
    d1.innerHTML=rollDiceSVG(Math.floor(Math.random()*6)+1,1,false);
    d2.innerHTML=rollDiceSVG(Math.floor(Math.random()*6)+1,2,false);
  },120);

  var fd=new FormData();
  fd.append('prize_id',selectedPrizeId);

  var fetchP=fetch('ajax.php?action=roll',{method:'POST',body:scsrf(fd)}).then(function(r){return r.json()});
  var delayP=new Promise(function(res){setTimeout(res,2800)});

  Promise.all([fetchP,delayP]).then(function(arr){
    clearInterval(animInterval);
    var data=arr[0];
    d1.classList.remove('animating');d2.classList.remove('animating');

    if(data.blocked === true){
      showToast(data.message || BLOCKED_MSG);
      isRolling=false;btn.disabled=false;updateRollBtn();
      setTimeout(function(){ fetch('ajax.php?action=logout_user',{credentials:'same-origin'}); location.reload(); }, 2500);
      return;
    }
    if(data.success){
      var isWin=data.is_winner;
      d1.innerHTML=rollDiceSVG(data.dice1,1,isWin);
      d2.innerHTML=rollDiceSVG(data.dice2,2,isWin);

      if(isWin){
        d1.classList.add('winner');d2.classList.add('winner');
        tray.classList.add('winning');
        spawnConfetti();
        playDiceWinSound();
      }else{
        playDiceLoseSound();
      }

      var badge=document.getElementById('rollBadge');
      if(badge)badge.textContent='🎲 '+toPersianNum(data.remaining_rolls);
      USER_ROLLS=data.remaining_rolls;
      saveLastRoll(data.dice1,data.dice2,isWin);
      showResult(data);
    }else{
      showToast(data.message);
      initDice();
    }
    isRolling=false;btn.disabled=false;updateRollBtn();
  }).catch(function(){
    clearInterval(animInterval);
    d1.classList.remove('animating');d2.classList.remove('animating');
    initDice();
    isRolling=false;btn.disabled=false;
    showToast('خطا در ارتباط با سرور');
  });
}

/* ═══════════════════════════════════════════════════════════
   RESULT — نمایش کامل اطلاعات جایزه
   ═══════════════════════════════════════════════════════════ */
<?php sitak_result_js($conn); ?>
function showResult(data){
  var ov=document.getElementById('resultOv');
  var card=document.getElementById('resultCard');
  if(data.is_winner){
    var extra='';
    if(data.prize.discount_code){
      extra+='<div style="margin-top:14px"><span style="font-size:.72rem;font-weight:600;color:var(--text2);display:block;margin-bottom:5px">کد تخفیف:</span><div class="code-row" onclick="copyCode(this)"><span class="code-val">'+escHtml(data.prize.discount_code)+'</span><span class="code-hint">کپی</span></div></div>';
    }
    if(data.prize.affiliate_link){
      extra+='<a href="'+escHtml(data.prize.affiliate_link)+'" target="_blank" class="aff-btn" style="margin-top:10px">مشاهده محصول 🔗</a>';
    }
    extra+=resultNoteHtml(data.prize);
    card.className='result-card win';
    card.innerHTML='<div class="r-icon">🎉</div><div class="r-badge">تبریک!</div><h3>برنده شدی!</h3><div class="r-prize">'+escHtml(data.prize.description)+'</div><p class="r-desc">جفت شش آوردی! 🎲🎲</p>'+extra+'<button class="btn-p" onclick="closeResult()" style="margin-top:16px">عالیه! ✨</button>';
  }else{
    card.className='result-card';
    card.innerHTML='<div class="r-icon">🎲</div><h3>نشد که بشه!</h3><p class="r-desc">'+resultEsc(RESULT_MSGS.result_lose_msg)+'</p><button class="btn-p" onclick="closeResult()">باشه</button>';
  }
  ov.classList.add('open');
}

function closeResult(){
  document.getElementById('resultOv').classList.remove('open');
  location.reload();
}

/* ═══════════════════════════════════════════════════════════
   REGISTRATION — با کپچا + auto-advance به انتخاب جایزه
   ═══════════════════════════════════════════════════════════ */
/* doRegister — در auth_sheet.php */

/* ═══════════════════════════════════════════════════════════
   CONFETTI
   ═══════════════════════════════════════════════════════════ */
function spawnConfetti(){
  var box=document.createElement('div');
  box.className='confetti-box';
  document.body.appendChild(box);
  var colors=['#ecb534','#ff453a','#30d158','#0a84ff','#f5d076','#bf5af2','#ff6482','#ffd60a'];
  for(var i=0;i<60;i++){
    var p=document.createElement('div');
    p.className='confetti';
    p.style.left=Math.random()*100+'%';
    p.style.width=(Math.random()*8+4)+'px';
    p.style.height=(Math.random()*10+4)+'px';
    p.style.background=colors[Math.floor(Math.random()*colors.length)];
    p.style.animationDuration=(Math.random()*2+2)+'s';
    p.style.animationDelay=Math.random()*0.6+'s';
    box.appendChild(p);
  }
  setTimeout(function(){box.remove()},5500);
}

/* ═══════════════════════════════════════════════════════════
   COPY CODE
   ═══════════════════════════════════════════════════════════ */
function copyCode(el){
  var code=el.querySelector('.code-val').textContent;
  if(navigator.clipboard){
    navigator.clipboard.writeText(code).then(function(){
      el.classList.add('copied');
      el.querySelector('.code-hint').textContent='کپی شد ✓';
      setTimeout(function(){el.classList.remove('copied');el.querySelector('.code-hint').textContent='کپی'},2000);
    });
  }else{
    var ta=document.createElement('textarea');
    ta.value=code;document.body.appendChild(ta);ta.select();document.execCommand('copy');document.body.removeChild(ta);
    el.classList.add('copied');
    el.querySelector('.code-hint').textContent='کپی شد ✓';
    setTimeout(function(){el.classList.remove('copied');el.querySelector('.code-hint').textContent='کپی'},2000);
  }
}

/* ═══════════════════════════════════════════════════════════
   ANNOUNCEMENT
   ═══════════════════════════════════════════════════════════ */
function closeAnnouncement(){
  document.getElementById('annPopup').classList.remove('open');
  sessionStorage.setItem('sitak_ann_seen','1');
}

/* ═══════════════════════════════════════════════════════════
   INIT
   ═══════════════════════════════════════════════════════════ */
(function(){
  initDice();
  updateRollBtn();
  <?php if ($announcement_enabled === '1' && !empty($announcement_message)): ?>
  if(!sessionStorage.getItem('sitak_ann_seen')){
    document.getElementById('annPopup').classList.add('open');
  }
  <?php endif; ?>
  // Bottom nav active state
  document.querySelectorAll('.bottom-nav a').forEach(function(a){
    if(a.pathname===window.location.pathname){
      a.classList.add('active');
    }else{
      a.classList.remove('active');
    }
  });
})();
</script>
<?php include 'cart_sheet.php'; ?>
</body>
</html>
