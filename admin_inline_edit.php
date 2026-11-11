<script>
(function(){
  document.addEventListener('click', function(e){
    var a = e.target.closest ? e.target.closest('a[data-inline-edit]') : null;
    if (!a) return;
    e.preventDefault();
    inlineEdit(a.getAttribute('href'), a.getAttribute('data-inline-edit'));
  });
  function inlineEdit(url, sel){
    fetch(url, {credentials: 'same-origin'}).then(function(r){ return r.text(); }).then(function(html){
      var doc = new DOMParser().parseFromString(html, 'text/html');
      var nf = doc.querySelector(sel), cf = document.querySelector(sel);
      if (!nf || !cf) { location.href = url; return; }
      cf.querySelectorAll('input, select, textarea, button').forEach(function(el){
        if (el.type === 'file') return;
        var src = null;
        if (el.id) src = nf.querySelector('#' + el.id);
        if (!src && el.name) src = nf.querySelector('[name="' + el.name + '"]');
        if (!src) return;
        if (el.tagName === 'BUTTON') {
          if (el.type === 'submit') el.textContent = src.textContent;
          if (el.id) el.style.display = src.style.display;
          return;
        }
        if (el.type === 'checkbox' || el.type === 'radio') el.checked = src.checked;
        else el.value = src.value;
      });
      var rte = cf.querySelector('.rte-content');
      var srcDesc = nf.querySelector('textarea[style*="display:none"]');
      var curDesc = cf.querySelector('textarea[style*="display:none"]');
      if (srcDesc && curDesc) curDesc.value = srcDesc.value;
      if (rte && srcDesc) rte.innerHTML = srcDesc.value;
      var ci = Array.prototype.filter.call(cf.querySelectorAll('img'), function(i){ return !i.closest('.rte-content'); })[0];
      var ni = Array.prototype.filter.call(nf.querySelectorAll('img'), function(i){ return !i.closest('.rte-content'); })[0];
      // تصویر کاور/محصول: اگر فرم قبلاً تصویر نداشت، بلوک تصویر جدید بعد از فیلد فایل اضافه می‌شود
      var fileInp = cf.querySelector('input[type="file"]');
      var cNode = ci ? (ci.parentElement && ci.parentElement !== cf ? ci.parentElement : ci) : null;
      var nNode = ni ? (ni.parentElement && ni.parentElement !== nf ? ni.parentElement : ni) : null;
      if (cNode && nNode) cNode.outerHTML = nNode.outerHTML;
      else if (cNode) cNode.remove();
      else if (nNode && fileInp) fileInp.insertAdjacentHTML('afterend', nNode.outerHTML);
      if (typeof window.syncEditUI === 'function') window.syncEditUI();
      cf.scrollIntoView({behavior: 'smooth', block: 'start'});
      var first = cf.querySelector('input[type="text"]');
      if (first) first.focus();
    }).catch(function(){ location.href = url; });
  }
})();
</script>
