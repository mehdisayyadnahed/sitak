<?php
// ajax.php
ob_start();
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT & ~E_WARNING);
ini_set('display_errors', '0');
require_once 'config.php';
require_once 'zarinpal.php';
require_once 'smsir.php';
require_once 'webpush.php';
header('Content-Type: application/json; charset=utf-8');

// لغو خودکار سفارش‌های کارت‌به‌کارتی که مهلت ثبت اطلاعات واریزشان تمام شده (و آزادسازی موجودی)
try {

sitak_expire_card_orders($conn);

$action = $_GET['action'] ?? $_POST['action'] ?? '';
// ── محافظ CSRF برای همه‌ی درخواست‌های POST (توکن در head صفحه‌ها توسط sitak_head_meta قرار می‌گیرد) ──
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !sitak_csrf_valid((string)($_POST['csrf_token'] ?? ''))) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'درخواست نامعتبر است. صفحه را تازه کنید و دوباره تلاش کنید.'], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($action === 'logout_user') { sitak_remember_revoke($conn); unset($_SESSION['sitak_user']); echo json_encode(['success'=>true]); exit; }
if ($action === 'get_comment_captcha') {
    $a=rand(1,10); $b=rand(1,10); $_SESSION['comment_captcha_answer']=$a+$b;
    $q=(string)($a.' + '.$b.' = ?'); $svg='<svg xmlns="http://www.w3.org/2000/svg" width=140 height=42><rect width="100%" height="100%" rx="8" fill="#f5d076"/><text x="70" y="27" text-anchor="middle" font-family="Arial" font-size="18" fill="#6b5314">'.htmlspecialchars($q,ENT_QUOTES,'UTF-8').'</text></svg>'; echo json_encode(['success'=>true,'question'=>pn($q),'image'=>'data:image/svg+xml;base64,'.base64_encode($svg)]); exit;
}
if ($action === 'vote_comment') {
    $phone=($_SESSION['sitak_user'] ?? '');
    $type=$_POST['target_type'] ?? '';
    $target=(int)($_POST['target_id'] ?? 0);
    $vote=$_POST['vote_type'] ?? '';
    $reaction_enabled=getSetting($conn,$type==='product'?'comments_reactions_products_enabled':'comments_reactions_posts_enabled','1') === '1';
    $dislike_enabled=getSetting($conn,$type==='product'?'comments_dislike_products_enabled':'comments_dislike_posts_enabled','1') === '1';
    if (!preg_match('/^09[0-9]{9}$/',$phone) || !in_array($type,['product','post'],true) || $target<1 || !in_array($vote,['like','dislike'],true) || !$reaction_enabled || ($vote==='dislike' && !$dislike_enabled)) {
        echo json_encode(['success'=>false,'message'=>'این واکنش در حال حاضر فعال نیست.']); exit;
    }
    $table=$type==='product'?'product_comments':'post_comments'; $col=$type==='product'?'product_id':'post_id';
    $check=$conn->prepare("SELECT id FROM `$table` WHERE id=? AND status='approved' LIMIT 1"); $check->bind_param('i',$target); $check->execute();
    if (!$check->get_result()->num_rows) { echo json_encode(['success'=>false,'message'=>'دیدگاه پیدا نشد.']); exit; }
    $current=$conn->prepare("SELECT vote_type FROM comment_votes WHERE comment_type=? AND comment_id=? AND user_phone=? LIMIT 1"); $current->bind_param('sis',$type,$target,$phone); $current->execute(); $existing=$current->get_result()->fetch_assoc();
    $removed=false;
    if ($existing && $existing['vote_type'] === $vote) {
        $del=$conn->prepare("DELETE FROM comment_votes WHERE comment_type=? AND comment_id=? AND user_phone=?"); $del->bind_param('sis',$type,$target,$phone); $del->execute(); $removed=true;
    } else {
        $st=$conn->prepare("INSERT INTO comment_votes (comment_type,comment_id,user_phone,vote_type) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE vote_type=VALUES(vote_type), created_at=CURRENT_TIMESTAMP");
        $st->bind_param('siss',$type,$target,$phone,$vote); $st->execute();
    }
    $counts=$conn->prepare("SELECT SUM(vote_type='like') likes,SUM(vote_type='dislike') dislikes FROM comment_votes WHERE comment_type=? AND comment_id=?"); $counts->bind_param('si',$type,$target); $counts->execute(); $row=$counts->get_result()->fetch_assoc();
    echo json_encode(['success'=>true,'likes'=>(int)($row['likes']??0),'dislikes'=>(int)($row['dislikes']??0),'vote'=>$removed?'':$vote,'removed'=>$removed]); exit;
}

if ($action === 'add_comment') {
    $phone=($_SESSION['sitak_user'] ?? ''); $type=$_POST['target_type'] ?? ''; $target=(int)($_POST['target_id'] ?? 0);
    $rating=(int)($_POST['rating'] ?? 0); $body=trim($_POST['body'] ?? ''); $captcha=(int)($_POST['captcha'] ?? -1);
    $enabled=getSetting($conn,$type==='product'?'comments_products_enabled':'comments_posts_enabled','1');
    $submit_enabled=getSetting($conn,$type==='product'?'comments_submit_products_enabled':'comments_submit_posts_enabled','1');
    if(!preg_match('/^09[0-9]{9}$/',$phone)||!in_array($type,['product','post'],true)||$target<1||$enabled!=='1') { echo json_encode(['success'=>false,'message'=>'ثبت دیدگاه در حال حاضر امکان‌پذیر نیست.']); exit; }
    if($submit_enabled!=='1') { echo json_encode(['success'=>false,'message'=>'در حال حاضر امکان ثبت دیدگاه وجود ندارد.']); exit; }
    $user_check=$conn->prepare("SELECT comment_blocked FROM users WHERE phone=? LIMIT 1"); $user_check->bind_param('s',$phone); $user_check->execute();
    if((int)($user_check->get_result()->fetch_assoc()['comment_blocked']??0)===1) { echo json_encode(['success'=>false,'message'=>'در حال حاضر امکان ثبت دیدگاه وجود ندارد.']); exit; }
    if($rating<1||$rating>5){echo json_encode(['success'=>false,'message'=>'برای ثبت دیدگاه ابتدا ستاره بده.']);exit;}
    if(!isset($_SESSION['comment_captcha_answer'])||$captcha!==(int)$_SESSION['comment_captcha_answer']) { echo json_encode(['success'=>false,'message'=>'پاسخ کپچا نادرست است.']); exit; }
    unset($_SESSION['comment_captcha_answer']);
    if($rating<1||$body===''||mb_strlen($body,'UTF-8')>2000){echo json_encode(['success'=>false,'message'=>'امتیاز و متن دیدگاه را کامل وارد کنید.']);exit;}
    $table=$type==='product'?'product_comments':'post_comments'; $col=$type==='product'?'product_id':'post_id'; $status=getSetting($conn,'comments_require_approval','1')==='1'?'pending':'approved';
    $st=$conn->prepare("INSERT INTO `$table` (`$col`,user_phone,rating,body,status) VALUES (?,?,?,?,?)"); $st->bind_param('isiss',$target,$phone,$rating,$body,$status); $st->execute();
    $html='';
    if($status==='approved'){
        $cid=(int)$conn->insert_id;
        $rx=getSetting($conn,$type==='product'?'comments_reactions_products_enabled':'comments_reactions_posts_enabled','1')==='1';
        $dx=getSetting($conn,$type==='product'?'comments_dislike_products_enabled':'comments_dislike_posts_enabled','1')==='1';
        $html='<div class="comment-item"><div class="comment-meta"><small class="comment-stars" dir="ltr" aria-label="امتیاز '.$rating.' از ۵"><b class="star-empty">'.str_repeat('★',5-$rating).'</b><b class="star-filled">'.str_repeat('★',$rating).'</b></small><small class="comment-author" dir="ltr">'.htmlspecialchars(mask_phone($phone),ENT_QUOTES,'UTF-8').'</small></div><div>'.htmlspecialchars($body,ENT_QUOTES,'UTF-8').'</div>';
        if($rx){
            $html.='<div class="comment-votes" data-type="'.$type.'" data-id="'.$cid.'"><button type="button" class="comment-vote like" onclick="voteComment(this,\'like\')">👍 <span>0</span></button>';
            if($dx) $html.='<button type="button" class="comment-vote dislike" onclick="voteComment(this,\'dislike\')">👎 <span>0</span></button>';
            $html.='</div>';
        }
        $html.='</div>';
    }
    echo json_encode(['success'=>true,'message'=>$status==='pending'?'دیدگاه شما برای بازبینی ارسال شد.':'دیدگاه شما ثبت شد.','html'=>$html]); exit;
}


// ── تولید کپچای جدید ──
if ($action === 'get_captcha') {
    $n1 = rand(1, 10);
    $n2 = rand(1, 10);
    $_SESSION['captcha_answer'] = $n1 + $n2;
    echo json_encode(['success' => true, 'question' => pn($n1) . ' + ' . pn($n2) . ' = ?']);
    exit;
}

if ($action === 'register') {
    if (getSetting($conn, 'firewall_enabled', '1') === '1' && getSetting($conn, 'firewall_honeypot_enabled', '1') === '1' && trim($_POST['website'] ?? '') !== '') {
        sitak_firewall_log($conn, 'honeypot', 'register');
        echo json_encode(['success'=>false,'message'=>'درخواست نامعتبر است.']); exit;
    }
    if (getSetting($conn, 'firewall_enabled', '1') === '1' && getSetting($conn, 'firewall_rate_limit_enabled', '1') === '1') {
        $window=(int)getSetting($conn,'firewall_rate_limit_window','60'); $max=(int)getSetting($conn,'firewall_rate_limit_max','5'); $ip=sitak_firewall_ip_hash();
        $q=$conn->prepare("SELECT COUNT(*) c FROM firewall_events WHERE event_type='register_attempt' AND ip_hash=? AND created_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)");
        $q->bind_param('si',$ip,$window); $q->execute(); $n=(int)$q->get_result()->fetch_assoc()['c'];
        sitak_firewall_log($conn,'register_attempt','');
        if($n >= $max){ sitak_firewall_log($conn,'rate_limit','register'); echo json_encode(['success'=>false,'message'=>'تعداد درخواست‌ها زیاد است؛ کمی بعد دوباره تلاش کنید.']); exit; }
    }
    $phone = trim($_POST['phone'] ?? '');
    // پاک‌سازی، ایمن‌سازی در مقابل XSS و مهار کاراکترهای مخرب نام در سمت سرور
    $name = htmlspecialchars(strip_tags(trim($_POST['name'] ?? '')), ENT_QUOTES, 'UTF-8');
    $address = htmlspecialchars(strip_tags(trim($_POST['address'] ?? '')), ENT_QUOTES, 'UTF-8');
    $referral = trim($_POST['referral'] ?? '');

    // ── اعتبارسنجی کپچا ──
    $captcha = intval($_POST['captcha'] ?? -1);
    if (!isset($_SESSION['captcha_answer']) || $captcha !== intval($_SESSION['captcha_answer'])) {
        echo json_encode(['success' => false, 'message' => 'پاسخ سوال امنیتی اشتباه است. لطفاً دوباره تلاش کنید.']);
        exit;
    }
    unset($_SESSION['captcha_answer']);

    // ── حالت گذرواژه ورود اختصاصی: بررسی شماره با SMS.ir و سپس تعیین/ورود با گذرواژه ──
    if (getSetting($conn, 'sms_password_login_enabled', '0') === '1') {
        $pwDigits = sitak_pw_digits($conn);
        if (!preg_match('/^09[0-9]{9}$/', $phone)) {
            echo json_encode(['success' => false, 'message' => 'شماره موبایل نامعتبر است. فرمت صحیح: 09123456789']); exit;
        }
        $uq = $conn->prepare("SELECT login_password, blocked FROM users WHERE phone = ?");
        $uq->bind_param('s', $phone); $uq->execute(); $pu = $uq->get_result()->fetch_assoc();
        if ($pu && (int)$pu['blocked'] === 1) {
            echo json_encode(['success' => false, 'message' => 'متاسفانه امکان ورود به این حساب‌کاربری در حال حاضر امکان پذیر نمی‌باشد. لطفاً در زمانی دیگر مجدداً تلاش کنید.']); exit;
        }
        if ($pu && !empty($pu['login_password'])) {
            $_SESSION['sitak_pw_mode'] = 'login';
            $_SESSION['sitak_pw_phone'] = $phone;
            $_SESSION['sitak_pw_expires'] = time() + 600;
            echo json_encode(['success' => true, 'password_required' => true, 'digits' => $pwDigits]); exit;
        }
        if (!$pu) {
            // ثبت‌نام بسته: شماره‌ی تازه حساب نمی‌سازد؛ کاربر پیام «تماس با مدیر» را می‌بیند
            if (sitak_register_closed($conn)) {
                echo json_encode(['success' => false, 'register_closed' => true, 'message' => sitak_register_closed_msg($conn)], JSON_UNESCAPED_UNICODE); exit;
            }
            // شماره‌ی تازه: با API سامانه‌ی SMS.ir بررسی می‌شود که واقعاً درست و قابل ارسال باشد
            $chk = sitak_generate_otp($conn, false);
            if (!sitak_sms_send($phone, 'sms_otp_template_id', ['Code'=>$chk, 'code'=>$chk])) {
                echo json_encode(['success' => false, 'message' => 'شماره‌ی وارد شده معتبر نیست یا امکان بررسی آن در حال حاضر وجود ندارد.']); exit;
            }
        }
        $_SESSION['sitak_pw_mode'] = 'setup';
        $_SESSION['sitak_pw_phone'] = $phone;
        $_SESSION['sitak_pw_expires'] = time() + 600;
        echo json_encode(['success' => true, 'password_setup' => true, 'digits' => $pwDigits]); exit;
    }

    // اصلاح شماره: حداکثر ۳ بار، بار چهارم قفل تصادفی ۱۵ تا ۳۰ دقیقه‌ای
    $pendingPhone = (string)($_SESSION['sitak_sms_phone'] ?? '');
    if ($pendingPhone !== '' && $pendingPhone !== $phone && !empty($_SESSION['sitak_sms_otp'])) {
        $fixKey = sitak_guard_fix_key();
        $fixMsg = 'تعداد اصلاح شماره زیاد بود. لطفاً مدتی صبر کنید و دوباره تلاش کنید.';
        if (sitak_guard_locked($conn, $fixKey)) { echo json_encode(['success'=>false,'message'=>$fixMsg]); exit; }
        $n = sitak_guard_row($conn, $fixKey)['fails'] + 1;
        if ($n > 3) { sitak_guard_lock_random($conn, $fixKey); echo json_encode(['success'=>false,'message'=>$fixMsg]); exit; }
        sitak_guard_save($conn, $fixKey, $n, 0);
    }
    if (sitak_guard_locked($conn, sitak_guard_code_key($phone))) {
        echo json_encode(['success'=>false,'message'=>'تعداد تلاش‌های اشتباه زیاد بود. لطفاً مدتی صبر کنید و درخواست کد جدید کنید.']); exit;
    }

    $sms_sandbox = getSetting($conn, 'sms_sandbox', '1') === '1';
    if (($sms_sandbox || getSetting($conn, 'sms_enabled', '0') === '1') && getSetting($conn, 'sms_notify_login', '1') === '1') {
        $otp = sitak_generate_otp($conn, $sms_sandbox);
        $_SESSION['sitak_sms_otp'] = password_hash($otp, PASSWORD_DEFAULT);
        $_SESSION['sitak_sms_phone'] = $phone;
        $_SESSION['sitak_sms_ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
        $_SESSION['sitak_sms_expires'] = time() + 180;
        $_SESSION['sitak_sms_limit_expires'] = time() + random_int(900, 1800);
        $_SESSION['sitak_sms_sent_at'] = time();
        $_SESSION['sitak_sms_attempts'] = 1;
        if (!sitak_sms_send($phone, 'sms_otp_template_id', ['Code'=>$otp, 'code'=>$otp])) {
            echo json_encode(['success'=>false, 'message'=>'اکنون امکان ارسال کد تأیید وجود ندارد.']);
            exit;
        }
        $otpResponse = ['success'=>true, 'otp_required'=>true, 'message'=>'کد تأیید برای شما ارسال شد.'];
        echo json_encode($otpResponse);
        exit;
    }

    if (!preg_match('/^09[0-9]{9}$/', $phone)) {
        echo json_encode(['success' => false, 'message' => 'شماره موبایل نامعتبر است. فرمت صحیح: 09123456789']);
        exit;
    }

    // بررسی و فیلتر کردن طول کاراکترهای نام (حداکثر ۳۰ کاراکتر)
    if (mb_strlen($name, 'UTF-8') > 30) {
        echo json_encode(['success' => false, 'message' => 'طول نام شما نمی‌تواند بیشتر از ۳۰ کاراکتر باشد.']);
        exit;
    }

    if (mb_strlen($address, 'UTF-8') > 200) {
        echo json_encode(['success' => false, 'message' => 'طول نشانی نمی‌تواند بیشتر از ۲۰۰ کاراکتر باشد.']);
        exit;
    }

    if (!empty($referral) && !preg_match('/^09[0-9]{9}$/', $referral)) {
        echo json_encode(['success' => false, 'message' => 'فرمت شماره معرف نامعتبر است.']);
        exit;
    }

    if ($phone === $referral) {
        echo json_encode(['success' => false, 'message' => 'نمی‌توانید شماره خودتان را به عنوان معرف وارد کنید.']);
        exit;
    }

    // بررسی ثبت‌نام قبلی کاربر
    $check_stmt = $conn->prepare("SELECT phone, blocked FROM users WHERE phone = ?");
    $check_stmt->bind_param("s", $phone);
    $check_stmt->execute();
    $user_row = $check_stmt->get_result()->fetch_assoc();
    $user_exists = !empty($user_row);

    if ($user_exists) {
        // اگر کاربر مسدود شده باشد
        if ($user_row['blocked'] == 1) {
            echo json_encode(['success' => false, 'message' => 'متاسفانه امکان ورود به این حساب‌کاربری در حال حاضر امکان پذیر نمی‌باشد. لطفاً در زمانی دیگر مجدداً تلاش کنید.']);
            exit;
        }
        // کاربر قبلاً ثبت شده، ورود انجام می‌شود
        if (!empty($address)) {
            $ua = $conn->prepare("UPDATE users SET address = ? WHERE phone = ?");
            $ua->bind_param("ss", $address, $phone); $ua->execute();
        }
        sitak_login_session($conn, $phone);
        echo json_encode(['success' => true, 'is_new' => false]);
        exit;
    }

    // کاربر جدید است — اگر ثبت‌نام بسته باشد، حساب ساخته نمی‌شود و کاربر به مدیر ارجاع می‌شود
    if (sitak_register_closed($conn)) {
        echo json_encode(['success' => false, 'register_closed' => true, 'message' => sitak_register_closed_msg($conn)], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ثبت‌نام بدون معرف (معرف در گام جداگانه گرفته می‌شود)
    $insert_stmt = $conn->prepare("INSERT INTO users (phone, name, address, referral_code, referral_rolls) VALUES (?, ?, ?, NULL, 0)");
    $insert_stmt->bind_param("sss", $phone, $name, $address);
    $insert_stmt->execute();

    sitak_login_session($conn, $phone);
    sitak_notify_admin($conn, 'users', 'کاربر جدید ثبت‌نام کرد', mask_phone($phone) . ($name !== '' ? ' — ' . $name : ''), 'admin.php?sec=users');
    echo json_encode(['success' => true, 'is_new' => true]);
    exit;
}

if ($action === 'set_password') {
    $phone = trim($_POST['phone'] ?? '');
    $pw = sitak_normalize_digits(trim((string)($_POST['password'] ?? '')));
    $pw2 = sitak_normalize_digits(trim((string)($_POST['password2'] ?? '')));
    $digits = sitak_pw_digits($conn);
    if (getSetting($conn, 'sms_password_login_enabled', '0') !== '1' || ($_SESSION['sitak_pw_mode'] ?? '') !== 'setup'
        || ($_SESSION['sitak_pw_phone'] ?? '') !== $phone || time() > (int)($_SESSION['sitak_pw_expires'] ?? 0)
        || !preg_match('/^09[0-9]{9}$/', $phone)) {
        echo json_encode(['success' => false, 'message' => 'درخواست تعیین گذرواژه معتبر نیست. دوباره وارد شو.']); exit;
    }
    if (!sitak_pw_valid($pw, $digits)) {
        echo json_encode(['success' => false, 'message' => 'گذرواژه باید ' . pn($digits) . ' رقمی باشد.']); exit;
    }
    if ($pw !== $pw2) {
        echo json_encode(['success' => false, 'message' => 'دو گذرواژه وارد شده یکسان نیستند.']); exit;
    }
    $chk = $conn->prepare("SELECT phone, blocked FROM users WHERE phone = ?");
    $chk->bind_param('s', $phone); $chk->execute(); $u = $chk->get_result()->fetch_assoc();
    if ($u && (int)$u['blocked'] === 1) {
        echo json_encode(['success' => false, 'message' => 'این حساب کاربری مسدود است.']); exit;
    }
    // ثبت‌نام بسته: نشست تعیین گذرواژه نباید برای شماره‌ی بدون حساب، کاربر جدید بسازد
    if (!$u && sitak_register_closed($conn)) {
        echo json_encode(['success' => false, 'message' => sitak_register_closed_msg($conn)], JSON_UNESCAPED_UNICODE); exit;
    }
    $hash = password_hash($pw, PASSWORD_DEFAULT);
    if ($u) {
        $up = $conn->prepare("UPDATE users SET login_password = ? WHERE phone = ?");
        $up->bind_param('ss', $hash, $phone); $up->execute();
    } else {
        $ins = $conn->prepare("INSERT INTO users (phone, name, address, referral_code, referral_rolls, login_password) VALUES (?, '', '', NULL, 0, ?)");
        $ins->bind_param('ss', $phone, $hash); $ins->execute();
        sitak_notify_admin($conn, 'users', 'کاربر جدید ثبت‌نام کرد', mask_phone($phone) . ' (با گذرواژه)', 'admin.php?sec=users');
    }
    unset($_SESSION['sitak_pw_mode'], $_SESSION['sitak_pw_phone'], $_SESSION['sitak_pw_expires']);
    sitak_login_session($conn, $phone);
    echo json_encode(['success' => true, 'is_new' => !$u, 'needs_referral' => !$u && getSetting($conn, 'referral_enabled', '1') === '1']); exit;
}

if ($action === 'password_login') {
    $phone = trim($_POST['phone'] ?? '');
    $pw = sitak_normalize_digits(trim((string)($_POST['password'] ?? '')));
    $key = 'pw:' . hash('sha256', (string)$phone);
    if (getSetting($conn, 'sms_password_login_enabled', '0') !== '1' || ($_SESSION['sitak_pw_mode'] ?? '') !== 'login'
        || ($_SESSION['sitak_pw_phone'] ?? '') !== $phone || time() > (int)($_SESSION['sitak_pw_expires'] ?? 0)) {
        echo json_encode(['success' => false, 'message' => 'درخواست ورود معتبر نیست. دوباره وارد شو.']); exit;
    }
    $lockMsg = 'تعداد تلاش‌های اشتباه زیاد بود. لطفاً مدتی صبر کنید و دوباره تلاش کنید.';
    if (sitak_guard_locked($conn, $key)) { echo json_encode(['success' => false, 'message' => $lockMsg]); exit; }
    $q = $conn->prepare("SELECT login_password, blocked FROM users WHERE phone = ?");
    $q->bind_param('s', $phone); $q->execute(); $u = $q->get_result()->fetch_assoc();
    if ($u && (int)$u['blocked'] === 1) { echo json_encode(['success' => false, 'message' => 'این حساب کاربری مسدود است.']); exit; }
    if (!$u || empty($u['login_password']) || !password_verify($pw, $u['login_password'])) {
        $fails = sitak_guard_row($conn, $key)['fails'] + 1;
        if ($fails >= 5) {
            sitak_guard_lock_random($conn, $key);
            unset($_SESSION['sitak_pw_mode']);
            echo json_encode(['success' => false, 'message' => $lockMsg]); exit;
        }
        sitak_guard_save($conn, $key, $fails, 0);
        echo json_encode(['success' => false, 'message' => 'گذرواژه وارد شده نادرست است.']); exit;
    }
    sitak_guard_save($conn, $key, 0, 0);
    unset($_SESSION['sitak_pw_mode'], $_SESSION['sitak_pw_phone'], $_SESSION['sitak_pw_expires']);
    sitak_login_session($conn, $phone);
    echo json_encode(['success' => true, 'is_new' => false, 'needs_referral' => false]); exit;
}

if ($action === 'password_reset_request') {
    $phone = trim($_POST['phone'] ?? '');
    if (getSetting($conn, 'sms_password_login_enabled', '0') !== '1') { echo json_encode(['success' => false, 'message' => 'این قابلیت در حال حاضر فعال نیست.']); exit; }
    if (!preg_match('/^09[0-9]{9}$/', $phone)) { echo json_encode(['success' => false, 'message' => 'شماره موبایل نامعتبر است.']); exit; }
    $uq = $conn->prepare("SELECT blocked FROM users WHERE phone = ?");
    $uq->bind_param('s', $phone); $uq->execute(); $u = $uq->get_result()->fetch_assoc();
    if (!$u) { echo json_encode(['success' => false, 'message' => 'این شماره در سامانه ثبت نشده است.']); exit; }
    $pq = $conn->prepare("SELECT id FROM password_reset_requests WHERE user_phone = ? AND status = 'pending' LIMIT 1");
    $pq->bind_param('s', $phone); $pq->execute();
    if ($pq->get_result()->num_rows > 0) { echo json_encode(['success' => true, 'message' => 'درخواست قبلی شما در انتظار بررسی مدیر است.']); exit; }
    $ins = $conn->prepare("INSERT INTO password_reset_requests (user_phone, status) VALUES (?, 'pending')");
    $ins->bind_param('s', $phone); $ins->execute();
    sitak_notify_admin($conn, 'resets', 'درخواست بازنشانی گذرواژه 🔑', mask_phone($phone) . ' درخواست بازنشانی گذرواژه داد', 'admin.php?sec=resets');
    echo json_encode(['success' => true, 'message' => getSetting($conn, 'password_reset_success_message', 'درخواست بازیابی گذرواژه شما ثبت شد. مدیر سایت به‌زودی به شما اطلاع می‌دهد.')]); exit;
}

if ($action === 'change_password') {
    $phone = (string)($_SESSION['sitak_user'] ?? '');
    if (getSetting($conn, 'sms_password_login_enabled', '0') !== '1') { echo json_encode(['success' => false, 'message' => 'این قابلیت در حال حاضر فعال نیست.']); exit; }
    if (!preg_match('/^09[0-9]{9}$/', $phone)) { echo json_encode(['success' => false, 'message' => 'ابتدا وارد شوید.']); exit; }
    $cur = sitak_normalize_digits(trim((string)($_POST['current'] ?? '')));
    $pw = sitak_normalize_digits(trim((string)($_POST['password'] ?? '')));
    $pw2 = sitak_normalize_digits(trim((string)($_POST['password2'] ?? '')));
    $digits = sitak_pw_digits($conn);
    $q = $conn->prepare("SELECT login_password FROM users WHERE phone = ?");
    $q->bind_param('s', $phone); $q->execute(); $u = $q->get_result()->fetch_assoc();
    if (!$u) { echo json_encode(['success' => false, 'message' => 'کاربر پیدا نشد.']); exit; }
    if (!empty($u['login_password']) && !password_verify($cur, $u['login_password'])) {
        echo json_encode(['success' => false, 'message' => 'گذرواژه فعلی نادرست است.']); exit;
    }
    if (!sitak_pw_valid($pw, $digits)) { echo json_encode(['success' => false, 'message' => 'گذرواژه جدید باید ' . pn($digits) . ' رقمی باشد.']); exit; }
    if ($pw !== $pw2) { echo json_encode(['success' => false, 'message' => 'دو گذرواژه جدید یکسان نیستند.']); exit; }
    if (!empty($u['login_password']) && password_verify($pw, $u['login_password'])) { echo json_encode(['success' => false, 'message' => 'گذرواژه جدید نمی‌تواند با گذرواژه قبلی یکسان باشد.']); exit; }
    $hash = password_hash($pw, PASSWORD_DEFAULT);
    $up = $conn->prepare("UPDATE users SET login_password = ? WHERE phone = ?");
    $up->bind_param('ss', $hash, $phone); $up->execute();
    echo json_encode(['success' => true, 'message' => 'گذرواژه ورود شما تغییر کرد.']); exit;
}

if ($action === 'verify_sms_login') {
    $phone = trim($_POST['phone'] ?? '');
    $otp = trim($_POST['otp'] ?? '');
    $otp = strtr($otp, ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9']);
    $sameIp = ($_SESSION['sitak_sms_ip'] ?? '') === ($_SERVER['REMOTE_ADDR'] ?? '');
    $codeKey = sitak_guard_code_key($phone);
    $lockMsg = 'تعداد تلاش‌های اشتباه زیاد بود. لطفاً مدتی صبر کنید و درخواست کد جدید کنید.';
    if (sitak_guard_locked($conn, $codeKey)) {
        unset($_SESSION['sitak_sms_otp']);
        echo json_encode(['success'=>false,'message'=>$lockMsg]); exit;
    }
    $valid = preg_match('/^09[0-9]{9}$/', $phone) && preg_match('/^[0-9]{' . sitak_otp_digits($conn) . '}$/', $otp)
        && ($_SESSION['sitak_sms_phone'] ?? '') === $phone
        && $sameIp
        && !empty($_SESSION['sitak_sms_otp'])
        && time() <= (int)($_SESSION['sitak_sms_expires'] ?? 0)
        && password_verify($otp, $_SESSION['sitak_sms_otp']);
    if (!$valid) {
        $hasCode = !empty($_SESSION['sitak_sms_otp']) && ($_SESSION['sitak_sms_phone'] ?? '') === $phone;
        if ($hasCode && time() <= (int)($_SESSION['sitak_sms_expires'] ?? 0)) {
            // ۳ اشتباه پشت‌سرهم برای این شماره: کد باطل و قفل تصادفی ۱۵ تا ۳۰ دقیقه‌ای
            $wrong = sitak_guard_row($conn, $codeKey)['fails'] + 1;
            if ($wrong >= 3) {
                sitak_guard_lock_random($conn, $codeKey);
                unset($_SESSION['sitak_sms_otp']);
                echo json_encode(['success'=>false,'message'=>$lockMsg]); exit;
            }
            sitak_guard_save($conn, $codeKey, $wrong, 0);
        }
        echo json_encode(['success'=>false,'message'=>'کد تأیید نامعتبر یا منقضی شده است.']); exit;
    }
    sitak_guard_save($conn, $codeKey, 0, 0);
    sitak_guard_save($conn, sitak_guard_fix_key(), 0, 0);
    unset($_SESSION['sitak_sms_otp'], $_SESSION['sitak_sms_phone'], $_SESSION['sitak_sms_ip'], $_SESSION['sitak_sms_expires'], $_SESSION['sitak_sms_limit_expires'], $_SESSION['sitak_sms_sent_at'], $_SESSION['sitak_sms_attempts']);
    $check = $conn->prepare("SELECT phone, blocked, referral_code FROM users WHERE phone = ?");
    $check->bind_param("s", $phone); $check->execute();
    $user = $check->get_result()->fetch_assoc();
    if ($user && (int)$user['blocked'] === 1) { echo json_encode(['success'=>false,'message'=>'این حساب کاربری مسدود است.']); exit; }
    if (!$user) {
        // ثبت‌نام بسته: کد تأیید درست است اما حساب جدید ساخته نمی‌شود؛ کاربر به مدیر ارجاع می‌شود
        if (sitak_register_closed($conn)) {
            echo json_encode(['success'=>false,'register_closed'=>true,'message'=>sitak_register_closed_msg($conn)], JSON_UNESCAPED_UNICODE); exit;
        }
        $ins = $conn->prepare("INSERT INTO users (phone, name, address, referral_code, referral_rolls) VALUES (?, '', '', NULL, 0)");
        $ins->bind_param("s", $phone); $ins->execute();
        $isNew = true;
        sitak_notify_admin($conn, 'users', 'کاربر جدید ثبت‌نام کرد', mask_phone($phone) . ' (ورود پیامکی)', 'admin.php?sec=users');
    } else { $isNew = false; }
    sitak_login_session($conn, $phone);
    echo json_encode(['success'=>true,'is_new'=>$isNew,'needs_referral'=>getSetting($conn,'referral_enabled','1')==='1' && $isNew]); exit;
}

if ($action === 'resend_sms_login') {
    $phone = trim($_POST['phone'] ?? '');
    $now = time();
    $sentAt = (int)($_SESSION['sitak_sms_sent_at'] ?? 0);
    $attempts = (int)($_SESSION['sitak_sms_attempts'] ?? 0);
    $sameIp = ($_SESSION['sitak_sms_ip'] ?? '') === ($_SERVER['REMOTE_ADDR'] ?? '');
    if (!preg_match('/^09[0-9]{9}$/', $phone) || ($_SESSION['sitak_sms_phone'] ?? '') !== $phone || !$sameIp) {
        echo json_encode(['success'=>false,'message'=>'درخواست ارسال دوباره معتبر نیست.']); exit;
    }
    if (sitak_guard_locked($conn, sitak_guard_code_key($phone))) {
        echo json_encode(['success'=>false,'cooldown'=>180,'message'=>'تعداد تلاش‌های اشتباه زیاد بود. لطفاً مدتی صبر کنید و درخواست کد جدید کنید.']); exit;
    }
    if ($now > (int)($_SESSION['sitak_sms_limit_expires'] ?? 0)) {
        echo json_encode(['success'=>false,'cooldown'=>180,'message'=>getSetting($conn, 'sms_resend_limit_message', 'در حال حاضر، امکان درخواست دوباره کد وجود ندارد، لطفاً مدتی دیگر تلاش کنید.')]); exit;
    }
    if ($attempts >= 3) {
        echo json_encode(['success'=>false,'cooldown'=>180,'message'=>getSetting($conn, 'sms_resend_limit_message', 'در حال حاضر، امکان درخواست دوباره کد وجود ندارد، لطفاً مدتی دیگر تلاش کنید.')]); exit;
    }
    $wait = 60 - ($now - $sentAt);
    if ($wait > 0) {
        echo json_encode(['success'=>false,'cooldown'=>$wait,'message'=>'برای ارسال دوباره کمی صبر کنید.']); exit;
    }
    $otp = sitak_generate_otp($conn, getSetting($conn, 'sms_sandbox', '1') === '1');
    $_SESSION['sitak_sms_otp'] = password_hash($otp, PASSWORD_DEFAULT);
    $_SESSION['sitak_sms_expires'] = $now + 180;
    $_SESSION['sitak_sms_sent_at'] = $now;
    $_SESSION['sitak_sms_attempts'] = $attempts + 1;
    if (!sitak_sms_send($phone, 'sms_otp_template_id', ['Code'=>$otp, 'code'=>$otp])) {
        echo json_encode(['success'=>false,'message'=>'اکنون امکان ارسال کد تأیید وجود ندارد.']); exit;
    }
    $response = ['success'=>true,'message'=>'کد جدید ارسال شد.','cooldown'=>60];
    echo json_encode($response); exit;
}

// ── ثبت شماره معرف برای کاربر تازه‌ثبت‌نام ──
if ($action === 'submit_referral') {
    if (getSetting($conn, 'referral_enabled', '1') !== '1') {
        echo json_encode(['success'=>true,'skipped'=>true]); exit;
    }
    $phone = ($_SESSION['sitak_user'] ?? '');
    if (empty($phone) || !preg_match('/^09[0-9]{9}$/', $phone)) {
        echo json_encode(['success' => false, 'message' => 'ابتدا وارد شوید.']);
        exit;
    }
    $referral = trim($_POST['referral'] ?? '');
    if (empty($referral)) {
        echo json_encode(['success' => true, 'skipped' => true]); exit;
    }
    if (!preg_match('/^09[0-9]{9}$/', $referral)) {
        echo json_encode(['success' => false, 'message' => 'فرمت شماره معرف نامعتبر است.']);
        exit;
    }
    if ($phone === $referral) {
        echo json_encode(['success' => false, 'message' => 'نمی‌توانید شماره خودتان را به عنوان معرف وارد کنید.']);
        exit;
    }
    // معرف باید قبلاً ثبت‌نام کرده باشد
    $ref_stmt = $conn->prepare("SELECT phone FROM users WHERE phone = ?");
    $ref_stmt->bind_param("s", $referral);
    $ref_stmt->execute();
    if ($ref_stmt->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'شماره معرف در سیستم ثبت‌نام نکرده است.']);
        exit;
    }
    // کاربر نباید قبلاً معرف داشته باشد
    $own = $conn->prepare("SELECT referral_code FROM users WHERE phone = ?");
    $own->bind_param("s", $phone); $own->execute();
    $own_row = $own->get_result()->fetch_assoc();
    if (!empty($own_row['referral_code'])) {
        echo json_encode(['success' => false, 'message' => 'شما قبلاً معرف ثبت کرده‌اید.']);
        exit;
    }
    // اتصال کاربر به معرف + یک شانس برای هر دو طرف
    $invitee_rolls = max(0, (int)getSetting($conn, 'referral_invitee_rolls', '1'));
    $inviter_rolls = max(0, (int)getSetting($conn, 'referral_inviter_rolls', '1'));
    $u1 = $conn->prepare("UPDATE users SET referral_code = ?, referral_rolls = referral_rolls + ? WHERE phone = ?");
    $u1->bind_param("sis", $referral, $invitee_rolls, $phone); $u1->execute();
    $u2 = $conn->prepare("UPDATE users SET referral_rolls = referral_rolls + ? WHERE phone = ?");
    $u2->bind_param("is", $inviter_rolls, $referral); $u2->execute();
    echo json_encode(['success' => true]); exit;
}

// ── بروزرسانی نام کاربر ──
if ($action === 'update_name') {
    $phone = ($_SESSION['sitak_user'] ?? '');
    if (empty($phone) || !preg_match('/^09[0-9]{9}$/', $phone)) {
        echo json_encode(['success' => false, 'message' => 'لطفاً ابتدا وارد سیستم شوید.']);
        exit;
    }
    $name = htmlspecialchars(strip_tags(trim($_POST['name'] ?? '')), ENT_QUOTES, 'UTF-8');
    if (mb_strlen($name, 'UTF-8') > 30) {
        echo json_encode(['success' => false, 'message' => 'طول نام نمی‌تواند بیشتر از ۳۰ کاراکتر باشد.']);
        exit;
    }
    $stmt = $conn->prepare("UPDATE users SET name = ? WHERE phone = ?");
    $stmt->bind_param("ss", $name, $phone); $stmt->execute();
    echo json_encode(['success' => true, 'name' => $name]); exit;
}

// ── بروزرسانی نشانی کاربر ──
if ($action === 'update_address') {
    $phone = ($_SESSION['sitak_user'] ?? '');
    if (empty($phone) || !preg_match('/^09[0-9]{9}$/', $phone)) {
        echo json_encode(['success' => false, 'message' => 'لطفاً ابتدا وارد سیستم شوید.']);
        exit;
    }
    $address = htmlspecialchars(strip_tags(trim($_POST['address'] ?? '')), ENT_QUOTES, 'UTF-8');
    if (mb_strlen($address, 'UTF-8') > 200) {
        echo json_encode(['success' => false, 'message' => 'طول نشانی نمی‌تواند بیشتر از ۲۰۰ کاراکتر باشد.']);
        exit;
    }
    $stmt = $conn->prepare("UPDATE users SET address = ? WHERE phone = ?");
    $stmt->bind_param("ss", $address, $phone); $stmt->execute();
    echo json_encode(['success' => true]); exit;
}

// ── بروزرسانی نام و نشانی با هم ──
if ($action === 'update_profile') {
    $phone = ($_SESSION['sitak_user'] ?? '');
    if (empty($phone) || !preg_match('/^09[0-9]{9}$/', $phone)) {
        echo json_encode(['success' => false, 'message' => 'لطفاً ابتدا وارد سیستم شوید.']);
        exit;
    }
    $first_name = htmlspecialchars(strip_tags(trim($_POST['first_name'] ?? $_POST['name'] ?? '')), ENT_QUOTES, 'UTF-8');
    $last_name = htmlspecialchars(strip_tags(trim($_POST['last_name'] ?? '')), ENT_QUOTES, 'UTF-8');
    $address = htmlspecialchars(strip_tags(trim($_POST['address'] ?? '')), ENT_QUOTES, 'UTF-8');
    $province = htmlspecialchars(strip_tags(trim($_POST['province'] ?? '')), ENT_QUOTES, 'UTF-8');
    $city = htmlspecialchars(strip_tags(trim($_POST['city'] ?? '')), ENT_QUOTES, 'UTF-8');
    $plaque = htmlspecialchars(strip_tags(trim($_POST['plaque'] ?? '')), ENT_QUOTES, 'UTF-8');
    $unit = htmlspecialchars(strip_tags(trim($_POST['unit'] ?? '')), ENT_QUOTES, 'UTF-8');
    $postal_code = htmlspecialchars(strip_tags(trim($_POST['postal_code'] ?? '')), ENT_QUOTES, 'UTF-8');
    $extra_notes = htmlspecialchars(strip_tags(trim($_POST['extra_notes'] ?? '')), ENT_QUOTES, 'UTF-8');
    if ($first_name === '' || $last_name === '' || $province === '' || $city === '' || $address === '' || $plaque === '' || $unit === '') {
        echo json_encode(['success'=>false,'message'=>'لطفاً همه فیلدهای ضروری گیرنده و نشانی را تکمیل کنید.']);
        exit;
    }
    if ($postal_code !== '' && !preg_match('/^[0-9۰-۹]{10}$/u', $postal_code)) {
        echo json_encode(['success'=>false,'message'=>'کد پستی باید ۱۰ رقم باشد.']);
        exit;
    }
    $has_orders_stmt = $conn->prepare("SELECT COUNT(*) AS c FROM orders WHERE user_phone = ?");
    $has_orders_stmt->bind_param("s", $phone); $has_orders_stmt->execute();
    $has_orders = (int)($has_orders_stmt->get_result()->fetch_assoc()['c'] ?? 0) > 0;
    /* سفارش اول، نشانی گیرنده را به پروفایل کاربر تبدیل می‌کند.
       بعد از آن، فرم سفارش فقط اطلاعات همان گیرنده را برای سفارش جاری نگه می‌دارد. */
    if (!$has_orders || ($_POST['save_profile'] ?? '') === '1') {
        $stmt = $conn->prepare("UPDATE users SET name=?, last_name=?, address=?, province=?, city=?, plaque=?, unit=?, postal_code=?, extra_notes=? WHERE phone=?");
        $stmt->bind_param("ssssssssss", $first_name, $last_name, $address, $province, $city, $plaque, $unit, $postal_code, $extra_notes, $phone); $stmt->execute();
        if (isset($_POST['delivery_lat']) && $_POST['delivery_lat'] !== '' && isset($_POST['delivery_lng']) && $_POST['delivery_lng'] !== '' && getSetting($conn, 'map_enabled', '0') === '1') {
            $plat = (float)$_POST['delivery_lat']; $plng = (float)$_POST['delivery_lng'];
            if (abs($plat) <= 90 && abs($plng) <= 180 && !($plat == 0 && $plng == 0)) { $pls = $conn->prepare("UPDATE users SET delivery_lat=?, delivery_lng=? WHERE phone=?"); $pls->bind_param('dds', $plat, $plng, $phone); $pls->execute(); }
        }
    }
    // اطلاعات پیش‌فرض واریزکننده (کارت‌به‌کارت) — مستقل از نشانی ذخیره می‌شود
    if (isset($_POST['payer_name']) || isset($_POST['payer_card']) || isset($_POST['payer_iban'])) {
        $pn_name = htmlspecialchars(strip_tags(sitak_clean_name($_POST['payer_name'] ?? '')), ENT_QUOTES, 'UTF-8');
        $pn_card = preg_replace('/\D/', '', sitak_digits_latin($_POST['payer_card'] ?? ''));
        $pn_iban = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', sitak_digits_latin($_POST['payer_iban'] ?? '')));
        if ($pn_card !== '' && !preg_match('/^\d{16}$/', $pn_card)) {
            echo json_encode(['success'=>false,'message'=>'شماره کارت واریزکننده باید ۱۶ رقم باشد.']); exit;
        }
        if ($pn_iban !== '' && !preg_match('/^IR\d{24}$/', $pn_iban)) {
            echo json_encode(['success'=>false,'message'=>'شماره شبای واریزکننده باید با IR شروع شود و ۲۶ کاراکتر باشد.']); exit;
        }
        $pn_c = $pn_card !== '' ? $pn_card : null;
        $pn_i = $pn_iban !== '' ? $pn_iban : null;
        $pst = $conn->prepare("UPDATE users SET payer_name = ?, payer_card = ?, payer_iban = ? WHERE phone = ?");
        $pst->bind_param('ssss', $pn_name, $pn_c, $pn_i, $phone); $pst->execute();
    }
    echo json_encode(['success' => true]); exit;
}

// ── دریافت نام و نشانی کاربر ──
if ($action === 'get_profile') {
    $phone = ($_SESSION['sitak_user'] ?? '');
    if (empty($phone) || !preg_match('/^09[0-9]{9}$/', $phone)) {
        echo json_encode(['success' => false]); exit;
    }
    $stmt = $conn->prepare("SELECT name, last_name, phone, address, province, city, plaque, unit, postal_code, extra_notes, payer_name, payer_card, payer_iban FROM users WHERE phone = ?");
    $stmt->bind_param("s", $phone); $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    echo json_encode(['success'=>true, 'first_name'=>$u['name']??'', 'last_name'=>$u['last_name']??'', 'phone'=>$u['phone']??$phone, 'address'=>$u['address']??'', 'province'=>$u['province']??'', 'city'=>$u['city']??'', 'plaque'=>$u['plaque']??'', 'unit'=>$u['unit']??'', 'postal_code'=>$u['postal_code']??'', 'extra_notes'=>$u['extra_notes']??'', 'payer_name'=>$u['payer_name']??'', 'payer_card'=>$u['payer_card']??'', 'payer_iban'=>$u['payer_iban']??'']); exit;
}

if ($action === 'wheel_spin') {
    $phone = ($_SESSION['sitak_user'] ?? '');
    if (!preg_match('/^09[0-9]{9}$/', $phone)) {
        echo json_encode(['success' => false, 'message' => 'لطفاً ابتدا با یک شماره معتبر وارد سیستم شوید.']); exit;
    }
    if (getSetting($conn, 'wheel_enabled', '1') !== '1') {
        echo json_encode(['success' => false, 'message' => 'گردونه در حال حاضر فعال نیست.']); exit;
    }
    $info = get_user_rolls_info($conn, $phone);
    if (!$info) { echo json_encode(['success' => false, 'message' => 'کاربر یافت نشد.']); exit; }
    if (intval($info['user']['blocked'] ?? 0) === 1) {
        echo json_encode(['success' => false, 'blocked' => true, 'error' => 'access_denied', 'message' => "متاسفانه در حال حاضر امکان انجام این فعالیت وجود ندارد.\nلطفاً در زمانی دیگر مجدداً تلاش کنید."]); exit;
    }
    if ($info['total_rolls'] <= 0) {
        echo json_encode(['success' => false, 'message' => 'شانس اضافه ندارید! منتظر ریست بعدی باشید یا دوستانتان را دعوت کنید.']); exit;
    }

    // همان سهمیه‌ی تاس؛ قاچ‌ها از جوایز شانس ساخته می‌شوند (همان تابع chance.php)
    $built = sitak_wheel_build($conn);
    $segs = $built['segments'];
    if (!$segs) { echo json_encode(['success' => false, 'message' => 'جایزه‌ای برای گردونه فعال نیست.']); exit; }

    // انتخاب بر اساس احتمال هر قاچ (واحد ۰.۰۱٪)
    $ticket = mt_rand(1, 10000);
    $acc = 0; $idx = count($segs) - 1;
    foreach ($segs as $i => $sg) {
        $acc += $sg['units'];
        if ($ticket <= $acc) { $idx = $i; break; }
    }
    $won = $segs[$idx];
    $is_winner = $won['prize'] !== null;
    // برد واقعی: سهمیه‌ی جایزه کم می‌شود (اگر همین لحظه تمام شده باشد، برد حساب نمی‌شود)
    if ($is_winner && !sitak_prize_claim($conn, $won['prize'])) { $is_winner = false; }

    // مصرف یک شانس، دقیقاً مثل تاس
    if ($info['has_daily_roll']) {
        $up_stmt = $conn->prepare("UPDATE users SET last_daily_roll = NOW(), daily_rolls_used = " . (int)($info['daily_used_today'] + 1) . " WHERE phone = ?");
    } else {
        $up_stmt = $conn->prepare("UPDATE users SET referral_rolls = referral_rolls - 1 WHERE phone = ?");
    }
    $up_stmt->bind_param("s", $phone); $up_stmt->execute();

    if ($is_winner) {
        $won_name = $won['prize']['description'];
        $won_stmt = $conn->prepare("INSERT INTO winners (user_id, phone, name, prize_name) VALUES (?, ?, ?, ?)");
        $won_stmt->bind_param("isss", $info['user']['id'], $phone, $info['user']['name'], $won_name);
        $won_stmt->execute();
        sitak_notify_admin($conn, 'winners', 'بُرد جایزه 🎉',
            trim(($info['user']['name'] ?: '') . ' (' . mask_phone($phone) . ')') . ' برنده‌ی «' . $won_name . '» شد',
            'admin.php?sec=winners');
    }

    $new_info = get_user_rolls_info($conn, $phone);
    echo json_encode([
        'success' => true,
        'index' => $idx,
        'is_winner' => $is_winner,
        'label' => $won['label'],
        'prize' => $is_winner ? ['description' => $won['prize']['description'], 'discount_code' => $won['prize']['discount_code'], 'affiliate_link' => $won['prize']['affiliate_link'], 'is_local' => $won['prize']['is_local'], 'is_instant' => $won['prize']['is_instant'] ?? 0] : null,
        'remaining_rolls' => $new_info['total_rolls']
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'roll') {
    $phone = ($_SESSION['sitak_user'] ?? '');
    if (empty($phone) || !preg_match('/^09[0-9]{9}$/', $phone)) {
        echo json_encode(['success' => false, 'message' => 'لطفاً ابتدا با یک شماره معتبر وارد سیستم شوید.']);
        exit;
    }

    $prize_id = intval($_POST['prize_id'] ?? 0);
    $info = get_user_rolls_info($conn, $phone);

    if (!$info) {
        ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'کاربر یافت نشد.']);
        exit;
    }
    // بررسی مسدودی — ایمن در برابر noticeها
    $blocked = isset($info['user']['blocked']) ? (int)$info['user']['blocked'] : 0;
    if ($blocked == 1) {
        ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'blocked' => true, 'error' => 'access_denied', 'message' => "متاسفانه در حال حاضر امکان انجام این فعالیت وجود ندارد.\nلطفاً در زمانی دیگر مجدداً تلاش کنید."]);
        exit;
    }
    if ($info['total_rolls'] <= 0) {
        echo json_encode(['success' => false, 'message' => 'تاس اضافه ندارید! منتظر ریست بعدی باشید یا دوستانتان را دعوت کنید.']);
        exit;
    }

    // گرفتن شانس جایزه انتخاب شده به روش امن
    $prize_stmt = $conn->prepare("SELECT * FROM prizes WHERE is_active = 1 AND is_void = 0 AND id = ?");
    $prize_stmt->bind_param("i", $prize_id);
    $prize_stmt->execute();
    $prize = $prize_stmt->get_result()->fetch_assoc();

    if (!$prize) {
        echo json_encode(['success' => false, 'message' => 'لطفاً یک جایزه معتبر انتخاب کنید.']);
        exit;
    }

    $prize_lim = sitak_prize_limits($prize);
    if (!$prize_lim['available']) {
        echo json_encode(['success' => false, 'message' => 'این جایزه دیگه موجود نیست، یکی دیگه رو انتخاب کن.']);
        exit;
    }

    $probability = floatval($prize['win_probability']);
    $rand_val = mt_rand(0, 10000) / 100; // مقدار تصادفی دقیق بین 0.00 تا 100.00

    $is_winner = false;
    $dice1 = 0; $dice2 = 0;

    if ($rand_val <= $probability) {
        $is_winner = true;
        $dice1 = 6;
        $dice2 = 6;
    } else {
        // جوری تاس می‌ریزد که جفت ۶ نشود
        do {
            $dice1 = mt_rand(1, 6);
            $dice2 = mt_rand(1, 6);
        } while ($dice1 === 6 && $dice2 === 6);
    }

    // برد واقعی: یکی از سهمیه‌ی جایزه کم می‌شود؛ اگر همین لحظه تمام شده بود، دیگر برنده نیست
    if ($is_winner && !sitak_prize_claim($conn, $prize)) {
        $is_winner = false;
        do {
            $dice1 = mt_rand(1, 6);
            $dice2 = mt_rand(1, 6);
        } while ($dice1 === 6 && $dice2 === 6);
    }

    // آپدیت کردن دفعات تاس کاربر
    if ($info['has_daily_roll']) {
        $up_stmt = $conn->prepare("UPDATE users SET last_daily_roll = NOW(), daily_rolls_used = " . (int)($info['daily_used_today'] + 1) . " WHERE phone = ?");
        $up_stmt->bind_param("s", $phone);
        $up_stmt->execute();
    } else {
        $up_stmt = $conn->prepare("UPDATE users SET referral_rolls = referral_rolls - 1 WHERE phone = ?");
        $up_stmt->bind_param("s", $phone);
        $up_stmt->execute();
    }

    // ثبت در جدول برندگان در صورت برنده شدن به روش کاملاً امن
    if ($is_winner) {
        $won_stmt = $conn->prepare("INSERT INTO winners (user_id, phone, name, prize_name) VALUES (?, ?, ?, ?)");
        $won_stmt->bind_param("isss", $info['user']['id'], $phone, $info['user']['name'], $prize['description']);
        $won_stmt->execute();
        sitak_notify_admin($conn, 'winners', 'بُرد جایزه 🎉',
            trim(($info['user']['name'] ?: '') . ' (' . mask_phone($phone) . ')') . ' برنده‌ی «' . $prize['description'] . '» شد',
            'admin.php?sec=winners');
    }

    $new_info = get_user_rolls_info($conn, $phone);
    echo json_encode([
        'success' => true,
        'dice1' => $dice1,
        'dice2' => $dice2,
        'is_winner' => $is_winner,
        'prize' => $prize,
        'remaining_rolls' => $new_info['total_rolls']
    ]);
    exit;
}

// ── بررسی روز و ساعت کاری فروشگاه با زمان سرور ──
if ($action === 'check_store_hours') {
    $status=sitak_store_hours_status();
    $message=$status['day_ok'] ? getSetting($conn,'store_closed_time_message','در حال حاضر خارج از ساعت کاری فروشگاه هستیم و امکان ثبت سفارش وجود ندارد.') : getSetting($conn,'store_closed_day_message','امروز فروشگاه تعطیل است و امکان ثبت سفارش وجود ندارد.');
    echo json_encode(['success'=>$status['allowed'],'message'=>$status['allowed']?'':$message],JSON_UNESCAPED_UNICODE); exit;
}

// ── بررسی در لحظه برای محصول تحویل در محل: برنامه محصول + ساعات فروشگاه، بر اساس ساعت تهران ──
if ($action === 'check_product_availability') {
    $pid = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    $st = $conn->prepare("SELECT id, name, is_available, onsite_product, morning_open, morning_close, afternoon_open, afternoon_close, active_days FROM products WHERE id=? LIMIT 1");
    $st->bind_param('i', $pid); $st->execute(); $product = $st->get_result()->fetch_assoc();
    if (!$product || (int)$product['is_available'] !== 1) { echo json_encode(['success'=>false,'message'=>'این محصول در حال حاضر موجود نیست.'],JSON_UNESCAPED_UNICODE); exit; }
    if (empty($product['onsite_product'])) { echo json_encode(['success'=>true],JSON_UNESCAPED_UNICODE); exit; }
    $ps = sitak_product_time_status($product);
    if (!$ps['day_ok']) {
        $schedule = sitak_onsite_product_schedule_labels($product);
        echo json_encode(['success'=>false,'message'=>'فروش این محصول فقط در روزهای '.$schedule['days'].' فعال است.'],JSON_UNESCAPED_UNICODE); exit;
    }
    if (!$ps['time_ok']) {
        $schedule = sitak_onsite_product_schedule_labels($product);
        echo json_encode(['success'=>false,'message'=>'ساعت فروش این محصول به پایان رسیده یا هنوز شروع نشده است. ساعت‌های مجاز: '.$schedule['hours'].'.'],JSON_UNESCAPED_UNICODE); exit;
    }
    $store = sitak_store_hours_status();
    if (!$store['allowed']) {
        $msg = $store['day_ok'] ? getSetting($conn,'store_closed_time_message','در حال حاضر خارج از ساعت کاری فروشگاه هستیم و امکان ثبت سفارش وجود ندارد.') : getSetting($conn,'store_closed_day_message','امروز فروشگاه تعطیل است و امکان ثبت سفارش وجود ندارد.');
        echo json_encode(['success'=>false,'message'=>$msg],JSON_UNESCAPED_UNICODE); exit;
    }
    echo json_encode(['success'=>true],JSON_UNESCAPED_UNICODE); exit;
}

// ── ایجاد سفارش ──
if ($action === 'validate_discount') {
    header('Content-Type: application/json; charset=utf-8');
    $phone = ($_SESSION['sitak_user'] ?? '');
    $cart_items = json_decode($_POST['items'] ?? '[]', true);
    $verified = []; $subtotal = 0;
    if (is_array($cart_items)) {
        $vp = $conn->prepare("SELECT id, price FROM products WHERE id = ?");
        foreach ($cart_items as $ci) {
            if (!isset($ci['id'])) continue;
            $pid = intval($ci['id']);
            $vp->bind_param("i", $pid); $vp->execute();
            $vrow = $vp->get_result()->fetch_assoc();
            if (!$vrow) continue;
            $qty = max(1, min(99, intval($ci['qty'] ?? 1)));
            $verified[] = ['product_id' => (int)$vrow['id'], 'price' => (int)$vrow['price'], 'qty' => $qty];
            $subtotal += (int)$vrow['price'] * $qty;
        }
    }
    if ($subtotal <= 0) { echo json_encode(['success' => false, 'message' => 'سبد خرید خالی است.'], JSON_UNESCAPED_UNICODE); exit; }
    if (($_POST['via_link'] ?? '') === '1') {
        $lk = $conn->prepare("SELECT link_enabled FROM discount_codes WHERE code = ? LIMIT 1");
        $lcode = strtoupper(trim(sitak_normalize_digits((string)($_POST['code'] ?? ''))));
        $lk->bind_param('s', $lcode); $lk->execute();
        $lrow = $lk->get_result()->fetch_assoc();
        if ($lrow && (int)$lrow['link_enabled'] !== 1) {
            echo json_encode(['success' => false, 'message' => 'این کد تخفیف از طریق لینک قابل استفاده نیست.', 'amount' => 0, 'code' => ''], JSON_UNESCAPED_UNICODE); exit;
        }
    }
    $dres = sitak_discount_apply($conn, $_POST['code'] ?? '', $verified, $subtotal, $phone);
    echo json_encode(['success' => $dres['ok'], 'message' => $dres['message'], 'amount' => $dres['amount'], 'code' => $dres['code']], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'map_reverse') {
    $mrlat = (float)($_POST['lat'] ?? 0);
    $mrlng = (float)($_POST['lng'] ?? 0);
    $mrphone = ($_SESSION['sitak_user'] ?? '');
    $mruser = false;
    if (preg_match('/^09[0-9]{9}$/', $mrphone)) { $mru = $conn->prepare("SELECT phone FROM users WHERE phone=? LIMIT 1"); $mru->bind_param('s', $mrphone); $mru->execute(); $mruser = (bool)$mru->get_result()->fetch_assoc(); }
    if (!$mruser) { echo json_encode(['success' => false, 'message' => 'برای ثبت موقعیت ابتدا وارد حساب خود شوید.'], JSON_UNESCAPED_UNICODE); exit; }
    $mrnow = time();
    $mrhits = array_values(array_filter($_SESSION['map_rev_hits'] ?? [], function ($t) use ($mrnow) { return $t > $mrnow - 600; }));
    if (count($mrhits) >= 30) { echo json_encode(['success' => false, 'message' => 'تعداد درخواست‌ها زیاد است. چند دقیقه بعد دوباره امتحان کن.'], JSON_UNESCAPED_UNICODE); exit; }
    $mrhits[] = $mrnow; $_SESSION['map_rev_hits'] = $mrhits;
    if (getSetting($conn, 'map_enabled', '0') !== '1' || abs($mrlat) > 90 || abs($mrlng) > 180 || ($mrlat == 0 && $mrlng == 0)) {
        echo json_encode(['success' => false, 'message' => 'موقعیت انتخاب‌شده معتبر نیست.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $mr = sitak_map_reverse($conn, $mrlat, $mrlng);
    echo json_encode(['success' => $mr['ok'], 'message' => $mr['message'] ?? '', 'address' => $mr['address'] ?? ''], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($action === 'create_order') {
    if (getSetting($conn, 'shop_enabled', '1') !== '1') {
        echo json_encode(['success' => false, 'message' => 'سیستم ثبت سفارش غیرفعال است.']);
        exit;
    }
    $phone = ($_SESSION['sitak_user'] ?? '');
    if (empty($phone) || !preg_match('/^09[0-9]{9}$/', $phone)) {
        echo json_encode(['success' => false, 'message' => 'لطفاً ابتدا وارد شوید.']);
        exit;
    }

    $info = get_user_rolls_info($conn, $phone);

    if (!$info) {
        ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'کاربر یافت نشد.']);
        exit;
    }
    $blocked = isset($info['user']['blocked']) ? (int)$info['user']['blocked'] : 0;
    if ($blocked == 1) {
        ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'blocked' => true, 'error' => 'access_denied', 'message' => "متاسفانه در حال حاضر امکان انجام این فعالیت وجود ندارد.\nلطفاً در زمانی دیگر مجدداً تلاش کنید."]);
        exit;
    }

    $items_json = $_POST['items'] ?? '[]';
    $total = intval($_POST['total'] ?? 0);
    $note = trim($_POST['note'] ?? '');

    $items = json_decode($items_json, true);
    if (!is_array($items) || count($items) === 0) {
        echo json_encode(['success' => false, 'message' => 'سبد خرید خالی است.']);
        exit;
    }
    if ($total <= 0) {
        echo json_encode(['success' => false, 'message' => 'مبلغ سفارش نامعتبر است.']);
        exit;
    }

    // امنیت: فیلتر کردن آیتم‌ها — فقط فیلدهای مجاز
    $clean_items = [];
    foreach ($items as $item) {
        if (isset($item['id'], $item['name'], $item['price'], $item['qty'])) {
            $clean_items[] = [
                'product_id' => intval($item['id']),
                'name'       => htmlspecialchars(strip_tags($item['name']), ENT_QUOTES, 'UTF-8'),
                'price'      => intval($item['price']),
                'qty'        => intval($item['qty'])
            ];
        }
    }
    if (empty($clean_items)) {
        echo json_encode(['success' => false, 'message' => 'آیتم‌های سفارش نامعتبر هستند.']);
        exit;
    }

    $has_onsite=false; $has_regular=false;
    // قیمت‌ها از دیتابیس خوانده می‌شوند؛ مبلغ ارسالی مرورگر قابل اعتماد نیست.
    $server_total = 0;
    $verified_items = [];
    $product_stmt = $conn->prepare("SELECT id, name, price, image_path, is_available, shipping_province, shipping_city, morning_open, morning_close, afternoon_open, afternoon_close, active_days, onsite_product, stock_qty FROM products WHERE id = ?");
    foreach ($clean_items as $item) {
        $pid = $item['product_id'];
        $product_stmt->bind_param("i", $pid);
        $product_stmt->execute();
        $product = $product_stmt->get_result()->fetch_assoc();
        $qty = max(1, min(99, (int)$item['qty']));
        if (!$product || (int)$product['is_available'] !== 1) {
            echo json_encode(['success' => false, 'message' => 'یکی از محصولات دیگر موجود نیست.']);
            exit;
        }
        if ($product['stock_qty'] !== null && (int)$product['stock_qty'] < $qty) {
            $left = (int)$product['stock_qty'];
            $smsg = $left <= 0
                ? '«' . $product['name'] . '» در حال حاضر موجود نیست.'
                : 'در حال حاضر فقط ' . pn($left) . ' عدد از «' . $product['name'] . '» موجوده و سفارش شما بیش از موجودی این محصول است.';
            echo json_encode(['success' => false, 'stock' => true, 'message' => $smsg], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (!empty($product['onsite_product'])) {
            $has_onsite=true;
            $product_status=sitak_product_time_status($product);
            if (!$product_status['allowed']) {
                $schedule=sitak_onsite_product_schedule_labels($product);
                $message=!$product_status['day_ok'] ? 'فروش «'.$product['name'].'» فقط در روزهای '.$schedule['days'].' فعال است.' : 'ساعت فروش «'.$product['name'].'» مجاز نیست. ساعت‌های مجاز: '.$schedule['hours'].'.';
                echo json_encode(['success'=>false,'message'=>$message],JSON_UNESCAPED_UNICODE); exit;
            }
        } else { $has_regular=true; }
        $verified_items[] = [
            'product_id' => (int)$product['id'],
            'name' => $product['name'],
            'price' => (int)$product['price'],
            'qty' => $qty
        ];
        $server_total += (int)$product['price'] * $qty;
    }
    if ($has_onsite && $has_regular) {
        echo json_encode(['success'=>false,'message'=>'محصول تحویل در محل را نمی‌توان با محصولات عادی در یک سفارش ترکیب کرد.'],JSON_UNESCAPED_UNICODE); exit;
    }
    if ($has_onsite) {
        $store_status=sitak_store_hours_status();
        if(!$store_status['allowed']){ $message=$store_status['day_ok']?getSetting($conn,'store_closed_time_message','در حال حاضر خارج از ساعت کاری فروشگاه هستیم و امکان ثبت سفارش وجود ندارد.'):getSetting($conn,'store_closed_day_message','امروز فروشگاه تعطیل است و امکان ثبت سفارش وجود ندارد.'); echo json_encode(['success'=>false,'message'=>$message],JSON_UNESCAPED_UNICODE); exit; }
        $rp=trim($_POST['recipient_province']??''); $rc=trim($_POST['recipient_city']??''); $sp=getSetting($conn,'store_shipping_province',''); $sc=getSetting($conn,'store_shipping_city','');
        if(($sp!=='' && $sp!==$rp)||($sc!=='' && $sc!==$rc)){$onsiteArea = trim($sp . ($sc !== '' ? '، ' . $sc : ''));
            echo json_encode(['success'=>false,'message'=>'محصولات تحویل در محل فقط در محدوده‌ی «' . $onsiteArea . '» قابل سفارش هستند.'],JSON_UNESCAPED_UNICODE);exit;}
    }
    if ($has_onsite && getSetting($conn, 'map_enabled', '0') === '1' && (!isset($_POST['delivery_lat']) || $_POST['delivery_lat'] === '')) {
        echo json_encode(['success' => false, 'message' => 'برای تحویل حضوری، موقعیت را روی نقشه ثبت کن.'], JSON_UNESCAPED_UNICODE); exit;
    }
    if ($server_total <= 0) {
        echo json_encode(['success' => false, 'message' => 'مبلغ سفارش نامعتبر است.']);
        exit;
    }

    // دریافت نام کاربر
    $u_stmt = $conn->prepare("SELECT name, last_name, province, city, address, plaque, unit, postal_code FROM users WHERE phone = ?");
    $u_stmt->bind_param("s", $phone);
    $u_stmt->execute();
    $u_row = $u_stmt->get_result()->fetch_assoc();
    $user_name = $u_row ? $u_row['name'] : '';

    $items_str = json_encode($verified_items, JSON_UNESCAPED_UNICODE);
    $last_name = $u_row['last_name'] ?? '';
    $province = $u_row['province'] ?? '';
    $city = $u_row['city'] ?? '';
    $address = $u_row['address'] ?? '';
    $plaque = $u_row['plaque'] ?? '';
    $unit = $u_row['unit'] ?? '';
    $postal_code = $u_row['postal_code'] ?? '';
    $recipient_first_name = trim($_POST['recipient_first_name'] ?? $user_name);
    $recipient_last_name = trim($_POST['recipient_last_name'] ?? $last_name);
    $recipient_phone = trim($_POST['recipient_phone'] ?? $phone);
    $recipient_province = trim($_POST['recipient_province'] ?? $province);
    $recipient_city = trim($_POST['recipient_city'] ?? $city);
    $recipient_address = trim($_POST['recipient_address'] ?? $address);
    $recipient_plaque = trim($_POST['recipient_plaque'] ?? $plaque);
    $recipient_unit = trim($_POST['recipient_unit'] ?? $unit);
    $recipient_postal_code = trim($_POST['recipient_postal_code'] ?? $postal_code);
    if ($recipient_first_name === '' || $recipient_last_name === '' || $recipient_phone === '' || $recipient_province === '' || $recipient_city === '' || $recipient_address === '' || $recipient_plaque === '' || $recipient_unit === '') {
        echo json_encode(['success'=>false,'message'=>'اطلاعات گیرنده کامل نیست.']);
        exit;
    }
    $user_name = $recipient_first_name;
    // هزینه‌ی ارسال از تنظیمات سرور و بر اساس نشانی گیرنده محاسبه می‌شود (مبلغ مرورگر قابل اعتماد نیست).
    // کد تخفیف (اختیاری): سمت سرور دوباره بررسی می‌شود. حداقل خرید و سقف ارسال رایگان روی جمع اقلام قبل از تخفیف است.
    $subtotal_before = $server_total;
    $disc = ['ok' => false, 'amount' => 0, 'code' => '', 'id' => 0];
    $discount_code_in = trim((string)($_POST['discount_code'] ?? ''));
    if ($discount_code_in !== '') {
        $disc = sitak_discount_apply($conn, $discount_code_in, $verified_items, $subtotal_before, $phone);
        if (!$disc['ok']) {
            echo json_encode(['success' => false, 'message' => $disc['message']], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    $ship = sitak_shipping_quote($conn, $subtotal_before, $recipient_province, $recipient_city);
    if (!$ship['ok']) {
        echo json_encode(['success' => false, 'message' => $ship['message']], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $shipping_fee = (int)$ship['fee'];
    $discount_amount = (int)$disc['amount'];
    $discount_code_db = $discount_amount > 0 ? $disc['code'] : null;
    $server_total = $subtotal_before - $discount_amount + $shipping_fee;
    if ($discount_amount > 0 && !sitak_discount_consume($conn, (int)$disc['id'])) {
        echo json_encode(['success' => false, 'message' => 'ظرفیت استفاده از این کد تمام شده است.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    // ── روش پرداخت: درگاه آنلاین / کارت‌به‌کارت / پرداخت پس از تأیید مدیر ──
    $zarin_on = getSetting($conn, 'zarinpal_enabled', '0') === '1';
    $card_pay = sitak_card_payment_info($conn);
    $method = trim((string)($_POST['payment_method'] ?? ''));
    $pending_on = getSetting($conn, 'pending_method_enabled', '0') === '1';
    if ($method === 'card' && !$card_pay['ready']) $method = '';
    if ($method === 'online' && !$zarin_on) $method = '';
    if ($method === 'pending' && !$pending_on && ($zarin_on || $card_pay['ready'])) $method = '';
    if (!in_array($method, ['online', 'card', 'pending'], true)) $method = $zarin_on ? 'online' : ($card_pay['ready'] ? 'card' : 'pending');
    $ins = $conn->prepare("INSERT INTO orders (user_phone,user_name,items,total_price,note,recipient_last_name,recipient_phone,recipient_province,recipient_city,recipient_address,recipient_plaque,recipient_unit,recipient_postal_code,shipping_fee,discount_code,discount_amount,payment_method) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $ins->bind_param("sssisssssssssisis", $phone, $user_name, $items_str, $server_total, $note, $recipient_last_name, $recipient_phone, $recipient_province, $recipient_city, $recipient_address, $recipient_plaque, $recipient_unit, $recipient_postal_code, $shipping_fee, $discount_code_db, $discount_amount, $method);
    if (!$ins->execute() && $discount_amount > 0) { $conn->query("UPDATE discount_codes SET used_count = GREATEST(used_count - 1, 0) WHERE id = ".(int)$disc['id']); }
    $order_id = $conn->insert_id;
    // موقعیت روی نقشه (اختیاری): روی سفارش و نشانی کاربر ذخیره می‌شود
    if (getSetting($conn, 'map_enabled', '0') === '1' && isset($_POST['delivery_lat']) && $_POST['delivery_lat'] !== '' && isset($_POST['delivery_lng']) && $_POST['delivery_lng'] !== '') {
        $dlat = (float)$_POST['delivery_lat']; $dlng = (float)$_POST['delivery_lng'];
        if (abs($dlat) <= 90 && abs($dlng) <= 180 && !($dlat == 0 && $dlng == 0)) {
            $daddr = mb_substr(trim((string)($_POST['delivery_address'] ?? '')), 0, 250, 'UTF-8');
            $dup = $conn->prepare("UPDATE orders SET delivery_lat=?, delivery_lng=?, delivery_address=? WHERE id=?");
            $dup->bind_param('ddsi', $dlat, $dlng, $daddr, $order_id); $dup->execute();
            $dul = $conn->prepare("UPDATE users SET delivery_lat=?, delivery_lng=? WHERE phone=?");
            $dul->bind_param('dds', $dlat, $dlng, $phone); $dul->execute();
        }
    }
    $order_count_stmt = $conn->prepare("SELECT COUNT(*) AS c FROM orders WHERE user_phone = ?");
    $order_count_stmt->bind_param("s", $phone); $order_count_stmt->execute();
    $is_first_order = (int)($order_count_stmt->get_result()->fetch_assoc()['c'] ?? 0) === 1;
    if ($is_first_order) {
        $save = $conn->prepare("UPDATE users SET name=?, last_name=?, address=?, province=?, city=?, plaque=?, unit=?, postal_code=?, extra_notes=? WHERE phone=?");
        $save->bind_param("ssssssssss", $recipient_first_name, $recipient_last_name, $recipient_address, $recipient_province, $recipient_city, $recipient_plaque, $recipient_unit, $recipient_postal_code, $extra_notes, $phone);
        $save->execute();
    }
    if (getSetting($conn, 'sms_notify_order', '1') === '1') {
        sitak_sms_send($phone, 'sms_order_template_id', [
            'OrderId'=>$order_id, 'OrderID'=>$order_id,
            'Total'=>number_format($server_total), 'total'=>number_format($server_total)
        ]);
    }

    // اطلاع‌رسانی سفارش جدید به ادمین (اختیاری؛ خطا در پیامک، ثبت سفارش را متوقف نمی‌کند)
    if (getSetting($conn, 'sms_notify_admin_order', '0') === '1') {
        $admin_phone = trim((string)getSetting($conn, 'sms_admin_phone', ''));
        if (preg_match('/^09[0-9]{9}$/', $admin_phone)) {
            $admin_all = [
                'OrderId' => (string)$order_id,
                'Name' => trim($recipient_first_name . ' ' . $recipient_last_name),
                'Phone' => (string)$recipient_phone,
                'City' => (string)$recipient_city,
                'Total' => number_format($server_total),
            ];
            $admin_params = [];
            foreach (array_filter(explode(',', (string)getSetting($conn, 'sms_admin_order_params', ''))) as $ak) {
                if (isset($admin_all[$ak])) $admin_params[$ak] = $admin_all[$ak];
            }
            sitak_sms_send($admin_phone, 'sms_admin_order_template_id', $admin_params);
        }
    }

    $payment_url = '';
    $card_payload = null;
    if ($method === 'card') {
        // کارت‌به‌کارت: موجودی همین حالا رزرو می‌شود؛ اگر اطلاعات واریز در مهلت ثبت نشود، سفارش خودکار لغو و موجودی آزاد می‌شود.
        sitak_stock_consume($conn, $items_str);
        $lock_seconds = $card_pay['lock_minutes'] * 60;
        $expires_ts = time() + $lock_seconds;
        $expires_dt = date('Y-m-d H:i:s', $expires_ts);
        $upc = $conn->prepare("UPDATE orders SET stock_reserved = 1, card_lock_expires = ? WHERE id = ?");
        $upc->bind_param('si', $expires_dt, $order_id); $upc->execute();
        $card_payload = [
            'owner'        => $card_pay['owner'],
            'card_number'  => $card_pay['card_formatted'],
            'card_raw'     => $card_pay['card'],
            'iban'         => $card_pay['iban_formatted'],
            'iban_raw'     => $card_pay['iban'],
            'amount'       => $server_total,
            'lock_seconds' => $lock_seconds,
            'expires'      => $expires_ts,
        ];
    } elseif ($method === 'online') {
        $merchant = trim(getSetting($conn, 'zarinpal_merchant_id', ''));
        $merchant = trim($merchant, "{} \t\n\r\0\x0B");
        if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $merchant)) {
            error_log('[SiTak ZarinPal] merchant id invalid');
            echo json_encode(['success' => false, 'message' => 'اکنون امکان ارتباط با درگاه پرداخت وجود ندارد.']);
            exit;
        }
        try {
            $base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
                . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
                . rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/ajax.php')), '/');
            $callback = $base . '/zarinpal-callback.php';
            $gateway = new SiTakZarinPal($merchant, getSetting($conn, 'zarinpal_sandbox', '1') === '1');
            $authority = $gateway->request($server_total, $callback . '?order=' . $order_id, 'سفارش شماره ' . $order_id, $phone);
            $up = $conn->prepare("UPDATE orders SET payment_authority = ? WHERE id = ?");
            $up->bind_param("si", $authority, $order_id);
            $up->execute();
            $payment_url = $gateway->startUrl($authority);
        } catch (Throwable $e) {
            $cancel = $conn->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?");
            $cancel->bind_param("i", $order_id); $cancel->execute();
            error_log('[SiTak ZarinPal] ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'اکنون امکان ارتباط با درگاه پرداخت وجود ندارد.']);
            exit;
        }
    }

    // ── وفاداری: هر خرید آنلاین = ۱ امتیاز (در صورت فعال بودن تنظیم) ──
    // سفارش کارت‌به‌کارت هنگام تأیید واریز توسط مدیر امتیاز می‌گیرد، نه اینجا.
    $loyalty_added = false;
    if ($payment_url === '' && $method !== 'card' && getSetting($conn, 'loyalty_enabled', '1') === '1'
        && getSetting($conn, 'loyalty_online_order', '0') === '1') {
        $lv = $conn->prepare("UPDATE users SET visits = visits + 1 WHERE phone = ?");
        $lv->bind_param("s", $phone);
        $lv->execute();
        $loyalty_added = $lv->affected_rows > 0;
    }

    echo json_encode(['success' => true, 'order_id' => $order_id, 'total' => $server_total, 'payment_url' => $payment_url, 'payment_method' => $method, 'card' => $card_payload, 'loyalty_added' => $loyalty_added], JSON_UNESCAPED_UNICODE);
    // اعلان برای مدیر: سفارش جدید
    $method_label = $method === 'card' ? ' (کارت‌به‌کارت)' : ($method === 'online' ? ' (درگاه)' : '');
    sitak_notify_admin($conn, 'orders', 'سفارش جدید #' . $order_id . $method_label,
        trim($recipient_first_name . ' ' . $recipient_last_name) . ' — ' . number_format($server_total) . ' تومان',
        'admin.php?sec=orders');
    exit;
}

// ── کارت‌به‌کارت: ثبت اطلاعات واریز توسط خریدار ──
if ($action === 'submit_card_payment') {
    $phone = ($_SESSION['sitak_user'] ?? '');
    if (empty($phone) || !preg_match('/^09[0-9]{9}$/', $phone)) {
        echo json_encode(['success' => false, 'message' => 'لطفاً ابتدا وارد شوید.']); exit;
    }
    sitak_expire_card_orders($conn);
    $order_id = (int)($_POST['order_id'] ?? 0);
    $payer_name = htmlspecialchars(strip_tags(sitak_clean_name($_POST['payer_name'] ?? '')), ENT_QUOTES, 'UTF-8');
    $payer_card = preg_replace('/\D/', '', sitak_digits_latin($_POST['payer_card'] ?? ''));
    $payer_iban = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', sitak_digits_latin($_POST['payer_iban'] ?? '')));
    $pay_date_in = trim((string)($_POST['pay_date'] ?? ''));
    $pay_time_in = sitak_digits_latin(trim((string)($_POST['pay_time'] ?? '')));

    $stmt = $conn->prepare("SELECT id, user_phone, status, payment_method, card_submitted_at FROM orders WHERE id = ?");
    $stmt->bind_param('i', $order_id); $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    if (!$order || $order['user_phone'] !== $phone) {
        echo json_encode(['success' => false, 'message' => 'سفارش پیدا نشد.']); exit;
    }
    if (($order['payment_method'] ?? '') !== 'card') {
        echo json_encode(['success' => false, 'message' => 'این سفارش کارت‌به‌کارت نیست.']); exit;
    }
    if ($order['card_submitted_at'] !== null) {
        echo json_encode(['success' => false, 'message' => 'اطلاعات واریز این سفارش قبلاً ثبت شده است.']); exit;
    }
    if ($order['status'] !== 'pending') {
        $msg = $order['status'] === 'cancelled' ? 'این سفارش لغو شده و امکان ثبت اطلاعات واریز ندارد.' : 'این سفارش دیگر در وضعیت پرداخت نیست.';
        echo json_encode(['success' => false, 'message' => $msg]); exit;
    }
    if (mb_strlen($payer_name, 'UTF-8') < 3) {
        echo json_encode(['success' => false, 'message' => 'نام و نام خانوادگی صاحب حساب را کامل وارد کن.']); exit;
    }
    if ($payer_card === '' && $payer_iban === '') {
        echo json_encode(['success' => false, 'message' => 'شماره کارت یا شبای واریز را وارد کن.']); exit;
    }
    if ($payer_card !== '' && !preg_match('/^\d{16}$/', $payer_card)) {
        echo json_encode(['success' => false, 'message' => 'شماره کارت باید ۱۶ رقم باشد.']); exit;
    }
    if ($payer_iban !== '' && !preg_match('/^IR\d{24}$/', $payer_iban)) {
        echo json_encode(['success' => false, 'message' => 'شماره شبا باید با IR شروع شود و ۲۶ کاراکتر باشد.']); exit;
    }
    $g_date = jalali_input_to_greg($pay_date_in);
    if ($g_date === '' || !preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $pay_time_in, $tm)) {
        echo json_encode(['success' => false, 'message' => 'تاریخ و ساعت تراکنش را از تقویم انتخاب کن.']); exit;
    }
    $paid_at = $g_date . ' ' . sprintf('%02d:%02d:00', (int)$tm[1], (int)$tm[2]);
    if (strtotime($paid_at) > time() + 300) {
        echo json_encode(['success' => false, 'message' => 'تاریخ و ساعت تراکنش نمی‌تواند در آینده باشد.']); exit;
    }
    $pc = $payer_card !== '' ? $payer_card : null;
    $pi = $payer_iban !== '' ? $payer_iban : null;
    $up = $conn->prepare("UPDATE orders SET card_payer_name = ?, card_payer_card = ?, card_payer_iban = ?, card_paid_at = ?, card_submitted_at = NOW(), card_status = 'submitted' WHERE id = ? AND card_submitted_at IS NULL AND status = 'pending'");
    $up->bind_param('ssssi', $payer_name, $pc, $pi, $paid_at, $order_id);
    $ok = $up->execute();
    if (!$ok) { echo json_encode(['success' => false, 'message' => 'خطا در ثبت اطلاعات. دوباره تلاش کن.']); exit; }
    if ($up->affected_rows === 0) { echo json_encode(['success' => false, 'message' => 'اطلاعات واریز قبلاً ثبت شده است.']); exit; }
    // اولین خرید کارت‌به‌کارت → اطلاعات واریز به‌عنوان پیش‌فرض در پروفایل کاربر ذخیره می‌شود
    $us = $conn->prepare("SELECT payer_name, payer_card, payer_iban FROM users WHERE phone = ?");
    $us->bind_param('s', $phone); $us->execute();
    $u = $us->get_result()->fetch_assoc();
    if ($u && trim((string)$u['payer_name']) === '' && trim((string)$u['payer_card']) === '' && trim((string)$u['payer_iban']) === '') {
        $uu = $conn->prepare("UPDATE users SET payer_name = ?, payer_card = ?, payer_iban = ? WHERE phone = ?");
        $uu->bind_param('ssss', $payer_name, $pc, $pi, $phone); $uu->execute();
    }
    sitak_notify_admin($conn, 'payments', 'واریز کارت‌به‌کارت ثبت شد 💳',
        mask_phone($phone) . ' برای سفارش #' . $order_id . ' اطلاعات واریز ثبت کرد — منتظر بررسی',
        'admin.php?sec=orders');
    echo json_encode(['success' => true, 'paid_at_fa' => jdatetime_fa($paid_at)]); exit;
}

// ── Fal Hafez Open ──
if ($action === 'hafez_open') {
    $phone = ($_SESSION['sitak_user'] ?? '');
    if (empty($phone)) {
        ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'ابتدا وارد شوید']);
        exit;
    }

    $info = get_user_rolls_info($conn, $phone);

    if (!$info) {
        ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'کاربر یافت نشد.']);
        exit;
    }
    $blocked = isset($info['user']['blocked']) ? (int)$info['user']['blocked'] : 0;
    if ($blocked == 1) {
        ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'blocked' => true, 'error' => 'access_denied', 'message' => "متاسفانه در حال حاضر امکان انجام این فعالیت وجود ندارد.\nلطفاً در زمانی دیگر مجدداً تلاش کنید."]);
        exit;
    }

    // محاسبه شروع سیکل روزانه (مشابه تاس)
    $reset_hour_res = $conn->query("SELECT value_data FROM settings WHERE key_name = 'reset_hour'");
    $reset_hour = intval($reset_hour_res->fetch_assoc()['value_data'] ?? '21');

    $now = new DateTime();
    $cycle_start = new DateTime();
    $cycle_start->setTime($reset_hour, 0, 0);
    if ($now < $cycle_start) {
        $cycle_start->modify('-1 day');
    }
    $cycle_start_str = $cycle_start->format('Y-m-d H:i:s');

    // بررسی Fal از آخرین ریست
    $check = $conn->prepare("SELECT ho.id, hv.* FROM hafez_opens ho JOIN hafez_verses hv ON ho.verse_id = hv.id WHERE ho.user_phone = ? AND ho.opened_at >= ?");
    $check->bind_param("ss", $phone, $cycle_start_str);
    $check->execute();
    $existing = $check->get_result()->fetch_assoc();
    if ($existing) {
        echo json_encode(['success' => false, 'message' => 'فال این دوره شما قبلاً باز شده است', 'verse' => $existing]);
        exit;
    }

    // انتخاب بیت تصادفی
    $verse_res = $conn->query("SELECT * FROM hafez_verses WHERE is_active = 1 ORDER BY RAND() LIMIT 1");
    $verse = $verse_res->fetch_assoc();
    if (!$verse) {
        echo json_encode(['success' => false, 'message' => 'هیچ بیتی ثبت نشده']);
        exit;
    }

    // ثبت باز کردن
    $ins = $conn->prepare("INSERT INTO hafez_opens (user_phone, verse_id) VALUES (?, ?)");
    $ins->bind_param("si", $phone, $verse['id']);
    $ins->execute();
    echo json_encode(['success' => true, 'verse' => $verse]);
    exit;
}


// اگر هیچ دستور مطابقتی پیدا نشد
echo json_encode(['success' => false, 'message' => 'دستور نامعتبر']);

} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
}
?>
