<?php
$admin_self = $admin_self ?? 'admin.php';
require 'admin_common.php';

if (!$is_authenticated) { admin_login_screen($login_error ?? null); exit; }
admin_page_start('خانه');
admin_header();
?>
<div class="container">

  <!-- 1. جستجوی کاربر -->
  <div class="sec-title">۱. جستجوی کاربر</div>
  <div class="search-row">
    <input type="tel" class="fi" id="searchPhone" placeholder="شماره موبایل">
    <button type="button" class="btn-p" onclick="searchUser()" style="font-size:.82rem">بررسی</button>
  </div>

  <!-- 2. برندگان -->
  <div class="acc-hdr open" onclick="toggleAcc(this)"><h3>۲. برندگان واقعی</h3><span class="acc-icon">−</span></div>
  <div class="acc-body open">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
      <span style="font-size:.82rem;font-weight:700;color:#1a8a3f">لیست برندگان واقعی</span>
      <a href="<?= admin_url() ?>?export=winners" class="btn-sm btn-blue" style="font-size:.68rem">📥 CSV</a>
    </div>
    <div class="tbl-wrap">
    <table id="winnersTbl"><thead><tr><th>تلفن</th><th>نام</th><th>جایزه</th><th>زمان</th><th>تحویل</th></tr></thead><tbody>
    <?php [$w_pp,$w_pg]=admin_list_page('w'); $w_total=(int)$conn->query("SELECT COUNT(*) AS c FROM winners WHERE is_fake=0")->fetch_assoc()["c"]; $w_pg=min($w_pg,max(1,(int)ceil($w_total/$w_pp))); $w_off=($w_pg-1)*$w_pp; $wrs=$conn->query("SELECT * FROM winners WHERE is_fake=0 ORDER BY won_at DESC LIMIT $w_off,$w_pp"); while($w=$wrs->fetch_assoc()): ?>
    <tr>
      <td><?= htmlspecialchars($w['phone'],ENT_QUOTES,'UTF-8') ?></td>
      <td><?= htmlspecialchars($w['name']?:'مهمان',ENT_QUOTES,'UTF-8') ?></td>
      <td><?= htmlspecialchars($w['prize_name'],ENT_QUOTES,'UTF-8') ?></td>
      <td><?= time_ago($w['won_at']) ?></td>
      <td style="text-align:center"><span class="tick <?= $w['claimed']?'on':'' ?>" onclick="toggleClaimed(<?= (int)$w['id'] ?>,this)">✔</span></td>
    </tr>
    <?php endwhile; ?></tbody></table>
    </div>
    <?= admin_pager('w', 'winners', $w_total, $w_pp, $w_pg) ?>
  </div>

  <!-- 3. سفارشات -->
  <div class="acc-hdr open" onclick="toggleAcc(this)"><h3>۳. سفارشات</h3><span class="acc-icon">−</span></div>
  <div class="acc-body open">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
      <span style="font-size:.82rem;font-weight:700;color:#1a8a3f">لیست سفارشات</span>
      <a href="<?= admin_url() ?>?export=orders" class="btn-sm btn-blue" style="font-size:.68rem">📥 CSV</a>
    </div>
    <div class="tbl-wrap">
    <table><thead><tr><th style="text-align:center">شماره</th><th>مشتری</th><th style="text-align:center">مبلغ</th><th style="text-align:center">وضعیت</th><th style="text-align:center">زمان</th><th style="text-align:center">عملیات</th></tr></thead><tbody>
    <?php [$o_pp,$o_pg]=admin_list_page('o'); $o_total=(int)$conn->query("SELECT COUNT(*) AS c FROM orders")->fetch_assoc()["c"]; $o_pg=min($o_pg,max(1,(int)ceil($o_total/$o_pp))); $o_off=($o_pg-1)*$o_pp; $os=$conn->query("SELECT * FROM orders ORDER BY created_at DESC LIMIT $o_off,$o_pp"); while($o=$os->fetch_assoc()): ?>
    <tr>
      <td style="text-align:center"><?= (int)$o['id'] ?></td>
      <td><?= htmlspecialchars($o['user_name']?:'مهمان',ENT_QUOTES,'UTF-8') ?><br><small style="color:var(--text3)"><?= htmlspecialchars($o['user_phone']) ?></small></td>
      <td style="text-align:center"><?= number_format($o['total_price']) ?> تومان<?php if (($o['payment_method'] ?? '') === 'card'): ?><br><small style="color:var(--gold-dark);font-weight:700"><?php
        if ($o['status'] === 'pending' && !empty($o['card_submitted_at'])) echo '💳 کارت‌به‌کارت — ⏳ در انتظار بررسی';
        elseif ($o['status'] === 'pending') echo '💳 در انتظار پرداخت';
        else echo '💳 کارت‌به‌کارت';
      ?></small><?php endif; ?></td>
      <td style="text-align:center">
        <select onchange="location.href='<?= admin_url() ?>?update_order=<?= (int)$o['id'] ?>&status='+this.value" style="font-size:.7rem;padding:4px 8px;border-radius:6px;border:1px solid var(--border);background:var(--bg);color:var(--text)">
          <option value="pending" <?= $o['status']==='pending'?'selected':'' ?>>در انتظار</option>
          <option value="confirmed" <?= $o['status']==='confirmed'?'selected':'' ?>>در حال آماده سازی</option>
          <option value="shipped" <?= $o['status']==='shipped'?'selected':'' ?>>ارسال شده</option>
          <option value="delivered" <?= $o['status']==='delivered'?'selected':'' ?>>تحویل داده شده</option>
          <option value="cancelled" <?= $o['status']==='cancelled'?'selected':'' ?>>لغو شده</option>
        </select>
      </td>
      <td style="text-align:center"><?= time_ago($o['created_at']) ?></td>
      <td style="text-align:center">
        <span class="btn-sm act-neutral" style="cursor:pointer" title="مشاهده و ویرایش" onclick="viewOrderDetails(<?= (int)$o['id'] ?>)">🔍</span>
        <span class="btn-sm btn-red" style="cursor:pointer" title="حذف" onclick="deleteOrder(<?= (int)$o['id'] ?>)">🗑️</span>
      </td>
    </tr>
    <?php endwhile; ?></tbody></table>
    </div>
    <?= admin_pager('o', 'orders', $o_total, $o_pp, $o_pg) ?>
  </div>

  <!-- 4. کاربران -->
  <div class="acc-hdr open" onclick="toggleAcc(this)"><h3>۴. کاربران</h3><span class="acc-icon">−</span></div>
  <div class="acc-body open">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
      <span style="font-size:.82rem;font-weight:700;color:#1a8a3f">لیست کاربران</span>
      <a href="<?= admin_url() ?>?export=users" class="btn-sm btn-blue" style="font-size:.68rem">📥 CSV</a>
    </div>
    <div class="tbl-wrap">
    <table id="usersTbl"><thead><tr><th style="text-align:center">تلفن</th><th style="text-align:center">نام</th><th style="text-align:center">معرف</th><th style="text-align:center">مراجعات</th><th style="text-align:center">تاریخ</th><th style="text-align:center">عملیات</th></tr></thead><tbody>
    <?php [$u_pp,$u_pg]=admin_list_page('u'); $u_total=(int)$conn->query("SELECT COUNT(*) AS c FROM users")->fetch_assoc()["c"]; $u_pg=min($u_pg,max(1,(int)ceil($u_total/$u_pp))); $u_off=($u_pg-1)*$u_pp; $ur=$conn->query("SELECT * FROM users ORDER BY created_at DESC LIMIT $u_off,$u_pp"); while($u=$ur->fetch_assoc()): ?>
    <tr>
      <td style="text-align:center"><?= htmlspecialchars($u['phone'],ENT_QUOTES,'UTF-8') ?></td>
      <td style="text-align:center"><?= htmlspecialchars($u['name']?:'بدون نام',ENT_QUOTES,'UTF-8') ?></td>
      <td style="text-align:center"><?= htmlspecialchars($u['referral_code']?:'مستقیم',ENT_QUOTES,'UTF-8') ?></td>
      <td style="font-weight:700;text-align:center"><?= intval($u['visits']??0) ?></td>
      <td style="text-align:center"><?= time_ago($u['created_at']) ?></td>
      <td style="white-space:nowrap;text-align:center"><span class="btn-sm btn-blue" style="cursor:pointer" title="ویرایش" onclick="openUserModal('<?= htmlspecialchars($u['phone'],ENT_QUOTES,'UTF-8') ?>')">✏️</span> <span class="btn-sm btn-red" style="cursor:pointer" title="حذف" onclick="deleteUser('<?= htmlspecialchars($u['phone'],ENT_QUOTES,'UTF-8') ?>')">🗑️</span></td>
    </tr>
    <?php endwhile; ?></tbody></table>
    </div>
    <?= admin_pager('u', 'users', $u_total, $u_pp, $u_pg) ?>
  </div>
  <?php if (getSetting($conn, 'sms_password_login_enabled', '0') === '1'): ?>
  <div class="acc-hdr open" onclick="toggleAcc(this)"><h3>۵. درخواست‌های بازنشانی گذرواژه</h3><span class="acc-icon">−</span></div>
  <div class="acc-body open">
    <?php [$r_pp,$r_pg]=admin_list_page('r'); $r_total=(int)$conn->query("SELECT COUNT(*) AS c FROM password_reset_requests")->fetch_assoc()["c"]; $r_pg=min($r_pg,max(1,(int)ceil($r_total/$r_pp))); $r_off=($r_pg-1)*$r_pp; $rr=$conn->query("SELECT * FROM password_reset_requests ORDER BY (status='pending') DESC, created_at DESC LIMIT $r_off,$r_pp"); ?>
    <div class="tbl-wrap">
    <table id="resetTbl"><thead><tr><th style="text-align:center">شماره کاربر</th><th style="text-align:center">وضعیت</th><th style="text-align:center">زمان درخواست</th><th style="text-align:center">عملیات</th></tr></thead><tbody>
    <?php if ($r_total === 0): ?><tr><td colspan="4" style="text-align:center;color:var(--text3)">درخواستی ثبت نشده است.</td></tr><?php endif; ?>
    <?php while($r=$rr->fetch_assoc()): $r_ph=htmlspecialchars($r['user_phone'],ENT_QUOTES,'UTF-8'); ?>
    <tr data-rr-phone="<?= $r_ph ?>">
      <td dir="ltr" style="text-align:center"><?= $r_ph ?></td>
      <td style="text-align:center;font-size:1.1rem"><span class="rr-status" title="<?= $r['status']==='done' ? 'بازنشانی انجام شده' : 'در انتظار بازنشانی' ?>"><?= $r['status']==='done' ? '✅' : '❌' ?></span></td>
      <td style="text-align:center"><?= time_ago($r['created_at']) ?></td>
      <td style="text-align:center;white-space:nowrap"><button type="button" class="btn-sm btn-blue" style="font-size:.7rem;cursor:pointer" onclick="openResetSheet('<?= $r_ph ?>')" title="تغییر گذرواژه">🔑</button></td>
    </tr>
    <?php endwhile; ?></tbody></table>
    </div>
    <?= admin_pager('r', 'requests', $r_total, $r_pp, $r_pg) ?>
  </div>
  <?php endif; ?>

  <?php if (getSetting($conn, 'register_closed', '0') === '1'): $cu_num = (getSetting($conn, 'sms_password_login_enabled', '0') === '1') ? '۶' : '۵'; $cu_digits = (int)sitak_pw_digits($conn); ?>
  <div class="acc-hdr open" onclick="toggleAcc(this)"><h3><?= $cu_num ?>. ساخت کاربر جدید</h3><span class="acc-icon">−</span></div>
  <div class="acc-body open">
    <p style="font-size:.78rem;color:var(--text2);line-height:1.8;margin-bottom:12px">ثبت‌نام بسته است؛ کاربرهای جدید فقط از اینجا ساخته می‌شوند. شماره و گذرواژه را وارد کن و پس از ساخت، گذرواژه را به کاربر بده تا با شماره و گذرواژه‌ی خودش وارد شود.</p>
    <div class="fg" style="margin-bottom:10px"><label>شماره موبایل کاربر</label><input type="tel" class="fi" id="cuPhone" maxlength="11" dir="ltr" style="text-align:center" placeholder="09123456789"></div>
    <div class="fg" style="margin-bottom:10px"><label>گذرواژه ورود (<?= pn($cu_digits) ?> رقمی)</label><div style="position:relative;margin-bottom:12px"><input type="password" inputmode="numeric" class="fi pw-field" id="cuPw1" maxlength="8" dir="ltr" placeholder="گذرواژه ورود" style="margin-bottom:0"><button type="button" onclick="togglePw('cuPw1',this)" aria-label="نمایش گذرواژه" style="position:absolute;left:0;top:0;height:100%;background:none;border:none;cursor:pointer;color:#86868b;padding:0 12px;display:flex;align-items:center;justify-content:center"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg></button></div></div>
    <div class="fg" style="margin-bottom:10px"><label>تکرار گذرواژه ورود</label><div style="position:relative;margin-bottom:12px"><input type="password" inputmode="numeric" class="fi pw-field" id="cuPw2" maxlength="8" dir="ltr" placeholder="تکرار گذرواژه ورود" style="margin-bottom:0" onkeydown="if(event.key==='Enter'){submitCreateUser()}"><button type="button" onclick="togglePw('cuPw2',this)" aria-label="نمایش گذرواژه" style="position:absolute;left:0;top:0;height:100%;background:none;border:none;cursor:pointer;color:#86868b;padding:0 12px;display:flex;align-items:center;justify-content:center"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg></button></div></div>
    <button type="button" class="btn-p" id="cuSubmit" onclick="submitCreateUser()" style="font-size:.85rem">ساخت کاربر ✅</button>
  </div>
  <?php endif; ?>


</div>

<script>
function pagerJump(el){var max=parseInt(el.max,10)||1;var p=Math.min(Math.max(1,parseInt(el.value,10)||1),max);location.href=el.dataset.tpl.replace('__P__',p)}
(function(){var sec=new URLSearchParams(location.search).get('sec');var t={winners:'برندگان',orders:'سفارشات',users:'کاربران',resets:'بازنشانی'}[sec];if(!t)return;document.querySelectorAll('.acc-hdr').forEach(function(h){if(h.textContent.indexOf(t)!==-1&&!h.classList.contains('open')){h.click()}});})();
</script>
<style>
.rr-ov{position:fixed;inset:0;background:rgba(0,0,0,.5);opacity:0;pointer-events:none;transition:opacity .25s;z-index:1000}
.rr-ov.open{opacity:1;pointer-events:auto}
.rr-sheet{position:fixed;left:0;right:0;bottom:0;margin:0 auto;max-width:520px;background:var(--card,#fff);border-radius:18px 18px 0 0;padding:18px 18px 26px;transform:translateY(105%);transition:transform .3s;z-index:1001;box-shadow:0 -8px 30px rgba(0,0,0,.25)}
.rr-sheet.open{transform:translateY(0)}
.toast{z-index:1100!important}
.rr-sheet .rr-handle{width:40px;height:4px;border-radius:4px;background:var(--border);margin:0 auto 14px}
.rr-sheet .rr-title{font-weight:700;font-size:.95rem;margin-bottom:14px;text-align:center}
.rr-sheet .fg{margin-bottom:10px}
@media(min-width:481px){
  .rr-sheet{left:50%;right:auto;transform:translate(-50%,105%);width:480px;max-width:calc(100vw - 24px)}
  .rr-sheet.open{transform:translate(-50%,0)}
}
</style>
<div class="rr-ov" id="rrOv" onclick="closeResetSheet()"></div>
<div class="rr-sheet" id="rrSheet">
  <div class="rr-handle"></div>
  <div class="rr-title">🔑 تغییر گذرواژه کاربر <span id="rrPhone" dir="ltr"></span></div>
  <div class="fg"><label>گذرواژه جدید</label><div style="position:relative;margin-bottom:12px"><input type="password" class="fi pw-field" id="rrPw1" placeholder="گذرواژه جدید" style="margin-bottom:0"><button type="button" onclick="togglePw('rrPw1',this)" aria-label="نمایش گذرواژه" style="position:absolute;left:0;top:0;height:100%;background:none;border:none;cursor:pointer;color:#86868b;padding:0 12px;display:flex;align-items:center;justify-content:center"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg></button></div></div>
  <div class="fg"><label>تکرار گذرواژه جدید</label><div style="position:relative;margin-bottom:12px"><input type="password" class="fi pw-field" id="rrPw2" placeholder="تکرار گذرواژه جدید" style="margin-bottom:0"><button type="button" onclick="togglePw('rrPw2',this)" aria-label="نمایش گذرواژه" style="position:absolute;left:0;top:0;height:100%;background:none;border:none;cursor:pointer;color:#86868b;padding:0 12px;display:flex;align-items:center;justify-content:center"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg></button></div></div>
  <button type="button" class="btn-p" id="rrSubmit" onclick="submitResetSheet()" style="margin-top:6px">ثبت گذرواژه</button>
</div>
<!-- Modal: User Detail -->
<div class="modal-ov" id="userModal">
  <div class="modal-body">
    <button class="close-x" onclick="closeModal()">×</button>
    <div class="modal-scroll">
      <div id="userMgmtBox">
        <h3>⚙ مدیریت کاربر</h3>
        <style></style>
        <div class="modal-acc-hdr open" onclick="toggleModalAcc(this)"><span>⭐ تغییر مستقیم مراجعات و شانس‌ها</span><span class="modal-acc-icon">−</span></div>
        <div class="modal-acc-body open">
        <div class="stat-box" style="margin-bottom:0;border:none;background:transparent;padding:0">
          <input type="hidden" id="editPhone">
          <div class="fg" style="margin-bottom:10px"><label>نام کاربر</label><input type="text" class="fi" id="editName" maxlength="30" placeholder="نام کاربر"></div>
          <div class="fg" style="margin-bottom:10px"><label>مراجعات (کارت وفاداری)</label><div class="stat-row"><button type="button" class="btn-h minus" onclick="chV('editVis',-1)">−</button><input type="text" inputmode="numeric" class="stat-input" id="editVis" min="0"><button type="button" class="btn-h plus" onclick="chV('editVis',1)">+</button></div></div>
          <button type="button" class="btn-p btn-3d-red" style="background:var(--red);font-size:.82rem;padding:10px;margin:0 0 10px" onclick="resetUserVisits()">بازنشانی مراجعات 🔄</button>
          <div class="fg" style="margin-bottom:10px"><label>تاس‌های دائمی</label><div class="stat-row"><button type="button" class="btn-h minus" onclick="chV('editRolls',-1)">−</button><input type="text" inputmode="numeric" class="stat-input" id="editRolls" min="0"><button type="button" class="btn-h plus" onclick="chV('editRolls',1)">+</button></div></div>
          <button type="button" class="btn-p" onclick="saveStats()" style="font-size:.82rem;padding:10px">ثبت تغییرات 💾</button>
        </div>
        </div>
      </div>
      <div id="modalContent"></div>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
<?php admin_common_js(); ?>
function toggleClaimed(id,el){fetch(`<?= admin_url() ?>?toggle_claimed=${id}&csrf=<?= sitak_csrf_token() ?>`).then(r=>r.text()).then(t=>{t=t.replace(/^\uFEFF/,'');return JSON.parse(t)}).then(d=>{if(d.success){el.classList.toggle('on');showToast(el.classList.contains('on')?'تحویل ثبت شد ✅':'تحویل برداشته شد')}else{showToast(d.message||'خطا در ثبت تحویل')}}).catch(()=>showToast('خطا در ارتباط با سرور'))}
function chV(id,off){const i=document.getElementById(id);let v=parseInt(String(i.value).replace(/[۰-۹]/g,d=>'۰۱۲۳۴۵۶۷۸۹'.indexOf(d)))||0;v+=off;if(v<0)v=0;i.value=v}
function searchUser(){
  const ph=document.getElementById('searchPhone').value.trim();
  if(!ph){adminAlert('شماره را وارد کنید.');return}
  openUserModal(ph);
}
function openUserModal(ph){
  fetch(`<?= admin_url() ?>?user_details=${ph}`).then(r=>r.text()).then(t=>{t=t.replace(/^\uFEFF/,'');return JSON.parse(t)}).then(d=>{
    if(!d.success){adminAlert(d.message);return}
    document.getElementById('userMgmtBox').style.display='';
    document.getElementById('editPhone').value=d.user.phone;
    document.getElementById('editName').value=d.user.name||'';
    document.getElementById('editVis').value=d.user.visits;
    document.getElementById('editRolls').value=d.user.referral_rolls;

    const statusFa={pending:'در انتظار',confirmed:'در حال آماده‌سازی',shipped:'ارسال شده',delivered:'تحویل شده',cancelled:'لغو شده'};

    let h='';

    h+='<div class="modal-acc-hdr open" onclick="toggleModalAcc(this)"><span>🧾 سابقه سفارشات ('+d.orders_count+')</span><span class="modal-acc-icon">−</span></div>';
    h+='<div class="modal-acc-body open">';
    if(d.orders_count===0){h+='<p style="color:var(--text3);font-size:.8rem">سفارشی ثبت نشده.</p>'}
    else{
      h+='<p style="font-size:.8rem;margin:4px 0 8px"><strong>'+d.orders_count+'</strong> سفارش — مجموع <strong>'+Number(d.orders_sum).toLocaleString('fa-IR')+' تومان</strong></p>';
      d.orders.forEach(o=>{
        h+='<div class="order-row">';
        h+='<div class="order-row-head">';
        h+='<span>'+o.id+' — '+o.created_at_relative+'</span>';
        h+='<span style="display:flex;gap:6px;align-items:center">';
        h+='<span style="font-weight:700;color:var(--gold-dark);white-space:nowrap">'+Number(o.total_price).toLocaleString('fa-IR')+' ت</span>';
        h+='<button type="button" class="btn-xs" onclick="toggleOrderItems(this,'+o.id+')">جزئیات 🔍</button>';
        h+='</span></div>';
        h+='<div class="order-items" id="oi-'+o.id+'">';
        let items=[];try{items=JSON.parse(o.items)||[]}catch(e){}
        if(!items.length){h+='<div class="order-item-line">آیتمی ثبت نشده.</div>'}
        else{items.forEach(it=>{h+='<div class="order-item-line">'+esc(it.name)+' × '+esc(String(it.qty||1))+' = '+Number(it.price||0).toLocaleString('fa-IR')+' ت</div>'})}
        if(o.note)h+='<div class="order-item-line" style="color:var(--text2)">📝 '+esc(o.note)+'</div>';
        if(o.admin_note)h+='<div class="order-item-line" style="color:var(--gold-dark)">📌 یادداشت ادمین: '+esc(o.admin_note)+'</div>';
        h+='<div class="order-item-line" style="color:var(--text3)">وضعیت: '+((o.status==='pending'&&o.payment_method==='card'&&!o.card_submitted_at)?'در انتظار پرداخت 💳':(statusFa[o.status]||esc(o.status)))+'</div>';
        h+='</div></div>';
      });
    }
    h+='</div>';

    h+='<div class="modal-acc-hdr open" onclick="toggleModalAcc(this)"><span>🏆 سابقه بردهای شانس ('+d.wins.length+')</span><span class="modal-acc-icon">−</span></div>';
    h+='<div class="modal-acc-body open">';
    if(d.wins.length===0){h+='<p style="color:var(--text3);font-size:.8rem">بردی ثبت نشده.</p>'}
    else{
      d.wins.forEach(w=>{
        const ct=w.claimed==1?'تحویل شده ✓':'در انتظار 🕒';
        const cc=w.claimed==1?'on':'';
        h+='<div class="win-card"><span style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">🎁 '+esc(w.prize_name)+' <small style="color:var(--text3)">— '+w.won_at_relative+'</small></span><span class="tick '+cc+'" onclick="toggleClaimedInModal('+w.id+',this)" style="min-width:100px;text-align:center;font-size:.72rem;padding:3px 8px">'+ct+'</span></div>';
      });
    }
    h+='</div>';

    h+='<div class="modal-acc-hdr open" onclick="toggleModalAcc(this)"><span>🌱 ورود با این معرف ('+d.referrals.length+')</span><span class="modal-acc-icon">−</span></div>';
    h+='<div class="modal-acc-body open">';
    if(d.referrals.length===0){h+='<p style="color:var(--text3);font-size:.8rem">کسی دعوت نشده.</p>'}
    else{
      d.referrals.forEach(r=>{
        h+='<div style="font-size:.78rem;padding:5px 8px;background:var(--bg);border-radius:6px;border:1px solid var(--border);margin-bottom:5px">👤 '+esc(r.name||'مهمان')+' ('+esc(r.phone)+') — '+r.created_at_relative+'</div>';
      });
    }
    h+='</div>';

    <?php if (getSetting($conn, 'sms_password_login_enabled', '0') === '1'): ?>
    h+='<div class="modal-acc-hdr open" onclick="toggleModalAcc(this)"><span>🔑 تغییر گذرواژه ورود</span><span class="modal-acc-icon">−</span></div>';
    h+='<div class="modal-acc-body open">';
    h+='<div class="fg" style="margin-bottom:10px"><label>گذرواژه جدید</label><div style="position:relative;margin-bottom:12px"><input type="password" class="fi pw-field" id="admPw1" placeholder="گذرواژه جدید" style="margin-bottom:0"><button type="button" onclick="togglePw(\'admPw1\',this)" aria-label="نمایش گذرواژه" style="position:absolute;left:0;top:0;height:100%;background:none;border:none;cursor:pointer;color:#86868b;padding:0 12px;display:flex;align-items:center;justify-content:center">'+PW_SVG_EYE+'</button></div></div>';
    h+='<div class="fg" style="margin-bottom:10px"><label>تکرار گذرواژه جدید</label><div style="position:relative;margin-bottom:12px"><input type="password" class="fi pw-field" id="admPw2" placeholder="تکرار گذرواژه جدید" style="margin-bottom:0"><button type="button" onclick="togglePw(\'admPw2\',this)" aria-label="نمایش گذرواژه" style="position:absolute;left:0;top:0;height:100%;background:none;border:none;cursor:pointer;color:#86868b;padding:0 12px;display:flex;align-items:center;justify-content:center">'+PW_SVG_EYE+'</button></div></div>';
    h+='<button type="button" class="btn-p" style="font-size:.82rem;padding:10px;margin:0 0 10px" onclick="adminSetUserPassword()">ثبت گذرواژه</button>';
    h+='</div>';
    <?php endif; ?>
    h+='<hr style="border:0;border-top:1px solid var(--border);margin:14px 0">';
    const commentBlocked = d.user.comment_blocked == 1;
    h+='<button type="button" class="btn-sm ' + (commentBlocked ? 'btn-blue' : 'btn-red') + '" style="width:100%;justify-content:center;padding:10px;margin-bottom:8px" onclick="toggleCommentBlock(document.getElementById(\'editPhone\').value)">'
        + (commentBlocked ? '💬 رفع محدودیت دیدگاه' : '🚫 مسدود کردن ثبت دیدگاه') + '</button>';
    const blocked = d.user.blocked == 1;
    h+='<button type="button" class="btn-sm ' + (blocked ? 'btn-blue' : 'btn-red') + '" style="width:100%;justify-content:center;padding:10px;margin-bottom:8px" onclick="toggleBlock(document.getElementById(\'editPhone\').value)">'
        + (blocked ? '🔓 رفع مسدودی' : '🚫 مسدود کردن') + ' این کاربر</button>';
    h+='<button type="button" class="btn-sm btn-red" style="width:100%;justify-content:center;padding:10px" onclick="deleteUser(document.getElementById(\'editPhone\').value)">🗑️ حذف کامل این کاربر</button>';

    document.getElementById('modalContent').innerHTML=h;
    document.getElementById('userModal').classList.add('open');
  }).catch(e=>{showToast('خطا در دریافت اطلاعات کاربر');console.error(e)});
}
function openResetSheet(ph){
  document.getElementById('rrPhone').textContent=ph;
  document.getElementById('rrPw1').value='';document.getElementById('rrPw2').value='';
  document.getElementById('rrOv').classList.add('open');document.getElementById('rrSheet').classList.add('open');
  setTimeout(function(){document.getElementById('rrPw1').focus()},250);
}
function closeResetSheet(){
  document.getElementById('rrOv').classList.remove('open');document.getElementById('rrSheet').classList.remove('open');
}
function faToEnDigits(v){return String(v).replace(/[۰-۹]/g,function(d){return '0123456789'['۰۱۲۳۴۵۶۷۸۹'.indexOf(d)]}).replace(/[٠-٩]/g,function(d){return '0123456789'['٠١٢٣٤٥٦٧٨٩'.indexOf(d)]})}
function checkNewPassword(a,b){
  const dg=<?= (int)sitak_pw_digits($conn) ?>;
  if(a===''){return 'گذرواژه‌ی جدید را وارد کنید'}
  if(!new RegExp('^[0-9]{'+dg+'}$').test(a)){return 'گذرواژه باید فقط عدد و '+dg+' رقمی باشد'}
  if(b===''){return 'تکرار گذرواژه را وارد کنید'}
  if(a!==b){return 'تکرار گذرواژه با گذرواژه‌ی جدید یکسان نیست'}
  return '';
}
function submitResetSheet(){
  const ph=document.getElementById('rrPhone').textContent.trim();
  const a=faToEnDigits(document.getElementById('rrPw1').value.trim()), b=faToEnDigits(document.getElementById('rrPw2').value.trim());
  const err=checkNewPassword(a,b);
  if(err){showToast(err);return}
  const btn=document.getElementById('rrSubmit');btn.disabled=true;
  const fd=new FormData();fd.append('admin_set_user_password','1');fd.append('phone',ph);fd.append('password',a);fd.append('password2',b);fd.append('csrf_token','<?= sitak_csrf_token() ?>');
  fetch('<?= admin_url() ?>',{method:'POST',body:fd,credentials:'same-origin'}).then(r=>r.text()).then(t=>{t=t.replace(/^\uFEFF/,'');return JSON.parse(t)}).then(res=>{
    btn.disabled=false;
    if(res.success){
      showToast(res.message);closeResetSheet();
      document.querySelectorAll('tr[data-rr-phone="'+ph+'"] .rr-status').forEach(e=>{e.textContent='✅';e.title='بازنشانی انجام شده'});
    }else showToast(res.message||'ثبت گذرواژه انجام نشد');
  }).catch(()=>{btn.disabled=false;showToast('خطا در ارتباط با سرور، دوباره تلاش کنید')});
}
function submitCreateUser(){
  const ph=faToEnDigits(document.getElementById('cuPhone').value.trim());
  if(!/^09[0-9]{9}$/.test(ph)){showToast('شماره موبایل نامعتبر است. فرمت صحیح: 09123456789');return}
  const a=faToEnDigits(document.getElementById('cuPw1').value.trim()), b=faToEnDigits(document.getElementById('cuPw2').value.trim());
  const err=checkNewPassword(a,b);
  if(err){showToast(err);return}
  const btn=document.getElementById('cuSubmit');btn.disabled=true;
  const fd=new FormData();fd.append('admin_create_user','1');fd.append('phone',ph);fd.append('password',a);fd.append('password2',b);fd.append('csrf_token','<?= sitak_csrf_token() ?>');
  fetch('<?= admin_url() ?>',{method:'POST',body:fd,credentials:'same-origin'}).then(r=>r.text()).then(t=>{t=t.replace(/^\uFEFF/,'');return JSON.parse(t)}).then(res=>{
    btn.disabled=false;
    if(res.success){
      showToast(res.message+(res.phone?' — '+res.phone:''));
      document.getElementById('cuPhone').value='';document.getElementById('cuPw1').value='';document.getElementById('cuPw2').value='';
      setTimeout(()=>location.reload(),800);
    }else showToast(res.message||'ساخت کاربر انجام نشد');
  }).catch(()=>{btn.disabled=false;showToast('خطا در ارتباط با سرور، دوباره تلاش کنید')});
}
var PW_SVG_EYE='<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>';var PW_SVG_OFF='<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 19c-6.5 0-10-7-10-7a18.5 18.5 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c6.5 0 10 7 10 7a18.5 18.5 0 0 1-2.16 3.19M1 1l22 22"/></svg>';
function togglePw(id,btn){var inp=document.getElementById(id);var isTxt=inp.type==='text';inp.type=isTxt?'password':'text';btn.innerHTML=isTxt?PW_SVG_EYE:PW_SVG_OFF;btn.setAttribute('aria-label',isTxt?'نمایش گذرواژه':'مخفی کردن گذرواژه')}
function adminSetUserPassword(){
  const ph=document.getElementById('editPhone').value;
  const a=faToEnDigits(document.getElementById('admPw1').value.trim()), b=faToEnDigits(document.getElementById('admPw2').value.trim());
  const err=checkNewPassword(a,b);
  if(err){showToast(err);return}
  const fd=new FormData();fd.append('admin_set_user_password','1');fd.append('phone',ph);fd.append('password',a);fd.append('password2',b);fd.append('csrf_token','<?= sitak_csrf_token() ?>');
  fetch('<?= admin_url() ?>',{method:'POST',body:fd,credentials:'same-origin'}).then(r=>r.text()).then(t=>{t=t.replace(/^\uFEFF/,'');return JSON.parse(t)}).then(res=>{
    if(res.success){showToast(res.message);document.getElementById('admPw1').value='';document.getElementById('admPw2').value=''}
    else showToast(res.message||'ثبت گذرواژه انجام نشد');
  }).catch(()=>showToast('خطا در ارتباط با سرور، دوباره تلاش کنید'));
}
async function deleteUser(ph){
  if(!(await adminConfirm({message:'کاربر '+ph+' و تمام داده‌های او (بردها، فال‌ها، سفارشات) برای همیشه حذف شود؟',danger:true})))return;
  fetch(`<?= admin_url() ?>?delete_user=${encodeURIComponent(ph)}&csrf=<?= sitak_csrf_token() ?>`).then(r=>r.text()).then(t=>{t=t.replace(/^\uFEFF/,'');return JSON.parse(t)}).then(d=>{
    if(d.success){showToast('کاربر حذف شد 🗑️');closeModal();setTimeout(()=>location.reload(),600)}
    else{showToast(d.message||'خطا در حذف')}
  }).catch(()=>showToast('خطا در ارتباط با سرور'));
}
function toggleCommentBlock(ph){
  fetch(`<?= admin_url() ?>?toggle_comment_block=${encodeURIComponent(ph)}&csrf=<?= sitak_csrf_token() ?>`).then(r=>r.text()).then(t=>{t=t.replace(/^\uFEFF/,'');return JSON.parse(t)}).then(d=>{
    if(d.success){showToast(d.comment_blocked == 1 ? 'ثبت دیدگاه برای کاربر مسدود شد 🚫' : 'محدودیت ثبت دیدگاه برداشته شد 💬');openUserModal(ph)}
    else showToast(d.message||'خطا در تغییر وضعیت');
  }).catch(()=>showToast('خطا در ارتباط با سرور'));
}
async function toggleBlock(ph){
  if(!(await adminConfirm({message:'آیا مطمئن هستید؟ این کار دسترسی کاربر را مسدود/رفع می‌کند.',danger:true})))return;
  fetch(`<?= admin_url() ?>?toggle_block=${encodeURIComponent(ph)}&csrf=<?= sitak_csrf_token() ?>`).then(r=>r.text()).then(t=>{t=t.replace(/^\uFEFF/,'');return JSON.parse(t)}).then(d=>{
    if(d.success){
      showToast(d.blocked == 1 ? 'کاربر مسدود شد 🚫' : 'مسدودیت کاربر برداشته شد 🔓');
      closeModal();
      setTimeout(()=>location.reload(),600);
    }else{showToast(d.message||'خطا در تغییر وضعیت')}
  }).catch(()=>showToast('خطا در ارتباط با سرور'));
}
function toggleClaimedInModal(id,el){
  fetch(`<?= admin_url() ?>?toggle_claimed=${id}&csrf=<?= sitak_csrf_token() ?>`).then(r=>r.text()).then(t=>{t=t.replace(/^\uFEFF/,'');return JSON.parse(t)}).then(d=>{
    if(d.success){if(el.classList.contains('on')){el.classList.remove('on');el.textContent='در انتظار 🕒'}else{el.classList.add('on');el.textContent='تحویل شده ✓'}}
  });
}
function saveStats(){
  const fd=new FormData();fd.append('update_user_stats','1');fd.append('user_phone',document.getElementById('editPhone').value);
  fd.append('new_visits',document.getElementById('editVis').value);fd.append('new_rolls',document.getElementById('editRolls').value);
  fd.append('new_name',document.getElementById('editName').value);
  fd.append('csrf_token','<?= htmlspecialchars(sitak_csrf_token(), ENT_QUOTES, 'UTF-8') ?>');
  fetch('<?= admin_url() ?>',{method:'POST',body:fd}).then(r=>r.text()).then(t=>{t=t.replace(/^\uFEFF/,'');return JSON.parse(t)}).then(d=>{if(d.success){
      showToast('بروزرسانی شد! ✨');
      const ph=document.getElementById('editPhone').value;
      const nm=document.getElementById('editName').value.trim();
      const vn=parseInt(String(document.getElementById('editVis').value).replace(/[۰-۹]/g,d=>'۰۱۲۳۴۵۶۷۸۹'.indexOf(d)))||0;
      document.querySelectorAll('#usersTbl tbody tr').forEach(tr=>{const c=tr.cells;if(c.length>=4&&c[0].textContent.trim()===ph){c[1].textContent=nm||'بدون نام';c[3].textContent=vn}});
    }else{showToast(d.message||'خطا')}}).catch(()=>showToast('ذخیره نشد، صفحه را دوباره بارگذاری کن'));
}
async function resetUserVisits(){
  const ph=document.getElementById('editPhone').value;
  if(!ph){showToast('کاربری انتخاب نشده');return}
  if(!(await adminConfirm({message:'شمارنده‌ی مراجعات این کاربر صفر بشه؟',danger:true})))return;
  const fd=new FormData();fd.append('reset_user_visits','1');fd.append('user_phone',ph);
  fd.append('csrf_token','<?= htmlspecialchars(sitak_csrf_token(), ENT_QUOTES, 'UTF-8') ?>');
  fetch('<?= admin_url() ?>',{method:'POST',body:fd}).then(r=>r.text()).then(t=>{t=t.replace(/^\uFEFF/,'');return JSON.parse(t)}).then(d=>{
    if(d.success){document.getElementById('editVis').value=0;showToast('شمارنده ریست شد');}else{showToast(d.message||'خطا در ریست')}
  }).catch(()=>showToast('خطا در ارتباط با سرور'));
}
function closeModal(){document.getElementById('userModal').classList.remove('open')}
/* ── Order Details ── */
function parseJsonLoose(t){t=String(t).replace(/^\uFEFF/,'');var a=t.indexOf('{'),b=t.lastIndexOf('}');if(a<0||b<a)throw new Error('no json');return JSON.parse(t.slice(a,b+1))}
function viewOrderDetails(id){
  fetch('<?= admin_url() ?>?action=order_details&order_id='+id,{cache:'no-store'}).then(r=>r.text()).then(parseJsonLoose).then(d=>{
    if(!d.success){showToast(d.message||'خطا در دریافت اطلاعات سفارش');return}
    const o=d.order;
    document.getElementById('userMgmtBox').style.display='none';
    const money=n=>Number(n||0).toLocaleString('en-US')+' تومان';
    const row=(k,v)=>'<p style="font-size:.82rem;margin:4px 0;line-height:1.7"><strong>'+k+':</strong> '+v+'</p>';
    const line='<hr style="border:0;border-top:1px solid var(--border);margin:14px 0">';
    let items=[];try{items=JSON.parse(o.items)||[]}catch(e){}
    let sum=0;items.forEach(it=>{sum+=(+it.price||0)*(+it.qty||1)});
    let h='<h4 style="margin:10px 0 8px;color:var(--gold-dark);font-size:.88rem">📋 جزئیات سفارش '+id+'</h4>';
    h+=row('زمان ثبت',esc(o.created_at));
    h+=row('وضعیت',(o.status==='pending'&&o.payment_method==='card'&&!o.card_submitted_at)?'در انتظار پرداخت 💳':esc({pending:'در انتظار',confirmed:'در حال آماده‌سازی',shipped:'ارسال شده',delivered:'تحویل شده',cancelled:'لغو شده'}[o.status]||o.status));
    h+=row('مشتری',esc(o.user_name||'مهمان')+' ('+esc(o.user_phone)+')');
    h+=line;
    h+=row('تحویل گیرنده',esc(((o.user_name||'')+' '+(o.recipient_last_name||'')).trim()||'—'));
    h+=row('شماره تماس گیرنده',esc(o.recipient_phone||'—'));
    h+=row('نشانی',esc([o.recipient_province,o.recipient_city,o.recipient_address].filter(Boolean).join('، ')||'—'));
    h+=row('پلاک / واحد',esc((o.recipient_plaque||'—')+' / '+(o.recipient_unit||'—')));
    h+=row('کد پستی',esc(o.recipient_postal_code||'—'));
    h+=row('نشانی روی نقشه',(o.delivery_address?esc(o.delivery_address):'—')+((o.delivery_lat!=null&&o.delivery_lat!=='')?' ('+esc(o.delivery_lat+', '+o.delivery_lng)+')':''));
    if(ORD_MAP.on&&o.delivery_lat!=null&&o.delivery_lat!=='')h+='<style>#admOrdMap,#admOrdMap .mapboxgl-canvas-container,#admOrdMap canvas{border-radius:14px}</style><div id="admOrdMap" style="height:220px;border-radius:14px;overflow:hidden;isolation:isolate;border:1px solid var(--border);margin:6px 0 10px"></div>';
    h+=line;
    items.forEach(it=>{h+='<div style="font-size:.8rem;padding:6px 8px;background:var(--bg);border-radius:6px;border:1px solid var(--border);margin-bottom:5px">'+esc(String(it.name||''))+' × '+esc(String(it.qty||''))+' = '+money((+it.price||0)*(+it.qty||1))+'</div>'});
    h+=row('جمع اقلام',money(sum));
    h+=row('هزینه‌ی ارسال',(+o.shipping_fee>0)?money(o.shipping_fee):'رایگان');
    h+=row('تخفیف',(+o.discount_amount>0)?('-'+money(o.discount_amount)+(o.discount_code?' ('+esc(o.discount_code)+')':'')):'—');
    h+=row('مجموع پرداختی',money(o.total_price));
    h+=line;
    h+=row('روش پرداخت',o.payment_method==='card'?'کارت‌به‌کارت 💳':((o.payment_ref_id||o.payment_authority)?'درگاه آنلاین':'پرداخت پس از هماهنگی'));
    h+=row('وضعیت پرداخت',o.payment_paid_at?'پرداخت شده ('+esc(o.payment_paid_at)+')':((o.payment_method==='card'&&o.card_submitted_at)?'واریز اعلام شده — در انتظار بررسی مدیر':'پرداخت نشده'));
    if(o.payment_ref_id)h+=row('کد پیگیری',esc(o.payment_ref_id));
    if(o.payment_method==='card'){
      h+=line;
      h+='<h4 style="margin:10px 0 8px;color:var(--gold-dark);font-size:.88rem">💳 بررسی واریز کارت‌به‌کارت</h4>';
      if(o.card_submitted_at){
        const fmtCard=v=>String(v||'').replace(/\D/g,'').replace(/(\d{4})(?=\d)/g,'$1-');
        const fmtIban=v=>String(v||'').replace(/[^A-Za-z0-9]/g,'').toUpperCase().replace(/(.{4})(?=.)/g,'$1-');
        h+=row('واریزکننده',esc(o.card_payer_name||'—'));
        if(o.card_payer_card)h+=row('شماره کارت واریز','<span dir="ltr" style="display:inline-block;letter-spacing:.5px">'+esc(fmtCard(o.card_payer_card))+'</span>');
        if(o.card_payer_iban)h+=row('شبای واریز','<span dir="ltr" style="display:inline-block;font-size:.74rem;letter-spacing:.5px">'+esc(fmtIban(o.card_payer_iban))+'</span>');
        h+=row('تاریخ و ساعت تراکنش (اعلامی کاربر)',o.card_paid_at_fa?'<span dir="rtl">'+esc(o.card_paid_at_fa)+'</span>':'—');
        h+=row('زمان ثبت اطلاعات',o.card_submitted_at_fa?esc(o.card_submitted_at_fa):esc(o.card_submitted_at||'—'));
        const stMap={submitted:'ثبت شده — در انتظار بررسی تو ⏳',approved:'تأیید شده ✅',rejected:'رد شده ❌'};
        h+=row('وضعیت بررسی',stMap[o.card_status]||'—');
        if(o.status==='pending'){
          h+='<div style="display:flex;gap:8px;margin-top:12px">';
          h+='<button type="button" class="btn-p" style="flex:1;background:linear-gradient(135deg,#30d158,#1a8a3f)" onclick="cardDecision('+id+',\'approve\',this)">تأیید واریز ✅</button>';
          h+='<button type="button" class="btn-sm btn-red" style="flex:1;padding:10px;font-size:.82rem" onclick="cardDecision('+id+',\'reject\',this)">رد واریز ❌</button>';
          h+='</div>';
          h+='<p style="font-size:.68rem;color:var(--text3);margin-top:8px;line-height:1.8">قبل از تأیید، مبلغ و مشخصات واریز را با تراکنش‌های حساب بانکی خودت مقایسه کن. با تأیید، سفارش «در حال آماده‌سازی» می‌شود و با رد، سفارش لغو و موجودی آزاد می‌شود.</p>';
        }
      }else{
        h+='<p style="font-size:.78rem;color:var(--text3);line-height:1.8">کاربر هنوز اطلاعات واریز را ثبت نکرده است.'+(o.card_lock_expires_fa?'<br>مهلت واریز تا: '+esc(o.card_lock_expires_fa)+' — پس از آن سفارش خودکار لغو می‌شود.':'')+'</p>';
      }
    }
    if(o.note)h+=row('یادداشت کاربر',esc(o.note));
    h+=line;
    h+='<h4 style="margin:10px 0 8px;color:var(--gold-dark);font-size:.88rem">📝 یادداشت ادمین (برای کاربر نمایش داده می‌شود)</h4>';
    h+='<textarea class="fi" id="adminNoteInput" rows="3" style="width:100%;font-size:.82rem;resize:vertical" placeholder="مثلاً: کد پیگیری پستی، وضعیت آماده‌سازی...">'+esc(o.admin_note||'')+'</textarea>';
    h+='<button type="button" class="btn-p" style="margin-top:10px;font-size:.82rem;padding:10px" onclick="saveOrderNote('+id+')">ذخیره یادداشت 💾</button>';
    document.getElementById('modalContent').innerHTML=h;
    document.getElementById('userModal').classList.add('open');
    if(ORD_MAP.on&&o.delivery_lat!=null&&o.delivery_lat!==''){SiteMap.loadSdk(function(){var m=SiteMap.create({container:'admOrdMap',key:ORD_MAP.key,lat:+o.delivery_lat,lng:+o.delivery_lng,zoom:16});if(m)SiteMap.pin(m,+o.delivery_lat,+o.delivery_lng);});}
  }).catch(e=>{showToast('خطا در دریافت اطلاعات سفارش');console.error(e)});
}
function saveOrderNote(id){
  const note=document.getElementById('adminNoteInput').value;
  const fd=new FormData();fd.append('save_order_note','1');fd.append('order_id',id);fd.append('admin_note',note);fd.append('csrf_token','<?= htmlspecialchars(sitak_csrf_token(), ENT_QUOTES, 'UTF-8') ?>');
  fetch('<?= admin_url() ?>',{method:'POST',body:fd}).then(r=>r.text()).then(t=>{t=t.replace(/^\uFEFF/,'');return JSON.parse(t)}).then(d=>{
    if(d.success){showToast('یادداشت ذخیره شد ✅')}
    else{showToast(d.message||'خطا')}
  });
}
async function cardDecision(id,decision,btn){
  const msg=decision==='approve'?'واریز این سفارش تأیید شود؟ سفارش به وضعیت «در حال آماده‌سازی» می‌رود و پیامک تأیید ارسال می‌شود.':'اطلاعات واریز رد شود؟ سفارش لغو می‌شود، موجودی آزاد می‌شود و پیامک لغو ارسال می‌شود.';
  if(!(await adminConfirm({message:msg,danger:decision!=='approve'})))return;
  if(btn)btn.disabled=true;
  const fd=new FormData();fd.append('card_decision','1');fd.append('order_id',id);fd.append('decision',decision);
  fd.append('csrf_token','<?= htmlspecialchars(sitak_csrf_token(), ENT_QUOTES, 'UTF-8') ?>');
  fetch('<?= admin_url() ?>',{method:'POST',body:fd}).then(()=>{
    showToast(decision==='approve'?'واریز تأیید شد ✅':'واریز رد شد ❌');
    setTimeout(()=>location.reload(),700);
  }).catch(()=>{if(btn)btn.disabled=false;showToast('خطا در ارتباط با سرور')});
}
async function deleteOrder(id){
  if(!(await adminConfirm({message:'این سفارش برای همیشه حذف شود؟',danger:true})))return;
  fetch('<?= admin_url() ?>?delete_order='+id+'&csrf=<?= sitak_csrf_token() ?>').then(r=>r.text()).then(t=>{t=t.replace(/^\uFEFF/,'');return JSON.parse(t)}).then(d=>{
    if(d.success){showToast('سفارش حذف شد 🗑️');setTimeout(()=>location.reload(),500)}
    else{showToast(d.message||'خطا در حذف')}
  }).catch(()=>showToast('خطا در ارتباط با سرور'));
}
</script>
<?php
admin_footer_nav('home');
?>
<script>var ORD_MAP={on:<?= (getSetting($conn,'map_enabled','0')==='1' && getSetting($conn,'map_browser_key','')!=='') ? 'true' : 'false' ?>,key:<?= json_encode(getSetting($conn,'map_browser_key',''), JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT) ?>};</script>
<?php include 'map_shared.php'; ?>
<?php include 'admin_ajax_save.php'; ?>
</body>
</html>
