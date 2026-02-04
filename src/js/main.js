/**
 * Tatami Theme - Main JavaScript
 *
 * @since 1.0.0
 */

// Import styles
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
