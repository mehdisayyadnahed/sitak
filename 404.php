<?php
http_response_code(404);
require_once __DIR__ . '/config.php';
$site_name = getSetting($conn, 'site_name', 'سای‌تک');
$custom_emoji = getSetting($conn, 'custom_emoji', '🌐');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="auto">
<head>
<?= sitak_head_meta($conn, ['title' => 'صفحه پیدا نشد']) ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<meta name="theme-color" content="#ffffff" id="metaTheme">
<script>(function(){function R(v){return v==="auto"?(matchMedia("(prefers-color-scheme: dark)").matches?"dark":"light"):v}try{var t=R(localStorage.getItem("sitak_theme")||"auto");document.documentElement.setAttribute('data-theme',t);var m=document.getElementById('metaTheme');if(m)m.setAttribute('content',t==='dark'?'#0a0a0c':'#ffffff');window.__R=R;window.__T=t;}catch(e){}})();</script>
<title>صفحه پیدا نشد | <?= htmlspecialchars($site_name ?? 'سای‌تک', ENT_QUOTES, 'UTF-8') ?></title>
<link href="assets/fonts/vazirmatn.css" rel="stylesheet">
<style>
:root,[data-theme="light"]{--bg:#f5f5f7;--card:#ffffff;--card-el:#f0eff2;--border:rgba(255,255,255,.08);--border-gold:rgba(236,181,52,.22);--text:#1d1d1f;--text2:#6e6e73;--text3:#aeaeb2;--rss:10px;--gold:#ecb534;--gold-dark:#c49625;--font:"Vazirmatn",-apple-system,BlinkMacSystemFont,sans-serif}
[data-theme="dark"]{--bg:#0a0a0c;--card:#1c1c1e;--card-el:#2c2c2e;--border:rgba(255,255,255,.08);--border-gold:rgba(236,181,52,.15);--text:#f0ece4;--text2:#9e9888;--text3:#5e5a52}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:var(--font);background:var(--bg);color:var(--text);min-height:100dvh;display:flex;align-items:center;justify-content:center;padding:20px;-webkit-font-smoothing:antialiased}
.card{background:var(--card);border:1px solid var(--border-gold);border-radius:24px;padding:40px 26px;width:100%;max-width:380px;text-align:center;box-shadow:0 4px 14px rgba(0,0,0,.06)}
.code{font-size:5rem;font-weight:900;line-height:1;background:linear-gradient(135deg,var(--gold),var(--gold-dark));-webkit-background-clip:text;background-clip:text;color:transparent;direction:ltr}
.icon{font-size:2.6rem;margin:8px 0 14px}
h1{font-size:1.1rem;font-weight:700;margin-bottom:8px}
p{font-size:.82rem;line-height:1.9;color:var(--text2);margin-bottom:22px}
a.btn{display:block;width:100%;padding:14px;background:linear-gradient(135deg,var(--gold),var(--gold-dark));color:#fff;border:none;border-radius:var(--rss);font-family:var(--font);font-size:.92rem;font-weight:700;text-decoration:none;;cursor:pointer}
a.btn:active{}
.sub{font-size:.72rem;color:var(--text3);margin-top:16px}
@media(min-width:481px){body{max-width:480px;margin:0 auto;box-shadow:0 0 0 1px var(--border),0 0 50px rgba(0,0,0,.1)}}
</style>
</head>
<body>
<div class="card">
  <div class="code">404</div>
  <div class="icon"><?= htmlspecialchars($custom_emoji, ENT_QUOTES, 'UTF-8') ?></div>
  <h1>صفحه پیدا نشد!</h1>
  <p>آدرسی که وارد کردید وجود ندارد یا تغییر کرده است.<br>اگر نشانی پنل ادمین را عوض کرده‌اید، آدرس جدید را وارد کنید.</p>
  <a class="btn" href="index.php">بازگشت به صفحه اصلی</a>
</div>
</body>
</html>
