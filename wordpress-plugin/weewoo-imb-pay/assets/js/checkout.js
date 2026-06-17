/* WeeWoo Pay (IMB) — checkout: QR render, tabs, countdown, AUTO verify + manual confirm. */
(function () {
  'use strict';
  if (typeof window.WW_IMB === 'undefined') return;
  var cfg = window.WW_IMB;

  var holder = document.getElementById('ww-qr');
  var statusEl = document.getElementById('ww-status');
  var statusTxt = statusEl ? statusEl.querySelector('span:last-child') : null;
  var successEl = document.getElementById('ww-success');
  var verifyBtn = document.getElementById('ww-verify');

  /* ---- 1. Render IMB's UPI link (bhim_link) as our QR ---- */
  if (holder && cfg.bhim && typeof window.QRCode !== 'undefined') {
    try {
      new window.QRCode(holder, {
        text: cfg.bhim, width: 230, height: 230,
        correctLevel: window.QRCode.CorrectLevel.H
      });
    } catch (e) { fallbackQr(); }
  } else { fallbackQr(); }

  function fallbackQr() {
    if (holder && cfg.paymentUrl) {
      holder.innerHTML = '<a href="' + cfg.paymentUrl + '" style="color:#0f1620;font-weight:700">Open payment page →</a>';
    }
  }

  /* ---- 2. Tabs ---- */
  var tabs = document.getElementById('ww-tabs');
  if (tabs) {
    tabs.querySelectorAll('.ww-tab').forEach(function (b) {
      b.addEventListener('click', function () {
        tabs.querySelectorAll('.ww-tab').forEach(function (t) { t.classList.remove('active'); });
        document.querySelectorAll('.ww-pane').forEach(function (p) { p.classList.remove('active'); });
        b.classList.add('active');
        var pane = b.getAttribute('data-pane');
        var el = document.getElementById('ww-pane-' + pane);
        if (el) el.classList.add('active');
        tabs.setAttribute('data-active', pane);
      });
    });
  }

  /* ---- 3. Countdown ---- */
  var total = (cfg.expirySeconds || 600), t = total;
  var fill = document.getElementById('ww-fill'), time = document.getElementById('ww-time');
  var cd = setInterval(function () {
    if (t > 0) {
      t--;
      if (time) time.textContent = Math.floor(t / 60) + ':' + String(t % 60).padStart(2, '0');
      if (fill) fill.style.width = (t / total * 100) + '%';
    } else { clearInterval(cd); }
  }, 1000);

  /* ---- 4. Download QR (so mobile users scan from gallery) ---- */
  var dl = document.getElementById('ww-dl');
  if (dl) {
    dl.addEventListener('click', function () {
      var canvas = holder ? holder.querySelector('canvas') : null;
      var img = holder ? holder.querySelector('img') : null;
      var data = canvas ? canvas.toDataURL('image/png') : (img ? img.src : '');
      if (!data) { if (cfg.paymentUrl) window.open(cfg.paymentUrl, '_blank'); return; }
      var a = document.createElement('a');
      a.href = data; a.download = 'pay-qr.png';
      document.body.appendChild(a); a.click(); document.body.removeChild(a);
    });
  }

  /* ---- 5. Verification: auto-poll + optional manual confirm ---- */
  var done = false, tries = 0, MAX = 240; // ~16 min @ 4s

  function setStatus(text, cls) {
    if (statusTxt) statusTxt.textContent = text;
    if (statusEl) statusEl.className = 'ww-autostatus' + (cls ? ' ' + cls : '');
  }

  function onResult(d) {
    if (done) return true;
    if (d && d.status === 'SUCCESS') {
      done = true;
      setStatus('Payment verified ✓', 'ok');
      if (successEl) successEl.classList.add('show');
      setTimeout(function () { if (d.redirect) window.location.href = d.redirect; }, 1400);
      return true;
    }
    if (d && d.status === 'FAILED') {
      done = true;
      setStatus('Payment failed or expired. Please try again.', 'fail');
      return true;
    }
    return false;
  }

  function check() {
    return fetch(cfg.statusUrl, { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(onResult)
      .catch(function () { return false; });
  }

  function poll() {
    if (done) return;
    tries++;
    check().then(function (finished) {
      if (finished || done) return;
      if (tries < MAX) setTimeout(poll, cfg.pollInterval || 4000);
      else setStatus('Stopped checking. Tap the button below to verify.', '');
    });
  }

  // Manual "I have completed payment" — immediate check.
  if (verifyBtn) {
    verifyBtn.addEventListener('click', function () {
      if (done) return;
      setStatus('Verifying your payment…', '');
      verifyBtn.disabled = true;
      check().then(function (finished) {
        verifyBtn.disabled = false;
        if (!finished && !done) setStatus("Not received yet — we'll keep checking automatically.", '');
      });
    });
  }

  setTimeout(poll, cfg.pollInterval || 4000); // auto-verify starts on its own
})();
