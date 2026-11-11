<?php /* cart_sheet.php — شیت سبد خرید مشترک در همه صفحات (CSS + HTML + JS) */ ?>
<?php
// ایموجی سفارشی محصولات بدون تصویر (خود این فایل آن را می‌خواند تا به صفحه‌ی میزبان وابسته نباشد)
// + تنظیمات پرداخت کارت‌به‌کارت برای نمایش انتخاب روش پرداخت و پاپ‌آپ اطلاعات واریز
if (isset($conn)) {
    $custom_emoji = getSetting($conn, 'custom_emoji', '🌐');
    $cs_card_pay = sitak_card_payment_info($conn);
    $cs_zarin_on = getSetting($conn, 'zarinpal_enabled', '0') === '1';
    $cs_pending_on = getSetting($conn, 'pending_method_enabled', '0') === '1';
    $cs_popup_default = 'مبلغ را به یکی از شماره‌های زیر واریز کن، سپس اطلاعات واریز را در صفحه‌ی سفارش‌ها ثبت کن؛ اگر تا پایان مهلت ثبت نکنی، سفارش بطور خودکار لغو می‌شود.';
    $cs_popup_text = trim((string)getSetting($conn, 'card_popup_text', ''));
    if ($cs_popup_text === '') $cs_popup_text = $cs_popup_default;
} else {
    $custom_emoji = '🌐';
    $cs_card_pay = ['ready' => false, 'enabled' => false, 'owner' => '', 'card' => '', 'card_formatted' => '', 'iban' => '', 'iban_formatted' => '', 'lock_minutes' => 30];
    $cs_zarin_on = false;
    $cs_pending_on = false;
    $cs_popup_text = 'مبلغ را به یکی از شماره‌های زیر واریز کن، سپس اطلاعات واریز را در صفحه‌ی سفارش‌ها ثبت کن؛ اگر تا پایان مهلت ثبت نکنی، سفارش بطور خودکار لغو می‌شود.';
}
?>
<style>
.sheet-hdr{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px}
.close-x{width:32px;height:32px;border-radius:50%;border:1px solid var(--border);background:var(--card-el);display:flex;align-items:center;justify-content:center;font-size:1rem;cursor:pointer;color:var(--text2)}
.close-x:active{background:var(--red-bg);color:var(--red)}
.hidden{display:none!important}
.toast{pointer-events:none;position:fixed;bottom:90px;left:50%;transform:translateX(-50%) translateY(100px);background:linear-gradient(135deg,var(--gold,#ecb534),var(--gold-dark,#c49625));color:#fff;border:none;padding:13px 22px;border-radius:16px;font-size:.82rem;font-weight:500;z-index:500;opacity:0;transition:all .35s cubic-bezier(.34,1.56,.64,1);width:max-content;max-width:min(420px,calc(100vw - 24px));text-align:center;box-shadow:0 6px 20px rgba(0,0,0,.2)}.toast.show{transform:translateX(-50%) translateY(0);opacity:1}
.cart-empty{text-align:center;padding:40px 20px;color:var(--text3);font-size:.85rem}
.cart-item{display:flex;gap:10px;padding:12px 0;border-bottom:1px solid var(--border)}.cart-item:last-child{border:none}
.cart-thumb{width:56px;height:56px;border-radius:12px;object-fit:cover;background:var(--card-el,#f0eff2);flex-shrink:0}
.ci-info{flex:1;min-width:0}.ci-name{font-size:.82rem;font-weight:600;margin-bottom:2px}.ci-price{font-size:.75rem;color:var(--gold-dark,#c49625);font-weight:700;margin-bottom:6px}
.ci-qty{display:flex;align-items:center;gap:8px}
.qty-btn{width:28px;height:28px;border-radius:8px;border:1px solid var(--border);background:var(--card-el,#f0eff2);display:flex;align-items:center;justify-content:center;font-size:.85rem;cursor:pointer;font-family:var(--font);color:var(--text)}
.qty-btn:active{background:var(--gold-subtle);border-color:var(--border-gold)}
.qty-val{font-size:.85rem;font-weight:700;min-width:24px;text-align:center}
.del-btn{margin-right:auto;color:var(--red);font-size:.8rem}
.cart-sum{padding-top:12px;border-top:1px solid var(--border)}
.checkout-hint{margin-bottom:0}
.sum-row{display:flex;justify-content:space-between;margin-bottom:10px}.sum-lbl{font-size:.85rem;color:var(--text2)}.sum-val{font-size:1.05rem;font-weight:800;color:var(--gold-dark,#c49625)}
.note-txt{width:100%;padding:10px 12px;background:var(--bg,#f5f5f7);border:1.5px solid var(--border);border-radius:var(--rss,10px);color:var(--text);font-family:var(--font);font-size:.85rem;resize:vertical;min-height:56px;outline:none;margin-top:8px;margin-bottom:10px}.note-txt::placeholder{color:var(--text3)}.note-txt:focus{border-color:var(--gold)}
.checkout-btn{width:100%;padding:15px;background:linear-gradient(135deg,var(--gold,#ecb534),var(--gold-dark,#c49625));color:#fff;border:none;border-radius:var(--rs,14px);font-family:var(--font);font-size:.95rem;font-weight:700;cursor:pointer;box-shadow:0 6px 18px rgba(236,181,52,.15);transition:all .12s}.checkout-btn:active{}.checkout-btn:disabled{opacity:.5;cursor:not-allowed;transform:none;box-shadow:none}
.checkout-hint{font-size:.68rem;color:var(--text3);text-align:center;margin-top:8px}
.cart-continue{flex:1;padding:15px;background:var(--card-el,#f0eff2);color:var(--text2);border:none;border-radius:var(--rs,14px);font-family:var(--font);font-size:.95rem;font-weight:700;cursor:pointer;transition:all .12s;}.cart-continue:active{;background:var(--gold-subtle);color:var(--gold-dark,#c49625)}
.cart-msg-box{background:var(--gold-subtle,rgba(236,181,52,.07));border:1.5px solid var(--border-gold,rgba(236,181,52,.22));border-radius:var(--rss,10px);padding:10px 14px;font-size:.8rem;font-weight:600;color:var(--gold-dark,#c49625);text-align:center;line-height:1.7;margin-bottom:12px;opacity:1;max-height:80px;overflow:hidden;transition:opacity .5s ease,max-height .5s ease,padding .4s ease,margin .4s ease,border-color .5s ease}
.cart-msg-box.msg-hide{opacity:0;max-height:0;padding:0 14px;margin-bottom:0;border-color:transparent}
#cartSheet{display:flex;flex-direction:column;max-height:82vh;overflow:hidden}
#cartSheet > *:not(#cartBody){flex-shrink:0}
#cartSheet #cartBody{flex:1 1 auto;min-height:0;overflow-y:auto;-webkit-overflow-scrolling:touch;scrollbar-gutter:stable;scrollbar-width:thin;scrollbar-color:var(--text3) transparent;padding-left:12px;box-sizing:border-box}
#cartSheet #cartBody::-webkit-scrollbar{width:6px}
#cartSheet #cartBody::-webkit-scrollbar-track{background:transparent}
#cartSheet #cartBody::-webkit-scrollbar-thumb{background:var(--text3);border-radius:6px}
#cartSheet #cartBody::-webkit-scrollbar-thumb:hover{background:var(--text2)}
@media (min-height:500px){#cartSheet #cartBody{min-height:120px}}
@media (min-height:700px){#cartSheet #cartBody{min-height:180px}}
@media (min-height:900px){#cartSheet #cartBody{min-height:240px}}
/* ── Confirm Sheet (نام و نشانی) — مستقل از CSS صفحات ── */
.conf-lbl{display:block;font-size:.75rem;font-weight:600;color:var(--text2);margin:12px 0 5px}
.conf-grid .conf-lbl{margin-top:0}
.conf-lbl b{font-size:.65rem;color:var(--gold-dark);font-weight:500}
.conf-section-title{font-size:.82rem;font-weight:700;color:var(--gold-dark);margin:18px 0 10px;padding-bottom:7px;border-bottom:1px solid var(--border)}
.conf-grid{display:grid;grid-template-columns:1fr;gap:12px}
.conf-grid-three{grid-template-columns:1fr;margin-top:12px}
.conf-in{display:block;width:100%;padding:13px 14px;background:var(--bg,#f5f5f7);border:1.5px solid var(--border);border-radius:var(--rss,10px);color:var(--text);font-family:var(--font);font-size:.92rem;outline:none;box-sizing:border-box}
.conf-in:focus{border-color:var(--gold)}
.conf-in.conf-invalid{border-color:var(--red,#ff453a)!important;box-shadow:0 0 0 2px rgba(255,69,58,.12)}
textarea.conf-in{min-height:60px;resize:vertical}
select.conf-in{appearance:auto}
#confPhone,#confPlaque,#confUnit,#confPostalCode{direction:ltr;text-align:left}
.conf-hint{display:block;font-size:.65rem;color:var(--text3);margin-top:6px;line-height:1.6}
.conf-btn-row{display:flex;gap:10px;margin-top:6px}
.conf-btn-ok{flex:1;padding:14px;background:linear-gradient(135deg,var(--gold,#ecb534),var(--gold-dark,#c49625));color:#fff;border:none;border-radius:var(--rss,10px);font-family:var(--font);font-size:.92rem;font-weight:700;cursor:pointer;}
.conf-btn-ok:active{}
.conf-btn-no{flex:1;padding:14px;background:var(--card-el,#f0eff2);color:var(--text2);border:none;border-radius:var(--rss,10px);font-family:var(--font);font-size:.92rem;font-weight:700;cursor:pointer;}.conf-btn-no:active{}
/* ── انتخاب روش پرداخت (کارت‌به‌کارت) ── */
.pay-opt{display:flex;align-items:flex-start;gap:9px;padding:12px 14px;background:var(--bg,#f5f5f7);border:1.5px solid var(--border);border-radius:var(--rss,10px);cursor:pointer;font-size:.85rem;font-weight:600;color:var(--text);transition:border-color .15s,background .15s}
.pay-opt input{margin-top:3px;accent-color:var(--gold-dark,#c49625)}
.pay-opt.pay-sel{border-color:var(--gold,#ecb534);background:var(--gold-subtle,rgba(236,181,52,.07))}
.pay-opt .pay-sub{display:block;font-size:.65rem;color:var(--text3);font-weight:500;margin-top:3px;line-height:1.7}
/* ── پاپ‌آپ پرداخت کارت‌به‌کارت ── */
.cp-amount-box{background:var(--gold-subtle,rgba(236,181,52,.08));border:1.5px solid var(--border-gold,rgba(236,181,52,.25));color:var(--gold-dark,#c49625);border-radius:var(--rss,10px);padding:10px 14px;font-size:.85rem;font-weight:700;text-align:center;margin-bottom:12px}
.cp-amount-box strong{font-size:1.05rem;font-weight:800}
.cp-timer{background:var(--gold-subtle,rgba(236,181,52,.08));border:1.5px solid var(--border-gold,rgba(236,181,52,.25));color:var(--gold-dark,#c49625);border-radius:var(--rss,10px);padding:10px 14px;font-size:.85rem;font-weight:700;text-align:center;margin-bottom:12px}
.cp-timer.cp-dead{background:var(--red-bg,rgba(255,69,58,.08));border-color:rgba(255,69,58,.3);color:var(--red,#ff453a)}
.cp-note{font-size:.72rem;color:var(--text2);line-height:1.9;margin-bottom:14px}
.cp-row{display:flex;align-items:center;gap:8px;background:var(--bg,#f5f5f7);border:1.5px solid var(--border);border-radius:var(--rss,10px);padding:11px 14px;margin-bottom:8px;flex-wrap:nowrap}
.cp-lbl{font-size:.68rem;color:var(--text3);flex:0 0 auto;white-space:nowrap}
.cp-val{font-size:.9rem;font-weight:700;letter-spacing:.5px;flex:1 1 auto;color:var(--text);min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.cp-copy{padding:7px 12px;border-radius:8px;border:1px solid var(--border-gold,rgba(236,181,52,.35));background:var(--gold-subtle,rgba(236,181,52,.08));color:var(--gold-dark,#c49625);font-family:var(--font);font-size:.72rem;font-weight:700;cursor:pointer;flex-shrink:0}
.cp-copy:active{transform:scale(.95)}
</style>
<!-- Sheet: Cart -->
<div class="overlay" id="cartOv" onclick="closeSheet('cart')"></div>
<div class="sheet" id="cartSheet">
  <div class="handle"></div>
  <div class="sheet-hdr"><span class="sheet-title">🛒 سبد خرید</span><button class="close-x" onclick="closeSheet('cart')">×</button></div>
<div class="cart-msg-box hidden" id="cartMessage"></div>
  <div id="cartBody"><div class="cart-empty">سبد خرید شما خالی است</div></div>
  <div id="cartFoot" style="flex-shrink:0">
    <div class="cart-sum hidden" id="cartSum" style="margin-top:0;padding-top:12px">
      <div class="sum-row"><span class="sum-lbl">جمع اقلام</span><span class="sum-val" id="cartSubtotal">۰ تومان</span></div>
      <div class="sum-row"><span class="sum-lbl">هزینه ارسال (تخمینی)</span><span class="sum-val" id="cartShipFee">۰ تومان</span></div>
      <div class="sum-row"><span class="sum-lbl">جمع کل</span><span class="sum-val" id="cartTotal">۰ تومان</span></div>
      <div style="display:flex;gap:10px">
        <button class="checkout-btn" id="checkoutBtn" onclick="openConfirmSheet()" style="flex:1;margin-top:0">ثبت سفارش</button>
        <button class="cart-continue" onclick="closeSheet('cart')">ادامه‌ی خرید</button>
      </div>
      <div class="checkout-hint" id="checkoutHint" style="margin-bottom:0">سبد شما خالی است</div>
    </div>
  </div>
</div>

<!-- Sheet: Order Confirm -->
<div class="overlay" id="confOv" onclick="closeSheet('conf')"></div>
<div class="sheet" id="confSheet">
  <div class="handle"></div>
  <div class="sheet-title">👤 مشخصات تحویل گیرنده</div>
  <div class="conf-section-title">مشخصات فرد تحویل گیرنده</div>
  <div class="conf-grid">
    <div><label class="conf-lbl">نام <b>(ضروری)</b></label><input type="text" class="conf-in" id="confFirstName" maxlength="40" placeholder="نام"></div>
    <div><label class="conf-lbl">نام خانوادگی <b>(ضروری)</b></label><input type="text" class="conf-in" id="confLastName" maxlength="60" placeholder="نام خانوادگی"></div>
  </div>
  <div class="conf-lbl" style="margin-top:12px">شماره تماس <b>(ضروری)</b></div>
  <input type="tel" class="conf-in" id="confPhone" maxlength="11" dir="ltr" style="text-align:left" placeholder="09123456789">
  <div class="conf-section-title">نشانی تحویل گیرنده</div>
  <div class="conf-grid">
    <div><label class="conf-lbl">استان <b>(ضروری)</b></label><select class="conf-in" id="confProvince"><option value="">انتخاب استان</option></select></div>
    <div><label class="conf-lbl">شهر <b>(ضروری)</b></label><select class="conf-in" id="confCity" disabled><option value="">ابتدا استان را انتخاب کنید</option></select></div>
  </div>
  <div class="conf-lbl" style="margin-top:12px">نشانی <b>(ضروری)</b></div>
  <textarea class="conf-in" id="confAddress" maxlength="255" placeholder="نشانی کامل"></textarea>
  <div class="conf-grid conf-grid-three">
    <div><label class="conf-lbl">پلاک <b>(ضروری)</b></label><input type="text" class="conf-in" id="confPlaque" maxlength="20"></div>
    <div><label class="conf-lbl">واحد <b>(ضروری)</b></label><input type="text" class="conf-in" id="confUnit" maxlength="20"></div>
    <div><label class="conf-lbl">کد پستی (اختیاری)</label><input type="text" class="conf-in" id="confPostalCode" maxlength="10" inputmode="numeric"></div>
  </div>

<?php $conf_map_on = getSetting($conn, 'map_enabled', '0') === '1' && getSetting($conn, 'map_browser_key', '') !== ''; ?>
<?php if ($conf_map_on): ?>
<div id="confMapWrap" style="margin-top:12px">
  <style>#confMap,#confMap .mapboxgl-canvas-container,#confMap canvas{border-radius:14px}</style>
  <label class="conf-lbl" id="confMapLbl">موقعیت دقیق تحویل روی نقشه <b>(اختیاری)</b></label>
  <div style="position:relative;height:220px;border-radius:14px;overflow:hidden;isolation:isolate;border:1px solid var(--border)">
    <div id="confMap" style="width:100%;height:100%"></div>
    <div style="position:absolute;left:50%;top:50%;transform:translate(-50%,-100%);pointer-events:none;font-size:28px;z-index:5">📍</div>
  </div>
  <button type="button" class="conf-btn-ok" style="width:100%;margin-top:8px" onclick="confMapPick()">ثبت این موقعیت</button>
  <div class="conf-hint" id="confMapAddr" style="margin-top:6px">موقعیت هنوز ثبت نشده است</div>
  <input type="hidden" id="confLat" value="">
  <input type="hidden" id="confLng" value="">
  <input type="hidden" id="confMapAddrVal" value="">
</div>
<script>
var CONF_MAP_KEY = <?= json_encode(getSetting($conn, 'map_browser_key', ''), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
var CONF_MAP_LAT = <?= (float)getSetting($conn, 'map_default_lat', '32.4279') ?>;
var CONF_MAP_LNG = <?= (float)getSetting($conn, 'map_default_lng', '53.6880') ?>;
var CONF_MAP_ZOOM = <?= (int)getSetting($conn, 'map_default_zoom', '5') ?>;
<?php $cs_phone = ($_SESSION['sitak_user'] ?? ''); $cs_saved = null; if (preg_match('/^09[0-9]{9}$/', $cs_phone)) { $cs = $conn->prepare("SELECT delivery_lat, delivery_lng FROM users WHERE phone=? LIMIT 1"); $cs->bind_param('s', $cs_phone); $cs->execute(); $csr = $cs->get_result()->fetch_assoc(); if ($csr && $csr['delivery_lat'] !== null && $csr['delivery_lng'] !== null) $cs_saved = ['lat' => (float)$csr['delivery_lat'], 'lng' => (float)$csr['delivery_lng']]; } ?>
var CONF_MAP_SAVED = <?= json_encode($cs_saved) ?>;
var confMapObj = null;
function confMapLoadSdk(cb){
  if(window.__mapSdkReady){cb();return}
  if(window.__mapSdkLoading){window.__mapSdkLoading.push(cb);return}
  window.__mapSdkLoading=[cb];
  var css=document.createElement('link');css.rel='stylesheet';css.href='https://cdn.map.ir/web-sdk/1.4.4/css/mapp.min.css';document.head.appendChild(css);
  var files=['https://cdn.map.ir/web-sdk/1.4.4/js/jquery-3.2.1.min.js','https://cdn.map.ir/web-sdk/1.4.4/js/mapp.env.js','https://cdn.map.ir/web-sdk/1.4.4/js/mapp.min.js'];
  (function next(i){
    if(i>=files.length){window.__mapSdkReady=true;var q=window.__mapSdkLoading;window.__mapSdkLoading=null;q.forEach(function(f){f()});return}
    if(i===0&&window.jQuery){next(1);return}
    var sc=document.createElement('script');sc.src=files[i];sc.onload=function(){next(i+1)};sc.onerror=function(){window.__mapSdkLoading=null};document.body.appendChild(sc);
  })(0);
}
function confMapBoot(){
  var wrap=document.getElementById('confMapWrap');
  var el=document.getElementById('confMap');
  if(!wrap||!el||confMapObj||el.offsetWidth===0)return;
  confMapLoadSdk(function(){
    if(confMapObj||!window.mapboxgl)return;
    if(!window.__rtlSet){window.__rtlSet=1;mapboxgl.setRTLTextPlugin('https://cdn.map.ir/web-sdk/1.4.4/js/mapbox-gl-rtl-text-v0.2.0.js',null,true);}
    confMapObj=new mapboxgl.Map({container:'confMap',style:'https://map.ir/vector/styles/main/mapir-xyz-style.json',center:CONF_MAP_SAVED?[CONF_MAP_SAVED.lng,CONF_MAP_SAVED.lat]:[CONF_MAP_LNG,CONF_MAP_LAT],zoom:CONF_MAP_SAVED?16:CONF_MAP_ZOOM,accessToken:CONF_MAP_KEY,transformRequest:function(url){return {url:url,headers:{'x-api-key':CONF_MAP_KEY}}}});
    clearInterval(confMapTimer);
  });
}
function confMapPick(){
  if(!confMapObj||!confMapObj.getCenter){_ctoast('نقشه هنوز بارگذاری نشده است');return}
  var c=confMapObj.getCenter();
  document.getElementById('confLat').value=c.lat;
  document.getElementById('confLng').value=c.lng;
  document.getElementById('confMapAddr').textContent='در حال دریافت نشانی...';
  var fd=new FormData();fd.append('action','map_reverse');fd.append('lat',c.lat);fd.append('lng',c.lng);
  fetch('ajax.php',{method:'POST',body:scsrf(fd)}).then(function(r){return r.json()}).then(function(d){
    if(d.success){document.getElementById('confMapAddrVal').value=d.address;document.getElementById('confMapAddr').textContent='📍 '+d.address}
    else{document.getElementById('confMapAddrVal').value='';document.getElementById('confMapAddr').textContent=d.message||'نشانی پیدا نشد'}
  }).catch(function(){document.getElementById('confMapAddr').textContent='ارتباط با سرور برقرار نشد'});
}
var confMapTimer=setInterval(confMapBoot,600);
</script>
<?php endif; ?>
  <div class="conf-lbl" style="margin-top:12px">توضیحات تکمیلی (اختیاری)</div>
  <textarea class="conf-in" id="confExtraNotes" maxlength="500" placeholder="مثلاً زنگ واحد خراب است"></textarea>
<?php if (isset($conn) && getSetting($conn,'discount_enabled','0')==='1'): ?>
  <div class="conf-lbl" style="margin-top:12px">کد تخفیف (اختیاری)</div>
  <div style="display:flex;gap:8px"><input type="text" class="conf-in" id="confDiscountCode" maxlength="40" dir="ltr" style="text-align:left;flex:1" placeholder="مثلاً WELCOME10"><button type="button" class="conf-btn-ok" style="flex:0 0 auto;padding:10px 18px" onclick="applyDiscountCode()">اعمال</button></div>
  <div id="confDiscountMsg" style="font-size:.72rem;margin-top:6px"></div>
<?php endif; ?>
<?php if ($cs_card_pay['ready']): ?>
  <div class="conf-section-title">💳 روش پرداخت</div>
  <div id="confPayMethods" style="display:flex;flex-direction:column;gap:8px;margin-bottom:6px">
    <?php if ($cs_zarin_on): ?>
    <label class="pay-opt"><input type="radio" name="confPayMethod" value="online" checked onchange="payStyleSync()">
      <span>پرداخت آنلاین درگاه<span class="pay-sub">انتقال مستقیم به درگاه پرداخت امن</span></span></label>
    <?php endif; ?>
    <label class="pay-opt"><input type="radio" name="confPayMethod" value="card" <?= $cs_zarin_on ? '' : 'checked' ?> onchange="payStyleSync()">
      <span>کارت‌به‌کارت<span class="pay-sub">واریز به کارت و ثبت اطلاعات واریز؛ <?= pn($cs_card_pay['lock_minutes']) ?> دقیقه مهلت (کالا در این مدت برای شما رزرو می‌ماند)</span></span></label>
    <?php if ($cs_pending_on): ?>
    <label class="pay-opt"><input type="radio" name="confPayMethod" value="pending" onchange="payStyleSync()">
      <span>پرداخت پس از هماهنگی<span class="pay-sub">سفارش ثبت می‌شود و پرداخت بعداً هماهنگ می‌شود</span></span></label>
    <?php endif; ?>
  </div>
<?php endif; ?>
  <div id="confShipBox" style="margin-top:12px"></div>
  <span class="conf-hint">اطلاعات گیرنده برای آماده‌سازی و تحویل سفارش ثبت می‌شود.</span>
  <div class="conf-btn-row">
    <button class="conf-btn-ok" id="confSubmitBtn" onclick="confirmOrder()">تأیید و ثبت سفارش</button>
    <button class="conf-btn-no" onclick="closeSheet('conf');setTimeout(function(){openSheet('cart')},300)">بازگشت</button>
  </div>
</div>
<script>
<?php $shipCfg = isset($conn) ? sitak_shipping_settings($conn) : ['general_fee'=>0,'general_free_min'=>0,'general_encourage_pct'=>0,'scope_province'=>'','scope_city'=>'','scope_fee'=>0,'scope_min'=>0,'scope_free_min'=>0,'scope_encourage_pct'=>0]; ?>
var shipCfg=<?= json_encode($shipCfg, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
var discountOn=<?= (isset($conn) && getSetting($conn,'discount_enabled','0')==='1') ? 'true' : 'false' ?>;
var discountState={code:'',amount:0};
var provinceCities={"آذربایجان شرقی":["تبریز","مراغه","مرند","میانه","اهر","بناب","سراب","شبستر","آذرشهر","عجب‌شیر","ملکان","بستان‌آباد","اسکو","هریس","کلیبر","جلفا","ورزقان","هوراند","خداآفرین","چاراویماق","سهند","سردرود"],"آذربایجان غربی":["ارومیه","خوی","بوکان","مهاباد","میاندوآب","سلماس","پیرانشهر","نقده","تکاب","ماکو","سردشت","شاهین‌دژ","اشنویه","قره‌ضیاءالدین","شوط","سیه‌چشمه","پلدشت","چایپاره","میرآباد"],"اردبیل":["اردبیل","پارس‌آباد","مشگین‌شهر","خلخال","گرمی","نمین","بیله‌سوار","اصلاندوز","کوثر","سرعین","انگوت","جعفرآباد","هشتجین","آبی‌بیگلو"],"اصفهان":["اصفهان","کاشان","خمینی‌شهر","نجف‌آباد","شاهین‌شهر","شهرضا","فولادشهر","تیران","مبارکه","آران و بیدگل","زرین‌شهر","گلپایگان","فریدن","فلاورجان","دهاقان","نطنز","سمیرم","نائین","اردستان","خوانسار","شاهپورآباد","خوروبیابانک"],"البرز":["کرج","فردیس","کمال‌شهر","نظرآباد","محمدشهر","ماهدشت","مشکین‌دشت","هشتگرد","چهارباغ","اشتهارد","گرمدره","طالقان","کوهسار","تنکمان","آسارا"],"ایلام":["ایلام","دهلران","ایوان","آبدانان","دره‌شهر","مهران","سرابله","ارکواز","آسمان‌آباد","چوار","بدره","پهله","مورموری","زرنه","لومار"],"بوشهر":["بوشهر","برازجان","بندر گناوه","بندر کنگان","خورموج","عسلویه","جم","بندر دیلم","بندر دیر","چغادک","آباد","نخل تقی","دلوار","کاکی","وحدتیه","شبانکاره","اهرم"],"تهران":["تهران","اسلامشهر","شهریار","قدس","ملارد","صالحیه","ورامین","قرچک","پاکدشت","ری","دماوند","بومهن","پردیس","باقرشهر","پیشوا","رباط‌کریم","نسیم‌شهر","اندیشه","صباشهر","وحیدیه","فیروزکوه","لواسان","آبسرد","فشم","کهریزک"],"چهارمحال و بختیاری":["شهرکرد","بروجن","لردگان","فرخ‌شهر","فارسان","هفشجان","سامان","جونقان","کیار","بن","سورشجان","بلداجی","اردل","نقنه","کوهرنگ","فرودین"],"خراسان جنوبی":["بیرجند","قائن","طبس","فردوس","نهبندان","بشرویه","سرایان","سربیشه","اسلامیه","حاجی‌آباد","خوسف","آرین‌شهر","اسدیه","سه قلعه"],"خراسان رضوی":["مشهد","نیشابور","سبزوار","تربت حیدریه","کاشمر","قوچان","تربت جام","تایباد","چناران","سرخس","گناباد","فریمان","گلبهار","درگز","خواف","بردسکن","طرقبه","شاندیز","خلیل‌آباد","مه ولات","کلات","فیروزه"],"خراسان شمالی":["بجنورد","شیروان","اسفراین","آشخانه","جاجرم","فاروج","گرمه","راز","ایور","درق","شوقان","صفی‌آباد"],"خوزستان":["اهواز","دزفول","آبادان","بندر ماهشهر","خرمشهر","شوشتر","مسجدسلیمان","اندیمشک","بهبهان","ایذه","شوش","شادگان","رامهرمز","امیدیه","سوسنگرد","بندر امام خمینی","باغ‌ملک","هندیجان","لالی","هویزه","گتوند","آغاجاری","حمیدیه"],"زنجان":["زنجان","ابهر","خرمدره","قیدار","هیدج","صائین‌قلعه","آب‌بر","زرین‌آباد","ماهنشان","سلطانیه","سهرورد","سجاس","گرماب"],"سمنان":["سمنان","شاهرود","دامغان","گرمسار","مهدی‌شهر","سرخه","ایوانکی","بسطام","آرادان","مجن","کلاته خیج","شهمیرزاد","میامی"],"سیستان و بلوچستان":["زاهدان","زابل","ایرانشهر","چابهار","سراوان","خاش","کنارک","جالق","نیک‌شهر","پیشین","سوران","زهک","فنوج","مهرستان","دلگان","بمپور","قصرقند","راسک","میرجاوه","هیرمند"],"فارس":["شیراز","مرودشت","جهرم","فسا","کازرون","صدرا","داراب","فیروزآباد","لار","آباده","نورآباد","نی‌ریز","اقلید","استهبان","گراش","زرقان","کوار","لامرد","خرامه","مهر","فراشبند","صفاشهر","قیر و کارزین"],"قزوین":["قزوین","الوند","محمدیه","تاکستان","آبیک","بوئین‌زهرا","شریفیه","اقبالیه","بیدستان","محمودآباد نمونه","اسفرورین","ضیاءآباد","آوج"],"قم":["قم","قنوات","جعفریه","کهک","دستجرد","سلفچگان"],"کردستان":["سنندج","سقز","مریوان","بانه","قروه","کامیاران","بیجار","دیواندره","دهگلان","کانی‌دینار","سروآباد","یاسوکند","زرینه"],"کرمان":["کرمان","سیرجان","رفسنجان","جیرفت","بم","زرند","کهنوج","شهر بابک","بافت","بردسیر","عنبرآباد","راور","محمدآباد","نجف‌شهر","ماهان","منوجان","انار","رودبار جنوب","فهرج","کوهبنان","قلعه گنج"],"کرمانشاه":["کرمانشاه","اسلام‌آباد غرب","جوانرود","کنگاور","سرپل ذهاب","سنقر","هرسین","صحنه","پاوه","گیلانغرب","روانسر","قصر شیرین","تازه‌آباد","کرند غرب","سرمست","باینگان"],"کهگیلویه و بویراحمد":["یاسوج","دوگنبدان","دهدشت","چرام","لیکک","سی‌سخت","لنده","باشت","مارگون","پاتاوه","دیشموک","قلعه رئیسی"],"گلستان":["گرگان","گنبد کاووس","علی‌آباد کتول","بندر ترکمن","کلاله","آزادشهر","آق‌قلا","کردکوی","مینوشت","گالیکش","بندر گز","فاضل‌آباد","گمش‌تپه","رامیان","خان‌ببین","مراوه‌تپه"],"گیلان":["رشت","بندر انزلی","لاهیجان","لنگرود","تالش","آستارا","صومعه‌سرا","آستانه اشرفیه","رودسر","فومن","خمام","سیاهکل","رضوانشهر","ماسال","منجیل","رودبار","کلاچای","لشت نشا","کوچصفهان","املش"],"لرستان":["خرم‌آباد","بروجرد","دورود","کوهدشت","الیگودرز","نورآباد","الشتر","پلدختر","ازنا","کوهنانی","سپیددشت","زاغه","سراب دوره","معمولان"],"مازندران":["ساری","بابل","آمل","قائم‌شهر","بهشهر","چالوس","نکا","بابلسر","تنکابن","نوشهر","فریدونکنار","رامسر","جویبار","محمودآباد","امیرکلا","نور","گلوگاه","سرخرود","عباس‌آباد","کلاردشت","زیرآب","پل سفید"],"مرکزی":["اراک","ساوه","خمین","محلات","دلیجان","زرندیه","شازند","مهاجران","تفرش","میلاجرد","کمیجان","آشتیان","فرمهین","خنداب","نراق"],"هرمزگان":["بندرعباس","میناب","دهبارز","بندر لنگه","قشم","کیش","حاجی‌آباد","کنگ","بندر خمیر","پارسیان","جاسک","بندر چارک","بستک","سوزا","ابوموسی","بندر سیریک"],"همدان":["همدان","ملایر","نهاوند","اسدآباد","تویسرکان","بهار","کبودرآهنگ","لالجین","فامنین","رزن","مریانج","جورقان","قروه درجزین"],"یزد":["یزد","میبد","اردکان","بافق","مهریز","ابرکوه","اشکذر","هرات","زارچ","شاهدیه","تفت","حمیدیا","مروست","بهاباد","عقدا"]};
function updateShippingEligibility(){
  var p=document.getElementById('confProvince');
  var c=document.getElementById('confCity');
  var b=document.getElementById('confSubmitBtn');
  if(!p||!c||!b)return;
  b.disabled=false;
  b.style.opacity='';
  b.style.cursor='';
  b.title='';
  renderConfShip();
  if(c.value && Array.isArray(cart)){
    var q=shippingQuote(cartTotal(),p.value,c.value);
    if(!q.ok) _ctoast('حداقل مقدار خرید برای ارسال به محدوده‌ی '+'«'+shipCfg.scope_province+(shipCfg.scope_city?'، '+shipCfg.scope_city:'')+'»'+' '+_cpn(q.min)+' تومان است.');
  }
}
function fillProvinces(){
  var p=document.getElementById('confProvince');
  if(!p || p.options.length>1)return;
  Object.keys(provinceCities).forEach(function(name){var o=document.createElement('option');o.value=name;o.textContent=name;p.appendChild(o)});
  p.onchange=function(){fillCities(p.value,'');updateShippingEligibility();};
}
function fillCities(province,selected){
  var c=document.getElementById('confCity'); if(!c)return;
  c.innerHTML='<option value="">انتخاب شهر</option>';
  (provinceCities[province]||[]).forEach(function(name){var o=document.createElement('option');o.value=name;o.textContent=name;o.selected=name===selected;c.appendChild(o)});
  c.disabled=!(provinceCities[province]||[]).length;
  c.onchange=updateShippingEligibility;
  updateShippingEligibility();
}
function setProvinceCity(province,city){fillProvinces();document.getElementById('confProvince').value=province;fillCities(province,city)}
fillProvinces();
updateShippingEligibility();
var _cuser='<?= isset($_SESSION['sitak_user']) ? htmlspecialchars($_SESSION['sitak_user'], ENT_QUOTES, 'UTF-8') : '' ?>';
/* اگر لاگین از داخل فلو checkout انجام شده بود، آیتم‌های سبدِ مهمان
   را به کلید کاربر لاگین‌شده منتقل می‌کنیم (قبل از تعیین _cartKey/Cart). */
try{
  var _gj=sessionStorage.getItem('_cart_guest_items');
  var _gp=sessionStorage.getItem('_cart_guest_phone')||'';
  if(_cuser && _cuser!=='' && _gp && _gp===_cuser && _gj){
    var _j2=JSON.parse(_gj||'[]');
    if(_j2&&_j2.length>0){
      sessionStorage.setItem('sitak_cart_'+_cuser,_gj);
    }
  }
}catch(e){}
var _cartKey='sitak_cart_'+(_cuser||'_guest');
var cart=[];
try{var _storedCart=sessionStorage.getItem(_cartKey)||'[]';var _parsedCart=JSON.parse(_storedCart);cart=Array.isArray(_parsedCart)?_parsedCart:[]}catch(e){cart=[];try{sessionStorage.removeItem(_cartKey)}catch(ignore){}}
/* اگر صفحه با فلگ logged_out لود شد (بعد خروج)، سبد رندر قبلی را کاملاً کنار بگذار و خالی شو */
try{
  if(window.location.search.indexOf('logged_out=1')!==-1){
    cart=[];
    sessionStorage.removeItem(_cartKey);
    sessionStorage.removeItem('sitak_cart__guest');
    sessionStorage.removeItem('_cart_guest_items');
    sessionStorage.removeItem('_cart_guest_phone');
    sessionStorage.removeItem('_cart_resume_checkout');
  }
}catch(e){}
function _cpn(n){return String(n).replace(/\d/g,function(d){return '۰۱۲۳۴۵۶۷۸۹'[d]})}
function _cpnT(n){return _cpn(String(n).replace(/\B(?=(\d{3})+(?!\d))/g,'٬'))}
function _cesc(s){if(!s)return'';return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;')}
function _cmsg(m){var b=document.getElementById('cartMessage');if(!b)return;clearTimeout(b._timer);clearTimeout(b._timer2);b.textContent=m;b.classList.remove('hidden');void b.offsetHeight;b.classList.remove('msg-hide');b._timer=setTimeout(function(){b.classList.add('msg-hide');b._timer2=setTimeout(function(){b.classList.add('hidden')},550)},2500)}
function _ctoast(m){var t=document.getElementById('toast');if(!t){t=document.createElement('div');t.id='toast';t.className='toast';document.body.appendChild(t)}t.textContent=m;t.classList.add('show');clearTimeout(t._timer);t._timer=setTimeout(function(){t.classList.remove('show')},3000)}
function _cphone(){return (typeof window.USER_PHONE!=='undefined'&&window.USER_PHONE)?window.USER_PHONE:_cuser}
function persist(){sessionStorage.setItem(_cartKey,JSON.stringify(cart));renderCart();if(typeof updateProductQtyUI==='function')updateProductQtyUI();encourageCart();}
// پیام تشویقی: وقتی جمع سبد به درصد مشخصی از کف ارسال رایگان رسیده ولی هنوز به آن نرسیده
function _encourageMsg(sub,freeMin,pct){if(!(freeMin>0&&pct>0))return '';var low=Math.ceil(freeMin*(1-pct/100));if(sub>=low&&sub<freeMin)return _cpn(freeMin-sub)+' تومان دیگه تا ارسال رایگان مونده 🎉';return ''}
// حالت فعلی سبد: تحویل در محل (محدوده‌ی فروشگاه) یا ارسال عادی، همراه با حد ارسال رایگان و درصد تشویق
function _shipMode(){
  var onsite=cart.some(function(i){return !!i.onsite_product});
  var p=(document.getElementById('confProvince')||{}).value||'', c=(document.getElementById('confCity')||{}).value||'';
  var inScope=onsite||(shipCfg.scope_province!==''&&p===shipCfg.scope_province&&(shipCfg.scope_city===''||c===shipCfg.scope_city));
  return inScope?{onsite:onsite,free:+shipCfg.scope_free_min||0,pct:+shipCfg.scope_encourage_pct||0}:{onsite:false,free:+shipCfg.general_free_min||0,pct:+shipCfg.general_encourage_pct||0};
}
function _freePrev(){try{return sessionStorage.getItem(_cartKey+'_free')}catch(e){return null}}
function _setFreePrev(v){try{sessionStorage.setItem(_cartKey+'_free',v?'1':'0')}catch(e){}}
function encourageCart(){
  var m=_shipMode(), sub=cartTotal();
  var nowFree=cart.length>0&&m.free>0&&sub>=m.free;
  var prev=_freePrev();
  var prevFree=prev===null?nowFree:prev==='1';
  _setFreePrev(nowFree);
  if(cart.length===0)return;
  if(m.free>0&&prevFree!==nowFree){
    var what=m.onsite?'تحویل در محل':'ارسال';
    _ctoast(nowFree?('سبد شما به حد لازم رسید و '+what+' رایگان است 🎉'):('سبد شما دیگر شامل '+what+' رایگان نمی‌شود'));
    return;
  }
  var msg=_encourageMsg(sub,m.free,m.pct);
  if(msg)_ctoast(msg);
}
function _addProductGuard(data, done){
  var mixed=(cart||[]).some(function(it){return !!it.onsite_product!==!!data.onsite_product});
  if(mixed){_ctoast(data.onsite_product?'محصول تحویل در محل را نمی‌توان با محصولات عادی در یک سبد ترکیب کرد.':'برای سفارش محصول عادی، ابتدا محصولات تحویل در محل را از سبد حذف کنید.');return;}
  if(!data.onsite_product){done();return;}
  fetch('ajax.php?action=check_product_availability&id='+encodeURIComponent(data.id)).then(function(r){return r.json()}).then(function(d){if(!d.success){_ctoast(d.message||'در حال حاضر امکان افزودن این محصول وجود ندارد.');return;}done()}).catch(function(){_ctoast('بررسی روز و ساعت فروش محصول انجام نشد؛ اتصال را بررسی کنید.');});
}
function _stockLimitHit(item, nextQty){
  var s=item.stock;
  if(s===null||s===undefined||s==='')return false;
  s=+s;
  if(nextQty<=s)return false;
  _ctoast(s<=0?'«'+item.name+'» در حال حاضر موجود نیست.':'در حال حاضر فقط '+_cpn(s)+' عدد از «'+item.name+'» موجوده.');
  return true;
}
function addToCart(btn){
  var data={stock:(btn.dataset.stock===undefined||btn.dataset.stock==='')?null:+btn.dataset.stock,id:+btn.dataset.id,name:btn.dataset.name,price:+btn.dataset.price,img:btn.dataset.img||'',shipping_province:btn.dataset.shipProvince||'',shipping_city:btn.dataset.shipCity||'',onsite_product:btn.dataset.local==='1'};
  {var _e0=cart.find(function(i){return i.id===data.id});if(_stockLimitHit(data,(_e0?_e0.qty:0)+1))return;}
  _addProductGuard(data,function(){var ex=cart.find(function(i){return i.id===data.id});if(ex)ex.qty++;else cart.push({stock:data.stock,id:data.id,name:data.name,price:data.price,img:data.img,shipping_province:data.shipping_province,shipping_city:data.shipping_city,onsite_product:data.onsite_product,qty:1});persist();btn.classList.add('done');btn.textContent='✓';setTimeout(function(){btn.classList.remove('done');btn.textContent='+'},400);openSheet('cart');_cmsg(data.name+' به سبد اضافه شد');});
}
function rmFromCart(id){cart=cart.filter(function(i){return i.id!==id});persist()}
function chgQty(id,d){var it=cart.find(function(i){return i.id===id});if(!it)return;if(d>0&&_stockLimitHit(it,it.qty+1))return;if(d>0&&it.onsite_product){_addProductGuard(it,function(){it.qty+=d;persist()});return}it.qty+=d;if(it.qty<1)rmFromCart(id);else persist()}
function cartTotal(){return cart.reduce(function(s,i){return s+i.price*i.qty},0)}
function cartCount(){return cart.reduce(function(s,i){return s+i.qty},0)}
// آستانه‌های ارسال فقط روی جمع اقلام سبد (بدون هزینه‌ی ارسال) سنجیده می‌شوند
function curSubtotal(){return Array.isArray(cart)?cartTotal():0}
function shippingQuote(sub,p,c){
  var s=shipCfg, inScope=s.scope_province!==''&&p===s.scope_province&&(s.scope_city===''||c===s.scope_city);
  if(inScope){
    if(s.scope_min>0&&sub<s.scope_min) return {ok:false,fee:0,free:false,min:s.scope_min};
    var f=s.scope_free_min>0&&sub>=s.scope_free_min;
    return {ok:true,fee:f?0:s.scope_fee,free:f,min:0};
  }
  var g=s.general_free_min>0&&sub>=s.general_free_min;
  return {ok:true,fee:g?0:s.general_fee,free:g,min:0};
}
function shipFeeText(q){return q.fee===0?'رایگان':_cpn(q.fee)+' تومان'}
function renderCartTotals(){
  var sub=cartTotal(), q=shippingQuote(sub,'','');
  document.getElementById('cartSubtotal').textContent=_cpn(sub)+' تومان';
  document.getElementById('cartShipFee').textContent=shipFeeText(q);
  document.getElementById('cartTotal').textContent=_cpn(sub+q.fee)+' تومان';
}
function applyDiscountCode(viaLink){
  var inp=document.getElementById('confDiscountCode'), msg=document.getElementById('confDiscountMsg');
  if(!inp||!msg)return;
  var code=(inp.value||'').trim();
  if(!code){discountState={code:'',amount:0};msg.textContent='';renderConfShip();return}
  var fd=new FormData();fd.append('action','validate_discount');fd.append('code',code);fd.append('items',JSON.stringify(cart));
  if(viaLink)fd.append('via_link','1');
  fetch('ajax.php',{method:'POST',body:scsrf(fd)}).then(function(r){return r.json()}).then(function(d){
    if(d.success){discountState={code:d.code,amount:d.amount};msg.style.color='var(--green-text)';msg.textContent='کد تخفیف اعمال شد: '+_cpn(d.amount)+' تومان تخفیف'}
    else{discountState={code:'',amount:0};msg.style.color='var(--red)';msg.textContent=d.message||'کد تخفیف معتبر نیست.'}
    renderConfShip();
  }).catch(function(){discountState={code:'',amount:0};msg.style.color='var(--red)';msg.textContent='بررسی کد تخفیف انجام نشد؛ اتصال را بررسی کنید.';renderConfShip()});
}
/* لینک تخفیف: ?coupon=CODE کد را نگه می‌دارد و در شیت ثبت سفارش خودکار اعمال می‌شود */
(function(){try{var c=new URLSearchParams(location.search).get('coupon');if(c)sessionStorage.setItem('sitak_coupon',c)}catch(e){}})();
function prefillCoupon(){
  if(!discountOn)return;
  var inp=document.getElementById('confDiscountCode');if(!inp)return;
  if(!inp.value){try{var c=sessionStorage.getItem('sitak_coupon');if(c)inp.value=c}catch(e){}if(inp.value)return applyDiscountCode(true)}
  if(inp.value.trim())applyDiscountCode();
}
function renderConfShip(){
  var box=document.getElementById('confShipBox'); if(!box)return;
  var p=document.getElementById('confProvince'), c=document.getElementById('confCity');
  var sub=curSubtotal(), q=shippingQuote(sub, p?p.value:'', c?c.value:'');
  var pv=p?p.value:'', cv=c?c.value:'';
  if(shipCfg.scope_province!==''&&pv===shipCfg.scope_province&&(shipCfg.scope_city===''||cv===shipCfg.scope_city)){var sm=_encourageMsg(sub,shipCfg.scope_free_min,shipCfg.scope_encourage_pct);if(sm)_ctoast(sm);}
  var disc=(discountOn&&discountState.amount>0)?discountState.amount:0;
  var html='<div class="sum-row"><span class="sum-lbl">جمع اقلام</span><span class="sum-val">'+_cpn(sub)+' تومان</span></div>'
    +(disc?'<div class="sum-row"><span class="sum-lbl">تخفیف ('+discountState.code+')</span><span class="sum-val">-'+_cpn(disc)+' تومان</span></div>':'')
    +'<div class="sum-row"><span class="sum-lbl">هزینه ارسال</span><span class="sum-val">'+(q.ok?shipFeeText(q):'—')+'</span></div>'
    +'<div class="sum-row"><span class="sum-lbl">مبلغ قابل پرداخت</span><span class="sum-val">'+(q.ok?_cpn(sub-disc+q.fee)+' تومان':'—')+'</span></div>';
  if(!q.ok) html+='<div style="font-size:.72rem;color:var(--red);margin-top:6px">حداقل مقدار خرید برای ارسال به محدوده‌ی '+'«'+shipCfg.scope_province+(shipCfg.scope_city?'، '+shipCfg.scope_city:'')+'»'+' '+_cpn(q.min)+' تومان است.</div>';
  box.innerHTML=html;
}
function renderCart(){
  var count=cartCount();
  var ce=document.getElementById('cartCount');
  var body=document.getElementById('cartBody');
  var sum=document.getElementById('cartSum');
  if(ce){if(count>0){ce.textContent=_cpn(count);ce.classList.remove('hidden')}else{ce.classList.add('hidden')}}
  if(cart.length===0){
    body.innerHTML='<div class="cart-empty">سبد خرید شما خالی است</div>';
    sum.classList.add('hidden');
    return;
  }
  body.innerHTML=cart.map(function(it){
    var imgHtml=it.img?'<img src="'+_cesc(it.img)+'" class="cart-thumb" loading="lazy" data-em="<?= htmlspecialchars($custom_emoji, ENT_QUOTES, 'UTF-8') ?>" onerror="this.replaceWith(Object.assign(document.createElement(\'div\'),{className:\'cart-thumb\',textContent:this.dataset.em,style:\'display:flex;align-items:center;justify-content:center;font-size:1.4rem\'}))">':'<div class="cart-thumb" style="display:flex;align-items:center;justify-content:center;font-size:1.4rem"><?= htmlspecialchars($custom_emoji, ENT_QUOTES, 'UTF-8') ?></div>';
    return '<div class="cart-item">'+imgHtml+'<div class="ci-info"><div class="ci-name">'+_cesc(it.name)+'</div><div class="ci-price">'+_cpn(it.price)+' تومان</div><div class="ci-qty"><button class="qty-btn" onclick="chgQty('+it.id+',-1)">−</button><span class="qty-val">'+_cpn(it.qty)+'</span><button class="qty-btn" onclick="chgQty('+it.id+',1)">+</button><button class="qty-btn del-btn" onclick="rmFromCart('+it.id+')" title="حذف">🗑️</button></div></div></div>';
  }).join('');
  sum.classList.remove('hidden');
  renderCartTotals();
  var hint2=document.getElementById('checkoutHint');
  if(hint2)hint2.textContent=cart.length===0?'سبد شما خالی است':'';
}
function openConfirmSheet(){
  var uph=_cphone();
  if(!uph){loginFrom='cart';closeSheet('cart');setTimeout(function(){openSheet('reg')},300);return}
  if(cart.length===0){_ctoast('سبد شما خالی است');return}
  var localItems=cart.filter(function(i){return !!i.onsite_product});
  if(!localItems.length){openConfirmSheet._continue();return;}
  // حداقل خرید محدوده: مجموع اقلام محصولات تحویل در محل (بدون هزینه‌ی ارسال) باید به حداقل برسد
  if(shipCfg.scope_province!=='' && shipCfg.scope_min>0){
    var localSum=localItems.reduce(function(s,i){return s+i.price*i.qty},0);
    if(localSum<shipCfg.scope_min){
      _ctoast('حداقل مقدار خرید برای ارسال محصولات تحویل در محل به «'+shipCfg.scope_province+(shipCfg.scope_city?'، '+shipCfg.scope_city:'')+'» '+_cpn(shipCfg.scope_min)+' تومان است. مجموع محصولات تحویل در محل سبد شما '+_cpn(localSum)+' تومان است.');
      return;
    }
  }
  Promise.all(localItems.map(function(it){return fetch('ajax.php?action=check_product_availability&id='+encodeURIComponent(it.id)).then(function(r){return r.json()})})).then(function(results){
    var blocked=results.find(function(r){return !r.success});
    if(blocked){_ctoast(blocked.message||'در حال حاضر امکان ثبت سفارش وجود ندارد.');return;}
    openConfirmSheet._continue();
  }).catch(function(){_ctoast('بررسی روز و ساعت محصولات تحویل در محل انجام نشد؛ اتصال را بررسی کنید.');});
}
openConfirmSheet._continue=function(){
  var uph=_cphone();
  if(!uph)return;
  prefillCoupon();
  /* گام تأیید: پاپآپ نام و نشانی */
  fetch('ajax.php?action=get_profile').then(function(r){return r.json()}).then(function(d){
    if(d.success){
      document.getElementById('confFirstName').value=d.first_name||'';
      document.getElementById('confLastName').value=d.last_name||'';
      document.getElementById('confPhone').value=d.phone||uph;
      document.getElementById('confAddress').value=d.address||'';
      document.getElementById('confPlaque').value=d.plaque||'';
      document.getElementById('confUnit').value=d.unit||'';
      document.getElementById('confPostalCode').value=d.postal_code||'';
      document.getElementById('confExtraNotes').value=d.extra_notes||'';
      setProvinceCity(d.province||'',d.city||'');
    }
    closeSheet('cart');
    setTimeout(function(){confMapRefreshLabel();openSheet('conf')},300);
  }).catch(function(){
    closeSheet('cart');
    setTimeout(function(){confMapRefreshLabel();openSheet('conf')},300);
  });
}
function shippingConflicts(province,city){
  var bad=[];
  (cart||[]).forEach(function(it){
    if(!it.onsite_product)return;
    var rp=it.shipping_province||'', rc=it.shipping_city||'';
    if((rp && rp!==province)||(rc && rc!==city)) bad.push({name:it.name,scope:rc||rp});
  });
  return bad;
}
function showShippingConflict(bad){
  var scopes=[];bad.forEach(function(x){if(scopes.indexOf(x.scope)<0)scopes.push(x.scope)});
  _ctoast('در سبد خرید، محصولی دارید که فقط ارسال به محدوده‌ی «'+scopes.join('، ')+'» دارد.');
}
function confMapRefreshLabel(){var l=document.getElementById('confMapLbl');if(!l)return;var req=cart.some(function(i){return !!i.onsite_product});l.innerHTML=req?'موقعیت دقیق تحویل روی نقشه <b style="color:var(--gold-dark)">(اجباری)</b>':'موقعیت دقیق تحویل روی نقشه <b>(اختیاری)</b>'}
function confirmOrder(){
  if(document.getElementById('confMapWrap')&&cart.some(function(i){return !!i.onsite_product})&&!(document.getElementById('confLat')||{}).value){_ctoast('برای تحویل حضوری، موقعیت را روی نقشه ثبت کن');return}
  var fa='۰۱۲۳۴۵۶۷۸۹', en='0123456789';
  function latin(v){return String(v).replace(/[۰-۹]/g,function(d){return en[fa.indexOf(d)]})}
  var first=document.getElementById('confFirstName').value.trim(), last=document.getElementById('confLastName').value.trim();
  var phone=latin(document.getElementById('confPhone').value.trim()), province=document.getElementById('confProvince').value, city=document.getElementById('confCity').value;
  var addr=document.getElementById('confAddress').value.trim(), plaque=latin(document.getElementById('confPlaque').value.trim()), unit=latin(document.getElementById('confUnit').value.trim()), postal=latin(document.getElementById('confPostalCode').value.trim()), extra=document.getElementById('confExtraNotes').value.trim();
  var fields=[
    ['confFirstName',!!first],['confLastName',!!last],['confPhone',/^09[0-9۰-۹]{9}$/.test(phone)],
    ['confProvince',!!province],['confCity',!!city],['confAddress',!!addr],['confPlaque',!!plaque],['confUnit',!!unit]
  ];
  document.querySelectorAll('#confSheet .conf-in').forEach(function(el){el.classList.remove('conf-invalid')});
  var invalid=false;
  fields.forEach(function(pair){if(!pair[1]){document.getElementById(pair[0]).classList.add('conf-invalid');invalid=true}});
  if(invalid){_ctoast('همه فیلدهای ضروری را تکمیل کنید');return}
  if(postal && !/^[0-9۰-۹]{10}$/.test(postal)){document.getElementById('confPostalCode').classList.add('conf-invalid');_ctoast('کد پستی باید ۱۰ رقم باشد');return}
  var shippingBad=shippingConflicts(province,city); if(shippingBad.length){showShippingConflict(shippingBad);return}
  var shipQ=shippingQuote(cartTotal(),province,city);
  if(!shipQ.ok){_ctoast('حداقل مقدار خرید برای ارسال به محدوده‌ی '+'«'+shipCfg.scope_province+(shipCfg.scope_city?'، '+shipCfg.scope_city:'')+'»'+' '+_cpn(shipQ.min)+' تومان است.');return}
  var btn=document.getElementById('confSubmitBtn');
  btn.disabled=true;btn.textContent='در حال ثبت...';
  var pf=new FormData();
  pf.append('action','update_profile');
  pf.append('first_name',first);pf.append('last_name',last);pf.append('address',addr);pf.append('province',province);pf.append('city',city);pf.append('plaque',plaque);pf.append('unit',unit);pf.append('postal_code',postal);pf.append('extra_notes',extra);
  var od=new FormData();
  od.append('action','create_order');
  od.append('items',JSON.stringify(cart));
  od.append('total',String(cartTotal()+shipQ.fee));
  od.append('note',(document.getElementById('confExtraNotes')||{value:''}).value.trim());
  od.append('discount_code', (discountOn&&discountState.code)?discountState.code:'');
  od.append('recipient_first_name',first);od.append('recipient_last_name',last);od.append('recipient_phone',phone);
  od.append('recipient_province',province);od.append('recipient_city',city);od.append('recipient_address',addr);
  od.append('recipient_plaque',plaque);od.append('recipient_unit',unit);od.append('recipient_postal_code',postal);
  od.append('extra_notes',extra);
  var _cl=document.getElementById('confLat'),_cg=document.getElementById('confLng'),_ca=document.getElementById('confMapAddrVal');
  od.append('delivery_lat',_cl?_cl.value:'');od.append('delivery_lng',_cg?_cg.value:'');od.append('delivery_address',_ca?_ca.value:'');
  var _pmEl=document.querySelector('input[name="confPayMethod"]:checked');
  od.append('payment_method',_pmEl?_pmEl.value:'');
  fetch('ajax.php',{method:'POST',body:scsrf(pf)}).then(function(r){return r.json()}).then(function(pd){
    return fetch('ajax.php',{method:'POST',body:scsrf(od)}).then(function(r){
      return r.text().then(function(raw){
        try{return JSON.parse(raw)}catch(e){throw new Error('پاسخ نامعتبر از سرور دریافت شد')}
      });
    }).then(function(odr){return {profile:pd,order:odr}});
  }).then(function(res){
    btn.disabled=false;btn.textContent='تأیید و ثبت سفارش';
    if(res.order&&res.order.success){
      cart=[];discountState={code:'',amount:0};persist();closeSheet('conf');
      if(res.order.payment_url){
        _ctoast('در حال انتقال به درگاه پرداخت...');
        setTimeout(function(){window.location.assign(res.order.payment_url)},350);
      }else if(res.order.payment_method==='card'&&res.order.card){
        openCardPaySheet(res.order.card);
      }else{
        _ctoast('سفارش شما ثبت شد ✅');
        setTimeout(function(){location.href='orders.php'},900);
      }
    }else if(res.order&&res.order.stock){
      closeSheet('conf');setTimeout(function(){openSheet('cart')},300);_ctoast(res.order.message);
    }else{_ctoast((res.order&&res.order.message)||(res.profile&&res.profile.message)||'خطا در ثبت سفارش')}
  }).catch(function(){
    btn.disabled=false;btn.textContent='تأیید و ثبت سفارش';
    _ctoast('خطا در ارتباط با سرور');
  });
}
function backToCart(){closeSheet('conf');setTimeout(function(){openSheet('cart')},300)}
renderCart();
/* بعد از ریفرش ناشی از لاگین در فلو checkout: مستقیم شیت تایید نام/نشانی */
(function(){
  try{
    if(sessionStorage.getItem('_cart_resume_checkout')!=='1')return;
    sessionStorage.removeItem('_cart_resume_checkout');
    sessionStorage.removeItem('_cart_guest_items');
    sessionStorage.removeItem('_cart_guest_phone');
    setTimeout(function(){ try{ openConfirmSheet(); }catch(e){} },450);
  }catch(e){}
})();
</script>

<!-- Sheet: پرداخت کارت‌به‌کارت -->
<div class="overlay" id="cardPayOv"></div>
<div class="sheet" id="cardPaySheet">
  <div class="handle"></div>
  <div class="sheet-hdr"><span class="sheet-title">💳 پرداخت کارت‌به‌کارت</span><button class="close-x" onclick="closeSheet('cardPay')">×</button></div>
  <div class="cp-amount-box">مبلغ قابل واریز: <strong id="cpAmount">۰ تومان</strong></div>
  <div class="cp-timer" id="cpTimer">⏳ <span id="cpTimerVal">--:--</span> تا پایان مهلت واریز و ثبت اطلاعات</div>
  <p class="cp-note"><?= nl2br(htmlspecialchars($cs_popup_text, ENT_QUOTES, 'UTF-8')) ?></p>
  <div class="cp-row" id="cpOwnerRow"><span class="cp-lbl">به نام</span><span class="cp-val" id="cpOwner"></span></div>
  <div class="cp-row" id="cpCardRow">
    <span class="cp-lbl">شماره کارت</span>
    <span class="cp-val" id="cpCard" dir="ltr" style="text-align:left"></span>
    <button type="button" class="cp-copy" onclick="cpCopy('cpCardRaw',this)">کپی 📋</button>
  </div>
  <div class="cp-row" id="cpIbanRow">
    <span class="cp-lbl">شماره شبا</span>
    <span class="cp-val" id="cpIban" dir="ltr" style="text-align:left;font-size:.8rem"></span>
    <button type="button" class="cp-copy" onclick="cpCopy('cpIbanRaw',this)">کپی 📋</button>
  </div>
  <input type="hidden" id="cpCardRaw"><input type="hidden" id="cpIbanRaw">
  <div class="conf-btn-row" style="margin-top:16px">
    <button class="conf-btn-ok" style="height:48px;padding:0 14px;box-sizing:border-box" onclick="location.href='orders.php'">ثبت اطلاعات واریز</button>
    <button class="conf-btn-no" style="height:48px;padding:0 14px;box-sizing:border-box" onclick="closeSheet('cardPay')">بستن</button>
  </div>
  <p class="conf-hint" style="margin-top:10px;text-align:center">با بستن این پنجره اتفاقی نمی‌افتد؛ سفارش با تایمر مهلت و فرم ثبت اطلاعات واریز، در صفحه‌ی سفارش‌ها منتظر توست.</p>
</div>
<script>
var cpTimerInt=null;
function cpStartTimer(expiresTs){
  clearInterval(cpTimerInt);
  var box=document.getElementById('cpTimer');
  function tick(){
    var left=expiresTs-Math.floor(Date.now()/1000);
    var el=document.getElementById('cpTimerVal');
    if(left<=0){clearInterval(cpTimerInt);box.classList.add('cp-dead');box.innerHTML='⛔ مهلت واریز تمام شد — این سفارش خودکار لغو می‌شود';return}
    if(el){var m=Math.floor(left/60),s=left%60;el.textContent=_cpn((m<10?'0':'')+m+':'+(s<10?'0':'')+s)}
  }
  box.classList.remove('cp-dead');
  box.innerHTML='⏳ <span id="cpTimerVal">--:--</span> تا پایان مهلت واریز و ثبت اطلاعات';
  tick();cpTimerInt=setInterval(tick,1000);
}
function openCardPaySheet(card){
  document.getElementById('cpAmount').textContent=_cpnT(card.amount)+' تومان';
  document.getElementById('cpOwner').textContent=card.owner||'—';
  document.getElementById('cpOwnerRow').style.display=card.owner?'flex':'none';
  document.getElementById('cpCard').textContent=_cpn(card.card_number||'');
  document.getElementById('cpCardRaw').value=card.card_raw||'';
  document.getElementById('cpCardRow').style.display=card.card_raw?'flex':'none';
  document.getElementById('cpIban').textContent=_cpn(card.iban||'');
  document.getElementById('cpIbanRaw').value=card.iban_raw||'';
  document.getElementById('cpIbanRow').style.display=card.iban_raw?'flex':'none';
  cpStartTimer(card.expires);
  closeSheet('conf');
  setTimeout(function(){openSheet('cardPay')},300);
}
function cpFallback(txt,done){
  var ta=document.createElement('textarea');ta.value=txt;ta.style.position='fixed';ta.style.opacity='0';
  document.body.appendChild(ta);ta.select();
  try{document.execCommand('copy');done()}catch(e){_ctoast('کپی نشد؛ شماره را دستی انتخاب کن')}
  document.body.removeChild(ta);
}
function cpCopy(id,btn){
  var txt=(document.getElementById(id)||{}).value||'';
  if(!txt){_ctoast('مقداری برای کپی وجود ندارد');return}
  function done(){var t=btn.textContent;btn.textContent='✓ کپی شد';_ctoast('کپی شد 📋');setTimeout(function(){btn.textContent=t},1500)}
  if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(txt).then(done).catch(function(){cpFallback(txt,done)})}
  else cpFallback(txt,done);
}
function payStyleSync(){
  document.querySelectorAll('#confPayMethods .pay-opt').forEach(function(l){
    var i=l.querySelector('input');
    l.classList.toggle('pay-sel',!!(i&&i.checked));
  });
}
payStyleSync();
</script>
