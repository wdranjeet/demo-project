/**
 * @file
 * Provides Intersection Observer API, or bLazy loader.
 */

(function (Drupal, drupalSettings, _db, window, document) {

  'use strict';

  /**
   * Blazy public methods.
   *
   * @namespace
   */
  Drupal.blazy = Drupal.blazy || {
    init: null,
    windowWidth: 0,
    blazySettings: drupalSettings.blazy || {},
    ioSettings: drupalSettings.blazyIo || {},
    isForced: false,
    revalidate: false,
    options: {},
    globals: function () {
      var me = this;
      var commons = {
        success: me.clearing.bind(me),
        error: me.clearing.bind(me),
        selector: '.b-lazy',
        errorClass: 'b-error',
        successClass: 'b-loaded'
      };

      return _db.extend(me.blazySettings, me.ioSettings, commons);
    },

    clearing: function (el) {
      var me = this;
      var ie = el.classList.contains('b-responsive') && el.hasAttribute('data-pfsrc');
      var cn = _db.closest(el, '.media');

      // The .b-lazy element can be attached to IMG, or DIV as CSS background.
      // The .(*)loading can be .media, .grid, .slide__content, .box, etc.
      var loaders = [
        el,
        _db.closest(el, '.is-loading'),
        _db.closest(el, '[class*="loading"]')
      ];

      _db.forEach(loaders, function (loader) {
        if (loader !== null) {
          loader.className = loader.className.replace(/(\S+)loading/, '');
        }
      });

      // @see http://scottjehl.github.io/picturefill/
      if (window.picturefill && ie) {
        window.picturefill({
          reevaluate: true,
          elements: [el]
        });
      }

      // Container might be the el itself for BG, do not NULL check here.
      me.updateContainer(el, cn);
      // Supports animate.css for CSS background, picture, image, media.
      if (me.isLoaded(el) && (me.has(cn, 'data-animation') || me.has(el, 'data-animation'))) {
        _db.animate(me.has(cn, 'data-animation') ? cn : el);
      }

      // Provides event listeners for easy overrides without full overrides.
      _db.trigger(el, 'blazy.done', {options: me.options});
    },

    isLoaded: function (el) {
      return el !== null && el.classList.contains(this.options.successClass);
    },

    has: function (el, attribute) {
      return el !== null && el.hasAttribute(attribute);
    },

    updateContainer: function (el, cn) {
      var me = this;

      if (me.isLoaded(el)) {
        if (_db.equal(el.parentNode, 'picture') && me.has(cn, 'data-dimensions')) {
          me.updatePicture(el, cn);
        }

        if (me.has(el, 'data-backgrounds')) {
          _db.updateBg(el, me.options.mobileFirst);
        }
      }
    },

    updatePicture: function (el, cn) {
      cn.style.paddingBottom = Math.round(((el.naturalHeight / el.naturalWidth) * 100), 2) + '%';
    },

    /**
     * Updates the dynamic multi-breakpoint aspect ratio: bg, picture or image.
     *
     * This only applies to Responsive images with aspect ratio fluid.
     * Static ratio (media--ratio--169, etc.) is ignored and uses CSS instead.
     *
     * @param {HTMLElement} cn
     *   The .media--ratio--fluid container HTML element.
     */
    updateRatio: function (cn) {
      var me = this;
      var dimensions = _db.parse(cn.getAttribute('data-dimensions')) || ('dimensions' in me.options ? me.options.dimensions : false);

      if (!dimensions) {
        me.updateFallbackRatio(cn);
        return;
      }

      // For picture, this is more a dummy space till the image is downloaded.
      var isPicture = cn.querySelector('picture') !== null;
      var pad = _db.activeWidth(dimensions, isPicture);
      if (pad !== 'undefined') {
        cn.style.paddingBottom = pad + '%';
      }

      // Fix for picture or bg element with resizing.
      if (isPicture || me.has(cn, 'data-backgrounds')) {
        me.updateContainer((isPicture ? cn.querySelector('img') : cn), cn);
      }
    },

    updateFallbackRatio: function (cn) {
      // Only rewrites if the style is indeed stripped out by Twig, and not set.
      if (!cn.hasAttribute('style') && cn.hasAttribute('data-ratio')) {
        cn.style.paddingBottom = cn.getAttribute('data-ratio') + '%';
      }
    },

    doNativeLazy: function (el) {
      var me = this;
      // Reset attributes, and let supportive browsers lazy load them natively.
      _db.setAttrs(el, ['srcset', 'src'], true);

      // Also supports PICTURE or (future) VIDEO element which contains SOURCEs.
      _db.setAttrsWithSources(el, false, true);

      // Mark it loaded to prevent Blazy/IO to do any further work.
      el.classList.add(me.options.successClass);
      me.clearing(el);
    },

    isNativeLazy: function () {
      return 'loading' in HTMLImageElement.prototype;
    },

    isIo: function () {
      return this.ioSettings && this.ioSettings.enabled && 'IntersectionObserver' in window;
    },

    isBlazy: function () {
      return !this.isIo() && 'Blazy' in window;
    },

    forEach: function (context) {
      var el = context.querySelector('[data-blazy]');
      var blazies = context.querySelectorAll('.blazy:not(.blazy--on)');

      // Various use cases: w/o formaters, custom, or basic, and mixed.
      // The [data-blazy] is set by the module for formatters, or Views gallery.
      if (blazies.length > 0) {
        _db.forEach(blazies, doBlazy, context);
      }

      // Runs basic Blazy if no [data-blazy] found, probably a single image or
      // a theme that does not use field attributes, or (non-grid) BlazyFilter.
      if (el === null) {
        initBlazy(context);
      }
    },

    run: function (opts) {
      return this.isIo() ? new BioMedia(opts) : new Blazy(opts);
    },

    afterInit: function (context) {
      var me = this;
      var elms = context.querySelector('.media--ratio') === null ? [] : context.querySelectorAll('.media--ratio');

      var checkRatio = function () {
        me.windowWidth = _db.windowWidth();

        if (elms.length > 0) {
          _db.forEach(elms, me.updateRatio.bind(me), context);
        }

        // BC with bLazy, native/IO doesn't need to revalidate, bLazy does.
        // Scenarios: long horizontal containers, Slick carousel slidesToShow >
        // 3. If any issue, add a class `blazy--revalidate` manually to .blazy.
        if (!me.isNativeLazy() && (me.isBlazy() || me.revalidate)) {
          me.init.revalidate(true);
        }
      };

      // Checks for aspect ratio.
      checkRatio();
      window.addEventListener('resize', _db.throttle(checkRatio, 200, me), false);
    }

  };

  /**
   * Initialize the blazy instance, either basic, advanced, or native.
   *
   * The initialization may take once for basic (not using module formatters),
   * or per .blazy/[data-blazy] formatter when they are one or many on a page.
   *
   * @param {HTMLElement} context
   *   This can be document, or .blazy container w/o [data-blazy].
   * @param {Object} opts
   *   The options might be empty for basic blazy, not using formatters.
   */
  var initBlazy = function (context, opts) {
    var me = Drupal.blazy;
    // Set docroot in case we are in an iframe.
    var documentElement = context instanceof HTMLDocument ? context : _db.closest(context, 'html');

    opts = opts || {};
    opts.mobileFirst = opts.mobileFirst || false;
    if (!document.documentElement.isSameNode(documentElement)) {
      opts.root = documentElement;
    }

    me.options = _db.extend({}, me.globals(), opts);

    // Old bLazy, not IO, might need scrolling CSS selector like Modal library.
    // A scrolling modal with an iframe like Entity Browser has no issue since
    // the scrolling container is the entire DOM.
    var scrollElms = '#drupal-modal';
    if (me.options.container) {
      scrollElms += ', ' + me.options.container.trim();
    }
    me.options.container = scrollElms;

    // Swap lazy attributes to let supportive browsers lazy load them.
    // This means Blazy and even IO should not lazy-load them any more.
    // Ensures to not touch lazy-loaded AJAX, or likely non-supported elements:
    // Video, DIV, etc. Only IMG and IFRAME are supported for now.
    // Enforced if required. Native lazy loading` must be enabled for now.
    if (me.isNativeLazy() || me.isForced) {
      var elms = context.querySelectorAll(me.options.selector + '[loading]:not(.' + me.options.successClass + ')');
      if (elms.length > 0) {
        _db.forEach(elms, me.doNativeLazy.bind(me));
      }
    }

    // Put the blazy/IO instance into a public object for references/ overrides.
    // If native lazy load is supported, the following will skip internally.
    me.init = me.run(me.options);

    // Reacts on resizing per 200ms.
    me.afterInit(context);
  };

  /**
   * Blazy utility functions.
   *
   * @param {HTMLElement} elm
   *   The .blazy/[data-blazy] container, not the lazyloaded .b-lazy element.
   */
  function doBlazy(elm) {
    var me = Drupal.blazy;
    var dataAttr = elm.getAttribute('data-blazy');
    var opts = (!dataAttr || dataAttr === '1') ? {} : (_db.parse(dataAttr) || {});

    me.revalidate = me.revalidate || elm.classList.contains('blazy--revalidate');
    elm.classList.add('blazy--on');

    // Initializes native, IntersectionObserver, or Blazy instance.
    initBlazy(elm, opts);
  }

  /**
   * Attaches blazy behavior to HTML element identified by .blazy/[data-blazy].
   *
   * The .blazy/[data-blazy] is the .b-lazy container, might be .field, etc.
   * The .b-lazy is the individual IMG, IFRAME, PICTURE, VIDEO, DIV, BODY, etc.
   * The lazy-loaded element is .b-lazy, not its container. Note the hypen (b-)!
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.blazy = {
    attach: function (context) {
      // Drupal.attachBehaviors already does this so if this is necessary,
      // someone does an invalid call. But let's be robust here.
      // Note: context can be unexpected <script> element with Media library.
      context = context || document;

      // Originally identified at D7, yet might happen at D8 with AJAX.
      // Prevents jQuery AJAX messes up where context might be an array.
      if ('length' in context) {
        context = context[0];
      }

      // Runs Blazy with multi-serving images, and aspect ratio supports.
      // W/o [data-blazy] to address various scenarios like custom simple works,
      // or within Views UI which is not easy to set [data-blazy] via UI.
      _db.once(Drupal.blazy.forEach(context));
    }
  };

}(Drupal, drupalSettings, dBlazy, this, this.document));
