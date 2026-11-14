/* Scratch editor integration for Moodle mod_scratch. */
(function () {
  'use strict';

  var CFG = window.SCRATCH_MOODLE || {};
  var S = CFG.strings || {};
  var appTarget = document.getElementById('app');
  var statusEl = document.getElementById('save-status');
  var saveBtn = document.getElementById('save-btn');
  var submitBtn = document.getElementById('submit-btn');
  var downloadBtn = document.getElementById('download-btn');

  var vm = null;
  var ready = false;
  var restoring = false;
  var saveTimer = 0;
  var saving = false;
  var SAVE_DEBOUNCE = 2500;

  function setStatus(text, kind) {
    if (!statusEl) return;
    statusEl.textContent = text;
    statusEl.className = 'save-status' + (kind ? ' is-' + kind : '');
  }

  function pageUrl(rel) {
    return new URL(rel, document.baseURI).href;
  }

  function findTitleInput() {
    var inputs = Array.prototype.slice.call(document.querySelectorAll('input[type="text"], input:not([type])'));
    for (var i = 0; i < inputs.length; i++) {
      var ph = (inputs[i].placeholder || '').toLowerCase();
      var cls = inputs[i].className || '';
      if (/project name|название проекта/.test(ph) || /titleField|project-title/.test(cls)) return inputs[i];
    }
    return inputs[0] || null;
  }

  function getTitle() {
    var input = findTitleInput();
    return (input && input.value) || CFG.title || 'Scratch Project';
  }

  function setTitle(title) {
    var input = findTitleInput();
    if (!input || !title) return;
    var setter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
    setter.call(input, title);
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
  }

  function installStaticShims() {
    function jsonResponse(obj) {
      return new Response(JSON.stringify(obj), { status: 200, headers: { 'Content-Type': 'application/json' } });
    }
    function isProjects(url) { return /\/projects\/?(\?|$)/.test(url); }
    function isAssetUpload(url) { return /\/assets\/[a-f0-9]{1,64}\.[a-z0-9]{1,8}(\?|$)/i.test(url); }

    var XHR = window.XMLHttpRequest.prototype;
    var origOpen = XHR.open;
    var origSend = XHR.send;
    function fakeXhr(xhr, obj) {
      var text = JSON.stringify(obj);
      setTimeout(function () {
        try {
          Object.defineProperties(xhr, {
            readyState: { configurable: true, get: function () { return 4; } },
            status: { configurable: true, get: function () { return 200; } },
            statusText: { configurable: true, get: function () { return 'OK'; } },
            responseText: { configurable: true, get: function () { return text; } },
            response: { configurable: true, get: function () { return text; } },
            responseURL: { configurable: true, get: function () { return xhr.__sc_url || ''; } }
          });
          xhr.getAllResponseHeaders = function () { return 'content-type: application/json\r\n'; };
          xhr.getResponseHeader = function (h) { return /content-type/i.test(h) ? 'application/json' : null; };
          if (typeof xhr.onreadystatechange === 'function') xhr.onreadystatechange();
          if (typeof xhr.onload === 'function') xhr.onload();
          if (typeof xhr.onloadend === 'function') xhr.onloadend();
          xhr.dispatchEvent(new Event('readystatechange'));
          xhr.dispatchEvent(new Event('load'));
          xhr.dispatchEvent(new Event('loadend'));
        } catch (e) { /* noop */ }
      }, 0);
    }
    XHR.open = function (method, url) {
      this.__sc_method = String(method || 'GET').toUpperCase();
      this.__sc_url = String(url || '');
      return origOpen.apply(this, arguments);
    };
    XHR.send = function () {
      var url = this.__sc_url || '';
      var method = this.__sc_method || 'GET';
      if ((method === 'POST' || method === 'PUT') && isProjects(url)) return fakeXhr(this, { id: 'local', 'content-name': 'local', status: 'ok' });
      if ((method === 'POST' || method === 'PUT') && isAssetUpload(url)) return fakeXhr(this, { status: 'ok' });
      return origSend.apply(this, arguments);
    };

    var origFetch = window.fetch;
    window.fetch = function (input, init) {
      var url = typeof input === 'string' ? input : (input && input.url) || '';
      var method = String((init && init.method) || (input && input.method) || 'GET').toUpperCase();
      if ((method === 'POST' || method === 'PUT') && isProjects(url)) return Promise.resolve(jsonResponse({ id: 'local', 'content-name': 'local', status: 'ok' }));
      if ((method === 'POST' || method === 'PUT') && isAssetUpload(url)) return Promise.resolve(jsonResponse({ status: 'ok' }));
      var music = url.match(/static\/extension-assets\/scratch3_music\/([a-f0-9]+\.[a-z0-9]+)(\?|$)/i);
      if (music) return origFetch.call(window, 'https://assets.scratch.mit.edu/internalapi/asset/' + music[1] + '/get/', init);
      return origFetch.apply(this, arguments);
    };
  }

  function scheduleSave() {
    if (!ready || restoring || !vm || !CFG.canEdit) return;
    clearTimeout(saveTimer);
    setStatus(S.saving || 'Saving…');
    saveTimer = setTimeout(function () { saveToMoodle('save'); }, SAVE_DEBOUNCE);
  }

  function saveToMoodle(action) {
    if (!vm || saving) return Promise.resolve();
    if (!CFG.canEdit && action !== 'download') return Promise.resolve();
    saving = true;
    if (saveBtn) saveBtn.disabled = true;
    if (submitBtn) submitBtn.disabled = true;
    setStatus(S.saving || 'Saving…');
    var title = getTitle();
    return vm.saveProjectSb3()
      .then(function (blob) {
        if (CFG.maxBytes && blob.size > CFG.maxBytes) {
          throw new Error(S.toolarge || 'File too large');
        }
        var form = new FormData();
        form.append('cmid', String(CFG.cmid));
        form.append('action', action);
        form.append('sesskey', CFG.sesskey);
        form.append('title', title);
        form.append('project', blob, 'project.sb3');
        return fetch(CFG.ajaxUrl, { method: 'POST', body: form, credentials: 'same-origin' });
      })
      .then(function (res) { return res.json().then(function (data) { return { okHttp: res.ok, data: data }; }); })
      .then(function (result) {
        if (!result.okHttp || !result.data || !result.data.ok) {
          throw new Error((result.data && result.data.error) || (S.saveerror || 'Save failed'));
        }
        CFG.status = result.data.status;
        setStatus(action === 'submit' ? (S.submitted || 'Submitted') : (S.saved || 'Saved'), 'saved');
        saving = false;
        if (saveBtn) saveBtn.disabled = false;
        if (submitBtn) submitBtn.disabled = false;
        return result.data;
      })
      .catch(function (err) {
        setStatus((S.saveerror || 'Save failed') + ': ' + err.message, 'error');
        saving = false;
        if (saveBtn) saveBtn.disabled = false;
        if (submitBtn) submitBtn.disabled = false;
        throw err;
      });
  }

  function downloadSb3() {
    if (!vm) return;
    downloadBtn.disabled = true;
    vm.saveProjectSb3()
      .then(function (blob) {
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = (getTitle() || 'project').replace(/[\\/:*?"<>|\u0000-\u001f]+/g, ' ').trim() + '.sb3';
        document.body.appendChild(a);
        a.click();
        a.remove();
        setTimeout(function () { URL.revokeObjectURL(a.href); }, 4000);
      })
      .catch(function () { setStatus(S.saveerror || 'Download failed', 'error'); })
      .then(function () { downloadBtn.disabled = false; });
  }

  function loadExistingProject() {
    if (!CFG.fileUrl || !vm) return Promise.resolve();
    restoring = true;
    setStatus(S.loading || 'Loading…');
    return fetch(CFG.fileUrl, { credentials: 'same-origin' })
      .then(function (res) {
        if (!res.ok) throw new Error('Project file not found');
        return res.arrayBuffer();
      })
      .then(function (data) { return vm.loadProject(data); })
      .then(function () {
        if (CFG.title) setTitle(CFG.title);
        setStatus(CFG.status === 'submitted' ? (S.submitted || 'Submitted') : (S.saved || 'Saved'), 'saved');
      })
      .catch(function (err) {
        setStatus((S.saveerror || 'Load failed') + ': ' + err.message, 'error');
      })
      .then(function () { restoring = false; ready = true; });
  }

  function boot() {
    var G = window.GUI;
    if (!G || !window.React || !window.ReactDOM) {
      appTarget.innerHTML = '<div class="loading-screen"><p>Scratch failed to load.</p></div>';
      setStatus(S.saveerror || 'Error', 'error');
      return;
    }
    var GUIComponent = G.default || G.GUI || G;
    var AppStateHOC = G.AppStateHOC;
    var setAppElement = G.setAppElement || GUIComponent.setAppElement;
    if (!AppStateHOC) {
      appTarget.innerHTML = '<div class="loading-screen"><p>Scratch GUI is missing AppStateHOC.</p></div>';
      return;
    }
    installStaticShims();
    if (setAppElement) setAppElement(appTarget);
    var Wrapped = AppStateHOC(GUIComponent);

    var props = {
      projectId: '0',
      canEditTitle: !!CFG.canEdit,
      canManageFiles: !!CFG.canEdit,
      canCreateNew: false,
      canSave: false,
      canCreateCopy: false,
      canRemix: false,
      canShare: false,
      enableCommunity: false,
      canUseCloud: false,
      backpackVisible: false,
      canChangeLanguage: true,
      canChangeTheme: true,
      showComingSoon: false,
      basePath: CFG.basePath,
      assetHost: CFG.assetHost,
      projectHost: pageUrl('projects'),
      onClickLogo: function () { window.location.href = CFG.returnUrl; },
      onVmInit: function (v) {
        vm = v;
        window.__scratchMoodle = { vm: v, save: function () { return saveToMoodle('save'); } };
        v.on('PROJECT_CHANGED', scheduleSave);
        v.on('PROJECT_LOADED', function () {
          if (!restoring && !ready) {
            ready = true;
            loadExistingProject();
          }
        });
      }
    };

    var loading = appTarget.querySelector('.loading-screen');
    if (loading) loading.remove();
    window.ReactDOM.render(window.React.createElement(Wrapped, props), appTarget);
    setTimeout(function () { window.onbeforeunload = null; }, 0);
    setStatus(S.loading || 'Loading…');

    if (saveBtn) saveBtn.addEventListener('click', function () { saveToMoodle('save').catch(function () {}); });
    if (submitBtn) submitBtn.addEventListener('click', function () {
      if (window.confirm(S.confirmSubmit || 'Submit?')) {
        saveToMoodle('submit').catch(function () {});
      }
    });
    if (downloadBtn) downloadBtn.addEventListener('click', downloadSb3);
    document.addEventListener('input', function (e) {
      if (e.target && e.target === findTitleInput()) scheduleSave();
    }, true);
    document.addEventListener('visibilitychange', function () {
      if (document.visibilityState === 'hidden' && saveTimer) {
        clearTimeout(saveTimer);
        saveToMoodle('save').catch(function () {});
      }
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
