var _toast=document.getElementById('toast'),_tt=null;
function showToast(m,t){_toast.textContent=m;_toast.className='show '+(t||'ok');clearTimeout(_tt);_tt=setTimeout(function(){_toast.className=''},2500)}
function showConfirm(e,f,m){e.preventDefault();if(!confirm(m))return false;f.submit();return false}

(function(){
history.scrollRestoration='manual';
var navActions=['dashboard','keys','users','config','settings','profile','playlist-detail'];

function setActiveNav(action){
  document.querySelectorAll('.sidebar .sb-item').forEach(function(el){
    el.classList.toggle('active',el.getAttribute('href')==='?action='+action);
  });
}

function navigateTo(url,push){
  var wrap=document.querySelector('.wrap');
  if(!wrap){window.location.href=url;return}
  wrap.classList.add('nav-out');
  fetch(url)
    .then(function(r){if(!r.ok)throw Error('http');return r.text()})
    .then(function(html){
      var doc=new DOMParser().parseFromString(html,'text/html');
      var nw=doc.querySelector('.wrap');
      if(!nw){wrap.classList.remove('nav-out');window.location.href=url;return}
      setTimeout(function(){
        wrap.innerHTML=nw.innerHTML;
        wrap.classList.remove('nav-out');
        wrap.classList.add('nav-in');
        setTimeout(function(){wrap.classList.remove('nav-in')},420);
        document.title=doc.title;
        var nc=doc.querySelector('#page-css');
        if(nc){var oc=document.querySelector('#page-css');if(oc)oc.href=nc.href}
        var a=new URL(url,location.origin).searchParams.get('action');
        if(a&&navActions.indexOf(a)!==-1)setActiveNav(a);
        if(push!==false)history.pushState(null,'',url);
        wrap.querySelectorAll('script').forEach(function(s){
          if(!s.textContent.trim())return;
          var m=s.textContent.match(/showToast\('((?:[^'\\]|\\.)*)','((?:[^'\\]|\\.)*)'/);
          if(m){showToast(m[1],m[2]);return}
          try{new Function(s.textContent)()}catch(e){}
        });
        if(typeof updateTestPlayerButtons==='function') updateTestPlayerButtons();
        if(typeof initDebugExpand==='function') initDebugExpand();
        initCodeMirror();
        wrap.scrollTo(0,0);
      },200);
    })
    .catch(function(){wrap.classList.remove('nav-out');window.location.href=url});
}
window.navigateTo=navigateTo;

var sb=document.getElementById('sidebar'),sbb=document.getElementById('sbBrand');
function applyCollapse(c){sb.classList.toggle('collapsed',c);if(sbb){var t=c?'展开侧边栏':'折叠侧边栏';sbb.setAttribute('title',t);sbb.setAttribute('aria-label',t)}}
if(sb&&sbb){var isCollapsed=localStorage.getItem('sidebar_collapsed')==='1';applyCollapse(isCollapsed);sbb.addEventListener('click',function(e){e.preventDefault();e.stopPropagation();var c=!sb.classList.contains('collapsed');applyCollapse(c);localStorage.setItem('sidebar_collapsed',c?'1':'0')})}

document.addEventListener('click',function(e){
  var link=e.target.closest('a[href^="?action="]');
  if(!link)return;
  var a=new URL(link.href,location.origin).searchParams.get('action');
  if(a==='logout'||navActions.indexOf(a)===-1)return;
  if(link.classList.contains('active')){e.preventDefault();return;}
  e.preventDefault();
  navigateTo(link.href);
});

document.addEventListener('submit',function(e){
  if(e.defaultPrevented)return;
  var f=e.target;
  if(f.method!=='post')return;
  if(f.hasAttribute('data-modal'))return;
  var act=(f.getAttribute('action')||'').trim()||location.href;
  if(act.indexOf('?action=')===-1)return;
  e.preventDefault();
  var btn=f.querySelector('[type="submit"],button:not([type]),button[type="submit"]');
  var origTxt=btn?btn.textContent:'';
  if(btn){btn.disabled=true;btn.textContent='保存中…'}
  var ow=document.querySelector('.wrap');
  if(!ow){window.location.href=f.action||location.href;return}
  var targetAct=new URL(act,location.origin).searchParams.get('action')||'';
  var currentAct=new URL(location.href).searchParams.get('action')||'';
  var samePage=targetAct===currentAct;
  if(!samePage)ow.classList.add('nav-out');
  fetch(f.action||location.href,{method:'POST',body:new FormData(f)})
    .then(function(r){if(!r.ok)throw Error();var u=r.url;return r.text().then(function(h){return{html:h,url:u}})})
    .then(function(res){
      var doc=new DOMParser().parseFromString(res.html,'text/html');
      var nw=doc.querySelector('.wrap');if(!nw){if(!samePage)ow.classList.remove('nav-out');window.location.href=f.action||location.href;return}
      if(samePage){
        var scr=ow.scrollTop;
        ow.innerHTML=nw.innerHTML;
        document.title=doc.title;
        var nc0=doc.querySelector('#page-css');
        if(nc0){var oc0=document.querySelector('#page-css');if(oc0)oc0.href=nc0.href}
        var a0=new URL(res.url,location.origin).searchParams.get('action');
        if(a0&&navActions.indexOf(a0)!==-1)setActiveNav(a0);
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
        ow.scrollTop=scr;
        return;
      }
      setTimeout(function(){
        ow.innerHTML=nw.innerHTML;
        ow.classList.remove('nav-out');
        void ow.offsetHeight;
        ow.classList.add('nav-in');
        setTimeout(function(){ow.classList.remove('nav-in')},420);
        document.title=doc.title;
        var nc=doc.querySelector('#page-css');
        if(nc){var oc=document.querySelector('#page-css');if(oc)oc.href=nc.href}
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
        ow.scrollTo(0,0);
      },240);
    })
    .catch(function(){if(!samePage&&ow)ow.classList.remove('nav-out');window.location.href=f.action||location.href})
    .finally(function(){if(btn){btn.disabled=false;btn.textContent=origTxt}});
});

window.addEventListener('popstate',function(){
  navigateTo(location.href,false);
});
})();

function copyEmbedKey(key){
  var code='<script src="'+window.location.origin+(window.RELAY&&window.RELAY.embed_js||'/embed.js')+'" key="'+key+'"><\/script>';
  navigator.clipboard.writeText(code);
}

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

(function(){
  var curUser = (document.querySelector('.sb-username') || {}).textContent || '';
  var pusher = new Pusher('333723b6068a283d1b6b', { cluster: 'ap3', channelAuthorization: { endpoint: '?action=pusher-auth', transport: 'ajax' } });
  var channel = pusher.subscribe('presence-admin-online');

  channel.bind('user-online', function(data) {
    if(data.username && data.username !== curUser) showFloatingToast(data.username + ' 上线了');
  });

  channel.bind('user-offline', function(data) {
    if(data.username && data.username !== curUser) showFloatingToast(data.username + ' 离线了');
  });

  window.pusherOnlineIds = {};
  window.pusherOnlineReady = false;

  channel.bind('user-online', function(data) {
    if(data.user_id){
      window.pusherOnlineIds[data.user_id] = true;
      window.pusherOnlineReady = true;
      var el = document.querySelector('.online-dot[data-uid="'+data.user_id+'"]');
      if(el){ el.style.background='#27ae60'; el.title='在线'; }
    }
  });

  channel.bind('user-offline', function(data) {
    if(data.user_id){
      delete window.pusherOnlineIds[data.user_id];
      var el = document.querySelector('.online-dot[data-uid="'+data.user_id+'"]');
      if(el){ el.style.background='#b2bec3'; el.title='离线'; }
    }
  });
})();

function showFloatingToast(msg){
  var el = document.createElement('div');
  el.className = 'float-toast';
  el.textContent = msg;
  document.body.appendChild(el);
  requestAnimationFrame(function(){ el.classList.add('show'); });
  setTimeout(function(){ el.classList.remove('show'); setTimeout(function(){ el.remove(); }, 400); }, 3000);
}
window.showFloatingToast = showFloatingToast;