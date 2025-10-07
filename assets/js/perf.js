// perf.js - تحسينات خفيفة للأداء (صور + مراقبة CLS + Service Worker)
(function(){
  'use strict';
  
  // إضافة loading="lazy" تلقائيًا للصور خارج الـ viewport
  if ('loading' in HTMLImageElement.prototype) {
    document.addEventListener('DOMContentLoaded', function() {
      var imgs = document.querySelectorAll('img:not([loading])');
      var viewportHeight = window.innerHeight;
      
      for (var i = 0; i < imgs.length; i++) {
        var img = imgs[i];
        // لا تلمس صور الهيدر أو اللوغو أو صور فوق الطية
        if (img.getBoundingClientRect().top > viewportHeight * 0.7) {
          img.setAttribute('loading', 'lazy');
          img.setAttribute('decoding', 'async');
        }
      }
    });
  }
  
  // إضافة أبعاد width/height تلقائيًا للصور التي تفتقدها
  if ('requestIdleCallback' in window) {
    requestIdleCallback(function() {
      var imgs = document.querySelectorAll('img');
      for (var i = 0; i < imgs.length; i++) {
        var img = imgs[i];
        if (!img.hasAttribute('width') && img.naturalWidth) {
          img.setAttribute('width', img.naturalWidth);
        }
        if (!img.hasAttribute('height') && img.naturalHeight) {
          img.setAttribute('height', img.naturalHeight);
        }
      }
    });
  }
  
  // Service Worker registration (non-blocking)
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
      navigator.serviceWorker.register('/sw.js').catch(function() {
        // Silent fail - SW is optional enhancement
      });
    });
  }
  
  // مراقبة CLS (تشخيص فقط، لا إرسال بيانات)
  if ('PerformanceObserver' in window) {
    try {
      var clsValue = 0;
      new PerformanceObserver(function(list) {
        var entries = list.getEntries();
        for (var i = 0; i < entries.length; i++) {
          if (!entries[i].hadRecentInput) {
            clsValue += entries[i].value;
          }
        }
        window.__CLS_DEBUG = clsValue;
      }).observe({type: 'layout-shift', buffered: true});
    } catch(e){}
  }
})();
