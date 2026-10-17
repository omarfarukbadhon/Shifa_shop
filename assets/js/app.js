/* Shifa storefront scripts — small, no frameworks */
(function () {
  'use strict';
  var S = window.SHIFA || { base: '/', i18n: {} };

  // Mobile menu
  var toggle = document.querySelector('.menu-toggle');
  var nav = document.getElementById('mainnav');
  if (toggle && nav) {
    toggle.addEventListener('click', function () {
      var open = nav.classList.toggle('open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

  // Toast
  var toastEl;
  function toast(msg, withLink) {
    if (!toastEl) {
      toastEl = document.createElement('div');
      toastEl.className = 'toast';
      toastEl.setAttribute('role', 'status');
      toastEl.setAttribute('aria-live', 'polite');
      document.body.appendChild(toastEl);
    }
    toastEl.textContent = msg;
    if (withLink) {
      var a = document.createElement('a');
      a.href = S.base + 'cart.php';
      a.textContent = S.i18n.view_cart + ' →';
      toastEl.appendChild(a);
    }
    toastEl.classList.add('show');
    clearTimeout(toastEl._t);
    toastEl._t = setTimeout(function () { toastEl.classList.remove('show'); }, 3200);
  }

  // Add to cart without leaving the page
  document.addEventListener('submit', function (ev) {
    var form = ev.target;
    if (!form.matches('[data-ajax-cart]') || !window.fetch) return;
    ev.preventDefault();
    var btn = form.querySelector('button[type=submit]');
    if (btn) btn.disabled = true;
    fetch(form.action, { method: 'POST', body: new FormData(form), headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        document.querySelectorAll('[data-cart-count]').forEach(function (el) { el.textContent = d.count ? d.count_label : ''; });
        toast(d.message, d.ok);
      })
      .catch(function () { toast(S.i18n.error, false); })
      .finally(function () { if (btn) btn.disabled = false; });
  });

  // Quantity steppers
  document.addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-step]');
    if (!b) return;
    var input = b.parentNode.querySelector('input');
    var min = parseInt(input.min || '0', 10), max = parseInt(input.max || '999', 10);
    var v = (parseInt(input.value, 10) || 0) + parseInt(b.getAttribute('data-step'), 10);
    input.value = Math.max(min, Math.min(max, v));
    input.dispatchEvent(new Event('change', { bubbles: true }));
  });

  // Product gallery thumbnails
  var main = document.getElementById('mainImg');
  document.querySelectorAll('.thumb').forEach(function (t) {
    t.addEventListener('click', function () {
      main.src = t.getAttribute('data-src');
      document.querySelectorAll('.thumb').forEach(function (x) { x.classList.remove('active'); });
      t.classList.add('active');
    });
  });

  // Copy serial
  document.querySelectorAll('[data-copy]').forEach(function (b) {
    b.addEventListener('click', function () {
      var txt = b.getAttribute('data-copy');
      if (navigator.clipboard) navigator.clipboard.writeText(txt).then(function () { b.textContent = '✓'; });
    });
  });

  // Prevent double submit of the order
  document.querySelectorAll('form').forEach(function (f) {
    var once = f.querySelector('[data-once]');
    if (!once) return;
    f.addEventListener('submit', function () { setTimeout(function () { once.disabled = true; }, 0); });
  });

  // Live delivery charge on checkout
  var co = document.getElementById('checkoutForm');
  var D = window.SHIFA_DELIVERY;
  if (co && D) {
    var subtotal = parseFloat(co.getAttribute('data-subtotal')) || 0;
    var weight = Math.round((parseFloat(co.getAttribute('data-weight')) || 0) * 1000) / 1000;
    var bn = S.lang === 'bn';
    function money(n) {
      var dec = Math.abs(n - Math.round(n)) > 0.001 ? 2 : 0;
      var s = n.toLocaleString('en-US', { minimumFractionDigits: dec, maximumFractionDigits: dec });
      if (bn) s = s.replace(/[0-9]/g, function (d) { return '০১২৩৪৫৬৭৮৯'[d]; });
      return '৳' + s;
    }
    function charge(zone) {
      if (D.free_over > 0 && subtotal >= D.free_over) return 0;
      var rates = D[zone] || [];
      if (!rates.length) return 0;
      for (var i = 0; i < rates.length; i++) if (weight >= rates[i][0] && weight <= rates[i][1]) return rates[i][2];
      var last = rates[rates.length - 1];
      if (weight > last[1]) return last[2] + Math.ceil(weight - last[1]) * (D.extra[zone] || 0);
      for (var j = 0; j < rates.length; j++) if (weight < rates[j][0]) return rates[j][2];
      return last[2];
    }
    function update() {
      var z = co.querySelector('input[name=zone]:checked');
      if (!z) return;
      var c = charge(z.value);
      co.querySelector('[data-delivery]').textContent = money(c);
      co.querySelector('[data-total]').textContent = money(subtotal + c);
    }
    co.querySelectorAll('input[name=zone]').forEach(function (r) { r.addEventListener('change', update); });
    update();
  }
})();
