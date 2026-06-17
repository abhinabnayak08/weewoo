/* WeeWoo Pay (IMB) — branded checkout: render QR + poll for confirmation. */
(function () {
  'use strict';
  if (typeof window.WW_IMB === 'undefined') return;
  var cfg = window.WW_IMB;

  // 1. Render IMB's UPI deep link (bhim_link) as our own QR.
  var holder = document.getElementById('ww-qr');
  if (holder && cfg.bhim && typeof window.QRCode !== 'undefined') {
    try {
      new window.QRCode(holder, {
        text: cfg.bhim,
        width: 208,
        height: 208,
        correctLevel: window.QRCode.CorrectLevel.M
      });
    } catch (e) {
      fallbackQr(holder);
    }
  } else if (holder) {
    fallbackQr(holder);
  }

  function fallbackQr(el) {
    // If the QR library failed, link to IMB's hosted page so the customer can still pay.
    if (cfg.paymentUrl) {
      el.innerHTML = '<a href="' + cfg.paymentUrl + '" style="color:#1e293b;font-weight:600">Open payment page →</a>';
    }
  }

  // 2. Poll for confirmation.
  var statusEl = document.getElementById('ww-status');
  var successEl = document.getElementById('ww-success');
  var tries = 0;
  var MAX = 225; // ~15 min at 4s

  function setStatus(text) {
    if (statusEl) statusEl.querySelector('span:last-child').textContent = text;
  }

  function poll() {
    tries++;
    fetch(cfg.statusUrl, { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d && d.status === 'SUCCESS') {
          if (successEl) successEl.classList.add('show');
          setTimeout(function () {
            if (d.redirect) window.location.href = d.redirect;
          }, 1400);
          return;
        }
        if (d && d.status === 'FAILED') {
          if (statusEl) statusEl.classList.add('fail');
          setStatus('Payment failed or expired. Please try again.');
          return;
        }
        schedule();
      })
      .catch(schedule);
  }

  function schedule() {
    if (tries < MAX) {
      setTimeout(poll, cfg.pollInterval || 4000);
    } else {
      setStatus('Stopped checking. Refresh this page to resume.');
    }
  }

  setTimeout(poll, cfg.pollInterval || 4000);
})();
