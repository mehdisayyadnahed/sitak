<?php
$admin_self = $admin_self ?? 'admin-blog.php';
require 'admin_common.php';

if (!$is_authenticated) { admin_login_screen($login_error ?? null); exit; }
admin_page_start('مطالب');
admin_header();

$editing_pid = intval($_GET['edit'] ?? 0);
$edit_post = null;
if ($editing_pid > 0) {
    $er = $conn->prepare("SELECT * FROM posts WHERE id = ?");
    $er->bind_param("i", $editing_pid);
    $er->execute();
    $edit_post = $er->get_result()->fetch_assoc();
}
$editing_cid = intval($_GET['editcat'] ?? 0);
$edit_cat = null;
if ($editing_cid > 0) {
    $er = $conn->prepare("SELECT * FROM post_categories WHERE id = ?");
    $er->bind_param("i", $editing_cid);
    $er->execute();
    $edit_cat = $er->get_result()->fetch_assoc();
}
$post_tags_str = '';
if ($edit_post) {
    $tr = $conn->prepare("SELECT t.name FROM post_tags t JOIN post_tag_relations r ON r.tag_id = t.id WHERE r.post_id = ?");
    $tr->bind_param("i", $editing_pid);
    $tr->execute();
    $tnames = [];
    $tres = $tr->get_result();
    while ($tn = $tres->fetch_assoc()) { $tnames[] = $tn['name']; }
    $post_tags_str = implode(', ', $tnames);
}
?>
<div class="container">

  <!-- ۱. مطالب -->
  <div class="acc-hdr" onclick="toggleAcc(this)"><h3>۱. مدیریت مطالب</h3><span class="acc-icon">+</span></div>
  <div class="acc-body">

    <form method="post" enctype="multipart/form-data" id="postForm">
      <input type="hidden" name="post_id" id="postId" value="<?= (int)($edit_post['id'] ?? 0) ?>">
      <div class="fg">
        <label>عنوان</label>
        <input type="text" class="fi" name="post_title" id="postTitle" value="<?= htmlspecialchars($edit_post['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>" maxlength="255">
      </div>
      <div class="fg">
          <label>دسته‌بندی</label>
          <select name="post_cat" id="postCat">
            <option value="">بدون دسته</option>
            <?php
            $pcats = $conn->query("SELECT id, name FROM post_categories ORDER BY sort_order ASC, id ASC");
            while ($pc = $pcats->fetch_assoc()):
            ?>
            <option value="<?= (int)$pc['id'] ?>" <?= ((int)($edit_post['category_id'] ?? 0)) === (int)$pc['id'] ? 'selected' : '' ?>><?= htmlspecialchars($pc['name'], ENT_QUOTES, 'UTF-8') ?></option>
            <?php endwhile; ?>
          </select>
        </div>
      <div class="fg">
          <label>برچسب‌ها (با کاما یا ویرگول جدا کنید)</label>
          <input type="text" class="fi" name="post_tags" id="postTags" value="<?= htmlspecialchars($post_tags_str, ENT_QUOTES, 'UTF-8') ?>" placeholder="مثلاً: نکات, آموزش, معرفی">
        </div>
      <div class="fg">
        <label>اسلگ (اختیاری — خودکار از عنوان)</label>
        <input type="text" class="fi" name="post_slug" id="postSlug" value="<?= htmlspecialchars($edit_post['slug'] ?? '', ENT_QUOTES, 'UTF-8') ?>" dir="ltr" style="text-align:left">
      </div>
      <div class="fg">
        <label>خلاصه</label>
        <textarea class="fi" name="post_excerpt" id="postExcerpt" rows="2" placeholder="خلاصه کوتاه مطلب..."><?= htmlspecialchars($edit_post['excerpt'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
      </div>
      <div class="fg">
        <label>متن مطلب (ویرایشگر غنی / HTML)</label>
        <textarea name="post_body" id="postBody" style="display:none"><?= htmlspecialchars($edit_post['body'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
        
        <style>
          .rte-container {
            border: 1px solid var(--border);
            border-radius: var(--rss, 10px);
            background: var(--card-el, #fff);
            overflow: hidden;
            transition: border-color .2s;
          }
          .rte-container:focus-within {
            border-color: var(--gold, #d4a373);
          }
          .rte-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            padding: 8px;
            background: var(--bg, #f9f9f9);
            border-bottom: 1px solid var(--border);
            align-items: center;
          }
          .rte-btn {
            background: var(--card, #fff);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 4px 8px;
            font-size: .8rem;
            font-weight: 700;
            color: var(--text);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 32px;
            height: 32px;
            user-select: none;
            transition: all .15s ease;
          }
          .rte-btn:hover {
            background: var(--gold-subtle, #fefae0);
            border-color: var(--border-gold, #ccd5ae);
            color: var(--gold-dark, #a3b18a);
          }
          .rte-divider {
            width: 1px;
            height: 20px;
            background: var(--border);
            margin: 0 3px;
          }
          .rte-content {
            min-height: 220px;
            max-height: 500px;
            overflow-y: auto;
            padding: 12px 14px;
            font-family: var(--font, sans-serif);
            font-size: .9rem;
            line-height: 1.8;
            color: var(--text);
            outline: none;
            direction: rtl;
            text-align: right;
          }
          .rte-content[contenteditable=true]:empty:before {
            content: attr(data-placeholder);
            color: var(--text3, #888);
            pointer-events: none;
            display: block;
          }
          .rte-content p { margin-bottom: 0.8em; }
          .rte-content h2, .rte-content h3 { font-weight: 800; color: var(--gold-dark, #b5838d); margin: 0.8em 0 0.4em; }
          .rte-content blockquote { border-right: 3px solid var(--gold); padding-right: 12px; margin: 0.8em 0; color: var(--text2); font-style: italic; }
          .rte-content ul, .rte-content ol { padding-right: 24px; margin-bottom: 0.8em; }
          .rte-content img { max-width: 100%; height: auto; border-radius: 8px; margin: 8px 0; }
          .rte-source {
            width: 100%;
            min-height: 220px;
            max-height: 500px;
            padding: 12px 14px;
            font-family: monospace;
            font-size: .85rem;
            line-height: 1.6;
            border: 0;
            background: var(--card-el);
            color: var(--text);
            direction: ltr;
            text-align: left;
            resize: vertical;
            display: none;
            box-sizing: border-box;
          }
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
          <div class="rte-content" id="rteEditor" contenteditable="true" data-placeholder="متن کامل مطلب را اینجا وارد کنید..."></div>
        </div>
      </div>
      <div class="fg">
        <label>تصویر کاور</label>
          <input type="file" name="post_cover" accept=".png,.jpg,.jpeg,.gif,.webp" style="padding:7px">
          <?php if (!empty($edit_post['cover_image'])): ?>
            <div style="display:flex;align-items:center;gap:8px;margin-top:8px">
              <img src="<?= htmlspecialchars($edit_post['cover_image']) ?>" style="height:50px;object-fit:cover;border:1px solid var(--border);border-radius:6px">
              <button type="submit" name="delete_post_cover" value="<?= (int)$edit_post['id'] ?>" class="btn-sm btn-red" style="font-size:.65rem" onclick="return adminConfirmEl(this,'کاور این مطلب حذف شود؟')">حذف 🗑️</button>
            </div>
          <?php endif; ?>
      </div>
      <div class="toggle-row" style="margin-bottom:12px">
        <label class="toggle"><input type="checkbox" name="post_published" value="1" <?= (!isset($edit_post['is_published']) || $edit_post['is_published']) ? 'checked' : '' ?>><span class="slider"></span></label>
        <span class="toggle-label">انتشار یافته (نمایش در سایت)</span>
      </div>
      <div class="fg"><button type="submit" name="save_post" class="btn-p"><?= $edit_post ? 'بروزرسانی مطلب' : 'افزودن مطلب' ?></button></div>
      <div class="fg js-cancel-wrap" style="<?= $edit_post ? '' : 'display:none' ?>"><button type="button" onclick="cancelPostEdit()" class="btn-sm" style="display:flex;background:var(--card-el);color:var(--text2);padding:10px 16px;border-radius:var(--rss);font-size:.82rem;font-weight:700;justify-content:center;text-decoration:none;border:none;cursor:pointer;font-family:var(--font)">انصراف از ویرایش</button></div>
    </form>

    <hr style="border:0;border-top:1px solid var(--border);margin:16px 0">

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
      <span style="font-size:.82rem;font-weight:700;color:var(--gold-dark)">لیست مطالب</span>
      <span style="font-size:.72rem;color:var(--text3)">تعداد: <?= (int)$conn->query("SELECT COUNT(*) c FROM posts")->fetch_assoc()['c'] ?></span>
    </div>
    <div class="tbl-wrap">
    <table><thead><tr><th>عنوان</th><th style="text-align:center">دسته</th><th style="text-align:center">بازدید</th><th style="text-align:center">وضعیت</th><th style="text-align:center">عملیات</th></tr></thead><tbody>
    <?php
    [$bp_pp,$bp_pg]=admin_list_page('bp'); $bp_total=(int)$conn->query("SELECT COUNT(*) AS c FROM posts")->fetch_assoc()["c"]; $bp_pg=min($bp_pg,max(1,(int)ceil($bp_total/$bp_pp))); $bp_off=($bp_pg-1)*$bp_pp; $psr = $conn->query("SELECT p.*, pc.name AS cat_name FROM posts p LEFT JOIN post_categories pc ON pc.id = p.category_id ORDER BY p.created_at DESC LIMIT $bp_off,$bp_pp");
    if ($psr && $psr->num_rows > 0):
      while ($p = $psr->fetch_assoc()):
    ?>
    <tr>
      <td style="font-weight:600;font-size:.8rem"><?= htmlspecialchars($p['title'], ENT_QUOTES, 'UTF-8') ?></td>
      <td style="text-align:center"><span style="font-size:.68rem;background:var(--gold-subtle);color:var(--gold-dark);padding:2px 8px;border-radius:999px"><?= $p['cat_name'] ? htmlspecialchars($p['cat_name'], ENT_QUOTES, 'UTF-8') : '—' ?></span></td>
      <td style="text-align:center"><?= (int)$p['views'] ?></td>
      <td style="text-align:center"><span class="tbl-act-status" title="<?= $p['is_published'] ? 'منتشر شده' : 'پیش‌نویس' ?>"><?= $p['is_published'] ? '✅' : '⛔' ?></span></td>
      <td style="white-space:nowrap;text-align:center">
        <a href="<?= admin_url('blog') ?>?edit=<?= (int)$p['id'] ?>" data-inline-edit="#postForm" class="btn-sm btn-blue" style="font-size:.68rem">✏️</a>
        <a href="<?= admin_url('blog') ?>?delete_post=<?= (int)$p['id'] ?>&csrf=<?= sitak_csrf_token() ?>" class="btn-sm btn-red" style="font-size:.68rem" onclick="return adminConfirmEl(this,'مطلب «<?= htmlspecialchars($p['title'], ENT_QUOTES, 'UTF-8') ?>» حذف شود؟')">🗑️</a>
      </td>
    </tr>
    <?php endwhile; else: ?>
    <tr><td colspan="5" style="text-align:center;color:var(--text3);padding:16px">مطلبی ثبت نشده است.</td></tr>
    <?php endif; ?>
    </tbody></table>
    </div>
    <?= admin_pager('bp', null, $bp_total, $bp_pp, $bp_pg) ?>
  </div>

  <!-- ۲. دسته‌بندی‌ها -->
  <div class="acc-hdr" onclick="toggleAcc(this)"><h3>۲. دسته‌بندی‌های مطالب</h3><span class="acc-icon">+</span></div>
  <div class="acc-body">

    <form method="post" id="catForm">
      <input type="hidden" name="cat_id" id="catId" value="<?= (int)($edit_cat['id'] ?? 0) ?>">
      <div class="fg">
          <label>نام دسته</label>
          <input type="text" class="fi" name="cat_name" id="catName" value="<?= htmlspecialchars($edit_cat['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" maxlength="100" required>
        </div>
      <div class="fg">
        <label>اسلگ (اختیاری)</label>
        <input type="text" class="fi" name="cat_slug" id="catSlug" value="<?= htmlspecialchars($edit_cat['slug'] ?? '', ENT_QUOTES, 'UTF-8') ?>" dir="ltr" style="text-align:left">
      </div>
      <div class="fg">
        <label>ترتیب</label>
        <div class="stat-row"><button type="button" class="btn-h minus" onclick="stepNum('catSort',-1)">−</button><input type="text" inputmode="numeric" class="stat-input" name="cat_sort" id="catSort" value="<?= (int)($edit_cat['sort_order'] ?? 0) ?>"><button type="button" class="btn-h plus" onclick="stepNum('catSort',1)">+</button></div>
      </div>
      <div class="fg">
        <label>توضیحات</label>
        <textarea class="fi" name="cat_desc" id="catDesc" rows="2" placeholder="توضیحات دسته..."><?= htmlspecialchars($edit_cat['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
      </div>
      <div class="fg"><button type="submit" name="save_category" class="btn-p"><?= $edit_cat ? 'بروزرسانی دسته' : 'افزودن دسته' ?></button></div>
      <div class="fg js-cancel-wrap" style="<?= $edit_cat ? '' : 'display:none' ?>"><button type="button" onclick="cancelCatEdit()" class="btn-sm" style="display:flex;background:var(--card-el);color:var(--text2);padding:10px 16px;border-radius:var(--rss);font-size:.82rem;font-weight:700;justify-content:center;text-decoration:none;border:none;cursor:pointer;font-family:var(--font)">انصراف از ویرایش</button></div>
    </form>

    <hr style="border:0;border-top:1px solid var(--border);margin:16px 0">

    <div class="tbl-wrap">
    <table><thead><tr><th>نام</th><th style="text-align:center">اسلگ</th><th style="text-align:center">ترتیب</th><th style="text-align:center">تعداد مطلب</th><th style="text-align:center">عملیات</th></tr></thead><tbody>
    <?php
    [$bc_pp,$bc_pg]=admin_list_page('bc'); $bc_total=(int)$conn->query("SELECT COUNT(*) AS c FROM post_categories")->fetch_assoc()["c"]; $bc_pg=min($bc_pg,max(1,(int)ceil($bc_total/$bc_pp))); $bc_off=($bc_pg-1)*$bc_pp; $catsr = $conn->query("SELECT pc.*, (SELECT COUNT(*) FROM posts p WHERE p.category_id = pc.id) AS pc FROM post_categories pc ORDER BY pc.sort_order ASC, pc.id ASC LIMIT $bc_off,$bc_pp");
    if ($catsr && $catsr->num_rows > 0):
      while ($c = $catsr->fetch_assoc()):
    ?>
    <tr>
      <td style="font-weight:600"><?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?></td>
      <td dir="ltr" style="text-align:center;font-size:.75rem"><?= htmlspecialchars($c['slug'], ENT_QUOTES, 'UTF-8') ?></td>
      <td style="text-align:center"><?= (int)$c['sort_order'] ?></td>
      <td style="text-align:center"><?= (int)$c['pc'] ?></td>
      <td style="white-space:nowrap;text-align:center">
        <a href="<?= admin_url('blog') ?>?editcat=<?= (int)$c['id'] ?>" data-inline-edit="#catForm" class="btn-sm btn-blue" style="font-size:.68rem">✏️</a>
        <a href="<?= admin_url('blog') ?>?delete_category=<?= (int)$c['id'] ?>&csrf=<?= sitak_csrf_token() ?>" class="btn-sm btn-red" style="font-size:.68rem" onclick="return adminConfirmEl(this,'دسته «<?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?>» حذف شود؟')">🗑️</a>
      </td>
    </tr>
    <?php endwhile; else: ?>
    <tr><td colspan="5" style="text-align:center;color:var(--text3);padding:16px">دسته‌بندی‌ای ثبت نشده است.</td></tr>
    <?php endif; ?>
    </tbody></table>
    </div>
    <?= admin_pager('bc', null, $bc_total, $bc_pp, $bc_pg) ?>
  </div>

  <!-- ۳. تنظیمات مطالب -->
  <div class="acc-hdr" onclick="toggleAcc(this)"><h3>۳. تنظیمات مطالب</h3><span class="acc-icon">+</span></div>
  <div class="acc-body">
    <form method="post">
      <div class="toggle-row">
        <label class="toggle"><input type="checkbox" name="blog_enabled" value="1" <?= $blog_enabled==='1'?'checked':'' ?>><span class="slider"></span></label>
        <span class="toggle-label">نمایش تب مطالب (بلاگ) در سایت</span>
      </div>
      <div class="toggle-row">
        <label class="toggle"><input type="checkbox" name="home_blog_enabled" value="1" <?= $home_blog_enabled==='1'?'checked':'' ?>><span class="slider"></span></label>
        <span class="toggle-label">نمایش بخش مطالب در صفحه‌ی اصلی</span>
      </div>
      <div class="toggle-row">
        <label class="toggle"><input type="checkbox" name="blog_views_enabled" value="1" <?= getSetting($conn, 'blog_views_enabled', '1') === '1' ? 'checked' : '' ?>><span class="slider"></span></label>
        <span class="toggle-label">نمایش تعداد بازدید مطالب</span>
      </div>
      <div class="fg"><label>تعداد مطالب در صفحه‌ی اصلی</label><div class="stat-row"><button type="button" class="btn-h minus" onclick="stepNum('homeBlogCount',-1)">−</button><input type="text" inputmode="numeric" class="stat-input fa-number" id="homeBlogCount" name="home_blog_count" min="1" max="50" value="<?= htmlspecialchars((string)$home_blog_count_admin,ENT_QUOTES,'UTF-8') ?>"><button type="button" class="btn-h plus" onclick="stepNum('homeBlogCount',1)">+</button></div></div>
      <div class="toggle-row">
        <label class="toggle"><input type="checkbox" name="show_post_ratings" value="1" <?= ($show_post_ratings_admin??'1')==='1'?'checked':'' ?>><span class="slider"></span></label>
        <span class="toggle-label">نمایش میانگین امتیاز مطالب (صفحه‌ی مطلب و لیست مطالب)</span>
      </div>
      <div class="fg"><label>عنوان هدر صفحه مطالب</label><input type="text" name="blog_page_title" value="<?= htmlspecialchars($blog_page_title??'', ENT_QUOTES, 'UTF-8') ?>"></div>
      <div class="fg"><label>زیرمتن هدر صفحه مطالب</label><textarea name="blog_page_description" rows="3"><?= htmlspecialchars($blog_page_description??'', ENT_QUOTES, 'UTF-8') ?></textarea></div>
      <div class="fg"><label>نوع نمایش مطالب در صفحه‌ی اصلی</label>
          <select name="home_blog_type">
            <option value="latest" <?= $home_blog_type==='most_viewed'?'':'selected' ?>>تازه‌های بلاگ (جدیدترین‌ها)</option>
            <option value="most_viewed" <?= $home_blog_type==='most_viewed'?'selected':'' ?>>بیشترین بازدید</option>
          </select>
      </div>
      <div class="fg"><button type="submit" name="save_blog_settings" class="btn-p">ذخیره تنظیمات مطالب</button></div>
      <p style="font-size:.72rem;color:var(--text3);line-height:1.6;margin-top:4px">گزینه‌ی اول کل تب بلاگ، صفحه‌ی بلاگ و آیتم‌های منوی پایین را مخفی می‌کند. گزینه‌ی دوم فقط سکشن ۵تایی صفحه‌ی اصلی را روشن/خاموش می‌کند. نوع نمایش در صفحه‌ی اصلی یکی از این دو حالت است.</p>
    </form>
  </div>

</div>

<div class="toast" id="toast"></div>

<script>
<?php admin_common_js(); ?>
const editor = document.getElementById('rteEditor');
const bodyInp = document.getElementById('postBody');

if (editor && bodyInp) editor.innerHTML = bodyInp.value;

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
  if (bodyInp && editor) bodyInp.value = editor.innerHTML;
}
if (editor) editor.addEventListener('input', syncSource);

window.syncEditUI = function(){
  var catIdEl = document.querySelector('#catForm input[name="cat_id"]');
  var catEditing = !!catIdEl && parseInt(catIdEl.value, 10) > 0;
  var catBtn = document.querySelector('#catForm button[name="save_category"]');
  if (catBtn) catBtn.textContent = catEditing ? 'بروزرسانی دسته' : 'افزودن دسته';
  var catCw = document.querySelector('#catForm .js-cancel-wrap');
  if (catCw) catCw.style.display = catEditing ? '' : 'none';
  var idEl = document.getElementById('postId');
  var editing = !!idEl && parseInt(idEl.value, 10) > 0;
  var btn = document.querySelector('#postForm button[name="save_post"]');
  if (btn) btn.textContent = editing ? 'بروزرسانی مطلب' : 'افزودن مطلب';
  var cw = document.querySelector('#postForm .js-cancel-wrap');
  if (cw) cw.style.display = editing ? '' : 'none';
};
window.cancelPostEdit = function(){
  var f = document.getElementById('postForm');
  if (f) {
    // پیش‌نمایش کاور قبلی (که از حالت ویرایش آمده) باید برای مطلب جدید حذف شود
    f.querySelectorAll('button[name="delete_post_cover"]').forEach(function(b){ if (b.parentElement) b.parentElement.remove(); });
    f.reset();
  }
  var idEl = document.getElementById('postId'); if (idEl) idEl.value = '0';
  var ed = document.getElementById('rteEditor'); if (ed) ed.innerHTML = '';
  var bd = document.getElementById('postBody'); if (bd) bd.value = '';
  window.syncEditUI();
};
window.cancelCatEdit = function(){
  var f = document.getElementById('catForm');
  if (f) f.reset();
  var idEl = document.getElementById('catId'); if (idEl) idEl.value = '0';
  window.syncEditUI();
};
window.addEventListener('pageshow', window.syncEditUI);
document.addEventListener('DOMContentLoaded', window.syncEditUI);
var postFormEl = document.getElementById('postForm');
var postTitleEl = document.getElementById('postTitle');
if (postTitleEl) postTitleEl.addEventListener('input', function(){ postTitleEl.classList.remove('is-invalid'); });
if (editor) editor.addEventListener('input', function(){ editor.classList.remove('is-invalid'); });
if (postFormEl) postFormEl.addEventListener('submit', function(e){
  var okTitle = postTitleEl && postTitleEl.value.trim() !== '';
  var okBody = !!editor && editor.textContent.trim() !== '';
  if (postTitleEl) postTitleEl.classList.toggle('is-invalid', !okTitle);
  if (editor) editor.classList.toggle('is-invalid', !okBody);
  if (!okTitle || !okBody) { e.preventDefault(); (!okTitle && postTitleEl ? postTitleEl : editor).focus(); }
});
document.getElementById('postBody').closest('form').addEventListener('submit', function(){
  if (bodyInp && editor) bodyInp.value = editor.innerHTML;
});
</script>
<?php
admin_footer_nav('blog');
?>
<?php include 'admin_inline_edit.php'; ?>
<?php include 'admin_ajax_save.php'; ?>
</body>
</html>
