<?php
$admin_self = $admin_self ?? 'admin-shop.php';
require 'admin_common.php';

if (!$is_authenticated) { admin_login_screen($login_error ?? null); exit; }
admin_page_start('فروشگاه');
admin_header();

$editing_pid = intval($_GET['edit'] ?? 0);
$edit_product = null;
if ($editing_pid > 0) {
    $er = $conn->prepare("SELECT * FROM products WHERE id = ?");
    $er->bind_param("i", $editing_pid);
    $er->execute();
    $edit_product = $er->get_result()->fetch_assoc();
}
$editing_cid = intval($_GET['editcat'] ?? 0);
$edit_cat_row = null;
if ($editing_cid > 0) {
    $er = $conn->prepare("SELECT * FROM shop_categories WHERE id = ?");
    $er->bind_param("i", $editing_cid);
    $er->execute();
    $edit_cat_row = $er->get_result()->fetch_assoc();
}
?>
<div class="container">
<script>
window.toggleAcc=function(el){var body=el.nextElementSibling,icon=el.querySelector('.acc-icon');if(!body)return;var open=body.classList.toggle('open');el.classList.toggle('open',open);if(icon)icon.textContent=open?'−':'+';};
window.toggleProductLimits=function(btn){var panel=btn.nextElementSibling;if(!panel)return;var open=panel.classList.toggle('open');btn.classList.toggle('open',open);btn.setAttribute('aria-expanded',open?'true':'false');};
</script>

  <!-- ۱. محصولات -->
  <div class="acc-hdr" onclick="toggleAcc(this)"><h3>۱. مدیریت محصولات</h3><span class="acc-icon">+</span></div>
  <div class="acc-body">


    <style>.store-days-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:4px}.store-days-grid label{position:relative}.store-days-grid input{position:absolute;opacity:0}.store-days-grid span{display:flex;align-items:center;justify-content:center;min-height:30px;padding:4px 2px;background:var(--bg);border:1px solid var(--border);border-radius:7px;color:var(--text2);font-size:.62rem;white-space:nowrap}.store-days-grid input:checked+span{background:var(--gold-subtle);border-color:var(--gold);color:var(--gold-dark);font-weight:700}.store-hours-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:8px}.store-hours-grid label{display:flex;flex-direction:column;gap:4px;font-size:.65rem;color:var(--text2);font-weight:600}.store-hours-grid .fi{margin:0;padding:9px 7px;font-size:.78rem}@media(max-width:520px){.store-days-grid{gap:3px}.store-days-grid span{font-size:.57rem}.store-hours-grid{grid-template-columns:repeat(2,1fr)}}</style>
    <form method="post" enctype="multipart/form-data" id="productForm">
      <input type="hidden" name="product_id" id="productId" value="<?= (int)($edit_product['id'] ?? 0) ?>">
      <div class="fg">
        <label>نام محصول</label>
        <input type="text" class="fi" name="product_name" id="productName" value="<?= htmlspecialchars($edit_product['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" maxlength="150" placeholder="مثلاً: نوشیدنی گرم">
      </div>
      <div class="fg">
          <label>دسته‌بندی</label>
          <select name="product_cat" id="productCat">
            <?php
            $cats = $conn->query("SELECT name FROM shop_categories ORDER BY sort_order ASC, id ASC");
            $cat_list = [];
            while ($cr = $cats->fetch_assoc()) { $cat_list[] = $cr['name']; }
            if (empty($cat_list)) { $cat_list = ['عمومی']; }
            foreach ($cat_list as $cn):
            ?>
            <option value="<?= htmlspecialchars($cn, ENT_QUOTES, 'UTF-8') ?>" <?= ($edit_product['category'] ?? 'عمومی') === $cn ? 'selected' : '' ?>><?= htmlspecialchars($cn, ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      <div class="fg">
        <label>برچسب‌ها (با کاما یا ویرگول جدا کنید)</label>
        <input type="text" class="fi" name="product_tags" value="<?= htmlspecialchars(implode(', ', array_column($edit_product ? sitak_product_tags($conn, (int)$edit_product['id']) : [], 'name')), ENT_QUOTES, 'UTF-8') ?>" maxlength="500" placeholder="مثلاً: گرم, کافئین, صبحانه">
      </div>
      <div class="fg">
        <label>قیمت (تومان)</label>
        <div class="stat-row"><button type="button" class="btn-h minus" onclick="stepNum('productPrice',-1)">−</button><input type="text" inputmode="numeric" class="stat-input" name="product_price" id="productPrice" value="<?= (int)($edit_product['price'] ?? 0) ?>" min="0"><button type="button" class="btn-h plus" onclick="stepNum('productPrice',1)">+</button></div>
      </div>
      <div class="fg">
        <label>ترتیب نمایش</label>
        <div class="stat-row"><button type="button" class="btn-h minus" onclick="stepNum('productSort',-1)">−</button><input type="text" inputmode="numeric" class="stat-input" name="product_sort" id="productSort" value="<?= (int)($edit_product['sort_order'] ?? 0) ?>"><button type="button" class="btn-h plus" onclick="stepNum('productSort',1)">+</button></div>
      </div>
      <div class="fg"><label>موجودی (تعداد؛ خالی یعنی محدودیتی ندارد)</label>
        <div class="stat-row"><button type="button" class="btn-h minus" onclick="stepNum('productStock',-1)">−</button><input type="text" inputmode="numeric" class="stat-input" name="product_stock" id="productStock" value="<?= (!empty($edit_product) && $edit_product['stock_qty'] !== null) ? (int)$edit_product['stock_qty'] : '' ?>"><button type="button" class="btn-h plus" onclick="stepNum('productStock',1)">+</button></div>
      </div>
      <div class="fg">
        <label>توضیحات (ویرایشگر غنی / HTML)</label>
        <textarea name="product_desc" id="productDesc" style="display:none"><?= htmlspecialchars($edit_product['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
        
        <style>
          .rte-container { border: 1px solid var(--border); border-radius: var(--rss, 10px); background: var(--card-el, #fff); overflow: hidden; transition: border-color .2s; }
          .rte-container:focus-within { border-color: var(--gold, #d4a373); }
          .rte-toolbar { display: flex; flex-wrap: wrap; gap: 4px; padding: 8px; background: var(--bg, #f9f9f9); border-bottom: 1px solid var(--border); align-items: center; }
          .rte-btn { background: var(--card, #fff); border: 1px solid var(--border); border-radius: 6px; padding: 4px 8px; font-size: .8rem; font-weight: 700; color: var(--text); cursor: pointer; display: inline-flex; align-items: center; justify-content: center; min-width: 32px; height: 32px; user-select: none; transition: all .15s ease; }
          .rte-btn:hover { background: var(--gold-subtle, #fefae0); border-color: var(--border-gold, #ccd5ae); color: var(--gold-dark, #a3b18a); }
          .rte-divider { width: 1px; height: 20px; background: var(--border); margin: 0 3px; }
          .rte-content { min-height: 180px; max-height: 400px; overflow-y: auto; padding: 12px 14px; font-family: var(--font, sans-serif); font-size: .9rem; line-height: 1.8; color: var(--text); outline: none; direction: rtl; text-align: right; }
          .rte-content[contenteditable=true]:empty:before { content: attr(data-placeholder); color: var(--text3, #888); pointer-events: none; display: block; }
          .rte-content p { margin-bottom: 0.8em; }
          .rte-content h2, .rte-content h3 { font-weight: 800; color: var(--gold-dark, #b5838d); margin: 0.8em 0 0.4em; }
          .rte-content blockquote { border-right: 3px solid var(--gold); padding-right: 12px; margin: 0.8em 0; color: var(--text2); font-style: italic; }
          .rte-content ul, .rte-content ol { padding-right: 24px; margin-bottom: 0.8em; }
          .rte-content img { max-width: 100%; height: auto; border-radius: 8px; margin: 8px 0; }
          .rte-source { width: 100%; min-height: 180px; max-height: 400px; padding: 12px 14px; font-family: monospace; font-size: .85rem; line-height: 1.6; border: 0; background: var(--card-el); color: var(--text); direction: ltr; text-align: left; resize: vertical; display: none; box-sizing: border-box; }
        </style>

        <div class="rte-container">
          <div class="rte-toolbar" id="rteToolbar">
            <button type="button" class="rte-btn" onclick="execRte('bold')" title="درشت (Bold)"><b>B</b></button>
            <button type="button" class="rte-btn" onclick="execRte('italic')" title="کج (Italic)"><i>I</i></button>
            <button type="button" class="rte-btn" onclick="execRte('underline')" title="زیرخط (Underline)"><u>U</u></button>
            <button type="button" class="rte-btn" onclick="execRte('strikeThrough')" title="خط روی متن"><s>S</s></button>
            <div class="rte-divider"></div>
            <button type="button" class="rte-btn" onclick="execRte('formatBlock','<h2>')" title="تیتر ۲">H2</button>
            <button type="button" class="rte-btn" onclick="execRte('formatBlock','<h3>')" title="تیتر ۳">H3</button>
            <button type="button" class="rte-btn" onclick="execRte('formatBlock','<p>')" title="پاراگراف عادی">P</button>
            <button type="button" class="rte-btn" onclick="execRte('formatBlock','<blockquote>')" title="نقل‌قول">« »</button>
            <div class="rte-divider"></div>
            <button type="button" class="rte-btn" onclick="execRte('insertUnorderedList')" title="لیست نقطه‌ای">• List</button>
            <button type="button" class="rte-btn" onclick="execRte('insertOrderedList')" title="لیست شماره‌دار">1. List</button>
            <div class="rte-divider"></div>
            <button type="button" class="rte-btn" onclick="execRteLink()" title="درج لینک">🔗</button>
            <button type="button" class="rte-btn" onclick="execRteImage()" title="درج تصویر (آدرس)">🖼️</button>
            <div class="rte-divider"></div>
            <button type="button" class="rte-btn" onclick="execRte('justifyRight')" title="راست‌چین">➔</button>
            <button type="button" class="rte-btn" onclick="execRte('justifyCenter')" title="وسط‌چین">↔</button>
            <button type="button" class="rte-btn" onclick="execRte('justifyLeft')" title="چپ‌چین">⬅</button>
            <div class="rte-divider"></div>
            <button type="button" class="rte-btn" onclick="execRteTable()" title="درج جدول">▦</button>
            <div class="rte-divider"></div>
            <button type="button" class="rte-btn" onclick="execRte('removeFormat')" title="پاک کردن استایل">🧹</button>
          </div>
          <div class="rte-content" id="rteEditor" contenteditable="true" data-placeholder="توضیحات محصول را اینجا وارد کنید..."></div>
        </div>
      </div>
      <div class="fg">
        <label>تصویر محصول</label>
        <input type="file" name="product_img" accept=".png,.jpg,.jpeg,.gif,.webp" style="padding:7px">
        <?php if (!empty($edit_product['image_path'])): ?>
          <div style="display:flex;align-items:center;gap:8px;margin-top:8px">
            <img src="<?= htmlspecialchars($edit_product['image_path']) ?>" style="height:56px;object-fit:contain;border:1px solid var(--border);border-radius:6px;background:#fff;padding:2px">
            <button type="submit" name="delete_product_image" value="<?= (int)$edit_product['id'] ?>" class="btn-sm btn-red" style="font-size:.65rem" onclick="return adminConfirmEl(this,'تصویر این محصول حذف شود؟')">حذف 🗑️</button>
          </div>
        <?php endif; ?>
      </div>
      <div class="toggle-row" style="margin-bottom:8px">
        <label class="toggle"><input type="checkbox" name="product_available" value="1" <?= (isset($edit_product['is_available']) && !$edit_product['is_available']) ? '' : 'checked' ?>><span class="slider"></span></label>
        <span class="toggle-label">موجود در فروشگاه</span>
      </div>
      <div class="toggle-row" style="margin-bottom:8px">
        <label class="toggle"><input type="checkbox" name="product_onsite" value="1" <?= (!empty($edit_product['onsite_product'])) ? 'checked' : '' ?>><span class="slider"></span></label>
        <span class="toggle-label">محصول تحویل در محل (مشمول محدوده، روزها و ساعات کاری)</span>
      </div>
      <div class="fg"><button type="submit" name="save_product" class="btn-p"><?= $edit_product ? 'بروزرسانی محصول' : 'افزودن محصول' ?></button></div>
      <div class="fg js-cancel-wrap" style="<?= $edit_product ? '' : 'display:none' ?>"><button type="button" onclick="cancelProductEdit()" class="btn-sm" style="display:flex;background:var(--card-el);color:var(--text2);padding:10px 16px;border-radius:var(--rss);font-size:.82rem;font-weight:700;justify-content:center;text-decoration:none;border:none;cursor:pointer;font-family:var(--font)">انصراف از ویرایش</button></div>
      <div id="prodFormMsg" style="margin-top:10px"></div>
    </form>

    <hr style="border:0;border-top:1px solid var(--border);margin:16px 0">

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
      <span style="font-size:.82rem;font-weight:700;color:var(--gold-dark)">لیست محصولات</span>
      <span style="font-size:.72rem;color:var(--text3)">تعداد: <?= (int)$conn->query("SELECT COUNT(*) c FROM products")->fetch_assoc()['c'] ?></span>
    </div>
    <div class="tbl-wrap">
    <table><thead><tr><th>نام</th><th style="text-align:center">دسته</th><th style="text-align:center">قیمت</th><th style="text-align:center">موجودی</th><th style="text-align:center">ترتیب</th><th style="text-align:center">عملیات</th></tr></thead><tbody>
    <?php
    [$sp_pp,$sp_pg]=admin_list_page('sp'); $sp_total=(int)$conn->query("SELECT COUNT(*) AS c FROM products")->fetch_assoc()["c"]; $sp_pg=min($sp_pg,max(1,(int)ceil($sp_total/$sp_pp))); $sp_off=($sp_pg-1)*$sp_pp; $prs = $conn->query("SELECT * FROM products ORDER BY sort_order ASC, id ASC LIMIT $sp_off,$sp_pp");
    if ($prs && $prs->num_rows > 0):
      while ($p = $prs->fetch_assoc()):
    ?>
    <tr>
      <td style="font-weight:600"><?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?></td>
      <td style="text-align:center"><span style="font-size:.68rem;background:var(--gold-subtle);color:var(--gold-dark);padding:2px 8px;border-radius:999px"><?= htmlspecialchars($p['category'], ENT_QUOTES, 'UTF-8') ?></span></td>
      <td style="font-weight:700;text-align:center"><?= number_format((int)$p['price']) ?> ت</td>
      <td style="text-align:center"><?php if ($p['stock_qty'] === null): ?><span class="tbl-act-status">✅</span><?php elseif ((int)$p['stock_qty'] <= 0): ?><span class="tbl-act-status">⛔</span><?php else: ?><span class="tbl-act-status">✅</span> <b style="font-size:.72rem"><?= pn((int)$p['stock_qty']) ?></b><?php endif; ?></td>
      <td style="text-align:center"><?= (int)$p['sort_order'] ?></td>
      <td style="white-space:nowrap;text-align:center">
        <a href="<?= admin_url('shop') ?>?edit=<?= (int)$p['id'] ?>" data-inline-edit="#productForm" class="btn-sm btn-blue" style="font-size:.68rem">✏️</a>
        <a href="<?= admin_url('shop') ?>?delete_product=<?= (int)$p['id'] ?>&csrf=<?= sitak_csrf_token() ?>" class="btn-sm btn-red" style="font-size:.68rem" onclick="return adminConfirmEl(this,'محصول «<?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?>» حذف شود؟')">🗑️</a>
      </td>
    </tr>
    <?php endwhile; else: ?>
    <tr><td colspan="6" style="text-align:center;color:var(--text3);padding:16px">محصولی ثبت نشده است.</td></tr>
    <?php endif; ?>
    </tbody></table>
    </div>
    <?= admin_pager('sp', null, $sp_total, $sp_pp, $sp_pg) ?>
  </div>

  <!-- 2. دسته‌بندی‌های فروشگاه -->
  <div class="acc-hdr" onclick="toggleAcc(this)"><h3>۲. دسته‌بندی‌های محصولات</h3><span class="acc-icon">+</span></div>
  <div class="acc-body">

    <form method="post" id="shopCatForm">
      <input type="hidden" name="shop_cat_id" id="shopCatId" value="<?= (int)($edit_cat_row['id'] ?? 0) ?>">
      <div class="fg">
          <label id="shopCatLabel">نام دسته‌بندی</label>
          <input type="text" class="fi" name="shop_cat_name" id="shopCatName" value="<?= htmlspecialchars($edit_cat_row['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" maxlength="50" placeholder="مثلاً: نوشیدنی‌های گرم">
        </div>
      <div class="fg">
        <label>ترتیب نمایش</label>
        <div class="stat-row"><button type="button" class="btn-h minus" onclick="stepNum('shopCatSort',-1)">−</button><input type="text" inputmode="numeric" class="stat-input" name="shop_cat_sort" id="shopCatSort" value="<?= (int)($edit_cat_row['sort_order'] ?? 0) ?>"><button type="button" class="btn-h plus" onclick="stepNum('shopCatSort',1)">+</button></div>
      </div>
      <div class="fg"><button type="submit" name="save_shop_cat" id="shopCatSubmitBtn" class="btn-p"><?= $edit_cat_row ? 'بروزرسانی دسته' : 'افزودن دسته' ?></button></div>
      <div class="fg"><button type="button" id="shopCatCancelBtn" class="btn-sm" style="<?= $edit_cat_row ? '' : 'display:none;' ?>background:var(--card-el);color:var(--text2);padding:10px 16px;border-radius:var(--rss);font-size:.82rem;font-weight:700;cursor:pointer;width:100%;justify-content:center;;" onclick="cancelShopCatEdit()">انصراف از ویرایش</button></div>
      <div id="shopCatMsg" style="margin-top:10px"></div>
    </form>

    <hr style="border:0;border-top:1px solid var(--border);margin:16px 0">

    <div class="tbl-wrap">
    <table><thead><tr><th>نام</th><th style="text-align:center">ترتیب</th><th style="text-align:center">تعداد محصول</th><th style="text-align:center">عملیات</th></tr></thead><tbody>
    <?php
    [$sc_pp,$sc_pg]=admin_list_page('sc'); $sc_total=(int)$conn->query("SELECT COUNT(*) AS c FROM shop_categories")->fetch_assoc()["c"]; $sc_pg=min($sc_pg,max(1,(int)ceil($sc_total/$sc_pp))); $sc_off=($sc_pg-1)*$sc_pp; $scr = $conn->query("SELECT sc.*, (SELECT COUNT(*) FROM products p WHERE p.category = sc.name) AS pc FROM shop_categories sc ORDER BY sc.sort_order ASC, sc.id ASC LIMIT $sc_off,$sc_pp");
    if ($scr && $scr->num_rows > 0):
      while ($sc = $scr->fetch_assoc()):
    ?>
    <tr>
      <td style="font-weight:600"><?= htmlspecialchars($sc['name'], ENT_QUOTES, 'UTF-8') ?></td>
      <td style="text-align:center"><?= (int)$sc['sort_order'] ?></td>
      <td style="text-align:center"><?= (int)$sc['pc'] ?></td>
      <td style="white-space:nowrap;text-align:center">
        <button type="button" class="btn-sm btn-blue" style="font-size:.68rem;cursor:pointer" onclick="startShopCatEdit(<?= (int)$sc['id'] ?>, <?= htmlspecialchars(json_encode($sc['name']), ENT_QUOTES, 'UTF-8') ?>, <?= (int)$sc['sort_order'] ?>)">✏️</button>
        <a href="<?= admin_url('shop') ?>?delete_shop_cat=<?= (int)$sc['id'] ?>&csrf=<?= sitak_csrf_token() ?>" class="btn-sm btn-red" style="font-size:.68rem" onclick="return adminConfirmEl(this,'دسته «<?= htmlspecialchars($sc['name'], ENT_QUOTES, 'UTF-8') ?>» حذف شود؟ (اگر محصول داشته باشد حذف نمی‌شود)')">🗑️</a>
      </td>
    </tr>
    <?php endwhile; else: ?>
    <tr><td colspan="4" style="text-align:center;color:var(--text3);padding:16px">دسته‌بندی‌ای ثبت نشده است.</td></tr>
    <?php endif; ?>
    </tbody></table>
    </div>
    <?= admin_pager('sc', null, $sc_total, $sc_pp, $sc_pg) ?>
  </div>

  <div class="acc-hdr" onclick="toggleAcc(this)"><h3>۳. محدوده فعالیت، روزها و ساعت‌های کاری</h3><span class="acc-icon">+</span></div>
  <div class="acc-body">
    <form method="post">
      <input type="hidden" name="save_store_schedule" value="1">
      <div class="fg"><label>محدوده ارسال محصولات تحویل در محل</label><div style="display:grid;grid-template-columns:1fr;gap:10px"><select class="fi" name="store_shipping_province" id="storeShippingProvince"><option value="">همه استان‌ها</option></select><select class="fi" name="store_shipping_city" id="storeShippingCity" disabled><option value="">همه شهرهای استان</option></select></div><small style="display:block;color:var(--text3);font-size:.68rem;margin-top:5px">این محدوده برای محصولات تحویل در محل و همچنین برای هزینه و حداقل خرید ارسال در «۴. هزینه و محدودیت ارسال» اعمال می‌شود؛ خالی گذاشتن یعنی بدون محدودیت مکانی.</small></div>
      <script>
      var storeShippingCities={"آذربایجان شرقی":["تبریز","مراغه","مرند","میانه","اهر","بناب","سراب","شبستر","آذرشهر","عجب‌شیر","ملکان","بستان‌آباد","اسکو","هریس","کلیبر","جلفا","ورزقان","هوراند","خداآفرین","چاراویماق","سهند","سردرود"],"آذربایجان غربی":["ارومیه","خوی","بوکان","مهاباد","میاندوآب","سلماس","پیرانشهر","نقده","تکاب","ماکو","سردشت","شاهین‌دژ","اشنویه","قره‌ضیاءالدین","شوط","سیه‌چشمه","پلدشت","چایپاره","میرآباد"],"اردبیل":["اردبیل","پارس‌آباد","مشگین‌شهر","خلخال","گرمی","نمین","بیله‌سوار","اصلاندوز","کوثر","سرعین","انگوت","جعفرآباد","هشتجین","آبی‌بیگلو"],"اصفهان":["اصفهان","کاشان","خمینی‌شهر","نجف‌آباد","شاهین‌شهر","شهرضا","فولادشهر","تیران","مبارکه","آران و بیدگل","زرین‌شهر","گلپایگان","فریدن","فلاورجان","دهاقان","نطنز","سمیرم","نائین","اردستان","خوانسار","شاهپورآباد","خوروبیابانک"],"البرز":["کرج","فردیس","کمال‌شهر","نظرآباد","محمدشهر","ماهدشت","مشکین‌دشت","هشتگرد","چهارباغ","اشتهارد","گرمدره","طالقان","کوهسار","تنکمان","آسارا"],"ایلام":["ایلام","دهلران","ایوان","آبدانان","دره‌شهر","مهران","سرابله","ارکواز","آسمان‌آباد","چوار","بدره","پهله","مورموری","زرنه","لومار"],"بوشهر":["بوشهر","برازجان","بندر گناوه","بندر کنگان","خورموج","عسلویه","جم","بندر دیلم","بندر دیر","چغادک","آباد","نخل تقی","دلوار","کاکی","وحدتیه","شبانکاره","اهرم"],"تهران":["تهران","اسلامشهر","شهریار","قدس","ملارد","صالحیه","ورامین","قرچک","پاکدشت","ری","دماوند","بومهن","پردیس","باقرشهر","پیشوا","رباط‌کریم","نسیم‌شهر","اندیشه","صباشهر","وحیدیه","فیروزکوه","لواسان","آبسرد","فشم","کهریزک"],"چهارمحال و بختیاری":["شهرکرد","بروجن","لردگان","فرخ‌شهر","فارسان","هفشجان","سامان","جونقان","کیار","بن","سورشجان","بلداجی","اردل","نقنه","کوهرنگ","فرودین"],"خراسان جنوبی":["بیرجند","قائن","طبس","فردوس","نهبندان","بشرویه","سرایان","سربیشه","اسلامیه","حاجی‌آباد","خوسف","آرین‌شهر","اسدیه","سه قلعه"],"خراسان رضوی":["مشهد","نیشابور","سبزوار","تربت حیدریه","کاشمر","قوچان","تربت جام","تایباد","چناران","سرخس","گناباد","فریمان","گلبهار","درگز","خواف","بردسکن","طرقبه","شاندیز","خلیل‌آباد","مه ولات","کلات","فیروزه"],"خراسان شمالی":["بجنورد","شیروان","اسفراین","آشخانه","جاجرم","فاروج","گرمه","راز","ایور","درق","شوقان","صفی‌آباد"],"خوزستان":["اهواز","دزفول","آبادان","بندر ماهشهر","خرمشهر","شوشتر","مسجدسلیمان","اندیمشک","بهبهان","ایذه","شوش","شادگان","رامهرمز","امیدیه","سوسنگرد","بندر امام خمینی","باغ‌ملک","هندیجان","لالی","هویزه","گتوند","آغاجاری","حمیدیه"],"زنجان":["زنجان","ابهر","خرمدره","قیدار","هیدج","صائین‌قلعه","آب‌بر","زرین‌آباد","ماهنشان","سلطانیه","سهرورد","سجاس","گرماب"],"سمنان":["سمنان","شاهرود","دامغان","گرمسار","مهدی‌شهر","سرخه","ایوانکی","بسطام","آرادان","مجن","کلاته خیج","شهمیرزاد","میامی"],"سیستان و بلوچستان":["زاهدان","زابل","ایرانشهر","چابهار","سراوان","خاش","کنارک","جالق","نیک‌شهر","پیشین","سوران","زهک","فنوج","مهرستان","دلگان","بمپور","قصرقند","راسک","میرجاوه","هیرمند"],"فارس":["شیراز","مرودشت","جهرم","فسا","کازرون","صدرا","داراب","فیروزآباد","لار","آباده","نورآباد","نی‌ریز","اقلید","استهبان","گراش","زرقان","کوار","لامرد","خرامه","مهر","فراشبند","صفاشهر","قیر و کارزین"],"قزوین":["قزوین","الوند","محمدیه","تاکستان","آبیک","بوئین‌زهرا","شریفیه","اقبالیه","بیدستان","محمودآباد نمونه","اسفرورین","ضیاءآباد","آوج"],"قم":["قم","قنوات","جعفریه","کهک","دستجرد","سلفچگان"],"کردستان":["سنندج","سقز","مریوان","بانه","قروه","کامیاران","بیجار","دیواندره","دهگلان","کانی‌دینار","سروآباد","یاسوکند","زرینه"],"کرمان":["کرمان","سیرجان","رفسنجان","جیرفت","بم","زرند","کهنوج","شهر بابک","بافت","بردسیر","عنبرآباد","راور","محمدآباد","نجف‌شهر","ماهان","منوجان","انار","رودبار جنوب","فهرج","کوهبنان","قلعه گنج"],"کرمانشاه":["کرمانشاه","اسلام‌آباد غرب","جوانرود","کنگاور","سرپل ذهاب","سنقر","هرسین","صحنه","پاوه","گیلانغرب","روانسر","قصر شیرین","تازه‌آباد","کرند غرب","سرمست","باینگان"],"کهگیلویه و بویراحمد":["یاسوج","دوگنبدان","دهدشت","چرام","لیکک","سی‌سخت","لنده","باشت","مارگون","پاتاوه","دیشموک","قلعه رئیسی"],"گلستان":["گرگان","گنبد کاووس","علی‌آباد کتول","بندر ترکمن","کلاله","آزادشهر","آق‌قلا","کردکوی","مینوشت","گالیکش","بندر گز","فاضل‌آباد","گمش‌تپه","رامیان","خان‌ببین","مراوه‌تپه"],"گیلان":["رشت","بندر انزلی","لاهیجان","لنگرود","تالش","آستارا","صومعه‌سرا","آستانه اشرفیه","رودسر","فومن","خمام","سیاهکل","رضوانشهر","ماسال","منجیل","رودبار","کلاچای","لشت نشا","کوچصفهان","املش"],"لرستان":["خرم‌آباد","بروجرد","دورود","کوهدشت","الیگودرز","نورآباد","الشتر","پلدختر","ازنا","کوهنانی","سپیددشت","زاغه","سراب دوره","معمولان"],"مازندران":["ساری","بابل","آمل","قائم‌شهر","بهشهر","چالوس","نکا","بابلسر","تنکابن","نوشهر","فریدونکنار","رامسر","جویبار","محمودآباد","امیرکلا","نور","گلوگاه","سرخرود","عباس‌آباد","کلاردشت","زیرآب","پل سفید"],"مرکزی":["اراک","ساوه","خمین","محلات","دلیجان","زرندیه","شازند","مهاجران","تفرش","میلاجرد","کمیجان","آشتیان","فرمهین","خنداب","نراق"],"هرمزگان":["بندرعباس","میناب","دهبارز","بندر لنگه","قشم","کیش","حاجی‌آباد","کنگ","بندر خمیر","پارسیان","جاسک","بندر چارک","بستک","سوزا","ابوموسی","بندر سیریک"],"همدان":["همدان","ملایر","نهاوند","اسدآباد","تویسرکان","بهار","کبودرآهنگ","لالجین","فامنین","رزن","مریانج","جورقان","قروه درجزین"],"یزد":["یزد","میبد","اردکان","بافق","مهریز","ابرکوه","اشکذر","هرات","زارچ","شاهدیه","تفت","حمیدیا","مروست","بهاباد","عقدا"]};
      (function(){var p=document.getElementById('storeShippingProvince'),c=document.getElementById('storeShippingCity'),savedP=<?= json_encode($store_shipping_province??'',JSON_UNESCAPED_UNICODE) ?>,savedC=<?= json_encode($store_shipping_city??'',JSON_UNESCAPED_UNICODE) ?>;if(!p||!c)return;Object.keys(storeShippingCities).forEach(function(n){var o=document.createElement('option');o.value=n;o.textContent=n;o.selected=n===savedP;p.appendChild(o)});function fill(){c.innerHTML='<option value="">همه شهرهای استان</option>';(storeShippingCities[p.value]||[]).forEach(function(n){var o=document.createElement('option');o.value=n;o.textContent=n;o.selected=n===savedC;c.appendChild(o)});c.disabled=!p.value}p.onchange=function(){savedC='';fill()};fill()})();
      </script>
      <div class="fg"><label>روزهای کاری فروشگاه</label><div class="store-days-grid">
        <?php $store_days=array_filter(array_map('intval',explode(',',(string)($store_active_days??'')))); foreach ([6=>'شنبه',7=>'۱شنبه',1=>'۲شنبه',2=>'۳شنبه',3=>'۴شنبه',4=>'۵شنبه',5=>'جمعه'] as $day=>$label): ?><label><input type="checkbox" name="store_active_days[]" value="<?= $day ?>" <?= in_array($day,$store_days,true)?'checked':'' ?>><span><?= $label ?></span></label><?php endforeach; ?>
      </div><small style="display:block;color:var(--text3);font-size:.68rem;margin-top:5px">اگر هیچ روزی انتخاب نشود، فروشگاه هر روز فعال است.</small></div>
      <div class="fg"><label>ساعات کاری فروشگاه</label><div class="store-hours-grid"><label><span>شروع صبح</span><select class="fi store-time-select" name="store_morning_open"><option value="">انتخاب ساعت</option><option value="00:00" <?= ($store_morning_open??'')==='00:00'?'selected':'' ?>>00:00</option><option value="00:15" <?= ($store_morning_open??'')==='00:15'?'selected':'' ?>>00:15</option><option value="00:30" <?= ($store_morning_open??'')==='00:30'?'selected':'' ?>>00:30</option><option value="00:45" <?= ($store_morning_open??'')==='00:45'?'selected':'' ?>>00:45</option><option value="01:00" <?= ($store_morning_open??'')==='01:00'?'selected':'' ?>>01:00</option><option value="01:15" <?= ($store_morning_open??'')==='01:15'?'selected':'' ?>>01:15</option><option value="01:30" <?= ($store_morning_open??'')==='01:30'?'selected':'' ?>>01:30</option><option value="01:45" <?= ($store_morning_open??'')==='01:45'?'selected':'' ?>>01:45</option><option value="02:00" <?= ($store_morning_open??'')==='02:00'?'selected':'' ?>>02:00</option><option value="02:15" <?= ($store_morning_open??'')==='02:15'?'selected':'' ?>>02:15</option><option value="02:30" <?= ($store_morning_open??'')==='02:30'?'selected':'' ?>>02:30</option><option value="02:45" <?= ($store_morning_open??'')==='02:45'?'selected':'' ?>>02:45</option><option value="03:00" <?= ($store_morning_open??'')==='03:00'?'selected':'' ?>>03:00</option><option value="03:15" <?= ($store_morning_open??'')==='03:15'?'selected':'' ?>>03:15</option><option value="03:30" <?= ($store_morning_open??'')==='03:30'?'selected':'' ?>>03:30</option><option value="03:45" <?= ($store_morning_open??'')==='03:45'?'selected':'' ?>>03:45</option><option value="04:00" <?= ($store_morning_open??'')==='04:00'?'selected':'' ?>>04:00</option><option value="04:15" <?= ($store_morning_open??'')==='04:15'?'selected':'' ?>>04:15</option><option value="04:30" <?= ($store_morning_open??'')==='04:30'?'selected':'' ?>>04:30</option><option value="04:45" <?= ($store_morning_open??'')==='04:45'?'selected':'' ?>>04:45</option><option value="05:00" <?= ($store_morning_open??'')==='05:00'?'selected':'' ?>>05:00</option><option value="05:15" <?= ($store_morning_open??'')==='05:15'?'selected':'' ?>>05:15</option><option value="05:30" <?= ($store_morning_open??'')==='05:30'?'selected':'' ?>>05:30</option><option value="05:45" <?= ($store_morning_open??'')==='05:45'?'selected':'' ?>>05:45</option><option value="06:00" <?= ($store_morning_open??'')==='06:00'?'selected':'' ?>>06:00</option><option value="06:15" <?= ($store_morning_open??'')==='06:15'?'selected':'' ?>>06:15</option><option value="06:30" <?= ($store_morning_open??'')==='06:30'?'selected':'' ?>>06:30</option><option value="06:45" <?= ($store_morning_open??'')==='06:45'?'selected':'' ?>>06:45</option><option value="07:00" <?= ($store_morning_open??'')==='07:00'?'selected':'' ?>>07:00</option><option value="07:15" <?= ($store_morning_open??'')==='07:15'?'selected':'' ?>>07:15</option><option value="07:30" <?= ($store_morning_open??'')==='07:30'?'selected':'' ?>>07:30</option><option value="07:45" <?= ($store_morning_open??'')==='07:45'?'selected':'' ?>>07:45</option><option value="08:00" <?= ($store_morning_open??'')==='08:00'?'selected':'' ?>>08:00</option><option value="08:15" <?= ($store_morning_open??'')==='08:15'?'selected':'' ?>>08:15</option><option value="08:30" <?= ($store_morning_open??'')==='08:30'?'selected':'' ?>>08:30</option><option value="08:45" <?= ($store_morning_open??'')==='08:45'?'selected':'' ?>>08:45</option><option value="09:00" <?= ($store_morning_open??'')==='09:00'?'selected':'' ?>>09:00</option><option value="09:15" <?= ($store_morning_open??'')==='09:15'?'selected':'' ?>>09:15</option><option value="09:30" <?= ($store_morning_open??'')==='09:30'?'selected':'' ?>>09:30</option><option value="09:45" <?= ($store_morning_open??'')==='09:45'?'selected':'' ?>>09:45</option><option value="10:00" <?= ($store_morning_open??'')==='10:00'?'selected':'' ?>>10:00</option><option value="10:15" <?= ($store_morning_open??'')==='10:15'?'selected':'' ?>>10:15</option><option value="10:30" <?= ($store_morning_open??'')==='10:30'?'selected':'' ?>>10:30</option><option value="10:45" <?= ($store_morning_open??'')==='10:45'?'selected':'' ?>>10:45</option><option value="11:00" <?= ($store_morning_open??'')==='11:00'?'selected':'' ?>>11:00</option><option value="11:15" <?= ($store_morning_open??'')==='11:15'?'selected':'' ?>>11:15</option><option value="11:30" <?= ($store_morning_open??'')==='11:30'?'selected':'' ?>>11:30</option><option value="11:45" <?= ($store_morning_open??'')==='11:45'?'selected':'' ?>>11:45</option><option value="12:00" <?= ($store_morning_open??'')==='12:00'?'selected':'' ?>>12:00</option><option value="12:15" <?= ($store_morning_open??'')==='12:15'?'selected':'' ?>>12:15</option><option value="12:30" <?= ($store_morning_open??'')==='12:30'?'selected':'' ?>>12:30</option><option value="12:45" <?= ($store_morning_open??'')==='12:45'?'selected':'' ?>>12:45</option><option value="13:00" <?= ($store_morning_open??'')==='13:00'?'selected':'' ?>>13:00</option><option value="13:15" <?= ($store_morning_open??'')==='13:15'?'selected':'' ?>>13:15</option><option value="13:30" <?= ($store_morning_open??'')==='13:30'?'selected':'' ?>>13:30</option><option value="13:45" <?= ($store_morning_open??'')==='13:45'?'selected':'' ?>>13:45</option><option value="14:00" <?= ($store_morning_open??'')==='14:00'?'selected':'' ?>>14:00</option><option value="14:15" <?= ($store_morning_open??'')==='14:15'?'selected':'' ?>>14:15</option><option value="14:30" <?= ($store_morning_open??'')==='14:30'?'selected':'' ?>>14:30</option><option value="14:45" <?= ($store_morning_open??'')==='14:45'?'selected':'' ?>>14:45</option><option value="15:00" <?= ($store_morning_open??'')==='15:00'?'selected':'' ?>>15:00</option><option value="15:15" <?= ($store_morning_open??'')==='15:15'?'selected':'' ?>>15:15</option><option value="15:30" <?= ($store_morning_open??'')==='15:30'?'selected':'' ?>>15:30</option><option value="15:45" <?= ($store_morning_open??'')==='15:45'?'selected':'' ?>>15:45</option><option value="16:00" <?= ($store_morning_open??'')==='16:00'?'selected':'' ?>>16:00</option><option value="16:15" <?= ($store_morning_open??'')==='16:15'?'selected':'' ?>>16:15</option><option value="16:30" <?= ($store_morning_open??'')==='16:30'?'selected':'' ?>>16:30</option><option value="16:45" <?= ($store_morning_open??'')==='16:45'?'selected':'' ?>>16:45</option><option value="17:00" <?= ($store_morning_open??'')==='17:00'?'selected':'' ?>>17:00</option><option value="17:15" <?= ($store_morning_open??'')==='17:15'?'selected':'' ?>>17:15</option><option value="17:30" <?= ($store_morning_open??'')==='17:30'?'selected':'' ?>>17:30</option><option value="17:45" <?= ($store_morning_open??'')==='17:45'?'selected':'' ?>>17:45</option><option value="18:00" <?= ($store_morning_open??'')==='18:00'?'selected':'' ?>>18:00</option><option value="18:15" <?= ($store_morning_open??'')==='18:15'?'selected':'' ?>>18:15</option><option value="18:30" <?= ($store_morning_open??'')==='18:30'?'selected':'' ?>>18:30</option><option value="18:45" <?= ($store_morning_open??'')==='18:45'?'selected':'' ?>>18:45</option><option value="19:00" <?= ($store_morning_open??'')==='19:00'?'selected':'' ?>>19:00</option><option value="19:15" <?= ($store_morning_open??'')==='19:15'?'selected':'' ?>>19:15</option><option value="19:30" <?= ($store_morning_open??'')==='19:30'?'selected':'' ?>>19:30</option><option value="19:45" <?= ($store_morning_open??'')==='19:45'?'selected':'' ?>>19:45</option><option value="20:00" <?= ($store_morning_open??'')==='20:00'?'selected':'' ?>>20:00</option><option value="20:15" <?= ($store_morning_open??'')==='20:15'?'selected':'' ?>>20:15</option><option value="20:30" <?= ($store_morning_open??'')==='20:30'?'selected':'' ?>>20:30</option><option value="20:45" <?= ($store_morning_open??'')==='20:45'?'selected':'' ?>>20:45</option><option value="21:00" <?= ($store_morning_open??'')==='21:00'?'selected':'' ?>>21:00</option><option value="21:15" <?= ($store_morning_open??'')==='21:15'?'selected':'' ?>>21:15</option><option value="21:30" <?= ($store_morning_open??'')==='21:30'?'selected':'' ?>>21:30</option><option value="21:45" <?= ($store_morning_open??'')==='21:45'?'selected':'' ?>>21:45</option><option value="22:00" <?= ($store_morning_open??'')==='22:00'?'selected':'' ?>>22:00</option><option value="22:15" <?= ($store_morning_open??'')==='22:15'?'selected':'' ?>>22:15</option><option value="22:30" <?= ($store_morning_open??'')==='22:30'?'selected':'' ?>>22:30</option><option value="22:45" <?= ($store_morning_open??'')==='22:45'?'selected':'' ?>>22:45</option><option value="23:00" <?= ($store_morning_open??'')==='23:00'?'selected':'' ?>>23:00</option><option value="23:15" <?= ($store_morning_open??'')==='23:15'?'selected':'' ?>>23:15</option><option value="23:30" <?= ($store_morning_open??'')==='23:30'?'selected':'' ?>>23:30</option><option value="23:45" <?= ($store_morning_open??'')==='23:45'?'selected':'' ?>>23:45</option></select></label><label><span>پایان صبح</span><select class="fi store-time-select" name="store_morning_close"><option value="">انتخاب ساعت</option><option value="00:00" <?= ($store_morning_close??'')==='00:00'?'selected':'' ?>>00:00</option><option value="00:15" <?= ($store_morning_close??'')==='00:15'?'selected':'' ?>>00:15</option><option value="00:30" <?= ($store_morning_close??'')==='00:30'?'selected':'' ?>>00:30</option><option value="00:45" <?= ($store_morning_close??'')==='00:45'?'selected':'' ?>>00:45</option><option value="01:00" <?= ($store_morning_close??'')==='01:00'?'selected':'' ?>>01:00</option><option value="01:15" <?= ($store_morning_close??'')==='01:15'?'selected':'' ?>>01:15</option><option value="01:30" <?= ($store_morning_close??'')==='01:30'?'selected':'' ?>>01:30</option><option value="01:45" <?= ($store_morning_close??'')==='01:45'?'selected':'' ?>>01:45</option><option value="02:00" <?= ($store_morning_close??'')==='02:00'?'selected':'' ?>>02:00</option><option value="02:15" <?= ($store_morning_close??'')==='02:15'?'selected':'' ?>>02:15</option><option value="02:30" <?= ($store_morning_close??'')==='02:30'?'selected':'' ?>>02:30</option><option value="02:45" <?= ($store_morning_close??'')==='02:45'?'selected':'' ?>>02:45</option><option value="03:00" <?= ($store_morning_close??'')==='03:00'?'selected':'' ?>>03:00</option><option value="03:15" <?= ($store_morning_close??'')==='03:15'?'selected':'' ?>>03:15</option><option value="03:30" <?= ($store_morning_close??'')==='03:30'?'selected':'' ?>>03:30</option><option value="03:45" <?= ($store_morning_close??'')==='03:45'?'selected':'' ?>>03:45</option><option value="04:00" <?= ($store_morning_close??'')==='04:00'?'selected':'' ?>>04:00</option><option value="04:15" <?= ($store_morning_close??'')==='04:15'?'selected':'' ?>>04:15</option><option value="04:30" <?= ($store_morning_close??'')==='04:30'?'selected':'' ?>>04:30</option><option value="04:45" <?= ($store_morning_close??'')==='04:45'?'selected':'' ?>>04:45</option><option value="05:00" <?= ($store_morning_close??'')==='05:00'?'selected':'' ?>>05:00</option><option value="05:15" <?= ($store_morning_close??'')==='05:15'?'selected':'' ?>>05:15</option><option value="05:30" <?= ($store_morning_close??'')==='05:30'?'selected':'' ?>>05:30</option><option value="05:45" <?= ($store_morning_close??'')==='05:45'?'selected':'' ?>>05:45</option><option value="06:00" <?= ($store_morning_close??'')==='06:00'?'selected':'' ?>>06:00</option><option value="06:15" <?= ($store_morning_close??'')==='06:15'?'selected':'' ?>>06:15</option><option value="06:30" <?= ($store_morning_close??'')==='06:30'?'selected':'' ?>>06:30</option><option value="06:45" <?= ($store_morning_close??'')==='06:45'?'selected':'' ?>>06:45</option><option value="07:00" <?= ($store_morning_close??'')==='07:00'?'selected':'' ?>>07:00</option><option value="07:15" <?= ($store_morning_close??'')==='07:15'?'selected':'' ?>>07:15</option><option value="07:30" <?= ($store_morning_close??'')==='07:30'?'selected':'' ?>>07:30</option><option value="07:45" <?= ($store_morning_close??'')==='07:45'?'selected':'' ?>>07:45</option><option value="08:00" <?= ($store_morning_close??'')==='08:00'?'selected':'' ?>>08:00</option><option value="08:15" <?= ($store_morning_close??'')==='08:15'?'selected':'' ?>>08:15</option><option value="08:30" <?= ($store_morning_close??'')==='08:30'?'selected':'' ?>>08:30</option><option value="08:45" <?= ($store_morning_close??'')==='08:45'?'selected':'' ?>>08:45</option><option value="09:00" <?= ($store_morning_close??'')==='09:00'?'selected':'' ?>>09:00</option><option value="09:15" <?= ($store_morning_close??'')==='09:15'?'selected':'' ?>>09:15</option><option value="09:30" <?= ($store_morning_close??'')==='09:30'?'selected':'' ?>>09:30</option><option value="09:45" <?= ($store_morning_close??'')==='09:45'?'selected':'' ?>>09:45</option><option value="10:00" <?= ($store_morning_close??'')==='10:00'?'selected':'' ?>>10:00</option><option value="10:15" <?= ($store_morning_close??'')==='10:15'?'selected':'' ?>>10:15</option><option value="10:30" <?= ($store_morning_close??'')==='10:30'?'selected':'' ?>>10:30</option><option value="10:45" <?= ($store_morning_close??'')==='10:45'?'selected':'' ?>>10:45</option><option value="11:00" <?= ($store_morning_close??'')==='11:00'?'selected':'' ?>>11:00</option><option value="11:15" <?= ($store_morning_close??'')==='11:15'?'selected':'' ?>>11:15</option><option value="11:30" <?= ($store_morning_close??'')==='11:30'?'selected':'' ?>>11:30</option><option value="11:45" <?= ($store_morning_close??'')==='11:45'?'selected':'' ?>>11:45</option><option value="12:00" <?= ($store_morning_close??'')==='12:00'?'selected':'' ?>>12:00</option><option value="12:15" <?= ($store_morning_close??'')==='12:15'?'selected':'' ?>>12:15</option><option value="12:30" <?= ($store_morning_close??'')==='12:30'?'selected':'' ?>>12:30</option><option value="12:45" <?= ($store_morning_close??'')==='12:45'?'selected':'' ?>>12:45</option><option value="13:00" <?= ($store_morning_close??'')==='13:00'?'selected':'' ?>>13:00</option><option value="13:15" <?= ($store_morning_close??'')==='13:15'?'selected':'' ?>>13:15</option><option value="13:30" <?= ($store_morning_close??'')==='13:30'?'selected':'' ?>>13:30</option><option value="13:45" <?= ($store_morning_close??'')==='13:45'?'selected':'' ?>>13:45</option><option value="14:00" <?= ($store_morning_close??'')==='14:00'?'selected':'' ?>>14:00</option><option value="14:15" <?= ($store_morning_close??'')==='14:15'?'selected':'' ?>>14:15</option><option value="14:30" <?= ($store_morning_close??'')==='14:30'?'selected':'' ?>>14:30</option><option value="14:45" <?= ($store_morning_close??'')==='14:45'?'selected':'' ?>>14:45</option><option value="15:00" <?= ($store_morning_close??'')==='15:00'?'selected':'' ?>>15:00</option><option value="15:15" <?= ($store_morning_close??'')==='15:15'?'selected':'' ?>>15:15</option><option value="15:30" <?= ($store_morning_close??'')==='15:30'?'selected':'' ?>>15:30</option><option value="15:45" <?= ($store_morning_close??'')==='15:45'?'selected':'' ?>>15:45</option><option value="16:00" <?= ($store_morning_close??'')==='16:00'?'selected':'' ?>>16:00</option><option value="16:15" <?= ($store_morning_close??'')==='16:15'?'selected':'' ?>>16:15</option><option value="16:30" <?= ($store_morning_close??'')==='16:30'?'selected':'' ?>>16:30</option><option value="16:45" <?= ($store_morning_close??'')==='16:45'?'selected':'' ?>>16:45</option><option value="17:00" <?= ($store_morning_close??'')==='17:00'?'selected':'' ?>>17:00</option><option value="17:15" <?= ($store_morning_close??'')==='17:15'?'selected':'' ?>>17:15</option><option value="17:30" <?= ($store_morning_close??'')==='17:30'?'selected':'' ?>>17:30</option><option value="17:45" <?= ($store_morning_close??'')==='17:45'?'selected':'' ?>>17:45</option><option value="18:00" <?= ($store_morning_close??'')==='18:00'?'selected':'' ?>>18:00</option><option value="18:15" <?= ($store_morning_close??'')==='18:15'?'selected':'' ?>>18:15</option><option value="18:30" <?= ($store_morning_close??'')==='18:30'?'selected':'' ?>>18:30</option><option value="18:45" <?= ($store_morning_close??'')==='18:45'?'selected':'' ?>>18:45</option><option value="19:00" <?= ($store_morning_close??'')==='19:00'?'selected':'' ?>>19:00</option><option value="19:15" <?= ($store_morning_close??'')==='19:15'?'selected':'' ?>>19:15</option><option value="19:30" <?= ($store_morning_close??'')==='19:30'?'selected':'' ?>>19:30</option><option value="19:45" <?= ($store_morning_close??'')==='19:45'?'selected':'' ?>>19:45</option><option value="20:00" <?= ($store_morning_close??'')==='20:00'?'selected':'' ?>>20:00</option><option value="20:15" <?= ($store_morning_close??'')==='20:15'?'selected':'' ?>>20:15</option><option value="20:30" <?= ($store_morning_close??'')==='20:30'?'selected':'' ?>>20:30</option><option value="20:45" <?= ($store_morning_close??'')==='20:45'?'selected':'' ?>>20:45</option><option value="21:00" <?= ($store_morning_close??'')==='21:00'?'selected':'' ?>>21:00</option><option value="21:15" <?= ($store_morning_close??'')==='21:15'?'selected':'' ?>>21:15</option><option value="21:30" <?= ($store_morning_close??'')==='21:30'?'selected':'' ?>>21:30</option><option value="21:45" <?= ($store_morning_close??'')==='21:45'?'selected':'' ?>>21:45</option><option value="22:00" <?= ($store_morning_close??'')==='22:00'?'selected':'' ?>>22:00</option><option value="22:15" <?= ($store_morning_close??'')==='22:15'?'selected':'' ?>>22:15</option><option value="22:30" <?= ($store_morning_close??'')==='22:30'?'selected':'' ?>>22:30</option><option value="22:45" <?= ($store_morning_close??'')==='22:45'?'selected':'' ?>>22:45</option><option value="23:00" <?= ($store_morning_close??'')==='23:00'?'selected':'' ?>>23:00</option><option value="23:15" <?= ($store_morning_close??'')==='23:15'?'selected':'' ?>>23:15</option><option value="23:30" <?= ($store_morning_close??'')==='23:30'?'selected':'' ?>>23:30</option><option value="23:45" <?= ($store_morning_close??'')==='23:45'?'selected':'' ?>>23:45</option></select></label><label><span>شروع ظهر</span><select class="fi store-time-select" name="store_afternoon_open"><option value="">انتخاب ساعت</option><option value="00:00" <?= ($store_afternoon_open??'')==='00:00'?'selected':'' ?>>00:00</option><option value="00:15" <?= ($store_afternoon_open??'')==='00:15'?'selected':'' ?>>00:15</option><option value="00:30" <?= ($store_afternoon_open??'')==='00:30'?'selected':'' ?>>00:30</option><option value="00:45" <?= ($store_afternoon_open??'')==='00:45'?'selected':'' ?>>00:45</option><option value="01:00" <?= ($store_afternoon_open??'')==='01:00'?'selected':'' ?>>01:00</option><option value="01:15" <?= ($store_afternoon_open??'')==='01:15'?'selected':'' ?>>01:15</option><option value="01:30" <?= ($store_afternoon_open??'')==='01:30'?'selected':'' ?>>01:30</option><option value="01:45" <?= ($store_afternoon_open??'')==='01:45'?'selected':'' ?>>01:45</option><option value="02:00" <?= ($store_afternoon_open??'')==='02:00'?'selected':'' ?>>02:00</option><option value="02:15" <?= ($store_afternoon_open??'')==='02:15'?'selected':'' ?>>02:15</option><option value="02:30" <?= ($store_afternoon_open??'')==='02:30'?'selected':'' ?>>02:30</option><option value="02:45" <?= ($store_afternoon_open??'')==='02:45'?'selected':'' ?>>02:45</option><option value="03:00" <?= ($store_afternoon_open??'')==='03:00'?'selected':'' ?>>03:00</option><option value="03:15" <?= ($store_afternoon_open??'')==='03:15'?'selected':'' ?>>03:15</option><option value="03:30" <?= ($store_afternoon_open??'')==='03:30'?'selected':'' ?>>03:30</option><option value="03:45" <?= ($store_afternoon_open??'')==='03:45'?'selected':'' ?>>03:45</option><option value="04:00" <?= ($store_afternoon_open??'')==='04:00'?'selected':'' ?>>04:00</option><option value="04:15" <?= ($store_afternoon_open??'')==='04:15'?'selected':'' ?>>04:15</option><option value="04:30" <?= ($store_afternoon_open??'')==='04:30'?'selected':'' ?>>04:30</option><option value="04:45" <?= ($store_afternoon_open??'')==='04:45'?'selected':'' ?>>04:45</option><option value="05:00" <?= ($store_afternoon_open??'')==='05:00'?'selected':'' ?>>05:00</option><option value="05:15" <?= ($store_afternoon_open??'')==='05:15'?'selected':'' ?>>05:15</option><option value="05:30" <?= ($store_afternoon_open??'')==='05:30'?'selected':'' ?>>05:30</option><option value="05:45" <?= ($store_afternoon_open??'')==='05:45'?'selected':'' ?>>05:45</option><option value="06:00" <?= ($store_afternoon_open??'')==='06:00'?'selected':'' ?>>06:00</option><option value="06:15" <?= ($store_afternoon_open??'')==='06:15'?'selected':'' ?>>06:15</option><option value="06:30" <?= ($store_afternoon_open??'')==='06:30'?'selected':'' ?>>06:30</option><option value="06:45" <?= ($store_afternoon_open??'')==='06:45'?'selected':'' ?>>06:45</option><option value="07:00" <?= ($store_afternoon_open??'')==='07:00'?'selected':'' ?>>07:00</option><option value="07:15" <?= ($store_afternoon_open??'')==='07:15'?'selected':'' ?>>07:15</option><option value="07:30" <?= ($store_afternoon_open??'')==='07:30'?'selected':'' ?>>07:30</option><option value="07:45" <?= ($store_afternoon_open??'')==='07:45'?'selected':'' ?>>07:45</option><option value="08:00" <?= ($store_afternoon_open??'')==='08:00'?'selected':'' ?>>08:00</option><option value="08:15" <?= ($store_afternoon_open??'')==='08:15'?'selected':'' ?>>08:15</option><option value="08:30" <?= ($store_afternoon_open??'')==='08:30'?'selected':'' ?>>08:30</option><option value="08:45" <?= ($store_afternoon_open??'')==='08:45'?'selected':'' ?>>08:45</option><option value="09:00" <?= ($store_afternoon_open??'')==='09:00'?'selected':'' ?>>09:00</option><option value="09:15" <?= ($store_afternoon_open??'')==='09:15'?'selected':'' ?>>09:15</option><option value="09:30" <?= ($store_afternoon_open??'')==='09:30'?'selected':'' ?>>09:30</option><option value="09:45" <?= ($store_afternoon_open??'')==='09:45'?'selected':'' ?>>09:45</option><option value="10:00" <?= ($store_afternoon_open??'')==='10:00'?'selected':'' ?>>10:00</option><option value="10:15" <?= ($store_afternoon_open??'')==='10:15'?'selected':'' ?>>10:15</option><option value="10:30" <?= ($store_afternoon_open??'')==='10:30'?'selected':'' ?>>10:30</option><option value="10:45" <?= ($store_afternoon_open??'')==='10:45'?'selected':'' ?>>10:45</option><option value="11:00" <?= ($store_afternoon_open??'')==='11:00'?'selected':'' ?>>11:00</option><option value="11:15" <?= ($store_afternoon_open??'')==='11:15'?'selected':'' ?>>11:15</option><option value="11:30" <?= ($store_afternoon_open??'')==='11:30'?'selected':'' ?>>11:30</option><option value="11:45" <?= ($store_afternoon_open??'')==='11:45'?'selected':'' ?>>11:45</option><option value="12:00" <?= ($store_afternoon_open??'')==='12:00'?'selected':'' ?>>12:00</option><option value="12:15" <?= ($store_afternoon_open??'')==='12:15'?'selected':'' ?>>12:15</option><option value="12:30" <?= ($store_afternoon_open??'')==='12:30'?'selected':'' ?>>12:30</option><option value="12:45" <?= ($store_afternoon_open??'')==='12:45'?'selected':'' ?>>12:45</option><option value="13:00" <?= ($store_afternoon_open??'')==='13:00'?'selected':'' ?>>13:00</option><option value="13:15" <?= ($store_afternoon_open??'')==='13:15'?'selected':'' ?>>13:15</option><option value="13:30" <?= ($store_afternoon_open??'')==='13:30'?'selected':'' ?>>13:30</option><option value="13:45" <?= ($store_afternoon_open??'')==='13:45'?'selected':'' ?>>13:45</option><option value="14:00" <?= ($store_afternoon_open??'')==='14:00'?'selected':'' ?>>14:00</option><option value="14:15" <?= ($store_afternoon_open??'')==='14:15'?'selected':'' ?>>14:15</option><option value="14:30" <?= ($store_afternoon_open??'')==='14:30'?'selected':'' ?>>14:30</option><option value="14:45" <?= ($store_afternoon_open??'')==='14:45'?'selected':'' ?>>14:45</option><option value="15:00" <?= ($store_afternoon_open??'')==='15:00'?'selected':'' ?>>15:00</option><option value="15:15" <?= ($store_afternoon_open??'')==='15:15'?'selected':'' ?>>15:15</option><option value="15:30" <?= ($store_afternoon_open??'')==='15:30'?'selected':'' ?>>15:30</option><option value="15:45" <?= ($store_afternoon_open??'')==='15:45'?'selected':'' ?>>15:45</option><option value="16:00" <?= ($store_afternoon_open??'')==='16:00'?'selected':'' ?>>16:00</option><option value="16:15" <?= ($store_afternoon_open??'')==='16:15'?'selected':'' ?>>16:15</option><option value="16:30" <?= ($store_afternoon_open??'')==='16:30'?'selected':'' ?>>16:30</option><option value="16:45" <?= ($store_afternoon_open??'')==='16:45'?'selected':'' ?>>16:45</option><option value="17:00" <?= ($store_afternoon_open??'')==='17:00'?'selected':'' ?>>17:00</option><option value="17:15" <?= ($store_afternoon_open??'')==='17:15'?'selected':'' ?>>17:15</option><option value="17:30" <?= ($store_afternoon_open??'')==='17:30'?'selected':'' ?>>17:30</option><option value="17:45" <?= ($store_afternoon_open??'')==='17:45'?'selected':'' ?>>17:45</option><option value="18:00" <?= ($store_afternoon_open??'')==='18:00'?'selected':'' ?>>18:00</option><option value="18:15" <?= ($store_afternoon_open??'')==='18:15'?'selected':'' ?>>18:15</option><option value="18:30" <?= ($store_afternoon_open??'')==='18:30'?'selected':'' ?>>18:30</option><option value="18:45" <?= ($store_afternoon_open??'')==='18:45'?'selected':'' ?>>18:45</option><option value="19:00" <?= ($store_afternoon_open??'')==='19:00'?'selected':'' ?>>19:00</option><option value="19:15" <?= ($store_afternoon_open??'')==='19:15'?'selected':'' ?>>19:15</option><option value="19:30" <?= ($store_afternoon_open??'')==='19:30'?'selected':'' ?>>19:30</option><option value="19:45" <?= ($store_afternoon_open??'')==='19:45'?'selected':'' ?>>19:45</option><option value="20:00" <?= ($store_afternoon_open??'')==='20:00'?'selected':'' ?>>20:00</option><option value="20:15" <?= ($store_afternoon_open??'')==='20:15'?'selected':'' ?>>20:15</option><option value="20:30" <?= ($store_afternoon_open??'')==='20:30'?'selected':'' ?>>20:30</option><option value="20:45" <?= ($store_afternoon_open??'')==='20:45'?'selected':'' ?>>20:45</option><option value="21:00" <?= ($store_afternoon_open??'')==='21:00'?'selected':'' ?>>21:00</option><option value="21:15" <?= ($store_afternoon_open??'')==='21:15'?'selected':'' ?>>21:15</option><option value="21:30" <?= ($store_afternoon_open??'')==='21:30'?'selected':'' ?>>21:30</option><option value="21:45" <?= ($store_afternoon_open??'')==='21:45'?'selected':'' ?>>21:45</option><option value="22:00" <?= ($store_afternoon_open??'')==='22:00'?'selected':'' ?>>22:00</option><option value="22:15" <?= ($store_afternoon_open??'')==='22:15'?'selected':'' ?>>22:15</option><option value="22:30" <?= ($store_afternoon_open??'')==='22:30'?'selected':'' ?>>22:30</option><option value="22:45" <?= ($store_afternoon_open??'')==='22:45'?'selected':'' ?>>22:45</option><option value="23:00" <?= ($store_afternoon_open??'')==='23:00'?'selected':'' ?>>23:00</option><option value="23:15" <?= ($store_afternoon_open??'')==='23:15'?'selected':'' ?>>23:15</option><option value="23:30" <?= ($store_afternoon_open??'')==='23:30'?'selected':'' ?>>23:30</option><option value="23:45" <?= ($store_afternoon_open??'')==='23:45'?'selected':'' ?>>23:45</option></select></label><label><span>پایان ظهر</span><select class="fi store-time-select" name="store_afternoon_close"><option value="">انتخاب ساعت</option><option value="00:00" <?= ($store_afternoon_close??'')==='00:00'?'selected':'' ?>>00:00</option><option value="00:15" <?= ($store_afternoon_close??'')==='00:15'?'selected':'' ?>>00:15</option><option value="00:30" <?= ($store_afternoon_close??'')==='00:30'?'selected':'' ?>>00:30</option><option value="00:45" <?= ($store_afternoon_close??'')==='00:45'?'selected':'' ?>>00:45</option><option value="01:00" <?= ($store_afternoon_close??'')==='01:00'?'selected':'' ?>>01:00</option><option value="01:15" <?= ($store_afternoon_close??'')==='01:15'?'selected':'' ?>>01:15</option><option value="01:30" <?= ($store_afternoon_close??'')==='01:30'?'selected':'' ?>>01:30</option><option value="01:45" <?= ($store_afternoon_close??'')==='01:45'?'selected':'' ?>>01:45</option><option value="02:00" <?= ($store_afternoon_close??'')==='02:00'?'selected':'' ?>>02:00</option><option value="02:15" <?= ($store_afternoon_close??'')==='02:15'?'selected':'' ?>>02:15</option><option value="02:30" <?= ($store_afternoon_close??'')==='02:30'?'selected':'' ?>>02:30</option><option value="02:45" <?= ($store_afternoon_close??'')==='02:45'?'selected':'' ?>>02:45</option><option value="03:00" <?= ($store_afternoon_close??'')==='03:00'?'selected':'' ?>>03:00</option><option value="03:15" <?= ($store_afternoon_close??'')==='03:15'?'selected':'' ?>>03:15</option><option value="03:30" <?= ($store_afternoon_close??'')==='03:30'?'selected':'' ?>>03:30</option><option value="03:45" <?= ($store_afternoon_close??'')==='03:45'?'selected':'' ?>>03:45</option><option value="04:00" <?= ($store_afternoon_close??'')==='04:00'?'selected':'' ?>>04:00</option><option value="04:15" <?= ($store_afternoon_close??'')==='04:15'?'selected':'' ?>>04:15</option><option value="04:30" <?= ($store_afternoon_close??'')==='04:30'?'selected':'' ?>>04:30</option><option value="04:45" <?= ($store_afternoon_close??'')==='04:45'?'selected':'' ?>>04:45</option><option value="05:00" <?= ($store_afternoon_close??'')==='05:00'?'selected':'' ?>>05:00</option><option value="05:15" <?= ($store_afternoon_close??'')==='05:15'?'selected':'' ?>>05:15</option><option value="05:30" <?= ($store_afternoon_close??'')==='05:30'?'selected':'' ?>>05:30</option><option value="05:45" <?= ($store_afternoon_close??'')==='05:45'?'selected':'' ?>>05:45</option><option value="06:00" <?= ($store_afternoon_close??'')==='06:00'?'selected':'' ?>>06:00</option><option value="06:15" <?= ($store_afternoon_close??'')==='06:15'?'selected':'' ?>>06:15</option><option value="06:30" <?= ($store_afternoon_close??'')==='06:30'?'selected':'' ?>>06:30</option><option value="06:45" <?= ($store_afternoon_close??'')==='06:45'?'selected':'' ?>>06:45</option><option value="07:00" <?= ($store_afternoon_close??'')==='07:00'?'selected':'' ?>>07:00</option><option value="07:15" <?= ($store_afternoon_close??'')==='07:15'?'selected':'' ?>>07:15</option><option value="07:30" <?= ($store_afternoon_close??'')==='07:30'?'selected':'' ?>>07:30</option><option value="07:45" <?= ($store_afternoon_close??'')==='07:45'?'selected':'' ?>>07:45</option><option value="08:00" <?= ($store_afternoon_close??'')==='08:00'?'selected':'' ?>>08:00</option><option value="08:15" <?= ($store_afternoon_close??'')==='08:15'?'selected':'' ?>>08:15</option><option value="08:30" <?= ($store_afternoon_close??'')==='08:30'?'selected':'' ?>>08:30</option><option value="08:45" <?= ($store_afternoon_close??'')==='08:45'?'selected':'' ?>>08:45</option><option value="09:00" <?= ($store_afternoon_close??'')==='09:00'?'selected':'' ?>>09:00</option><option value="09:15" <?= ($store_afternoon_close??'')==='09:15'?'selected':'' ?>>09:15</option><option value="09:30" <?= ($store_afternoon_close??'')==='09:30'?'selected':'' ?>>09:30</option><option value="09:45" <?= ($store_afternoon_close??'')==='09:45'?'selected':'' ?>>09:45</option><option value="10:00" <?= ($store_afternoon_close??'')==='10:00'?'selected':'' ?>>10:00</option><option value="10:15" <?= ($store_afternoon_close??'')==='10:15'?'selected':'' ?>>10:15</option><option value="10:30" <?= ($store_afternoon_close??'')==='10:30'?'selected':'' ?>>10:30</option><option value="10:45" <?= ($store_afternoon_close??'')==='10:45'?'selected':'' ?>>10:45</option><option value="11:00" <?= ($store_afternoon_close??'')==='11:00'?'selected':'' ?>>11:00</option><option value="11:15" <?= ($store_afternoon_close??'')==='11:15'?'selected':'' ?>>11:15</option><option value="11:30" <?= ($store_afternoon_close??'')==='11:30'?'selected':'' ?>>11:30</option><option value="11:45" <?= ($store_afternoon_close??'')==='11:45'?'selected':'' ?>>11:45</option><option value="12:00" <?= ($store_afternoon_close??'')==='12:00'?'selected':'' ?>>12:00</option><option value="12:15" <?= ($store_afternoon_close??'')==='12:15'?'selected':'' ?>>12:15</option><option value="12:30" <?= ($store_afternoon_close??'')==='12:30'?'selected':'' ?>>12:30</option><option value="12:45" <?= ($store_afternoon_close??'')==='12:45'?'selected':'' ?>>12:45</option><option value="13:00" <?= ($store_afternoon_close??'')==='13:00'?'selected':'' ?>>13:00</option><option value="13:15" <?= ($store_afternoon_close??'')==='13:15'?'selected':'' ?>>13:15</option><option value="13:30" <?= ($store_afternoon_close??'')==='13:30'?'selected':'' ?>>13:30</option><option value="13:45" <?= ($store_afternoon_close??'')==='13:45'?'selected':'' ?>>13:45</option><option value="14:00" <?= ($store_afternoon_close??'')==='14:00'?'selected':'' ?>>14:00</option><option value="14:15" <?= ($store_afternoon_close??'')==='14:15'?'selected':'' ?>>14:15</option><option value="14:30" <?= ($store_afternoon_close??'')==='14:30'?'selected':'' ?>>14:30</option><option value="14:45" <?= ($store_afternoon_close??'')==='14:45'?'selected':'' ?>>14:45</option><option value="15:00" <?= ($store_afternoon_close??'')==='15:00'?'selected':'' ?>>15:00</option><option value="15:15" <?= ($store_afternoon_close??'')==='15:15'?'selected':'' ?>>15:15</option><option value="15:30" <?= ($store_afternoon_close??'')==='15:30'?'selected':'' ?>>15:30</option><option value="15:45" <?= ($store_afternoon_close??'')==='15:45'?'selected':'' ?>>15:45</option><option value="16:00" <?= ($store_afternoon_close??'')==='16:00'?'selected':'' ?>>16:00</option><option value="16:15" <?= ($store_afternoon_close??'')==='16:15'?'selected':'' ?>>16:15</option><option value="16:30" <?= ($store_afternoon_close??'')==='16:30'?'selected':'' ?>>16:30</option><option value="16:45" <?= ($store_afternoon_close??'')==='16:45'?'selected':'' ?>>16:45</option><option value="17:00" <?= ($store_afternoon_close??'')==='17:00'?'selected':'' ?>>17:00</option><option value="17:15" <?= ($store_afternoon_close??'')==='17:15'?'selected':'' ?>>17:15</option><option value="17:30" <?= ($store_afternoon_close??'')==='17:30'?'selected':'' ?>>17:30</option><option value="17:45" <?= ($store_afternoon_close??'')==='17:45'?'selected':'' ?>>17:45</option><option value="18:00" <?= ($store_afternoon_close??'')==='18:00'?'selected':'' ?>>18:00</option><option value="18:15" <?= ($store_afternoon_close??'')==='18:15'?'selected':'' ?>>18:15</option><option value="18:30" <?= ($store_afternoon_close??'')==='18:30'?'selected':'' ?>>18:30</option><option value="18:45" <?= ($store_afternoon_close??'')==='18:45'?'selected':'' ?>>18:45</option><option value="19:00" <?= ($store_afternoon_close??'')==='19:00'?'selected':'' ?>>19:00</option><option value="19:15" <?= ($store_afternoon_close??'')==='19:15'?'selected':'' ?>>19:15</option><option value="19:30" <?= ($store_afternoon_close??'')==='19:30'?'selected':'' ?>>19:30</option><option value="19:45" <?= ($store_afternoon_close??'')==='19:45'?'selected':'' ?>>19:45</option><option value="20:00" <?= ($store_afternoon_close??'')==='20:00'?'selected':'' ?>>20:00</option><option value="20:15" <?= ($store_afternoon_close??'')==='20:15'?'selected':'' ?>>20:15</option><option value="20:30" <?= ($store_afternoon_close??'')==='20:30'?'selected':'' ?>>20:30</option><option value="20:45" <?= ($store_afternoon_close??'')==='20:45'?'selected':'' ?>>20:45</option><option value="21:00" <?= ($store_afternoon_close??'')==='21:00'?'selected':'' ?>>21:00</option><option value="21:15" <?= ($store_afternoon_close??'')==='21:15'?'selected':'' ?>>21:15</option><option value="21:30" <?= ($store_afternoon_close??'')==='21:30'?'selected':'' ?>>21:30</option><option value="21:45" <?= ($store_afternoon_close??'')==='21:45'?'selected':'' ?>>21:45</option><option value="22:00" <?= ($store_afternoon_close??'')==='22:00'?'selected':'' ?>>22:00</option><option value="22:15" <?= ($store_afternoon_close??'')==='22:15'?'selected':'' ?>>22:15</option><option value="22:30" <?= ($store_afternoon_close??'')==='22:30'?'selected':'' ?>>22:30</option><option value="22:45" <?= ($store_afternoon_close??'')==='22:45'?'selected':'' ?>>22:45</option><option value="23:00" <?= ($store_afternoon_close??'')==='23:00'?'selected':'' ?>>23:00</option><option value="23:15" <?= ($store_afternoon_close??'')==='23:15'?'selected':'' ?>>23:15</option><option value="23:30" <?= ($store_afternoon_close??'')==='23:30'?'selected':'' ?>>23:30</option><option value="23:45" <?= ($store_afternoon_close??'')==='23:45'?'selected':'' ?>>23:45</option></select></label></div><small style="display:block;color:var(--text3);font-size:.68rem;margin-top:5px">انتخاب ساعت با فرمت ۲۴ ساعته انجام می‌شود.</small></div>
      <script>
(function(){
  var sel=document.querySelector('select[name="store_morning_open"]');
  if(!sel)return;
  var form=sel.closest('form');
  if(!form)return;
  function val(n){var el=form.querySelector('select[name="'+n+'"]');return el?el.value:''}
  function toMin(v){if(!v)return null;var p=v.split(':');return parseInt(p[0],10)*60+parseInt(p[1],10)}
  function label(n){var m={store_morning_open:'شروع صبح',store_morning_close:'پایان صبح',store_afternoon_open:'شروع بعدازظهر',store_afternoon_close:'پایان بعدازظهر'};return m[n]}
  function checkRange(openN,closeN,title){
    var o=toMin(val(openN)), c=toMin(val(closeN));
    if(o===null && c===null)return [];
    if(o===null || c===null)return ['برای '+title+' هر دو ساعت شروع و پایان را انتخاب کنید.'];
    if(o>=c)return ['ساعت '+label(openN)+' باید قبل از ساعت '+label(closeN)+' باشد.'];
    return [];
  }
  function validate(){
    var errs=[];
    errs=errs.concat(checkRange('store_morning_open','store_morning_close','بازه‌ی صبح'));
    errs=errs.concat(checkRange('store_afternoon_open','store_afternoon_close','بازه‌ی بعدازظهر'));
    var mc=toMin(val('store_morning_close')), ao=toMin(val('store_afternoon_open'));
    if(mc!==null && ao!==null && mc>ao) errs.push('ساعت پایان صبح نباید بعد از ساعت شروع بعدازظهر باشد.');
    return errs;
  }
  form.addEventListener('submit',function(e){
    if(!e.submitter || e.submitter.name!=='save_store_schedule') {
      if(!(e.submitter && e.submitter.name==='save_store_schedule')) return;
    }
    var errs=validate();
    if(errs.length){
      e.preventDefault();
      var msg=errs.join(' ؛ ');
      var t=document.getElementById('toast');
      if(t){t.textContent=msg;t.classList.add('show');clearTimeout(t._timer);t._timer=setTimeout(function(){t.classList.remove('show')},4000)}
    }
  });
})();
</script>
<div class="fg"><label>پیام تعطیلی روز</label><input type="text" name="store_closed_day_message" value="<?= htmlspecialchars($store_closed_day_message??'',ENT_QUOTES,'UTF-8') ?>"></div>
      <div class="fg"><label>پیام خارج از ساعت کاری</label><input type="text" name="store_closed_time_message" value="<?= htmlspecialchars($store_closed_time_message??'',ENT_QUOTES,'UTF-8') ?>"></div>
      <div class="fg"><button type="submit" name="save_store_schedule" value="1" class="btn-p">ذخیره روزها و ساعات کاری</button></div>
    </form>
  </div>


  <!-- ۵. هزینه و حالت‌های ارسال -->
  <div class="acc-hdr" onclick="toggleAcc(this)"><h3>۴. هزینه و حالت‌های ارسال</h3><span class="acc-icon">+</span></div>
  <div class="acc-body">
    <?php $ship_cfg = sitak_shipping_settings($conn); ?>
    <form method="post">
      <input type="hidden" name="save_shipping_settings" value="1">
      <div class="fg"><label>۱. هزینه‌ی ارسال بصورت عادی (تومان)</label><input type="text" inputmode="numeric" name="shipping_general_fee" value="<?= htmlspecialchars((string)$ship_cfg['general_fee'],ENT_QUOTES,'UTF-8') ?>"><small style="display:block;color:var(--text3);font-size:.68rem;margin-top:5px">این هزینه برای همه‌ی سفارش‌ها خارج از تحویل در محل اعمال می‌شود.</small></div>
            <div class="fg"><label>۲. هزینه‌ی ارسال بصورت تحویل در محل (تومان)</label><input type="text" inputmode="numeric" name="shipping_scope_fee" value="<?= htmlspecialchars((string)$ship_cfg['scope_fee'],ENT_QUOTES,'UTF-8') ?>"><small style="display:block;color:var(--text3);font-size:.68rem;margin-top:5px">این هزینه فقط برای سفارش‌هایی اعمال می‌شود که نشانی گیرنده‌شان در محدوده‌ی تحویل در محل (استان و شهر تنظیم‌شده در بخش ۴) باشد.</small></div>
      <div class="fg"><label>۳. حداقل مقدار خرید برای ارسال بصورت تحویل در محل (تومان)</label><input type="text" inputmode="numeric" name="shipping_scope_min" value="<?= htmlspecialchars((string)$ship_cfg['scope_min'],ENT_QUOTES,'UTF-8') ?>"><small style="display:block;color:var(--text3);font-size:.68rem;margin-top:5px">۰ یعنی بدون حداقل. اگر سبد کمتر از این مبلغ باشد، ثبت سفارش با پیام حداقل خرید متوقف می‌شود.</small></div>
      <div class="fg"><label>۴. حداقل مقدار خرید با ارسال رایگان بصورت عادی (تومان)</label><input type="text" inputmode="numeric" name="shipping_general_free_min" value="<?= htmlspecialchars((string)$ship_cfg['general_free_min'],ENT_QUOTES,'UTF-8') ?>"><small style="display:block;color:var(--text3);font-size:.68rem;margin-top:5px">۰ یعنی غیرفعال.</small></div>
<div class="fg"><label>۵. حداقل مقدار خرید با ارسال رایگان بصورت تحویل در محل (تومان)</label><input type="text" inputmode="numeric" name="shipping_scope_free_min" value="<?= htmlspecialchars((string)$ship_cfg['scope_free_min'],ENT_QUOTES,'UTF-8') ?>"><small style="display:block;color:var(--text3);font-size:.68rem;margin-top:5px">۰ یعنی غیرفعال.</small></div>
      <div class="fg"><label>۶. درصد مانده تا ارسال رایگان برای محصولات دارای ارسال عمومی (٪)</label><div class="stat-row"><button type="button" class="btn-h minus" onclick="stepNum('shipGeneralEncouragePct',-1)">−</button><input type="text" inputmode="numeric" class="stat-input fa-number" id="shipGeneralEncouragePct" name="shipping_general_encourage_pct" min="0" max="100" value="<?= htmlspecialchars((string)$ship_cfg['general_encourage_pct'],ENT_QUOTES,'UTF-8') ?>"><button type="button" class="btn-h plus" onclick="stepNum('shipGeneralEncouragePct',1)">+</button></div><small style="display:block;color:var(--text3);font-size:.68rem;margin-top:5px">مثلاً ۲۰ یعنی وقتی سبد به ۸۰٪ حداقل ارسال رایگان رسید، پیام تشویقی نمایش داده شود. ۰ یعنی غیرفعال.</small></div>
      <div class="fg"><label>۷. درصد مانده تا ارسال رایگان برای محصولات دارای تحویل در محل (٪)</label><div class="stat-row"><button type="button" class="btn-h minus" onclick="stepNum('shipScopeEncouragePct',-1)">−</button><input type="text" inputmode="numeric" class="stat-input fa-number" id="shipScopeEncouragePct" name="shipping_scope_encourage_pct" min="0" max="100" value="<?= htmlspecialchars((string)$ship_cfg['scope_encourage_pct'],ENT_QUOTES,'UTF-8') ?>"><button type="button" class="btn-h plus" onclick="stepNum('shipScopeEncouragePct',1)">+</button></div><small style="display:block;color:var(--text3);font-size:.68rem;margin-top:5px">مثلاً ۲۰ یعنی وقتی سبد به ۸۰٪ حداقل ارسال رایگان محدوده رسید، پیام تشویقی نمایش داده شود. ۰ یعنی غیرفعال.</small></div>
      <div class="fg"><button type="submit" name="save_shipping_settings" value="1" class="btn-p">ذخیره هزینه و محدودیت ارسال</button></div>
      
      <div class="fg"><small style="display:block;color:var(--text3);font-size:.68rem">محدوده‌ی ارسال از بخش «۴. محدوده فعالیت» خوانده می‌شود (اگر استان آنجا خالی باشد، محدوده‌ای تعریف نشده و فقط قوانین عادی اعمال می‌شود). اگر نشانی گیرنده در محدوده باشد، قوانین محدوده (شماره‌های ۲، ۳ و ۵) اعمال می‌شود؛ در غیر این صورت هزینه‌ی عادی (۱) و ارسال رایگان عادی (۴).</small></div>
    </form>
  </div>

  <div class="acc-hdr" onclick="toggleAcc(this)"><h3>۵. مدیریت کدهای تخفیف</h3><span class="acc-icon">+</span></div>
  <div class="acc-body">
    <?php
      $dc_on = getSetting($conn, 'discount_enabled', '0') === '1';
      $dc_err = (string)($_GET['dc_err'] ?? '');
      $dc_products = [];
      $dcp = $conn->query("SELECT id, name FROM products ORDER BY sort_order, name");
      while ($dcr = $dcp->fetch_assoc()) $dc_products[] = $dcr;
      $dc_base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
    ?>
    <form method="post" style="margin-bottom:14px">
      <input type="hidden" name="save_discount_enabled" value="1">
      <div class="toggle-row" style="margin-bottom:10px">
        <label class="toggle"><input type="checkbox" name="discount_enabled" value="1" <?= $dc_on ? 'checked' : '' ?>><span class="slider"></span></label>
        <span class="toggle-label">فعال بودن قابلیت کد تخفیف در سایت</span>
      </div>
      <button type="submit" class="btn-p" style="font-size:.82rem">ذخیره وضعیت تخفیف</button>
    </form>
    <?php if ($dc_err !== ''): ?>
      <div style="background:var(--red-bg);color:var(--red);border-radius:var(--rss);padding:10px 12px;font-size:.78rem;margin-bottom:12px"><?= htmlspecialchars($dc_err, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <div style="font-size:.82rem;font-weight:700;color:#1a8a3f;margin-bottom:8px" id="dcFormTitle">ثبت کد تخفیف جدید</div>
    <form method="post" id="dcForm">
      <input type="hidden" name="save_discount_code" value="1">
      <input type="hidden" name="dc_id" id="dcId" value="0">
      <div class="fg"><label>کد تخفیف (حروف انگلیسی، عدد، - یا _)</label><input type="text" class="fi" name="dc_code" id="dcCode" maxlength="40" dir="ltr" style="text-align:left" required></div>
      <div class="fg"><label>نوع تخفیف</label>
        <select class="fi" name="dc_type" id="dcType"><option value="amount">مقداری (تومان)</option><option value="percent">درصدی (٪)</option></select></div>
      <div class="fg"><label>مقدار تخفیف (تومان یا درصد)</label><input type="text" inputmode="numeric" class="fi" name="dc_value" id="dcValue" required></div>
      <div class="fg"><label>سقف تخفیف برای نوع درصدی (تومان، ۰ یعنی بدون سقف)</label><input type="text" inputmode="numeric" class="fi" name="dc_max" id="dcMax" value="0"></div>
      <div class="fg"><label>حداقل مقدار خرید برای استفاده (تومان، ۰ یعنی بدون حداقل)</label><input type="text" inputmode="numeric" class="fi" name="dc_min" id="dcMin" value="0"></div>
      <div class="fg"><label>تعداد دفعات قابل استفاده (۰ یعنی نامحدود)</label><div class="stat-row"><button type="button" class="btn-h minus" onclick="stepNum('dcLimit',-1)">−</button><input type="text" inputmode="numeric" class="stat-input" name="dc_limit" id="dcLimit" value="0" min="0"><button type="button" class="btn-h plus" onclick="stepNum('dcLimit',1)">+</button></div></div>
      <div class="fg"><label>مدت اعتبار کد (روز از لحظه‌ی ثبت یا تغییر مدت؛ ۰ یعنی بدون تاریخ انقضا)</label>
        <div class="stat-row"><button type="button" class="btn-h minus" onclick="stepNum('dcDays',-1)">−</button><input type="text" inputmode="numeric" class="stat-input" name="dc_days" id="dcDays" value="0" min="0"><button type="button" class="btn-h plus" onclick="stepNum('dcDays',1)">+</button></div>
      </div>
      <div class="fg"><label>فقط برای این کاربر یا کاربران (خالی یعنی همه؛ چند شماره را با ویرگول جدا کنید)</label><textarea class="fi" name="dc_phone" id="dcPhone" rows="2" dir="ltr" style="text-align:left" placeholder="09123456789, 09351234567"></textarea></div>
      <div class="fg"><label>فقط برای این محصول‌ها (هیچ انتخابی نشده یعنی همه‌ی محصولات)</label>
        <div id="dcProductBox">
          <div class="modal-acc-hdr" onclick="dcToggleBox(this)"><span id="dcProductSumText">همه‌ی محصولات</span><span class="modal-acc-icon">+</span></div>
          <div class="modal-acc-body">
            <?php foreach ($dc_products as $dp): ?>
            <label style="display:flex;align-items:center;gap:10px;padding:9px 2px;font-size:.82rem;cursor:pointer;border-bottom:1px solid var(--border)"><input type="checkbox" name="dc_product[]" value="<?= (int)$dp['id'] ?>" onchange="dcProductChanged()" style="width:18px;height:18px;margin:0"><span><?= htmlspecialchars($dp['name'], ENT_QUOTES, 'UTF-8') ?></span></label>
            <?php endforeach; ?>
          </div>
        </div></div>
      <div class="toggle-row" style="margin-bottom:12px">
        <label class="toggle"><input type="checkbox" name="dc_active" id="dcActive" value="1" checked><span class="slider"></span></label>
        <span class="toggle-label">کد فعال باشد</span>
      </div>
      <div class="toggle-row" style="margin-bottom:12px">
        <label class="toggle"><input type="checkbox" name="dc_link" id="dcLink" value="1" checked><span class="slider"></span></label>
        <span class="toggle-label">لینک تخفیف برای این کد فعال باشد</span>
      </div>
      <div class="fg"><button type="submit" class="btn-p" style="font-size:.82rem">ذخیره کد تخفیف</button></div>
      <div class="fg"><button type="button" id="dcCancelBtn" class="btn-sm" onclick="resetDiscountForm()" style="display:none;width:100%;background:var(--card-el);color:var(--text2);padding:10px 16px;border-radius:var(--rss);font-size:.82rem;font-weight:700;justify-content:center;;">انصراف از ویرایش</button></div>
    </form>

    <div class="tbl-wrap" style="margin-top:18px">
    <table><thead><tr><th>کد</th><th>تخفیف</th><th>سقف / حداقل خرید</th><th>استفاده</th><th>کاربر / محصول</th><th style="text-align:center">وضعیت</th><th style="text-align:center">لینک</th><th style="text-align:center">عملیات</th></tr></thead><tbody>
    <?php $dc_pmap = []; foreach ($dc_products as $dpm) $dc_pmap[(int)$dpm['id']] = $dpm['name'];
      [$sd_pp,$sd_pg]=admin_list_page('sd'); $sd_total=(int)$conn->query("SELECT COUNT(*) AS c FROM discount_codes")->fetch_assoc()["c"]; $sd_pg=min($sd_pg,max(1,(int)ceil($sd_total/$sd_pp))); $sd_off=($sd_pg-1)*$sd_pp; $dcl = $conn->query("SELECT d.* FROM discount_codes d ORDER BY d.created_at DESC LIMIT $sd_off,$sd_pp");
      while ($dc = $dcl->fetch_assoc()):
        $dc_val = $dc['type'] === 'percent' ? pn($dc['value']).'٪' : pn(number_format($dc['value'])).' تومان';
        $dc_cap = ($dc['type'] === 'percent' && (int)$dc['max_discount'] > 0) ? 'سقف '.pn(number_format($dc['max_discount'])).' تومان' : '—';
        $dc_min_txt = (int)$dc['min_subtotal'] > 0 ? pn(number_format($dc['min_subtotal'])).' تومان' : 'بدون حداقل';
        $dc_use = pn($dc['used_count']).' از '.($dc['usage_limit'] === null ? 'نامحدود' : pn($dc['usage_limit']));
        $dc_who = $dc['target_phone'] ? pn($dc['target_phone']) : 'همه‌ی کاربران';
        $dc_prod_names = [];
        foreach (array_filter(explode(',', (string)$dc['product_id'])) as $dpid) $dc_prod_names[] = $dc_pmap[(int)$dpid] ?? 'محصول حذف‌شده';
        $dc_prod = $dc_prod_names ? implode('، ', $dc_prod_names) : 'همه‌ی محصولات';
        $dc_link = $dc_base.'/index.php?coupon='.urlencode($dc['code']);
    ?>
    <tr>
      <td style="direction:ltr;text-align:center"><b><?= htmlspecialchars($dc['code'], ENT_QUOTES, 'UTF-8') ?></b></td>
      <td style="text-align:center"><?= $dc_val ?></td>
      <td style="text-align:center"><?= $dc_cap ?><br><small><?= $dc_min_txt ?></small></td>
      <td style="text-align:center"><?= $dc_use ?><br><small><?= !empty($dc['expires_at']) ? 'تا '.jdate_fa($dc['expires_at']) : 'بدون انقضا' ?></small></td>
      <td style="text-align:center"><?= htmlspecialchars($dc_who, ENT_QUOTES, 'UTF-8') ?><br><small><?= htmlspecialchars($dc_prod, ENT_QUOTES, 'UTF-8') ?></small></td>
      <td style="text-align:center"><span class="tbl-act-status" title="<?= $dc['is_active'] ? 'فعال' : 'غیرفعال' ?>"><?= $dc['is_active'] ? '✅' : '⛔' ?></span></td>
      <td style="text-align:center"><?php if ((int)$dc['link_enabled'] === 1): ?><button type="button" class="btn-sm act-neutral" data-link="<?= htmlspecialchars($dc_link, ENT_QUOTES, 'UTF-8') ?>" onclick="copyDiscountLink(this)">کپی 🔗</button><?php else: ?>—<?php endif; ?></td>
      <td style="white-space:nowrap;text-align:center">
        <span class="btn-sm btn-blue" style="cursor:pointer" title="ویرایش" onclick='startDiscountEdit(<?= htmlspecialchars(json_encode($dc, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>)'>✏️</span>
        <form method="post" style="display:inline"><input type="hidden" name="toggle_discount_code" value="1"><input type="hidden" name="dc_id" value="<?= (int)$dc['id'] ?>"><button type="submit" class="btn-sm act-toggle" title="<?= $dc['is_active'] ? 'غیرفعال کردن' : 'فعال کردن' ?>"><?= $dc['is_active'] ? '⛔' : '✅' ?></button></form>
      
        <form method="post" id="dcDel<?= (int)$dc['id'] ?>" style="display:inline"><input type="hidden" name="delete_discount_code" value="1"><input type="hidden" name="dc_id" value="<?= (int)$dc['id'] ?>"><button type="button" class="btn-sm btn-red" title="حذف" style="font-size:.68rem" onclick="armDiscountDelete(this)">🗑️</button></form>
      </td>
    </tr>
    <?php endwhile; ?></tbody></table>
    </div>
    <?= admin_pager('sd', null, $sd_total, $sd_pp, $sd_pg) ?>
  </div>

  <!-- ۶. تنظیمات فروشگاه -->
  <div class="acc-hdr" onclick="toggleAcc(this)"><h3>۶. تنظیمات فروشگاه</h3><span class="acc-icon">+</span></div>
  <div class="acc-body">
    <form method="post">
      <div class="toggle-row">
        <label class="toggle"><input type="checkbox" name="shop_enabled" value="1" <?= $shop_enabled==='1'?'checked':'' ?>><span class="slider"></span></label>
        <span class="toggle-label">نمایش تب منو در سایت</span>
      </div>
      <div class="toggle-row">
        <label class="toggle"><input type="checkbox" name="orders_tab_enabled" value="1" <?= ($orders_tab_enabled??'1')==='1'?'checked':'' ?>><span class="slider"></span></label>
        <span class="toggle-label">نمایش تب سفارشات در سایت</span>
      </div>
      <div class="toggle-row">
        <label class="toggle"><input type="checkbox" name="home_shop_enabled" value="1" <?= $home_shop_enabled==='1'?'checked':'' ?>><span class="slider"></span></label>
        <span class="toggle-label">نمایش بخش محصولات در صفحه‌ی اصلی</span>
      </div>
      <div class="fg"><label>تعداد محصولات در صفحه‌ی اصلی</label><div class="stat-row"><button type="button" class="btn-h minus" onclick="stepNum('homeShopCount',-1)">−</button><input type="text" inputmode="numeric" class="stat-input fa-number" id="homeShopCount" name="home_shop_count" min="1" max="50" value="<?= htmlspecialchars((string)$home_shop_count_admin,ENT_QUOTES,'UTF-8') ?>"><button type="button" class="btn-h plus" onclick="stepNum('homeShopCount',1)">+</button></div></div>
      <div class="toggle-row">
        <label class="toggle"><input type="checkbox" name="show_product_ratings" value="1" <?= ($show_product_ratings_admin??'1')==='1'?'checked':'' ?>><span class="slider"></span></label>
        <span class="toggle-label">نمایش میانگین امتیاز محصولات (صفحه‌ی محصول و لیست منو)</span>
      </div>
      <div class="fg"><label>نوع نمایش محصولات در صفحه‌ی اصلی</label>
          <select name="home_shop_type">
            <option value="best" <?= $home_shop_type==='newest'?'':'selected' ?>>پرفروش‌ترین محصولات</option>
            <option value="newest" <?= $home_shop_type==='newest'?'selected':'' ?>>تازه‌ترین محصولات</option>
          </select>
      </div>
      <div class="fg"><label>عنوان هدر صفحه منو</label><input type="text" name="shop_page_title" value="<?= htmlspecialchars($shop_page_title??'', ENT_QUOTES, 'UTF-8') ?>"></div>
      <div class="toggle-row" style="margin-bottom:12px">
        <label class="toggle"><input type="checkbox" name="stock_badge_enabled" value="1" <?= getSetting($conn, 'stock_badge_enabled', '1') === '1' ? 'checked' : '' ?>><span class="slider"></span></label>
        <span class="toggle-label">نمایش حباب کمبود موجودی محصول</span>
      </div>
      <div class="fg"><label>حباب کمبود موجودی وقتی نمایش داده شود که موجودی به این عدد یا کمتر برسد</label>
        <div class="stat-row"><button type="button" class="btn-h minus" onclick="stepNum('stockBadgeThreshold',-1)">−</button><input type="text" inputmode="numeric" class="stat-input" name="stock_badge_threshold" id="stockBadgeThreshold" value="<?= (int)getSetting($conn, 'stock_badge_threshold', '3') ?>"><button type="button" class="btn-h plus" onclick="stepNum('stockBadgeThreshold',1)">+</button></div>
      </div>
      <div class="fg"><label>زیرمتن هدر صفحه منو</label><textarea name="shop_page_description" rows="2"><?= htmlspecialchars($shop_page_description??'', ENT_QUOTES, 'UTF-8') ?></textarea></div>
      <div class="fg"><label>عنوان هدر صفحه سفارش</label><input type="text" name="orders_page_title" value="<?= htmlspecialchars($orders_page_title??'', ENT_QUOTES, 'UTF-8') ?>"></div>
      <div class="fg"><label>زیرمتن هدر صفحه سفارش</label><textarea name="orders_page_description" rows="2"><?= htmlspecialchars($orders_page_description??'', ENT_QUOTES, 'UTF-8') ?></textarea></div>
      <div class="fg"><button type="submit" name="save_shop_settings" class="btn-p">ذخیره تنظیمات فروشگاه</button></div>
      <p style="font-size:.72rem;color:var(--text3);line-height:1.6;margin-top:4px">تب منو، فروشگاه و سبد خرید را کنترل می‌کند و تب سفارشات فقط صفحه‌ی سفارش‌ها را. بخش محصولات صفحه‌ی اصلی جداست و نوع نمایش یکی از دو حالت است.</p>
    </form>
  </div>


</div>

<style>
#toast.toast{background:linear-gradient(135deg,var(--gold,#ecb534),var(--gold-dark,#c49625));color:#fff;border:none;font-weight:500;border-radius:16px;padding:13px 22px;box-shadow:0 6px 20px rgba(0,0,0,.2);bottom:28px;white-space:pre-line}
</style>
<div class="toast" id="toast"></div>

<script>
function startShopCatEdit(id, name, sort){document.getElementById('shopCatId').value=id;document.getElementById('shopCatName').value=name;document.getElementById('shopCatSort').value=sort;var lbl=document.getElementById('shopCatLabel'); if(lbl) lbl.textContent='ویرایش دسته‌بندی';var btn=document.getElementById('shopCatSubmitBtn'); if(btn) btn.textContent='بروزرسانی دسته';document.getElementById('shopCatCancelBtn').style.display='';document.getElementById('shopCatName').focus();}function cancelShopCatEdit(){document.getElementById('shopCatId').value='0';document.getElementById('shopCatName').value='';document.getElementById('shopCatSort').value='0';var lbl=document.getElementById('shopCatLabel'); if(lbl) lbl.textContent='نام دسته‌بندی';var btn=document.getElementById('shopCatSubmitBtn'); if(btn) btn.textContent='افزودن دسته';document.getElementById('shopCatCancelBtn').style.display='none';}
function toggleProductLimits(btn){var panel=btn.nextElementSibling;var open=panel.classList.toggle('open');btn.classList.toggle('open',open);btn.setAttribute('aria-expanded',open?'true':'false');}
<?php admin_common_js(); ?>

const editor = document.getElementById('rteEditor');
const descInp = document.getElementById('productDesc');

if (editor && descInp) {
  editor.innerHTML = descInp.value;
}

function saveRteSel(){var s=window.getSelection();return(s&&s.rangeCount)?s.getRangeAt(0).cloneRange():null;}
function restoreRteSel(r){if(typeof editor!=='undefined'&&editor){editor.focus();}if(!r)return;var s=window.getSelection();s.removeAllRanges();s.addRange(r);}
async function execRteTable(){
  var __sel=saveRteSel();
  var rs=await adminPrompt({title:'جدول جدید',message:'تعداد سطرها؟',defaultValue:'3'}); if(rs===null) return;
  var cs=await adminPrompt({title:'جدول جدید',message:'تعداد ستون‌ها؟',defaultValue:'3'}); if(cs===null) return;
  var r=parseInt(rs,10), c=parseInt(cs,10);
  restoreRteSel(__sel);
  if(!(r>0&&c>0)) return;
  var h='<table style="width:100%;border-collapse:collapse;margin:12px 0"><tbody>';
  for(var i=0;i<r;i++){h+='<tr>';for(var j=0;j<c;j++){h+='<td style="border:1px solid #bbb;padding:6px 8px">&nbsp;</td>';}h+='</tr>';}
  h+='</tbody></table><p><br></p>';
  execRte('insertHTML', h);
}
function execRte(cmd, val=null){
  document.execCommand(cmd, false, val);
  syncSource();
}
async function execRteLink(){
  const __sel=saveRteSel();
  const url = await adminPrompt({title:'افزودن لینک',message:'آدرس لینک را وارد کنید:',placeholder:'https://',defaultValue:'https://'});
  restoreRteSel(__sel);
  if(url) execRte('createLink', url);
}
async function execRteImage(){
  const __sel=saveRteSel();
  const url = await adminPrompt({title:'افزودن تصویر',message:'آدرس تصویر را وارد کنید:',placeholder:'https://',defaultValue:'https://'});
  restoreRteSel(__sel);
  if(url) execRte('insertImage', url);
}
function syncSource(){
  if (descInp && editor) descInp.value = editor.innerHTML;
}
if (editor) editor.addEventListener('input', syncSource);

var prodForm = document.getElementById('productForm');
window.syncEditUI = function(){
  var idEl = document.getElementById('productId');
  var editing = !!idEl && parseInt(idEl.value, 10) > 0;
  var btn = document.querySelector('#productForm button[name="save_product"]');
  if (btn) btn.textContent = editing ? 'بروزرسانی محصول' : 'افزودن محصول';
  var cw = document.querySelector('#productForm .js-cancel-wrap');
  if (cw) cw.style.display = editing ? '' : 'none';
};
window.cancelProductEdit = function(){
  var f = document.getElementById('productForm');
  if (f) {
    // پیش‌نمایش عکس قبلی (که از حالت ویرایش آمده) باید برای افزودن محصول جدید حذف شود
    f.querySelectorAll('button[name="delete_product_image"]').forEach(function(b){ if (b.parentElement) b.parentElement.remove(); });
    f.reset();
  }
  var idEl = document.getElementById('productId'); if (idEl) idEl.value = '0';
  var ed = document.getElementById('rteEditor'); if (ed) ed.innerHTML = '';
  var de = document.getElementById('productDesc'); if (de) de.value = '';
  window.syncEditUI();
};
window.addEventListener('pageshow', window.syncEditUI);
document.addEventListener('DOMContentLoaded', window.syncEditUI);
if (prodForm) {
  var pNameEl = document.getElementById('productName'), pPriceEl = document.getElementById('productPrice');
  [pNameEl, pPriceEl].forEach(function(el){ if (el) el.addEventListener('input', function(){ el.classList.remove('is-invalid'); }); });
  prodForm.addEventListener('submit', function(e){
    var price = parseInt(String(pPriceEl.value).replace(/[۰-۹]/g, function(d){ return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d); }).replace(/[^0-9]/g, ''), 10) || 0;
    var okName = pNameEl.value.trim() !== '';
    var okPrice = price > 0;
    pNameEl.classList.toggle('is-invalid', !okName);
    pPriceEl.classList.toggle('is-invalid', !okPrice);
    if (!okName || !okPrice) { e.preventDefault(); (!okName ? pNameEl : pPriceEl).focus(); }
  });
}
if (prodForm && descInp && editor) {
  prodForm.addEventListener('submit', function(){
    descInp.value = editor.innerHTML;
  });
}
</script>
<?php
admin_footer_nav('shop');
?>
<script>
function startDiscountEdit(d){
  document.getElementById('dcFormTitle').textContent='ویرایش کد: '+d.code;
  document.getElementById('dcCancelBtn').style.display='inline-flex';
  document.getElementById('dcId').value=d.id;
  document.getElementById('dcCode').value=d.code;
  document.getElementById('dcType').value=d.type;
  document.getElementById('dcValue').value=d.value;
  document.getElementById('dcMax').value=d.max_discount||0;
  document.getElementById('dcMin').value=d.min_subtotal||0;
  document.getElementById('dcLimit').value=d.usage_limit||0;
  document.getElementById('dcDays').value=d.valid_days||0;
  document.getElementById('dcPhone').value=d.target_phone||'';
  var dpl=String(d.product_id||'').split(',').filter(Boolean);
  document.querySelectorAll('#dcProductBox input[name="dc_product[]"]').forEach(function(c){c.checked=dpl.indexOf(c.value)!==-1});
  dcProductChanged();
  document.getElementById('dcActive').checked=!!parseInt(d.is_active,10);
  document.getElementById('dcLink').checked=!!parseInt(d.link_enabled,10);
  var f=document.getElementById('dcForm');f.scrollIntoView({behavior:'smooth',block:'center'});
}
function copyDiscountLink(btn){
  var url=location.origin+btn.dataset.link;
  var done=function(){btn.textContent='کپی شد ✅';setTimeout(function(){btn.textContent='کپی 🔗'},1500)};
  var fallback=function(){var t=document.createElement('textarea');t.value=url;t.style.position='fixed';t.style.opacity='0';document.body.appendChild(t);t.select();try{document.execCommand('copy');done()}catch(e){}t.remove()};
  if(navigator.clipboard&&window.isSecureContext){navigator.clipboard.writeText(url).then(done).catch(fallback)}else{fallback()}
}
function armDiscountDelete(btn){
  if(!btn.dataset.armed){btn.dataset.armed='1';btn.textContent='⚠️';setTimeout(function(){delete btn.dataset.armed;btn.textContent='🗑️'},4000);return}
  btn.closest('form').submit();
}
function dcToggleBox(el){var b=el.nextElementSibling;var o=b.classList.toggle('open');el.classList.toggle('open',o);var ic=el.querySelector('.modal-acc-icon');if(ic)ic.textContent=o?'−':'+'}
function dcProductChanged(){var names=[];document.querySelectorAll('#dcProductBox input[name="dc_product[]"]:checked').forEach(function(c){names.push(c.parentNode.textContent.trim())});document.getElementById('dcProductSumText').textContent=names.length?names.join('، '):'همه‌ی محصولات'}
function resetDiscountForm(){
  document.getElementById('dcForm').reset();
  dcProductChanged();
  document.getElementById('dcId').value=0;
  document.getElementById('dcFormTitle').textContent='ثبت کد تخفیف جدید';
  document.getElementById('dcCancelBtn').style.display='none';
}
(function(){var sec=new URLSearchParams(location.search).get('sec');if(sec!=='discounts')return;document.querySelectorAll('.acc-hdr').forEach(function(h){if(h.textContent.indexOf('مدیریت کدهای تخفیف')!==-1&&!h.classList.contains('open')){h.click()}});})();
</script>
<?php include 'admin_inline_edit.php'; ?>
<?php include 'admin_ajax_save.php'; ?>
</body>
</html>
