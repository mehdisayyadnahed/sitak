<?php
$admin_self = $admin_self ?? 'admin-settings.php';
require 'admin_common.php';

if (!$is_authenticated) { admin_login_screen($login_error ?? null); exit; }
admin_page_start('تنظیمات');
admin_header();
?>
<div class="container">

  <!-- ۱. تنظیمات اسم و شعار سایت -->
  <div class="acc-hdr" onclick="toggleAcc(this)"><h3>۱. اسم و شعار سایت</h3><span class="acc-icon">+</span></div>
  <div class="acc-body">
    <form method="post">
      <div class="fg"><label>اسم سایت (برند) — بدون ایموجی</label><input type="text" name="site_name" value="<?= htmlspecialchars($site_name ?? 'سای‌تک', ENT_QUOTES, 'UTF-8') ?>" required></div>
      <div class="fg"><label>شعار سایت (زیرعنوان)</label><input type="text" name="site_slogan" value="<?= htmlspecialchars($site_slogan ?? 'یک وبسایت تک', ENT_QUOTES, 'UTF-8') ?>" required></div>
      <div class="fg"><button type="submit" name="save_hero_settings" class="btn-p">ذخیره اسم و شعار</button></div>
    </form>
  </div>

  <!-- ۲. لوگو و فاوآیکون -->
  <div class="acc-hdr" onclick="toggleAcc(this)"><h3>۲. لوگو، فاوآیکون و ایموجی سفارشی</h3><span class="acc-icon">+</span></div>
  <div class="acc-body">
    <form method="post" enctype="multipart/form-data" data-full-reload>
        <div class="fg">
          <label>تصویر لوگو در هدر (Logo)</label>
          <input type="file" name="logo" accept=".png,.jpg,.jpeg,.gif,.svg,.webp" style="padding: 7px;">
          <?php if ($custom_logo): ?>
            <div style="display:flex;align-items:center;gap:8px;margin-top:8px">
              <img src="<?= htmlspecialchars($custom_logo) ?>" style="width:32px;height:32px;object-fit:contain;background:#ccc;padding:2px;border-radius:4px">
              <button type="submit" name="delete_logo" class="btn-sm btn-red" style="font-size:.65rem">حذف 🗑️</button>
            </div>
          <?php endif; ?>
      </div>
      <div class="fg">
          <label>تصویر فاوآیکون (Favicon)</label>
          <input type="file" name="favicon" accept=".ico,.png,.jpg,.jpeg,.gif,.svg,.webp" style="padding: 7px;">
          <?php if ($custom_favicon): ?>
            <div style="display:flex;align-items:center;gap:8px;margin-top:8px">
              <img src="<?= htmlspecialchars($custom_favicon) ?>" style="width:32px;height:32px;object-fit:contain;background:#ccc;padding:2px;border-radius:4px">
              <button type="submit" name="delete_favicon" class="btn-sm btn-red" style="font-size:.65rem">حذف 🗑️</button>
            </div>
          <?php endif; ?>
        </div>
      <hr style="border:0;border-top:1px dashed var(--border);margin:16px 0">
      <div class="fg"><label>ایموجی سفارشی</label><input type="text" name="custom_emoji" value="<?= htmlspecialchars($custom_emoji ?? '🌐', ENT_QUOTES, 'UTF-8') ?>" maxlength="8" placeholder="مثلاً: 🧁" dir="ltr" style="font-size:1.5rem;text-align:center!important;direction:ltr;unicode-bidi:plaintext;width:100%;box-sizing:border-box"></div>
      <p style="font-size:.72rem;color:var(--text3);line-height:1.6;margin-bottom:10px">این ایموجی در جایگزین تصویر محصول (منو و سبد خرید)، کارت برندگان و حالت خالی منو نمایش داده می‌شود.</p>
      <div class="toggle-row" style="margin-bottom:12px">
        <label class="toggle"><input type="checkbox" name="show_header_emoji" value="1" <?= getSetting($conn, 'show_header_emoji', '1') === '1' ? 'checked' : '' ?>><span class="slider"></span></label>
        <span class="toggle-label">ایموجی کنار اسم سایت در هدر نمایش داده شود</span>
      </div>
      <div class="fg"><button type="submit" name="upload_assets" class="btn-p" style="margin-top:10px">ذخیره‌ی تنظیمات هویت بصری</button></div>
    </form>
  </div>

  <!-- ۳. تنظیمات متن بنر هدر -->
  <div class="acc-hdr" onclick="toggleAcc(this)"><h3>۳. تنظیمات متن بنر هدر صفحه‌ی اصلی</h3><span class="acc-icon">+</span></div>
  <div class="acc-body">
    <form method="post">
      <div class="toggle-row">
        <label class="toggle"><input type="checkbox" name="hero_enabled" value="1" <?= ($hero_enabled??'1')==='1'?'checked':'' ?>><span class="slider"></span></label>
        <span class="toggle-label">نمایش بنر هدر فعال باشد</span>
      </div>
      <div class="fg"><label>عنوان</label><input type="text" name="hero_title" value="<?= htmlspecialchars($hero_title??'', ENT_QUOTES, 'UTF-8') ?>"></div>
      <div class="fg"><label>توضیحات</label><textarea name="hero_description" rows="3"><?= htmlspecialchars($hero_description??'', ENT_QUOTES, 'UTF-8') ?></textarea></div>
      <input type="hidden" name="site_name" value="<?= htmlspecialchars($site_name ?? 'سای‌تک', ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="site_slogan" value="<?= htmlspecialchars($site_slogan ?? 'یک وبسایت تک', ENT_QUOTES, 'UTF-8') ?>">
      <div class="fg"><button type="submit" name="save_hero_settings" class="btn-p">ذخیره بنر</button></div>
    </form>
  </div>

  <!-- ۴. راه‌های ارتباطی -->
  <div class="acc-hdr" onclick="toggleAcc(this)"><h3>۴. راه‌های ارتباطی و کپی‌رایت در صفحه‌ی اصلی</h3><span class="acc-icon">+</span></div>
  <div class="acc-body">
    <form method="post">
      <div class="toggle-row">
        <label class="toggle"><input type="checkbox" name="contact_enabled" value="1" <?= $contact_enabled==='1'?'checked':'' ?>><span class="slider"></span></label>
        <span class="toggle-label">نمایش بخش ارتباط با ما در ابتدای صفحه اصلی</span>
      </div>
      <div class="fg"><label>اینستاگرام (لینک کامل)</label><input type="url" name="contact_instagram" value="<?= htmlspecialchars($contact_instagram, ENT_QUOTES, 'UTF-8') ?>" placeholder="https://instagram.com/..."></div>
        <div class="fg"><label>تلگرام (لینک کامل)</label><input type="url" name="contact_telegram" value="<?= htmlspecialchars($contact_telegram, ENT_QUOTES, 'UTF-8') ?>" placeholder="https://t.me/..."></div>
        <div class="fg"><label>یوتیوب (لینک کامل)</label><input type="url" name="contact_youtube" value="<?= htmlspecialchars($contact_youtube, ENT_QUOTES, 'UTF-8') ?>" placeholder="https://youtube.com/..."></div>
        <div class="fg"><label>شماره تماس</label><input type="tel" name="contact_phone" value="<?= htmlspecialchars($contact_phone, ENT_QUOTES, 'UTF-8') ?>" placeholder="09123456789"></div>
      <div class="fg"><label>نشانی یا متن توضیحی</label><input type="text" name="contact_address" value="<?= htmlspecialchars($contact_address, ENT_QUOTES, 'UTF-8') ?>" placeholder="مثلاً: خیابان ولیعصر، فروشگاه‌شن"></div>
      <hr style="border:0;border-top:1px dashed var(--border);margin:16px 0">
      <div style="font-size:.85rem;font-weight:700;color:var(--gold-dark);margin-bottom:10px">کپی‌رایت فوتر صفحه‌ی اصلی</div>
      <div class="toggle-row">
        <label class="toggle"><input type="checkbox" name="footer_copyright_enabled" value="1" <?= getSetting($conn, 'footer_copyright_enabled', '1')==='1'?'checked':'' ?>><span class="slider"></span></label>
        <span class="toggle-label">نمایش کپی‌رایت در فوتر فعال باشد</span>
      </div>
      <div class="fg"><label>متن کپی‌رایت (زیر آدرس‌ها در فوتر نمایش داده می‌شود)</label><input type="text" name="footer_copyright" value="<?= htmlspecialchars(getSetting($conn, 'footer_copyright', ''), ENT_QUOTES, 'UTF-8') ?>"></div>
      <button type="submit" name="save_contact_settings" class="btn-p">ذخیره تنظیمات ارتباطی و کپی‌رایت</button>
    </form>
  </div>

  <!-- ۴. ترتیب کارت‌های خانه -->
  <div class="acc-hdr" onclick="toggleAcc(this)"><h3>۵. ترتیب نمایش کارت‌های صفحه‌ی اصلی</h3><span class="acc-icon">+</span></div>
  <div class="acc-body">
    <p style="font-size:.74rem;color:var(--text3);margin-bottom:10px">عدد کوچک‌تر، بالاتر. نمایش یا عدم نمایش هر کارت جدا از همین تنظیمات است.</p>
    <form method="post" onsubmit="return validateCardOrderForm(this)">
      <input type="hidden" name="card_scope" value="home">
<?php foreach (sitak_card_keys('home') as $ck => $cl): $cv = getSetting($conn, 'card_order_home_' . $ck, (string)(array_search($ck, array_keys(sitak_card_keys('home'))) + 1)); $cid = 'ord_home_' . $ck; ?>
      <div class="fg"><label><?= htmlspecialchars($cl, ENT_QUOTES, 'UTF-8') ?></label><div class="stat-row"><button type="button" class="btn-h minus" onclick="stepNum('<?= $cid ?>',-1)">−</button><input type="text" inputmode="numeric" class="stat-input fa-number" id="<?= $cid ?>" name="order_<?= $ck ?>" min="1" max="99" value="<?= htmlspecialchars((string)$cv, ENT_QUOTES, 'UTF-8') ?>"><button type="button" class="btn-h plus" onclick="stepNum('<?= $cid ?>',1)">+</button></div></div>
    <?php endforeach; ?>
      <div class="fg"><button type="submit" name="save_card_order" value="1" class="btn-p">ذخیره ترتیب کارت‌ها</button></div>
    </form>
  </div>

  <!-- ۵. تم پیش‌فرض -->
  <div class="acc-hdr" onclick="toggleAcc(this)"><h3>۶. تنظیمات تم پیش‌فرض</h3><span class="acc-icon">+</span></div>
  <div class="acc-body">
    <form method="post">
      <div class="fg"><label>تم پیش‌فرض سایت (برای کاربران جدید)</label>
          <select name="default_theme">
            <option value="auto" <?= $default_theme==='auto'?'selected':'' ?>>🖥️ خودکار (تم سیستم)</option>
            <option value="light" <?= $default_theme==='light'?'selected':'' ?>>☀️ روشن</option>
            <option value="dark" <?= $default_theme==='dark'?'selected':'' ?>>🌙 تاریک</option>
          </select>
      </div>
      <div class="fg"><button type="submit" name="save_theme_settings" class="btn-p">ذخیره</button></div>
      <p style="font-size:.72rem;color:var(--text3);line-height:1.6;margin-top:4px">در حالت «خودکار»، تم سایت با تنظیمات مرورگر یا سیستم‌عامل کاربر هماهنگ می‌شود. کاربرانی که قبلاً تم خود را انتخاب کرده‌اند، اولویت با انتخاب شخصی آن‌هاست. این تنظیم فقط برای بازدیدکنندگان جدید اعمال می‌شود.</p>
    </form>
  </div>

    <!-- ۸. پنل پیامکی -->
  <div class="acc-hdr" onclick="toggleAcc(this)"><h3>۷. ثبت‌نام، ورود و پنل پیامکی</h3><span class="acc-icon">+</span></div>
  <div class="acc-body">
    <?php
      $sms_enabled = getSetting($conn, 'sms_enabled', '0');
      $sms_api_key = getSetting($conn, 'sms_api_key', '');
      $sms_line_number = getSetting($conn, 'sms_line_number', '');
      $sms_otp_template_id = getSetting($conn, 'sms_otp_template_id', '');
      $sms_order_template_id = getSetting($conn, 'sms_order_template_id', '');
      $sms_status_confirmed_template_id = getSetting($conn, 'sms_status_confirmed_template_id', '');
      $sms_status_delivered_template_id = getSetting($conn, 'sms_status_delivered_template_id', '');
      $sms_status_cancelled_template_id = getSetting($conn, 'sms_status_cancelled_template_id', '');
      $sms_resend_limit_message = getSetting($conn, 'sms_resend_limit_message', 'در حال حاضر، امکان درخواست دوباره کد وجود ندارد، لطفاً مدتی دیگر تلاش کنید.');
    ?>
    <form method="post">
      <div class="toggle-row">
        <label class="toggle"><input type="checkbox" name="sms_enabled" value="1" <?= $sms_enabled==='1'?'checked':'' ?>><span class="slider"></span></label>
        <span class="toggle-label">فعال‌سازی پنل پیامکی SMS.ir</span>
      </div>
      <p style="font-size:.72rem;color:var(--text3);line-height:1.6;margin-bottom:14px">این اتصال فقط از API سرویس SMS.ir استفاده می‌کند و برای ورود، سفارش و تغییر وضعیت سفارش پیامک می‌فرستد.</p>
      <div class="toggle-row">
        <label class="toggle"><input type="checkbox" name="sms_sandbox" value="1" <?= getSetting($conn,'sms_sandbox','1')==='1'?'checked':'' ?>><span class="slider"></span></label>
        <span class="toggle-label">حالت آزمایشی (Sandbox)</span>
      </div>
      <p style="font-size:.72rem;color:var(--text3);line-height:1.6;margin-bottom:14px">در حالت آزمایشی، درخواست واقعاً به API تستی SMS.ir ارسال می‌شود اما پیامک واقعی فرستاده و اعتباری کسر نمی‌شود. برای این حالت باید API Key نوع Sandbox را از پنل SMS.ir وارد کنی. گذرواژه پیش‌فرض این حالت ۱۲۳۴۵۶ است.</p>
      <div class="fg"><label>کلید API وب‌سرویس SMS.ir</label><input type="text" name="sms_api_key" value="<?= htmlspecialchars($sms_api_key, ENT_QUOTES, 'UTF-8') ?>" dir="ltr" style="text-align:left" autocomplete="new-password"><span style="display:block;font-size:.72rem;color:var(--text3);line-height:1.6;margin-top:4px">از بخش برنامه‌نویسان پنل SMS.ir دریافت می‌شود.</span></div>
      <div class="fg"><label>شماره خط ارسال (اختیاری)</label><input type="text" name="sms_line_number" value="<?= htmlspecialchars($sms_line_number, ENT_QUOTES, 'UTF-8') ?>" dir="ltr" style="text-align:left" placeholder="در این نسخه، ارسال قالبی از خط خدماتی قالب استفاده می‌کند."></div>
      <hr style="border:0;border-top:1px solid var(--border);margin:18px 0">
      <div class="toggle-row" style="margin-top:6px"><label class="toggle"><input type="checkbox" name="sms_password_login_enabled" value="1" <?= getSetting($conn,'sms_password_login_enabled','0')==='1'?'checked':'' ?>><span class="slider"></span></label><span class="toggle-label">ورود و ثبت‌نام با گذرواژه اختصاصی (به جای کد پیامکی)</span></div>
      <p style="font-size:.72rem;color:var(--text3);line-height:1.6;margin:4px 0 12px">با این حالت، شماره‌ی تازه با API سامانه‌ی SMS.ir بررسی می‌شود. کاربر بعدش به‌جای کد پیامکی یک گذرواژه برای خودش تعیین می‌کند و در ورودهای بعدی همان گذرواژه را می‌زند. این حالت با Sandbox هم کار می‌کند.</p>
      <div class="fg">
        <label>تعداد رقم‌های گذرواژه ورود</label>
        <select name="sms_password_digits">
          <?php $pw_digits_saved = (int)getSetting($conn, 'sms_password_digits', '6'); foreach ([4,5,6,7,8] as $pdv): ?>
          <option value="<?= $pdv ?>" <?= $pw_digits_saved===$pdv?'selected':'' ?>><?= pn($pdv) ?> رقمی</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="toggle-row" style="margin-top:14px"><label class="toggle"><input type="checkbox" name="register_closed" value="1" <?= getSetting($conn,'register_closed','0')==='1'?'checked':'' ?>><span class="slider"></span></label><span class="toggle-label">بستن ثبت‌نام (فقط مدیر کاربر جدید بسازد)</span></div>
      <p style="font-size:.72rem;color:var(--text3);line-height:1.6;margin:4px 0 12px">با روشن بودن این سوئیچ، ساخت حساب جدید از سمت کاربر بسته می‌شود؛ شماره‌ی تازه به‌جای ورود به مرحله‌ی تعیین گذرواژه، پاپ‌آپ «ثبت‌نام بسته است» را می‌بیند و فقط مدیر می‌تواند از بخش «ساخت کاربر جدید» در صفحه‌ی خانه‌ی پنل، کاربر بسازد و رمز ورود را به او بدهد. ورود کاربران قبلی تغییری نمی‌کند.</p>
      <div class="fg">
        <label>متن پیام «ثبت‌نام بسته است»</label>
        <textarea name="register_closed_message" rows="3" maxlength="500"><?= htmlspecialchars(getSetting($conn, 'register_closed_message', sitak_register_closed_msg_default()), ENT_QUOTES, 'UTF-8') ?></textarea>
        <span style="display:block;font-size:.72rem;color:var(--text3);line-height:1.6;margin-top:4px">این متن در پاپ‌آپی نمایش داده می‌شود که کاربرِ بدون حساب، هنگام تلاش برای ثبت‌نام می‌بیند. اطلاعات تماس مدیر (تلفن، تلگرام، اینستاگرام) از بخش تماس سایت خوانده و زیر این متن نمایش داده می‌شود.</span>
      </div>
      <div class="fg">
        <label>متن پیام «فراموشی گذرواژه»</label>
        <textarea name="forgot_password_message" rows="3" maxlength="500"><?= htmlspecialchars(getSetting($conn, 'forgot_password_message', 'برای بازیابی گذرواژه ورود، لطفاً با مدیر سایت تماس بگیرید.'), ENT_QUOTES, 'UTF-8') ?></textarea>
        <span style="display:block;font-size:.72rem;color:var(--text3);line-height:1.6;margin-top:4px">اطلاعات تماس مدیر (تلفن، تلگرام، اینستاگرام) از بخش تماس سایت خوانده و زیر این متن نمایش داده می‌شود.</span>
      </div>
      <div class="fg">
        <label>متن پیام پس از ثبت درخواست بازیابی گذرواژه</label>
        <textarea name="password_reset_success_message" rows="2" maxlength="255"><?= htmlspecialchars(getSetting($conn, 'password_reset_success_message', 'درخواست بازیابی گذرواژه شما ثبت شد. مدیر سایت به‌زودی به شما اطلاع می‌دهد.'), ENT_QUOTES, 'UTF-8') ?></textarea>
        <span style="display:block;font-size:.72rem;color:var(--text3);line-height:1.6;margin-top:4px">این متن بعد از زدن دکمه‌ی «درخواست بازیابی گذرواژه» در پاپ‌آپ فراموشی گذرواژه نمایش داده می‌شود.</span>
      </div>
      <hr style="border:0;border-top:1px solid var(--border);margin:18px 0">
      <div class="toggle-row"><label class="toggle"><input type="checkbox" name="sms_notify_login" value="1" <?= getSetting($conn,'sms_notify_login','1')==='1'?'checked':'' ?>><span class="slider"></span></label><span class="toggle-label">کد تأیید ورود پیامکی فعال باشد</span></div>
      <?php $sms_otp_digits = (int)getSetting($conn, 'sms_otp_digits', '6'); ?>
      <div class="fg">
        <label>تعداد رقم‌های کد تأیید ورود</label>
        <select name="sms_otp_digits">
          <?php foreach ([4=>'۴ رقمی',5=>'۵ رقمی',6=>'۶ رقمی'] as $dv=>$dl): ?>
          <option value="<?= $dv ?>" <?= $sms_otp_digits===$dv?'selected':'' ?>><?= $dl ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <p style="font-size:.72rem;color:var(--text3);line-height:1.6;margin:4px 0 12px">برای هر رویداد، یک قالب ارسال سریع در SMS.ir بساز و شناسه قالب را اینجا وارد کن. نام پارامترهای قالب باید مطابق راهنمای زیر باشد.</p>
      <div class="fg"><label>شناسه قالب کد ورود، پارامتر: <code>Code</code></label><div class="stat-row"><button type="button" class="btn-h minus" onclick="stepNum('smsOtpTemplateId',-1)">−</button><input type="text" class="stat-input fa-number" name="sms_otp_template_id" id="smsOtpTemplateId" value="<?= htmlspecialchars($sms_otp_template_id, ENT_QUOTES, 'UTF-8') ?>" min="1" inputmode="numeric"><button type="button" class="btn-h plus" onclick="stepNum('smsOtpTemplateId',1)">+</button></div></div>
      <p style="font-size:.68rem;color:var(--text3);line-height:1.6;margin:-4px 0 12px">اگر Sandbox روشن باشد و این فیلد خالی بماند، قالب پیش‌فرض Verify با شناسه ۱۲۳۴۵۶ استفاده می‌شود.</p>
      <div class="fg">
        <label>متن پیام محدودیت درخواست مجدد کد</label>
        <textarea name="sms_resend_limit_message" rows="3" maxlength="255"><?= htmlspecialchars($sms_resend_limit_message, ENT_QUOTES, 'UTF-8') ?></textarea>
        <span style="display:block;font-size:.72rem;color:var(--text3);line-height:1.6;margin-top:4px">این متن در Toast به کاربری نمایش داده می‌شود که سقف ارسال دوباره کد را رد کرده باشد.</span>
      </div>
      <hr style="border:0;border-top:1px solid var(--border);margin:18px 0">
      <div class="toggle-row"><label class="toggle"><input type="checkbox" name="sms_notify_order" value="1" <?= getSetting($conn,'sms_notify_order','1')==='1'?'checked':'' ?>><span class="slider"></span></label><span class="toggle-label">پیامک ثبت سفارش فعال باشد</span></div>
      <div class="fg"><label>شناسه قالب ثبت سفارش، پارامترها: <code>OrderId</code> و <code>Total</code></label><div class="stat-row"><button type="button" class="btn-h minus" onclick="stepNum('smsOrderTemplateId',-1)">−</button><input type="text" class="stat-input fa-number" name="sms_order_template_id" id="smsOrderTemplateId" value="<?= htmlspecialchars($sms_order_template_id, ENT_QUOTES, 'UTF-8') ?>" min="1" inputmode="numeric"><button type="button" class="btn-h plus" onclick="stepNum('smsOrderTemplateId',1)">+</button></div></div>
      <div class="toggle-row" style="margin-bottom:14px"><label class="toggle"><input type="checkbox" name="sms_notify_status" value="1" <?= getSetting($conn,'sms_notify_status','1')==='1'?'checked':'' ?>><span class="slider"></span></label><span class="toggle-label">پیامک تغییر وضعیت سفارش فعال باشد</span></div>
      <div class="fg"><label>شناسه قالب «در حال آماده‌سازی»، پارامترها: <code>OrderId</code> و <code>Total</code></label><div class="stat-row"><button type="button" class="btn-h minus" onclick="stepNum('smsConfirmedTemplateId',-1)">−</button><input type="text" class="stat-input fa-number" name="sms_status_confirmed_template_id" id="smsConfirmedTemplateId" value="<?= htmlspecialchars($sms_status_confirmed_template_id, ENT_QUOTES, 'UTF-8') ?>" min="1" inputmode="numeric"><button type="button" class="btn-h plus" onclick="stepNum('smsConfirmedTemplateId',1)">+</button></div></div>
      <div class="fg"><label>شناسه قالب «تحویل شده»، پارامترها: <code>OrderId</code> و <code>Total</code></label><div class="stat-row"><button type="button" class="btn-h minus" onclick="stepNum('smsDeliveredTemplateId',-1)">−</button><input type="text" class="stat-input fa-number" name="sms_status_delivered_template_id" id="smsDeliveredTemplateId" value="<?= htmlspecialchars($sms_status_delivered_template_id, ENT_QUOTES, 'UTF-8') ?>" min="1" inputmode="numeric"><button type="button" class="btn-h plus" onclick="stepNum('smsDeliveredTemplateId',1)">+</button></div></div>
      <div class="fg"><label>شناسه قالب «لغو شده»، پارامترها: <code>OrderId</code> و <code>Total</code></label><div class="stat-row"><button type="button" class="btn-h minus" onclick="stepNum('smsCancelledTemplateId',-1)">−</button><input type="text" class="stat-input fa-number" name="sms_status_cancelled_template_id" id="smsCancelledTemplateId" value="<?= htmlspecialchars($sms_status_cancelled_template_id, ENT_QUOTES, 'UTF-8') ?>" min="1" inputmode="numeric"><button type="button" class="btn-h plus" onclick="stepNum('smsCancelledTemplateId',1)">+</button></div></div>
      <hr style="border:0;border-top:1px solid var(--border);margin:18px 0">
      <div class="toggle-row" style="margin-top:14px"><label class="toggle"><input type="checkbox" name="sms_notify_admin_order" value="1" <?= getSetting($conn,'sms_notify_admin_order','0')==='1'?'checked':'' ?>><span class="slider"></span></label><span class="toggle-label">پیامک سفارش جدید برای ادمین فعال باشد</span></div>
      <div class="fg"><label>شماره‌ی موبایل ادمین</label><input type="text" name="sms_admin_phone" value="<?= htmlspecialchars((string)getSetting($conn,'sms_admin_phone',''), ENT_QUOTES, 'UTF-8') ?>" dir="ltr" style="text-align:left" placeholder="09123456789"></div>
      <div class="fg"><label>شناسه قالب «سفارش جدید» برای ادمین (در SMS.ir ساخته شود)</label><div class="stat-row"><button type="button" class="btn-h minus" onclick="stepNum('smsAdminOrderTemplateId',-1)">−</button><input type="text" class="stat-input fa-number" name="sms_admin_order_template_id" id="smsAdminOrderTemplateId" value="<?= htmlspecialchars((string)getSetting($conn,'sms_admin_order_template_id',''), ENT_QUOTES, 'UTF-8') ?>" inputmode="numeric" dir="ltr" min="0"><button type="button" class="btn-h plus" onclick="stepNum('smsAdminOrderTemplateId',1)">+</button></div></div>
      <?php $sms_admin_params_saved = array_filter(explode(',', (string)getSetting($conn, 'sms_admin_order_params', ''))); ?>
      <div class="fg"><label>اطلاعات اضافه‌ای که در پیامک ادمین فرستاده شود (اختیاری)</label>
        <div style="display:flex;flex-direction:column;gap:8px;font-size:.8rem">
          <?php foreach ([['OrderId','شماره‌ی سفارش'],['Name','نام گیرنده'],['Phone','شماره‌ی گیرنده'],['City','شهر گیرنده'],['Total','مبلغ کل']] as $sp): ?>
          <label style="display:flex;align-items:center;gap:10px;cursor:pointer"><input type="checkbox" name="sms_admin_params[]" value="<?= $sp[0] ?>" <?= in_array($sp[0], $sms_admin_params_saved, true) ? 'checked' : '' ?> style="width:18px;height:18px;margin:0"><span><?= $sp[1] ?> (<code><?= $sp[0] ?></code>)</span></label>
          <?php endforeach; ?>
        </div>
        <p style="font-size:.68rem;color:var(--text3);line-height:1.6;margin:6px 0 0">نام پارامترها باید دقیقاً با متغیرهای قالب در SMS.ir یکی باشد. اگر قالب هیچ متغیری نداشته باشد، همه‌ی این گزینه‌ها خاموش بمانند.</p>
      </div>
      <p style="font-size:.72rem;color:var(--text3);line-height:1.6;margin-bottom:14px">اگر شناسه یک قالب خالی باشد، همان رویداد بدون خطا رد می‌شود و پیامکی ارسال نمی‌کند.</p>
      <div class="fg"><button type="submit" name="save_sms_settings" class="btn-p" style="margin-top:10px">ذخیره تنظیمات پنل پیامکی</button></div>
    </form>
  </div>

  <!-- ۸. روش‌ها و درگاه پرداخت -->
  <div class="acc-hdr" onclick="toggleAcc(this)"><h3>۸. روش‌ها و درگاه پرداخت</h3><span class="acc-icon">+</span></div>
  <div class="acc-body">
    <?php
      $zarinpal_enabled = getSetting($conn, 'zarinpal_enabled', '0');
      $zarinpal_sandbox = getSetting($conn, 'zarinpal_sandbox', '1');
      $zarinpal_merchant_id = getSetting($conn, 'zarinpal_merchant_id', '');
      $zarinpal_title = getSetting($conn, 'zarinpal_title', 'پرداخت امن زرین‌پال');
    ?>
    <?php if (!empty($payment_error)): ?><div class="msg-err"><?= htmlspecialchars($payment_error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <form method="post">
      <div class="toggle-row">
        <label class="toggle"><input type="checkbox" name="zarinpal_sandbox" value="1" <?= $zarinpal_sandbox === '1' ? 'checked' : '' ?>><span class="slider"></span></label>
        <span class="toggle-label">حالت آزمایشی (Sandbox)</span>
      </div>
      <p style="font-size:.72rem;color:var(--text3);line-height:1.6;margin-bottom:14px">در حالت آزمایشی، پرداخت واقعی انجام نمی‌شود و درخواست‌ها به محیط تست زرین‌پال می‌روند. برای پرداخت واقعی، این گزینه را خاموش کن.</p>
      <div class="toggle-row">
        <label class="toggle"><input type="checkbox" name="zarinpal_enabled" value="1" <?= $zarinpal_enabled === '1' ? 'checked' : '' ?>><span class="slider"></span></label>
        <span class="toggle-label">فعال‌سازی پرداخت آنلاین زرین‌پال</span>
      </div>
      <p style="font-size:.72rem;color:var(--text3);line-height:1.6;margin-bottom:14px">برای پرداخت آنلاین، ابتدا مرچنت‌کد زرین‌پال را وارد و سپس این گزینه را فعال کن.</p>
      <div class="fg">
        <label>مرچنت‌کد زرین‌پال</label>
        <input type="text" name="zarinpal_merchant_id" value="<?= htmlspecialchars($zarinpal_merchant_id, ENT_QUOTES, 'UTF-8') ?>" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" minlength="36" maxlength="36" dir="ltr" style="text-align:left" autocomplete="off">
        <span style="font-size:.65rem;color:var(--text3);margin-top:3px;line-height:1.6">UUID کامل ۳۶ کاراکتری را وارد کن، نه نام کاربری، شماره ترمینال یا کد کوتاه.</span>
      </div>
      <div class="fg">
        <label>عنوان درگاه</label>
        <input type="text" name="zarinpal_title" value="<?= htmlspecialchars($zarinpal_title, ENT_QUOTES, 'UTF-8') ?>" maxlength="100">
      </div>
      <p style="font-size:.72rem;color:var(--text3);line-height:1.6;margin-bottom:14px">مبلغ‌ها در فروشگاه تومان هستند و هنگام ارسال به زرین‌پال به ریال تبدیل می‌شوند.</p>
      <hr style="border:0;border-top:1px dashed var(--border);margin:18px 0">
      <?php
        $card_payment_enabled = getSetting($conn, 'card_payment_enabled', '0');
        $card_owner_name_v = getSetting($conn, 'card_owner_name', '');
        $card_number_v = sitak_format_card_number(getSetting($conn, 'card_number', ''));
        $card_iban_v = sitak_format_iban(getSetting($conn, 'card_iban', ''));
        $card_lock_minutes_v = (int)getSetting($conn, 'card_lock_minutes', '30');
        $card_popup_text_default = 'مبلغ را به یکی از شماره‌های زیر واریز کن، سپس اطلاعات واریز را در صفحه‌ی سفارش‌ها ثبت کن؛ اگر تا پایان مهلت ثبت نکنی، سفارش بطور خودکار لغو می‌شود.';
        $card_popup_text_v = getSetting($conn, 'card_popup_text', $card_popup_text_default);
        $pending_method_enabled_v = getSetting($conn, 'pending_method_enabled', '0');
      ?>
      <div style="font-size:.85rem;font-weight:700;color:var(--gold-dark);margin-bottom:10px">پرداخت کارت‌به‌کارت</div>
      <div class="toggle-row">
        <label class="toggle"><input type="checkbox" name="card_payment_enabled" value="1" <?= $card_payment_enabled === '1' ? 'checked' : '' ?>><span class="slider"></span></label>
        <span class="toggle-label">فعال‌سازی پرداخت کارت‌به‌کارت</span>
      </div>
      <p style="font-size:.72rem;color:var(--text3);line-height:1.6;margin-bottom:14px">خریدار این روش را در سبد خرید انتخاب می‌کند، مبلغ را به کارت یا شبای تو واریز می‌کند و اطلاعات واریز را در صفحه‌ی سفارش‌ها ثبت می‌کند تا آن را تأیید یا رد کنی.</p>
      <div class="fg">
        <label>نام و نام خانوادگی صاحب حساب</label>
        <input type="text" name="card_owner_name" value="<?= htmlspecialchars($card_owner_name_v, ENT_QUOTES, 'UTF-8') ?>" maxlength="120" placeholder="مثلاً: علی محمدی" autocomplete="off">
      </div>
      <div class="fg">
        <label>شماره کارت</label>
        <input type="text" name="card_number" value="<?= htmlspecialchars($card_number_v, ENT_QUOTES, 'UTF-8') ?>" dir="ltr" style="text-align:left" inputmode="numeric" maxlength="23" placeholder="6037-9911-2233-4455" autocomplete="off">
        <span style="font-size:.65rem;color:var(--text3);margin-top:3px;line-height:1.6">۱۶ رقم؛ برای خریدار ۴ رقم ۴ رقم با خط تیره و دکمه‌ی کپی نمایش داده می‌شود.</span>
      </div>
      <div class="fg">
        <label>شماره شبا (اختیاری)</label>
        <input type="text" name="card_iban" value="<?= htmlspecialchars($card_iban_v, ENT_QUOTES, 'UTF-8') ?>" dir="ltr" style="text-align:left" maxlength="39" placeholder="IR82-0540-1026-8002-0817-9090-02" autocomplete="off">
        <span style="font-size:.65rem;color:var(--text3);margin-top:3px;line-height:1.6">با IR شروع می‌شود؛ برای خریدار ۴ کاراکتر ۴ کاراکتر با خط تیره و دکمه‌ی کپی نمایش داده می‌شود.</span>
      </div>
      <div class="fg">
        <label>متن راهنمای پاپ‌آپ واریز</label>
        <textarea name="card_popup_text" rows="3" maxlength="600"><?= htmlspecialchars($card_popup_text_v, ENT_QUOTES, 'UTF-8') ?></textarea>
        <span style="font-size:.65rem;color:var(--text3);margin-top:3px;line-height:1.6">این متن در پاپ‌آپ اطلاعات واریز، بعد از ثبت سفارش کارت‌به‌کارت نمایش داده می‌شود. اگر خالی بگذاری، همین متن پیش‌فرض ذخیره می‌شود.</span>
      </div>
      <div class="fg">
        <label>مدت قفل (رزرو) سفارش در سبد خرید — به دقیقه</label>
        <div class="stat-row"><button type="button" class="btn-h minus" onclick="stepNum('cardLockMinutes',-5)">−</button><input type="text" inputmode="numeric" class="stat-input" name="card_lock_minutes" id="cardLockMinutes" min="5" max="1440" value="<?= $card_lock_minutes_v ?>"><button type="button" class="btn-h plus" onclick="stepNum('cardLockMinutes',5)">+</button></div>
        <span style="font-size:.65rem;color:var(--text3);margin-top:3px;line-height:1.6">تایمر از لحظه‌ی نمایش پاپ‌آپ واریز شروع می‌شود و کالاها در این مدت برای خریدار رزرو می‌مانند؛ اگر اطلاعات واریز ثبت نشود، سفارش بطور خودکار لغو می‌شود.</span>
      </div>
      <hr style="border:0;border-top:1px dashed var(--border);margin:18px 0">
      <div style="font-size:.85rem;font-weight:700;color:var(--gold-dark);margin-bottom:10px">پرداخت پس از هماهنگی</div>
      <div class="toggle-row">
        <label class="toggle"><input type="checkbox" name="pending_method_enabled" value="1" <?= $pending_method_enabled_v === '1' ? 'checked' : '' ?>><span class="slider"></span></label>
        <span class="toggle-label">گزینه‌ی «پرداخت پس از هماهنگی» در سبد خرید نمایش داده شود</span>
      </div>
      <p style="font-size:.72rem;color:var(--text3);line-height:1.6;margin-bottom:14px">در این روش سفارش بدون پرداخت ثبت می‌شود و پرداخت بعداً هماهنگ می‌شود؛ موجودی با تأیید تو کسر می‌گردد. این گزینه بطور پیش‌فرض غیرفعال است.</p>
      <div class="fg"><button type="submit" name="save_zarinpal_settings" class="btn-p" style="margin-top:10px">ذخیره تنظیمات روش‌های پرداخت</button></div>
    </form>

  </div>

  <!-- ۹. نقشه‌ی تحویل سفارش -->
  <div class="acc-hdr" onclick="toggleAcc(this)"><h3>۹. نقشه‌ی تحویل سفارش</h3><span class="acc-icon">+</span></div>
  <div class="acc-body">
    <?php
      $map_on_v = getSetting($conn, 'map_enabled', '0') === '1';
      $map_key_v = getSetting($conn, 'map_api_key', '');
      $map_browser_v = getSetting($conn, 'map_browser_key', '');
      $map_lat_v = getSetting($conn, 'map_default_lat', '32.4279');
      $map_lng_v = getSetting($conn, 'map_default_lng', '53.6880');
      $map_zoom_v = getSetting($conn, 'map_default_zoom', '5');
    ?>
    <form method="post" onsubmit="return adminMapFill()" style="margin-top:6px">
      <div style="font-size:.85rem;font-weight:700;color:var(--gold-dark);margin-bottom:10px">نقشه‌ی تحویل سفارش (map.ir)</div>
      <div class="toggle-row" style="margin-bottom:12px">
        <label class="toggle"><input type="checkbox" name="map_enabled" value="1" <?= $map_on_v ? 'checked' : '' ?>><span class="slider"></span></label>
        <span class="toggle-label">ثبت موقعیت تحویل روی نقشه فعال باشد</span>
      </div>
      <div class="fg"><label>کلید سرور (فقط برای آدرس‌یابی، هیچ‌جا در صفحه نمایش داده نمی‌شود)</label><input type="text" class="fi" name="map_api_key" dir="ltr" style="text-align:left" value="<?= htmlspecialchars($map_key_v, ENT_QUOTES, 'UTF-8') ?>"></div>
      <div class="fg"><label>کلید مرورگر (برای نمایش نقشه؛ در پنل مپ به دامنه‌ی سایت محدودش کن)</label><input type="text" class="fi" name="map_browser_key" dir="ltr" style="text-align:left" value="<?= htmlspecialchars($map_browser_v, ENT_QUOTES, 'UTF-8') ?>"></div>
      <div class="fg"><label>محل و زوم پیش‌فرض نقشه (نقشه را جابه‌جا و زوم کنید، سپس ذخیره را بزنید)</label>
        <style>#adminMap,#adminMap .mapboxgl-canvas-container,#adminMap canvas{border-radius:12px}</style>
        <div style="position:relative;height:260px;border-radius:12px;overflow:hidden;isolation:isolate;border:1px solid var(--border)">
          <div id="adminMap" style="width:100%;height:100%"></div>
          <div style="position:absolute;left:50%;top:50%;transform:translate(-50%,-100%);pointer-events:none;font-size:28px;z-index:5">📍</div>
        </div>
        <div style="font-size:.72rem;color:var(--text3);margin-top:6px" dir="ltr">lat <?= htmlspecialchars($map_lat_v, ENT_QUOTES, 'UTF-8') ?> · lng <?= htmlspecialchars($map_lng_v, ENT_QUOTES, 'UTF-8') ?> · zoom <?= htmlspecialchars($map_zoom_v, ENT_QUOTES, 'UTF-8') ?></div>
      </div>
      <input type="hidden" name="map_default_lat" id="mapDefLat" value="">
      <input type="hidden" name="map_default_lng" id="mapDefLng" value="">
      <input type="hidden" name="map_default_zoom" id="mapDefZoom" value="">
      <div class="fg"><button type="submit" name="save_map_settings" class="btn-p" style="margin-top:10px">ذخیره تنظیمات نقشه</button></div>
    </form>
    <script>
    var ADMIN_MAP_ON = <?= ($map_on_v && $map_browser_v !== '') ? 'true' : 'false' ?>;
    var ADMIN_MAP_KEY = <?= json_encode($map_browser_v, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var ADMIN_MAP_LAT = <?= (float)$map_lat_v ?>;
    var ADMIN_MAP_LNG = <?= (float)$map_lng_v ?>;
    var ADMIN_MAP_ZOOM = <?= (int)$map_zoom_v ?>;
    var adminMapObj = null;
    function adminMapLoadSdk(cb){
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
    function adminMapBoot(){
      if(!ADMIN_MAP_ON||adminMapObj)return;
      var el=document.getElementById('adminMap');
      if(!el||el.offsetWidth===0)return;
      adminMapLoadSdk(function(){
        if(adminMapObj||!window.mapboxgl)return;
        if(!window.__rtlSet){window.__rtlSet=1;mapboxgl.setRTLTextPlugin('https://cdn.map.ir/web-sdk/1.4.4/js/mapbox-gl-rtl-text-v0.2.0.js',null,true);}
        adminMapObj=new mapboxgl.Map({container:'adminMap',style:'https://map.ir/vector/styles/main/mapir-xyz-style.json',center:[ADMIN_MAP_LNG,ADMIN_MAP_LAT],zoom:ADMIN_MAP_ZOOM,accessToken:ADMIN_MAP_KEY,transformRequest:function(url){return {url:url,headers:{'x-api-key':ADMIN_MAP_KEY}}}});
        clearInterval(adminMapTimer);
      });
    }
    function adminMapFill(){
      if(adminMapObj&&adminMapObj.getCenter){
        var c=adminMapObj.getCenter();
        document.getElementById('mapDefLat').value=c.lat;
        document.getElementById('mapDefLng').value=c.lng;
        if(adminMapObj.getZoom) document.getElementById('mapDefZoom').value=adminMapObj.getZoom();
      }
      return true;
    }
    var adminMapTimer=setInterval(adminMapBoot,600);
    </script>
  </div>

  <!-- ۱۰. مجوزها -->
  <div class="acc-hdr" onclick="toggleAcc(this)"><h3>۱۰. مجوزها</h3><span class="acc-icon">+</span></div>
  <div class="acc-body">
    <form method="post" enctype="multipart/form-data" id="licenseForm">
      <div class="toggle-row" style="margin-bottom:14px">
        <label class="toggle"><input type="checkbox" name="licenses_enabled" value="1" <?= $licenses_enabled==='1'?'checked':'' ?>><span class="slider"></span></label>
        <span class="toggle-label">نمایش مجوزها در پایین صفحه‌ی اصلی</span>
      </div>
      <p style="font-size:.72rem;color:var(--text3);line-height:1.6;margin-bottom:14px">اگر تصویر مجوزی تنظیم نشده باشد، آن مورد در صفحه‌ی اصلی نمایش داده نمی‌شود.</p>
      <div class="fg">
        <label>مجوز ۱ — نام (برای نمایش در hover)</label>
        <input type="text" name="license1_name" value="<?= htmlspecialchars($license1_name ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="مثلاً: نماد اعتماد الکترونیکی" maxlength="60">
      </div>
      <div class="fg">
        <label>مجوز ۱ — لینک (اختیاری)</label>
        <input type="url" name="license1_link" value="<?= htmlspecialchars($license1_link, ENT_QUOTES, 'UTF-8') ?>" placeholder="https://...">
      </div>
      <div class="fg">
        <label>مجوز ۱ — کد (اختیاری، HTML/JS از سرور مجوز)</label>
        <textarea name="license1_embed" rows="4" dir="ltr" style="text-align:left;font-family:monospace" placeholder="<script>...</script> یا کد نماد..."><?= htmlspecialchars($license1_embed ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
      </div>
      <div class="fg">
        <label>مجوز ۱ — تصویر</label>
        <input type="file" name="license1_img" accept=".png,.jpg,.jpeg,.gif,.svg,.webp" style="padding:7px">
        <?php if ($license1_img): ?>
          <div style="display:flex;align-items:center;gap:8px;margin-top:8px">
            <img src="<?= htmlspecialchars($license1_img) ?>" style="height:60px;object-fit:contain;border:1px solid var(--border);border-radius:6px;background:#fff;padding:2px">
            <button type="submit" name="delete_license1" class="btn-sm btn-red" style="font-size:.65rem">حذف 🗑️</button>
          </div>
        <?php endif; ?>
      </div>
      <hr style="border:0;border-top:1px dashed var(--border);margin:14px 0">
      <div class="fg">
        <label>مجوز ۲ — نام (برای نمایش در hover)</label>
        <input type="text" name="license2_name" value="<?= htmlspecialchars($license2_name ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="مثلاً: زرین‌پال" maxlength="60">
      </div>
      <div class="fg">
        <label>مجوز ۲ — لینک (اختیاری)</label>
        <input type="url" name="license2_link" value="<?= htmlspecialchars($license2_link, ENT_QUOTES, 'UTF-8') ?>" placeholder="https://...">
      </div>
      <div class="fg">
        <label>مجوز ۲ — کد (اختیاری، HTML/JS از سرور مجوز)</label>
        <textarea name="license2_embed" rows="4" dir="ltr" style="text-align:left;font-family:monospace" placeholder="<script>...</script> یا کد نماد..."><?= htmlspecialchars($license2_embed ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
      </div>
      <div class="fg">
        <label>مجوز ۲ — تصویر</label>
        <input type="file" name="license2_img" accept=".png,.jpg,.jpeg,.gif,.svg,.webp" style="padding:7px">
        <?php if ($license2_img): ?>
          <div style="display:flex;align-items:center;gap:8px;margin-top:8px">
            <img src="<?= htmlspecialchars($license2_img) ?>" style="height:60px;object-fit:contain;border:1px solid var(--border);border-radius:6px;background:#fff;padding:2px">
            <button type="submit" name="delete_license2" class="btn-sm btn-red" style="font-size:.65rem">حذف 🗑️</button>
          </div>
        <?php endif; ?>
      </div>
      <div class="fg"><button type="submit" name="save_licenses" class="btn-p" style="margin-top:10px">ذخیره تنظیمات مجوزها</button></div>
    </form>
  </div>

  <!-- ۱۱. اعلان‌های مدیر -->
  <div class="acc-hdr" onclick="toggleAcc(this)"><h3>۱۱. اعلان‌های مدیر</h3><span class="acc-icon">+</span></div>
  <div class="acc-body">
    <?php
      $an_enabled = getSetting($conn, 'admin_notify_enabled', '1');
      $an_orders = getSetting($conn, 'admin_notify_orders', '1');
      $an_winners = getSetting($conn, 'admin_notify_winners', '1');
      $an_users = getSetting($conn, 'admin_notify_users', '1');
      $an_resets = getSetting($conn, 'admin_notify_resets', '1');
      $an_payments = getSetting($conn, 'admin_notify_payments', '1');
      $an_sound = getSetting($conn, 'admin_notify_sound', '1');
      $an_display = getSetting($conn, 'admin_notify_display', 'popup');
      if (!in_array($an_display, ['popup', 'toast', 'none'], true)) $an_display = 'popup';
      $an_radio_style = 'display:flex;align-items:flex-start;gap:9px;padding:11px 14px;background:var(--bg,#f5f5f7);border:1.5px solid var(--border,rgba(0,0,0,.08));border-radius:12px;cursor:pointer;font-size:.82rem;font-weight:600;color:var(--text,#1d1d1f)';
      $an_radio_sub = 'display:block;font-size:.66rem;color:var(--text3,#aeaeb2);font-weight:500;margin-top:3px;line-height:1.7';
      $an_radio_in = 'margin-top:3px;accent-color:var(--gold-dark,#c49625)';
    ?>
    <form method="post">
      <div class="toggle-row">
        <label class="toggle"><input type="checkbox" name="admin_notify_enabled" value="1" <?= $an_enabled === '1' ? 'checked' : '' ?>><span class="slider"></span></label>
        <span class="toggle-label">پخش اعلان برای مدیر فعال باشد</span>
      </div>
      <p style="font-size:.72rem;color:var(--text3);line-height:1.6;margin-bottom:14px">وقتی رویداد تازه‌ای رخ دهد (سفارش جدید، بُرد جایزه، ثبت اطلاعات واریز، پرداخت آنلاین، کاربر جدید یا درخواست بازنشانی)، اعلان برای مدیر پخش می‌شود. اگر صفحه‌ی پنل باز باشد، اعلان روی همان صفحه نمایش داده می‌شود؛ اگر مرورگر بسته باشد، از طریق اعلان مرورگر (Web Push) اطلاع داده می‌شود — برای فعال‌سازی آن، دکمه‌ی 🔔 در هدر پنل را بزن و اجازه بده (نیاز به HTTPS).</p>
      <div class="toggle-row">
        <label class="toggle"><input type="checkbox" name="admin_notify_orders" value="1" <?= $an_orders === '1' ? 'checked' : '' ?>><span class="slider"></span></label>
        <span class="toggle-label">اعلان سفارش جدید (لیست سفارشات)</span>
      </div>
      <div class="toggle-row">
        <label class="toggle"><input type="checkbox" name="admin_notify_payments" value="1" <?= $an_payments === '1' ? 'checked' : '' ?>><span class="slider"></span></label>
        <span class="toggle-label">اعلان ثبت اطلاعات واریز کارت‌به‌کارت و پرداخت آنلاین</span>
      </div>
      <div class="toggle-row">
        <label class="toggle"><input type="checkbox" name="admin_notify_winners" value="1" <?= $an_winners === '1' ? 'checked' : '' ?>><span class="slider"></span></label>
        <span class="toggle-label">اعلان بُرد جایزه (لیست برندگان)</span>
      </div>
      <div class="toggle-row">
        <label class="toggle"><input type="checkbox" name="admin_notify_users" value="1" <?= $an_users === '1' ? 'checked' : '' ?>><span class="slider"></span></label>
        <span class="toggle-label">اعلان ثبت‌نام کاربر جدید (لیست کاربران)</span>
      </div>
      <div class="toggle-row">
        <label class="toggle"><input type="checkbox" name="admin_notify_resets" value="1" <?= $an_resets === '1' ? 'checked' : '' ?>><span class="slider"></span></label>
        <span class="toggle-label">اعلان درخواست بازنشانی گذرواژه</span>
      </div>
      <div class="toggle-row" style="margin-top:14px;padding-top:14px;border-top:1px dashed var(--border,rgba(0,0,0,.08))">
        <label class="toggle"><input type="checkbox" name="admin_notify_sound" value="1" <?= $an_sound === '1' ? 'checked' : '' ?>><span class="slider"></span></label>
        <span class="toggle-label">پخش صدای اعلان هنگام رویداد تازه</span>
      </div>
      <div style="margin:14px 0 6px">
        <span style="display:block;font-size:.78rem;font-weight:700;color:var(--text2,#6e6e73);margin-bottom:8px">نمایش پیام اعلان در صفحه‌های باز پنل</span>
        <div style="display:flex;flex-direction:column;gap:8px">
          <label style="<?= $an_radio_style ?>">
            <input type="radio" name="admin_notify_display" value="popup" <?= $an_display === 'popup' ? 'checked' : '' ?> style="<?= $an_radio_in ?>">
            <span>پاپ‌آپ نیازمند تأیید<span style="<?= $an_radio_sub ?>">پیام از پایین صفحه باز می‌شود و برای بستن باید دکمه‌ی «بستن» زده شود</span></span>
          </label>
          <label style="<?= $an_radio_style ?>">
            <input type="radio" name="admin_notify_display" value="toast" <?= $an_display === 'toast' ? 'checked' : '' ?> style="<?= $an_radio_in ?>">
            <span>پیام خودمحو شونده<span style="<?= $an_radio_sub ?>">پیام کوتاه (Toast) گوشه‌ی صفحه نمایش داده و خودش محو می‌شود</span></span>
          </label>
          <label style="<?= $an_radio_style ?>">
            <input type="radio" name="admin_notify_display" value="none" <?= $an_display === 'none' ? 'checked' : '' ?> style="<?= $an_radio_in ?>">
            <span>بدون نمایش پیام<span style="<?= $an_radio_sub ?>">پیامی روی صفحه نمایش داده نمی‌شود (اعلان مرورگر و صدا سر جای خودشان هستند)</span></span>
          </label>
        </div>
      </div>
      <div class="fg"><button type="submit" name="save_admin_notify" class="btn-p" style="margin-top:10px">ذخیره تنظیمات اعلان‌ها</button></div>
    </form>
    <form method="post" style="margin-top:14px;padding-top:14px;border-top:1px dashed var(--border,rgba(0,0,0,.08))">
      <button type="submit" name="test_notify" class="btn-p">پخش اعلان آزمایشی 🔔</button>
      <p style="font-size:.65rem;color:var(--text3);margin-top:8px;line-height:1.7">برای سنجش مسیر اعلان: با زدن این دکمه یک اعلان آزمایشی پخش می‌شود. این صفحه را تا حدود ۲۵ ثانیه باز نگه دار تا پیام و صدا را ببینی.</p>
    </form>
    <?php if (isset($_GET['notify_test'])): ?>
    <script>document.addEventListener('DOMContentLoaded',function(){ if (typeof showToast === 'function') showToast(<?= ($_GET['notify_test'] ?? '') === '1' ? '"اعلان آزمایشی پخش شد 🔔 — اگر پنل باز بماند، پیام تا ~۲۵ ثانیه‌ی دیگر ظاهر می‌شود"' : '"اعلان‌ها خاموش است — ابتدا کلید اصلی را روشن و ذخیره کن"' ?>); });</script>
    <?php endif; ?>
  </div>

  <!-- ۱۲. اسکریپت‌های سفارشی هدر -->
  <div class="acc-hdr" onclick="toggleAcc(this)"><h3>۱۲. اسکریپت‌های سفارشی هدر</h3><span class="acc-icon">+</span></div>
  <div class="acc-body">
    <p style="font-size:.72rem;color:var(--text3);line-height:1.7;margin-bottom:12px">کدهای این بخش (مثل گوگل‌آنالیتیکس، تأیید سرچ‌کنسول یا مایکروسافت کلاریتی) در <code>&lt;head&gt;</code> تمام صفحات سمت کاربر قرار می‌گیرند. فقط اسکریپت‌های فعال تزریق می‌شوند.</p>
    <div class="fg">
      <label>عنوان اسکریپت جدید / در حال ویرایش</label>
      <input type="text" class="fi" id="scriptTitle" maxlength="60" placeholder="مثلاً: گوگل آنالیتیکس">
    </div>
    <div class="fg">
      <label>کد اسکریپت (HTML کامل)</label>
      <textarea class="fi" id="scriptCode" rows="5" dir="ltr" style="text-align:left;font-family:monospace" placeholder="<script>...</script>"></textarea>
    </div>
    <input type="hidden" id="scriptId" value="0">
    <div style="display:flex;gap:8px;margin-bottom:14px">
      <button type="button" class="btn-p" onclick="saveScript()"">ذخیره اسکریپت</button>
      <button type="button" class="btn-sm btn-red" id="scriptCancelEdit" style="display:none;font-size:.78rem" onclick="cancelScriptEdit()">انصراف از ویرایش</button>
    </div>
    <hr style="border:0;border-top:1px solid var(--border);margin:16px 0">
    <div style="font-size:.82rem;font-weight:700;color:var(--gold-dark);margin-bottom:10px">لیست اسکریپت‌ها</div>
    <div id="scriptList" class="tbl-wrap">
      <table><thead><tr><th>عنوان</th><th style="text-align:center">وضعیت</th><th style="text-align:center">عملیات</th></tr></thead><tbody>
      <?php
      [$ss_pp,$ss_pg]=admin_list_page('ss'); $ss_total=(int)$conn->query("SELECT COUNT(*) AS c FROM custom_scripts")->fetch_assoc()["c"]; $ss_pg=min($ss_pg,max(1,(int)ceil($ss_total/$ss_pp))); $ss_off=($ss_pg-1)*$ss_pp; $sres = $conn->query("SELECT * FROM custom_scripts ORDER BY id ASC LIMIT $ss_off,$ss_pp");
      if ($sres && $sres->num_rows > 0):
        while ($s = $sres->fetch_assoc()):
      ?>
      <tr>
        <td style="font-size:.78rem"><?= htmlspecialchars($s['title'], ENT_QUOTES, 'UTF-8') ?></td>
        <td style="text-align:center;font-size:1rem"><?= $s['is_active'] ? '✅' : '⛔' ?></td>
        <td style="white-space:nowrap;text-align:center">
          <button type="button" class="btn-sm btn-blue" style="font-size:.68rem" onclick="editScript(<?= (int)$s['id'] ?>)">✏️</button>
          <a href="<?= $admin_self ?>?toggle_script=<?= (int)$s['id'] ?>&csrf=<?= sitak_csrf_token() ?>" class="btn-sm act-toggle" title="<?= $s['is_active'] ? 'غیرفعال کردن' : 'فعال کردن' ?>"><?= $s['is_active'] ? '⛔' : '✅' ?></a>
          <a href="<?= $admin_self ?>?delete_script=<?= (int)$s['id'] ?>&csrf=<?= sitak_csrf_token() ?>" class="btn-sm btn-red" style="font-size:.68rem" onclick="return adminConfirmEl(this,'این اسکریپت حذف شود؟')">🗑️</a>
        </td>
      </tr>
      <?php endwhile; else: ?>
      <tr><td colspan="3" style="text-align:center;color:var(--text3);padding:16px">اسکریپتی ثبت نشده است.</td></tr>
      <?php endif; ?>
      </tbody></table>
    </div>
    <?= admin_pager('ss', null, $ss_total, $ss_pp, $ss_pg) ?>
  </div>

<div class="acc-hdr" onclick="toggleAcc(this)"><h3>۱۳. تنظیمات کد معرف</h3><span class="acc-icon">+</span></div>
  <div class="acc-body">
    <?php $referral_hint=getSetting($conn,'referral_hint','با ثبت شماره معرف، هم شما و هم معرفتان یک شانس اضافه دریافت می‌کنید.'); ?>
    <form method="post">
      <div class="toggle-row"><label class="toggle"><input type="checkbox" name="referral_enabled" value="1" <?= getSetting($conn,'referral_enabled','1')==='1'?'checked':'' ?>><span class="slider"></span></label><span class="toggle-label">فعال‌سازی کد معرف</span></div>
      <div class="fg"><label>متن زیر فیلد معرف</label><textarea name="referral_hint" rows="3" maxlength="255"><?= htmlspecialchars($referral_hint,ENT_QUOTES,'UTF-8') ?></textarea></div>
      <div class="fg"><label>تعداد شانس معرفی‌کننده</label><div class="stat-row"><button type="button" class="btn-h minus" onclick="stepNum('referralInviterRolls',-1)">−</button><input type="text" class="stat-input fa-number" name="referral_inviter_rolls" id="referralInviterRolls" min="0" max="99" inputmode="numeric" value="<?= (int)getSetting($conn,'referral_inviter_rolls','1') ?>"><button type="button" class="btn-h plus" onclick="stepNum('referralInviterRolls',1)">+</button></div></div>
      <div class="fg"><label>تعداد شانس معرفی‌شونده</label><div class="stat-row"><button type="button" class="btn-h minus" onclick="stepNum('referralInviteeRolls',-1)">−</button><input type="text" class="stat-input fa-number" name="referral_invitee_rolls" id="referralInviteeRolls" min="0" max="99" inputmode="numeric" value="<?= (int)getSetting($conn,'referral_invitee_rolls','1') ?>"><button type="button" class="btn-h plus" onclick="stepNum('referralInviteeRolls',1)">+</button></div></div>
      <div class="fg"><button type="submit" name="save_referral_settings" class="btn-p">ذخیره تنظیمات کد معرف</button></div>
    </form>
  </div>
  <!-- ۱۴. فایروال سبک -->
  <style>
    [data-theme="light"] .fw-wrap{--border:rgba(0,0,0,.16);--border-gold:rgba(236,181,52,.4);--input-bg:#f5f5f7}
    .fw-wrap{display:grid;gap:12px}
    .fw-hero{padding:16px;background:var(--card);border:1px solid var(--border-gold);border-radius:var(--r);display:flex;align-items:center;justify-content:space-between;gap:16px;box-shadow:var(--shadow-sm)}
    .fw-hero h4{margin:0 0 5px;font-size:1rem;color:var(--text)}.fw-hero p{margin:0;color:var(--text2);font-size:.72rem;line-height:1.7;max-width:430px}
    .fw-state{display:inline-flex;align-items:center;gap:7px;padding:7px 11px;border-radius:var(--rss);background:var(--green-bg);border:1px solid rgba(48,209,88,.18);font-size:.72rem;font-weight:800;white-space:nowrap;color:var(--green-text)}.fw-dot{width:7px;height:7px;border-radius:50%;background:var(--green)}
    .fw-kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:9px}.fw-kpi{padding:13px;border-radius:var(--rs);background:var(--card);border:1px solid var(--border);min-width:0;box-shadow:var(--shadow-sm)}.fw-kpi .label{font-size:.66rem;color:var(--text2);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.fw-kpi .num{font-size:1.2rem;font-weight:900;color:var(--gold-dark);margin-top:5px}.fw-kpi .sub{font-size:.62rem;color:var(--text3);margin-top:2px}
    .fw-panel{padding:15px;border-radius:var(--r);background:var(--card);border:1px solid var(--border);box-shadow:var(--shadow-sm)}.fw-panel-title{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}.fw-panel-title h4{margin:0;font-size:.84rem}.fw-panel-title span{font-size:.65rem;color:var(--text3)}
    .fw-switch{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:11px 0;border-bottom:1px solid var(--border)}.fw-switch:last-of-type{border-bottom:0}.fw-switch strong{display:block;font-size:.76rem}.fw-switch small{display:block;color:var(--text3);font-size:.65rem;margin-top:3px;line-height:1.6}
    .fw-fields{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin-top:13px}.fw-field{padding:10px;border-radius:var(--rss);background:var(--input-bg);border:1px solid var(--border)}.fw-field label{display:block;font-size:.68rem;color:var(--text2);margin-bottom:5px}.fw-field input{margin:0!important}
    .fw-actions{display:flex;flex-direction:column;align-items:stretch;gap:10px;margin-top:14px}.fw-buttons{display:flex;justify-content:flex-end;gap:8px;align-items:center;width:100%}.fw-note{font-size:.65rem;color:var(--text3);line-height:1.6}.fw-save{width:auto!important;padding:10px 17px!important;white-space:nowrap;order:0}.fw-reset{width:auto!important;padding:10px 14px!important;white-space:nowrap;background:var(--card-el)!important;color:var(--text2)!important;box-shadow:none!important;border:1px solid var(--border)!important}
    @media(max-width:560px){.fw-hero{align-items:flex-start;flex-direction:column}.fw-kpis{grid-template-columns:repeat(2,1fr)}.fw-fields{grid-template-columns:1fr}.fw-buttons{display:grid;grid-template-columns:1fr 1fr}.fw-save,.fw-reset{width:100%!important}}
  </style>
  <div class="acc-hdr" onclick="toggleAcc(this)"><h3>۱۴. فایروال و ضدربات</h3><span class="acc-icon">+</span></div>
  <div class="acc-body">
    <div class="fw-wrap">
      <div class="fw-hero">
        <div><h4>فایروال سایت</h4><p>محافظت سبک در برابر ربات‌ها و درخواست‌های غیرعادی، هماهنگ با ظاهر ساده و گرم پنل.</p></div>
        <div class="fw-state"><span class="fw-dot"></span><?= $firewall_enabled==='1' ? 'فایروال فعال' : 'فایروال خاموش' ?></div>
      </div>
      <div class="fw-kpis">
        <div class="fw-kpi"><div class="label">کل رخدادها</div><div class="num"><?= pn($firewall_stats_total) ?></div><div class="sub">از شروع ثبت لاگ</div></div>
        <div class="fw-kpi"><div class="label">۲۴ ساعت اخیر</div><div class="num"><?= pn($firewall_stats_24h) ?></div><div class="sub">فعالیت تازه</div></div>
        <div class="fw-kpi"><div class="label">Honeypot</div><div class="num"><?= pn($firewall_stats_honeypot) ?></div><div class="sub">ربات‌های شناسایی‌شده</div></div>
        <div class="fw-kpi"><div class="label">Rate Limit</div><div class="num"><?= pn($firewall_stats_rate) ?></div><div class="sub">درخواست‌های مهارشده</div></div>
      </div>
      <form method="post">
        <div class="fw-panel">
          <div class="fw-panel-title"><h4>لایه‌های محافظت</h4><span>تنظیمات اصلی</span></div>
          <div class="fw-switch"><div><strong>فایروال سبک</strong><small>هسته‌ی کنترل رخدادها و سیاست‌های امنیتی.</small></div><label class="toggle"><input type="checkbox" name="firewall_enabled" value="1" <?= $firewall_enabled==='1'?'checked':'' ?>><span class="slider"></span></label></div>
          <div class="fw-switch"><div><strong>Honeypot ضدربات</strong><small>فیلد تله‌ای نامرئی برای فرم ثبت‌نام.</small></div><label class="toggle"><input type="checkbox" name="firewall_honeypot_enabled" value="1" <?= $firewall_honeypot_enabled==='1'?'checked':'' ?>><span class="slider"></span></label></div>
          <div class="fw-switch"><div><strong>محدودسازی درخواست‌ها</strong><small>جلوگیری از ارسال پشت‌سرهم درخواست‌های ورود و ثبت‌نام.</small></div><label class="toggle"><input type="checkbox" name="firewall_rate_limit_enabled" value="1" <?= $firewall_rate_limit_enabled==='1'?'checked':'' ?>><span class="slider"></span></label></div>
          <div class="fw-switch"><div><strong>ثبت رخدادهای امنیتی</strong><small>ذخیره‌ی آمار برای پایش، بدون نگهداری IP خام.</small></div><label class="toggle"><input type="checkbox" name="firewall_logging_enabled" value="1" <?= $firewall_logging_enabled==='1'?'checked':'' ?>><span class="slider"></span></label></div>
        </div>
        <div class="fw-panel" style="margin-top:12px">
          <div class="fw-panel-title"><h4>محدودسازی درخواست‌ها</h4><span>تنظیمات پیشرفته</span></div>
          <div class="fw-fields">
            <div class="fw-field"><label>پنجره‌ی زمانی (ثانیه)</label><input class="fi" type="text" name="firewall_rate_limit_window" inputmode="numeric" value="<?= htmlspecialchars($firewall_rate_limit_window,ENT_QUOTES,'UTF-8') ?>"></div>
            <div class="fw-field"><label>حداکثر درخواست در بازه</label><input class="fi" type="text" name="firewall_rate_limit_max" inputmode="numeric" value="<?= htmlspecialchars($firewall_rate_limit_max,ENT_QUOTES,'UTF-8') ?>"></div>
            <div class="fw-field"><label>آستانه‌ی رخداد برای مسدودسازی</label><input class="fi" type="text" name="firewall_block_threshold" inputmode="numeric" value="<?= htmlspecialchars($firewall_block_threshold,ENT_QUOTES,'UTF-8') ?>"></div>
            <div class="fw-field"><label>نگهداری لاگ (روز)</label><input class="fi" type="text" name="firewall_log_retention_days" inputmode="numeric" value="<?= htmlspecialchars($firewall_log_retention_days,ENT_QUOTES,'UTF-8') ?>"></div>
          </div>
          <div class="fw-actions"><div class="fw-note"><strong style="display:block;color:var(--text2);font-size:.68rem;margin-bottom:2px">پیشنهاد شروع</strong><span>۵ درخواست در ۶۰ ثانیه، نگهداری لاگ برای ۳۰ روز.</span></div><div class="fw-buttons"><button type="submit" name="reset_firewall_settings" class="btn-p fw-reset" onclick="return adminConfirmEl(this,'تنظیمات فایروال به مقادیر پیش‌فرض بازنشانی شود؟')">بازنشانی پیش‌فرض</button><button type="submit" name="save_firewall_settings" class="btn-p fw-save">ذخیره تنظیمات</button></div></div>
        </div>
      </form>
    </div>
  </div>

<style>
.backup-intro{font-size:.78rem;color:var(--text2);line-height:1.9;background:var(--gold-subtle);border:1px solid var(--border-gold);border-radius:var(--rss);padding:11px 12px;margin-bottom:16px}
.backup-group{margin-top:18px}.backup-group-title{display:flex;align-items:center;gap:7px;font-size:.78rem;font-weight:700;color:var(--gold-dark);margin-bottom:9px}
.backup-downloads{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}.backup-download{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;min-height:0;padding:9px 7px!important;text-align:center;text-decoration:none!important;line-height:1.45;font-size:.75rem!important}
.backup-download small{font-size:.62rem;font-weight:500;opacity:.82}.backup-restore-list{display:grid;gap:10px}.backup-restore-card{border:1px solid var(--border);background:var(--bg);border-radius:var(--rss);padding:11px}.backup-restore-card form{display:grid;grid-template-columns:1fr auto;gap:8px;align-items:center}.backup-restore-card input[type=file]{min-width:0;width:100%;padding:8px;border:1px dashed var(--border-gold);border-radius:var(--rss);background:var(--card);color:var(--text2);font-family:var(--font);font-size:.72rem}.backup-restore-card button{white-space:nowrap;padding:11px 14px!important;font-size:.75rem!important}.backup-restore-label{display:block;font-size:.74rem;font-weight:700;color:var(--text2);margin-bottom:7px}.backup-restore-note{font-size:.64rem;color:var(--text3);line-height:1.6;margin-top:6px}
.backup-status{padding:10px 12px;border-radius:var(--rss);font-size:.75rem;line-height:1.7;margin-bottom:12px}.backup-status.ok{color:var(--green);background:rgba(48,209,88,.08);border:1px solid rgba(48,209,88,.18)}.backup-status.err{color:var(--red);background:var(--red-bg);border:1px solid rgba(255,69,58,.18)}
@media(max-width:520px){.backup-downloads{grid-template-columns:1fr}.backup-download{min-height:0;flex-direction:row;justify-content:flex-start;text-align:right;padding:9px 12px!important}.backup-download small{margin-right:auto}.backup-restore-card form{grid-template-columns:1fr}.backup-restore-card button{width:100%}}
</style>
  <!-- ۱۲. ساعت ریست -->
  <div class="acc-hdr" onclick="toggleAcc(this)"><h3>۱۵. ساعت ریست روزانه</h3><span class="acc-icon">+</span></div>
  <div class="acc-body">
    <form method="post">
      <div class="toggle-row">
        <label class="toggle"><input type="checkbox" name="reset_hour_enabled" value="1" <?= ($reset_hour_enabled??'1')==='1'?'checked':'' ?>><span class="slider"></span></label>
        <span class="toggle-label">ساعت ریست روزانه فعال باشد</span>
      </div>
      <div class="toggle-row">
        <label class="toggle"><input type="checkbox" name="reset_hour_home_enabled" value="1" <?= ($reset_hour_home_enabled??'1')==='1'?'checked':'' ?>><span class="slider"></span></label>
        <span class="toggle-label">نمایش ساعت ریست در هدر صفحه‌ی اصلی</span>
      </div>
      <div class="toggle-row">
        <label class="toggle"><input type="checkbox" name="reset_hour_chance_enabled" value="1" <?= ($reset_hour_chance_enabled??'1')==='1'?'checked':'' ?>><span class="slider"></span></label>
        <span class="toggle-label">نمایش ساعت ریست در هدر صفحه‌ی شانس</span>
      </div>
      <div class="fg"><label>ساعت بازنشانی (۰ تا ۲۳)</label>
        <div class="stat-row"><button type="button" class="btn-h minus" onclick="stepNum('resetHourNum',-1)">−</button><input type="text" class="stat-input fa-number" name="reset_hour" id="resetHourNum" inputmode="numeric" value="<?= (int)$reset_hour ?>"><button type="button" class="btn-h plus" onclick="stepNum('resetHourNum',1)">+</button></div>
      </div>
      <div class="fg"><button type="submit" name="save_settings" class="btn-p">ذخیره</button></div>
    </form>
  </div>

  <!-- ۱۳. تنظیمات وفاداری -->
  <div class="acc-hdr" onclick="toggleAcc(this)"><h3>۱۶. تنظیمات وفاداری</h3><span class="acc-icon">+</span></div>
  <div class="acc-body">
    <form method="post">
      <div class="toggle-row">
        <label class="toggle"><input type="checkbox" name="loyalty_enabled" value="1" <?= $loyalty_enabled==='1'?'checked':'' ?>><span class="slider"></span></label>
        <span class="toggle-label">کارت وفاداری فعال باشد</span>
      </div>
      <div class="toggle-row" style="margin-bottom:14px">
        <label class="toggle"><input type="checkbox" name="loyalty_online_order" value="1" <?= $loyalty_online_order==='1'?'checked':'' ?>><span class="slider"></span></label>
        <span class="toggle-label">خرید آنلاین هم به امتیاز وفاداری اضافه شود</span>
      </div>
      <div class="fg"><label>عنوان هدر صفحه وفاداری</label><input type="text" name="loyalty_page_title" value="<?= htmlspecialchars($loyalty_page_title??'', ENT_QUOTES, 'UTF-8') ?>"></div>
      <div class="fg"><label>زیرمتن هدر صفحه وفاداری</label><textarea name="loyalty_page_description" rows="3"><?= htmlspecialchars($loyalty_page_description??'', ENT_QUOTES, 'UTF-8') ?></textarea></div>
      <div class="fg"><label>متن زیر تاس در کارت وفاداری</label><textarea name="loyalty_card_hint" rows="3"><?= htmlspecialchars($loyalty_card_hint??'', ENT_QUOTES, 'UTF-8') ?></textarea></div>
      <div class="fg"><label>متن پیام تکمیل کارت</label><textarea name="loyalty_complete_msg" rows="3"><?= htmlspecialchars($loyalty_complete_msg??'', ENT_QUOTES, 'UTF-8') ?></textarea></div>
      <div class="fg"><label>هدف دریافت جایزه‌ی وفاداری از خرید (تعداد تاس)</label>
          <select name="loyalty_dice_count">
            <option value="1" <?= $loyalty_dice_count===1?'selected':'' ?>>۱ تاس (۶ خرید)</option>
            <option value="2" <?= $loyalty_dice_count===2?'selected':'' ?>>۲ تاس (۱۲ خرید)</option>
            <option value="3" <?= $loyalty_dice_count===3?'selected':'' ?>>۳ تاس (۱۸ خرید)</option>
            <option value="4" <?= $loyalty_dice_count===4?'selected':'' ?>>۴ تاس (۲۴ خرید)</option>
          </select>
      </div>
      <div class="fg"><button type="submit" name="save_loyalty_settings" class="btn-p">ذخیره</button></div>
      <p style="font-size:.72rem;color:var(--text3);line-height:1.6">با فعال بودن گزینه بالا، هر سفارش آنلاین ثبت‌شده به‌صورت خودکار ۱ امتیاز به کارت وفاداری همان کاربر اضافه می‌کند (معادل یک خرید حضوری). اگر غیرفعال باشد، فقط خریدهای حضوری که کارمند فروشگاه در پنل ثبت می‌کند امتیاز دارند.</p>
    </form>
  </div>

  <!-- ۱۴. پاپ‌آپ اطلاعیه -->
  <div class="acc-hdr" onclick="toggleAcc(this)"><h3>۱۷. پاپ‌آپ اطلاعیه سایت</h3><span class="acc-icon">+</span></div>
  <div class="acc-body">
    <form method="post">
      <div class="toggle-row">
        <label class="toggle"><input type="checkbox" name="announcement_enabled" value="1" <?= $announcement_enabled==='1'?'checked':'' ?>><span class="slider"></span></label>
        <span class="toggle-label">نمایش پاپ‌آپ اطلاعیه هنگام ورود کاربر به سایت</span>
      </div>
      <div class="fg">
        <label>متن اطلاعیه</label>
        <textarea name="announcement_message" rows="4" placeholder="متن اطلاعیه‌ای که به محض ورود کاربر به سایت نمایش داده می‌شود..."><?= htmlspecialchars($announcement_message, ENT_QUOTES, 'UTF-8') ?></textarea>
      </div>
      <p style="font-size:.72rem;color:var(--text3);line-height:1.6;margin-bottom:12px">این پاپ‌آپ تنها یک‌بار در هر جلسه مرورگر به کاربر نمایش داده می‌شود. اگر متن خالی باشد، پاپ‌آپی نمایش داده نخواهد شد.</p>
      <button type="submit" name="save_announcement_settings" class="btn-p">ذخیره تنظیمات اطلاعیه</button>
    </form>
  </div>

<div class="acc-hdr" onclick="toggleAcc(this)"><h3>۱۸. پشتیبان‌گیری و بازیابی سایت</h3><span class="acc-icon">+</span></div>
  <div class="acc-body">
    <div class="backup-intro">برای حفظ اطلاعات سایت، می‌توانی فقط از پایگاه داده، فقط از فایل‌ها یا از هر دو با هم پشتیبان‌گیری کنی و بعداً آن‌ها را بازیابی کنی. بازیابی، اطلاعات فعلی همان بخش را جایگزین می‌کند.</div>
    <div class="backup-intro">🆘 اگر پایگاه داده یا فایل‌های درگاه مدیریت به مشکل خوردند، از صفحه‌ی اضطراری استفاده کن: <a href="<?= htmlspecialchars($admin_prefix, ENT_QUOTES, 'UTF-8') ?>-emergency.php" target="_blank" rel="noopener" style="display:inline-block;background:var(--red);color:#fff;padding:3px 12px;border-radius:8px;text-decoration:none;margin:4px 0"><b dir="ltr"><?= htmlspecialchars($admin_prefix, ENT_QUOTES, 'UTF-8') ?>-emergency.php</b></a><br>گذرواژه‌ی این صفحه همان گذرواژه‌ی درگاه مدیریت است و با تغییر آن به‌روز می‌شود.</div>
    <?php if (($_GET['backup'] ?? '') === 'success'): ?><div class="backup-status ok">بازیابی با موفقیت انجام شد ✅</div><?php endif; ?>
    <?php if (!empty($_GET['backup_error'])): ?><div class="backup-status err"><?= htmlspecialchars($_GET['backup_error'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <div class="backup-group">
      <div class="backup-group-title">⬇️ پشتیبان‌گیری</div>
      <div class="backup-downloads">
        <a class="btn-p backup-download" href="backup.php?download=database&amp;csrf=<?= htmlspecialchars(sitak_csrf_token(), ENT_QUOTES, 'UTF-8') ?>"><span>🗄️ پایگاه داده</span><small>فایل SQL</small></a>
        <a class="btn-p backup-download" href="backup.php?download=files&amp;csrf=<?= htmlspecialchars(sitak_csrf_token(), ENT_QUOTES, 'UTF-8') ?>"><span>📁 فایل‌ها</span><small>فایل ZIP پشتیبان</small></a>
        <a class="btn-p backup-download" href="backup.php?download=full&amp;csrf=<?= htmlspecialchars(sitak_csrf_token(), ENT_QUOTES, 'UTF-8') ?>"><span>📦 کامل</span><small>پایگاه داده + فایل‌ها</small></a>
      </div>
    </div>
    <div class="backup-group">
      <div class="backup-group-title">⬆️ بازیابی</div>
      <div class="backup-restore-list">
        <div class="backup-restore-card"><span class="backup-restore-label">🗄️ بازیابی پایگاه داده</span><form method="post" action="backup.php" enctype="multipart/form-data" onsubmit="return adminConfirmForm(this,'پایگاه داده فعلی با این فایل جایگزین می‌شود. ادامه می‌دهید؟')"><input type="hidden" name="restore_type" value="database"><input type="hidden" name="return_to" value="<?= htmlspecialchars($admin_self, ENT_QUOTES, 'UTF-8') ?>"><input type="file" name="backup_file" accept=".sql" required><button class="btn-p" type="submit">انتخاب و بازیابی</button></form><div class="backup-restore-note">فرمت مجاز: SQL</div></div>
        <div class="backup-restore-card"><span class="backup-restore-label">📁 بازیابی فایل‌ها</span><form method="post" action="backup.php" enctype="multipart/form-data" onsubmit="return adminConfirmForm(this,'فایل‌های فعلی با این فایل پشتیبان جایگزین می‌شوند. ادامه می‌دهید؟')"><input type="hidden" name="restore_type" value="files"><input type="hidden" name="return_to" value="<?= htmlspecialchars($admin_self, ENT_QUOTES, 'UTF-8') ?>"><input type="file" name="backup_file" accept=".zip" required><button class="btn-p" type="submit">انتخاب و بازیابی</button></form><div class="backup-restore-note">فرمت مجاز: ZIP</div></div>
        <div class="backup-restore-card"><span class="backup-restore-label">📦 بازیابی کامل پایگاه داده و فایل‌ها</span><form method="post" action="backup.php" enctype="multipart/form-data" onsubmit="return adminConfirmForm(this,'پایگاه داده و فایل‌های فعلی با این فایل پشتیبان جایگزین می‌شوند. ادامه می‌دهید؟')"><input type="hidden" name="restore_type" value="full"><input type="hidden" name="return_to" value="<?= htmlspecialchars($admin_self, ENT_QUOTES, 'UTF-8') ?>"><input type="file" name="backup_file" accept=".zip" required><button class="btn-p" type="submit">انتخاب و بازیابی</button></form><div class="backup-restore-note">فایل ZIP پشتیبان کامل شامل پایگاه داده و فایل‌های سایت</div></div>
      </div>
    </div>
  </div>

  <div class="acc-hdr" onclick="toggleAcc(this)"><h3>۱۹. تغییر نشانی و گذرواژه‌ی درگاه مدیریت</h3><span class="acc-icon">+</span></div>
  <div class="acc-body">
    <?php if(!empty($pass_error)): ?><script>document.addEventListener('DOMContentLoaded',function(){adminAlert(<?= json_encode((string)$pass_error, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>,'⚠️ خطا')});</script><?php endif; ?>
    <?php if(!empty($pass_success)): ?><script>document.addEventListener('DOMContentLoaded',function(){adminAlert(<?= json_encode((string)$pass_success, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>,'✅ انجام شد')});</script><?php endif; ?>
    <form method="post" id="adminSecurityForm" style="max-width:100%">
      <div class="fg"><label>نشانی صفحه‌ی لاگین پنل ادمین<br><span style="font-weight:400">( فقط حروف کوچک a-z، اعداد، - و _ | ۳ تا ۴۰ کاراکتر.<br>خالی بگذارید تا آدرس پیش‌فرض admin.php فعال بماند. )</span></label><input type="text" class="fi" dir="ltr" style="text-align:left" name="admin_login_slug" id="adminLoginSlug" data-current="<?= htmlspecialchars($admin_login_slug ?? '', ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars($admin_login_slug ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="مثال: pamah (نتیجه: pamah.php)"></div>
      <?php if (!empty($admin_login_slug ?? '')): ?><p style="font-size:.72rem;color:var(--green);margin-bottom:12px">نشانی فعلی: <code><?= htmlspecialchars($admin_login_slug, ENT_QUOTES, 'UTF-8') ?>.php</code></p><?php endif; ?>
      <div class="fg"><label>گذرواژه فعلی</label><div style="position:relative;margin-bottom:12px"><input type="password" class="fi pw-field" name="current_pass" id="adminCurPass" placeholder="گذرواژه فعلی" style="margin-bottom:0"><button type="button" onclick="togglePw('adminCurPass',this)" aria-label="نمایش گذرواژه" style="position:absolute;left:0;top:0;height:100%;background:none;border:none;cursor:pointer;color:#86868b;padding:0 12px;display:flex;align-items:center;justify-content:center"></button></div></div>
      <div class="fg"><label>گذرواژه جدید<br><span style="font-weight:400">(حداقل ۸ کاراکتر و ترکیبی از کاراکترهای انگلیسی شامل حروف بزرگ و کوچک، اعداد و نمادها. )</span></label><div style="position:relative;margin-bottom:12px"><input type="password" class="fi pw-field" name="new_pass" id="adminNewPass" placeholder="گذرواژه جدید" minlength="8" style="margin-bottom:0"><button type="button" onclick="togglePw('adminNewPass',this)" aria-label="نمایش گذرواژه" style="position:absolute;left:0;top:0;height:100%;background:none;border:none;cursor:pointer;color:#86868b;padding:0 12px;display:flex;align-items:center;justify-content:center"></button></div></div>
      <div class="fg"><label>تکرار گذرواژه جدید</label><div style="position:relative;margin-bottom:12px"><input type="password" class="fi pw-field" name="confirm_pass" id="adminConfirmPass" placeholder="تکرار گذرواژه جدید" minlength="8" style="margin-bottom:0"><button type="button" onclick="togglePw('adminConfirmPass',this)" aria-label="نمایش گذرواژه" style="position:absolute;left:0;top:0;height:100%;background:none;border:none;cursor:pointer;color:#86868b;padding:0 12px;display:flex;align-items:center;justify-content:center"></button></div></div>
      <div id="secClientMsg" style="display:none;font-size:.78rem;margin-bottom:10px;line-height:1.7"></div>
      <button type="submit" name="save_admin_security" class="btn-p">ذخیره تغییرات نشانی و گذرواژه</button>
    </form>
  </div>



</div>

<div id="slugConfirmOv" class="modal-ov" role="dialog" aria-modal="true" onclick="if(event.target===this)closeSlugConfirm()">
  <div class="modal-body" style="padding:26px 22px">
    <h3 style="margin-bottom:10px;font-size:1rem">ذخیره نشانی جدید پنل ادمین؟</h3>
    <p style="font-size:.82rem;color:var(--text2);line-height:1.8">نشانی صفحه‌ی لاگین پنل ادمین به آدرس زیر تغییر خواهد کرد. لطفاً این نشانی را کپی و در جای امنی نگهداری کنید — آدرس قبلی پس از ثبت غیرفعال می‌شود.</p>
    <div style="display:flex;gap:8px;align-items:center;margin:16px 0">
      <button type="button" class="btn-sm" id="slugCopyBtn" onclick="copySlugUrl()" style="white-space:nowrap;min-width:78px">کپی 📋</button>
      <code id="slugConfirmUrl" dir="ltr" style="flex:1;text-align:left;word-break:break-all;background:var(--bg);border:1px solid var(--border);border-radius:var(--rss);padding:11px 12px;font-size:.78rem;color:var(--text);line-height:1.6"></code>
    </div>
    <p id="slugCopyOk" style="display:none;font-size:.72rem;color:var(--green);margin-bottom:12px">کپی شد ✓</p>
    <div style="display:flex;gap:10px">
      <button type="button" class="btn-p" style="flex:1" onclick="confirmSlugSubmit()">تأیید و ذخیره</button>
      <button type="button" onclick="closeSlugConfirm()" style="flex:1;margin-top:0;padding:14px;background:var(--card-el);;border:0;border-radius:var(--rss,10px);font-family:var(--font);font-size:.92rem;font-weight:700;color:var(--text2);cursor:pointer">لغو</button>
    </div>
  </div>
</div>

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
function saveScript(){
  var title=document.getElementById('scriptTitle').value.trim();
  var code=document.getElementById('scriptCode').value.trim();
  var sid=document.getElementById('scriptId').value||'0';
  if(!title){showToast('عنوان اسکریپت را وارد کنید');return}
  if(!code){showToast('کد اسکریپت را وارد کنید');return}
  var fd=new FormData();
  fd.append('save_script','1');
  fd.append('script_id',sid);
  fd.append('script_title',title);
  fd.append('script_code',code);
  fd.append('csrf_token','<?= htmlspecialchars(sitak_csrf_token(), ENT_QUOTES, 'UTF-8') ?>');
  fetch('<?= $admin_self ?>',{method:'POST',body:fd}).then(function(){location.reload()});
}
function editScript(id){
  fetch('<?= $admin_self ?>?script_json='+id).then(function(r){return r.json()}).then(function(d){
    if(!d.success){showToast(d.message||'خطا');return}
    document.getElementById('scriptTitle').value=d.script.title;
    document.getElementById('scriptCode').value=d.script.code;
    document.getElementById('scriptId').value=d.script.id;
    document.getElementById('scriptCancelEdit').style.display='';
    document.getElementById('scriptTitle').scrollIntoView({behavior:'smooth',block:'center'});
  });
}
function cancelScriptEdit(){
  document.getElementById('scriptTitle').value='';
  document.getElementById('scriptCode').value='';
  document.getElementById('scriptId').value='0';
  document.getElementById('scriptCancelEdit').style.display='none';
}
function secShowErr(msg){
  var el=document.getElementById('secClientMsg');
  el.style.display='';el.style.color='var(--red)';el.textContent='❌ '+msg;
}
var PW_SVG_EYE='<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>';
var PW_SVG_OFF='<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 19c-6.5 0-10-7-10-7a18.5 18.5 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c6.5 0 10 7 10 7a18.5 18.5 0 0 1-2.16 3.19M1 1l22 22"/></svg>';
document.querySelectorAll('button[onclick^="togglePw"]').forEach(function(b){b.innerHTML=PW_SVG_EYE});
function togglePw(id,btn){
  var inp=document.getElementById(id);
  var isTxt=inp.type==='text';
  inp.type=isTxt?'password':'text';
  btn.innerHTML=isTxt?PW_SVG_EYE:PW_SVG_OFF;
  btn.setAttribute('aria-label',isTxt?'نمایش گذرواژه':'مخفی کردن گذرواژه');
}
var slugFormPending=false;
document.getElementById('adminSecurityForm').addEventListener('submit',function(e){
  if(slugFormPending)return;
  var slug=document.getElementById('adminLoginSlug').value.trim();
  var cur=document.getElementById('adminCurPass').value;
  var np=document.getElementById('adminNewPass').value;
  var cp=document.getElementById('adminConfirmPass').value;
  var errs=[];
  if(slug!=='' && !/^[a-z0-9_-]{3,40}$/.test(slug)){
    errs.push('نشانی لاگین نامعتبر است: فقط حروف کوچک a-z، اعداد، - و _ و بین ۳ تا ۴۰ کاراکتر باشد.');
  }
  var anyPass = cur!=='' || np!=='' || cp!=='';
  if(anyPass){
    if(cur==='') errs.push('«گذرواژه فعلی» وارد نشده است.');
    if(np==='') errs.push('«گذرواژه جدید» وارد نشده است.');
    if(cp==='') errs.push('«تکرار گذرواژه جدید» وارد نشده است.');
    if(np!=='' && np.length<8) errs.push('گذرواژه جدید باید حداقل ۸ کاراکتر باشد.');
    if(np!==''){
      if(!/[0-9]/.test(np)) errs.push('گذرواژه جدید فاقد رقم (number) است.');
      if(!/[a-z]/.test(np)) errs.push('گذرواژه جدید فاقد حرف کوچک انگلیسی (lowercase) است.');
      if(!/[A-Z]/.test(np)) errs.push('گذرواژه جدید فاقد حرف بزرگ انگلیسی (uppercase) است.');
      if(!/[^A-Za-z0-9]/.test(np)) errs.push('گذرواژه جدید فاقد نماد (symbol مثل !@#$) است.');
      if(/[^ -~]/.test(np)) errs.push('گذرواژه جدید فقط باید شامل کاراکترهای انگلیسی باشد.');
    }
    if(np!=='' && cp!=='' && np!==cp) errs.push('مقدار «گذرواژه جدید» و «تکرار گذرواژه جدید» یکسان نیستند.');
  }
  if(errs.length){
    e.preventDefault();
    secShowErr(errs.join(' | '));
    return;
  }
  document.getElementById('secClientMsg').style.display='none';
  var newSlug=slug.toLowerCase();
  var curSlug=document.getElementById('adminLoginSlug').getAttribute('data-current')||'';
  if(newSlug!==curSlug){
    e.preventDefault();
    var baseUrl=location.origin+location.pathname.substring(0,location.pathname.lastIndexOf('/')+1);
    var fileName=(newSlug===''?'admin':newSlug)+'.php';
    showSlugConfirm(baseUrl+fileName);
  }
});
function showSlugConfirm(url){
  document.getElementById('slugConfirmUrl').textContent=url;
  document.getElementById('slugCopyOk').style.display='none';
  var b=document.getElementById('slugCopyBtn');
  if(b)b.textContent='کپی 📋';
  document.getElementById('slugConfirmOv').classList.add('open');
}
function closeSlugConfirm(){
  document.getElementById('slugConfirmOv').classList.remove('open');
}
function copySlugUrl(){
  var t=document.getElementById('slugConfirmUrl').textContent;
  function done(){
    document.getElementById('slugCopyOk').style.display='';
    var b=document.getElementById('slugCopyBtn');
    if(b)b.textContent='کپی شد ✓';
  }
  if(navigator.clipboard&&navigator.clipboard.writeText){
    navigator.clipboard.writeText(t).then(done,function(){fallbackCopy(t,done)});
  }else{
    fallbackCopy(t,done);
  }
}
function fallbackCopy(t,done){
  var ta=document.createElement('textarea');
  ta.value=t;ta.style.position='fixed';ta.style.opacity='0';
  document.body.appendChild(ta);ta.select();
  try{document.execCommand('copy');done();}catch(err){}
  document.body.removeChild(ta);
}
function confirmSlugSubmit(){
  document.getElementById('slugConfirmOv').classList.remove('open');
  slugFormPending=true;
  var f=document.getElementById('adminSecurityForm');
  var hidden=document.createElement('input');
  hidden.type='hidden'; hidden.name='save_admin_security'; hidden.value='1';
  f.appendChild(hidden);
  f.submit();
}
document.addEventListener('DOMContentLoaded',function(){
  var fa='۰۱۲۳۴۵۶۷۸۹';
  document.querySelectorAll('.fa-number').forEach(function(el){
    el.value=String(el.value).replace(/\d/g,function(d){return fa[d]});
    el.addEventListener('input',function(){
      this.value=this.value.replace(/[0-9۰-۹]/g,function(d){
        var i='۰۱۲۳۴۵۶۷۸۹'.indexOf(d); return i>=0?fa[i]:d;
      });
    });
    var form=el.form;
    if(form&&!form._faNumberBound){
      form._faNumberBound=true;
      form.addEventListener('submit',function(){
        form.querySelectorAll('.fa-number').forEach(function(field){
          field.value=field.value.replace(/[۰-۹]/g,function(d){return String('۰۱۲۳۴۵۶۷۸۹'.indexOf(d))});
        });
      });
    }
  });
});
</script>
<?php
admin_footer_nav('settings');
?>
<?php include 'admin_ajax_save.php'; ?>
</body>
</html>
