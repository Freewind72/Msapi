/* settings.js — 设置页面脚本（移动端） */
(function(){var m=document.getElementById('mailTemplateModal');if(m&&m.parentElement!==document.body){var s=document.body.querySelector('#mailTemplateModal');if(s)s.remove();document.body.appendChild(m)}})()

function openMailTemplateModal(id){
  var idEl=document.getElementById('tplId'),
      nm=document.getElementById('tplName'),sj=document.getElementById('tplSubject'),
      ht=document.getElementById('tplHtml'),title=document.getElementById('tplModalTitle'),
      ta=document.querySelector('textarea[name="mail_tpl_body"]');
  var modal=document.getElementById('mailTemplateModal');
  if(modal&&modal.parentElement!==document.body){var s=document.body.querySelector('#mailTemplateModal');if(s)s.remove();document.body.appendChild(modal)}

  if(id>0){
    var card=document.getElementById('tplItem'+id);
    if(card){
      nm.value=card.getAttribute('data-name')||'';
      sj.value=card.getAttribute('data-subject')||'';
      var bodyEl=card.querySelector('.tpl-body-data');
      var body=bodyEl?bodyEl.value:'';
      ht.value=card.getAttribute('data-html')||'0';
      idEl.value=id;
      title.textContent='编辑邮件模板';
      if(ta&&ta.nextSibling&&ta.nextSibling.CodeMirror){ta.nextSibling.CodeMirror.setValue(body)}
      else if(ta){ta.value=body}
    }
  }else{
    nm.value='';sj.value='';ht.value='0';idEl.value='0';
    title.textContent='新建邮件模板';
    if(ta&&ta.nextSibling&&ta.nextSibling.CodeMirror){ta.nextSibling.CodeMirror.setValue('')}
    else if(ta){ta.value=''}
  }

  modal.style.display='flex';
  if(typeof initCodeMirror==='function')initCodeMirror();
  if(ta&&ta.nextSibling&&ta.nextSibling.CodeMirror){ta.nextSibling.CodeMirror.refresh()}
}

function closeMailTemplateModal(){document.getElementById('mailTemplateModal').style.display='none'}

document.addEventListener('keydown',function(e){if(e.key==='Escape'){var m=document.getElementById('mailTemplateModal');if(m&&m.style.display==='flex')closeMailTemplateModal()}})

function handleMailTemplateSubmit(e){
  e.preventDefault();e.stopPropagation();
  var f=e.target,btn=document.querySelector('.tpl-modal-footer [type="submit"]'),orig=btn.textContent;
  var ta=document.querySelector('textarea[name="mail_tpl_body"]');
  if(ta&&ta.nextSibling&&ta.nextSibling.CodeMirror){ta.nextSibling.CodeMirror.save()}
  btn.disabled=true;btn.textContent='保存中…';
  var fd=new FormData(f);
  fetch(f.action,{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}})
    .then(function(r){return r.json()})
    .then(function(res){
      btn.disabled=false;btn.textContent=orig;
      if(res.ok){
        if(typeof showToast==='function')showToast('邮件模板已保存','ok');closeMailTemplateModal();
        var tplId=parseInt(document.getElementById('tplId').value)||res.id||0;
        var nm=document.getElementById('tplName').value.trim()||'未命名模板';
        var sj=document.getElementById('tplSubject').value.trim()||'未设置';
        var bd=document.getElementById('tplBody').value;
        var ht=parseInt(document.getElementById('tplHtml').value)||0;
        var preview=bd.replace(/<[^>]*>/g,'');
        if(preview.length>60)preview=preview.substring(0,60)+'\u2026';
        if(!preview)preview='空正文';
        updateTplCard(tplId,nm,sj,preview,ht,bd)
      }else{if(typeof showToast==='function')showToast(res.error||'保存失败','err')}
    })
    .catch(function(){btn.disabled=false;btn.textContent=orig;if(typeof showToast==='function')showToast('网络错误','err')})
  return false
}

function updateTplCard(id,name,subject,preview,isHtml,body){
  var card=document.getElementById('tplItem'+id);
  if(!card){
    var grid=document.querySelector('.tpl-list-grid');
    if(!grid){
      var wrap=document.getElementById('tplCardBody');
      var empty=wrap.querySelector('.empty');
      if(empty)empty.remove();
      grid=document.createElement('div');grid.className='tpl-list-grid';wrap.insertBefore(grid,wrap.lastElementChild)
    }
    card=document.createElement('div');card.className='tpl-item';card.id='tplItem'+id;
    grid.appendChild(card)
  }
  card.setAttribute('data-name',name);card.setAttribute('data-subject',subject);card.setAttribute('data-html',isHtml?'1':'0');
  var isDef=card.classList.contains('tpl-item-default');
  var defHtml='<span class="tpl-badge-fmt">'+(isHtml?'HTML':'纯文本')+'</span>'+(isDef?'<span class="tpl-badge-def">默认</span>':'');
  card.innerHTML='<div class="tpl-item-top">'
    +'<span class="tpl-item-name">'+escapeHtml(name)+'</span>'
    +defHtml
    +'</div>'
    +'<div class="tpl-item-subject">主题：'+escapeHtml(subject||'未设置')+'</div>'
    +'<div class="tpl-item-preview">'+escapeHtml(preview)+'</div>'
    +'<textarea class="tpl-body-data" style="display:none">'+escapeHtml(body||'')+'</textarea>'
    +'<div class="tpl-item-actions">'
    +'<button class="btn btn-sm btn-outline" onclick="openMailTemplateModal('+id+')">编辑</button>'
    +(isDef?'':'<button class="btn btn-sm btn-outline" onclick="setDefaultTemplate('+id+')">设默认</button>')
    +'<button class="btn btn-sm btn-outline btn-danger-outline" onclick="deleteTemplate('+id+')">删除</button>'
    +'</div>'
}

function escapeHtml(s){var d=document.createElement('div');d.textContent=s;return d.innerHTML}

function deleteTemplate(id){
  if(!confirm('确认删除该邮件模板？'))return;
  var csrfEl=document.querySelector('input[name="_csrf"]');
  var fd=new FormData();fd.append('_mail_tpl_delete','1');fd.append('_mail_tpl_id',id);
  if(csrfEl)fd.append('_csrf',csrfEl.value);
  fetch('?action=settings',{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}})
    .then(function(r){return r.json()})
    .then(function(res){
      if(res.ok){
        if(typeof showToast==='function')showToast('模板已删除','ok');
        var card=document.getElementById('tplItem'+id);if(card)card.remove();
        var grid=document.querySelector('.tpl-list-grid');
        if(grid&&!grid.querySelector('.tpl-item')){
          var wrap=document.getElementById('tplCardBody');
          grid.remove();
          var empty=document.createElement('div');empty.className='empty';empty.textContent='暂无邮件模板';
          wrap.insertBefore(empty,wrap.lastElementChild)
        }
      }else{if(typeof showToast==='function')showToast('删除失败','err')}
    })
    .catch(function(){if(typeof showToast==='function')showToast('网络错误','err')})
}

function setDefaultTemplate(id){
  var csrfEl=document.querySelector('input[name="_csrf"]');
  var fd=new FormData();fd.append('_mail_tpl_default','1');fd.append('_mail_tpl_id',id);
  if(csrfEl)fd.append('_csrf',csrfEl.value);
  fetch('?action=settings',{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}})
    .then(function(r){return r.json()})
    .then(function(res){
      if(res.ok){
        if(typeof showToast==='function')showToast('已设为默认模板','ok');
        var items=document.querySelectorAll('.tpl-item');
        for(var i=0;i<items.length;i++){
          var item=items[i];item.classList.remove('tpl-item-default');
          var defBadge=item.querySelector('.tpl-badge-def');if(defBadge)defBadge.remove();
          var act=item.querySelector('.tpl-item-actions');
          if(act){
            var btns=act.querySelectorAll('button');
            for(var j=btns.length-1;j>=0;j--){
              if(btns[j].textContent.indexOf('设默认')!==-1)btns[j].remove()
            }
            if(parseInt(item.id.replace('tplItem',''))===id){
              var isHtml=item.getAttribute('data-html')==='1';
              item.classList.add('tpl-item-default');
              var top=item.querySelector('.tpl-item-top');
              if(top){
                var fmtBadge=top.querySelector('.tpl-badge-fmt');
                var defSpan=document.createElement('span');
                defSpan.className='tpl-badge-def';defSpan.textContent='默认';
                if(fmtBadge)fmtBadge.parentNode.insertBefore(defSpan,fmtBadge.nextSibling)
              }
            }
          }
        }
      }else{if(typeof showToast==='function')showToast('设置失败','err')}
    })
    .catch(function(){if(typeof showToast==='function')showToast('网络错误','err')})
}