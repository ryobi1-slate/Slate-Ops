/*
 * Slate Ops — Resource Hub mockup interactions.
 *
 * Scope: filter-chip toggle only. Multi-select within a single .rh-chips
 * strip. The "All" chip (.rh-chip[data-chip-all]) is a reset — clicking it
 * deactivates every other chip in its strip; clicking any other chip
 * deactivates the All chip in that strip.
 *
 * Static mockup. No data layer, no fetch, no router.
 */
(function () {
  'use strict';

  function strips() {
    return document.querySelectorAll('.rh-chips');
  }

  function init() {
    strips().forEach(function (strip) {
      strip.addEventListener('click', function (event) {
        var chip = event.target.closest('.rh-chip');
        if (!chip || !strip.contains(chip)) return;

        var isAll = chip.hasAttribute('data-chip-all');

        if (isAll) {
          strip.querySelectorAll('.rh-chip').forEach(function (c) {
            c.classList.toggle('is-active', c === chip);
          });
          return;
        }

        chip.classList.toggle('is-active');

        var allChip = strip.querySelector('.rh-chip[data-chip-all]');
        if (allChip) {
          var anyActive = strip.querySelector('.rh-chip.is-active:not([data-chip-all])');
          allChip.classList.toggle('is-active', !anyActive);
        }
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
