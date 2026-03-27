var ChartColor = ["#5D62B4", "#54C3BE", "#EF726F", "#F9C446", "rgb(93.0, 98.0, 180.0)", "#21B7EC", "#04BCCC"];
var primaryColor = getComputedStyle(document.body).getPropertyValue('--primary');
var secondaryColor = getComputedStyle(document.body).getPropertyValue('--secondary');
var successColor = getComputedStyle(document.body).getPropertyValue('--success');
var warningColor = getComputedStyle(document.body).getPropertyValue('--warning');
var dangerColor = getComputedStyle(document.body).getPropertyValue('--danger');
var infoColor = getComputedStyle(document.body).getPropertyValue('--info');
var darkColor = getComputedStyle(document.body).getPropertyValue('--dark');
var lightColor = getComputedStyle(document.body).getPropertyValue('--light');

(function($) {
  'use strict';
  $(function() {
    var body = $('body');
    var contentWrapper = $('.content-wrapper');
    var scroller = $('.container-scroller');
    var footer = $('.footer');
    var sidebar = $('.sidebar');

    //Add active class to nav-link based on url dynamically
    //Active class can be hard coded directly in html file also as required

    function addActiveClass(element) {
      var href = element.attr('href') || '';
      if (!href || href === '#') return;

      var elementPath = normalizePath(href);
      if (!elementPath) return;

      // Match exactly or by section prefix:
      // - current: /partner/create should activate /partner
      // - current: /application/create should activate /application/create
      var matches = (currentPath === elementPath) || (currentPath.indexOf(elementPath + '/') === 0);

      // Special-case old root behaviour
      if (!matches && currentPath === '/' && href.indexOf("index.html") !== -1) {
        matches = true;
      }

      if (matches) {
        element.parents('.nav-item').last().addClass('active');
        if (element.parents('.sub-menu').length) {
          element.closest('.collapse').addClass('show');
          element.addClass('active');
        }
        if (element.parents('.submenu-item').length) {
          element.addClass('active');
        }
      }
    }

    function normalizePath(url) {
      try {
        var path = new URL(url, window.location.origin).pathname;
        return path.replace(/\/+$/g, '') || '/';
      } catch (e) {
        var s = String(url || '').split('?')[0];
        if (!s) return '';
        // Keep only the pathname part for absolute URLs.
        s = s.replace(window.location.origin, '');
        if (s[0] !== '/') s = '/' + s;
        return s.replace(/\/+$/g, '') || '/';
      }
    }

    var currentPath = normalizePath(window.location.pathname);
    $('.nav li a', sidebar).each(function() {
      var $this = $(this);
      addActiveClass($this);
    })

    $('.horizontal-menu .nav li > a').each(function() {
      var $this = $(this);
      addActiveClass($this);
    })

    //Close other submenu in sidebar on opening any

    sidebar.on('show.bs.collapse', '.collapse', function() {
      sidebar.find('.collapse.show').collapse('hide');
    });


    //Change sidebar and content-wrapper height
    applyStyles();

    function applyStyles() {
      //Applying perfect scrollbar
      if (!body.hasClass("rtl")) {
        if ($('.settings-panel .tab-content .tab-pane.scroll-wrapper').length) {
          const settingsPanelScroll = new PerfectScrollbar('.settings-panel .tab-content .tab-pane.scroll-wrapper');
        }
        if ($('.chats').length) {
          const chatsScroll = new PerfectScrollbar('.chats');
        }
        if (body.hasClass("sidebar-fixed")) {
          var fixedSidebarScroll = new PerfectScrollbar('#sidebar .nav');
        }
      }
    }

    $('[data-toggle="minimize"]').on("click", function() {
      if ((body.hasClass('sidebar-toggle-display')) || (body.hasClass('sidebar-absolute'))) {
        body.toggleClass('sidebar-hidden');
      } else {
        body.toggleClass('sidebar-icon-only');
      }
    });

    //checkbox and radios
    $(".form-check label, .form-radio label").append('<i class="input-helper"></i>');
  });
})(jQuery);
