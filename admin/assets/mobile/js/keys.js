var _testPlayerKey=null;
var _svgPlay='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"/></svg>';
var _svgStop='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="16" height="16" rx="2"/></svg>';

function toggleTestPlayer(apiKey){
  if(_testPlayerKey===apiKey){unloadTestPlayer()}
  else{loadTestPlayer(apiKey)}
}

function loadTestPlayer(apiKey){
  unloadTestPlayer();
  _testPlayerKey=apiKey;
  try{localStorage.setItem('_mapiPlayerKey',apiKey)}catch(e){}
  var s=document.createElement('script');
  s.id='testPlayerScript';
  s.src=window.location.origin+(window.RELAY&&window.RELAY.embed_js||'/embed.js');
  s.setAttribute('key',apiKey);
  document.body.appendChild(s);
  if(!document.getElementById('testPlayerStyles')){
    var st=document.createElement('style');
    st.id='testPlayerStyles';
    st.textContent='.btn-test-load.active-test{background:rgba(46,204,113,.15);color:#27ae60;border-color:rgba(46,204,113,.3)}.data-item.testing{box-shadow:inset 3px 0 0 #27ae60}';
    document.head.appendChild(st);
  }
  updateTestPlayerButtons();
}

function unloadTestPlayer(){
  if(window.__mapiPlayer&&typeof window.__mapiPlayer.destroy==='function'){
    try{window.__mapiPlayer.destroy()}catch(e){}
    window.__mapiPlayer=null;
  }
  document.querySelectorAll('[id^="mapi-player-"]').forEach(function(host){
    if(host.shadowRoot){
      host.shadowRoot.querySelectorAll('audio,video').forEach(function(a){a.pause();a.src='';a.load()});
    }
    host.querySelectorAll('audio,video').forEach(function(a){a.pause();a.src='';a.load()});
    host.remove();
  });
  document.querySelectorAll('audio,video').forEach(function(a){
    if(a.currentSrc&&a.currentSrc.indexOf('api')!==-1){a.pause();a.src='';a.load()}
  });
  var s=document.getElementById('testPlayerScript');
  if(s)s.remove();
  var styles=document.getElementById('testPlayerStyles');
  if(styles)styles.remove();
  document.querySelectorAll('style').forEach(function(st){
    if(st.id==='testPlayerStyles')return;
    if(st.textContent.indexOf('html::-webkit-scrollbar{display:none}')!==-1)st.remove();
  });
  var co=document.getElementById('mapi-consent-overlay');
  if(co)co.remove();
  var sl=document.getElementById('mapi-scroll-lock');
  if(sl)sl.remove();
  var lrc=document.querySelector('[data-mp="lrc"]');
  if(lrc)lrc.remove();
  _testPlayerKey=null;
  try{localStorage.removeItem('_mapiPlayerKey')}catch(e){}
  updateTestPlayerButtons();
}

function updateTestPlayerButtons(){
  document.querySelectorAll('.btn-test-load').forEach(function(btn){
    var key=btn.getAttribute('data-key');
    var item=btn.closest('.data-item');
    if(key===_testPlayerKey){
      btn.innerHTML=_svgStop+' 卸载';
      btn.classList.add('active-test');
      if(item)item.classList.add('testing');
    }else{
      btn.innerHTML=_svgPlay+' 加载';
      btn.classList.remove('active-test');
      if(item)item.classList.remove('testing');
    }
  });
}

(function(){
  try{var savedKey=localStorage.getItem('_mapiPlayerKey')}catch(e){return}
  if(savedKey)loadTestPlayer(savedKey);
})();