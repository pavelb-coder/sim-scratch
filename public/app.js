/* Scratch-only editor wrapper.
 * Uses the official scratch-gui UMD build, but disables community/cloud modes.
 * Adds local autosave (IndexedDB) and a .sb3 download button.
 */
(function () {
  'use strict';

  var appTarget = document.getElementById('app');
  var statusEl = document.getElementById('save-status');
  var downloadBtn = document.getElementById('download-btn');

  var DB = 'scratch-only-code';
  var DB_VERSION = 2;
  var STORE = 'projects';
  var KEY = 'current';
  var SAVE_DEBOUNCE = 1500;

  var vm = null;
  var ready = false;
  var restoring = false;
  var restoreStarted = false;
  var saveTimer = 0;

  function setStatus(text, kind) {
    if (!statusEl) return;
    statusEl.textContent = text;
    statusEl.className = 'save-status' + (kind ? ' is-' + kind : '');
  }

  function idb(mode, fn) {
    return new Promise(function (resolve, reject) {
      if (!window.indexedDB) return reject(new Error('no indexedDB'));
      var req = window.indexedDB.open(DB, DB_VERSION);
      req.onupgradeneeded = function () {
        if (!req.result.objectStoreNames.contains(STORE)) req.result.createObjectStore(STORE);
      };
      req.onerror = function () { reject(req.error); };
      req.onsuccess = function () {
        var db = req.result;
        var tx = db.transaction(STORE, mode);
        var out;
        var r = fn(tx.objectStore(STORE));
        if (r) r.onsuccess = function () { out = r.result; };
        tx.oncomplete = function () { db.close(); resolve(out); };
        tx.onerror = function () { db.close(); reject(tx.error); };
      };
    });
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
    return (input && input.value) || 'Scratch Project';
  }

  function setTitle(title) {
    var input = findTitleInput();
    if (!input || !title) return;
    var setter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
    setter.call(input, title);
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
  }

  function scheduleSave() {
    if (!ready || restoring || !vm) return;
    clearTimeout(saveTimer);
    setStatus('Сохраняю…');
    saveTimer = setTimeout(saveNow, SAVE_DEBOUNCE);
  }

  function saveNow() {
    saveTimer = 0;
    if (!vm || restoring) return Promise.resolve();
    var title = getTitle();
    return vm.saveProjectSb3()
      .then(function (blob) { return blob.arrayBuffer ? blob.arrayBuffer() : new Response(blob).arrayBuffer(); })
      .then(function (data) {
        return idb('readwrite', function (s) { return s.put({ data: data, title: title, savedAt: Date.now() }, KEY); });
      })
      .then(function () { setStatus('Сохранено локально', 'saved'); })
      .catch(function (err) {
        console.warn('Autosave failed', err);
        setStatus('Ошибка автосохранения', 'error');
      });
  }

  function restoreOnce() {
    if (restoreStarted || !vm) return;
    restoreStarted = true;
    idb('readonly', function (s) { return s.get(KEY); })
      .then(function (rec) {
        if (!rec || !rec.data) { ready = true; setStatus('Автосохранение включено'); return; }
        restoring = true;
        setStatus('Восстанавливаю проект…');
        return vm.loadProject(rec.data)
          .then(function () {
            setTitle(rec.title);
            setStatus('Сохранено локально', 'saved');
          })
          .catch(function (err) {
            console.warn('Restore failed', err);
            setStatus('Не удалось восстановить проект', 'error');
          })
          .then(function () { restoring = false; ready = true; });
      })
      .catch(function () { ready = true; setStatus('Автосохранение включено'); });
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
      .catch(function (err) {
        console.warn('Download failed', err);
        setStatus('Не удалось скачать .sb3', 'error');
      })
      .then(function () { downloadBtn.disabled = false; });
  }

  function boot() {
    var G = window.GUI;
    if (!G || !window.React || !window.ReactDOM) {
      appTarget.innerHTML = '<div class="loading-screen"><p>Не удалось загрузить редактор Scratch.</p><p class="loading-note">Обновите страницу или проверьте консоль.</p></div>';
      setStatus('Ошибка загрузки', 'error');
      return;
    }
    var GUIComponent = G.default || G.GUI || G;
    var AppStateHOC = G.AppStateHOC;
    var setAppElement = G.setAppElement || GUIComponent.setAppElement;
    if (!AppStateHOC) {
      appTarget.innerHTML = '<div class="loading-screen"><p>Сборка scratch-gui не содержит AppStateHOC.</p></div>';
      setStatus('Ошибка загрузки', 'error');
      return;
    }
    if (setAppElement) setAppElement(appTarget);
    var Wrapped = AppStateHOC(GUIComponent);

    var props = {
      projectId: '0',
      canEditTitle: true,
      canManageFiles: true,
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
      basePath: '/vendor/scratch-gui/dist/',
      assetHost: '/assets',
      projectHost: '/projects',
      onClickLogo: function () { window.location.href = '/'; },
      onVmInit: function (v) {
        vm = v;
        window.__scratchOnly = { vm: v, saveNow: saveNow, downloadSb3: downloadSb3 };
        v.on('PROJECT_CHANGED', scheduleSave);
        v.on('PROJECT_LOADED', restoreOnce);
      },
      onProjectLoaded: restoreOnce
    };

    var loading = appTarget.querySelector('.loading-screen');
    if (loading) loading.remove();
    window.ReactDOM.render(window.React.createElement(Wrapped, props), appTarget);
    // Local-only editor: do not use Scratch's cloud-save beforeunload prompt.
    setTimeout(function () { window.onbeforeunload = null; }, 0);
    setStatus('Загрузка проекта…');
    downloadBtn.addEventListener('click', downloadSb3);
    document.addEventListener('input', function (e) {
      if (e.target && e.target === findTitleInput()) scheduleSave();
    }, true);
    document.addEventListener('visibilitychange', function () {
      if (document.visibilityState === 'hidden' && saveTimer) {
        clearTimeout(saveTimer);
        saveNow();
      }
    });
    window.addEventListener('pagehide', function () {
      if (saveTimer) { clearTimeout(saveTimer); saveNow(); }
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
