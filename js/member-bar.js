/**
 * @file
 * Member bar behaviour.
 *
 * - Dropdowns are native <details>, so they work without this script; it adds
 *   one-open-at-a-time and close on outside click or Escape.
 * - Fit: link widths depend on the theme's font, the viewer's roles and the
 *   length of their name, so instead of guessing breakpoints the bar measures
 *   itself: `is-tight` drops the end group's labels, and `is-compact` also
 *   drops the primary labels.
 */
(function (Drupal, once) {
  Drupal.behaviors.mhMemberBar = {
    attach(context) {
      once('mh-member-bar', '.mh-member-bar', context).forEach((bar) => {
        const drops = bar.querySelectorAll('.mh-member-bar__drop');
        const closeAll = (except) => {
          drops.forEach((d) => {
            if (d !== except) {
              d.open = false;
            }
          });
        };
        drops.forEach((d) => {
          d.addEventListener('toggle', () => {
            if (d.open) {
              closeAll(d);
            }
          });
        });
        document.addEventListener('click', (e) => {
          if (!bar.contains(e.target)) {
            closeAll(null);
          }
        });
        const primary = bar.querySelector('.mh-member-bar__primary');
        const overflows = () =>
          primary && primary.scrollWidth > primary.clientWidth + 1;
        // Shed width in steps: first the end group's labels, then the
        // primary labels.
        const fit = () => {
          bar.classList.remove('is-tight', 'is-compact');
          if (overflows()) {
            bar.classList.add('is-tight');
            if (overflows()) {
              bar.classList.add('is-compact');
            }
          }
        };
        fit();
        if (window.ResizeObserver) {
          new ResizeObserver(fit).observe(bar);
        }
        if (document.fonts && document.fonts.ready) {
          document.fonts.ready.then(fit);
        }
        document.addEventListener('keydown', (e) => {
          if (e.key === 'Escape') {
            const open = bar.querySelector('.mh-member-bar__drop[open]');
            if (open) {
              open.open = false;
              open.querySelector('summary').focus();
            }
          }
        });
      });
    },
  };
})(Drupal, once);
