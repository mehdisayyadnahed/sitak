<?php
$admin_self = $admin_self ?? 'admin-stats.php';
require 'admin_common.php';
if (!$is_authenticated) { admin_login_screen($login_error ?? null); exit; }
/* ── تعیین بازه زمانی ── */
$range = $_GET['range'] ?? 'daily';
if (!in_array($range, ['daily','weekly','monthly','yearly','custom'])) { $range = 'daily'; }
$f = jalali_input_to_greg($_GET['from'] ?? '');
$t = jalali_input_to_greg($_GET['to'] ?? '');
$to = date('Y-m-d 23:59:59');
switch ($range) {
    case 'daily':   $from = date('Y-m-d 00:00:00'); break;
    case 'weekly':  $from = date('Y-m-d 00:00:00', strtotime('-6 days')); break;
    case 'monthly': $from = date('Y-m-01 00:00:00'); break;
    case 'yearly':  $from = date('Y-01-01 00:00:00'); break;
    default:
        $from = $f !== '' ? $f.' 00:00:00' : date('Y-m-d 00:00:00', strtotime('-29 days'));
        $to   = $t !== '' ? $t.' 23:59:59' : date('Y-m-d 23:59:59');
}
$d_from = substr($from, 0, 10);
$d_to   = substr($to, 0, 10);
$range_fa = ['daily'=>'روزانه','weekly'=>'هفتگی','monthly'=>'ماهانه','yearly'=>'سالانه','custom'=>'بازه انتخابی'];
$page_labels = ['home'=>'خانه','shop'=>'فروشگاه','blog'=>'وبلاگ','loyalty'=>'وفاداری','chance'=>'شانس','hafez'=>'فال حافظ'];

/* ── KPI: سفارش‌ها بر اساس وضعیت ── */
$st = $conn->prepare("SELECT status, COUNT(*) c, COALESCE(SUM(total_price),0) s FROM orders WHERE created_at BETWEEN ? AND ? GROUP BY status");
$st->bind_param('ss', $from, $to); $st->execute();
$ord = ['pending'=>['c'=>0,'s'=>0],'confirmed'=>['c'=>0,'s'=>0],'shipped'=>['c'=>0,'s'=>0],'delivered'=>['c'=>0,'s'=>0],'cancelled'=>['c'=>0,'s'=>0]];
foreach ($st->get_result() as $r) { $ord[$r['status']] = ['c'=>(int)$r['c'],'s'=>(int)$r['s']]; }
$paid_c = $ord['confirmed']['c'] + $ord['shipped']['c'] + $ord['delivered']['c'];
$paid_s = $ord['confirmed']['s'] + $ord['shipped']['s'] + $ord['delivered']['s'];
$all_c  = $paid_c + $ord['pending']['c'] + $ord['cancelled']['c'];
$reg_stmt = $conn->prepare("SELECT COUNT(*) AS c FROM users WHERE created_at BETWEEN ? AND ?");
$reg_stmt->bind_param('ss', $from, $to); $reg_stmt->execute();
$reg_c = (int)$reg_stmt->get_result()->fetch_assoc()['c'];

/* ── نرخ تبدیل: کاربران ثبت‌نامی‌شده در بازه که حداقل یک سفارش دارند ── */
$conv_stmt = $conn->prepare("SELECT COUNT(DISTINCT u.phone) AS c FROM users u JOIN orders o ON o.user_phone = u.phone WHERE u.created_at BETWEEN ? AND ?");
$conv_stmt->bind_param('ss', $from, $to); $conv_stmt->execute();
$conv_c = (int)$conv_stmt->get_result()->fetch_assoc()['c'];
$conv_rate = $reg_c > 0 ? round($conv_c * 100 / $reg_c) : 0;

/* ── سفارش‌های تحویل در محل و عادی (بر اساس محصولات سفارش؛ لغوشده‌ها حذف) ── */
$onsite_ids = [];
$lr = $conn->query("SELECT id FROM products WHERE onsite_product = 1");
while ($lrow = $lr->fetch_assoc()) $onsite_ids[(int)$lrow['id']] = true;
$onsite = ['c' => 0, 's' => 0]; $normal = ['c' => 0, 's' => 0];
$ord_stmt = $conn->prepare("SELECT items, total_price, status FROM orders WHERE created_at BETWEEN ? AND ? AND status <> 'cancelled'");
$ord_stmt->bind_param('ss', $from, $to); $ord_stmt->execute();
$ores = $ord_stmt->get_result();
while ($orow = $ores->fetch_assoc()) {
    $oitems = json_decode((string)$orow['items'], true) ?: [];
    $is_onsite = false;
    foreach ($oitems as $oi) { if (isset($onsite_ids[(int)($oi['product_id'] ?? 0)])) { $is_onsite = true; break; } }
    $bucket = $is_onsite ? 'onsite' : 'normal';
    if ($is_onsite) { $onsite['c']++; $onsite['s'] += (int)$orow['total_price']; }
    else { $normal['c']++; $normal['s'] += (int)$orow['total_price']; }
}

/* ── نرخ تحویل جایزه‌های واقعی در بازه ── */
$claim_stmt = $conn->prepare("SELECT COUNT(*) AS t, SUM(claimed = 1) AS k FROM winners WHERE is_fake = 0 AND won_at BETWEEN ? AND ?");
$claim_stmt->bind_param('ss', $from, $to); $claim_stmt->execute();
$claim_row = $claim_stmt->get_result()->fetch_assoc();
$win_total = (int)$claim_row['t']; $win_claimed = (int)$claim_row['k'];
$claim_rate = $win_total > 0 ? round($win_claimed * 100 / $win_total) : 0;
$avg_basket = $paid_c > 0 ? (int)round($paid_s / $paid_c) : 0;

/* ── آیتم‌های سفارش‌های پرداخت‌شده: توزیع اندازه سبد + فروش هر محصول ── */
$psales = []; $dist = [];
$st = $conn->prepare("SELECT items FROM orders WHERE status IN ('confirmed','shipped','delivered') AND created_at BETWEEN ? AND ?");
$st->bind_param('ss', $from, $to); $st->execute();
foreach ($st->get_result() as $r) {
    $items = json_decode($r['items'], true);
    $qty = 0;
    if (is_array($items)) foreach ($items as $it) {
        $q = max(1, intval($it['qty'] ?? 1));
        $qty += $q;
        $key = (intval($it['product_id'] ?? 0) > 0) ? 'p'.$it['product_id'] : 'n'.($it['name'] ?? '');
        if (!isset($psales[$key])) { $psales[$key] = ['name' => $it['name'] ?? 'نامشخص', 'qty' => 0, 'sum' => 0]; }
        $psales[$key]['qty'] += $q;
        $psales[$key]['sum'] += $q * intval($it['price'] ?? 0);
    }
    if ($qty > 0) { $dist[$qty] = ($dist[$qty] ?? 0) + 1; }
}
ksort($dist);
usort($psales, fn($a, $b) => $b['sum'] <=> $a['sum']);

/* ── جدول‌ها ── */
function stat_table(array $headers, array $rows) {
    echo '<div class="tbl-wrap" style="margin-top:12px"><table><thead><tr>';
    foreach ($headers as $h) { echo '<th onclick="sortTable(this)">'.$h.'<span class="sarw">↕</span></th>'; }
    echo '</tr></thead><tbody>';
    if (!$rows) { echo '<tr><td colspan="'.count($headers).'" style="color:var(--text3)">داده‌ای در این بازه ثبت نشده است</td></tr>'; }
    foreach ($rows as $r) {
        echo '<tr>';
        foreach ($r as $c) { echo is_array($c) ? '<td data-v="'.$c[1].'">'.$c[0].'</td>' : '<td>'.$c.'</td>'; }
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}
function money_cell($n) { return [number_format((float)$n).' تومان', (int)$n]; }
function num_cell($n) { return [pn($n), (int)$n]; }

/* کاربران برتر (تعداد و مبلغ خرید) */
$st = $conn->prepare("SELECT o.user_phone, MAX(COALESCE(u.name,'مهمان')) n, COUNT(*) c, SUM(o.total_price) s
    FROM orders o LEFT JOIN users u ON u.phone = o.user_phone
    WHERE o.status IN ('confirmed','shipped','delivered') AND o.created_at BETWEEN ? AND ?
    GROUP BY o.user_phone ORDER BY s DESC LIMIT 50");
$st->bind_param('ss', $from, $to); $st->execute();
$top_users = [];
foreach ($st->get_result() as $r) { $top_users[] = [htmlspecialchars($r['n'], ENT_QUOTES, 'UTF-8'), num_cell($r['c']), money_cell($r['s'])]; }

/* رفرال‌ها */
$st = $conn->prepare("SELECT r.referral_code ref, MAX(COALESCE(u.name,'—')) n, COUNT(*) c
    FROM users r LEFT JOIN users u ON u.phone = r.referral_code
    WHERE r.referral_code IS NOT NULL AND r.referral_code <> '' AND r.created_at BETWEEN ? AND ?
    GROUP BY r.referral_code ORDER BY c DESC LIMIT 50");
$st->bind_param('ss', $from, $to); $st->execute();
$ref_rows = [];
foreach ($st->get_result() as $r) { $ref_rows[] = ['<span dir="ltr">'.htmlspecialchars($r['ref'], ENT_QUOTES, 'UTF-8').'</span>', htmlspecialchars($r['n'], ENT_QUOTES, 'UTF-8'), num_cell($r['c'])]; }

/* بردهای تاس کاربران */
$st = $conn->prepare("SELECT phone, MAX(COALESCE(name,'مهمان')) n, COUNT(*) c FROM winners
    WHERE is_fake = 0 AND won_at BETWEEN ? AND ? GROUP BY phone ORDER BY c DESC LIMIT 50");
$st->bind_param('ss', $from, $to); $st->execute();
$win_users = [];
foreach ($st->get_result() as $r) { $win_users[] = [htmlspecialchars($r['n'], ENT_QUOTES, 'UTF-8'), '<span dir="ltr">'.htmlspecialchars($r['phone'], ENT_QUOTES, 'UTF-8').'</span>', num_cell($r['c'])]; }

/* بردهای تاس هر محصول (جایزه) */
$st = $conn->prepare("SELECT prize_name, COUNT(*) c FROM winners
    WHERE is_fake = 0 AND won_at BETWEEN ? AND ? GROUP BY prize_name ORDER BY c DESC LIMIT 50");
$st->bind_param('ss', $from, $to); $st->execute();
$win_prizes = [];
foreach ($st->get_result() as $r) { $win_prizes[] = [htmlspecialchars($r['prize_name'], ENT_QUOTES, 'UTF-8'), num_cell($r['c'])]; }

/* بازدید صفحات */
$st = $conn->prepare("SELECT page, SUM(views) v FROM page_views WHERE page NOT LIKE '%:%' AND view_date BETWEEN ? AND ? GROUP BY page ORDER BY v DESC");
$st->bind_param('ss', $d_from, $d_to); $st->execute();
$page_rows = [];
foreach ($st->get_result() as $r) { $page_rows[] = [htmlspecialchars($page_labels[$r['page']] ?? $r['page'], ENT_QUOTES, 'UTF-8'), num_cell($r['v'])]; }

/* بازدید محصولات */
$st = $conn->prepare("SELECT pv.page, COALESCE(p.name, CONCAT('محصول حذف‌شده (#', SUBSTRING(pv.page, 9), ')')) n, SUM(pv.views) v
    FROM page_views pv LEFT JOIN products p ON p.id = CAST(SUBSTRING(pv.page, 9) AS UNSIGNED)
    WHERE pv.page LIKE 'product:%' AND pv.view_date BETWEEN ? AND ? GROUP BY pv.page ORDER BY v DESC LIMIT 50");
$st->bind_param('ss', $d_from, $d_to); $st->execute();
$prod_views = [];
foreach ($st->get_result() as $r) { $prod_views[] = [htmlspecialchars($r['n'], ENT_QUOTES, 'UTF-8'), num_cell($r['v'])]; }

/* بازدید مطالب */
$st = $conn->prepare("SELECT pv.page, COALESCE(p.title, CONCAT('مطلب حذف‌شده (#', SUBSTRING(pv.page, 6), ')')) n, SUM(pv.views) v
    FROM page_views pv LEFT JOIN posts p ON p.id = CAST(SUBSTRING(pv.page, 6) AS UNSIGNED)
    WHERE pv.page LIKE 'post:%' AND pv.view_date BETWEEN ? AND ? GROUP BY pv.page ORDER BY v DESC LIMIT 50");
$st->bind_param('ss', $d_from, $d_to); $st->execute();
$post_views = [];
foreach ($st->get_result() as $r) { $post_views[] = [htmlspecialchars($r['n'], ENT_QUOTES, 'UTF-8'), num_cell($r['v'])]; }

/* ── خروجی کامل CSV از همه داده‌های همین بازه ── */
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $csv_clean = static function ($value) {
        if (is_array($value)) $value = $value[0];
        $value = html_entity_decode(strip_tags((string)$value), ENT_QUOTES, 'UTF-8');
        return preg_replace('/[\x00-\x1F\x7F]/u', ' ', $value);
    };
    $csv_rows = [];
    $csv_rows[] = ['بخش', 'ستون', 'مقدار'];
    $csv_rows[] = ['اطلاعات گزارش', 'بازه', $range_fa[$range]];
    $csv_rows[] = ['اطلاعات گزارش', 'از', $d_from];
    $csv_rows[] = ['اطلاعات گزارش', 'تا', $d_to];
    foreach ([
        ['مجموع مبلغ فروش', $paid_s], ['سبدهای پرداخت‌شده', $paid_c], ['کل سبدهای ثبت‌شده', $all_c],
        ['در انتظار تأیید', $ord['pending']['c']], ['لغو شده', $ord['cancelled']['c']], ['میانگین مبلغ سبد', $avg_basket], ['کاربران ثبت‌نامی', $reg_c], ['نرخ تبدیل ثبت‌نام به سفارش (٪)', $conv_rate], ['سفارش‌های تحویل در محل', $onsite['c']], ['مبلغ سفارش‌های تحویل در محل', $onsite['s']], ['سفارش‌های ارسال عادی', $normal['c']], ['مبلغ سفارش‌های ارسال عادی', $normal['s']], ['نرخ تحویل جایزه (٪)', $claim_rate]
    ] as $kpi) $csv_rows[] = ['شاخص‌های اصلی', $kpi[0], $kpi[1]];
    $csv_add = static function (&$out, $section, $headers, $rows) use ($csv_clean) {
        foreach ($rows as $row) {
            $vals = [];
            foreach ($row as $cell) $vals[] = $csv_clean($cell);
            foreach ($headers as $i => $header) $out[] = [$section, $header, $vals[$i] ?? ''];
        }
    };
    $csv_add($csv_rows, 'توزیع اندازه سبد', ['اندازه سبد', 'تعداد سبد'], array_map(static function($size, $cnt) { return [$size, $cnt]; }, array_keys($dist), array_values($dist)));
    $csv_add($csv_rows, 'فروش هر محصول', ['محصول', 'تعداد فروش', 'مجموع مبلغ'], array_map(static function($p) { return [$p['name'], $p['qty'], $p['sum']]; }, $psales));
    $csv_add($csv_rows, 'کاربران برتر', ['کاربر', 'تعداد خرید', 'مجموع مبلغ خرید'], $top_users);
    $csv_add($csv_rows, 'رفرال‌ها', ['معرف', 'نام', 'تعداد رفرال'], $ref_rows);
    $csv_add($csv_rows, 'بردهای کاربران', ['کاربر', 'شماره', 'تعداد برد'], $win_users);
    $csv_add($csv_rows, 'جوایز برنده‌شده', ['محصول/جایزه', 'تعداد برد'], $win_prizes);
    $csv_add($csv_rows, 'بازدید صفحات', ['صفحه', 'تعداد بازدید'], $page_rows);
    $csv_add($csv_rows, 'بازدید محصولات', ['محصول', 'تعداد بازدید'], $prod_views);
    $csv_add($csv_rows, 'بازدید مطالب', ['مطلب', 'تعداد بازدید'], $post_views);
    $filename = 'sitak-stats-' . date('Y-m-d-His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    header('Pragma: no-cache');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    foreach ($csv_rows as $row) fputcsv($out, $row);
    fclose($out);
    exit;
}

admin_page_start('آمار فروش');
admin_header();
?>
<style>
.kpi-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin:16px 0}
.kpi{background:var(--card);border:1px solid var(--border-gold);border-radius:var(--rs);padding:14px;box-shadow:var(--shadow-sm)}
.kpi .t{font-size:.7rem;color:var(--text2);margin-bottom:6px}
.kpi .v{font-size:1.05rem;font-weight:900;color:var(--gold-dark)}
.range-bar{display:flex;gap:8px;flex-wrap:nowrap;overflow-x:auto;align-items:center;margin:14px 0;padding-bottom:2px;-webkit-overflow-scrolling:touch}
.rpill{padding:9px 16px;border-radius:999px;background:var(--card);border:1px solid var(--border);font-size:.78rem;font-weight:600;color:var(--text2);text-decoration:none;cursor:pointer;transition:all .2s;flex-shrink:0;white-space:nowrap}
.rpill.on{background:linear-gradient(135deg,var(--gold),var(--gold-dark));color:#fff;border-color:transparent}
.range-custom{display:flex;gap:6px;align-items:center;flex-wrap:wrap;margin:0 0 6px}
.range-custom .jdate-in{padding:8px 9px;background:var(--bg);border:1.5px solid var(--border);border-radius:var(--rss);color:var(--text);font-family:var(--font);font-size:.75rem;width:104px;text-align:center;direction:ltr}
.range-custom .jdate-in:focus{border-color:var(--gold)}
.sarw{opacity:.35;font-size:.62rem;margin-right:4px}
.arw{color:var(--gold-dark);font-size:.68rem;margin-right:4px}
.hint{font-size:.68rem;color:var(--text3);margin-bottom:14px}
</style>

<div class="container">

  <div class="range-bar">
    <?php foreach ($range_fa as $k => $lbl): ?>
    <a class="rpill<?= $range === $k ? ' on' : '' ?>" href="<?= admin_url('stats') ?>?range=<?= $k ?>"><?= $lbl ?></a>
    <?php endforeach; ?>
  </div>
  <?php if ($range === 'custom'): ?>
  <form method="get" class="range-custom">
    <input type="hidden" name="range" value="custom">
    <input type="text" class="jdate-in" name="from" dir="ltr" inputmode="numeric" placeholder="1404/07/05" value="<?= jdate_fa($from) ?>" oninput="jfmt(this)" autocomplete="off">
    <input type="text" class="jdate-in" name="to" dir="ltr" inputmode="numeric" placeholder="1404/07/05" value="<?= jdate_fa($to) ?>" oninput="jfmt(this)" autocomplete="off">
    <button class="btn-p" style="width:auto;padding:9px 16px;font-size:.75rem">اعمال</button>
  </form>
  <?php endif; ?>
  <div class="hint">بازه فعال: <?= $range_fa[$range] ?> — <?= jdate_fa($from) ?> تا <?= jdate_fa($to) ?>. تاریخ را به شکل ۱۴۰۴/۰۷/۰۵ وارد کنید.</div>

  <div class="kpi-grid">
    <div class="kpi"><div class="t">💰 مجموع مبلغ فروش</div><div class="v"><?= pn(number_format($paid_s)) ?> تومان</div></div>
    <div class="kpi"><div class="t">🛒 سبدهای ثبت و پرداخت شده</div><div class="v"><?= pn($paid_c) ?></div></div>
    <div class="kpi"><div class="t">⏳ در انتظار تأیید</div><div class="v"><?= pn($ord['pending']['c']) ?></div></div>
    <div class="kpi"><div class="t">🚫 لغو شده</div><div class="v"><?= pn($ord['cancelled']['c']) ?></div></div>
    <div class="kpi"><div class="t">📊 میانگین مبلغ هر سبد</div><div class="v"><?= pn(number_format($avg_basket)) ?> تومان</div></div>
    <div class="kpi"><div class="t">👥 کاربران ثبت‌نامی</div><div class="v"><?= pn($reg_c) ?></div></div>
    <div class="kpi"><div class="t">🔁 نرخ تبدیل ثبت‌نام به سفارش</div><div class="v"><?= pn($conv_rate) ?>٪</div><div class="t" style="margin:6px 0 0"><?= pn($conv_c) ?> از <?= pn($reg_c) ?> کاربر ثبت‌نامی‌شده</div></div>
    <div class="kpi"><div class="t">⚡ سفارش‌های تحویل در محل</div><div class="v"><?= pn($onsite['c']) ?></div><div class="t" style="margin:6px 0 0"><?= pn(number_format($onsite['s'])) ?> تومان</div></div>
    <div class="kpi"><div class="t">🏠 سفارش‌های ارسال عادی</div><div class="v"><?= pn($normal['c']) ?></div><div class="t" style="margin:6px 0 0"><?= pn(number_format($normal['s'])) ?> تومان</div></div>
    <div class="kpi"><div class="t">🎁 نرخ تحویل جایزه</div><div class="v"><?= pn($claim_rate) ?>٪</div><div class="t" style="margin:6px 0 0"><?= pn($win_claimed) ?> از <?= pn($win_total) ?> برنده</div></div>
  </div>
  <div class="hint">سبد پرداخت‌شده = سفارش‌های «تأیید شده» یا «تحویل شده». آمار بازدید از زمان راه‌اندازی این بخش شروع به ثبت می‌شود.</div>

  <div class="acc-hdr open" onclick="toggleAcc(this)"><h3>۱. توزیع اندازه سبد (تعداد آیتم در هر سبد)</h3><span class="acc-icon">−</span></div>
  <div class="acc-body open"><?php
    $rows = [];
    foreach ($dist as $size => $cnt) { $rows[] = [[pn($size).' آیتمی', $size], num_cell($cnt)]; }
    stat_table(['اندازه سبد', 'تعداد سبد'], $rows);
  ?></div>

  <div class="acc-hdr" onclick="toggleAcc(this)"><h3>۲. فروش هر محصول</h3><span class="acc-icon">+</span></div>
  <div class="acc-body"><?php
    $rows = [];
    foreach ($psales as $p) { $rows[] = [htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8'), num_cell($p['qty']), money_cell($p['sum'])]; }
    stat_table(['محصول', 'تعداد فروش', 'مجموع مبلغ'], $rows);
  ?></div>

  <div class="acc-hdr" onclick="toggleAcc(this)"><h3>۳. کاربران با بیشترین تعداد و مبلغ خرید</h3><span class="acc-icon">+</span></div>
  <div class="acc-body"><?php stat_table(['کاربر', 'تعداد خرید', 'مجموع مبلغ خرید'], $top_users); ?></div>

  <div class="acc-hdr" onclick="toggleAcc(this)"><h3>۴. تعداد رفرال‌های کاربران</h3><span class="acc-icon">+</span></div>
  <div class="acc-body"><?php stat_table(['معرف (کاربر)', 'نام', 'تعداد رفرال'], $ref_rows); ?></div>

  <div class="acc-hdr" onclick="toggleAcc(this)"><h3>۵. تعداد بُردهای تاس کاربران</h3><span class="acc-icon">+</span></div>
  <div class="acc-body"><?php stat_table(['کاربر', 'شماره', 'تعداد برد'], $win_users); ?></div>

  <div class="acc-hdr" onclick="toggleAcc(this)"><h3>۶. بُردهای تاس ثبت‌شده برای هر محصول</h3><span class="acc-icon">+</span></div>
  <div class="acc-body"><?php stat_table(['محصول (جایزه)', 'تعداد برد'], $win_prizes); ?></div>

  <div class="acc-hdr" onclick="toggleAcc(this)"><h3>۷. تعداد بازدید از هر صفحه</h3><span class="acc-icon">+</span></div>
  <div class="acc-body"><?php stat_table(['صفحه', 'تعداد بازدید'], $page_rows); ?></div>

  <div class="acc-hdr" onclick="toggleAcc(this)"><h3>۸. تعداد بازدید از هر محصول</h3><span class="acc-icon">+</span></div>
  <div class="acc-body"><?php stat_table(['محصول', 'تعداد بازدید'], $prod_views); ?></div>

  <div class="acc-hdr" onclick="toggleAcc(this)"><h3>۹. تعداد بازدید از هر مطلب</h3><span class="acc-icon">+</span></div>
  <div class="acc-body"><?php stat_table(['مطلب', 'تعداد بازدید'], $post_views); ?></div>

  <div style="margin:24px 0 10px;padding:16px;background:var(--card);border:1px solid var(--border-gold);border-radius:var(--rs);text-align:center">
    <div style="font-size:.82rem;font-weight:700;margin-bottom:6px">خروجی کامل آمار</div>
    <div style="font-size:.7rem;color:var(--text2);margin-bottom:12px">تمام شاخص‌ها و جدول‌های این بازه را برای تحلیل در Excel، نرم‌افزارهای آماری یا هوش مصنوعی دریافت کنید.</div>
    <a class="btn-p" style="display:inline-flex;width:auto;text-decoration:none;padding:10px 18px;font-size:.78rem" href="<?= admin_url('stats') ?>?range=<?= urlencode($range) ?><?= $range === 'custom' ? '&from='.urlencode($_GET['from'] ?? '').'&to='.urlencode($_GET['to'] ?? '') : '' ?>&export=csv">دانلود خروجی CSV 📥</a>
  </div>

</div>

<div class="toast" id="toast"></div>

<script>
<?php admin_common_js(); ?>
function jfmt(el){
  let d = el.value.replace(/[۰-۹]/g, c => '۰۱۲۳۴۵۶۷۸۹'.indexOf(c)).replace(/\D/g, '').slice(0, 8);
  let o = d;
  if (d.length > 4) o = d.slice(0, 4) + '/' + d.slice(4);
  if (d.length > 6) o = d.slice(0, 4) + '/' + d.slice(4, 6) + '/' + d.slice(6);
  el.value = o;
}
function sortTable(th){
  const table = th.closest('table'), tb = table.querySelector('tbody');
  const i = [...th.parentElement.children].indexOf(th);
  const dir = th.dataset.dir === 'desc' ? 'asc' : 'desc';
  th.dataset.dir = dir;
  table.querySelectorAll('th').forEach(h => { if (h !== th) { delete h.dataset.dir; const a = h.querySelector('.arw'); if (a) { a.classList.remove('arw'); a.classList.add('sarw'); a.textContent = '↕'; } } });
  let arw = th.querySelector('.arw') || th.querySelector('.sarw');
  if (!arw) { th.insertAdjacentHTML('beforeend', ' <span class="arw"></span>'); arw = th.querySelector('.arw'); }
  arw.classList.remove('sarw'); arw.classList.add('arw');
  arw.textContent = dir === 'asc' ? '▲' : '▼';
  [...tb.rows].sort((a, b) => {
    let x = a.cells[i].dataset.v ?? a.cells[i].textContent;
    let y = b.cells[i].dataset.v ?? b.cells[i].textContent;
    const nx = parseFloat(x), ny = parseFloat(y);
    if (!isNaN(nx) && !isNaN(ny)) return dir === 'asc' ? nx - ny : ny - nx;
    x = String(x); y = String(y);
    return dir === 'asc' ? x.localeCompare(y, 'fa') : y.localeCompare(x, 'fa');
  }).forEach(r => tb.appendChild(r));
}
</script>

<?php admin_footer_nav('stats'); ?>
</body>
</html>
