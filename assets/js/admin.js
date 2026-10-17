/* Shifa admin scripts */
(function () {
  'use strict';
  var A = window.SHIFA_ADMIN || {};

  // Delivery charge rows
  document.addEventListener('click', function (ev) {
    var add = ev.target.closest('[data-add-row]');
    if (add) {
      var zone = add.getAttribute('data-add-row');
      var tbody = document.querySelector('.rate-table[data-zone="' + zone + '"] tbody');
      var tpl = document.getElementById('rateRow').content.cloneNode(true);
      tpl.querySelectorAll('input').forEach(function (i) { i.name = zone + '[' + i.getAttribute('data-n') + '][]'; });
      var rows = tbody.querySelectorAll('tr');
      if (rows.length) {
        var lastMax = rows[rows.length - 1].querySelectorAll('input')[1].value;
        tpl.querySelector('[data-n=min]').value = lastMax;
      } else {
        tpl.querySelector('[data-n=min]').value = 0;
      }
      tbody.appendChild(tpl);
      tbody.lastElementChild.querySelectorAll('input')[1].focus();
    }
    var rm = ev.target.closest('[data-remove-row]');
    if (rm) rm.closest('tr').remove();

    var preset = ev.target.closest('[data-preset]');
    if (preset) {
      var f = preset.closest('form');
      var p = preset.getAttribute('data-preset');
      if (p === 'gmail') {
        f.smtp_host.value = 'smtp.gmail.com'; f.smtp_port.value = 587; f.smtp_secure.value = 'tls';
        if (!f.smtp_user.value) f.smtp_user.focus();
      } else {
        var host = location.hostname.replace(/^www\./, '');
        f.smtp_host.value = 'mail.' + host; f.smtp_port.value = 465; f.smtp_secure.value = 'ssl';
        if (!f.smtp_user.value) f.smtp_user.value = 'shop@' + host;
      }
    }
  });

  // Image preview before upload
  document.querySelectorAll('input[type=file][data-preview]').forEach(function (inp) {
    inp.addEventListener('change', function () {
      var img = document.getElementById(inp.getAttribute('data-preview'));
      if (inp.files && inp.files[0]) {
        img.src = URL.createObjectURL(inp.files[0]);
        img.hidden = false;
      }
    });
  });

  // Marketing: send in small batches
  var sender = document.getElementById('sender');
  if (sender) {
    var id = sender.getAttribute('data-id');
    var bar = document.getElementById('pbar');
    var txt = document.getElementById('ptext');
    var errs = document.getElementById('perrors');
    var fails = 0;
    var step = function () {
      var fd = new FormData();
      fd.append('csrf', A.csrf);
      fd.append('action', 'batch');
      fd.append('id', id);
      fetch('marketing.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          var pct = d.total ? Math.min(100, Math.round((d.sent + d.failed) / d.total * 100)) : 100;
          bar.style.width = pct + '%';
          txt.textContent = d.sent + ' sent' + (d.failed ? ', ' + d.failed + ' failed' : '') + ' of ' + d.total + (d.done ? ' — finished ✓' : ' — sending…');
          (d.errors || []).forEach(function (e) { var li = document.createElement('li'); li.textContent = e; errs.appendChild(li); });
          if (!d.done) setTimeout(step, 600);
          else setTimeout(function () { location.href = 'marketing.php'; }, 2500);
        })
        .catch(function () {
          fails++;
          txt.textContent = 'Connection problem, retrying… (' + fails + ')';
          if (fails < 5) setTimeout(step, 3000);
          else txt.textContent = 'Stopped. You can continue later from "Sent emails".';
        });
    };
    step();
  }

  // Close sidebar on mobile when clicking outside
  document.addEventListener('click', function (ev) {
    var sb = document.getElementById('sidebar');
    if (sb && sb.classList.contains('open') && !ev.target.closest('#sidebar') && !ev.target.closest('.burger')) sb.classList.remove('open');
  });
})();
