# سای‌تک | SiTak

پلتفرم مدرن، سبک و جامع فروشگاهی، محتوایی و تعاملی (گیمیفیکیشن) بر پایه PHP و MySQL با رابط کاربری App-Like و بدون وابستگی به فریم‌ورک‌های سنگین.

> 🤖 **توسعه‌یافته با هوش مصنوعی:** تمامی بخش‌های این سامانه اعم از منطق بک‌اند، رابط کاربری، موتورهای صوتی/تصویری و لایه‌های امنیتی با همراهی و کمک مدل‌های هوش مصنوعی (AI) طراحی و پیاده‌سازی شده است.

---

## فهرست مطالب
- [معرفی کلی](#معرفی-کلی)
- [امکانات و ویژگی‌ها](#امکانات-و-ویژگیها)
  - [۱. فروشگاه و سبد خرید](#۱-فروشگاه-و-سبد-خرید)
  - [۲. روش‌ها و درگاه‌های پرداخت](#۲-روشها-و-درگاههای-پرداخت)
  - [۳. سیستم‌های تعاملی و سرگرمی (گیمیفیکیشن)](#۳-سیستمهای-تعاملی-و-سرگرمی-گیمیفیکیشن)
  - [۴. وبلاگ و سیستم دیدگاه‌ها](#۴-وبلاگ-و-سیستم-دیدگاهها)
  - [۵. پیشخوان مدیریت، امنیت و پشتیبان‌گیری](#۵-پیشخوان-مدیریت-امنیت-و-پشتیبانگیری)
  - [۶. پیامک، احراز هویت و سامانه معرف](#۶-پیامک-احراز-هویت-و-سامانه-معرف)
- [پیش‌نیازها و راهنمای نصب](#پیشنیازها-و-راهنمای-نصب)
- [English Documentation](#english-documentation)

---

## معرفی کلی

**سای‌تک (SiTak)** یک وب‌اپلیکیشن مستقل و سریع است که تنها بر پایه **Pure PHP**، **MySQL/MariaDB** و جاوااسکریپت خام توسعه یافته و هیچ‌گونه وابستگی به پکیج‌های حجیم یا فریم‌ورک‌های سنگین ندارد.

رابط کاربری این پلتفرم به‌صورت **Mobile-First** و مشابه با اپلیکیشن‌های بومی تلفن همراه (Native App-Like) طراحی شده و امکاناتی نظیر منوی ناوبری چسبان پایین صفحه، پاپ‌آپ‌های کشویی مدرن (Bottom Sheets)، پشتیبانی خودکار از حالت‌های تم تیره/روشن/سیستم و قلم خوانای وزیرمتن را به همراه دارد.

---

### 🛠️ فناوری‌ها و زیرساخت‌های استفاده‌شده

#### هسته و پایگاه‌داده (Backend & Database)
![PHP](https://img.shields.io/badge/PHP_8+-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![MariaDB](https://img.shields.io/badge/MariaDB-003545?style=for-the-badge&logo=mariadb&logoColor=white)

#### رابط کاربری و وب (Frontend & Web APIs)
![JavaScript](https://img.shields.io/badge/Vanilla_JS-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)
![HTML5](https://img.shields.io/badge/HTML5-E34F26?style=for-the-badge&logo=html5&logoColor=white)
![CSS3](https://img.shields.io/badge/CSS3_Modern-1572B6?style=for-the-badge&logo=css3&logoColor=white)
![PWA](https://img.shields.io/badge/PWA_Ready-5A0FC8?style=for-the-badge&logo=pwa&logoColor=white)
![Web Audio API](https://img.shields.io/badge/Web_Audio_API-333333?style=for-the-badge&logo=w3c&logoColor=white)
![Canvas API](https://img.shields.io/badge/HTML5_Canvas-E34F26?style=for-the-badge&logo=html5&logoColor=white)

#### وب‌سرور و استقرار (Servers & Infrastructure)
![Apache](https://img.shields.io/badge/Apache_HTTP_Server-D22128?style=for-the-badge&logo=apache&logoColor=white)
![Nginx](https://img.shields.io/badge/Nginx-009639?style=for-the-badge&logo=nginx&logoColor=white)
![Linux](https://img.shields.io/badge/Linux_Compatible-FCC624?style=for-the-badge&logo=linux&logoColor=black)

#### سرویس‌ها و وب‌سرویس‌ها (Services & APIs)
![ZarinPal](https://img.shields.io/badge/ZarinPal_API_v4-FFD600?style=for-the-badge&logoColor=black)
![SMS.ir](https://img.shields.io/badge/SMS.ir_UltraFast_OTP-006699?style=for-the-badge&logoColor=white)
![Map.ir](https://img.shields.io/badge/Map.ir_SDK-2E7D32?style=for-the-badge&logoColor=white)
![Web Push](https://img.shields.io/badge/Web_Push_(VAPID)-FF5722?style=for-the-badge&logo=w3c&logoColor=white)

#### ابزارها و توسعه (Tools & AI)
![Developed With AI](https://img.shields.io/badge/Developed_With-AI_Assistance-8A2BE2?style=for-the-badge&logo=openai&logoColor=white)
![Git](https://img.shields.io/badge/Git-F05032?style=for-the-badge&logo=git&logoColor=white)
![GitHub](https://img.shields.io/badge/GitHub-181717?style=for-the-badge&logo=github&logoColor=white)

---

## امکانات و ویژگی‌ها

### ۱. فروشگاه و سبد خرید
- **کاتالوگ محصولات:** دسته‌بندی موضوعی، برچسب‌ها، مدیریت تصاویر و ویرایشگر متن ساختاریافته (Rich-Text Editor).
- **انبارداری و کنترل موجودی:** کسر خودکار موجودی کالا پس از ثبت یا تأیید سفارش و برچسب‌های هشدار کمبود موجودی بر اساس حد آستانه تنظیمی مدیر.
- **محصولات تحویل در محل (On-site):** تفکیک اقلام محلی با امکان محدودسازی جغرافیایی به استان و شهر خاص، روزهای مجاز کاری و ساعت‌های فروش در شیفت‌های صبح و عصر بر مبنای ساعت رسمی تهران.
- **سیستم کرایه و قوانین حمل‌ونقل:** تعریف هزینه ارسال عادی و ارسال محلی، تعیین حداقل سبد خرید جهت فعال شدن ارسال و تعیین سقف خرید جهت رایگان شدن هزینه ارسال به همراه اعلان‌های تشویقی.
- **کدهای تخفیف چندمنظوره:** امکان ثبت تخفیف‌های درصدی یا مبلغی ثابت، تعیین سقف تخفیف، محدودیت حداقل مبلغ سبد خرید، تاریخ انقضا و فعال‌سازی خودکار از طریق پیوند اختصاصی (`?coupon=CODE`).
- **رسید تصویری مشتری:** امکان تولید و دانلود خودکار فاکتور رسمی گرافیکی شفاف با جزئیات کامل و موقعیت مکانی تحویل در قالب فایل تصویری PNG از طریق HTML5 Canvas.

### ۲. روش‌ها و درگاه‌های پرداخت
- **درگاه آنلاین زرین‌پال (ZarinPal v4):** پیاده‌سازی سبک RESTful با قابلیت انتخاب و سوئیچ بین حالت آزمایشی (Sandbox) و محیط زنده.
- **پرداخت کارت‌به‌کارت هوشمند:** نمایش اطلاعات حساب (شماره کارت، شماره شبا و نام صاحب حساب) همراه با دکمه کپی سریع، تایمر معکوس رزرو اقلام سبد خرید، فرم اختصاصی ثبت مشخصات تراکنش توسط خریدار و سیستم بررسی و تأیید/رد توسط مدیر.
- **پرداخت پس از هماهنگی:** گزینه ثبت سفارش برای تسویه حساب دستی یا تحویل حضوری.

### ۳. سیستم‌های تعاملی و سرگرمی (گیمیفیکیشن)
- **تاس شانس روزانه:** شبیه‌ساز سه‌بعدی انداختن جفت‌تاس با طراحی اختصاصی صدا از طریق **Web Audio API** (بدون نیاز به دانلود یا لود فایل‌های صوتی خارجی).
- **گردونه شانس:** رندر گرافیکی گردونه با گرادیان‌های مخروطی داینامیک، محاسبه دقیق احتمالات آماری بر اساس جوایز تعریف‌شده و قاچ پوچ خودکار.
- **فال حافظ تعاملی:** انیمیشن واقع‌گرایانه باز شدن سه‌بعدی کتاب دیوان حافظ، افکت صوتی ورق خوردن کاغذ، همراه با متن کامل ابیات و تفاسیر کوتاه.
- **کارت وفاداری دیجیتال:** کارت مراجعات حضوری یا سفارش‌های آنلاین با تاس‌های امتیازی جهت اهدای جایزه ویژه پس از تکمیل دوره‌های خرید.
- **سیستم پایش برندگان:** جدول زنده برندگان جوایز در سایت به همراه موتور تولید خودکار برندگان ساختگی جهت افزایش تعامل و اعتماد کاربران.

### ۴. وبلاگ و سیستم دیدگاه‌ها
- **مدیریت وبلاگ:** انتشار مقالات، نامک سئو (Slug)، تصویر شاخص، برچسب‌ها، شمارنده بازدید و زمان مطالعه.
- **سیستم بازخورد و دیدگاه‌ها:** امتیازدهی ستاره‌ای ۵ مرحله‌ای، ثبت نظر برای محصولات و مطالب، کپچای ضد اسپم ریاضی، ثبت واکنش‌های لایک و دیس‌لایک و صف تأیید پیش از انتشار.

### ۵. پیشخوان مدیریت، امنیت و پشتیبان‌گیری
- **تغییر آدرس ورود ادمین:** قابلیت تغییر نشانی ورود پیش‌فرض از `admin.php` به اسلاگ دلخواه جهت مصون ماندن از حملات نفوذ ربات‌ها.
- **صفحه بازیابی اضطراری (Emergency Recovery):** تولید خودکار اسکریپت اضطراری مستقل از دیتابیس با رمز عبور هش‌شده داخلی جهت نجات و بازیابی فایل‌ها و دیتابیس در شرایط بحرانی.
- **اعلان‌های زنده Web Push:** ارسال مستقیم پوش‌نوتیفیکیشن مرورگر تحت استاندارد VAPID (RFC 8291 و RFC 8292) بدون وابستگی به سرویس‌های تجاری واسط همراه با هشدار صوتی در پنل.
- **فایروال نرم‌افزاری سبک:** تله نامرئی ضدربات (Honeypot)، محدودسازی نرخ درخواست‌ها (Rate Limiting) و مانیتورینگ رویدادها با هش IP.
- **پشتیبان‌گیری استریمینگ:** دانلود و بازیابی مستقیم نسخه‌های پشتیبان پایگاه‌داده (SQL) و فایل‌ها (ZIP) بدون پر شدن رم سرور.
- **داشبورد تحلیلی و اکسپورت:** بررسی شاخص‌های کلیدی عملکرد، نرخ تبدیل سفارش‌ها، پرفروش‌ترین اقلام و امکان خروجی گرفتن CSV.

### ۶. پیامک، احراز هویت و سامانه معرف
- **وب‌سرویس پیامکی SMS.ir:** ارسال کدهای اعتبارسنجی با سرویس سریع Verify و الگوهای پیش‌فرض خدماتی.
- **ورود دومنظوره:** امکان ورود با رمز یکبار مصرف پیامکی (OTP) یا تنظیم گذرواژه عددی با مکانیزم‌های امنیتی ضد حدس رمز.
- **سامانه دعوت از دوستان (Referral):** تخصیص شانس‌های تاس اضافی به معرفی‌کننده و عضو جدید پس از ثبت موفق.

---

## پیش‌نیازها و راهنمای نصب

### نیازمندی‌های سرور
- وب‌سرور: **Apache** (با فعال بودن ماژول `mod_rewrite`) یا **Nginx**
- نسخه **PHP 8.0** یا بالاتر
- دیتابیس **MySQL 5.7+** یا **MariaDB 10.3+**
- اکستنشن‌های ضروری PHP:
  - `mysqli`
  - `curl`
  - `openssl`
  - `gd`
  - `fileinfo`
  - `zip`
  - `mbstring`

### مراحل نصب گام‌به‌گام

۱. **کلون کردن یا آپلود سورس پروژه:**
   کدها را در دایرکتوری وب‌سرور خود قرار دهید:
   ```bash
   git clone https://github.com/your-username/sitak.git
   cd sitak
   ```

۲. **بررسی مجوزهای دسترسی پوشه‌ها:**
   اطمینان حاصل کنید وب‌سرور دسترسی نوشتن روی ریشه و پوشه آپلودها را دارد:
   ```bash
   chmod -R 755 .
   chmod -R 775 uploads/
   ```

۳. **اجرای نصب‌کننده تحت وب:**
   دامنه یا مسیر نصب پروژه را در مرورگر خود باز کنید. سیستم به‌صورت خودکار شما را به صفحه نصب هدایت می‌کند:
   ```text
   http://your-domain.com/install.php
   ```

۴. **تکمیل اطلاعات راه‌اندازی:**
   - مشخصات اتصال به پایگاه‌داده (نام میزبان، نام کاربری، گذرواژه و نام دیتابیس).
   - تعیین نام اولیه برند و فروشگاه.
   - تعیین گذرواژه قوی برای مدیر ارشد (شامل حروف بزرگ، کوچک، عدد و نماد).

۵. **اتمام نصب:**
   پس از اتمام مراحل، فایل امنیتی `install.lock` در ریشه پروژه ایجاد شده و دسترسی به فایل نصب مسدود می‌گردد. اکنون می‌توانید با مراجعه به `admin.php` به پیشخوان مدیریت وارد شوید.

---

<div id="english-documentation"></div>

# SiTak (English Documentation)

A modern, lightweight, standalone e-commerce, content management, and gamification platform crafted with native PHP and pure web technologies.

> 🤖 **Developed with AI Assistance:** This platform and all its core modules (backend workflows, frontend UX, procedural audio/visual interactive engines, and security subsystems) were engineered with the aid of Artificial Intelligence (AI) models.

---

## Architectural Highlights

- **Zero Heavy Dependencies:** Built on vanilla PHP 8+ and modern vanilla JavaScript without framework overhead.
- **Mobile-First App Experience:** Smooth bottom sheets, responsive layouts, unified mobile navigation, and native app aesthetics.
- **Autonomous Theme Management:** Integrated Dark, Light, and System-preferred color palette switching.
- **Privacy & Security First:** Native firewall, Honeypot detection, Rate Limiting, and protected admin endpoints.

---

### 🛠️ Tech Stack & Infrastructure

#### Backend & Storage
![PHP](https://img.shields.io/badge/PHP_8+-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![MariaDB](https://img.shields.io/badge/MariaDB-003545?style=for-the-badge&logo=mariadb&logoColor=white)

#### Frontend & Browser APIs
![JavaScript](https://img.shields.io/badge/Vanilla_JS-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)
![HTML5](https://img.shields.io/badge/HTML5-E34F26?style=for-the-badge&logo=html5&logoColor=white)
![CSS3](https://img.shields.io/badge/CSS3_Modern-1572B6?style=for-the-badge&logo=css3&logoColor=white)
![PWA](https://img.shields.io/badge/PWA_Ready-5A0FC8?style=for-the-badge&logo=pwa&logoColor=white)
![Web Audio API](https://img.shields.io/badge/Web_Audio_API-333333?style=for-the-badge&logo=w3c&logoColor=white)
![Canvas API](https://img.shields.io/badge/HTML5_Canvas-E34F26?style=for-the-badge&logo=html5&logoColor=white)

#### Web Servers & Deployment
![Apache](https://img.shields.io/badge/Apache_HTTP_Server-D22128?style=for-the-badge&logo=apache&logoColor=white)
![Nginx](https://img.shields.io/badge/Nginx-009639?style=for-the-badge&logo=nginx&logoColor=white)
![Linux](https://img.shields.io/badge/Linux_Compatible-FCC624?style=for-the-badge&logo=linux&logoColor=black)

#### Integrated Services & APIs
![ZarinPal](https://img.shields.io/badge/ZarinPal_API_v4-FFD600?style=for-the-badge&logoColor=black)
![SMS.ir](https://img.shields.io/badge/SMS.ir_UltraFast_OTP-006699?style=for-the-badge&logoColor=white)
![Map.ir](https://img.shields.io/badge/Map.ir_SDK-2E7D32?style=for-the-badge&logoColor=white)
![Web Push](https://img.shields.io/badge/Web_Push_(VAPID)-FF5722?style=for-the-badge&logo=w3c&logoColor=white)

#### Tooling & AI
![Developed With AI](https://img.shields.io/badge/Developed_With-AI_Assistance-8A2BE2?style=for-the-badge&logo=openai&logoColor=white)
![Git](https://img.shields.io/badge/Git-F05032?style=for-the-badge&logo=git&logoColor=white)
![GitHub](https://img.shields.io/badge/GitHub-181717?style=for-the-badge&logo=github&logoColor=white)

---

## Feature Matrix

### 1. Store, Inventory & Smart Logistics
- **Catalog Management:** Products organized with categories, tags, image assets, and a clean built-in Rich Text Editor.
- **Stock Tracking:** Automated inventory deductions upon order payment, low-stock visual badges, and timed reservations.
- **Local Delivery Engine:** Schedule products for on-site delivery based on designated provinces, cities, business hours (morning/evening shifts), and working weekdays.
- **Tiered Shipping Rules:** Configuration for regional vs. national delivery fees, free-shipping spend goals with proactive toast incentives, and minimum checkout thresholds.
- **Discount Code System:** Percentage or fixed discount coupons with usage quotas, minimum subtotal requirements, expiry dates, and instant activation via query parameters (`?coupon=CODE`).
- **HTML5 Canvas Receipts:** Downloadable branded graphic receipt with items breakdown and embedded location mapping.

### 2. Payment Processing
- **ZarinPal API v4:** Native integration supporting seamless switching between Sandbox and Live production environments.
- **Card-to-Card Payment Flow:** Interactive account details presentation, integrated Jalali calendar/clock picker for transaction reporting, and inventory reservation timers.
- **Pay After Confirmation:** Manual processing pathway for orders settled outside the gateway.

### 3. Gamification Suite
- **Daily Dice Roller:** 3D CSS dice animation featuring procedural audio generated on-the-fly via the browser's **Web Audio API** (no static audio files required).
- **Interactive Wheel:** Mathematically distributed segments rendered with dynamic conic gradients and custom win probabilities.
- **Hafez Divination:** 3D perspective book-opening simulation accompanied by simulated paper-turn acoustics, poem verses, and interpretations.
- **Loyalty Stamp Cards:** Real-visit and online order rewards system based on multi-tier dice cards.
- **Simulated Winners Feed:** Real winner records mixed with an adjustable automated bot feed to maintain engagement.

### 4. Blog & Engagement
- **Publishing Engine:** Article management with custom slugs, cover pictures, category trees, tag indexing, and view counters.
- **User Feedback & Ratings:** Five-star customer rating system, moderation queues, spam-prevention math captchas, and Like/Dislike reaction toggles.

### 5. Control Center & System Security
- **Obfuscated Admin Endpoint:** Changeable admin entry slug (`admin_login_slug`) to prevent default URL brute-force attempts.
- **Independent Emergency Suite (`emergency_core.php`):** Generates `{slug}-emergency.php` with self-contained authentication to restore file/database backups even when the database is offline.
- **Direct Web Push Notifications:** Browser Push implementation conforming to RFC 8291 and RFC 8292 (VAPID) with real-time in-panel sound alerts.
- **Built-in Application Firewall:** Honeypot traps on forms, IP-hashed rate-limiting for auth requests, and non-invasive audit logs.
- **Streaming Backups:** Memory-safe database dumps (SQL) and compressed archive creation (ZIP) supporting instant restore.
- **Business Analytics:** Sales reporting, conversion rates, and period-specific CSV data exports.

### 6. SMS Infrastructure & Authentication
- **SMS.ir Web Service:** Fast Verify OTP pattern messaging and operational transactional notices.
- **Dual Login Systems:** Mobile authentication via SMS OTP verification or permanent numeric passwords.
- **Referral Mechanics:** Shared dice reward system for referrers and newly invited users.

---

## Installation & Server Setup

### Prerequisites
- Web Server: **Apache** (`mod_rewrite` enabled) or **Nginx**
- **PHP:** Version 8.0 or higher
- **Database:** MySQL 5.7+ or MariaDB 10.3+
- PHP Extensions: `mysqli`, `curl`, `openssl`, `gd`, `fileinfo`, `zip`, `mbstring`

### Quick Deployment

1. **Clone the code:**
   ```bash
   git clone https://github.com/your-username/sitak.git
   cd sitak
   ```

2. **Set Write Permissions:**
   Ensure your web server has write access to the root and `uploads/` directory:
   ```bash
   chmod -R 755 .
   chmod -R 775 uploads/
   ```

3. **Run the Installer:**
   Open the application root in your browser. You will be automatically redirected to the installer:
   ```text
   http://localhost/sitak/install.php
   ```

4. **Complete the Setup Wizard:**
   - Provide database credentials.
   - Choose your brand name.
   - Set a strong administrator password.

5. **Lock Down:**
   The installation wizard creates an `install.lock` file upon completion to prevent replay attacks. Log into your dashboard using `admin.php`.

---

## License

This software is released under the open-source license detailed in the repository.
