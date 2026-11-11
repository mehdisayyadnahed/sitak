<?php
require_once 'config.php';
$blog_views_on = getSetting($conn, 'blog_views_enabled', '1') === '1';

$captcha_n1 = rand(1, 10);
$captcha_n2 = rand(1, 10);
$_SESSION['captcha_answer'] = $captcha_n1 + $captcha_n2;

$slug = trim($_GET['slug'] ?? '');
if (empty($slug)) { header('Location: blog.php'); exit; }

$blog_enabled = getSetting($conn, 'blog_enabled', '1');
$shop_enabled = getSetting($conn, 'shop_enabled', '1');
$orders_tab_enabled = getSetting($conn, 'orders_tab_enabled', '1');
$site_name    = getSetting($conn, 'site_name', 'سای‌تک');
$site_slogan  = getSetting($conn, 'site_slogan', 'یک وبسایت تک');
if ($blog_enabled !== '1') { header('Location: index.php'); exit; }

$default_theme  = getSetting($conn, 'default_theme', 'auto');
$custom_favicon = getSetting($conn, 'custom_favicon', '');
$custom_logo    = getSetting($conn, 'custom_logo', '');
$loyalty_enabled = getSetting($conn, 'loyalty_enabled', '1');
$chance_enabled = getSetting($conn, 'chance_enabled', '1');

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

// ── دریافت مقاله ──
$stmt = $conn->prepare("SELECT p.*, pc.name AS cat_name, pc.slug AS cat_slug, pc.color AS cat_color
    FROM posts p LEFT JOIN post_categories pc ON p.category_id = pc.id
    WHERE p.slug = ? AND p.is_published = 1");
$stmt->bind_param("s", $slug);
$stmt->execute();
$post = $stmt->get_result()->fetch_assoc();
if (!$post) { header('Location: blog.php'); exit; }

// ── افزایش بازدید ──
$conn->query("UPDATE posts SET views = views + 1 WHERE id = {$post['id']}");
track_view($conn, 'post:' . $post['id']);
$show_post_ratings = getSetting($conn, 'show_post_ratings', '1') === '1';
$post_rating = $show_post_ratings ? (sitak_post_ratings($conn, [$post['id']])[$post['id']] ?? null) : null;
$post['views']++;

// ── برچسب‌ها ──
$tags = [];
$tags_res = $conn->query("SELECT t.name, t.slug FROM post_tags t
    JOIN post_tag_relations ptr ON t.id = ptr.tag_id
    WHERE ptr.post_id = {$post['id']}");
while ($t = $tags_res->fetch_assoc()) { $tags[] = $t; }

// ── مقالات مرتبط ──
$related = [];
if ($post['category_id']) {
    $r_res = $conn->query("SELECT p.id, p.title, p.slug, p.cover_image, p.excerpt, p.views, p.created_at, pc.name AS cat_name, pc.color AS cat_color
        FROM posts p LEFT JOIN post_categories pc ON pc.id = p.category_id
        WHERE p.category_id = {$post['category_id']} AND p.id != {$post['id']} AND p.is_published = 1
        ORDER BY p.created_at DESC LIMIT 4");
    while ($r = $r_res->fetch_assoc()) { $related[] = $r; }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="<?= htmlspecialchars($default_theme, ENT_QUOTES, 'UTF-8') ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<meta name="theme-color" content="#ffffff" id="metaTheme">
<?= theme_head_script('sitak_theme', $default_theme) ?>
<?= sitak_head_meta($conn, ['title' => $post['title'], 'description' => $post['excerpt'] ?: $post['body'], 'image' => $post['cover_image'] ?? '', 'type' => 'article']) ?>
<title><?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?> | <?= htmlspecialchars($site_name ?? 'سای‌تک', ENT_QUOTES, 'UTF-8') ?></title>
<link href="assets/fonts/vazirmatn.css" rel="stylesheet">
<style>
:root,[data-theme="light"]{--bg:#f5f5f7;--surface:#fff;--card:#fff;--card-el:#f0eff2;--input-bg:#f0eff2;--border:rgba(255,255,255,.08);--border-gold:rgba(236,181,52,.22);--text:#1d1d1f;--text2:#6e6e73;--text3:#aeaeb2;--header-bg:rgba(255,255,255,.82);--shadow-sm:0 1px 3px rgba(0,0,0,.06);--red:#ff453a;--red-bg:rgba(255,69,58,.06);--gold:#ecb534;--gold-dark:#c49625;--gold-subtle:rgba(236,181,52,.07);--r:20px;--rs:14px;--rss:10px;--safe-t:env(safe-area-inset-top,0px);--safe-b:env(safe-area-inset-bottom,0px);--font:'Vazirmatn',-apple-system,BlinkMacSystemFont,sans-serif}
[data-theme="dark"]{--bg:#0a0a0c;--surface:#161618;--card:#1c1c1e;--card-el:#2c2c2e;--input-bg:#2c2c2e;--border:rgba(255,255,255,.08);--border-gold:rgba(236,181,52,.15);--text:#f0ece4;--text2:#9e9888;--text3:#5e5a52;--header-bg:rgba(10,10,12,.88);--shadow-sm:0 1px 3px rgba(0,0,0,.3);--red:#f87171;--red-bg:rgba(248,113,113,.08)}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent}
html{font-size:16px;-webkit-text-size-adjust:100%;overscroll-behavior:none}
body{font-family:var(--font);background:var(--bg);color:var(--text);min-height:100dvh;overflow-x:hidden;-webkit-font-smoothing:antialiased;padding-top:var(--safe-t);transition:background .35s,color .35s}

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

.content{position:relative;z-index:1;padding:12px 14px 10px;max-width:600px;margin:0 auto}

/* Cover */
.post-cover-full{width:100%;height:240px;object-fit:cover;border-radius:var(--r);margin-bottom:20px;background:var(--card-el)}

/* Header */
.post-hdr{margin-bottom:24px}
.post-cat-badge{border:1px solid var(--gold);min-height:24px;line-height:17px;display:inline-flex;align-items:center;justify-content:center;padding:2px 8px;border-radius:10px;font-size:.64rem;font-weight:700;margin-bottom:10px;background:var(--gold-subtle);color:var(--gold-dark);vertical-align:middle;text-decoration:none}
.post-title-full{font-size:1.35rem;font-weight:900;line-height:1.7;margin-bottom:10px}
.post-meta-full{display:flex;gap:14px;font-size:.75rem;color:var(--text3)}

/* Tags */
.post-tags-full{display:flex;gap:6px;flex-wrap:wrap;margin-top:12px}
.post-tag-full{padding:3px 10px;background:var(--card-el);border:1px solid var(--border);border-radius:10px;font-size:.7rem;color:var(--text2);font-weight:500}

/* Body */
.post-content{line-height:2;font-size:.95rem;color:var(--text)}
.post-content h1,.post-content h2,.post-content h3{font-weight:800;margin:24px 0 12px;color:var(--gold-dark)}
.post-content h1{font-size:1.3rem}.post-content h2{font-size:1.15rem}.post-content h3{font-size:1.05rem}
.post-content p{margin-bottom:16px}
.post-content img{max-width:100%;border-radius:var(--r);margin:16px 0;display:block}
.post-content ul,.post-content ol{padding-right:1.4em;padding-left:0;margin:0 0 16px}
.post-content li{margin-bottom:6px}
.post-content table{width:100%;max-width:100%;display:block;overflow-x:auto;border-collapse:collapse;margin:12px 0}
.post-content td,.post-content th{border:1px solid var(--border);padding:6px 8px}
.post-content iframe{max-width:100%}
.post-content{overflow-wrap:anywhere;word-break:break-word}
.post-content a{color:#a8781a;font-weight:600;text-decoration:underline;text-underline-offset:3px}
[data-theme="dark"] .post-content a{color:#ecb534}
@media (prefers-color-scheme: dark){[data-theme="auto"] .post-content a{color:#ecb534}}
.post-content blockquote{border-right:3px solid var(--gold);padding:12px 16px;background:var(--gold-subtle);border-radius:0 var(--rss) var(--rss) 0;margin:16px 0;font-style:italic;color:var(--text2)}
.post-content ul,.post-content ol{padding-right:20px;margin-bottom:16px}
.post-content li{margin-bottom:6px;line-height:1.8}
.post-content a{color:var(--gold-dark);font-weight:600;text-decoration:underline;text-underline-offset:3px}
.post-content code{background:var(--card-el);padding:2px 6px;border-radius:4px;font-size:.88rem}
.post-content pre{background:var(--card-el);padding:16px;border-radius:var(--rss);overflow-x:auto;margin:16px 0}
.post-content pre code{background:none;padding:0}
.post-content iframe{width:100%;border-radius:var(--rss);margin:16px 0;border:none;aspect-ratio:16/9}
.post-content hr{border:none;border-top:1px solid var(--border);margin:24px 0}
.post-content strong{font-weight:700;color:var(--text)}

/* Related */
.related-section{display:block;width:100%;box-sizing:border-box;margin:10px 0 0;padding-top:24px;border-top:1px solid var(--border)}
.related-section h3{font-size:1rem;font-weight:700;margin-bottom:16px;color:var(--gold-dark)}
.related-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.post-list{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.post-card{background:var(--card);border:1px solid var(--border-gold);border-radius:var(--r);overflow:hidden;transition:transform .2s;box-shadow:var(--shadow-sm);text-decoration:none;color:inherit;display:flex;flex-direction:column}
.post-card:active{transform:scale(.98)}
.post-cover{width:100%;height:120px;object-fit:cover;background:var(--card-el);display:block}.post-cover.miss{display:flex;align-items:center;justify-content:center;font-size:2rem;color:var(--gold-dark);line-height:1}
.post-body{padding:10px 12px 6px;flex:1;display:flex;flex-direction:column}
.post-title{font-size:.88rem;font-weight:700;margin-bottom:6px;line-height:1.5}
.post-excerpt{font-size:.7rem;color:var(--text3);line-height:1.4;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;margin:6px 0}
.post-meta{font-size:.7rem;color:var(--text3);margin-top:auto;padding-top:6px}
.related-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
@media(max-width:400px){.related-grid{grid-template-columns:1fr}}
.prod-desc-full blockquote,.post-content blockquote{margin:12px 0;padding:10px 14px;border-right:4px solid var(--gold);background:var(--card-el);border-radius:var(--rs);color:var(--text2)}
.related-card:active{transform:scale(.98)}
.related-cover{width:100%;height:120px;object-fit:cover;background:var(--card-el);display:block}
.related-cover.miss{display:flex;align-items:center;justify-content:center;font-size:2rem;color:var(--gold-dark);height:120px;background:var(--card-el)}
.related-info{padding:10px 12px 6px;flex:1;display:flex;flex-direction:column}
.related-cat{border:0;min-height:24px;line-height:17px;display:inline-flex;align-items:center;justify-content:center;padding:2px 8px;border-radius:10px;font-size:.64rem;font-weight:700;margin-bottom:8px;align-self:flex-start}.related-info h4{font-size:.88rem;font-weight:700;line-height:1.5;margin-bottom:6px}.related-excerpt{font-size:.7rem;color:var(--text3);line-height:1.4;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;margin:6px 0}.related-info span{font-size:.7rem;color:var(--text3)}.related-meta{display:flex;align-items:center;gap:10px;font-size:.7rem;color:var(--text3);margin-top:auto;padding-top:6px}
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
  <?php if (!empty($post['cover_image'])): ?>
    <img src="<?= htmlspecialchars($post['cover_image'], ENT_QUOTES, 'UTF-8') ?>" alt="" class="post-cover-full" loading="lazy">
  <?php endif; ?>

  <div class="post-hdr">
    <?php if ($post['cat_name']): ?>
      <a href="blog.php?cat=<?= urlencode($post['cat_slug']) ?>" class="post-cat-badge"><?= htmlspecialchars($post['cat_name'], ENT_QUOTES, 'UTF-8') ?></a>
    <?php endif; ?>
    <h1 class="post-title-full"><?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?></h1>
    <div class="post-meta-full" style="display:grid;grid-template-columns:1fr auto 1fr;align-items:center;gap:10px">
      <span style="justify-self:start">📅 <?= jdate_fa($post['created_at']) ?></span>
      <span style="justify-self:center<?= $blog_views_on ? '' : ';visibility:hidden' ?>">👁 <?= pn($post['views']) ?> بازدید</span>
      <?php if ($post_rating): ?><span style="justify-self:end;font-weight:700;color:var(--gold-dark)">★ <?= sitak_rating_text($post_rating['avg']) ?> <small style="font-weight:500;color:var(--text3)">(<?= pn(number_format($post_rating['count'])) ?>)</small></span><?php else: ?><span></span><?php endif; ?>
    </div>
    <?php if (!empty($tags)): ?>
      <div class="post-tags-full">
        <?php foreach ($tags as $t): ?>
          <a href="blog.php?tag=<?= rawurlencode($t['slug']) ?>" class="post-tag-full" style="text-decoration:none">#<?= htmlspecialchars($t['name'], ENT_QUOTES, 'UTF-8') ?></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="post-content"><?= $post['body'] /* امنیت: محتوا توسط ادمین نوشته شده، sanitize شده در هنگام ذخیره */ ?></div>

  <?php if (!empty($related)): ?>
  <?php $related_ratings = getSetting($conn, 'show_post_ratings', '1') === '1' ? sitak_post_ratings($conn, array_column($related, 'id')) : []; ?>
  <section class="related-section">
    <h3>📎 مقالات مرتبط</h3>
    <div class="related-grid">
      <?php foreach ($related as $r): ?>
      <a href="post.php?slug=<?= htmlspecialchars($r['slug'], ENT_QUOTES, 'UTF-8') ?>" class="post-card">
        <?php if (!empty($r['cover_image'])): ?>
          <img src="<?= htmlspecialchars($r['cover_image'], ENT_QUOTES, 'UTF-8') ?>" alt="" class="post-cover" loading="lazy">
        <?php else: ?>
          <div class="post-cover miss">📝</div>
        <?php endif; ?>
        <div class="post-body">
          <h3 class="post-title"><?= htmlspecialchars($r['title'], ENT_QUOTES, 'UTF-8') ?></h3>
          <?php if (!empty($r['excerpt'])): ?>
            <p class="post-excerpt"><?= htmlspecialchars($r['excerpt'], ENT_QUOTES, 'UTF-8') ?></p>
          <?php endif; ?>
          <div class="post-meta" style="display:grid;grid-template-columns:1fr auto 1fr;align-items:center;gap:10px">
            <span style="justify-self:start">📅 <?= jdate_fa($r['created_at']) ?></span>
            <span style="justify-self:center<?= $blog_views_on ? '' : ';visibility:hidden' ?>">👁 <?= pn($r['views']) ?></span>
            <?php if (isset($related_ratings[(int)$r['id']])): ?><span style="justify-self:end;font-weight:700;color:var(--gold-dark)">★ <?= sitak_rating_text($related_ratings[(int)$r['id']]['avg']) ?></span><?php else: ?><span></span><?php endif; ?>
          </div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>
  </main>
  
  <!-- Bottom Navigation -->
  <?php $comment_type='post';$comment_target_id=$post['id'];include 'comments.php'; ?>
<div class="page-bottom-spacer" aria-hidden="true"></div>
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
    <a href="loyalty.php">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
      <span>وفاداری</span>
    </a>
    <?php endif; ?>
    <?php if (($blog_enabled ?? '1') === '1'): ?>
    <a href="blog.php" class="active">
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
  function pn(n){return String(n).replace(/\d/g,function(d){return '۰۱۲۳۴۵۶۷۸۹'[d]})}
  document.querySelectorAll('.bottom-nav a').forEach(function(a){
    if(a.pathname===window.location.pathname){a.classList.add('active')}else{a.classList.remove('active')}
  });
  </script>
  <?php include 'cart_sheet.php'; ?>
</body>
  <script>
  /* openSheet/closeSheet/refreshCaptcha/doRegister — در auth_sheet.php */
  </script>
  </html>
