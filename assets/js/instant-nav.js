// instant-nav.js - تنقّل سريع اختياري (Prefetch + Instant Navigation)
(function(){
  if (!(window.ENABLE_PERF_TUNING)) return;
  var supportsPush = !!(window.history && history.pushState);
  if (!supportsPush) return;
  var main = document.querySelector('main');
  if (!main) return;
  var prefetchLink;
  function prefetch(url) {
    if (prefetchLink) prefetchLink.remove();
    prefetchLink = document.createElement('link');
    prefetchLink.rel = 'prefetch';
    prefetchLink.href = url;
    document.head.appendChild(prefetchLink);
  }
  document.addEventListener('mouseover', function(e) {
    var a = e.target.closest('a');
    if (!a || a.target==='_blank' || a.hasAttribute('data-no-instant')) return;
    if (a.origin !== location.origin) return;
    if (a.pathname === location.pathname) return;
    if (a.href.indexOf('#') > -1) return;
    if (a.closest('form')) return;
    prefetch(a.href);
  });
  document.addEventListener('click', function(e) {
    var a = e.target.closest('a');
    if (!a || a.target==='_blank' || a.hasAttribute('data-no-instant')) return;
    if (a.origin !== location.origin) return;
    if (a.pathname === location.pathname) return;
    if (a.closest('form')) return;
    if (a.href.indexOf('#') > -1) return;
    e.preventDefault();
    fetch(a.href, {
      credentials:'same-origin',
      timeout: 8000,
      silentError: true
    }).then(function(html){
      var dom = document.createElement('div');
      dom.innerHTML = html;
      var newMain = dom.querySelector('main');
      var newTitle = dom.querySelector('title');
      if (newMain && newTitle) {
        main.replaceWith(newMain);
        document.title = newTitle.textContent;
        history.pushState({}, '', a.href);
        window.scrollTo(0,0);
      } else {
        location.href = a.href;
      }
    }).catch(function(){ location.href = a.href; });
  });
  window.addEventListener('popstate', function(){ location.reload(); });
})();
