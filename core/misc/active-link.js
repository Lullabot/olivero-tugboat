/**
* DO NOT EDIT THIS FILE.
* See the following change record for more information,
* https://www.drupal.org/node/2815083
* @preserve
**/

(function (Drupal, drupalSettings) {
  Drupal.behaviors.activeLinks = {
    attach(context) {
      const path = drupalSettings.path;
      const queryString = JSON.stringify(path.currentQuery);
      const querySelector = path.currentQuery ? `[data-drupal-link-query='${queryString}']` : ':not([data-drupal-link-query])';
      const originalSelectors = [`[data-drupal-link-system-path="${path.currentPath}"]`];
      let selectors;

      if (path.isFront) {
        originalSelectors.push('[data-drupal-link-system-path="<front>"]');
      }

      selectors = [].concat(originalSelectors.map(selector => `${selector}:not([hreflang])`), originalSelectors.map(selector => `${selector}[hreflang="${path.currentLanguage}"]`));
      selectors = selectors.map(current => current + querySelector);
      const activeLinks = context.querySelectorAll(selectors.join(','));
      const il = activeLinks.length;

      for (let i = 0; i < il; i++) {
        activeLinks[i].classList.add('is-active');
      }
    },

    detach(context, settings, trigger) {
      if (trigger === 'unload') {
        const activeLinks = context.querySelectorAll('[data-drupal-link-system-path].is-active');
        const il = activeLinks.length;

        for (let i = 0; i < il; i++) {
          activeLinks[i].classList.remove('is-active');
        }
      }
    }

  };
})(Drupal, drupalSettings);