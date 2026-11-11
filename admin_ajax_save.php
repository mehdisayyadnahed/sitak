<script>
(function(){
  var SYNC_FORMS = ['productForm', 'postForm', 'licenseForm', 'uploadForm'];
  function bodies(){ return document.querySelectorAll('.acc-body'); }
  function notify(msg){ if (typeof showToast === 'function') showToast(msg); }
  function parse(text){ return new DOMParser().parseFromString(text, 'text/html'); }

  // بخش‌های غیرفرم را از صفحه‌ی جدید جایگزین می‌کند (فرم‌ها دست نمی‌خورند)
  function refreshBody(cur, doc){
    var idx = Array.prototype.indexOf.call(bodies(), cur);
    var next = doc.querySelectorAll('.acc-body')[idx];
    if (idx < 0 || !next) return false;
    var C = Array.prototype.filter.call(cur.children, function(c){ return c.tagName !== 'FORM'; });
    var N = Array.prototype.filter.call(next.children, function(c){ return c.tagName !== 'FORM'; });
    var n = Math.max(C.length, N.length);
    for (var i = 0; i < n; i++) {
      if (i < N.length) {
        var clone = document.importNode(N[i], true);
        if (i < C.length) C[i].parentNode.replaceChild(clone, C[i]); else cur.appendChild(clone);
      } else if (i < C.length) { C[i].remove(); }
    }
    return true;
  }

  // همه بخش‌های acc-body را به‌روز می‌کند
  function refreshAllBodies(doc){
    var curs = bodies();
    var nexts = doc.querySelectorAll('.acc-body');
    for (var i = 0; i < Math.min(curs.length, nexts.length); i++) {
      refreshBody(curs[i], doc);
    }
  }

  // برای فرم‌هایی که حالت ویرایش دارن: مقادیر رو از حالت افزودن صفحه‌ی جدید می‌گیره
  function syncForm(cur, nw){
    cur.querySelectorAll('input, select, textarea').forEach(function(el){
      if (el.type === 'file' || el.type === 'submit' || el.type === 'button') return;
      var src = (el.id && nw.querySelector('#' + CSS.escape(el.id))) || (el.name && nw.querySelector('[name="' + el.name + '"]'));
      if (!src) return;
      if (el.type === 'checkbox' || el.type === 'radio') el.checked = src.checked; else el.value = src.value;
    });
    var rte = cur.querySelector('.rte-content'), nrte = nw.querySelector('.rte-content');
    if (rte && nrte) rte.innerHTML = nrte.innerHTML;
    // تمام تصاویر را به‌روز می‌کند (نه فقط اول)
    var curImgs = Array.prototype.filter.call(cur.querySelectorAll('img'), function(i){ return !i.closest('.rte-content'); });
    var newImgs = Array.prototype.filter.call(nw.querySelectorAll('img'), function(i){ return !i.closest('.rte-content'); });
    var fileInputs = Array.prototype.filter.call(cur.querySelectorAll('input[type="file"]'), function(f){ return f.type === 'file'; });
    var n = Math.max(curImgs.length, newImgs.length);
    for (var i = 0; i < n; i++) {
      var ci = curImgs[i], ni = newImgs[i];
      var cNode = ci ? (ci.parentElement && ci.parentElement !== cur ? ci.parentElement : ci) : null;
      var nNode = ni ? (ni.parentElement && ni.parentElement !== nw ? ni.parentElement : ni) : null;
      if (cNode && nNode) {
        cNode.outerHTML = nNode.outerHTML;
      } else if (cNode) {
        cNode.remove();
      } else if (nNode && fileInputs[i]) {
        fileInputs[i].insertAdjacentHTML('afterend', nNode.outerHTML);
      }
    }
  }

  // ثبت فرم‌ها بدون رفرش
  document.addEventListener('submit', function(e){
    var form = e.target;
    if (!form || form.tagName !== 'FORM' || e.defaultPrevented) return;
    if ((form.getAttribute('method') || 'get').toLowerCase() !== 'post') return;
    if (form.hasAttribute('data-full-reload')) return;

    // فرم‌های داخل شیت (مثل تغییر نام فایل)
    var sheet = form.closest('.adm-lo-sheet');
    if (sheet) {
      e.preventDefault();
      var fd = new FormData(form);
      if (e.submitter && e.submitter.name) fd.append(e.submitter.name, e.submitter.value);
      var btns = form.querySelectorAll('button[type="submit"], button:not([type])');
      btns.forEach(function(b){ b.disabled = true; });
      fetch(form.getAttribute('action') || location.href, {method: 'POST', body: fd, credentials: 'same-origin'})
        .then(function(r){ return r.text().then(function(t){ return {url: r.url, ok: r.ok, text: t}; }); })
        .then(function(res){
          btns.forEach(function(b){ b.disabled = false; });
          var doc = parse(res.text);
          if (!res.ok || !doc.querySelector('.acc-body')) { location.reload(); return; }
          var sheetId = sheet.id;
          if (typeof admCloseFrame === 'function') admCloseFrame(sheetId);
          refreshAllBodies(doc);
          var te = doc.getElementById('sitakToastErr');
          if (te) notify('⚠️ ' + te.getAttribute('data-msg'));
          else notify('انجام شد ✅');
        })
        .catch(function(){ location.reload(); });
      return;
    }

    var cur = form.closest('.acc-body');
    if (!cur) return;
    if (form.querySelector('input[type="file"]') && SYNC_FORMS.indexOf(form.id) < 0) return;
    e.preventDefault();
    var fd = new FormData(form);
    if (e.submitter && e.submitter.name) fd.append(e.submitter.name, e.submitter.value);
    var btns = form.querySelectorAll('button[type="submit"], button:not([type])');
    btns.forEach(function(b){ b.disabled = true; });
    var formIdx = Array.prototype.indexOf.call(document.forms, form);
    fetch(form.getAttribute('action') || location.href, {method: 'POST', body: fd, credentials: 'same-origin'})
      .then(function(r){ return r.text().then(function(t){ return {url: r.url, ok: r.ok, text: t}; }); })
      .then(function(res){
        btns.forEach(function(b){ b.disabled = false; });
        var doc = parse(res.text);
        if (!res.ok || !doc.querySelector('.acc-body')) { location.reload(); return; }

        if (form.id === 'uploadForm') {
          refreshAllBodies(doc);
          var fi = form.querySelector('input[type="file"]');
          if (fi) fi.value = '';
          var te = doc.getElementById('sitakToastErr');
          if (te) notify('⚠️ ' + te.getAttribute('data-msg'));
          else if (res.url.indexOf('imgerr=1') >= 0) notify('تصویر ذخیره نشد. فرمت یا حجم فایل را بررسی کن');
          else notify('ذخیره شد ✅');
          return;
        }

        if (!refreshBody(cur, doc)) { location.reload(); return; }
        var nf = doc.forms[formIdx];
        var cancel = Array.prototype.filter.call(form.querySelectorAll('button[id$="CancelBtn"]'), function(b){ return b.style.display !== 'none'; })[0];
        if (cancel) cancel.click();
        else if (nf && SYNC_FORMS.indexOf(form.id) >= 0) syncForm(form, nf);
        var te = doc.getElementById('sitakToastErr');
        if (te) notify('⚠️ ' + te.getAttribute('data-msg'));
        else if (res.url.indexOf('imgerr=1') >= 0) notify('تصویر ذخیره نشد. فرمت یا حجم فایل را بررسی کن');
        else notify('ذخیره شد ✅');
      })
      .catch(function(){ location.reload(); });
  });

  // تغییر وضعیت و حذف از روی لینک‌ها بدون رفرش
  document.addEventListener('click', function(e){
    var a = e.target.closest ? e.target.closest('a[href*="toggle_"], a[href*="delete_"]') : null;
    if (!a || e.defaultPrevented || e.button !== 0 || e.ctrlKey || e.metaKey || a.target === '_blank') return;
    var cur = a.closest('.acc-body');
    if (!cur) return;
    e.preventDefault();
    fetch(a.href, {credentials: 'same-origin'})
      .then(function(r){ return r.text(); })
      .then(function(t){
        var doc = parse(t);
        if (doc.querySelector('.acc-body') && refreshBody(cur, doc)) notify('انجام شد ✅');
        else location.reload();
      })
      .catch(function(){ location.reload(); });
  });
})();
</script>
