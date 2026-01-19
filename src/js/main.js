/**
 * Tatami Theme - Main JavaScript Starter
 *
 * - Utility helpers (DOM ready, throttle via rAF, body scroll lock, viewport check)
 * - IntersectionObserver wrapper with a consistent API
 * - Single init block that wires everything together
 *
 * @since 1.0.0
 */

// Import styles
import '../css/tailwind.css';

// =============================================================================
// UTILITY FUNCTIONS
// =============================================================================

/**
 * Utility functions for common DOM operations and performance optimizations
 */
const Utils = {
  /**
   * Toggle body scroll overflow to prevent/allow scrolling
   * @param {boolean} prevent - Whether to prevent scrolling
   */
  toggleBodyScroll: (prevent) => {
    document.body.style.overflow = prevent ? 'hidden' : '';
  },

  /**
   * Create a throttled version of a function using requestAnimationFrame
   * @param {Function} callback - Function to throttle
   * @returns {Function} - Throttled function
   */
  throttleRAF: (callback) => {
    let ticking = false;
    return (...args) => {
      if (!ticking) {
        requestAnimationFrame(() => {
          callback.apply(null, args);
          ticking = false;
        });
        ticking = true;
      }
    };
  },

  /**
   * Execute callback when DOM is ready
   * @param {Function} callback - Function to execute when DOM is ready
   */
  domReady: (callback) => {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', callback, { once: true });
    } else {
      callback();
    }
  },

  /**
   * Check if element is fully in viewport
   * @param {Element} element - DOM element to check
   * @returns {boolean}
   */
  isInViewport: (element) => {
    const rect = element.getBoundingClientRect();
    return (
      rect.top >= 0 &&
      rect.left >= 0 &&
      rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
      rect.right <= (window.innerWidth || document.documentElement.clientWidth)
    );
  }
};

// =============================================================================
// INTERSECTION OBSERVER WRAPPER
// =============================================================================

/**
 * Creates an Intersection Observer to detect when elements enter/exit the viewport.
 * Unified API for viewport-driven interactions.
 *
 * @param {Object} options
 * @param {Function} options.onEnter - Called when target enters viewport
 * @param {Function} [options.onExit] - Called when target exits viewport
 * @param {string|Element|NodeList|Array} options.target - Selector, element, or collection
 * @param {Object} [options.observerOptions] - { root, rootMargin, threshold }
 * @param {boolean} [options.once=false] - Unobserve after first entry
 * @param {boolean} [options.immediate=false] - Fire immediately if already in view
 * @returns {{disconnect: Function, observer: IntersectionObserver}}
 */
const createIntersectionObserver = ({
  onEnter,
  onExit,
  target,
  observerOptions = { root: null, rootMargin: '0px', threshold: 0 },
  once = false,
  immediate = false
}) => {
  if (!onEnter || typeof onEnter !== 'function') {
    throw new Error('onEnter callback is required and must be a function');
  }
  if (!target) {
    throw new Error('target is required (CSS selector, DOM element, or collection)');
  }

  let elements = [];
  if (typeof target === 'string') {
    elements = Array.from(document.querySelectorAll(target));
  } else if (target instanceof Element) {
    elements = [target];
  } else if (target instanceof NodeList || Array.isArray(target)) {
    elements = Array.from(target);
  }

  if (elements.length === 0) {
    return { disconnect: () => {}, observer: null };
  }

  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        onEnter(entry.target, entry);
        if (once) observer.unobserve(entry.target);
      } else if (onExit && typeof onExit === 'function') {
        onExit(entry.target, entry);
      }
    });
  }, observerOptions);

  elements.forEach((el) => {
    observer.observe(el);
    if (immediate && Utils.isInViewport(el)) {
      onEnter(el, { target: el, isIntersecting: true });
      if (once) observer.unobserve(el);
    }
  });

  return { disconnect: () => observer.disconnect(), observer };
};

// =============================================================================
// EXAMPLE FEATURE MODULE (SHELL)
// =============================================================================

/**
 * ExampleFeature
 * --------------
 * Tiny demonstration of the module pattern you use.
 * Adds a class to any element with [data-example] when it enters the viewport,
 * and removes it when it exits. Safe to delete or replace later.
 *
 * Usage in markup:
 *   <div data-example class="opacity-50 transition-opacity">Hello</div>
 * CSS (Tailwind example):
 *   .is-visible { @apply opacity-100; }
 */
const ExampleFeature = (() => {
  // Local state (if needed in the future)
  let controller = null;

  const onEnter = (el) => {
    el.classList.add('is-visible');
  };

  const onExit = (el) => {
    el.classList.remove('is-visible');
  };

  const init = () => {
    // Early exit if no targets; keeps init safe on any page.
    const targets = document.querySelectorAll('[data-example]');
    if (!targets.length) return;

    controller = createIntersectionObserver({
      target: targets,
      onEnter,
      onExit,
      observerOptions: { root: null, rootMargin: '0px 0px -10% 0px', threshold: 0.15 },
      once: false,
      immediate: true
    });
  };

  const destroy = () => {
    if (controller && controller.disconnect) controller.disconnect();
    controller = null;
  };

  return { init, destroy };
})();

// =============================================================================
// (ADD FUTURE MODULES BELOW THIS LINE)
// =============================================================================
// Example stubs for future features—keep the pattern consistent:
//
// const MobileNavigation = (() => {
//   const init = () => { /* ... */ };
//   return { init };
// })();
//
// const Modal = (() => {
//   const init = () => { /* ... */ };
//   return { init };
// })();
//
// const PageLoader = (() => {
//   const init = () => { /* ... */ };
//   return { init };
// })();

// =============================================================================
// INITIALIZATION
// =============================================================================

/**
 * Initialize all modules when DOM is ready.
 * Keep this as the single entry point to maintain order & consistency.
 */
Utils.domReady(() => {
  ExampleFeature.init();
  // MobileNavigation.init();
  // Modal.init();
  // PageLoader.init();
});
