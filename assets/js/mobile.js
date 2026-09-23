/* ═══════════ MOBILE — Smooth Scroll ═══════════ */
(function(){
  /* Smooth scroll with passive listener optimization */
  document.querySelectorAll('a[href^="#"]').forEach(function(a){
    a.addEventListener('click',function(e){
      e.preventDefault();
      var t = document.querySelector(a.getAttribute('href'));
      if(t) t.scrollIntoView({behavior:'smooth',block:'start'});
    },{passive:false});
  });

  /* Touch-optimized context menu */
  var menu = document.getElementById('contextMenu');
  if(menu){
    menu.querySelectorAll('a').forEach(function(a){
      a.addEventListener('touchend',function(e){
        e.preventDefault();
        var href = a.getAttribute('href');
        if(href && href.startsWith('#')){
          var t = document.querySelector(href);
          if(t) t.scrollIntoView({behavior:'smooth',block:'start'});
        }else if(href){
          window.location.href = href;
        }
        menu.classList.remove('visible');
      });
    });
  }

  /* Passive scroll for spectrum parallax on mobile */
  var bars = document.querySelectorAll('.spec-bar');
  if(bars.length){
    var ticking = false;
    window.addEventListener('scroll',function(){
      if(!ticking){
        requestAnimationFrame(function(){
          var scrollY = window.scrollY;
          bars.forEach(function(bar,i){
            var factor = .03 + (i % 4) * .015;
            bar.style.transform = 'scaleY('+(.7+Math.sin(scrollY*factor+i)*.2)+')';
          });
          ticking = false;
        });
        ticking = true;
      }
    },{passive:true});
  }

  /* Touch-friendly hero CTA hover state reset */
  var heroCta = document.querySelector('.hero-cta');
  if(heroCta){
    heroCta.addEventListener('touchstart',function(){
      this.style.transform = 'translateY(-2px)';
    },{passive:true});
    heroCta.addEventListener('touchend',function(){
      this.style.transform = '';
    },{passive:true});
  }
})();
