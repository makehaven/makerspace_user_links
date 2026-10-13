/**
 * @file
 * Member bar behaviour.
 *
 * - Dropdowns are native <details>, so they work without this script; it adds
 *   one-open-at-a-time and close on outside click or Escape.
 * - Fit: the end group's width depends on the viewer's roles (Dashboards)
 *   and the length of their name, so instead of guessing breakpoints the bar
 *   measures itself and sheds width in steps until nothing touches the
 *   centred wordmark: `is-tight` turns the search input into an icon,
 *   `is-compact` drops the labels, `is-crowded` drops the wordmark.
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
        const inner = bar.querySelector('.mh-member-bar__inner');
        const start = bar.querySelector('.mh-member-bar__start');
        const end = bar.querySelector('.mh-member-bar__end');
        const logo = bar.querySelector('.mh-member-bar__logo');
        const clear = 12;
        const collides = () => {
          if (inner.scrollWidth > inner.clientWidth + 1) {
            return true;
          }
          if (!logo || logo.offsetParent === null) {
            return false;
          }
          const l = logo.getBoundingClientRect();
          return (
            start.getBoundingClientRect().right + clear > l.left ||
            end.getBoundingClientRect().left - clear < l.right
          );
        };
        const steps = ['is-tight', 'is-compact', 'is-crowded'];
        const fit = () => {
          bar.classList.remove(...steps);
          steps.every((step) => {
            if (!collides()) {
              return false;
            }
            bar.classList.add(step);
            return true;
          });
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
