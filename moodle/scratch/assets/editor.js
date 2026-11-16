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

  /* ---------------------------------------------------------------------------------------
   * Hardware support (LEGO WeDo 2.0 / LEGO MINDSTORMS EV3).
   *
   * The bundled Scratch GUI already contains both extensions and the Scratch Link client.
   * Scratch Link is a program installed on the user computer that listens on
   * ws://127.0.0.1:20111 and talks to the LEGO hub over Bluetooth. The GUI normally also opens
   * a fallback connection to the hosted Scratch relay at wss://device-manager.scratch.mit.edu
   * and uses whichever answers first. We only want the local connection, so device data never
   * leaves the computer: the relay socket is turned into an inert object that never connects.
   * ------------------------------------------------------------------------------------ */
  var CLOUD_RELAY_PATTERN = /^wss?:\/\/device-manager\.scratch\.mit\.edu(:\d+)?\//i;

  function blockCloudRelay() {
    var HW = CFG.hardware || {};
    if (HW.allowCloudRelay) return;
    var NativeWebSocket = window.WebSocket;
    if (!NativeWebSocket || NativeWebSocket.__scratchMoodlePatched) return;

    function LocalOnlyWebSocket(url, protocols) {
      if (CLOUD_RELAY_PATTERN.test(String(url))) {
        return createInertSocket(url);
      }
      return protocols === undefined ? new NativeWebSocket(url) : new NativeWebSocket(url, protocols);
    }
    // Keep the constructor contract of the native object (static constants, prototype).
    LocalOnlyWebSocket.prototype = NativeWebSocket.prototype;
    LocalOnlyWebSocket.CONNECTING = NativeWebSocket.CONNECTING;
    LocalOnlyWebSocket.OPEN = NativeWebSocket.OPEN;
    LocalOnlyWebSocket.CLOSING = NativeWebSocket.CLOSING;
    LocalOnlyWebSocket.CLOSED = NativeWebSocket.CLOSED;
    LocalOnlyWebSocket.__scratchMoodlePatched = true;
    Object.defineProperty(LocalOnlyWebSocket, 'name', { value: 'WebSocket' });
    window.WebSocket = LocalOnlyWebSocket;
  }

  /**
   * A WebSocket stand-in that never connects and never fires events.
   *
   * The GUI races the local and the relay socket and closes the loser. A relay socket that
   * merely failed would be reported as an error, so this one stays silent forever and simply
   * loses the race to the local Scratch Link connection.
   */
  function createInertSocket(url) {
    var socket = {
      url: String(url),
      readyState: 0, // CONNECTING
      binaryType: 'blob',
      bufferedAmount: 0,
      extensions: '',
      protocol: '',
      onopen: null,
      onclose: null,
      onerror: null,
      onmessage: null,
      send: function () {},
      close: function () { socket.readyState = 3; },
      addEventListener: function () {},
      removeEventListener: function () {},
      dispatchEvent: function () { return true; }
    };
    return socket;
  }

  /**
   * Stop the Settings menu from blinking.
   *
   * When a dropdown opens, the GUI calls scrollIntoView({block: 'center'}) on the menu item of
   * the current language. Inside its own layout that scrolls the menu, but here the scrollable
   * ancestor is the editor page (#app), so the whole editor jumps a few dozen pixels. The menu
   * slides out from under the pointer, closes, and the pointer then reopens it — the menu
   * flickers while it is being used.
   *
   * Scrolling is therefore limited to elements that are genuine, height constrained scroll
   * containers. The menu inside the editor page no longer moves, the page stays put, and the
   * language list still scrolls to the selected language where the GUI does give it its own
   * scroll area.
   */
  function stabiliseMenuScrolling() {
    var native = Element.prototype.scrollIntoView;
    if (!native || native.__scratchMoodlePatched) return;

    // The menu lists carry their own scroll area, so nothing needs to move the editor page.
    function wouldScrollEditorPage(el) {
      var page = document.getElementById('app');
      var node = el.parentElement;
      while (node && node !== document.body && node !== document.documentElement) {
        if (node === page) {
          var style = window.getComputedStyle(node);
          var scrollable = style.overflowY === 'auto' || style.overflowY === 'scroll';
          return scrollable && node.scrollHeight > node.clientHeight + 4;
        }
        node = node.parentElement;
      }
      return false;
    }

    function patchedScrollIntoView() {
      if (wouldScrollEditorPage(this)) return undefined;
      return native.apply(this, arguments);
    }
    patchedScrollIntoView.__scratchMoodlePatched = true;
    Element.prototype.scrollIntoView = patchedScrollIntoView;
  }

  /**
   * Provide Russian text for GUI messages that ship untranslated.
   *
   * The editor falls back to the English `defaultMessage` for these ids, which is why parts of
   * the debug window stayed in English. The GUI resolves a locale's catalogue lazily and caches
   * the result, so the messages have to be captured the first time the catalogue is built. A
   * proxy is installed over `messagesByLocale` before the editor boots: it records every
   * catalogue the GUI creates, and the Russian strings are injected into them.
   *
   * Only the current locale is touched; English stays English.
   */
  /**
   * Hide the tutorial library button.
   *
   * The menu bar renders the "Tutorials" entry unconditionally — it is wired straight into the
   * component tree, with no prop to disable it — so the only way to remove it in this deployment
   * is to hide it. Tutorials from scratch.mit.edu are not part of a course activity anyway.
   */
  function hideTutorialsButton() {
    var style = document.createElement('style');
    style.textContent = '[class*="tutorials-button"]{display:none !important}';
    (document.head || document.documentElement).appendChild(style);
  }

  /** Show the "how to connect LEGO" panel. */
  function openHardwareHelp() {
    var panel = document.getElementById('hardware-panel');
    if (panel) panel.hidden = false;
  }

  function closeHardwareHelp() {
    var panel = document.getElementById('hardware-panel');
    if (panel) panel.hidden = true;
  }

  function installHardwareHelp() {
    if (CFG.canEdit === false) {
      // Read-only previews never talk to hardware; the button would only add confusion.
      var button = document.getElementById('hardware-btn');
      if (button) button.hidden = true;
      return;
    }
    var btn = document.getElementById('hardware-btn');
    var close = document.getElementById('hardware-close');
    var panel = document.getElementById('hardware-panel');
    if (btn) btn.addEventListener('click', openHardwareHelp);
    if (close) close.addEventListener('click', closeHardwareHelp);
    if (panel) {
      panel.addEventListener('click', function (event) {
        if (event.target === panel) closeHardwareHelp();
      });
    }
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') closeHardwareHelp();
    });
  }

  /**
   * Tell the Scratch GUI which language to use.
   *
   * The GUI resolves its locale from ?locale= / ?lang= in the page URL and only then from the
   * browser, and it does so when the React tree is created. Moodle already knows the right
   * language (current_language()), so that value is put into the URL before the editor boots.
   * Without this the editor would follow the browser language and could ignore the course
   * language chosen in Moodle.
   */
  function applyLocale() {
    var locale = CFG.locale;
    if (!locale || typeof window.history === 'undefined' || !window.history.replaceState) return;
    try {
      var url = new URL(window.location.href);
      if (url.searchParams.has('locale') || url.searchParams.has('lang')) return;
      url.searchParams.set('locale', locale);
      window.history.replaceState(window.history.state, '', url.href);
    } catch (e) { /* not critical: the GUI falls back to the browser language */ }
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



  /**
   * Offer "continue saved project" once the editor markup is in the document.
   *
   * PROJECT_LOADED can fire before React has mounted the surrounding page elements, so the
   * panel is looked up on a few short retries instead of assuming it is already there.
   */
  function offerContinueWhenReady(attempt) {
    if (document.getElementById('continue-panel')) {
      offerContinue();
      return;
    }
    if (attempt > 40) { loadExistingProject(); return; }
    setTimeout(function () { offerContinueWhenReady(attempt + 1); }, 100);
  }

  /** Status line text for the current submission state. */
  function statusText() {
    if (CFG.status === 'submitted') return S.submitted || 'Submitted';
    if (CFG.status === 'draft') return S.draft || 'Draft';
    return S.newproject || 'New project';
  }

  /**
   * Ask the student whether to continue the saved project.
   *
   * The editor starts on an empty project. When a saved .sb3 exists, a small panel offers to
   * load it; choosing "start fresh" leaves the blank project untouched. The saved file stays in
   * Moodle either way — nothing is deleted until the student saves again.
   */
  function offerContinue() {
    var panel = document.getElementById('continue-panel');
    if (!panel) { loadExistingProject(); return; }
    panel.hidden = false;
    var resume = document.getElementById('continue-btn');
    var fresh = document.getElementById('continue-fresh');
    if (resume) {
      resume.addEventListener('click', function () {
        panel.hidden = true;
        loadExistingProject();
      });
    }
    if (fresh) {
      fresh.addEventListener('click', function () {
        panel.hidden = true;
        setStatus(S.newproject || 'New project');
      });
    }
    setStatus(statusText());
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
    applyLocale();
    hideTutorialsButton();
    stabiliseMenuScrolling();
    installStaticShims();
    blockCloudRelay();
    installHardwareHelp();
    if (setAppElement) setAppElement(appTarget);
    var Wrapped = AppStateHOC(GUIComponent);

    var props = {
      projectId: '0',
      // The GUI picks its language from ?locale=/?lang= in the URL or from the browser, not from
      // props, so editor.php puts the Moodle language into the URL (see localeQuery()).
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
        // The editor opens on an empty project. A saved project is loaded only when the student
        // asks for it (see the "Continue" button), so nobody overwrites earlier work by accident
        // and a fresh activity does not look empty-handed.
        // The VM may already have loaded its default project before this callback runs, in which
        // case PROJECT_LOADED never reaches us; the target list is then already populated.
        var onReady = function () {
          if (restoring || ready) return;
          ready = true;
          if (CFG.fileUrl && CFG.canEdit) offerContinueWhenReady(0);
          else setStatus(statusText());
        };
        v.on('PROJECT_LOADED', onReady);
        var poll = 0;
        (function waitForTargets() {
          if (ready || poll++ > 100) return;
          if (v.runtime && v.runtime.targets && v.runtime.targets.length > 0) onReady();
          else setTimeout(waitForTargets, 100);
        })();
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
