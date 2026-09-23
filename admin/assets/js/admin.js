/* ═══ 顶栏导航 pill（PC端） ═══ */
var tpill=document.getElementById('topbarPill'),tnav=document.getElementById('topbarNav');
function moveTopPill(){if(!tpill||!tnav||tnav.offsetParent===null)return;var a=tnav.querySelector('.topbar-nav-item.active');if(!a){tpill.style.display='none';return}var r=a.getBoundingClientRect(),tr=tpill.parentNode.getBoundingClientRect();tpill.style.display='';tpill.style.left=(r.left-tr.left)+'px';tpill.style.top=(r.top-tr.top)+'px';tpill.style.width=r.width+'px';tpill.style.height=r.height+'px'}

/* ═══ SPA 无缝页面导航 + 水珠追踪 ═══ */
(function(){
history.scrollRestoration='manual';
var navActions=['dashboard','keys','shop','orders','users','config','settings','profile','playlist-detail'];
var bnav=document.getElementById('bottomNav'),bpill=document.getElementById('navPill');

function movePill(){
  if(!bnav||!bpill||bnav.offsetWidth===0)return;
  var a=bnav.querySelector('.nav-item.active');
  if(!a){bpill.style.display='none';return}
  bpill.style.display='';
  bpill.style.transform='translateX('+(a.offsetLeft+a.offsetWidth/2-22)+'px)';
}

function setActiveNav(action){
  document.querySelectorAll('.topbar-nav-item').forEach(function(el){
    el.classList.toggle('active',el.getAttribute('href')==='?action='+action);
  });
  moveTopPill();
  document.querySelectorAll('.bottom-nav .nav-item').forEach(function(el){
    var h=el.getAttribute('href');
    if(h&&h.indexOf('?action=')===0)el.classList.toggle('active',h==='?action='+action);
  });
  movePill();
}

function navigateTo(url,push){
  fetch(url)
    .then(function(r){if(!r.ok)throw Error('http');return r.text()})
    .then(function(html){
      var doc=new DOMParser().parseFromString(html,'text/html');
      var nw=doc.querySelector('.wrap');
      if(!nw){window.location.href=url;return}
      var ow=document.querySelector('.wrap');
      if(!ow){window.location.href=url;return}
      ow.innerHTML=nw.innerHTML;
      document.title=doc.title;
      var a=new URL(url,location.origin).searchParams.get('action');
      if(a&&navActions.indexOf(a)!==-1)setActiveNav(a);
      if(push!==false)history.pushState(null,'',url);
      ow.querySelectorAll('script').forEach(function(s){
        if(!s.textContent.trim())return;
        var m=s.textContent.match(/showToast\('((?:[^'\\]|\\.)*)','((?:[^'\\]|\\.)*)'/);
        if(m){showToast(m[1],m[2]);return}
        try{new Function(s.textContent)()}catch(e){}
      });
      if(typeof updateTestPlayerButtons==='function') updateTestPlayerButtons();
      if(typeof initDebugExpand==='function') initDebugExpand();
      initCodeMirror();
      window.scrollTo(0,0);
    })
    .catch(function(){window.location.href=url});
}
window.navigateTo=navigateTo;

document.addEventListener('click',function(e){
  var link=e.target.closest('a[href^="?action="]');
  if(!link)return;
  var a=new URL(link.href,location.origin).searchParams.get('action');
  if(a==='logout'||navActions.indexOf(a)===-1)return;
  if(link.classList.contains('active')){e.preventDefault();return;}
  e.preventDefault();
  navigateTo(link.href);
});

/* ═══ 表单提交 → SPA 保存，不刷新页面 ═══ */
document.addEventListener('submit',function(e){
  var f=e.target;
  if(f.method!=='post')return;
  var act=(f.getAttribute('action')||'').trim()||location.href;
  if(act.indexOf('?action=')===-1)return;
  e.preventDefault();
  var btn=f.querySelector('[type="submit"],button:not([type]),button[type="submit"]');
  var origTxt=btn?btn.textContent:'';
  if(btn){btn.disabled=true;btn.textContent='保存中…'}
  fetch(f.action||location.href,{method:'POST',body:new FormData(f)})
    .then(function(r){if(!r.ok)throw Error();var u=r.url;return r.text().then(function(h){return{html:h,url:u}})})
    .then(function(res){
      var doc=new DOMParser().parseFromString(res.html,'text/html');
      var nw=doc.querySelector('.wrap');if(!nw)throw Error();
      var ow=document.querySelector('.wrap');if(!ow)throw Error();
      ow.innerHTML=nw.innerHTML;
      document.title=doc.title;
      var a=new URL(res.url,location.origin).searchParams.get('action');
      if(a&&navActions.indexOf(a)!==-1)setActiveNav(a);
      if(res.url!==location.href)history.pushState(null,'',res.url);
      ow.querySelectorAll('script').forEach(function(s){
        if(!s.textContent.trim())return;
        var m=s.textContent.match(/showToast\('((?:[^'\\]|\\.)*)','((?:[^'\\]|\\.)*)'/);
        if(m){showToast(m[1],m[2]);return}
        try{new Function(s.textContent)()}catch(e){}
      });
      if(typeof updateTestPlayerButtons==='function')updateTestPlayerButtons();
      if(typeof initDebugExpand==='function')initDebugExpand();
      initCodeMirror();
    })
    .catch(function(){window.location.href=f.action||location.href})
    .finally(function(){if(btn){btn.disabled=false;btn.textContent=origTxt}});
});

window.addEventListener('popstate',function(){
  navigateTo(location.href,false);
});

// 水珠初始位置（无动画）
if(bpill){bpill.style.transition='none';movePill();bpill.offsetHeight;bpill.style.transition=''}

// 窗口大小变化时重算
window.addEventListener('resize',function(){moveTopPill();movePill()});
})();

/* ═══ 顶栏 pill 初始化 ═══ */
(function(){if(tpill){tpill.style.transition='none';moveTopPill();tpill.offsetHeight;tpill.style.transition=''}})();

/* ═══ 嵌入代码复制 ═══ */
function copyEmbedKey(key){
  var code='<script src="'+window.location.origin+(window.RELAY&&window.RELAY.embed_js||'/embed.js')+'" key="'+key+'"><\/script>';
  navigator.clipboard.writeText(code);
}

/* ═══ 个人资料页 — 主题切换 / 背景链接保存 / 背景上传 ═══ */
function toggleTheme(){
  var btn=document.getElementById('themeSwitch');if(!btn)return;
  var isDark=!btn.classList.contains('active');
  fetch('?action=profile',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'_theme_toggle=1&_csrf='+encodeURIComponent(document.querySelector('input[name=_csrf]').value)+'&mode='+(isDark?'dark':'light')})
  .then(function(r){if(r.ok){btn.classList.toggle('active',isDark);document.body.setAttribute('data-theme',isDark?'dark':'light');}});
}
function saveBgUrl(){
  var url=document.getElementById('bgUrlInput');var btn=document.getElementById('bgUrlSaveBtn');var st=document.getElementById('bgStatus');
  if(!url||!btn||!st)return;url=url.value.trim();btn.disabled=true;btn.textContent='保存中…';var _c=document.querySelector('input[name=_csrf]');
  fetch('?action=bg-url-save',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({url:url,_csrf:_c&&_c.value})})
  .then(function(r){return r.json()}).then(function(d){
    if(!d.ok)throw Error(d.error||'保存失败');
    st.style.display='block';st.style.color='#27ae60';st.textContent='壁纸链接已保存';
    if(url)document.body.style.background='#e8e8ec url('+url+') center/cover no-repeat fixed';
    else location.reload();
  }).catch(function(e){st.style.display='block';st.style.color='#d63031';st.textContent=e.message||'保存失败';})
  .finally(function(){btn.disabled=false;btn.textContent='保存链接';});
}
var bgUploading=false;
function uploadBg(){var _c=document.querySelector('input[name=_csrf]');
  if(bgUploading)return;var file=document.getElementById('bgFileInput');
  if(!file||!file.files[0]){alert('请选择文件');return}file=file.files[0];
  if(file.size>5*1024*1024){alert('文件大小不能超过5MB');return}
  if(['image/jpeg','image/png','image/webp','image/gif'].indexOf(file.type)===-1){alert('仅支持JPG/PNG/WebP/GIF');return}
  bgUploading=true;var btn=document.getElementById('bgUploadBtn');var st=document.getElementById('bgStatus');
  btn.disabled=true;btn.textContent='获取签名…';st.style.display='block';st.style.color='#666';st.textContent='';
  fetch('?action=bg-presign',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({mime:file.type,_csrf:_c&&_c.value})})
  .then(function(r){return r.json()}).then(function(d){
    if(!d.ok)throw Error(d.error||'获取签名失败');st.textContent='正在上传…';
    return fetch(d.url,{method:'PUT',body:file,headers:{'Content-Type':file.type}}).then(function(s3r){if(!s3r.ok)throw Error('上传失败HTTP '+s3r.status);return d.key;});
  }).then(function(key){st.textContent='正在保存…';
    return fetch('?action=bg-confirm',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({key:key,_csrf:_c&&_c.value})}).then(function(r){return r.json()});
  }).then(function(d){
    if(!d.ok)throw Error(d.error||'保存失败');st.style.color='#27ae60';st.textContent='上传成功！';
    var prev=document.getElementById('bgPreview');if(!prev){prev=document.createElement('div');prev.id='bgPreview';prev.style.cssText='margin-bottom:12px;border-radius:8px;overflow:hidden;max-height:160px';var card=document.querySelector('.card:nth-child(2)');if(card)card.insertBefore(prev,card.children[1]);}
    prev.innerHTML='<img src="'+d.url+'?t='+Date.now()+'" alt="当前背景" style="width:100%;height:auto;display:block;object-fit:cover;max-height:160px">';prev.style.display='block';
    document.body.style.background='#e8e8ec url('+d.url+'?t='+Date.now()+') center/cover no-repeat fixed';
  }).catch(function(e){st.style.color='#d63031';st.textContent=e.message||'上传失败';})
  .finally(function(){bgUploading=false;btn.disabled=false;btn.textContent='上传背景';});
}

/* ═══ WebAuthn 工具 ═══ */
function b64urlToBuf(s){var bin=atob(s.replace(/-/g,'+').replace(/_/g,'/'));var buf=new ArrayBuffer(bin.length);var v=new Uint8Array(buf);for(var i=0;i<bin.length;i++)v[i]=bin.charCodeAt(i);return buf}
function bufToB64url(buf){var v=new Uint8Array(buf);var bin='';for(var i=0;i<v.length;i++)bin+=String.fromCharCode(v[i]);return btoa(bin).replace(/=/g,'').replace(/\+/g,'-').replace(/\//g,'_')}

/* ═══ 通行密钥注册 ═══ */
function logErr(tag,msg){
  var fd=new FormData();
  fd.append('name',tag);
  fd.append('message',String(msg).substring(0,500));
  navigator.sendBeacon('?action=js-log',fd);
}
function pkErrMsg(e){
  var m={
    'NotAllowedError':'操作已取消或超时',
    'InvalidStateError':'该通行密钥已绑定过',
    'SecurityError':'安全限制：请使用域名或localhost访问，不支持IP地址',
    'TypeError':'请求参数异常，请刷新页面重试',
    'NetworkError':'网络连接失败，请检查网络',
    'AbortError':'操作被中断',
    'ConstraintError':'当前设备不支持通行密钥',
    'UnknownError':'认证器发生未知错误'
  };
  if(m[e.name])return m[e.name];
  if(e.message){
    if(e.message.indexOf('invalid domain')!==-1)return '域名无效：请使用域名或localhost访问，不支持IP地址';
    if(e.message.indexOf('The operation either timed out')!==-1||e.message.indexOf('timed out')!==-1)return '操作超时，请重试';
    if(e.message.indexOf('The authenticator was already registered')!==-1)return '该通行密钥已绑定过';
    if(e.message.indexOf('User consent')!==-1||e.message.indexOf('user canceled')!==-1||e.message.indexOf('cancelled')!==-1)return '操作已取消';
    if(e.message.indexOf('not available')!==-1||e.message.indexOf('not supported')!==-1)return '当前浏览器或设备不支持通行密钥';
  }
  return '绑定失败：'+(e.message||e.name||'未知错误');
}
function registerPasskey(){
  var btn=document.getElementById('pkRegBtn');btn.disabled=true;btn.textContent='绑定中...';
  var csrfToken='';
  fetch('?action=pk-begin',{credentials:'same-origin'}).then(function(r){return r.json()}).then(function(d){
    if(d.error){alert(d.error);btn.disabled=false;btn.textContent='绑定通行密钥';return}
    if(!d.challenge){alert('获取挑战失败，请刷新重试');btn.disabled=false;btn.textContent='绑定通行密钥';return}
    csrfToken=d._csrf||'';
    var rpId=d.rpId||location.hostname;
    var uid=new TextEncoder().encode(''+d.userId);
    return navigator.credentials.create({publicKey:{
      challenge:b64urlToBuf(d.challenge),
      rp:{id:rpId,name:'顺雅音乐'},
      user:{id:uid,name:d.userName,displayName:d.userName},
      pubKeyCredParams:[{type:'public-key',alg:-7},{type:'public-key',alg:-257}],
      excludeCredentials:d.excludeCredentials?d.excludeCredentials.map(function(c){return {type:'public-key',id:b64urlToBuf(c)}}):[],
      authenticatorSelection:{residentKey:'required',userVerification:'preferred'},
      timeout:d.timeout||300000
    }});
  }).then(function(cred){
    if(!cred){btn.disabled=false;btn.textContent='绑定通行密钥';return}
    var ao=bufToB64url(cred.response.attestationObject),cjd=bufToB64url(cred.response.clientDataJSON);
    var fd=new FormData();fd.append('attestationObject',ao);fd.append('clientDataJSON',cjd);fd.append('_csrf',csrfToken);
    return fetch('?action=pk-complete',{method:'POST',body:fd}).then(function(r){return r.json()});
  }).then(function(d){
    btn.disabled=false;btn.textContent='绑定通行密钥';
    if(d&&d.ok)window.location.reload();
    else if(d&&d.err){logErr('err','complete:'+d.err);alert('绑定失败：'+d.err);}
  }).catch(function(e){
    btn.disabled=false;btn.textContent='绑定通行密钥';
    logErr('catch',e.name+': '+e.message);
    alert(pkErrMsg(e));
  });
}
/* ═══ Toast ═══ */
var _toast=document.getElementById('toast'),_tt=null;
function showToast(m,t){_toast.textContent=m;_toast.className='show '+(t||'ok');clearTimeout(_tt);_tt=setTimeout(function(){_toast.className=''},2500)}

/* ═══ 密钥测试播放器 ═══ */
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

/* ═══ 调试模式初始展开状态 ═══ */
function initDebugExpand(){
  var dt = document.getElementById('debugToggle');
  if (dt && dt.checked) {
    var card = dt.closest('.card');
    if (card) card.classList.add('debug-expanded');
  }
}
initDebugExpand();

/* ═══ 调试模式开关 ═══ */
document.addEventListener('change', function(e){
  var dt = e.target;
  if (dt.id !== 'debugToggle') return;
  var on = dt.checked ? 1 : 0;
  var card = dt.closest('.card');
  if (card) {
    if (on) card.classList.add('debug-expanded');
    else card.classList.remove('debug-expanded');
  }
  var fd = new FormData();
  fd.append('debug', on);
  fetch('?action=debug-toggle', { method: 'POST', body: fd })
    .then(function(r){ return r.text(); })
    .then(function(txt){
      if (txt.trim() !== 'ok') { showToast('切换失败', 'err'); }
      else {
        var logList = document.getElementById('logList');
        if (logList) {
          fetch('?action=dashboard')
            .then(function(r){ return r.text(); })
            .then(function(html){
              var doc = new DOMParser().parseFromString(html, 'text/html');
              var newLogList = doc.getElementById('logList');
              if (newLogList) logList.innerHTML = newLogList.innerHTML;
            });
        }
      }
    })
    .catch(function(){ showToast('请求失败', 'err'); });
});

/* ═══ 清空调用记录 ═══ */
document.addEventListener('click', function(e){
  var btn = e.target.closest('#clearLogsBtn');
  if (!btn) return;
  e.preventDefault();
  if (!confirm('确认清空所有调用记录？此操作不可恢复。')) return;
  btn.disabled = true;
  btn.textContent = '清空中…';
  fetch('?action=clear-logs', { method: 'POST' })
    .then(function(r){ return r.text(); })
    .then(function(txt){
      if (txt.trim() === 'ok') {
        var logList = document.getElementById('logList');
        if (logList) logList.innerHTML = '<div class="empty">暂无调用记录</div>';
        showToast('调用记录已清空', 'ok');
      } else {
        showToast('清空失败', 'err');
      }
    })
    .catch(function(){ showToast('请求失败', 'err'); })
    .finally(function(){ btn.disabled = false; btn.textContent = '清空'; });
});

/* ═══ CodeMirror 邮件模板编辑器（SPA 导航后动态初始化） ═══ */
function initCodeMirror() {
  var ta = document.querySelector('textarea[name="mail_tpl_body"]');
  if (!ta) return;
  if (ta.nextSibling && ta.nextSibling.classList && ta.nextSibling.classList.contains('CodeMirror')) return;

  var isMobile = window.innerWidth < 768;
  var editor = CodeMirror.fromTextArea(ta, {
    mode: 'htmlmixed',
    theme: 'monokai',
    lineNumbers: !isMobile,
    lineWrapping: false,
    tabSize: 2,
    indentUnit: 2,
    matchBrackets: true,
    autoCloseTags: true,
    styleActiveLine: true
  });
  editor.setSize(null, isMobile ? 280 : 400);
  editor.on('change', function(){ editor.save(); });
  var wrapper = editor.getWrapperElement();
  wrapper.style.borderRadius = '10px';
  wrapper.style.overflow = 'hidden';
  var gutter = wrapper.querySelector('.CodeMirror-gutters');
  if (gutter) gutter.style.borderRadius = '10px 0 0 10px';
  var scroll = wrapper.querySelector('.CodeMirror-scroll');
  if (scroll) {
    scroll.style.scrollbarWidth = 'none';
    scroll.style.msOverflowStyle = 'none';
    if (isMobile) scroll.style.webkitOverflowScrolling = 'touch';
  }
}

initCodeMirror();