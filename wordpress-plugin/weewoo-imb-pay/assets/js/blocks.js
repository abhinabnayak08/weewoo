/* WeeWoo Pay (IMB) — WooCommerce Checkout block registration (no build step). */
(function () {
  'use strict';
  if (!window.wc || !window.wc.wcBlocksRegistry || !window.wp || !window.wp.element) return;

  var registerPaymentMethod = window.wc.wcBlocksRegistry.registerPaymentMethod;
  var createElement = window.wp.element.createElement;
  var decode = (window.wp.htmlEntities && window.wp.htmlEntities.decodeEntities) || function (s) { return s; };

  var settings = (window.wc.wcSettings && window.wc.wcSettings.getSetting)
    ? window.wc.wcSettings.getSetting('weewoo_imb_data', {})
    : {};

  var label = decode(settings.title || 'UPI / QR (WeeWoo Pay)');
  var description = decode(settings.description || '');

  var Content = function () {
    return createElement('div', { className: 'ww-imb-blocks-desc' }, description);
  };

  registerPaymentMethod({
    name: 'weewoo_imb',
    label: createElement('span', null, label),
    content: createElement(Content, null),
    edit: createElement(Content, null),
    canMakePayment: function () { return true; },
    ariaLabel: label,
    supports: { features: (settings.supports || ['products']) }
  });
})();
