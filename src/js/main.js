/**
 * Tatami entry point — imports the stylesheet and initializes JS modules
 * once the DOM is ready.
 */

import '../css/tailwind.css';

// =============================================================================
// UTILITY FUNCTIONS
// =============================================================================

const Utils = {
  domReady: (callback) => {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', callback, { once: true });
    } else {
      callback();
    }
  },
};

// =============================================================================
// MODULES
// =============================================================================
// const MyFeature = (() => { const init = () => {}; return { init }; })();

// =============================================================================
// INITIALIZATION
// =============================================================================

Utils.domReady(() => {
  // init modules here
});
