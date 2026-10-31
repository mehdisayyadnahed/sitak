<?php
/* auth_sheet.php — پاپ‌آپ ورود/ثبت‌نام یکسان در همه صفحات (CSS + HTML + JS)
   استفاده: در انتهای صفحه (قبل از </body>) اینکلود شود:
   <?php include 'auth_sheet.php'; ?> */
$captcha_n1 = rand(1, 10);
$captcha_n2 = rand(1, 10);
$_SESSION['captcha_answer'] = $captcha_n1 + $captcha_n2;
?>
<style>
.overlay{position:fixed;inset:0;background:var(--overlay-bg,rgba(0,0,0,.3));backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);z-index:100;opacity:0;pointer-events:none;transition:opacity .3s}
.overlay.open{opacity:1;pointer-events:auto}
.sheet{position:fixed;bottom:0;left:0;right:0;z-index:101;width:auto;max-width:100vw;box-sizing:border-box;overflow-x:hidden;background:var(--surface);border-radius:22px 22px 0 0;padding:0 18px 18px;padding-bottom:calc(18px + var(--safe-b,0px));max-height:82vh;overflow-y:auto;transform:translateY(100%);transition:transform .38s cubic-bezier(.32,.72,0,1),background .35s;-webkit-overflow-scrolling:touch;box-shadow:var(--sheet-shadow,0 -10px 40px rgba(0,0,0,.08))}
.sheet.open{transform:translateY(0)}
.sheet *{max-width:100%;box-sizing:border-box}
.sheet input,.sheet button,.sheet textarea{min-width:0}
/* شیت بسته نباید سایه بیندازد؛ سایه‌اش از لبه پایین صفحه روی فوتر هاله می‌ساخت */
.sheet:not(.open){box-shadow:none}
.handle{width:34px;height:4px;background:#d1d1d6;border-radius:4px;margin:10px auto 18px}
.sheet-title{font-size:1.05rem;font-weight:700;text-align:center;margin-bottom:16px}
.fg{margin-bottom:13px}
.fg label{display:block;font-size:.75rem;font-weight:600;color:var(--text2);margin-bottom:5px}
.fh{font-size:.65rem;color:var(--text3);margin-top:3px}
.btn-p{width:100%;padding:14px;background:linear-gradient(135deg,var(--gold,#ecb534),var(--gold-dark,#c49625));color:#fff;border:none;border-radius:var(--rss,10px);font-family:var(--font);font-size:.92rem;font-weight:700;cursor:pointer;transition:all .12s;}
.btn-p:active{}
.captcha-block{margin-bottom:14px}
@media(min-width:481px){
  .overlay{inset:auto;position:fixed;top:0;left:50%;transform:translateX(-50%);width:480px;height:100vh}
  .sheet{left:50%;right:auto;transform:translate(-50%,100%);width:480px;max-width:calc(100vw - 24px)}
  .sheet.open{transform:translate(-50%,0)}
}
.captcha-block label{display:block;font-size:.75rem;font-weight:600;color:var(--text2);margin-bottom:5px}
.captcha-row{display:flex;align-items:center;gap:10px;background:var(--bg);border:1.5px solid var(--border);border-radius:var(--rss,10px);padding:12px 14px;margin-bottom:10px;transition:border-color .2s,background .35s}
.captcha-q{font-size:1.15rem;font-weight:700;color:var(--gold-dark,#c49625);flex:1;text-align:center;letter-spacing:2px;direction:ltr}
.captcha-refresh{width:38px;height:38px;border-radius:50%;border:1px solid var(--border);background:var(--card-el,#f0eff2);display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .2s;flex-shrink:0}
.captcha-row{position:relative;justify-content:center}.captcha-q{flex:0 1 auto}.captcha-refresh{position:absolute;left:10px;top:50%;margin-top:-19px}
.captcha-refresh:active{background:var(--gold-subtle);border-color:var(--gold);transform:rotate(180deg)}
.captcha-refresh svg{display:block}
#captchaAnswer{text-align:center !important;direction:ltr}
#otpSheet .fg{text-align:center}
#otpSheet .otp-captcha-block>label{font-size:.75rem;font-weight:600;color:var(--text2);margin-bottom:5px}
#otpSheet #smsOtp{width:100%;height:auto;margin-bottom:0;padding:13px 14px;background:var(--input-bg,#f0eff2);border:1.5px solid var(--border);border-radius:var(--rss,10px);font-family:var(--font);font-size:.92rem;line-height:1.2;text-align:center!important;letter-spacing:4px}
#otpSheet #smsOtp:focus{border-color:var(--gold);box-shadow:none}
#otpSheet .otp-captcha-block>label{font-size:.8rem}.otp-instruction{font-size:.8rem!important}#otpPhoneRow{display:inline-flex;align-items:center;justify-content:center;gap:9px;max-width:100%;direction:ltr;font-size:.8rem;line-height:2.05}#otpSheet .fh{font-size:.8rem;line-height:2.05}#editOtpPhone{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;padding:0;border:1px solid var(--border);border-radius:50%;background:var(--card-el);color:var(--text2);cursor:pointer;font-size:.82rem;vertical-align:middle}#editOtpPhone{-webkit-tap-highlight-color:transparent;outline:none;-webkit-appearance:none;appearance:none}#editOtpPhone:focus,#editOtpPhone:focus-visible{outline:none;box-shadow:none}#editOtpPhone:active{transform:scale(.92);background:var(--gold-subtle);border-color:var(--gold)}
/* استایل مرجع فرم ورود (نسخه صفحه خانه) — تا در همه صفحات یکسان دیده شود */
#regSheet .fi,#refSheet .fi{width:100%;padding:13px 14px;background:var(--input-bg,#f0eff2);border:1.5px solid var(--border);border-radius:var(--rss,10px);color:var(--text);font-family:var(--font);font-size:.92rem;outline:none;transition:border-color .2s,background .35s;margin-bottom:0}
#regSheet .fi::placeholder,#refSheet .fi::placeholder{color:var(--text3)}
#regSheet .fi:focus,#refSheet .fi:focus{border-color:var(--gold,#ecb534)}
#regSheet #regPhone{text-align:center!important;direction:ltr}
.pw-wrap{position:relative;display:block;width:100%}.pw-wrap .fi{padding-left:48px!important;padding-right:14px!important}.pw-eye{position:absolute;left:0;top:0;height:100%;background:none;border:none;cursor:pointer;color:#86868b;padding:0 12px;display:flex;align-items:center;justify-content:center}.pw-eye:hover{color:var(--text2)}.c-chip{min-height:28px;display:inline-flex;align-items:center;gap:5px;padding:6px 13px;background:var(--bg);border:1px solid var(--border);border-radius:20px;font-size:.72rem;font-weight:600;color:var(--text2);text-decoration:none}.c-chip:active{background:var(--gold-subtle);border-color:var(--border-gold);color:var(--gold-dark)}
.btn-g{width:100%;padding:11px;background:0;border:0;color:var(--text2);font-family:var(--font);font-size:.8rem;cursor:pointer;margin-top:6px}
</style>
<!-- Sheet: Register -->
<div class="overlay" id="regOv" onclick="closeSheet('reg')"></div>
<div class="sheet" id="regSheet">
  <div class="handle"></div>
  <div class="sheet-title">🌐 ورود یا ثبت‌نام</div>
  <div class="fg">
    <label>شماره موبایل (الزامی)</label>
    <input type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-10000px;width:1px;height:1px;opacity:0" value="">
    <input type="tel" class="fi" id="regPhone" placeholder="۰۹۱۲۳۴۵۶۷۸۹" maxlength="11" dir="ltr" style="text-align:center">
  </div>
  <!-- کپچا -->
  <div class="captcha-block">
    <label>سوال امنیتی</label>
    <div class="captcha-row">
      <button type="button" class="captcha-refresh" onclick="refreshCaptcha()" title="سوال جدید"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg></button>
      <span class="captcha-q" id="captchaQ"><?= pn($captcha_n1) ?> + <?= pn($captcha_n2) ?> = ?</span>
    </div>
    <input type="text" inputmode="numeric" class="fi" id="captchaAnswer" placeholder="پاسخ را وارد کنید" style="margin-bottom:0;text-align:center !important;direction:ltr" onkeydown="if(event.key==='Enter'){doRegister()}">
  </div>
  <button class="btn-p" id="regSubmitBtn" onclick="doRegister()">ثبت‌نام / ورود</button>
</div>

<!-- Sheet: SMS OTP -->
<div class="overlay" id="otpOv" onclick="closeSheet('otp')"></div>
<div class="sheet" id="otpSheet">
  <div class="handle"></div>
  <div class="sheet-title" id="otpTitle">🔐 تأیید شماره موبایل</div>
  <div id="otpSandboxCode" style="display:none;background:var(--gold-subtle);border:1px solid var(--border-gold);border-radius:var(--rss);padding:10px 12px;margin-bottom:14px;text-align:center;color:var(--gold-dark);font-size:.82rem;line-height:1.8">
    کد تست Sandbox: <strong id="otpSandboxCodeValue" dir="ltr" style="font-size:1.1rem;letter-spacing:3px"></strong>
  </div>
  <p class="fh otp-instruction" style="display:block;text-align:center;line-height:1.8;margin-bottom:8px"><span id="otpInstrText">کد تأیید ارسال‌شده به شماره‌ی زیر را وارد کن:</span><br><span id="otpPhoneRow"><strong id="otpPhone" dir="ltr"></strong><button type="button" id="editOtpPhone" onclick="editOtpPhone()" title="اصلاح شماره" aria-label="اصلاح شماره">✎</button></span></p>
  <div class="captcha-block otp-captcha-block">
    <label id="otpLabel">کد تأیید پیامکی</label>
    <?php $otpDigits = sitak_otp_digits($conn); ?>
    <input type="text" inputmode="numeric" class="fi" id="smsOtp" maxlength="<?= $otpDigits ?>" placeholder="کد <?= pn($otpDigits) ?> رقمی" dir="ltr" onkeydown="if(event.key==='Enter'){verifySmsOtp()}">
    <span class="fh" id="otpValidity">اعتبار کد ۳ دقیقه است.</span>
  </div>
  <button class="btn-p" id="otpSubmitBtn" onclick="verifySmsOtp()">تأیید و ورود</button>
  <button type="button" class="btn-g" id="resendOtpBtn" onclick="resendSmsOtp()" disabled>ارسال دوباره کد <span id="resendOtpTimer">(۶۰)</span></button>
  <button type="button" class="btn-g" id="otpForgotBtn" onclick="openForgot()" style="display:none">گذرواژه را فراموش کرده‌ام</button>
</div>

<!-- Sheet: Referral (کاربر جدید) -->
<div class="overlay" id="refOv"></div>
<div class="sheet" id="refSheet">
  <div class="handle"></div>
  <div class="sheet-title">🎁 شماره موبایل معرف (اختیاری)</div>
  <div class="fg">
    <label>شماره موبایل معرف</label>
    <input type="tel" class="fi" id="refInput" placeholder="شماره موبایل معرف" maxlength="11" dir="ltr" style="text-align:center">
  <span class="fh" id="referralHint"><?= htmlspecialchars(getSetting($conn, 'referral_hint', 'با ثبت شماره معرف، هم شما و هم معرفتان یک شانس اضافه دریافت می‌کنید.'), ENT_QUOTES, 'UTF-8') ?></span>
  </div>
  <div style="display:flex;gap:10px">
    <button class="btn-p" onclick="submitReferral()" style="flex:1">ثبت معرف</button>
    <button class="btn-g" onclick="skipReferral()" style="flex:1;margin-top:0;padding:14px;background:var(--card-el);;border-radius:var(--rss,10px);font-family:var(--font);font-size:.92rem;font-weight:700;color:var(--text2)">معرف ندارم</button>
  </div>
</div>
<!-- Sheet: تعیین گذرواژه ورود (کاربر جدید) -->
<div class="overlay" id="pwSetOv"></div>
<div class="sheet" id="pwSetSheet">
  <div class="handle"></div>
  <div class="sheet-title">🔐 تعیین گذرواژه ورود</div>
  <p class="fh" id="pwSetHint" style="text-align:center;line-height:1.8;margin-bottom:12px">یک گذرواژه عددی برای ورود بعدی انتخاب کن.</p>
  <div class="fg"><label>گذرواژه جدید</label><span class="pw-wrap"><input type="password" inputmode="numeric" class="fi" id="pwNew" maxlength="8" dir="ltr" style="text-align:center" placeholder=""><button type="button" class="pw-eye" onclick="togglePwVis(this)" aria-label="نمایش گذرواژه"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button></span></div>
  <div class="fg"><label>تکرار گذرواژه جدید</label><span class="pw-wrap"><input type="password" inputmode="numeric" class="fi" id="pwNew2" maxlength="8" dir="ltr" style="text-align:center" placeholder="" onkeydown="if(event.key==='Enter'){submitPwSet()}"><button type="button" class="pw-eye" onclick="togglePwVis(this)" aria-label="نمایش گذرواژه"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button></span></div>
  <button class="btn-p" id="pwSetBtn" onclick="submitPwSet()">ثبت گذرواژه و ادامه</button>
</div>

<!-- Sheet: فراموشی گذرواژه -->
<div class="overlay" id="forgotOv" onclick="closeSheet('forgot')"></div>
<div class="sheet" id="forgotSheet">
  <div class="handle"></div>
  <div class="sheet-title">🔑 فراموشی گذرواژه ورود</div>
  <p style="font-size:.85rem;color:var(--text2);text-align:center;line-height:1.9;margin-bottom:14px"><?= nl2br(htmlspecialchars(getSetting($conn, 'forgot_password_message', 'برای بازیابی گذرواژه ورود، لطفاً با مدیر سایت تماس بگیرید.'), ENT_QUOTES, 'UTF-8')) ?></p>
  <div style="display:flex;flex-wrap:wrap;gap:8px;justify-content:center;margin-bottom:14px">
    <?php $fg_ig = trim((string)getSetting($conn, 'contact_instagram', '')); $fg_tg = trim((string)getSetting($conn, 'contact_telegram', '')); $fg_yt = trim((string)getSetting($conn, 'contact_youtube', '')); $fg_ph = trim((string)getSetting($conn, 'contact_phone', '')); ?>
    <?php if ($fg_ig !== ''): ?><a href="<?= htmlspecialchars($fg_ig, ENT_QUOTES, 'UTF-8') ?>" target="_blank" class="c-chip">📸 اینستاگرام</a><?php endif; ?>
    <?php if ($fg_tg !== ''): ?><a href="<?= htmlspecialchars($fg_tg, ENT_QUOTES, 'UTF-8') ?>" target="_blank" class="c-chip">📢 تلگرام</a><?php endif; ?>
    <?php if ($fg_yt !== ''): ?><a href="<?= htmlspecialchars($fg_yt, ENT_QUOTES, 'UTF-8') ?>" target="_blank" class="c-chip">🎥 یوتیوب</a><?php endif; ?>
    <?php if ($fg_ph !== ''): ?><a href="tel:<?= htmlspecialchars($fg_ph, ENT_QUOTES, 'UTF-8') ?>" class="c-chip">📞 <?= htmlspecialchars($fg_ph, ENT_QUOTES, 'UTF-8') ?></a><?php endif; ?>
  </div>
  <?php $fg_addr = trim((string)getSetting($conn, 'contact_address', '')); ?>
  <?php if ($fg_addr !== ''): ?><p style="font-size:.82rem;color:var(--text2);text-align:center;line-height:1.8;margin-bottom:14px">📍 <?= nl2br(htmlspecialchars($fg_addr, ENT_QUOTES, 'UTF-8')) ?></p><?php endif; ?>
  <button class="btn-p" id="pwResetReqBtn" onclick="requestPwReset()" style="margin-top:4px">درخواست بازنشانی گذرواژه</button>
</div>

<!-- Sheet: ثبت‌نام بسته است (ارجاع به مدیر) -->
<div class="overlay" id="regClosedOv" onclick="closeSheet('regClosed')"></div>
<div class="sheet" id="regClosedSheet">
  <div class="handle"></div>
  <div class="sheet-title">🔒 ثبت‌نام بسته است</div>
  <p style="font-size:.85rem;color:var(--text2);text-align:center;line-height:1.9;margin-bottom:14px"><?= nl2br(htmlspecialchars(sitak_register_closed_msg($conn), ENT_QUOTES, 'UTF-8')) ?></p>
  <div style="display:flex;flex-wrap:wrap;gap:8px;justify-content:center;margin-bottom:14px">
    <?php $rc_ig = trim((string)getSetting($conn, 'contact_instagram', '')); $rc_tg = trim((string)getSetting($conn, 'contact_telegram', '')); $rc_yt = trim((string)getSetting($conn, 'contact_youtube', '')); $rc_ph = trim((string)getSetting($conn, 'contact_phone', '')); ?>
    <?php if ($rc_ig !== ''): ?><a href="<?= htmlspecialchars($rc_ig, ENT_QUOTES, 'UTF-8') ?>" target="_blank" class="c-chip">📸 اینستاگرام</a><?php endif; ?>
    <?php if ($rc_tg !== ''): ?><a href="<?= htmlspecialchars($rc_tg, ENT_QUOTES, 'UTF-8') ?>" target="_blank" class="c-chip">📢 تلگرام</a><?php endif; ?>
    <?php if ($rc_yt !== ''): ?><a href="<?= htmlspecialchars($rc_yt, ENT_QUOTES, 'UTF-8') ?>" target="_blank" class="c-chip">🎥 یوتیوب</a><?php endif; ?>
    <?php if ($rc_ph !== ''): ?><a href="tel:<?= htmlspecialchars($rc_ph, ENT_QUOTES, 'UTF-8') ?>" class="c-chip">📞 <?= htmlspecialchars($rc_ph, ENT_QUOTES, 'UTF-8') ?></a><?php endif; ?>
  </div>
  <button class="btn-p" onclick="closeSheet('regClosed')">باشه</button>
</div>

<!-- Sheet: تغییر گذرواژه ورود (کاربر وارد شده) -->
<div class="overlay" id="pwChangeOv" onclick="closeSheet('pwChange')"></div>
<div class="sheet" id="pwChangeSheet">
  <div class="handle"></div>
  <div class="sheet-title">🔑 تغییر گذرواژه ورود</div>
  <div class="fg"><label>گذرواژه فعلی</label><span class="pw-wrap"><input type="password" inputmode="numeric" class="fi" id="pwCur" maxlength="8" dir="ltr" style="text-align:center" placeholder=""><button type="button" class="pw-eye" onclick="togglePwVis(this)" aria-label="نمایش گذرواژه"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button></span></div>
  <div class="fg"><label>گذرواژه جدید</label><span class="pw-wrap"><input type="password" inputmode="numeric" class="fi" id="pwCh1" maxlength="8" dir="ltr" style="text-align:center" placeholder=""><button type="button" class="pw-eye" onclick="togglePwVis(this)" aria-label="نمایش گذرواژه"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button></span></div>
  <div class="fg"><label>تکرار گذرواژه جدید</label><span class="pw-wrap"><input type="password" inputmode="numeric" class="fi" id="pwCh2" maxlength="8" dir="ltr" style="text-align:center" placeholder="" onkeydown="if(event.key==='Enter'){submitPwChange()}"><button type="button" class="pw-eye" onclick="togglePwVis(this)" aria-label="نمایش گذرواژه"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button></span></div>
  <button class="btn-p" id="pwChangeBtn" onclick="submitPwChange()">ذخیره گذرواژه جدید</button>
</div>
<!-- Sheet: Logout Confirm -->
<div class="overlay" id="logoutOv" onclick="closeSheet('logout')"></div>
<div class="sheet" id="logoutSheet">
  <div class="handle"></div>
  <div class="sheet-title">🚪 خروج از حساب</div>
  <p style="font-size:.85rem;color:var(--text2);text-align:center;margin-bottom:18px;line-height:1.8">آیا می‌خواهید از حساب کاربری خود خارج شوید؟</p>
  <div style="display:flex;gap:10px">
    <button class="btn-g" onclick="closeSheet('logout')" style="flex:1;margin-top:0;padding:14px;background:var(--card-el);;border-radius:var(--rss,10px);font-family:var(--font);font-size:.92rem;font-weight:700;color:var(--text2)">انصراف</button>
    <button class="btn-p" onclick="doLogout()" style="flex:1">بله، خارج شوم</button>
  </div>
</div>
<script>
if(typeof loginFrom==='undefined')var loginFrom=null;
/* پیش‌ذخیره سبد مهمان + پرچم ادامه‌ی خرید بعد از ریفرش (فلو checkout از سبد) */
function _stashGuestCart(phone){
  try{
    var items=(typeof cart!=='undefined'&&cart)?cart:[];
    sessionStorage.setItem('_cart_guest_items',JSON.stringify(items));
    sessionStorage.setItem('_cart_guest_phone',phone||'');
    sessionStorage.setItem('_cart_resume_checkout','1');
  }catch(e){}
}
function askLogout(){openSheet('logout')}
function doLogout(){
  /* پاک‌سازی کامل سمت کلاینت: سبد کاربر جاری + سبد مهمان + پرچم‌های فلو */
  try{
    var _ph=(typeof window.USER_PHONE&&window.USER_PHONE)?window.USER_PHONE:((typeof _cuser!=='undefined'&&_cuser)?_cuser:'');
    if(_ph){sessionStorage.removeItem('sitak_cart_'+_ph)}
    sessionStorage.removeItem('sitak_cart__guest');
    sessionStorage.removeItem('_cart_guest_items');
    sessionStorage.removeItem('_cart_guest_phone');
    sessionStorage.removeItem('_cart_resume_checkout');
    fetch('ajax.php?action=logout_user',{credentials:'same-origin'});
  }catch(e){}
  location.href='index.php?logout_user=true';
}
function openSheet(type){document.getElementById(type+'Ov').classList.add('open');document.getElementById(type+'Sheet').classList.add('open')}
function closeSheet(type){document.getElementById(type+'Ov').classList.remove('open');document.getElementById(type+'Sheet').classList.remove('open');if(type==='cart'){var b=document.getElementById('cartMessage');if(b){b.classList.add('hidden');b.classList.add('msg-hide');}}}
function refreshCaptcha(){
  fetch('ajax.php?action=get_captcha')
    .then(function(r){return r.json()})
    .then(function(d){
      if(d.success){
        document.getElementById('captchaQ').textContent=d.question;
        document.getElementById('captchaAnswer').value='';
      }
    });
}
function doRegister(){
  var phone=document.getElementById('regPhone').value.trim();
  var captcha=document.getElementById('captchaAnswer').value;

  if(!phone){showToast('شماره موبایل را وارد کنید');return}
  if(!captcha && captcha!=='0'){showToast('پاسخ سوال امنیتی را وارد کنید');return}

  var fd=new FormData();
  fd.append('phone',phone);
  fd.append('captcha',captcha);
  fd.append('website',document.querySelector('[name=website]')?.value||'');

  fetch('ajax.php?action=register',{method:'POST',body:scsrf(fd)})
    .then(function(r){return r.json()})
    .then(function(data){
      if(data.success){
        if(data.password_setup||data.password_required){
          window._smsLoginPhone=phone;
          closeSheet('reg');
          if(data.password_setup){
            window._pwMode='setup';window._pwDigits=data.digits;
            ['pwNew','pwNew2'].forEach(function(id){var e=document.getElementById(id);e.value='';e.maxLength=data.digits});
            document.getElementById('pwSetHint').textContent='یک گذرواژه '+faN(data.digits)+' رقمی برای ورود بعدی انتخاب کن.';
            setTimeout(function(){openSheet('pwSet');document.getElementById('pwNew').focus()},280);
          }else{
            setTimeout(function(){setOtpPwLoginMode(phone,data.digits);openSheet('otp');document.getElementById('smsOtp').focus()},280);
          }
          return;
        }
        if(data.otp_required){
          window._smsLoginPhone=phone;
          setOtpSmsMode();
          document.getElementById('otpPhone').textContent=phone;
          document.getElementById('otpSandboxCodeValue').textContent='';
          document.getElementById('otpSandboxCode').style.display='none';
          closeSheet('reg');
          setTimeout(function(){openSheet('otp');document.getElementById('smsOtp').focus();startResendCooldown(60)},280);
          showToast(data.message); return;
        }
        finishLogin(data);
      }else{
        /* ثبت‌نام بسته است: پاپ‌آپ «تماس با مدیر» باز می‌شود */
        if(data.register_closed){
          refreshCaptcha();
          closeSheet('reg');
          setTimeout(function(){openSheet('regClosed')},280);
          return;
        }
        showToast(data.message);
        refreshCaptcha();
      }
    }).catch(function(){
      showToast('خطا در ارتباط با سرور');
      refreshCaptcha();
    });
}
function editOtpPhone(){
  document.getElementById('editOtpPhone').blur();
  var phone=window._smsLoginPhone||document.getElementById('regPhone').value.trim();
  clearInterval(resendTimer);
  closeSheet('otp');
  document.getElementById('regPhone').value=phone;
  document.getElementById('smsOtp').value='';
  document.getElementById('resendOtpBtn').disabled=true;
  document.getElementById('resendOtpTimer').textContent='(۶۰)';
  setTimeout(function(){openSheet('reg');refreshCaptcha();document.getElementById('regPhone').focus();},280);
}
var OTP_DIGITS=<?= (int)$otpDigits ?>;
var OTP_DIGITS_FA='<?= pn($otpDigits) ?>';
function faDigit(s){return String(s).replace(/[۰-۹]/g,function(d){return '0123456789'['۰۱۲۳۴۵۶۷۸۹'.indexOf(d)]}).replace(/[٠-٩]/g,function(d){return '0123456789'['٠١٢٣٤٥٦٧٨٩'.indexOf(d)]})}
function faN(n){return String(n).replace(/[0-9]/g,function(x){return '۰۱۲۳۴۵۶۷۸۹'[x]})}
function pwOk(v,d){return new RegExp('^[0-9]{'+d+'}$').test(v)}
var PW_DIGITS=<?= (int)sitak_pw_digits($conn) ?>;
function setOtpSmsMode(){
  window._pwMode='sms';
  document.getElementById('otpTitle').textContent='🔐 تأیید شماره موبایل';
  document.getElementById('otpInstrText').textContent='کد تأیید ارسال‌شده به شماره‌ی زیر را وارد کن:';
  document.getElementById('otpLabel').textContent='کد تأیید پیامکی';
  var i=document.getElementById('smsOtp');i.type='text';i.value='';i.maxLength=OTP_DIGITS;i.placeholder='کد '+OTP_DIGITS_FA+' رقمی';
  document.getElementById('otpValidity').style.display='';
  document.getElementById('resendOtpBtn').style.display='';
  document.getElementById('otpForgotBtn').style.display='none';
  document.getElementById('otpSubmitBtn').textContent='تأیید و ورود';
}
function setOtpPwLoginMode(phone,digits){
  window._pwMode='login';window._pwDigits=digits;window._smsLoginPhone=phone;
  document.getElementById('otpTitle').textContent='🔐 ورود با گذرواژه';
  document.getElementById('otpInstrText').textContent='گذرواژه ورود خود را وارد کن:';
  document.getElementById('otpLabel').textContent='گذرواژه ورود';
  var i=document.getElementById('smsOtp');i.type='password';i.value='';i.maxLength=digits;i.placeholder='گذرواژه '+faN(digits)+' رقمی';
  document.getElementById('otpValidity').style.display='none';
  document.getElementById('resendOtpBtn').style.display='none';
  document.getElementById('otpForgotBtn').style.display='';
  document.getElementById('otpPhone').textContent=phone;
  document.getElementById('otpSandboxCode').style.display='none';
  document.getElementById('otpSubmitBtn').textContent='ورود';
}
var PW_EYE_ON='<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>',PW_EYE_OFF='<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';function togglePwVis(btn){var i=btn.parentNode.querySelector('input');var show=i.type==='password';i.type=show?'text':'password';btn.innerHTML=show?PW_EYE_OFF:PW_EYE_ON;btn.setAttribute('aria-label',show?'پنهان کردن گذرواژه':'نمایش گذرواژه')}
function requestPwReset(){
  var phone=window._smsLoginPhone||'';
  if(!phone){showToast('ابتدا شماره‌ی موبایل را وارد کن');return}
  var btn=document.getElementById('pwResetReqBtn');btn.disabled=true;
  var fd=new FormData();fd.append('phone',phone);
  fetch('ajax.php?action=password_reset_request',{method:'POST',body:scsrf(fd)}).then(function(r){return r.json()}).then(function(data){
    btn.disabled=false;showToast(data.message);
  }).catch(function(){btn.disabled=false;showToast('خطا در ارتباط با سرور')});
}
function openForgot(){closeSheet('otp');setTimeout(function(){openSheet('forgot')},280)}
function verifyPwLogin(){
  var phone=window._smsLoginPhone||'';var d=window._pwDigits||PW_DIGITS;
  var pw=faDigit(document.getElementById('smsOtp').value.trim());
  if(!pwOk(pw,d)){showToast('گذرواژه '+faN(d)+' رقمی را وارد کنید');return}
  var btn=document.getElementById('otpSubmitBtn');btn.disabled=true;btn.textContent='در حال بررسی...';
  var fd=new FormData();fd.append('phone',phone);fd.append('password',pw);
  fetch('ajax.php?action=password_login',{method:'POST',body:scsrf(fd)}).then(function(r){return r.json()}).then(function(data){
    btn.disabled=false;btn.textContent='ورود';
    if(!data.success){showToast(data.message);document.getElementById('smsOtp').value='';return}
    closeSheet('otp');finishLogin(data);
  }).catch(function(){btn.disabled=false;btn.textContent='ورود';showToast('خطا در ارتباط با سرور')});
}
function submitPwSet(){
  var phone=window._smsLoginPhone||'';var d=window._pwDigits||PW_DIGITS;
  var a=faDigit(document.getElementById('pwNew').value.trim()), b=faDigit(document.getElementById('pwNew2').value.trim());
  if(!pwOk(a,d)){showToast('گذرواژه باید '+faN(d)+' رقمی باشد');return}
  if(a!==b){showToast('دو گذرواژه وارد شده یکسان نیستند');return}
  var btn=document.getElementById('pwSetBtn');btn.disabled=true;
  var fd=new FormData();fd.append('phone',phone);fd.append('password',a);fd.append('password2',b);
  fetch('ajax.php?action=set_password',{method:'POST',body:scsrf(fd)}).then(function(r){return r.json()}).then(function(data){
    btn.disabled=false;
    if(!data.success){showToast(data.message);return}
    closeSheet('pwSet');finishLogin(data);
  }).catch(function(){btn.disabled=false;showToast('خطا در ارتباط با سرور')});
}
function submitPwChange(){
  var d=PW_DIGITS;
  var cur=faDigit(document.getElementById('pwCur').value.trim()), a=faDigit(document.getElementById('pwCh1').value.trim()), b=faDigit(document.getElementById('pwCh2').value.trim());
  if(!pwOk(cur,d)){showToast('گذرواژه فعلی را '+faN(d)+' رقمی وارد کنید');return}
  if(!pwOk(a,d)){showToast('گذرواژه جدید باید '+faN(d)+' رقمی باشد');return}
  if(a!==b){showToast('دو گذرواژه جدید یکسان نیستند');return}
  var btn=document.getElementById('pwChangeBtn');btn.disabled=true;
  var fd=new FormData();fd.append('current',cur);fd.append('password',a);fd.append('password2',b);
  fetch('ajax.php?action=change_password',{method:'POST',body:scsrf(fd)}).then(function(r){return r.json()}).then(function(data){
    btn.disabled=false;showToast(data.message);
    if(data.success){['pwCur','pwCh1','pwCh2'].forEach(function(id){document.getElementById(id).value=''});closeSheet('pwChange')}
  }).catch(function(){btn.disabled=false;showToast('خطا در ارتباط با سرور')});
}
function verifySmsOtp(){
  if(window._pwMode==='login'){return verifyPwLogin()}
  var phone=window._smsLoginPhone||document.getElementById('regPhone').value.trim();
  var otp=document.getElementById('smsOtp').value.trim().replace(/[۰-۹]/g,function(d){return '0123456789'['۰۱۲۳۴۵۶۷۸۹'.indexOf(d)]});
  if(!new RegExp('^[0-9۰-۹]{'+OTP_DIGITS+'}$').test(otp)){showToast('کد '+OTP_DIGITS_FA+' رقمی را وارد کنید');return}
  var btn=document.getElementById('otpSubmitBtn');btn.disabled=true;btn.textContent='در حال بررسی...';
  var vf=new FormData();vf.append('phone',phone);vf.append('otp',otp);
  fetch('ajax.php?action=verify_sms_login',{method:'POST',body:scsrf(vf)}).then(function(r){return r.json()}).then(function(data){
    btn.disabled=false;btn.textContent='تأیید و ورود';
    if(!data.success){
      /* ثبت‌نام بسته است: پاپ‌آپ «تماس با مدیر» باز می‌شود */
      if(data.register_closed){closeSheet('otp');setTimeout(function(){openSheet('regClosed')},280);return}
      showToast(data.message);return;
    }
    closeSheet('otp');finishLogin(data);
  }).catch(function(){btn.disabled=false;btn.textContent='تأیید و ورود';showToast('خطا در ارتباط با سرور')});
}
var resendTimer=null;
function startResendCooldown(seconds){
  var btn=document.getElementById('resendOtpBtn'), timer=document.getElementById('resendOtpTimer');
  clearInterval(resendTimer); seconds=Math.max(0,parseInt(seconds||0,10));
  btn.disabled=seconds>0; timer.textContent=seconds>0?' ('+seconds+')':'';
  resendTimer=setInterval(function(){seconds--;btn.disabled=seconds>0;timer.textContent=seconds>0?' ('+seconds+')':'';if(seconds<=0)clearInterval(resendTimer)},1000);
}
function resendSmsOtp(){
  var phone=window._smsLoginPhone||'';
  var fd=new FormData();fd.append('phone',phone);
  document.getElementById('resendOtpBtn').disabled=true;
  fetch('ajax.php?action=resend_sms_login',{method:'POST',body:scsrf(fd)}).then(function(r){return r.json()}).then(function(data){
    if(data.success){
      showToast(data.message);startResendCooldown(data.cooldown||60);
    }else{showToast(data.message);startResendCooldown(data.cooldown||180)}
  }).catch(function(){showToast('خطا در ارتباط با سرور');startResendCooldown(180)});
}
function finishLogin(data){
      var phone=document.getElementById('regPhone').value.trim();
        /* پاسخ fetch هم‌زمان با Set-Cookie سرور می‌رسد؛ برای اینکه UI همین لحظه
           حساب را لاگین‌شده بداند، کوکی را هم سمت کاربر ثبت می‌کنیم. */
        /* هویت کاربر در نشست سرور است */
        USER_PHONE=phone;
        _cuser=phone;
        if(data.needs_referral){
          /* کاربر جدید — پاپآپ معرف (در فلو سبد هم باید دیده شود؛ loginFrom می‌ماند) */
          closeSheet('reg');
          setTimeout(function(){openSheet('ref')},350);
        }else if(typeof loginFrom!=='undefined' && loginFrom==='cart'){
          // کاربر قدیمی از checkout لاگین شد → سبد را پیش‌ذخیره و ریفرش تا لاگین کامل ثبت شود
          _stashGuestCart(phone);
          closeSheet('reg');
          showToast('ورود موفق ✅');
          setTimeout(function(){location.reload()},700);
        }else{
          showToast('خوش آمدید!');
          setTimeout(function(){location.reload();}, 900);
        }
}
function submitReferral(){
  var referral=document.getElementById('refInput').value.trim();
  if(!referral){showToast('شماره موبایل معرف را وارد کنید یا «معرف ندارم» را بزنید');return}
  var fd=new FormData();
  fd.append('referral',referral);
  fd.append('phone',USER_PHONE||document.getElementById('regPhone').value.trim());
  fetch('ajax.php?action=submit_referral',{method:'POST',body:scsrf(fd)})
    .then(function(r){return r.json()})
    .then(function(data){
      if(data.success){
        closeSheet('ref');
        /* هویت کاربر در نشست سرور است */
        if(typeof loginFrom!=='undefined' && loginFrom==='cart'){
          showToast('معرف ثبت شد — یک شانس اضافه گرفتید! 🎉');
          _stashGuestCart(USER_PHONE||document.getElementById('regPhone').value.trim());
          loginFrom=null;
          setTimeout(function(){location.reload()},900);
        }else{
          showToast('معرف ثبت شد — یک شانس اضافه گرفتید! 🎉');
          setTimeout(function(){location.reload();}, 900);
        }
      }else{
        showToast(data.message);
      }
    }).catch(function(){showToast('خطا در ارتباط با سرور')});
}
function skipReferral(){
  closeSheet('ref');
  var loggedPhone=USER_PHONE||document.getElementById('regPhone').value.trim();
  /* هویت کاربر در نشست سرور است */
  if(typeof loginFrom!=='undefined' && loginFrom==='cart'){
    _stashGuestCart(loggedPhone);
    loginFrom=null;
    showToast('خوش آمدید! 🎉');
    setTimeout(function(){location.reload()},700);
    return;
  }
  loginFrom=null;
  showToast('خوش آمدید! 🎉');
  setTimeout(function(){location.reload();}, 700);
}
function showToast(m){var t=document.getElementById('toast');if(!t)return;t.textContent=m;t.classList.add('show');clearTimeout(t._timer);t._timer=setTimeout(function(){t.classList.remove('show')},3000)}
</script>
