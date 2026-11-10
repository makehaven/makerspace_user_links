/**
 * @file
 * Public header strip: the Dashboards menu.
 *
 * The menu is a native <details>, so it opens without this script; this adds
 * close on outside click and on Escape.
 */
(function (Drupal, once) {
  Drupal.behaviors.mhMemberReturn = {
    attach(context) {
      once('mh-member-return', '.mh-member-return__dashboards', context).forEach((menu) => {
        document.addEventListener('click', (e) => {
          if (!menu.contains(e.target)) {
            menu.open = false;
          }
        });
        document.addEventListener('keydown', (e) => {
          if (e.key === 'Escape' && menu.open) {
            menu.open = false;
            menu.querySelector('summary').focus();
          }
        });
      });
    },
  };
})(Drupal, once);
