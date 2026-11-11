<?php
$admin_self = $admin_self ?? 'admin-comments.php';
require 'admin_common.php';
if (!$is_authenticated) { admin_login_screen($login_error ?? null); exit; }
admin_page_start('دیدگاه‌ها');
admin_header();
function render_comment_table($conn, $type, $pending_only, $title) {
    $table = $type === 'product' ? 'product_comments' : 'post_comments';
    $where = $pending_only ? " WHERE status='pending'" : " WHERE status<>'pending'";
    $cmk = 'cm'.$type.($pending_only ? '_pending' : '_approved');
    [$cm_pp,$cm_pg]=admin_list_page($cmk); $cm_total=(int)$conn->query("SELECT COUNT(*) AS c FROM `$table` $where")->fetch_assoc()["c"]; $cm_pg=min($cm_pg,max(1,(int)ceil($cm_total/$cm_pp))); $cm_off=($cm_pg-1)*$cm_pp;
    $rows = $conn->query("SELECT * FROM `$table` $where ORDER BY created_at DESC LIMIT $cm_off,$cm_pp");
    echo '<div class="acc-hdr" onclick="toggleAcc(this)"><h3>'.htmlspecialchars($title, ENT_QUOTES, 'UTF-8').'</h3><span class="acc-icon">+</span></div><div class="acc-body"><div class="tbl-wrap"><table><thead><tr><th>شماره نویسنده</th><th>خلاصه دیدگاه</th><th>امتیاز</th><th>وضعیت</th><th style="text-align:center">عملیات</th></tr></thead><tbody>';
    if (!$rows || $rows->num_rows === 0) echo '<tr><td colspan="5" style="text-align:center;color:var(--text3)">موردی نیست</td></tr>';
    while ($r = $rows->fetch_assoc()) {
        $body = htmlspecialchars($r['body'], ENT_QUOTES, 'UTF-8');
        $phone = htmlspecialchars($r['user_phone'], ENT_QUOTES, 'UTF-8');
        $status = $r['status']==='approved' ? '✅' : ($r['status']==='rejected' ? '❌' : '🔄');
        echo '<tr><td dir="ltr">'.$phone.'</td><td>'.htmlspecialchars(mb_strimwidth($r['body'], 0, 90, '…', 'UTF-8'), ENT_QUOTES, 'UTF-8').'</td><td>'.str_repeat('★', (int)$r['rating']).'</td><td style="text-align:center;font-size:1.1rem">'.$status.'</td><td><form method="post" class="comment-admin-actions">';
        echo '<input type="hidden" name="comment_type" value="'.$type.'"><input type="hidden" name="comment_id" value="'.(int)$r['id'].'"><input type="hidden" name="comment_body" value="'.$body.'"><input type="hidden" name="comment_rating" value="'.(int)$r['rating'].'">';
        echo '<button type="button" class="btn-sm action-edit" onclick="editComment(this)">✏️</button><button class="btn-sm action-approve" name="comment_action" value="approved">✅</button><button class="btn-sm action-reject" name="comment_action" value="rejected">❌</button><button class="btn-sm action-review" name="comment_action" value="pending">🔄</button></form></td></tr>';
    }
    echo '</tbody></table></div>';
    echo admin_pager($cmk, null, $cm_total, $cm_pp, $cm_pg);
    echo '</div>';
}
?>
<style>
.comment-admin-actions{display:flex;gap:6px;flex-wrap:nowrap;align-items:center;justify-content:center}.comment-admin-actions .btn-sm{border:0;cursor:pointer;min-width:36px;width:36px;height:34px;padding:0;font-size:1.05rem;line-height:1;display:inline-flex;align-items:center;justify-content:center;text-align:center;border-radius:8px}.comment-admin-actions .action-edit{background:#e4b72e;color:#241c00}.comment-admin-actions .action-approve{background:#39a967;color:#fff}.comment-admin-actions .action-reject{background:#e34b4b;color:#fff}.comment-admin-actions .action-review{background:#2d8fe5;color:#fff}.comment-admin-actions .btn-sm:hover{filter:brightness(1.08);transform:translateY(-1px)}
.comment-settings-note{font-size:.72rem;color:var(--text3);line-height:1.7;margin:10px 0 14px}
</style>
<div class="container">
  <?php render_comment_table($conn,'product',true,'۱. دیدگاه‌های بازبینی‌نشده محصولات'); render_comment_table($conn,'post',true,'۲. دیدگاه‌های بازبینی‌نشده مطالب'); render_comment_table($conn,'product',false,'۳. دیدگاه‌های محصولات'); render_comment_table($conn,'post',false,'۴. دیدگاه‌های مطالب'); ?>
  <div class="acc-hdr" onclick="toggleAcc(this)"><h3>۵. تنظیمات دیدگاه‌ها</h3><span class="acc-icon">+</span></div>
  <div class="acc-body"><form method="post">
    <div class="toggle-row"><label class="toggle"><input type="checkbox" name="comments_require_approval" value="1" <?= $comments_require_approval==='1'?'checked':'' ?>><span class="slider"></span></label><span class="toggle-label">تأیید ادمین پیش از نمایش دیدگاه</span></div>
    <div class="toggle-row"><label class="toggle"><input type="checkbox" name="comments_submit_posts_enabled" value="1" <?= $comments_submit_posts_enabled==='1'?'checked':'' ?>><span class="slider"></span></label><span class="toggle-label">فعال بودن امکان ثبت دیدگاه برای مطالب</span></div>
    <div class="toggle-row"><label class="toggle"><input type="checkbox" name="comments_submit_products_enabled" value="1" <?= $comments_submit_products_enabled==='1'?'checked':'' ?>><span class="slider"></span></label><span class="toggle-label">فعال بودن امکان ثبت دیدگاه برای محصولات</span></div>
    <div class="toggle-row"><label class="toggle"><input type="checkbox" name="comments_posts_enabled" value="1" <?= $comments_posts_enabled==='1'?'checked':'' ?>><span class="slider"></span></label><span class="toggle-label">فعال بودن نمایش بخش دیدگاه‌ها برای مطالب</span></div>
    <div class="toggle-row"><label class="toggle"><input type="checkbox" name="comments_products_enabled" value="1" <?= $comments_products_enabled==='1'?'checked':'' ?>><span class="slider"></span></label><span class="toggle-label">فعال بودن نمایش بخش دیدگاه‌ها برای محصولات</span></div>
    <div class="toggle-row"><label class="toggle"><input type="checkbox" name="comments_dislike_posts_enabled" value="1" <?= $comments_dislike_posts_enabled==='1'?'checked':'' ?>><span class="slider"></span></label><span class="toggle-label">فعال بودن دیسلایک در بخش مطالب</span></div>
    <div class="toggle-row"><label class="toggle"><input type="checkbox" name="comments_dislike_products_enabled" value="1" <?= $comments_dislike_products_enabled==='1'?'checked':'' ?>><span class="slider"></span></label><span class="toggle-label">فعال بودن دیسلایک در بخش محصولات</span></div>
    <div class="toggle-row"><label class="toggle"><input type="checkbox" name="comments_reactions_posts_enabled" value="1" <?= $comments_reactions_posts_enabled==='1'?'checked':'' ?>><span class="slider"></span></label><span class="toggle-label">فعال بودن لایک و دیسلایک در بخش مطالب</span></div>
    <div class="toggle-row"><label class="toggle"><input type="checkbox" name="comments_reactions_products_enabled" value="1" <?= $comments_reactions_products_enabled==='1'?'checked':'' ?>><span class="slider"></span></label><span class="toggle-label">فعال بودن لایک و دیسلایک در بخش محصولات</span></div>
    <p class="comment-settings-note">در حالت تأیید ادمین، دیدگاه‌های جدید ابتدا در بخش بازبینی‌نشده قرار می‌گیرند.</p><button class="btn-p" name="save_comment_settings">ذخیره تنظیمات دیدگاه‌ها</button>
  </form></div>
</div>
<div class="toast" id="toast"></div><script><?php admin_common_js(); ?>
async function editComment(btn){var f=btn.closest('form'),b=await adminPrompt({title:'ویرایش دیدگاه',defaultValue:f.comment_body.value,multiline:true,okText:'ذخیره'});if(b!==null){f.comment_body.value=b;var a=document.createElement('input');a.type='hidden';a.name='comment_action';a.value='edit';f.appendChild(a);f.requestSubmit();}}
</script><script>
(function(){
  function parse(t){ return new DOMParser().parseFromString(t, 'text/html'); }
  function notify(m){ if (typeof showToast === 'function') showToast(m); }
  document.addEventListener('submit', function(e){
    var form = e.target;
    if (!form || form.tagName !== 'FORM' || e.defaultPrevented) return;
    if ((form.getAttribute('method') || 'get').toLowerCase() !== 'post') return;
    e.preventDefault();
    var isComment = form.classList.contains('comment-admin-actions');
    var fd = new FormData(form);
    if (e.submitter && e.submitter.name) fd.append(e.submitter.name, e.submitter.value);
    var btns = form.querySelectorAll('button');
    btns.forEach(function(b){ b.disabled = true; });
    fetch(location.href, {method: 'POST', body: fd, credentials: 'same-origin'})
      .then(function(r){ return r.text().then(function(t){ return {ok: r.ok, t: t}; }); })
      .then(function(res){
        btns.forEach(function(b){ b.disabled = false; });
        var doc = parse(res.t);
        var cur = document.querySelectorAll('.acc-body'), nw = doc.querySelectorAll('.acc-body');
        if (!res.ok || !nw.length) { location.reload(); return; }
        if (isComment) {
          // هر چهار جدول دیدگاه‌ها تازه می‌شوند تا انتقال بین بخش‌ها و وضعیت‌ها درست نشان داده شود
          for (var i = 0; i < 4; i++) { if (cur[i] && nw[i]) cur[i].innerHTML = nw[i].innerHTML; }
        }
        notify('ذخیره شد ✅');
      })
      .catch(function(){ location.reload(); });
  });
})();
</script>
<?php admin_footer_nav('comments'); ?></body></html>
