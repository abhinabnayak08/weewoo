/* WeeWoo Pay (IMB) — admin dashboard interactions. */
(function () {
  'use strict';
  if (typeof window.WW_IMB_ADMIN === 'undefined') return;
  var C = window.WW_IMB_ADMIN;

  function post(action) {
    var body = new URLSearchParams();
    body.set('action', action);
    body.set('nonce', C.nonce);
    return fetch(C.ajax, { method: 'POST', credentials: 'same-origin', body: body })
      .then(function (r) { return r.json(); });
  }

  // Test connection
  var test = document.getElementById('ww-test');
  var note = document.getElementById('ww-test-result');
  if (test && note) {
    test.addEventListener('click', function () {
      test.disabled = true;
      var old = test.textContent;
      test.textContent = 'Testing…';
      note.style.display = 'block';
      note.className = 'ww-adm-note';
      note.textContent = 'Checking connection to IMB…';
      post('ww_imb_test').then(function (d) {
        note.className = 'ww-adm-note ' + (d && d.ok ? 'ok' : 'bad');
        note.textContent = (d && d.msg) || 'Unexpected response.';
        test.disabled = false; test.textContent = old;
      }).catch(function () {
        note.className = 'ww-adm-note bad';
        note.textContent = 'Network error while testing.';
        test.disabled = false; test.textContent = old;
      });
    });
  }

  // Copy webhook URL
  var copy = document.getElementById('ww-copy');
  var hook = document.getElementById('ww-webhook');
  if (copy && hook) {
    copy.addEventListener('click', function () {
      hook.select();
      try {
        navigator.clipboard ? navigator.clipboard.writeText(hook.value) : document.execCommand('copy');
        var o = copy.textContent; copy.textContent = 'Copied ✓';
        setTimeout(function () { copy.textContent = o; }, 1500);
      } catch (e) {}
    });
  }

  // Clear errors
  var clear = document.getElementById('ww-clear');
  if (clear) {
    clear.addEventListener('click', function () {
      clear.disabled = true;
      post('ww_imb_clear_errors').then(function () { window.location.reload(); });
    });
  }
})();
