/* sw.js — سرویس‌ورکر اعلان‌های مدیر سای‌تک (نسخه ۲)
   حتی وقتی صفحه‌ی پنل بسته است، push از اینجا دریافت و نوتیفیکیشن پخش می‌شود.
   اگر پنل در پیش‌زمینه باز باشد، اعلان درون‌صفحه‌ای (پاپ‌آپ/Toast) نمایش داده می‌شود
   و برای جلوگیری از تکرار، نوتیفیکیشن سیستم پخش نمی‌شود. */
self.addEventListener('push', function (event) {
  var data = {};
  try { data = event.data ? event.data.json() : {}; } catch (e) { data = { title: 'اعلان سای‌تک', body: event.data ? event.data.text() : '' }; }
  var title = data.title || 'اعلان سای‌تک';
  var options = {
    body: data.body || '',
    icon: data.icon || undefined,
    badge: data.icon || undefined,
    tag: data.tag || 'sitak-admin',
    dir: 'rtl',
    lang: 'fa',
    data: { url: data.url || 'admin.php' }
  };
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (list) {
      for (var i = 0; i < list.length; i++) {
        if (list[i].focused) return; // پنل در پیش‌زمینه باز است — نمایش درون‌صفحه‌ای کافی است
      }
      return self.registration.showNotification(title, options);
    })
  );
});

self.addEventListener('notificationclick', function (event) {
  event.notification.close();
  var url = (event.notification.data && event.notification.data.url) || 'admin.php';
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (list) {
      for (var i = 0; i < list.length; i++) {
        var c = list[i];
        if (c.url && c.url.indexOf(url.split('?')[0]) !== -1 && 'focus' in c) { return c.focus(); }
      }
      for (var j = 0; j < list.length; j++) {
        if ('navigate' in list[j] && 'focus' in list[j]) {
          try { list[j].navigate(url); } catch (e) {}
          return list[j].focus();
        }
      }
      return clients.openWindow(url);
    })
  );
});
