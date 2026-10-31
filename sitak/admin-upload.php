<?php
$admin_self = $admin_self ?? 'admin-upload.php';
require 'admin_common.php';

if (!$is_authenticated) { admin_login_screen($login_error ?? null); exit; }

$upload_dir = __DIR__ . '/uploads';
if (!file_exists($upload_dir)) {
    @mkdir($upload_dir, 0755, true);
}

$msg_ok = '';
$msg_err = '';

// محاسبه حجم کل فایل‌های موجود در پوشه uploads (بایت)
$total_used_bytes = 0;
$files_list = [];
if (file_exists($upload_dir) && is_dir($upload_dir)) {
    $scanned = scandir($upload_dir);
    foreach ($scanned as $f) {
        if ($f === '.' || $f === '..' || strpos($f, '.') === 0) continue;
        $fpath = $upload_dir . '/' . $f;
        if (is_file($fpath)) {
            $fsize = filesize($fpath);
            $total_used_bytes += $fsize;
            $files_list[] = [
                'name' => $f,
                'size' => $fsize,
                'mtime' => filemtime($fpath),
                'url' => 'uploads/' . rawurlencode($f)
            ];
        }
    }
    usort($files_list, function($a, $b) {
        return $b['mtime'] <=> $a['mtime'];
    });
}
[$fu_pp,$fu_pg]=admin_list_page('fu'); $fu_total=count($files_list); $fu_pg=min($fu_pg,max(1,(int)ceil($fu_total/$fu_pp))); $fu_off=($fu_pg-1)*$fu_pp; $files_page=array_slice($files_list,$fu_off,$fu_pp);

$upload_limit_enabled = getSetting($conn, 'upload_limit_enabled', '0');
$upload_max_mb = floatval(getSetting($conn, 'upload_max_mb', '100'));
$max_bytes = $upload_max_mb * 1024 * 1024;

// ۱. پردازش بارگذاری فایل
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['upload_file'])) {
    $file = $_FILES['upload_file'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        $original_name = basename($file['name']);
        $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        
        $forbidden_exts = ['php', 'phtml', 'pht', 'phar', 'php3', 'php4', 'php5', 'php7', 'php8', 'phps', 'cgi', 'pl', 'py', 'asp', 'aspx', 'jsp', 'shtml', 'htaccess'];
        if (in_array($ext, $forbidden_exts, true)) {
            $msg_err = 'بارگذاری فایل‌های اسکریپتی سرور (مانند PHP) مجاز نیست.';
        } else {
            // چک کردن محدودیت حجم
            if ($upload_limit_enabled === '1' && ($total_used_bytes + $file['size']) > $max_bytes) {
                $msg_err = 'فضای بارگذاری تکمیل است. امکان ذخیره این فایل وجود ندارد.';
            } else {
                $safe_filename = bin2hex(random_bytes(12));
                if ($safe_filename === '') { $safe_filename = 'file_' . time(); }
                
                $target_filename = $safe_filename . '.' . $ext;
                $target_path = $upload_dir . '/' . $target_filename;
                
                $counter = 1;
                while (file_exists($target_path)) {
                    $target_filename = $safe_filename . '_' . $counter . '.' . $ext;
                    $target_path = $upload_dir . '/' . $target_filename;
                    $counter++;
                }
                
                if (move_uploaded_file($file['tmp_name'], $target_path)) {
                    $msg_ok = 'فایل «' . htmlspecialchars($target_filename, ENT_QUOTES, 'UTF-8') . '» با موفقیت بارگذاری شد.';
                    // رفرش برای محاسبه مجدد حجم
                    header('Location: ' . admin_url('upload') . '?msg=uploaded');
                    exit;
                } else {
                    $msg_err = 'خطا در ذخیره‌سازی فایل روی سرور.';
                }
            }
        }
    } else if ($file['error'] !== UPLOAD_ERR_NO_FILE) {
        $msg_err = 'خطا در ارسال فایل (کد خطا: ' . $file['error'] . ')';
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'uploaded') {
    $msg_ok = 'فایل با موفقیت بارگذاری شد.';
}

// ۲. پردازش حذف فایل
if (isset($_GET['delete_file'])) {
    if (!sitak_csrf_valid($_GET['csrf'] ?? '')) { http_response_code(403); exit('درخواست نامعتبر است.'); }
    $del_file = basename($_GET['delete_file']);
    $del_path = $upload_dir . '/' . $del_file;
    if (file_exists($del_path) && is_file($del_path)) {
        if (@unlink($del_path)) {
            header('Location: ' . admin_url('upload') . '?msg=deleted');
            exit;
        } else {
            $msg_err = 'خطا در حذف فایل.';
        }
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') {
    $msg_ok = 'فایل با موفقیت حذف شد.';
}

// ۳. پردازش تغییر نام فایل
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rename_file'])) {
    $old_name = basename($_POST['old_filename'] ?? '');
    $new_name_raw = trim($_POST['new_filename'] ?? '');
    
    if ($old_name !== '' && $new_name_raw !== '') {
        $old_path = $upload_dir . '/' . $old_name;
        $old_ext = strtolower(pathinfo($old_name, PATHINFO_EXTENSION));
        
        $new_ext = strtolower(pathinfo($new_name_raw, PATHINFO_EXTENSION));
        if ($new_ext === '') {
            $new_filename = $new_name_raw . ($old_ext !== '' ? '.' . $old_ext : '');
        } else {
            $new_filename = $new_name_raw;
        }
        
        $new_filename = basename($new_filename);
        $check_ext = strtolower(pathinfo($new_filename, PATHINFO_EXTENSION));
        $forbidden_exts = ['php', 'phtml', 'pht', 'phar', 'php3', 'php4', 'php5', 'php7', 'php8', 'phps', 'cgi', 'pl', 'py', 'asp', 'aspx', 'jsp', 'shtml', 'htaccess'];
        
        if (in_array($check_ext, $forbidden_exts, true)) {
            $msg_err = 'تغییر پسوند به فایل‌های اسکریپتی سرور مجاز نیست.';
        } else {
            $new_path = $upload_dir . '/' . $new_filename;
            if (!file_exists($old_path)) {
                $msg_err = 'فایل اصلی یافت نشد.';
            } elseif (file_exists($new_path) && $old_name !== $new_filename) {
                $msg_err = 'فایلی با این نام از قبل وجود دارد.';
            } else {
                if (@rename($old_path, $new_path)) {
                    $msg_ok = 'نام فایل با موفقیت تغییر کرد.';
                    header('Location: ' . admin_url('upload') . '?msg=renamed');
                    exit;
                } else {
                    $msg_err = 'خطا در تغییر نام فایل.';
                }
            }
        }
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'renamed') {
    $msg_ok = 'نام فایل با موفقیت تغییر کرد.';
}

function fa_num($n, $decimals = 2) {
    $str = number_format((float)$n, $decimals, '.', '');
    return strtr($str, ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹','.'=>'٫']);
}

function format_size_units($bytes) {
    if ($bytes >= 1073741824) {
        return fa_num($bytes / 1073741824, 2) . ' گیگابایت';
    } elseif ($bytes >= 1048576) {
        return fa_num($bytes / 1048576, 2) . ' مگابایت';
    } elseif ($bytes >= 1024) {
        return fa_num($bytes / 1024, 2) . ' کیلوبایت';
    } elseif ($bytes > 1) {
        return fa_num($bytes, 0) . ' بایت';
    } elseif ($bytes == 1) {
        return '۱ بایت';
    } else {
        return '۰ بایت';
    }
}

// محاسبات نوار پیشرفت حافظه
$used_mb = $total_used_bytes / (1024 * 1024);
$percent = ($upload_limit_enabled === '1' && $upload_max_mb > 0) ? min(100, round(($used_mb / $upload_max_mb) * 100, 1)) : 0;

admin_page_start('مدیریت فایل‌ها');
admin_header();
?>
<div class="container">

  <!-- نوار وضعیت فضای بارگذاری -->
  <?php if ($upload_limit_enabled === '1'): ?>
  <div class="card" style="margin-bottom:14px;padding:14px 18px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;font-size:.8rem;font-weight:700">
      <span>📊 وضعیت فضای بارگذاری</span>
      <span>مصرف: <?= format_size_units($total_used_bytes) ?> از <?= $upload_max_mb >= 1024 ? fa_num($upload_max_mb/1024, 2).' گیگابایت' : fa_num($upload_max_mb, 0).' مگابایت' ?> (<?= fa_num($percent, 1) ?>%)</span>
    </div>
    <div style="width:100%;background:rgba(128,128,128,.22);border-radius:999px;height:10px;overflow:hidden">
      <div style="width:<?= $percent ?>%;background:<?= $percent > 90 ? 'var(--red)' : 'var(--gold)' ?>;height:100%;transition:width .3s"></div>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($msg_ok): ?><div class="msg-ok" style="margin-bottom:12px"><?= $msg_ok ?></div><?php endif; ?>
  <?php if ($msg_err): ?><div class="msg-err" style="margin-bottom:12px"><?= $msg_err ?></div><?php endif; ?>

  <!-- ۱. بارگذاری فایل جدید (باز به طور پیش‌فرض) -->
  <div class="acc-hdr open" onclick="toggleAcc(this)"><h3>۱. بارگذاری فایل جدید</h3><span class="acc-icon">−</span></div>
  <div class="acc-body open">
    <form method="post" enctype="multipart/form-data" id="uploadForm" onsubmit="return validateUploadSize(event)">
      <div class="fg">
        <label>انتخاب فایل جهت بارگذاری</label>
        <input type="file" name="upload_file" id="uploadFileInput" class="fi" required style="padding:9px">
      </div>
      <button type="submit" class="btn-p">شروع بارگذاری 📤</button>
    </form>
  </div>

  <!-- ۲. لیست فایل‌های بارگذاری شده (بسته به طور پیش‌فرض) -->
  <div class="acc-hdr" onclick="toggleAcc(this)"><h3>۲. لیست فایل‌های بارگذاری‌شده</h3><span class="acc-icon">+</span></div>
  <div class="acc-body">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
      <span style="font-size:.82rem;font-weight:700;color:var(--gold-dark)">محتویات پوشه uploads/</span>
      <span style="font-size:.72rem;color:var(--text3)">تعداد: <?= count($files_list) ?></span>
    </div>

    <div class="tbl-wrap">
      <table>
        <thead>
          <tr>
            <th>نام فایل</th>
            <th style="text-align:center">تاریخ بارگذاری</th>
            <th style="text-align:center">حجم</th>
            <th style="text-align:center">عملیات</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($files_page)): foreach ($files_page as $fl): ?>
          <tr>
            <td style="font-weight:600;font-size:.8rem;word-break:break-all">
              <a href="<?= htmlspecialchars($fl['url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" style="color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:6px">
                📄 <?= htmlspecialchars($fl['name'], ENT_QUOTES, 'UTF-8') ?>
              </a>
            </td>
            <td style="text-align:center;font-size:.72rem;color:var(--text2);white-space:nowrap">
              <?= pn(date('H:i', $fl['mtime'])) ?> <?= jdate_fa($fl['mtime']) ?>
            </td>
            <td style="text-align:center;font-size:.72rem;font-weight:700" dir="ltr">
              <?= format_size_units($fl['size']) ?>
            </td>
            <td style="white-space:nowrap;text-align:center">
              <button type="button" class="btn-sm act-neutral" data-link="<?= htmlspecialchars($fl['url'], ENT_QUOTES, 'UTF-8') ?>" onclick="copyUploadLink(this)" title="کپی لینک">🔗</button>
              <a href="<?= htmlspecialchars($fl['url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" class="btn-sm act-neutral" download title="دانلود">⬇️</a>
              <button type="button" class="btn-sm btn-blue" style="font-size:.68rem" onclick="openRenameModal('<?= htmlspecialchars($fl['name'], ENT_QUOTES, 'UTF-8') ?>')" title="تغییر نام">✏️</button>
              <a href="<?= admin_url('upload') ?>?delete_file=<?= urlencode($fl['name']) ?>&csrf=<?= sitak_csrf_token() ?>" class="btn-sm btn-red" style="font-size:.68rem" onclick="return adminConfirmEl(this,'آیا از حذف فایل «<?= htmlspecialchars($fl['name'], ENT_QUOTES, 'UTF-8') ?>» اطمینان دارید؟')" title="حذف">🗑️</a>
            </td>
          </tr>
          <?php endforeach; else: ?>
          <tr>
            <td colspan="4" style="text-align:center;color:var(--text3);padding:16px">فایلی تا کنون بارگذاری نشده است.</td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?= admin_pager('fu', null, $fu_total, $fu_pp, $fu_pg) ?>
  </div>

  <!-- ۳. تنظیمات فضای بارگذاری -->
  <div class="acc-hdr" onclick="toggleAcc(this)"><h3>۳. تنظیمات فضای بارگذاری</h3><span class="acc-icon">+</span></div>
  <div class="acc-body">
    <form method="post">
      <div class="toggle-row" style="margin-bottom:14px">
        <label class="toggle"><input type="checkbox" name="upload_limit_enabled" value="1" <?= $upload_limit_enabled === '1' ? 'checked' : '' ?>><span class="slider"></span></label>
        <span class="toggle-label">محدودیت حجم کل بارگذاری فعال باشد</span>
      </div>
      <div class="fg">
        <label>حداکثر فضای مجاز</label>
        <div style="display:flex;gap:8px">
          <input type="text" inputmode="numeric" step="0.1" min="0.1" class="fi" name="upload_max_value" value="<?= $upload_max_mb >= 1024 ? round($upload_max_mb/1024, 2) : $upload_max_mb ?>" required style="flex:1;margin-bottom:0">
          <select name="upload_max_unit" class="fi" style="width:110px;margin-bottom:0;cursor:pointer">
            <option value="MB" <?= $upload_max_mb < 1024 ? 'selected' : '' ?>>مگابایت (MB)</option>
            <option value="GB" <?= $upload_max_mb >= 1024 ? 'selected' : '' ?>>گیگابایت (GB)</option>
          </select>
        </div>
      </div>
      <div class="fg"><button type="submit" name="save_upload_settings" class="btn-p">ذخیره تنظیمات بارگذاری</button></div>
    </form>
  </div>

</div>

<!-- شیت تغییر نام فایل -->
<div id="renameSheet" class="adm-lo-sheet" role="dialog" aria-modal="true">
  <div class="adm-lo-handle"></div>
  <div class="adm-lo-title">✏️ تغییر نام فایل</div>
  <form method="post" id="renameSheetForm">
    <input type="hidden" name="old_filename" id="modalOldFileName">
    <div class="fg">
      <label>نام جدید فایل</label>
      <input type="text" class="fi" name="new_filename" id="modalNewFileName" required dir="ltr" style="text-align:left">
    </div>
    <div class="adm-lo-btns">
      <button type="button" class="adm-lo-cancel" onclick="closeRenameModal()">انصراف</button>
      <button type="submit" name="rename_file" class="adm-lo-ok">ثبت تغییر نام</button>
    </div>
  </form>
</div>
<script>document.body.appendChild(document.getElementById('renameSheet'));</script>

<div class="toast" id="toast"></div>
<script>
<?php admin_common_js(); ?>
const limitEnabled = <?= $upload_limit_enabled === '1' ? 'true' : 'false' ?>;
const maxBytes = <?= $max_bytes ?>;
const currentUsedBytes = <?= $total_used_bytes ?>;

function validateUploadSize(e) {
  if (!limitEnabled) return true;
  const input = document.getElementById('uploadFileInput');
  if (input && input.files && input.files[0]) {
    const fileSize = input.files[0].size;
    if ((currentUsedBytes + fileSize) > maxBytes) {
      e.preventDefault();
      adminAlert('حجم این فایل بیشتر از فضای باقی‌مانده است و امکان بارگذاری آن وجود ندارد.');
      return false;
    }
  }
  return true;
}

function copyUploadLink(btn) {
  var url = new URL(btn.dataset.link, location.href).href;
  var done = function(){ showToast('لینک فایل کپی شد ✅'); };
  var fallback = function(){ var t=document.createElement('textarea'); t.value=url; t.style.position='fixed'; t.style.opacity='0'; document.body.appendChild(t); t.select(); try{ document.execCommand('copy'); done(); }catch(e){} t.remove(); };
  if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(url).then(done).catch(fallback); } else { fallback(); }
}
function openRenameModal(filename) {
  document.getElementById('modalOldFileName').value = filename;
  document.getElementById('modalNewFileName').value = filename;
  admOpenFrame('renameSheet');
  document.getElementById('modalNewFileName').focus();
}
function closeRenameModal() {
  admCloseFrame('renameSheet');
}
</script>
<?php
admin_footer_nav('upload');
?>
<?php include 'admin_ajax_save.php'; ?>
</body>
</html>
