// Resource Hub — shared mockup interactions.
// 1) Filter chip toggle.
// 2) Sortable table headers on admin queue (visual indicator only — sort logic is mock).

(function () {
  // ---- Chip toggle ----
  document.querySelectorAll('.rh-chip').forEach(function (chip) {
    chip.addEventListener('click', function () {
      var pressed = chip.getAttribute('aria-pressed') === 'true';
      chip.setAttribute('aria-pressed', pressed ? 'false' : 'true');
    });
  });

  // ---- Sortable table headers ----
  document.querySelectorAll('.rh-table thead th[data-sortable]').forEach(function (th) {
    th.addEventListener('click', function () {
      var current = th.getAttribute('aria-sort');
      var next;
      if (current === 'ascending') next = 'descending';
      else if (current === 'descending') next = 'none';
      else next = 'ascending';

      // Clear siblings
      th.parentElement.querySelectorAll('th[data-sortable]').forEach(function (sib) {
        if (sib !== th) sib.setAttribute('aria-sort', 'none');
      });
      th.setAttribute('aria-sort', next);

      // Update arrow glyph
      var arrow = th.querySelector('.rh-sort__arrow');
      if (arrow) {
        arrow.textContent = next === 'ascending' ? '↑' : next === 'descending' ? '↓' : '↕';
      }
    });
  });
})();
