/* ═══════════ STARFIELD ═══════════ */
(function(){
  var container = document.getElementById('starfield');
  if(!container) return;
  var frag = document.createDocumentFragment();
  for(var i=0;i<60;i++){
    var s = document.createElement('div');
    s.className='star';
    s.style.cssText = [
      'left:'+Math.random()*100+'%',
      'top:'+Math.random()*100+'%',
      'width:'+(Math.random()*2+1)+'px',
      'height:'+(Math.random()*2+1)+'px',
      '--tw-dur:'+(Math.random()*4+3)+'s',
      '--tw-delay:'+(Math.random()*5)+'s',
      '--tw-peak:'+(Math.random()*.5+.2)
    ].join(';');
    frag.appendChild(s);
  }
  container.appendChild(frag);
})();

/* ═══════════ SPECTRUM BARS ═══════════ */
(function(){
  var stage = document.getElementById('spectrumStage');
  if(!stage) return;
  var colors = ['#c8965a','#c49055','#b8854f','#a87a4a','#9e7042','#8c5ee0','#7c5ce7','#6e4edb','#6040cf','#c8965a','#b8854f','#8c5ee0'];
  var frag = document.createDocumentFragment();
  for(var i=0;i<16;i++){
    var bar = document.createElement('div');
    bar.className='spec-bar';
    var x = 2 + (i * 6) + (Math.random()*4-2);
    var h = 15 + Math.random()*55;
    bar.style.cssText = [
      '--sb-x:'+x+'%',
      '--sb-h:'+h+'%',
      '--sb-w:'+(2+Math.random()*3)+'px',
      '--sb-color:'+colors[i % colors.length],
      '--sb-dur:'+(2+Math.random()*2.4)+'s',
      '--sb-delay:'+(Math.random()*3)+'s'
    ].join(';');
    frag.appendChild(bar);
  }
  stage.appendChild(frag);
})();

/* ═══════════ CONTEXT MENU ═══════════ */
(function(){
  var menu = document.getElementById('contextMenu');
  if(!menu) return;
  document.addEventListener('contextmenu',function(e){
    e.preventDefault();
    var x = e.clientX, y = e.clientY;
    menu.classList.add('visible');
    var mw = menu.offsetWidth || 160;
    var mh = menu.offsetHeight || 120;
    if(x + mw > window.innerWidth) x = window.innerWidth - mw - 8;
    if(y + mh > window.innerHeight) y = window.innerHeight - mh - 8;
    menu.style.left = x + 'px';
    menu.style.top = y + 'px';
  });
  document.addEventListener('click',function(e){
    if(!menu.contains(e.target)) menu.classList.remove('visible');
  });
  menu.querySelectorAll('a').forEach(function(a){
    a.addEventListener('click',function(){ menu.classList.remove('visible'); });
  });
})();

/* ═══════════ INTERSECTION OBSERVER (Anim Fade-Up) ═══════════ */
(function(){
  var observer = new IntersectionObserver(function(entries){
    entries.forEach(function(entry){
      if(entry.isIntersecting){
        entry.target.classList.add('visible');
      }
    });
  },{threshold:.15});

  document.querySelectorAll('.anim-fade-up').forEach(function(el){
    observer.observe(el);
  });
})();

/* ═══════════ TERMINAL TYPEWRITER ═══════════ */
(function(){
  var win = document.getElementById('terminalWin');
  var lines = document.querySelectorAll('#terminalBody .terminal-line');
  if(!win || !lines.length) return;
  var typed = false;
  var observer = new IntersectionObserver(function(entries){
    if(entries[0].isIntersecting && !typed){
      typed = true;
      lines.forEach(function(line,i){
        setTimeout(function(){line.classList.add('visible');},i*400);
      });
    }
  },{threshold:.4});
  observer.observe(win);
})();

/* ═══════════ SPECTRUM SCROLL PARALLAX ═══════════ */
(function(){
  var bars = document.querySelectorAll('.spec-bar');
  if(!bars.length) return;
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
})();

/* ═══════════ COUNTER ANIMATION ═══════════ */
(function(){
  var counters = document.querySelectorAll('.hero-stat-value[data-target]');
  counters.forEach(function(el){
    var raw = el.getAttribute('data-target').replace(/,/g,'');
    var target = parseInt(raw,10);
    if(isNaN(target)||target===0) return;
    var observer = new IntersectionObserver(function(entries){
      if(entries[0].isIntersecting){
        var start = 0;
        var duration = 1800;
        var startTime = null;
        function step(ts){
          if(!startTime) startTime=ts;
          var progress = Math.min((ts-startTime)/duration,1);
          var eased = 1 - Math.pow(1-progress,3);
          el.textContent = Math.round(eased*target).toLocaleString();
          if(progress<1) requestAnimationFrame(step);
          else el.textContent = target.toLocaleString();
        }
        requestAnimationFrame(step);
        observer.disconnect();
      }
    },{threshold:.5});
    observer.observe(el);
  });
})();

/* ═══════════ SMOOTH SCROLL ═══════════ */
document.querySelectorAll('a[href^="#"]').forEach(function(a){
  a.addEventListener('click',function(e){
    e.preventDefault();
    var t = document.querySelector(a.getAttribute('href'));
    if(t) t.scrollIntoView({behavior:'smooth',block:'start'});
  });
});

/* ═══════════ HERO SPECTRUM PARALLAX ON LOAD ═══════════ */
(function(){
  var bars = document.querySelectorAll('.spec-bar');
  if(!bars.length) return;
  var scrollY = window.scrollY;
  bars.forEach(function(bar,i){
    var factor = .03 + (i % 4) * .015;
    bar.style.transform = 'scaleY('+(.7+Math.sin(scrollY*factor+i)*.2)+')';
  });
})();
