<?php
$comment_type=$comment_type??'product';
$comment_target_id=(int)($comment_target_id??0);
$comment_enabled=getSetting($conn,$comment_type==='product'?'comments_products_enabled':'comments_posts_enabled','1');
$submit_enabled=getSetting($conn,$comment_type==='product'?'comments_submit_products_enabled':'comments_submit_posts_enabled','1')==='1';
$reactions_enabled=getSetting($conn,$comment_type==='product'?'comments_reactions_products_enabled':'comments_reactions_posts_enabled','1')==='1';
$dislike_enabled=getSetting($conn,$comment_type==='product'?'comments_dislike_products_enabled':'comments_dislike_posts_enabled','1')==='1';
$comment_table=$comment_type==='product'?'product_comments':'post_comments';
$comment_col=$comment_type==='product'?'product_id':'post_id';
$cs=$conn->prepare("SELECT * FROM `$comment_table` WHERE `$comment_col`=? AND status='approved' ORDER BY created_at DESC");
$cs->bind_param('i',$comment_target_id);$cs->execute();$comment_rows=$cs->get_result();
if($comment_enabled==='1'): ?>
<div class="comments-section-wrap"><div class="comments-heading">💬 دیدگاه‌ها</div>
<section class="comments-box" id="commentsBox">
<?php while($c=$comment_rows->fetch_assoc()):
  $vote_type=$comment_type; $vote_id=(int)$c['id'];
  $vc=$conn->prepare("SELECT SUM(vote_type='like') likes,SUM(vote_type='dislike') dislikes FROM comment_votes WHERE comment_type=? AND comment_id=?");
  $vc->bind_param('si',$vote_type,$vote_id);$vc->execute();$votes=$vc->get_result()->fetch_assoc();
  $likes=(int)($votes['likes']??0);$dislikes=(int)($votes['dislikes']??0);$my_vote='';if(!empty($_SESSION['sitak_user'])){$mv=$conn->prepare("SELECT vote_type FROM comment_votes WHERE comment_type=? AND comment_id=? AND user_phone=? LIMIT 1");$mv->bind_param('sis',$vote_type,$vote_id,$_SESSION['sitak_user']);$mv->execute();$my_vote=$mv->get_result()->fetch_assoc()['vote_type']??'';}
?>
<div class="comment-item">
  <div class="comment-meta"><small class="comment-stars" dir="ltr" aria-label="امتیاز <?= (int)$c['rating'] ?> از ۵"><b class="star-empty"><?= str_repeat('★',5-(int)$c['rating']) ?></b><b class="star-filled"><?= str_repeat('★',(int)$c['rating']) ?></b></small><small class="comment-author" dir="ltr"><?= htmlspecialchars(mask_phone($c['user_phone']),ENT_QUOTES,'UTF-8') ?></small></div>
  <div><?= htmlspecialchars($c['body'],ENT_QUOTES,'UTF-8') ?></div>
  <?php if($reactions_enabled): ?><div class="comment-votes" data-type="<?=$comment_type?>" data-id="<?=$vote_id?>"><button type="button" class="comment-vote like <?= $my_vote==='like'?'active':'' ?>" onclick="voteComment(this,'like')">👍 <span><?= $likes ?></span></button><?php if($dislike_enabled): ?><button type="button" class="comment-vote dislike <?= $my_vote==='dislike'?'active':'' ?>" onclick="voteComment(this,'dislike')">👎 <span><?= $dislikes ?></span></button><?php endif; ?></div><?php endif; ?>
</div>
<?php endwhile; ?>
<?php if(!empty($_SESSION['sitak_user'])): ?>
<div class="comment-form">
  <div class="comment-rating-row"><span class="comment-rating-label">ستاره انتخاب کن و امتیاز بده:</span><div class="star-picker" id="commentStars"><?php for($i=1;$i<=5;$i++): ?><button type="button" data-rating="<?=$i?>">★</button><?php endfor; ?></div></div>
  <textarea id="commentBody" placeholder="دیدگاه شما درباره این <?= $comment_type==='product'?'محصول':'مطلب' ?>..."></textarea>
  <div class="comment-captcha"><label class="comment-captcha-label">سوال امنیتی</label><div class="captcha-row"><button type="button" class="captcha-refresh" onclick="loadCommentCaptcha()" title="سوال جدید"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg></button><span class="captcha-q" id="commentCaptchaQ">کپچا</span></div><input class="fi comment-captcha-answer" id="commentCaptcha" inputmode="numeric" placeholder="پاسخ را وارد کنید" style="text-align:center!important;direction:ltr"></div>
  <button type="button" class="btn-p" onclick="submitComment()">ثبت دیدگاه</button>
</div>
<?php else: ?><p class="comment-login-hint">برای ثبت دیدگاه ابتدا وارد شوید.</p><?php endif; ?>
</section></div>
<script>
var commentRating=0;
function loadCommentCaptcha(){fetch('ajax.php?action=get_comment_captcha').then(r=>r.json()).then(d=>{if(d.success)document.getElementById('commentCaptchaQ').textContent=d.question})}
document.querySelectorAll('#commentStars button').forEach(b=>{b.onmouseenter=function(){var n=+this.dataset.rating;document.querySelectorAll('#commentStars button').forEach(x=>x.classList.toggle('hover-on',+x.dataset.rating<=n))};b.onmouseleave=function(){document.querySelectorAll('#commentStars button').forEach(x=>x.classList.remove('hover-on'))};b.onclick=function(){commentRating=+this.dataset.rating;document.querySelectorAll('#commentStars button').forEach(x=>x.classList.toggle('on',+x.dataset.rating<=commentRating))}});
function showCommentToast(message,ok){if(typeof showToast==='function'){showToast(message);}else{var t=document.getElementById('toast');if(t){t.textContent=message;t.classList.add('show');clearTimeout(t._timer);t._timer=setTimeout(function(){t.classList.remove('show')},3000);}}}
function submitComment(){
  if(<?= $submit_enabled?'true':'false' ?>===false){showCommentToast('در حال حاضر امکان ثبت دیدگاه وجود ندارد.',false);return;}
  if(!commentRating){showCommentToast('برای ثبت دیدگاه ابتدا ستاره بده.',false);return;}
  var f=new FormData();f.append('action','add_comment');f.append('target_type','<?=$comment_type?>');f.append('target_id','<?=$comment_target_id?>');f.append('rating',commentRating);f.append('body',document.getElementById('commentBody').value);f.append('captcha',document.getElementById('commentCaptcha').value);
  fetch('ajax.php',{method:'POST',body:scsrf(f)}).then(r=>r.json()).then(d=>{showCommentToast(d.message,d.success);if(!d.success&&d.message==='پاسخ کپچا نادرست است.'){document.getElementById('commentCaptcha').value='';loadCommentCaptcha();}if(d.success){if(d.html){var box=document.getElementById('commentsBox');var fm=box.querySelector('.comment-form');if(fm)fm.insertAdjacentHTML('beforebegin',d.html);}document.getElementById('commentBody').value='';document.getElementById('commentCaptcha').value='';commentRating=0;document.querySelectorAll('#commentStars button').forEach(x=>x.classList.remove('on','hover-on'));loadCommentCaptcha();}})
}
function faNum(n){return String(n).replace(/[0-9]/g,function(x){return '۰۱۲۳۴۵۶۷۸۹'[x]})}function voteComment(btn,type){var box=btn.closest('.comment-votes');fetch('ajax.php',{method:'POST',body:new URLSearchParams({action:'vote_comment',target_type:box.dataset.type,target_id:box.dataset.id,vote_type:type,csrf_token:(window.SITAK_CSRF||'')})}).then(r=>r.json()).then(d=>{if(!d.success){showCommentToast(d.message,false);return}box.querySelector('.like span').textContent=faNum(d.likes);var dis=box.querySelector('.dislike span');if(dis)dis.textContent=faNum(d.dislikes);box.querySelectorAll('.comment-vote').forEach(x=>x.classList.remove('active'));if(!d.removed)btn.classList.add('active')})}
loadCommentCaptcha();
</script>
<?php endif; ?>
