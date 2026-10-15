/**
 * style-lang-fix.js
 * این اسکریپت را قبل از </body> در تمام فایل‌های style_*.html اضافه کنید
 * (style_european.html, style_american.html, style_eastasian.html, style_arabic.html, style_iranian.html)
 * 
 * مشکل: header و footer در صفحات سبک فارسی می‌مانند حتی وقتی زبان انگلیسی انتخاب شده
 * دلیل: سیستم i18n فقط در index.html وجود دارد، style pages باید مستقیم swap کنند
 */

(function() {
  'use strict';

  // ترجمه‌های مورد نیاز برای header و footer
  var TRANS = {
    fa: {
      'nav.home':     'خانه',
      'nav.styles':   'سبک‌ها',
      'nav.showcase': 'گالری تعاملی',
      'nav.services': 'خدمات',
      'nav.contact':  'تماس',
      'nav.cta':      'مشاوره رایگان',
      'brand':        'دکوراسیون آرتا',
      'brandTag':     'استودیو دکوراسیون منزل',
      'footer.about': 'دکوراسیون آرتا؛ استودیو طراحی و مشاوره دکوراسیون منزل. از ایده تا اجرا، در پنج سبک جهانی همراه شما هستیم.',
      'footer.links': 'دسترسی سریع',
      'footer.styles':'سبک‌ها',
      'footer.contact':'تماس',
      'footer.rights':'© ۲۰۲۶ دکوراسیون آرتا · تمامی حقوق محفوظ است.',
      'c.addrV':      'تهران، خیابان ولیعصر، مجتمع دید، واحد ۱۲',
      'theme.dark':   'حالت تاریک',
      'theme.light':  'حالت روشن'
    },
    en: {
      'nav.home':     'Home',
      'nav.styles':   'Styles',
      'nav.showcase': 'Interactive Gallery',
      'nav.services': 'Services',
      'nav.contact':  'Contact',
      'nav.cta':      'Free Consultation',
      'brand':        'Arta Decor',
      'brandTag':     'Home Decoration Studio',
      'footer.about': 'Arta Decor — home interior design & consultancy studio. From idea to execution, in five world styles.',
      'footer.links': 'Quick Links',
      'footer.styles':'Styles',
      'footer.contact':'Contact',
      'footer.rights':'© 2026 Arta Decor. All rights reserved.',
      'c.addrV':      'Tehran, Valiasr St., Did Complex, Unit 12',
      'theme.dark':   'Dark mode',
      'theme.light':  'Light mode'
    }
  };

  // نقشه ترجمه نام سبک‌ها در drawer و footer
  var STYLE_NAMES = {
    fa: { european:'اروپایی', american:'آمریکایی', eastasian:'آسیای شرقی', arabic:'عربی', iranian:'ایرانی' },
    en: { european:'European', american:'American', eastasian:'East Asian', arabic:'Arabic', iranian:'Iranian' }
  };

  // خواندن زبان ذخیره‌شده
  var _lang = 'fa';
  try { _lang = localStorage.getItem('dcr-lang') || 'fa'; } catch(e) {}

  function t(key, lang) {
    lang = lang || _lang;
    return (TRANS[lang] && TRANS[lang][key]) || (TRANS['fa'][key]) || key;
  }

  function applyLang(lang) {
    _lang = lang;
    try { localStorage.setItem('dcr-lang', lang); } catch(e) {}

    var isRtl = (lang === 'fa');
    document.documentElement.lang = lang;
    document.documentElement.dir = isRtl ? 'rtl' : 'ltr';

    // --- Drawer RTL/LTR fix ---
    var drawer = document.getElementById('drawer');
    if (drawer) {
      // در CSS هست ولی direction تغییر نمی‌کند بدون این
      drawer.style.right = isRtl ? '0' : 'auto';
      drawer.style.left  = isRtl ? 'auto' : '0';
    }

    // --- Header: لوگو ---
    var logoBrands = document.querySelectorAll('.logo b');
    logoBrands.forEach(function(el) { el.textContent = t('brand', lang); });
    var logoSmalls = document.querySelectorAll('.logo small');
    logoSmalls.forEach(function(el) { el.textContent = t('brandTag', lang); });

    // --- Header: nav links ---
    var navKeys = ['home','styles','showcase','services','contact'];
    var hrefs   = ['index.html#home','index.html#styles','index.html#showcase','index.html#services','index.html#contact'];
    var deskNavLinks = document.querySelectorAll('nav.desk a');
    deskNavLinks.forEach(function(a, i) {
      var href = a.getAttribute('href');
      for (var j = 0; j < hrefs.length; j++) {
        if (href === hrefs[j] || href === '#' + navKeys[j]) {
          a.textContent = t('nav.' + navKeys[j], lang);
          break;
        }
      }
    });

    // --- Header: CTA button ---
    var ctaBtns = document.querySelectorAll('header .btn-primary');
    ctaBtns.forEach(function(btn) { btn.textContent = t('nav.cta', lang); });

    // --- Lang pill ---
    var langPill = document.getElementById('langPill');
    if (langPill) langPill.textContent = (lang === 'fa') ? 'EN' : 'FA';

    // --- Theme drawer button label ---
    var isLight = document.documentElement.classList.contains('light');
    var themeLabel = isLight ? t('theme.dark', lang) : t('theme.light', lang);
    var drawerThemeLabel = document.getElementById('themeBtnDrawerLabel');
    if (drawerThemeLabel) drawerThemeLabel.textContent = themeLabel;

    // --- Drawer nav buttons ---
    // نام سبک‌ها در drawer
    var drNavBtns = document.querySelectorAll('.dr-nav button span:not(.ic)');
    var drNavMap = [
      { fa:'خانه',        en:'Home' },
      { fa:'سبک‌ها',      en:'Styles' },
      { fa:'اروپایی',     en:'European' },
      { fa:'آمریکایی',    en:'American' },
      { fa:'آسیای شرقی',  en:'East Asian' },
      { fa:'عربی',        en:'Arabic' },
      { fa:'ایرانی',      en:'Iranian' },
      { fa:'گالری تعاملی',en:'Interactive Gallery' },
      { fa:'خدمات',       en:'Services' },
      { fa:'تماس',        en:'Contact' }
    ];
    drNavBtns.forEach(function(span, i) {
      if (drNavMap[i]) {
        span.textContent = drNavMap[i][lang] || drNavMap[i]['fa'];
      }
    });

    // --- Drawer: دکمه مشاوره ---
    var drFootBtn = document.querySelector('.dr-foot .btn-primary');
    if (drFootBtn) drFootBtn.textContent = t('nav.cta', lang);

    // --- Footer ---
    // عنوان‌های h4
    var ftH4s = document.querySelectorAll('footer h4');
    var ftH4Keys = ['footer.links', 'footer.styles', 'footer.contact'];
    ftH4s.forEach(function(h4, i) {
      if (ftH4Keys[i]) h4.textContent = t(ftH4Keys[i], lang);
    });

    // لینک‌های footer
    var ftLinks = document.querySelectorAll('footer .ft-links a');
    var ftLinkMap = {
      'index.html#home':     'nav.home',
      'index.html#styles':   'nav.styles',
      'index.html#showcase': 'nav.showcase',
      'index.html#services': 'nav.services',
      'index.html#contact':  'nav.contact',
      'style_european.html': null,
      'style_american.html': null,
      'style_eastasian.html': null,
      'style_arabic.html':   null,
      'style_iranian.html':  null
    };
    var styleHrefs = ['style_european.html','style_american.html','style_eastasian.html','style_arabic.html','style_iranian.html'];
    var styleIds   = ['european','american','eastasian','arabic','iranian'];
    ftLinks.forEach(function(a) {
      var href = a.getAttribute('href');
      var key  = ftLinkMap[href];
      if (key) {
        a.textContent = t(key, lang);
      } else {
        // style links
        for (var si = 0; si < styleHrefs.length; si++) {
          if (href === styleHrefs[si]) {
            a.textContent = STYLE_NAMES[lang][styleIds[si]];
            break;
          }
        }
      }
    });

    // متن about footer
    var ftAbout = document.querySelector('footer .ft-grid > div:first-child p');
    if (ftAbout) ftAbout.textContent = t('footer.about', lang);

    // copyright
    var ftRights = document.querySelector('.ft-bottom span:first-child');
    if (ftRights) ftRights.textContent = t('footer.rights', lang);

    // آدرس در footer
    var ftAddrSpan = document.querySelector('footer .ft-links span:last-child');
    if (ftAddrSpan) ftAddrSpan.textContent = t('c.addrV', lang);

    // --- fa-text / en-text spans در hero section ---
    document.querySelectorAll('.fa-text').forEach(function(el) {
      el.style.display = (lang === 'fa') ? '' : 'none';
    });
    document.querySelectorAll('.en-text').forEach(function(el) {
      el.style.display = (lang === 'en') ? '' : 'none';
    });
  }

  // اعمال اولیه زبان
  applyLang(_lang);

  // اتصال به دکمه تغییر زبان
  var langPill = document.getElementById('langPill');
  if (langPill) {
    // حذف onclick قدیمی
    langPill.onclick = function() {
      applyLang(_lang === 'fa' ? 'en' : 'fa');
    };
  }

  // sync با theme toggle برای label درست
  var origSetTheme = window.setTheme;
  if (typeof origSetTheme === 'function') {
    window.setTheme = function(mode, save, originEl) {
      origSetTheme(mode, save, originEl);
      // بعد از toggle، label را با زبان فعلی آپدیت کن
      setTimeout(function() {
        var isLight = document.documentElement.classList.contains('light');
        var label = isLight ? t('theme.dark', _lang) : t('theme.light', _lang);
        var dl = document.getElementById('themeBtnDrawerLabel');
        if (dl) dl.textContent = label;
      }, 50);
    };
  }

  // expose برای استفاده خارجی
  window.__artaLang = { apply: applyLang, t: t };

})();
