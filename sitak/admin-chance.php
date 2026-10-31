<?php
$admin_self = $admin_self ?? 'admin-chance.php';
require 'admin_common.php';

if (!$is_authenticated) { admin_login_screen($login_error ?? null); exit; }
admin_page_start('شانس');
admin_header();

$editing_pid = intval($_GET['edit'] ?? 0);
$edit_prize = null;
if ($editing_pid > 0) {
    $er = $conn->prepare("SELECT * FROM prizes WHERE id = ?");
    $er->bind_param("i", $editing_pid);
    $er->execute();
    $edit_prize = $er->get_result()->fetch_assoc();
}
$editing_hid = intval($_GET['edith'] ?? 0);
$edit_verse = null;
if ($editing_hid > 0) {
    $er = $conn->prepare("SELECT * FROM hafez_verses WHERE id = ?");
    $er->bind_param("i", $editing_hid);
    $er->execute();
    $edit_verse = $er->get_result()->fetch_assoc();
}
$hafez_enabled = getSetting($conn, 'hafez_enabled', '1');
$hafez_home_enabled = getSetting($conn, 'hafez_home_enabled', '1');
$chance_enabled = getSetting($conn, 'chance_enabled', '1');
$dice_enabled = getSetting($conn, 'dice_enabled', '1');
$dice_home_enabled = getSetting($conn, 'dice_home_enabled', '1');
$wheel_enabled = getSetting($conn, 'wheel_enabled', '1');
$wheel_home_enabled = getSetting($conn, 'wheel_home_enabled', '1');
$wheel_build = sitak_wheel_build($conn);
$void_row = $conn->query("SELECT id, description, win_probability FROM prizes WHERE is_void = 1 LIMIT 1")->fetch_assoc() ?: ['description' => 'پوچ', 'win_probability' => 0];
?>
<div class="container">

  <!-- ۱. فال حافظ -->
  <div class="acc-hdr" onclick="toggleAcc(this)"><h3>۱. ابیات فال حافظ</h3><span class="acc-icon">+</span></div>
  <div class="acc-body">
    <!-- مدیریت ابیات -->
    <form method="post" id="hafezForm" style="margin-bottom:16px">
      <input type="hidden" name="hafez_id" id="hafezId" value="<?= (int)($edit_verse['id'] ?? 0) ?>">
      <?php
        $ev_m1 = trim($edit_verse['verse_m1'] ?? '');
        $ev_m2 = trim($edit_verse['verse_m2'] ?? '');
      ?>
      <div class="fg">
        <label>مصرع اول</label>
        <input type="text" class="fi" name="hafez_m1" id="hafezM1" value="<?= htmlspecialchars($ev_m1, ENT_QUOTES, 'UTF-8') ?>" required placeholder="الا یا ایها الساقی ادر کأسا و ناولها">
      </div>
      <div class="fg">
        <label>مصرع دوم</label>
        <input type="text" class="fi" name="hafez_m2" id="hafezM2" value="<?= htmlspecialchars($ev_m2, ENT_QUOTES, 'UTF-8') ?>" required placeholder="که عشق آسان نمود اول ولی افتاد مشکل‌ها">
      </div>
      <div class="fg">
        <label>تفسیر کوتاه</label>
        <textarea class="fi" name="hafez_meaning" id="hafezMeaning" rows="3" required placeholder="تفسیر کوتاه بیت..."><?= htmlspecialchars($edit_verse['meaning_short'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
      </div>
      <div class="toggle-row" style="margin-bottom:12px">
        <label class="toggle"><input type="checkbox" name="hafez_active" id="hafezActive" value="1" <?= (!$edit_verse || !empty($edit_verse['is_active'])) ? 'checked' : '' ?>><span class="slider"></span></label>
        <span class="toggle-label">این بیت در فال نمایش داده شود</span>
      </div>
      <div class="fg"><button type="submit" name="save_hafez" id="hafezSubmitBtn" class="btn-p"><?= $edit_verse ? 'بروزرسانی بیت' : 'افزودن بیت' ?></button></div>
      <div class="fg"><button type="button" id="hafezCancelBtn" class="btn-sm" style="display:<?= $edit_verse ? '' : 'none' ?>;background:var(--card-el);color:var(--text2);padding:10px 16px;border-radius:var(--rss);font-size:.82rem;font-weight:700;cursor:pointer;width:100%;justify-content:center;;" onclick="cancelHafezEdit()">انصراف از ویرایش</button></div>
    </form>

    <hr style="border:0;border-top:1px solid var(--border);margin:16px 0">
    <!-- مدیریت ابیات -->
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
      <span style="font-size:.82rem;font-weight:700;color:var(--gold-dark)">ابیات فال حافظ</span>
      <span style="font-size:.72rem;color:var(--text3)">تعداد: <?= (int)$conn->query("SELECT COUNT(*) c FROM hafez_verses")->fetch_assoc()['c'] ?></span>
    </div>

    <div class="tbl-wrap">
    <table><thead><tr><th>متن بیت</th><th style="text-align:center">وضعیت</th><th style="text-align:center">عملیات</th></tr></thead><tbody>
    <?php
    [$ch_pp,$ch_pg]=admin_list_page('ch'); $ch_total=(int)$conn->query("SELECT COUNT(*) AS c FROM hafez_verses")->fetch_assoc()["c"]; $ch_pg=min($ch_pg,max(1,(int)ceil($ch_total/$ch_pp))); $ch_off=($ch_pg-1)*$ch_pp; $hvr = $conn->query("SELECT * FROM hafez_verses ORDER BY id ASC LIMIT $ch_off,$ch_pp");
    if ($hvr && $hvr->num_rows > 0):
      while ($hv = $hvr->fetch_assoc()):
        $vt = verse_display_text($hv);
        $vt_short = mb_strlen($vt, 'UTF-8') > 80 ? mb_substr($vt, 0, 80, 'UTF-8') . '...' : $vt;
    ?>
    <tr>
      <td style="font-size:.78rem;line-height:1.6"><?= htmlspecialchars($vt_short, ENT_QUOTES, 'UTF-8') ?></td>
      <td style="text-align:center;vertical-align:middle;font-size:1rem"><?= !empty($hv['is_active']) ? '✅' : '⛔' ?></td>
      <td style="white-space:nowrap;text-align:center;vertical-align:middle">
        <button type="button" class="btn-sm btn-blue" style="font-size:.68rem;cursor:pointer" onclick="startHafezEdit(this)" data-id="<?= (int)$hv['id'] ?>" data-active="<?= !empty($hv['is_active']) ? '1' : '0' ?>" data-m1="<?= htmlspecialchars($hv['verse_m1'] ?? '', ENT_QUOTES, 'UTF-8') ?>" data-m2="<?= htmlspecialchars($hv['verse_m2'] ?? '', ENT_QUOTES, 'UTF-8') ?>" data-meaning="<?= htmlspecialchars($hv['meaning_short'], ENT_QUOTES, 'UTF-8') ?>">✏️</button>
        <a href="<?= admin_url('chance') ?>?toggle_hafez_active=<?= (int)$hv['id'] ?>&csrf=<?= sitak_csrf_token() ?>" class="btn-sm act-toggle" style="margin-left:4px" title="<?= !empty($hv['is_active']) ? 'غیرفعال کردن' : 'فعال کردن' ?>"><?= !empty($hv['is_active']) ? '⛔' : '✅' ?></a>
        <a href="<?= admin_url('chance') ?>?delete_hafez=<?= (int)$hv['id'] ?>&csrf=<?= sitak_csrf_token() ?>" class="btn-sm btn-red" style="font-size:.68rem" onclick="return adminConfirmEl(this,'این بیت حذف شود؟')">🗑️</a>
      </td>
    </tr>
    <?php endwhile; else: ?>
    <tr><td colspan="3" style="text-align:center;color:var(--text3);padding:16px">بیتی ثبت نشده است.</td></tr>
    <?php endif; ?>
    </tbody></table>
    </div>
    <?= admin_pager('ch', null, $ch_total, $ch_pp, $ch_pg) ?>
  </div>

  <!-- ۲. جوایز -->
  <div class="acc-hdr" onclick="toggleAcc(this)"><h3>۲. جوایز شانس</h3><span class="acc-icon">+</span></div>
  <div class="acc-body">

    <?php $sel_prize_hex = strtolower((string)($edit_prize['color'] ?? ''));
$prize_color_names = ['#e8a33d'=>'کهربایی','#d9534f'=>'قرمز','#5b7fd1'=>'آبی','#3aa88f'=>'سبز دودی','#9b6bd0'=>'بنفش','#e0729a'=>'صورتی','#4fa3c7'=>'آبی روشن','#f0b35a'=>'زرد نارنجی'];
if (!isset($prize_color_names[$sel_prize_hex])) $sel_prize_hex = '';
$sel_prize_name = $sel_prize_hex !== '' ? $prize_color_names[$sel_prize_hex] : 'خودکار (ترتیبی)';
?>
    <form method="post" id="prizeForm" style="margin-bottom:16px" onsubmit="return validatePrizeTypes(event)">
      <input type="hidden" name="prize_id" id="prizeId" value="<?= (int)($edit_prize['id'] ?? 0) ?>">
      <div class="fg">
        <label>نام و توضیحات جایزه</label>
        <textarea class="fi" name="desc" id="prizeDesc" rows="3" required placeholder="مثلاً: تخفیف ۲۰٪ از پیج اینستاگرام..."><?= htmlspecialchars($edit_prize['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
      </div>
      <div class="fg">
          <label>لینک (اختیاری)</label>
          <input type="url" class="fi" name="link" id="prizeLink" value="<?= htmlspecialchars($edit_prize['affiliate_link'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="https://...">
        </div>
        <div class="fg">
          <label>کد تخفیف (اختیاری)</label>
          <input type="text" class="fi" name="discount" id="prizeDiscount" value="<?= htmlspecialchars($edit_prize['discount_code'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="مثلاً: SALE20">
        </div>
        <div class="fg">
      </div>
      <div class="fg">
        <label>درصد شانس برد</label>
        <div class="stat-row"><button type="button" class="btn-h minus" onclick="stepNum('prizeProbability',-0.5)">−</button><input type="text" inputmode="numeric" class="stat-input" name="probability" id="prizeProbability" value="<?= $edit_prize ? rtrim(rtrim((string)$edit_prize['win_probability'], '0'), '.') : '0' ?>" min="0" max="100" step="0.01"><button type="button" class="btn-h plus" onclick="stepNum('prizeProbability',0.5)">+</button></div>
      </div>
      <div class="fg">
        <label>تعداد جایزه (خالی یعنی بدون محدودیت)</label>
        <div class="stat-row"><button type="button" class="btn-h minus" onclick="stepNum('prizeQtyLimit',-1)">−</button><input type="text" inputmode="numeric" class="stat-input" name="qty_limit" id="prizeQtyLimit" min="0" value="<?= ($edit_prize && $edit_prize['qty_limit'] !== null) ? (int)$edit_prize['qty_limit'] : '' ?>"><button type="button" class="btn-h plus" onclick="stepNum('prizeQtyLimit',1)">+</button></div>
        <small style="font-size:.7rem;color:var(--text3)">خالی = نامحدود، ۰ = تمام‌شده. با هر برد واقعی، یکی از این تعداد کم می‌شه و جایزه ساختگی نمی‌گیره.</small>
      </div>
      <div class="fg">
        <label>مهلت جایزه (روز، خالی یعنی بدون محدودیت)</label>
        <div class="stat-row"><button type="button" class="btn-h minus" onclick="stepNum('prizeTimeDays',-1)">−</button><input type="text" inputmode="numeric" class="stat-input" name="time_days" id="prizeTimeDays" min="0" value="<?= ($edit_prize && $edit_prize['time_limit_days'] !== null) ? (int)$edit_prize['time_limit_days'] : '' ?>"><button type="button" class="btn-h plus" onclick="stepNum('prizeTimeDays',1)">+</button></div>
        <small style="font-size:.7rem;color:var(--text3)">خالی = نامحدود، ۰ = همین الآن تمام‌شده. از لحظه‌ی ثبت یا تغییر تعداد روز شروع می‌شه؛ ویرایش بقیه‌ی مشخصات شمارنده را ریست نمی‌کنه.</small>
      </div>
      <div class="fg" id="prizeColorWrap">
        <label>رنگ قاچ در گردونه</label>
        <input type="hidden" name="color" id="prizeColor" value="<?= htmlspecialchars($sel_prize_hex, ENT_QUOTES, 'UTF-8') ?>">
        <div class="modal-acc-hdr" onclick="toggleModalAcc(this)"><span style="display:flex;align-items:center;gap:8px"><i id="prizeColorSw" style="width:14px;height:14px;border-radius:4px;flex-shrink:0;border:1px solid var(--border);background:<?= $sel_prize_hex !== '' ? htmlspecialchars($sel_prize_hex, ENT_QUOTES, 'UTF-8') : 'transparent' ?>"></i><span id="prizeColorTxt" style="color:<?= $sel_prize_hex !== '' ? htmlspecialchars($sel_prize_hex, ENT_QUOTES, 'UTF-8') : 'var(--gold-dark)' ?>"><?= htmlspecialchars($sel_prize_name, ENT_QUOTES, 'UTF-8') ?></span></span><span class="modal-acc-icon">+</span></div>
        <div class="modal-acc-body">
          <div class="pc-opt" data-hex="" data-name="خودکار (ترتیبی)" onclick="pickPrizeColor(this)"><i style="width:14px;height:14px;border-radius:4px;background:transparent;border:1px dashed var(--text3);flex-shrink:0"></i><span style="color:var(--text2);font-weight:700">خودکار (ترتیبی)</span></div>
      <div class="pc-opt" data-hex="#e8a33d" data-name="کهربایی" onclick="pickPrizeColor(this)"><i style="width:14px;height:14px;border-radius:4px;background:#e8a33d;flex-shrink:0"></i><span style="color:#e8a33d;font-weight:700">کهربایی</span></div>
      <div class="pc-opt" data-hex="#d9534f" data-name="قرمز" onclick="pickPrizeColor(this)"><i style="width:14px;height:14px;border-radius:4px;background:#d9534f;flex-shrink:0"></i><span style="color:#d9534f;font-weight:700">قرمز</span></div>
      <div class="pc-opt" data-hex="#5b7fd1" data-name="آبی" onclick="pickPrizeColor(this)"><i style="width:14px;height:14px;border-radius:4px;background:#5b7fd1;flex-shrink:0"></i><span style="color:#5b7fd1;font-weight:700">آبی</span></div>
      <div class="pc-opt" data-hex="#3aa88f" data-name="سبز دودی" onclick="pickPrizeColor(this)"><i style="width:14px;height:14px;border-radius:4px;background:#3aa88f;flex-shrink:0"></i><span style="color:#3aa88f;font-weight:700">سبز دودی</span></div>
      <div class="pc-opt" data-hex="#9b6bd0" data-name="بنفش" onclick="pickPrizeColor(this)"><i style="width:14px;height:14px;border-radius:4px;background:#9b6bd0;flex-shrink:0"></i><span style="color:#9b6bd0;font-weight:700">بنفش</span></div>
      <div class="pc-opt" data-hex="#e0729a" data-name="صورتی" onclick="pickPrizeColor(this)"><i style="width:14px;height:14px;border-radius:4px;background:#e0729a;flex-shrink:0"></i><span style="color:#e0729a;font-weight:700">صورتی</span></div>
      <div class="pc-opt" data-hex="#4fa3c7" data-name="آبی روشن" onclick="pickPrizeColor(this)"><i style="width:14px;height:14px;border-radius:4px;background:#4fa3c7;flex-shrink:0"></i><span style="color:#4fa3c7;font-weight:700">آبی روشن</span></div>
      <div class="pc-opt" data-hex="#f0b35a" data-name="زرد نارنجی" onclick="pickPrizeColor(this)"><i style="width:14px;height:14px;border-radius:4px;background:#f0b35a;flex-shrink:0"></i><span style="color:#f0b35a;font-weight:700">زرد نارنجی</span></div>
        </div>
      </div>
      <div class="toggle-row" style="margin-bottom:12px">
        <label class="toggle"><input type="checkbox" name="prize_active" id="prizeActive" value="1" <?= (!$edit_prize || !empty($edit_prize['is_active'])) ? 'checked' : '' ?>><span class="slider"></span></label>
        <span class="toggle-label">این جایزه در گردونه فعال باشد</span>
      </div>
      <div class="toggle-row" style="margin-bottom:12px">
        <label class="toggle"><input type="checkbox" name="is_local" id="prizeIsLocal" value="1" <?= (!empty($edit_prize['is_local'])) ? 'checked' : '' ?>><span class="slider"></span></label>
        <span class="toggle-label">تحویل با حضور برنده</span>
      </div>
      <div class="toggle-row" style="margin-bottom:12px">
        <label class="toggle"><input type="checkbox" name="is_instant" id="prizeIsInstant" value="1" <?= (!empty($edit_prize['is_instant'])) ? 'checked' : '' ?>><span class="slider"></span></label>
        <span class="toggle-label">تحویل در محل برنده</span>
      </div>
      <div class="toggle-row" style="margin-bottom:12px">
        <label class="toggle"><input type="checkbox" name="is_external" id="prizeIsExternal" value="1" <?= (!empty($edit_prize) && empty($edit_prize['is_local']) && empty($edit_prize['is_instant'])) ? 'checked' : '' ?>><span class="slider"></span></label>
        <span class="toggle-label">محصول خارجی</span>
      </div>
      <div class="fg"><button type="submit" name="add_prize" id="prizeSubmitBtn" class="btn-p"><?= $edit_prize ? 'بروزرسانی جایزه' : 'افزودن جایزه' ?></button></div>
      <div class="fg"><button type="button" id="prizeCancelBtn" class="btn-sm" style="display:<?= $edit_prize ? '' : 'none' ?>;background:var(--card-el);color:var(--text2);padding:10px 16px;border-radius:var(--rss);font-size:.82rem;font-weight:700;cursor:pointer;width:100%;justify-content:center;;" onclick="cancelPrizeEdit()">انصراف از ویرایش</button></div>
    </form>

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
      <span style="font-size:.82rem;font-weight:700;color:var(--gold-dark)">لیست جوایز</span>
      <span style="font-size:.72rem;color:var(--text3)">تعداد: <?= (int)$conn->query("SELECT COUNT(*) c FROM prizes")->fetch_assoc()['c'] ?></span>
    </div>

    <div class="tbl-wrap">
    <table><thead><tr><th>توضیحات</th><th style="text-align:center">لینک</th><th>کد تخفیف</th><th>درصد شانس برد</th><th style="text-align:center">موجودی</th><th style="text-align:center">مهلت</th><th style="text-align:center">رنگ قاچ</th><th style="text-align:center">نوع تحویل</th><th style="text-align:center">وضعیت</th><th style="text-align:center">عملیات</th></tr></thead><tbody>
    <?php
    $pz_row_idx = 0;
    $pz_pal = ['#e8a33d', '#d9534f', '#5b7fd1', '#3aa88f', '#9b6bd0', '#e0729a', '#4fa3c7', '#f0b35a'];
    [$cz_pp,$cz_pg]=admin_list_page('cz'); $cz_total=(int)$conn->query("SELECT COUNT(*) AS c FROM prizes")->fetch_assoc()["c"]; $cz_pg=min($cz_pg,max(1,(int)ceil($cz_total/$cz_pp))); $cz_off=($cz_pg-1)*$cz_pp; $pzr = $conn->query("SELECT * FROM prizes ORDER BY is_void ASC, id ASC LIMIT $cz_off,$cz_pp");
    if ($pzr && $pzr->num_rows > 0):
      while ($pz = $pzr->fetch_assoc()):
        $dt = $pz['description'];
        $dt_short = mb_strlen($dt, 'UTF-8') > 60 ? mb_substr($dt, 0, 60, 'UTF-8') . '...' : $dt;
        // رنگ مؤثر جایزه: همان رنگی که گردونه نشون می‌ده (در صورت نبود رنگ ذخیره‌شده، ترتیبی)
        $pz_hex = strtolower((string)($pz['color'] ?? ''));
        if (empty($pz['is_void'])) {
            if (!in_array($pz_hex, $pz_pal, true)) $pz_hex = $pz_pal[$pz_row_idx % count($pz_pal)];
            $pz_row_idx++;
        }
    ?>
    <tr>
      <td style="font-size:.78rem;line-height:1.6"><?= htmlspecialchars($dt_short, ENT_QUOTES, 'UTF-8') ?><?php if (!empty($pz['is_void'])): ?> <span style="font-size:.64rem;padding:1px 7px;border-radius:999px;background:var(--card-el);color:var(--text2);border:1px solid var(--border)">ویژه گردونه</span><?php endif; ?></td>
      <td style="font-size:.72rem;text-align:center"><?= $pz['affiliate_link'] ? '<a href="'.htmlspecialchars($pz['affiliate_link'], ENT_QUOTES, 'UTF-8').'" target="_blank" rel="noopener" class="btn-sm" style="font-size:.68rem;text-decoration:none;display:inline-block;background:#fff;color:#000;border:1px solid #d0d0d0">مشاهده 🔗</a>' : '—' ?></td>
      <td style="font-size:.72rem;font-weight:600"><?= $pz['discount_code'] ? htmlspecialchars($pz['discount_code'], ENT_QUOTES, 'UTF-8') : '—' ?></td>
      <td style="text-align:center;font-weight:700"><?= rtrim(rtrim((string)$pz['win_probability'], '0'), '.') ?>%</td>
      <td style="text-align:center;font-size:.72rem;white-space:nowrap"><?php if ($pz['qty_limit'] !== null): $pz_left = max(0, (int)$pz['qty_limit'] - (int)$pz['qty_used']); ?><?= pn($pz_left) ?> از <?= pn((int)$pz['qty_limit']) ?><?php else: ?>—<?php endif; ?></td>
      <td style="text-align:center;font-size:.72rem;white-space:nowrap"><?php $pz_days = sitak_prize_days_left($pz); echo $pz_days === null ? '—' : ($pz_days <= 0 ? 'منقضی شده' : pn($pz_days) . ' روز مانده'); ?></td>
      <td style="text-align:center;font-size:.72rem;font-weight:700"><?php if (empty($pz['is_void'])): ?><span style="color:<?= htmlspecialchars($pz_hex, ENT_QUOTES, 'UTF-8') ?>">■ <?= htmlspecialchars($prize_color_names[$pz_hex] ?? '', ENT_QUOTES, 'UTF-8') ?></span><?php else: ?>—<?php endif; ?></td>
      <td style="text-align:center"><span style="font-size:.68rem;padding:2px 8px;border-radius:999px;background:var(--card-el);color:var(--text2)"><?= !empty($pz['is_instant']) ? 'در محل' : (!empty($pz['is_local']) ? 'با حضور' : 'خارجی') ?></span></td>
      <td style="text-align:center;vertical-align:middle" class="tbl-act-status"><?= !empty($pz['is_active']) ? '✅' : '⛔' ?></td>
      <td style="white-space:nowrap;text-align:center;vertical-align:middle">
        <button type="button" class="btn-sm btn-blue" style="font-size:.68rem;cursor:pointer" onclick="startPrizeEdit(this)" data-id="<?= (int)$pz['id'] ?>" data-active="<?= !empty($pz['is_active']) ? '1' : '0' ?>" data-desc="<?= htmlspecialchars($pz['description'], ENT_QUOTES, 'UTF-8') ?>" data-link="<?= htmlspecialchars($pz['affiliate_link'] ?? '', ENT_QUOTES, 'UTF-8') ?>" data-disc="<?= htmlspecialchars($pz['discount_code'] ?? '', ENT_QUOTES, 'UTF-8') ?>" data-prob="<?= htmlspecialchars((string)$pz['win_probability'], ENT_QUOTES, 'UTF-8') ?>" data-color="<?= htmlspecialchars($pz_hex, ENT_QUOTES, 'UTF-8') ?>" data-local="<?= !empty($pz['is_local']) ? '1' : '0' ?>" data-instant="<?= !empty($pz['is_instant']) ? '1' : '0' ?>" data-qty="<?= $pz['qty_limit'] !== null ? (int)$pz['qty_limit'] : '' ?>" data-days="<?= $pz['time_limit_days'] !== null ? (int)$pz['time_limit_days'] : '' ?>">✏️</button>
        <a href="<?= admin_url('chance') ?>?toggle_prize_active=<?= (int)$pz['id'] ?>&csrf=<?= sitak_csrf_token() ?>" class="btn-sm act-toggle" title="<?= !empty($pz['is_active']) ? 'غیرفعال کردن' : 'فعال کردن' ?>"><?= !empty($pz['is_active']) ? '⛔' : '✅' ?></a>
        <?php if (empty($pz['is_void'])): ?><a href="<?= admin_url('chance') ?>?delete_prize=<?= (int)$pz['id'] ?>&csrf=<?= sitak_csrf_token() ?>" class="btn-sm btn-red" style="font-size:.68rem" onclick="return adminConfirmEl(this,'این جایزه حذف شود؟')">🗑️</a><?php endif; ?>
      </td>
    </tr>
    <?php endwhile; else: ?>
    <tr><td colspan="10" style="text-align:center;color:var(--text3);padding:16px">جایزه‌ای ثبت نشده است.</td></tr>
    <?php endif; ?>
    </tbody></table>
    </div>
    <?= admin_pager('cz', null, $cz_total, $cz_pp, $cz_pg) ?>
  </div>


  <!-- ۳. پیام‌های نتیجه تاس و گردونه -->
  <div class="acc-hdr" onclick="toggleAcc(this)"><h3>۳. پیام‌های نتیجه تاس و گردونه</h3><span class="acc-icon">+</span></div>
  <div class="acc-body">
    <?php $rm = sitak_result_msgs($conn); ?>
    <form method="post">
      <p style="font-size:.78rem;color:var(--text3);margin-bottom:10px">این پیام‌ها برای تاس و گردونه مشترک‌اند.</p>
      <div class="fg"><label>پیام عدم برد</label><textarea class="fi" name="result_lose_msg" rows="2"><?= htmlspecialchars($rm['result_lose_msg'], ENT_QUOTES, 'UTF-8') ?></textarea></div>
      <div class="fg"><label>پیام برد — تحویل با حضور برنده</label><textarea class="fi" name="result_win_local_msg" rows="2"><?= htmlspecialchars($rm['result_win_local_msg'], ENT_QUOTES, 'UTF-8') ?></textarea></div>
      <div class="fg"><label>پیام برد — تحویل در محل برنده</label><textarea class="fi" name="result_win_instant_msg" rows="2"><?= htmlspecialchars($rm['result_win_instant_msg'], ENT_QUOTES, 'UTF-8') ?></textarea></div>
      <div class="fg"><label>پیام برد — محصول خارجی</label><textarea class="fi" name="result_win_external_msg" rows="2"><?= htmlspecialchars($rm['result_win_external_msg'], ENT_QUOTES, 'UTF-8') ?></textarea></div>
      <div class="fg"><label>پیام کارت آخرین برد — فرصت تحویل تمام شده</label><textarea class="fi" name="result_last_expired_msg" rows="2"><?= htmlspecialchars($rm['result_last_expired_msg'], ENT_QUOTES, 'UTF-8') ?></textarea></div>
      <div class="fg"><label>پیام کارت آخرین برد — تحویل داده شده</label><textarea class="fi" name="result_last_delivered_msg" rows="2"><?= htmlspecialchars($rm['result_last_delivered_msg'], ENT_QUOTES, 'UTF-8') ?></textarea></div>
      <div class="fg"><button type="submit" name="save_result_msgs" class="btn-p">ذخیره پیام‌ها</button></div>
    </form>
  </div>

  <!-- ۴. کنترل لیست برندگان -->
  <div class="acc-hdr" onclick="toggleAcc(this)"><h3>۴. کنترل لیست برندگان</h3><span class="acc-icon">+</span></div>
  <div class="acc-body">

    <form method="post" id="winnersCtlForm">
      <div class="toggle-row">
        <label class="toggle"><input type="checkbox" name="winners_home_enabled" value="1" <?= ($winners_home_enabled ?? '1') === '1' ? 'checked' : '' ?>><span class="slider"></span></label>
        <span class="toggle-label">نمایش لیست برندگان (واقعی + ساختگی) در صفحه‌ی اصلی</span>
      </div>
      <div class="toggle-row">
        <label class="toggle"><input type="checkbox" name="winners_list_enabled" value="1" <?= ($winners_list_enabled ?? '1') === '1' ? 'checked' : '' ?>><span class="slider"></span></label>
        <span class="toggle-label">نمایش لیست برندگان (واقعی + ساختگی) در صفحه‌ی شانس</span>
      </div>
      <div class="toggle-row" style="margin-bottom:16px">
        <label class="toggle"><input type="checkbox" name="winners_fake_enabled" value="1" <?= ($winners_fake_enabled ?? '1') === '1' ? 'checked' : '' ?>><span class="slider"></span></label>
        <span class="toggle-label">نمایش برندگان ساختگی در لیست</span>
      </div>
      <div class="fg">
        <label>حداقل بازه زمانی اعلام برنده ساختگی جدید (ساعت)</label>
        <div class="stat-row"><button type="button" class="btn-h minus" onclick="stepNum('fake_interval_hours_min',-1)">−</button><input type="text" inputmode="numeric" class="stat-input" name="fake_interval_hours_min" id="wMin" value="<?= htmlspecialchars($fake_interval_hours_min ?? '2', ENT_QUOTES, 'UTF-8') ?>" min="1" max="48"><button type="button" class="btn-h plus" onclick="stepNum('fake_interval_hours_min',1)">+</button></div>
      </div>
      <div class="fg">
        <label>حداکثر بازه زمانی اعلام برنده ساختگی جدید (ساعت)</label>
        <div class="stat-row"><button type="button" class="btn-h minus" onclick="stepNum('fake_interval_hours_max',-1)">−</button><input type="text" inputmode="numeric" class="stat-input" name="fake_interval_hours_max" id="wMax" value="<?= htmlspecialchars($fake_interval_hours_max ?? '6', ENT_QUOTES, 'UTF-8') ?>" min="1" max="48"><button type="button" class="btn-h plus" onclick="stepNum('fake_interval_hours_max',1)">+</button></div>
      </div>
      <div class="fg">
        <label>تعداد برنده ساختگی جدید در هر بازه</label>
        <div class="stat-row"><button type="button" class="btn-h minus" onclick="stepNum('fake_winner_count',-1)">−</button><input type="text" inputmode="numeric" class="stat-input" name="fake_winner_count" id="wCnt" value="<?= htmlspecialchars($fake_winner_count ?? '1', ENT_QUOTES, 'UTF-8') ?>" min="1"><button type="button" class="btn-h plus" onclick="stepNum('fake_winner_count',1)">+</button></div>
      </div>
      <div class="fg">
        <label>تعداد نمایش برندگان در لیست برندگان (صفحه‌ی اصلی و شانس)</label>
        <div class="stat-row"><button type="button" class="btn-h minus" onclick="stepNum('winners_display_count',-1)">−</button><input type="text" inputmode="numeric" class="stat-input" name="winners_display_count" id="wDisp" value="<?= htmlspecialchars($winners_display_count ?? '5', ENT_QUOTES, 'UTF-8') ?>" min="1" max="50"><button type="button" class="btn-h plus" onclick="stepNum('winners_display_count',1)">+</button></div>
      </div>
      <button type="submit" name="save_winners_control" class="btn-p">ذخیره کنترل برندگان</button>
      <p style="font-size:.72rem;color:var(--text3);line-height:1.7;margin-top:10px">برای هر بار تولید، بازه‌ای رندوم بین حداقل و حداکثر انتخاب می‌شود؛ وقتی از آخرین برد بیشتر از آن گذشت، به تعداد مشخص‌شده برنده ساختگی با پیش‌شماره‌های فعال ساخته می‌شود.</p>
    </form>

    <hr style="border:0;border-top:1px solid var(--border);margin:16px 0">

    <span style="font-size:.82rem;font-weight:700;color:var(--gold-dark)">پیش‌شماره‌های برندگان ساختگی</span>
    <?php
    $editing_prefix = intval($_GET['editprefix'] ?? 0);
    $prefix_rows = [];
    $pfr = $conn->query("SELECT * FROM fake_prefixes ORDER BY prefix ASC");
    if ($pfr && $pfr->num_rows > 0) { while ($pf = $pfr->fetch_assoc()) { $prefix_rows[] = $pf; } }
    $editing_prefix_val = '';
    if ($editing_prefix > 0) {
        foreach ($prefix_rows as $pf) { if ((int)$pf['id'] === $editing_prefix) { $editing_prefix_val = $pf['prefix']; break; } }
    }
    ?>

    <form method="post" style="margin:8px 0 16px" id="prefixForm">
      <input type="hidden" name="prefix_id" id="prefixEditId" value="<?= $editing_prefix ?>">
      <div class="fg">
        <label id="prefixLabel"><?= $editing_prefix > 0 ? 'ویرایش پیش‌شماره' : 'پیش‌شماره جدید (۴ رقم با 09)' ?></label>
        <input type="text" class="fi" name="prefix" id="prefixInput" dir="ltr" style="text-align:left" maxlength="4" required
          value="<?= htmlspecialchars($editing_prefix_val, ENT_QUOTES, 'UTF-8') ?>"
          placeholder="09XX">
          <div id="prefixMsg" style="display:none;font-size:.72rem;line-height:1.6;margin-top:6px"></div>
      </div>
      <button type="submit" name="<?= $editing_prefix > 0 ? 'edit_prefix' : 'add_prefix' ?>" id="prefixSubmitBtn" class="btn-p" style="margin-bottom:8px"><?= $editing_prefix > 0 ? 'بروزرسانی پیش‌شماره' : 'افزودن پیش‌شماره' ?></button>
      <button type="button" id="prefixCancelBtn" class="btn-sm" style="display:none;background:var(--card-el);color:var(--text2);padding:10px 16px;border-radius:var(--rss);font-size:.82rem;font-weight:700;cursor:pointer;width:100%;justify-content:center;;" onclick="cancelPrefixEdit()">انصراف از ویرایش</button>
    </form>

    <hr style="border:0;border-top:1px solid var(--border);margin:16px 0">
    <div style="display:flex;justify-content:space-between;align-items:center;margin:16px 0 10px">
      <span style="font-size:.82rem;font-weight:700;color:var(--gold-dark)">لیست پیش‌شماره‌ها</span>
      <span style="font-size:.72rem;color:var(--text3)">تعداد: <?= count($prefix_rows) ?></span>
    </div>
    <div class="tbl-wrap">
    <table><thead><tr><th>پیش‌شماره</th><th style="text-align:center;width:70px">وضعیت</th><th style="text-align:center">عملیات</th></tr></thead><tbody>
    <?php
    [$cf_pp,$cf_pg]=admin_list_page('cf'); $cf_total=count($prefix_rows); $cf_pg=min($cf_pg,max(1,(int)ceil($cf_total/$cf_pp))); $cf_off=($cf_pg-1)*$cf_pp; $prefix_rows_page=array_slice($prefix_rows,$cf_off,$cf_pp);
    if ($prefix_rows_page): foreach ($prefix_rows_page as $pf): ?>
    <tr>
      <td style="font-weight:700"><span dir="ltr"><?= htmlspecialchars($pf['prefix'], ENT_QUOTES, 'UTF-8') ?></span></td>
      <td style="text-align:center;vertical-align:middle" class="tbl-act-status"><?= $pf['is_active'] ? '✅' : '⛔' ?></td>
      <td style="white-space:nowrap;text-align:center;vertical-align:middle">
        <button type="button" class="btn-sm btn-blue" style="font-size:.68rem;cursor:pointer" onclick="startPrefixEdit(<?= (int)$pf['id'] ?>, <?= htmlspecialchars(json_encode($pf['prefix']), ENT_QUOTES, 'UTF-8') ?>)">✏️</button>
        <a href="<?= admin_url('chance') ?>?toggle_prefix=<?= (int)$pf['id'] ?>&csrf=<?= sitak_csrf_token() ?>" class="btn-sm act-toggle" title="<?= $pf['is_active'] ? 'غیرفعال کردن' : 'فعال کردن' ?>"><?= $pf['is_active'] ? '⛔' : '✅' ?></a>
        <a href="<?= admin_url('chance') ?>?delete_prefix=<?= (int)$pf['id'] ?>&csrf=<?= sitak_csrf_token() ?>" class="btn-sm btn-red" style="font-size:.68rem" onclick="return adminConfirmEl(this,'حذف شود؟')">🗑️</a>
      </td>
    </tr>
    <?php endforeach; else: ?>
    <tr><td colspan="3" style="text-align:center;color:var(--text3);padding:16px">پیش‌شماره‌ای ثبت نشده است.</td></tr>
    <?php endif; ?>
    </tbody></table>
    </div>
    <?= admin_pager('cf', null, $cf_total, $cf_pp, $cf_pg) ?>

  </div>

  <!-- ۵. گردونه شانس -->
  <div class="acc-hdr" onclick="toggleAcc(this)"><h3>۵. پیش‌نمایش گردونه شانس</h3><span class="acc-icon">+</span></div>
  <div class="acc-body">
    <?php
      $aw_segs = $wheel_build['segments'];
      $aw_n = count($aw_segs);
      $aw_bg = [];
      foreach ($aw_segs as $i => $sg) {
          $aw_bg[] = $sg['color'].' '.round($i*100/max(1,$aw_n),4).'% '.round(($i+1)*100/max(1,$aw_n),4).'%';
      }
      $aw_css = $aw_bg ? 'conic-gradient('.implode(',', $aw_bg).')' : 'var(--card-el)';
    ?>
    <p style="font-size:.74rem;color:var(--text3);line-height:1.7;margin-bottom:12px">گردونه مستقیم از <strong>جوایز شانس (بخش ۲)</strong> ساخته میشه: هر جایزه یه قاچ مساوی با احتمال خودش، و مابقی درصد‌ها «پوچ» هستن. برای تغییر درصدها همون‌جا رو ویرایش کن.</p>
    <?php if ($wheel_build['over']): ?>
      <div class="wseg-info-box">مجموع همه‌ی درصدها <?= pn(number_format($wheel_build['sum']/100, 2, '.', '')) ?>٪ است و به ۱۰۰٪ تبدیل شده. احتمال تاس دست‌نخورده میمونه.</div>
    <?php elseif (!$aw_segs): ?>
      <div class="wseg-warn-box">هیچ جایزه‌ی فعالی با احتمال بیشتر از صفر نداری؛ گردونه نمایش داده نمیشه.</div>
    <?php endif; ?>
    <div class="wseg-preview">
      <div class="wseg-wheel" style="background:<?= $aw_css ?>"></div>
      <div class="wseg-list-info">
        <?php foreach ($aw_segs as $sg): ?>
          <div class="wseg-item"><i style="background:<?= htmlspecialchars($sg['color'], ENT_QUOTES, 'UTF-8') ?>"></i><span><?= htmlspecialchars($sg['label'], ENT_QUOTES, 'UTF-8') ?></span><b><?= pn(number_format($sg['units']/100, $sg['units']%100===0?0:2, '.', '')) ?>٪</b></div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- ۵. تنظیمات فال، گردونه و تاس -->
  <div class="acc-hdr" onclick="toggleAcc(this)"><h3>۶. ترتیب نمایش کارت‌های بخش شانس</h3><span class="acc-icon">+</span></div>
  <div class="acc-body">
    <p style="font-size:.74rem;color:var(--text3);margin-bottom:10px">عدد کوچک‌تر، بالاتر. نمایش یا عدم نمایش هر کارت جدا از همین تنظیمات است.</p>
    <form method="post" onsubmit="return validateCardOrderForm(this)">
      <input type="hidden" name="card_scope" value="chance">
<?php foreach (sitak_card_keys('chance') as $ck => $cl): $cv = getSetting($conn, 'card_order_chance_' . $ck, (string)(array_search($ck, array_keys(sitak_card_keys('chance'))) + 1)); $cid = 'ord_chance_' . $ck; ?>
      <div class="fg"><label><?= htmlspecialchars($cl, ENT_QUOTES, 'UTF-8') ?></label><div class="stat-row"><button type="button" class="btn-h minus" onclick="stepNum('<?= $cid ?>',-1)">−</button><input type="text" inputmode="numeric" class="stat-input fa-number" id="<?= $cid ?>" name="order_<?= $ck ?>" min="1" max="99" value="<?= htmlspecialchars((string)$cv, ENT_QUOTES, 'UTF-8') ?>"><button type="button" class="btn-h plus" onclick="stepNum('<?= $cid ?>',1)">+</button></div></div>
    <?php endforeach; ?>
      <div class="fg"><button type="submit" name="save_card_order" value="1" class="btn-p">ذخیره ترتیب کارت‌ها</button></div>
    </form>
  </div>
  <div class="acc-hdr" onclick="toggleAcc(this)"><h3>۷. تنظیمات فال، گردونه و تاس</h3><span class="acc-icon">+</span></div>
  <div class="acc-body">
    <form method="post">
      <div class="fg"><label>تعداد شانس روزانه هر کاربر</label><div class="stat-row"><button type="button" class="btn-h minus" onclick="stepNum('dailyRollCount',-1)">−</button><input type="text" inputmode="numeric" class="stat-input fa-number" id="dailyRollCount" name="daily_roll_count" min="0" max="50" value="<?= htmlspecialchars((string)$daily_roll_count_admin,ENT_QUOTES,'UTF-8') ?>"><button type="button" class="btn-h plus" onclick="stepNum('dailyRollCount',1)">+</button></div><small style="display:block;color:var(--text3);font-size:.68rem;margin-top:5px">پیش‌فرض ۱. هر روز این تعداد شانس به کاربر داده می‌شود. ۰ یعنی شانس روزانه نداشته باشد (فقط شانس دعوت).</small></div>
      <div class="toggle-row">
        <label class="toggle"><input type="checkbox" name="dice_home_enabled" value="1" <?= $dice_home_enabled==='1'?'checked':'' ?>><span class="slider"></span></label>
        <span class="toggle-label">بخش تاس در صفحه‌ی اصلی فعال باشد</span>
      </div>
      <div class="toggle-row">
        <label class="toggle"><input type="checkbox" name="dice_enabled" value="1" <?= $dice_enabled==='1'?'checked':'' ?>><span class="slider"></span></label>
        <span class="toggle-label">بخش تاس در سایت فعال باشد</span>
      </div>
      <div class="toggle-row">
        <label class="toggle"><input type="checkbox" name="wheel_home_enabled" value="1" <?= $wheel_home_enabled==='1'?'checked':'' ?>><span class="slider"></span></label>
        <span class="toggle-label">بخش گردونه در صفحه‌ی اصلی فعال باشد</span>
      </div>
      <div class="toggle-row">
        <label class="toggle"><input type="checkbox" name="wheel_enabled" value="1" <?= $wheel_enabled==='1'?'checked':'' ?>><span class="slider"></span></label>
        <span class="toggle-label">بخش گردونه در سایت فعال باشد</span>
      </div>
      <div class="toggle-row">
        <label class="toggle"><input type="checkbox" name="hafez_home_enabled" value="1" <?= $hafez_home_enabled==='1'?'checked':'' ?>><span class="slider"></span></label>
        <span class="toggle-label">بخش فال در صفحه‌ی اصلی فعال باشد</span>
      </div>
      <div class="toggle-row">
        <label class="toggle"><input type="checkbox" name="hafez_enabled" value="1" <?= $hafez_enabled==='1'?'checked':'' ?>><span class="slider"></span></label>
        <span class="toggle-label">بخش فال در سایت فعال باشد</span>
      </div>
      <div class="toggle-row">
        <label class="toggle"><input type="checkbox" name="chance_enabled" value="1" <?= $chance_enabled==='1'?'checked':'' ?>><span class="slider"></span></label>
        <span class="toggle-label">نمایش تب شانس، فال و تاس در سایت</span>
      </div>
      <div class="fg"><label>عنوان هدر صفحه شانس</label><input type="text" name="chance_title" value="<?= htmlspecialchars($chance_title??'', ENT_QUOTES, 'UTF-8') ?>"></div>
      <div class="fg"><label>زیرمتن هدر صفحه شانس</label><textarea name="chance_description" rows="3"><?= htmlspecialchars($chance_description??'', ENT_QUOTES, 'UTF-8') ?></textarea></div>
      <div class="fg"><label>پیام نیت قبل از فال</label><textarea name="intention_msg" rows="3"><?= htmlspecialchars(sitak_intention_msg($conn), ENT_QUOTES, 'UTF-8') ?></textarea></div>
      <div class="fg"><button type="submit" name="save_chance_settings" class="btn-p">ذخیره تنظیمات فال و تاس</button></div>
      <p style="font-size:.72rem;color:var(--text3);line-height:1.6;margin-top:4px">دو گزینه‌ی اول بخش تاس را در صفحه‌ی اصلی و کل سایت روشن/خاموش می‌کنند. دو گزینه‌ی بعدی همین کار را برای فال حافظ انجام می‌دهند (فال در سایت = تب hafez.php و باکس فال هر دو صفحه؛ فال در صفحه‌ی اصلی = فقط باکس index.php). گزینه‌ی آخر تب شانس (به‌همراه فال و تاس داخل آن) و آیتم منوی پایین را مخفی می‌کند.</p>
    </form>
  </div>

  <style>
    #prizeColorWrap .modal-acc-body{flex-direction:column;gap:8px;padding:10px}
    #prizeColorWrap .modal-acc-body:not(.open){display:none}
    #prizeColorWrap .modal-acc-body.open{display:flex}
    #prizeColorWrap .pc-opt{display:flex;align-items:center;gap:10px;padding:9px 12px;border:1px solid var(--border);border-radius:var(--rss);cursor:pointer;font-size:.85rem;transition:background .15s}
    [data-theme="light"] #prizeColorWrap{--border:rgba(0,0,0,.16)}
    #prizeColorWrap .pc-opt:hover{background:var(--card-el)}
    .wseg-preview{display:flex;gap:18px;align-items:center;background:var(--card-el);border:1px solid var(--border);border-radius:14px;padding:14px;flex-wrap:wrap}
    .wseg-wheel{width:130px;height:130px;flex-shrink:0;border-radius:50%;border:3px solid var(--gold);box-shadow:var(--shadow-sm)}
    .wseg-list-info{flex:1;min-width:200px;display:flex;flex-direction:column;gap:8px}
    .wseg-item{display:flex;align-items:center;gap:8px;font-size:.8rem}
    .wseg-item i{width:12px;height:12px;border-radius:50%;flex-shrink:0}
    .wseg-item span{flex:1}
    .wseg-item b{color:var(--gold-dark)}
    .wseg-void-form{background:var(--card-el);border:1px solid var(--border);border-radius:14px;padding:12px;margin-bottom:14px}
    .wseg-void-title{font-size:.82rem;font-weight:700;color:var(--gold-dark);margin-bottom:8px}
    .wseg-void-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;align-items:end}
    .wseg-void-grid label{display:flex;flex-direction:column;gap:4px;font-size:.72rem;color:var(--text2)}
    .wseg-void-note{font-size:.7rem;color:var(--text3);margin-top:8px;line-height:1.6}
    .wseg-info-box{background:var(--gold-subtle);color:var(--gold-dark);border:1px solid var(--border-gold);border-radius:12px;padding:10px 12px;font-size:.78rem;margin-bottom:12px}
    .wseg-warn-box{background:var(--red-bg);color:var(--red);border:1px solid var(--red);border-radius:12px;padding:10px 12px;font-size:.78rem;margin-bottom:12px}
  </style>

<style>
  .stat-row input[type=number]::-webkit-inner-spin-button,
  .stat-row input[type=number]::-webkit-outer-spin-button{-webkit-appearance:none;margin:0}
  .stat-row input[type=number]{-moz-appearance:textfield;text-align:center}
</style>
<script>
function startPrefixEdit(id, val){
  document.getElementById('prefixEditId').value = id;
  document.getElementById('prefixInput').value = val;
  var lbl=document.getElementById('prefixLabel'); if(lbl) lbl.textContent = 'ویرایش پیش‌شماره';
  var btn=document.getElementById('prefixSubmitBtn'); btn.setAttribute('name','edit_prefix'); btn.textContent='بروزرسانی پیش‌شماره';
  document.getElementById('prefixCancelBtn').style.display='';
  validatePrefix(val); document.getElementById('prefixInput').focus();
}
function cancelPrefixEdit(){
  document.getElementById('prefixEditId').value='0';
  document.getElementById('prefixInput').value='';
  var lbl=document.getElementById('prefixLabel'); if(lbl) lbl.textContent='پیش‌شماره جدید (۴ رقم با 09)';
  var btn=document.getElementById('prefixSubmitBtn'); btn.setAttribute('name','add_prefix'); btn.textContent='افزودن پیش‌شماره';
  document.getElementById('prefixCancelBtn').style.display='none';
  var f=document.getElementById('prefixMsg'); if(f){f.style.display='none';}
}
function focusForm(id){
  var f=document.getElementById(id);
  if(f){ f.scrollIntoView({behavior:'smooth',block:'start'}); }
}
function validatePrizeTypes(e){
  var checked=['prizeIsLocal','prizeIsInstant','prizeIsExternal'].filter(function(id){return document.getElementById(id).checked}).length;
  if(checked!==1){
    e.preventDefault();
    showToast('فقط یکی از گزینه‌های نوع تحویل رو انتخاب کن');
    return false;
  }
  return true;
}
function setPrizeColorUI(hex){
  hex=(hex||'').toLowerCase();
  var row=document.querySelector('#prizeColorWrap .pc-opt[data-hex="'+hex+'"]')||document.querySelector('#prizeColorWrap .pc-opt[data-hex=""]');
  document.getElementById('prizeColor').value=row.dataset.hex;
  document.getElementById('prizeColorSw').style.background=row.dataset.hex||'transparent';
  var t=document.getElementById('prizeColorTxt');
  t.textContent=row.dataset.name;
  t.style.color=row.dataset.hex||'var(--gold-dark)';
  document.querySelectorAll('#prizeColorWrap .pc-opt').forEach(function(r){r.style.background=(r===row)?'var(--gold-subtle)':'';});
}
function pickPrizeColor(el){
  setPrizeColorUI(el.dataset.hex);
  var body=el.parentElement, hdr=body.previousElementSibling;
  body.classList.remove('open'); hdr.classList.remove('open');
  hdr.querySelector('.modal-acc-icon').textContent='+';
}

function startPrizeEdit(btn){
  document.getElementById('prizeId').value=btn.dataset.id;
  document.getElementById('prizeDesc').value=btn.dataset.desc||'';
  document.getElementById('prizeLink').value=btn.dataset.link||'';
  document.getElementById('prizeDiscount').value=btn.dataset.disc||'';
  var pv=parseFloat(btn.dataset.prob);
  document.getElementById('prizeProbability').value=isNaN(pv)?'0':String(pv);
  document.getElementById('prizeIsLocal').checked=btn.dataset.local==='1';
  document.getElementById('prizeIsInstant').checked=btn.dataset.instant==='1';
  document.getElementById('prizeIsExternal').checked=(btn.dataset.local!=='1' && btn.dataset.instant!=='1');
  document.getElementById('prizeQtyLimit').value=btn.dataset.qty;
  document.getElementById('prizeTimeDays').value=btn.dataset.days;
  document.getElementById('prizeActive').checked=btn.dataset.active==='1';
  document.getElementById('prizeSubmitBtn').textContent='بروزرسانی جایزه';
  document.getElementById('prizeCancelBtn').style.display='';
  focusForm('prizeForm');
  setPrizeColorUI(btn.dataset.color||'');
  document.getElementById('prizeDesc').focus();
}
function cancelPrizeEdit(){
  document.getElementById('prizeId').value='0';
  document.getElementById('prizeDesc').value='';
  document.getElementById('prizeLink').value='';
  document.getElementById('prizeDiscount').value='';
  document.getElementById('prizeProbability').value='0';
  document.getElementById('prizeIsLocal').checked=false;
  setPrizeColorUI('');
  document.getElementById('prizeIsInstant').checked=false;
  document.getElementById('prizeIsExternal').checked=false;
  document.getElementById('prizeQtyLimit').value='';
  document.getElementById('prizeTimeDays').value='';
  document.getElementById('prizeActive').checked=true;
  document.getElementById('prizeSubmitBtn').textContent='افزودن جایزه';
  document.getElementById('prizeCancelBtn').style.display='none';
}
function startHafezEdit(btn){
  document.getElementById('hafezId').value=btn.dataset.id;
  document.getElementById('hafezM1').value=btn.dataset.m1||'';
  document.getElementById('hafezM2').value=btn.dataset.m2||'';
  document.getElementById('hafezActive').checked=btn.dataset.active==='1';
  document.getElementById('hafezMeaning').value=btn.dataset.meaning||'';
  document.getElementById('hafezSubmitBtn').textContent='بروزرسانی بیت';
  document.getElementById('hafezCancelBtn').style.display='';
  focusForm('hafezForm');
  document.getElementById('hafezM1').focus();
}
function cancelHafezEdit(){
  document.getElementById('hafezId').value='0';
  document.getElementById('hafezM1').value='';
  document.getElementById('hafezM2').value='';
  document.getElementById('hafezMeaning').value='';
  document.getElementById('hafezActive').checked=true;
  document.getElementById('hafezSubmitBtn').textContent='افزودن بیت';
  document.getElementById('hafezCancelBtn').style.display='none';
}
function stepWinners(name,off){
  var el=document.querySelector('[name="'+name+'"]');
  var v=parseInt(String(el.value).replace(/[۰-۹]/g,d=>'۰۱۲۳۴۵۶۷۸۹'.indexOf(d)),10)||0; v+=off;
  var mn=parseInt(el.getAttribute('min'),10), mx=parseInt(el.getAttribute('max'),10);
  if(!isNaN(mn)&&v<mn) v=mn; if(!isNaN(mx)&&v>mx) v=mx;
  el.value=v;
}
function validatePrefixForm(e){
  var inp=document.getElementById('prefixInput'); if(!inp) return true;
  var v=inp.value.trim(); if(!/^09[0-9۰-۹]{2}$/.test(v)){
    e.preventDefault();
    var f=document.getElementById('prefixMsg'); if(f){f.style.display=''; f.style.color='var(--red)'; f.textContent='پیش‌شماره نامعتبر — فقط ۴ رقم با 09 (مثل 0912) مورد قبول است.';}
    inp.focus(); return false;
  }
  return true;
}
var __pForm=document.getElementById('prefixInput'); if(__pForm){var formEl=__pForm.closest('form'); if(formEl)formEl.addEventListener('submit',validatePrefixForm);}
var PREFIX_BASE = '<?= admin_url('chance') ?>';
function validatePrefix(v){
  var f=document.getElementById('prefixMsg');
  if(!v){if(f){f.style.display='none';f.textContent='';}return false;}
  var ok=/^09[0-9۰-۹]{2}$/.test(v);
  if(f){f.style.display='';f.style.color=ok?'var(--green)':'var(--red)';f.textContent=ok?'معتبر ✓':'نامعتبر — فقط شماره‌های ۴ رقمی که با 09 شروع شوند (مثل 0912) قابل قبول هستند.';}
  return ok;
}
var __prefixInputEl=document.getElementById('prefixInput');
if(__prefixInputEl){__prefixInputEl.addEventListener('input',function(){validatePrefix(this.value.trim());});}
function validateWinnersCtl(e){
  var mn=document.querySelector('[name="fake_interval_hours_min"]');
  var mx=document.querySelector('[name="fake_interval_hours_max"]');
  var cnt=document.querySelector('[name="fake_winner_count"]');
  var errs=[];
  var mnv=mn?parseInt(String(mn.value).replace(/[۰-۹]/g,d=>'۰۱۲۳۴۵۶۷۸۹'.indexOf(d)),10):2, mxv=mx?parseInt(String(mx.value).replace(/[۰-۹]/g,d=>'۰۱۲۳۴۵۶۷۸۹'.indexOf(d)),10):6;
  if(mn&&mnv<1){errs.push('حداقل بازه باید حداقل ۱ ساعت باشد.');}
  if(mx&&mxv<1){errs.push('حداکثر بازه باید حداقل ۱ ساعت باشد.');}
  if(mn&&mx&&mnv>mxv){errs.push('حداقل بازه ( '+mnv+' ) نباید از حداکثر ( '+mxv+' ) بیشتر باشد.');}
  if(cnt&&parseInt(String(cnt.value).replace(/[۰-۹]/g,d=>'۰۱۲۳۴۵۶۷۸۹'.indexOf(d)),10)<1){errs.push('تعداد برنده جدید باید حداقل ۱ باشد.');}
  if(errs.length){e.preventDefault();var el=document.getElementById('winnersCtlMsg')||mn; if(el){var m=document.getElementById('winnersCtlMsg'); if(!m){m=document.createElement('p'); m.id='winnersCtlMsg'; m.style.cssText='font-size:.72rem;color:var(--red);line-height:1.7;margin-top:10px'; mn.parentElement.parentElement.appendChild(m);} m.style.display=''; m.textContent=errs.join(' | ');} return false;}
  return true;
}
var __wForm=document.getElementById('winnersCtlForm'); if(__wForm){__wForm.addEventListener('submit',validateWinnersCtl);}
</script></div>

<div class="toast" id="toast"></div>

<script>
function validateCardOrderForm(form){
  var seen={}, dup=false;
  form.querySelectorAll('input[name^="order_"]').forEach(function(i){
    var v=String(i.value).replace(/[۰-۹]/g,function(d){return String('۰۱۲۳۴۵۶۷۸۹'.indexOf(d))}).trim();
    if(seen[v]) dup=true; seen[v]=true;
  });
  if(dup){ showToast('هر کارت باید عدد ترتیب جدا داشته باشه. عدد تکراری ثبت نشد.'); return false; }
  return true;
}
</script>
<script>
<?php admin_common_js(); ?>
</script>
<?php
admin_footer_nav('chance');
?>
<?php include 'admin_ajax_save.php'; ?>
</body>
</html>
