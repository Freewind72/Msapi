/* config.js — 系统配置页面脚本 */
(function(){
  var D = document.getElementById('config-data');
  if (!D) return;
  var csrf = D.dataset.csrf;
  var activeKeyId = 0;

  function apiPost(action, data, cb) {
    data._csrf = csrf;
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '?action=' + action, true);
    xhr.setRequestHeader('Content-Type', 'application/json');
    xhr.onload = function() {
      try { cb(JSON.parse(xhr.responseText)); } catch(e) { cb({ok:false, msg:'解析失败: ' + xhr.responseText.substring(0, 200)}); }
    };
    xhr.onerror = function() { cb({ok:false, msg:'网络错误'}); };
    xhr.send(JSON.stringify(data));
  }

  function formPost(action, data, cb) {
    var fd = new FormData();
    fd.append('_csrf', csrf);
    for (var k in data) fd.append(k, data[k]);
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '?action=' + action, true);
    xhr.onload = function() {
      try { cb(JSON.parse(xhr.responseText)); } catch(e) { cb({ok:false, msg:'解析失败: ' + xhr.responseText.substring(0, 200)}); }
    };
    xhr.onerror = function() { cb({ok:false, msg:'网络错误'}); };
    xhr.send(fd);
  }

  function toast(msg, ok) {
    var t = document.getElementById('toast');
    if (!t) return;
    t.textContent = msg;
    t.style.opacity = '1';
    t.style.transform = 'translateX(-50%) translateY(0)';
    t.style.background = ok ? 'rgba(40,200,64,.12)' : 'rgba(255,95,87,.12)';
    t.style.color = ok ? '#28c840' : '#ff5f57';
    t.style.borderColor = ok ? 'rgba(40,200,64,.2)' : 'rgba(255,95,87,.2)';
    clearTimeout(t._tid);
    t._tid = setTimeout(function() { t.style.opacity = '0'; t.style.transform = 'translateX(-50%) translateY(-20px)'; }, 2000);
  }

  document.querySelectorAll('.playlist-card-delete').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      var plId = parseInt(this.dataset.plId);
      if (!confirm('确定删除此歌单？')) return;
      apiPost('playlist-delete', { id: plId }, function(r) {
        toast(r.msg, r.ok);
        if (r.ok) setTimeout(function() { if(typeof navigateTo==='function')navigateTo(location.href,false);else location.reload(); }, 500);
      });
    });
  });

  var createModal = document.getElementById('createModal');
  document.querySelectorAll('.playlist-card-add').forEach(function(btn) {
    btn.addEventListener('click', function() {
      activeKeyId = parseInt(this.dataset.keyId);
      document.getElementById('createName').value = '';
      document.getElementById('createRemoteName').value = '';
      document.getElementById('createRemoteId').value = '';
      document.getElementById('createCoverUrl').value = '';
      document.querySelector('input[name="create_type"][value="custom"]').checked = true;
      document.querySelector('input[name="create_cover"][value="auto"]').checked = true;
      toggleCreateType();
      toggleCoverFields();
      createModal.style.display = 'flex';
    });
  });

  createModal.querySelector('.song-modal-backdrop').addEventListener('click', function() { createModal.style.display = 'none'; });
  createModal.querySelector('.song-modal-close').addEventListener('click', function() { createModal.style.display = 'none'; });

  document.querySelectorAll('input[name="create_type"]').forEach(function(r) {
    r.addEventListener('change', toggleCreateType);
  });
  document.querySelectorAll('input[name="create_cover"]').forEach(function(r) {
    r.addEventListener('change', toggleCoverFields);
  });

  function toggleCreateType() {
    var isRemote = document.querySelector('input[name="create_type"][value="remote"]').checked;
    document.getElementById('createCustomFields').style.display = isRemote ? 'none' : '';
    document.getElementById('createRemoteFields').style.display = isRemote ? '' : 'none';
  }

  function toggleCoverFields() {
    var isUrl = document.querySelector('input[name="create_cover"][value="url"]').checked;
    document.getElementById('createCoverUrl').style.display = isUrl ? '' : 'none';
  }

  document.getElementById('createSubmit').addEventListener('click', function() {
    var type = document.querySelector('input[name="create_type"]:checked').value;
    var coverMode = document.querySelector('input[name="create_cover"]:checked').value;
    var coverUrl = document.getElementById('createCoverUrl').value.trim();
    var data = { key_id: activeKeyId, type: type, cover_mode: coverMode, cover_url: coverUrl };

    if (type === 'custom') {
      data.name = document.getElementById('createName').value.trim();
      data.server = 'netease';
      if (!data.name) { toast('请输入歌单名称', false); return; }
    } else {
      data.name = document.getElementById('createRemoteName').value.trim();
      data.remote_id = document.getElementById('createRemoteId').value.trim();
      data.server = document.getElementById('createRemoteServer').value;
      if (!data.name || !data.remote_id) { toast('名称和 ID 必填', false); return; }
    }

    apiPost('playlist-create', data, function(r) {
      toast(r.msg, r.ok);
      if (r.ok) {
        createModal.style.display = 'none';
        if (type === 'custom' && r.id) {
          if(typeof navigateTo==='function')navigateTo('?action=playlist-detail&id='+r.id);else location.href='?action=playlist-detail&id='+r.id;
        } else {
          setTimeout(function() { if(typeof navigateTo==='function')navigateTo(location.href,false);else location.reload(); }, 500);
        }
      }
    });
  });

  var editModal = document.getElementById('editRemoteModal');
  document.querySelectorAll('.playlist-card-remote').forEach(function(card) {
    card.addEventListener('click', function(e) {
      if (e.target.closest('.playlist-card-delete')) return;
      document.getElementById('editPlId').value = this.dataset.plId;
      document.getElementById('editName').value = this.dataset.plName;
      document.getElementById('editRemoteId').value = this.dataset.remoteId;
      document.getElementById('editServer').value = this.dataset.server;
      document.getElementById('editCoverUrl').value = '';
      var cm = this.dataset.coverMode || 'auto';
      document.querySelector('input[name="edit_cover"][value="auto"]').checked = cm !== 'url';
      document.querySelector('input[name="edit_cover"][value="url"]').checked = cm === 'url';
      document.getElementById('editCoverUrl').style.display = cm === 'url' ? '' : 'none';
      editModal.style.display = 'flex';
    });
  });

  editModal.querySelector('.song-modal-backdrop').addEventListener('click', function() { editModal.style.display = 'none'; });
  editModal.querySelector('.song-modal-close').addEventListener('click', function() { editModal.style.display = 'none'; });

  document.querySelectorAll('input[name="edit_cover"]').forEach(function(r) {
    r.addEventListener('change', function() {
      document.getElementById('editCoverUrl').style.display = document.querySelector('input[name="edit_cover"][value="url"]').checked ? '' : 'none';
    });
  });

  document.getElementById('editSubmit').addEventListener('click', function() {
    var pid = parseInt(document.getElementById('editPlId').value);
    var name = document.getElementById('editName').value.trim();
    var remoteId = document.getElementById('editRemoteId').value.trim();
    var server = document.getElementById('editServer').value;
    var coverMode = document.querySelector('input[name="edit_cover"]:checked').value;
    var coverUrl = document.getElementById('editCoverUrl').value.trim();
    if (!name || !remoteId) { toast('名称和 ID 必填', false); return; }
    apiPost('playlist-update', { id: pid, name: name, remote_id: remoteId, server: server, cover_mode: coverMode, cover_url: coverUrl }, function(r) {
      toast(r.msg, r.ok);
      if (r.ok) setTimeout(function() { if(typeof navigateTo==='function')navigateTo(location.href,false);else location.reload(); }, 500);
    });
  });

  document.querySelectorAll('[data-need-cover]').forEach(function(el) {
    var plId = el.getAttribute('data-need-cover');
    if (!plId) return;
    apiPost('playlist-fetch-cover', { id: parseInt(plId) }, function(r) {
      if (r.ok && (r.cover_b64 || r.cover_url)) {
        var coverDiv = el.parentElement;
        var img = document.createElement('img');
        img.src = r.cover_b64 || r.cover_url;
        img.alt = '';
        img.loading = 'lazy';
        coverDiv.innerHTML = '';
        coverDiv.appendChild(img);
      }
    });
  });
})();