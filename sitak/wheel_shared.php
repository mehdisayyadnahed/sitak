<?php
// گردونه شانس: بخش مشترک بین صفحه‌ی اصلی (index.php) و صفحه‌ی شانس (chance.php)
// قاچ‌ها از جوایز شانس ساخته می‌شوند (sitak_wheel_build در config.php).

// قاچ‌ها و رنگ‌بندی شِیپ CSS را آماده می‌کند.
function sitak_wheel_prepare($conn, $enabled) {
    $build = $enabled ? sitak_wheel_build($conn) : ['segments' => [], 'over' => false, 'sum' => 0];
    $segs = $build['segments'];
    $has_prize = false;
    foreach ($segs as $sg) if ($sg['prize'] !== null) $has_prize = true;
    if (!$has_prize) $segs = [];
    $n = count($segs);
    $parts = [];
    foreach ($segs as $i => $sg) {
        $parts[] = $sg['color'] . ' ' . round($i * 100 / $n, 4) . '% ' . round(($i + 1) * 100 / $n, 4) . '%';
    }
    $bg = $parts ? 'conic-gradient(' . implode(',', $parts) . ')' : 'var(--card-el)';
    return ['segments' => $segs, 'bg' => $bg];
}

// بخش ظاهری گردونه (استایل + چرخنده + دکمه)
function sitak_wheel_section($segments, $bg, $disabled) {
    $n = count($segments);
    if ($n === 0) return;
    ?>
  <style>
    .wheel-wrap{position:relative;width:270px;height:270px;max-width:86vw;max-height:86vw;margin:14px auto 20px}
    .wheel{position:absolute;inset:0;border-radius:50%;border:4px solid var(--gold);box-shadow:var(--shadow-md);transition:transform 2.8s ease-out;overflow:hidden}
    .wheel-pointer{position:absolute;top:-14px;left:50%;transform:translateX(-50%);width:0;height:0;border-left:12px solid transparent;border-right:12px solid transparent;border-top:24px solid var(--gold-dark);z-index:2}
    .wl{position:absolute;left:50%;top:50%;width:0;height:0}
    .wl > span{position:absolute;left:-60px;top:-110px;width:120px;text-align:center;font-size:.86rem;font-weight:700;line-height:1.35;color:#fff;text-shadow:0 1px 2px rgba(0,0,0,.45);display:flex;flex-direction:column;align-items:center;justify-content:center;height:66px;overflow:hidden}
    .wl .wl-badge{display:inline-block;margin-top:3px;padding:1px 6px;border-radius:8px;background:rgba(0,0,0,.4);color:#fff;font-size:.58rem;font-weight:700;line-height:1.4;text-shadow:none;white-space:nowrap}
    .wl .wl-name{position:static;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}
  </style>
  <section class="card card-gold wheel-card" style="text-align:center;padding-top:26px;margin-bottom:14px">
    <div class="win-badge" style="position:absolute;top:18px;right:18px;margin:0">🎡 گردونه شانس</div>
    <div class="wheel-wrap">
      <div class="wheel-pointer"></div>
      <div class="wheel" id="wheel" data-n="<?= $n ?>" style="background:<?= htmlspecialchars($bg, ENT_QUOTES, 'UTF-8') ?>">
        <?php foreach ($segments as $i => $sg):
          $ang = ($i + 0.5) * 360 / $n;
        ?>
        <div class="wl" style="transform:rotate(<?= round($ang, 3) ?>deg)"><span><span class="wl-name"><?= htmlspecialchars($sg['label'], ENT_QUOTES, 'UTF-8') ?></span><?php foreach (($sg['badges'] ?? []) as $bd): ?><span class="wl-badge"><?= htmlspecialchars($bd, ENT_QUOTES, 'UTF-8') ?></span><?php endforeach; ?></span></div>
        <?php endforeach; ?>
      </div>
    </div>
    <button class="roll-btn" id="wheelBtn" onclick="spinWheel()"<?php if ($disabled): ?> disabled style="width:100%;opacity:.5;cursor:default"<?php endif; ?>><span id="wheelBtnText"><?= $disabled ? 'شانس امروز تموم شد ✅' : 'گردونه رو بچرخون 🎡' ?></span></button>
  </section>
  <?php sitak_wheel_script(); ?>
    <?php
}

// منطق چرخش (سمت کاربر). نتیجه همیشه از سرور می‌آید.
function sitak_wheel_script() {
    ?>
<script>
var wheelRot = 0;
var wheelBusy = false;
function updateWheelBtn(){
  var b=document.getElementById('wheelBtn'),t=document.getElementById('wheelBtnText');if(!b||!t)return;
  if(USER_PHONE&&USER_ROLLS<=0){b.disabled=true;b.style.opacity='.5';b.style.cursor='default';t.textContent='شانس امروز تموم شد ✅'}
  else{b.disabled=false;b.style.opacity='';b.style.cursor='';t.textContent='گردونه رو بچرخون 🎡'}
}
function spinWheel(){
  try{getAC()}catch(e){}
  if(wheelBusy) return;
  if(!USER_PHONE){loginFrom='wheel';openSheet('reg');return}
  if(USER_ROLLS<=0){showToast('شانس امروزت تموم شده! تا ریست بعدی صبر کن یا دوستانت رو دعوت کن 🎡');return}
  wheelBusy=true;
  var btn=document.getElementById('wheelBtn');btn.disabled=true;
  document.getElementById('wheelBtnText').textContent='در حال چرخش...';
  fetch('ajax.php?action=wheel_spin',{method:'POST',body:scsrf(new FormData())}).then(function(r){return r.json()}).then(function(data){
    if(data.blocked===true){showBlockedAndLogout();return}
    if(!data.success){showToast(data.message);wheelBusy=false;updateWheelBtn();return}
    var wheel=document.getElementById('wheel');
    var n=parseInt(wheel.getAttribute('data-n'),10)||1;
    var slice=360/n;
    var center=data.index*slice+slice/2+(Math.random()-0.5)*slice*0.6;
    var mod=((wheelRot%360)+360)%360;
    var add=(((360-center)-mod)%360+360)%360;
    wheelRot=wheelRot+1800+add;
    try{playWheelSpinSound()}catch(e){}
    wheel.style.transform='rotate('+wheelRot+'deg)';
    setTimeout(function(){finishWheel(data)},2800);
  }).catch(function(){wheelBusy=false;updateWheelBtn();showToast('خطا در ارتباط با سرور')});
}
// آخرین نتیجه‌ی چرخش کاربر (لاگین‌شده، در همان چرخه) نگه داشته می‌شود؛ بقیه‌ی بارها زاویه‌ی تصادفی است
function wheelShowAngle(deg){
  var wheel=document.getElementById('wheel');if(!wheel)return;
  wheel.style.transition='none';
  wheelRot=deg;
  wheel.style.transform='rotate('+wheelRot+'deg)';
  void wheel.offsetWidth;
  wheel.style.transition='';
}
function wheelInit(){
  var wheel=document.getElementById('wheel');if(!wheel)return;
  var n=parseInt(wheel.getAttribute('data-n'),10)||1;
  var slice=360/n;
  var labels=[];
  wheel.querySelectorAll('.wl-name').forEach(function(el){labels.push(el.textContent)});
  var saved=null;
  if(USER_PHONE){
    try{saved=JSON.parse(localStorage.getItem('sitak_lastwheel')||'null')}catch(e){saved=null}
    if(saved&&(saved.c!==ROLL_CYCLE_START||saved.p!==USER_PHONE))saved=null;
  }
  var idx=saved?labels.indexOf(saved.l):-1;
  if(saved&&typeof saved.a==='number'&&isFinite(saved.a)){
    // دقیقاً همون زاویه‌ای که گردونه آخرین بار توقف کرده بود
    wheelShowAngle(((saved.a%360)+360)%360);
  }else if(idx>=0){
    wheelShowAngle(((360-(idx*slice+slice/2))%360+360)%360);
  }else{
    // زاویه‌ی تصادفی، ولی هیچ‌وقت وسط یک قاچ نیست: اشاره‌گر همیشه کنار مرز قاچ می‌ایسته
    var pick=Math.floor(Math.random()*n);
    var edge=(Math.random()<0.5?-1:1)*(0.3+Math.random()*0.2)*slice;
    wheelShowAngle(((360-(pick*slice+slice/2+edge))%360+360)%360);
  }
}
document.addEventListener('DOMContentLoaded',wheelInit);
function finishWheel(data){
  try{playWheelStopSound()}catch(e){}
  try{localStorage.setItem('sitak_lastwheel',JSON.stringify({l:data.label,c:ROLL_CYCLE_START,p:USER_PHONE,a:wheelRot}))}catch(e){}
  wheelBusy=false;
  USER_ROLLS=data.remaining_rolls;
  var badge=document.getElementById('rollBadge');if(badge)badge.textContent='🎲 '+toPersianNum(data.remaining_rolls);
  updateWheelBtn();updateRollBtn();
  var card=document.getElementById('resultCard'),ov=document.getElementById('resultOv');
  if(data.is_winner){
    spawnConfetti();playDiceWinSound();
    var extra='';
    if(data.prize.discount_code){
      extra+='<div style="margin-top:14px"><span style="font-size:.72rem;font-weight:600;color:var(--text2);display:block;margin-bottom:5px">کد تخفیف:</span><div class="code-row" onclick="copyCode(this)"><span class="code-val">'+escHtml(data.prize.discount_code)+'</span><span class="code-hint">کپی</span></div></div>';
    }
    if(data.prize.affiliate_link){
      extra+='<a href="'+escHtml(data.prize.affiliate_link)+'" target="_blank" class="aff-btn" style="margin-top:10px">مشاهده محصول 🔗</a>';
    }
    extra+=resultNoteHtml(data.prize);
    card.className='result-card win';
    card.innerHTML='<div class="r-icon">🎉</div><div class="r-badge">تبریک!</div><h3>برنده شدی!</h3><div class="r-prize">'+escHtml(data.prize.description)+'</div><p class="r-desc">گردونه رو چرخوندی و شانس با تو بود! 🎡</p>'+extra+'<button class="btn-p" onclick="closeResult();location.reload()" style="margin-top:16px">عالیه! ✨</button>';
  }else{
    playDiceLoseSound();
    card.className='result-card';
    card.innerHTML='<div class="r-icon">🎡</div><h3>نشد که بشه!</h3><p class="r-desc">'+resultEsc(RESULT_MSGS.result_lose_msg)+'</p><button class="btn-p" onclick="closeResult()">باشه</button>';
  }
  ov.classList.add('open');
}
</script>
    <?php
}
