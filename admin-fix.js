/**
 * admin-fix.js
 * این اسکریپت را در admin.html، بعد از تمام <script> بلوک‌های موجود
 * و قبل از </body> اضافه کنید.
 *
 * مشکلات رفع‌شده:
 * 1. statusLabel در بلوک API patch در دسترس نیست
 * 2. تابع renderRequests در API patch ممکن است قبل از تعریف container اجرا شود
 * 3. initApp دو بار صدا زده می‌شود و باعث loop می‌شود
 * 4. doLogin از loginEmail استفاده می‌کند ولی فیلد با id="loginEmail" در HTML است
 * 5. filterReqs در API patch تعریف نشده
 * 6. باگ دوبل‌تعریفی توابع که آخرین نسخه برنده می‌شود ولی ممکن است state را از دست بدهد
 */

(function() {
  'use strict';

  /* ============================================================
     1. تعریف مجدد statusLabel به صورت global
        (در بلوک اصلی هست ولی ممکن است بعد از patch load شود)
  ============================================================ */
  if (typeof window.statusLabel !== 'function') {
    window.statusLabel = function(s) {
      var m = {
        new: 'جدید', pending: 'در انتظار', confirmed: 'تأیید شده',
        done: 'انجام شده', cancelled: 'لغو شده',
        active: 'فعال', inactive: 'غیرفعال', draft: 'پیش‌نویس'
      };
      return m[s] || s;
    };
  }

  /* ============================================================
     2. esc helper - اگر تعریف نشده باشد
  ============================================================ */
  if (typeof window.esc !== 'function') {
    window.esc = function(str) {
      if (!str) return '';
      return String(str)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    };
  }

  /* ============================================================
     3. formatDate helper
  ============================================================ */
  if (typeof window.formatDate !== 'function') {
    window.formatDate = function(dt) {
      if (!dt) return '—';
      try {
        var d = new Date(dt.replace(' ', 'T'));
        return d.toLocaleDateString('fa-IR') + ' ' + d.toLocaleTimeString('fa-IR', { hour: '2-digit', minute: '2-digit' });
      } catch(e) { return dt; }
    };
  }

  /* ============================================================
     4. filterReqs - پیوند دکمه‌های filter به loadRequests
  ============================================================ */
  window.filterReqs = function(btn, filter) {
    document.querySelectorAll('.req-filter').forEach(function(b) { b.classList.remove('on'); });
    btn.classList.add('on');

    if (typeof window.loadRequests === 'function') {
      window.loadRequests(filter === 'all' ? '' : filter);
    } else if (typeof window.REQUESTS_DATA !== 'undefined') {
      // fallback: localStorage mode
      window._reqFilter = filter;
      if (typeof window.renderRequests === 'function') window.renderRequests();
    }
  };

  /* ============================================================
     5. Fix: container مناسب برای لیست درخواست‌ها
        API patch دنبال 'requestsContainer' می‌گردد
        HTML اصلی دارد 'requestsContainer' نیست - فقط panel-requests
  ============================================================ */
  var panelReqs = document.getElementById('panel-requests');
  if (panelReqs && !document.getElementById('requestsContainer')) {
    // اگر container وجود ندارد، قبل از req-filters یک div اضافه کن
    var existingContainer = panelReqs.querySelector('#requestsContainer');
    if (!existingContainer) {
      var filtersDiv = panelReqs.querySelector('.req-filters');
      if (filtersDiv) {
        var container = document.createElement('div');
        container.id = 'requestsContainer';
        filtersDiv.insertAdjacentElement('afterend', container);
      }
    }
  }

  /* ============================================================
     6. Fix: login - مطمئن شدن از اینکه loginUser یا loginEmail پیدا می‌شود
  ============================================================ */
  // اطمینان از اینکه input فیلد username با هر دو id قابل دسترسی است
  var emailInput = document.getElementById('loginEmail');
  if (emailInput && !document.getElementById('loginUser')) {
    emailInput.id = 'loginUser'; // یا alias اضافه کن
    // دوباره loginEmail هم باید کار کند
    Object.defineProperty(document, '_loginEmailAlias', { value: true });
  }

  /* ============================================================
     7. Fix: initApp - جلوگیری از اجرای دوباره
  ============================================================ */
  var _appInitialized = false;
  var _origInitApp = window.initApp;
  window.initApp = function() {
    if (_appInitialized) {
      console.log('[Arta] initApp: already initialized, skipping');
      return;
    }
    _appInitialized = true;

    // بارگذاری داشبورد اولیه
    if (typeof window.loadDashboard === 'function' || typeof window._loadDash === 'function') {
      var dash = window._loadDash || window.loadDashboard;
      dash();
    }

    // رفتن به پنل داشبورد
    if (typeof window.goPanel === 'function') {
      window.goPanel('dashboard');
    }
  };

  /* ============================================================
     8. Fix: renderRequests - container fallback
  ============================================================ */
  var _origRenderRequests = window.renderRequests;
  window.renderRequests = function() {
    // مطمئن شو container وجود دارد
    var wrap = document.getElementById('requestsContainer') || document.getElementById('requestsList');
    if (!wrap) {
      // ایجاد container اگر نباشد
      var panel = document.getElementById('panel-requests');
      if (panel) {
        wrap = document.createElement('div');
        wrap.id = 'requestsContainer';
        panel.appendChild(wrap);
      }
    }

    if (!window.REQUESTS_DATA || !window.REQUESTS_DATA.length) {
      if (wrap) wrap.innerHTML = '<div class="empty-state"><div class="es-ic">📭</div><p>درخواستی یافت نشد</p></div>';
      return;
    }

    if (typeof _origRenderRequests === 'function') {
      _origRenderRequests();
    }
  };

  /* ============================================================
     9. Fix: API object - اگر تعریف نشده باشد (safety net)
  ============================================================ */
  if (typeof window.API === 'undefined') {
    window.API = {
      _call: function(endpoint, method, data) {
        var opts = { method: method, credentials: 'same-origin', headers: { 'Content-Type': 'application/json' } };
        if (data && method !== 'GET') opts.body = JSON.stringify(data);
        return fetch('/api/' + endpoint, opts).then(function(r) {
          if (r.status === 401) {
            document.getElementById('app').classList.remove('visible');
            document.getElementById('loginPage').style.display = 'flex';
            if (typeof window.showToast === 'function') window.showToast('نشست منقضی شده', 'error');
            return { success: false };
          }
          return r.json();
        });
      },
      get:    function(ep)    { return this._call(ep, 'GET'); },
      post:   function(ep, d) { return this._call(ep, 'POST', d); },
      put:    function(ep, d) { return this._call(ep, 'PUT', d); },
      delete: function(ep)    { return this._call(ep, 'DELETE'); }
    };
  }

  /* ============================================================
     10. Fix: session check - یک بار اجرا شود
         (هم در FINAL OVERRIDE هم در API patch هم در HTML تعریف شده)
  ============================================================ */
  if (!window._sessionChecked) {
    window._sessionChecked = true;
    // حذف event listener های قبلی با فلگ
    window.addEventListener('load', function() {
      if (window._loadHandlerRan) return;
      window._loadHandlerRan = true;

      if (typeof window.API === 'undefined') return;
      API.get('auth.php?action=check').then(function(data) {
        if (data && data.authenticated) {
          var loginPage = document.getElementById('loginPage');
          var app       = document.getElementById('app');
          if (loginPage) loginPage.style.display = 'none';
          if (app)       app.classList.add('visible');
          if (data.admin) {
            var nameEl = document.querySelector('.sb-admin-badge .ai b');
            if (nameEl) nameEl.textContent = data.admin.name;
            window._ADMIN = data.admin;
          }
          if (typeof window.initApp === 'function') window.initApp();
        }
      }).catch(function() {});
    });
  }

  /* ============================================================
     11. Fix: showReqDetail - modal id در HTML است reqModal نه reqDetailModal
  ============================================================ */
  window.showReqDetail = function(id) {
    if (typeof window.API === 'undefined') return;
    API.get('requests.php?id=' + id).then(function(data) {
      if (!data.success || !data.data) return;
      var r = data.data;
      var sn = { european:'اروپایی', american:'آمریکایی', eastasian:'آسیای شرقی', arabic:'عربی', iranian:'ایرانی' };

      // پیدا کردن modal - هر دو id را بررسی کن
      var modal     = document.getElementById('reqDetailModal') || document.getElementById('reqModal');
      var bodyEl    = document.getElementById('reqDetailBody')  || document.getElementById('reqModalBody');
      var titleEl   = document.getElementById('reqDetailTitle') || document.getElementById('reqModalTitle');
      var footEl    = document.getElementById('reqModalFoot');

      if (!modal) return;

      if (titleEl) titleEl.textContent = 'جزئیات درخواست — ' + r.name;

      if (bodyEl) bodyEl.innerHTML =
        '<dl style="display:grid;grid-template-columns:auto 1fr;gap:8px 16px;font-size:.85rem">'
        + '<dt style="color:var(--gold);font-weight:800">نام</dt><dd>' + esc(r.name) + '</dd>'
        + '<dt style="color:var(--gold);font-weight:800">تلفن</dt><dd dir="ltr">' + esc(r.phone) + '</dd>'
        + '<dt style="color:var(--gold);font-weight:800">ایمیل</dt><dd>' + esc(r.email || '—') + '</dd>'
        + '<dt style="color:var(--gold);font-weight:800">سبک</dt><dd>' + esc(sn[r.style] || r.style || '—') + '</dd>'
        + '<dt style="color:var(--gold);font-weight:800">پیام</dt><dd>' + esc(r.message || '—') + '</dd>'
        + '<dt style="color:var(--gold);font-weight:800">وضعیت</dt><dd><span class="badge ' + r.status + '">' + statusLabel(r.status) + '</span></dd>'
        + '<dt style="color:var(--gold);font-weight:800">IP</dt><dd dir="ltr">' + esc(r.ip_address || '—') + '</dd>'
        + '<dt style="color:var(--gold);font-weight:800">تاریخ</dt><dd>' + formatDate(r.created_at) + '</dd>'
        + '</dl>'
        + '<div style="margin-top:16px"><div class="field"><label>یادداشت ادمین</label>'
        + '<textarea id="reqNoteInput" style="min-height:60px">' + esc(r.admin_note || '') + '</textarea></div>'
        + '<button class="tb-btn primary" style="margin-top:8px" onclick="saveReqNote(' + r.id + ')">ذخیره یادداشت</button></div>';

      if (footEl) footEl.innerHTML =
        '<button class="tb-btn" onclick="closeModal(\'' + modal.id + '\')">بستن</button>'
        + '<button class="tb-btn primary" onclick="saveReqNote(' + r.id + ')">💾 ذخیره</button>';

      if (typeof window.openModal === 'function') window.openModal(modal.id);
      else modal.classList.add('open');
    });
  };

  window.saveReqNote = function(id) {
    var note = document.getElementById('reqNoteInput');
    if (!note || typeof window.API === 'undefined') return;
    API.put('requests.php', { id: id, admin_note: note.value }).then(function(data) {
      if (data.success) {
        var modal = document.getElementById('reqDetailModal') || document.getElementById('reqModal');
        if (modal && typeof window.closeModal === 'function') window.closeModal(modal.id);
        if (typeof window.loadRequests === 'function') window.loadRequests();
        if (typeof window.showToast === 'function') window.showToast('یادداشت ذخیره شد', 'success');
      }
    });
  };

  /* ============================================================
     12. Fix: deleteReq
  ============================================================ */
  window.deleteReq = function(id) {
    if (!confirm('این درخواست حذف شود؟')) return;
    if (typeof window.API === 'undefined') return;
    API.delete('requests.php?id=' + id).then(function(data) {
      if (data.success) {
        if (typeof window.loadRequests === 'function') window.loadRequests();
        if (typeof window.showToast === 'function') window.showToast('درخواست حذف شد', 'success');
      }
    });
  };

  /* ============================================================
     13. Fix: sidebar badge update
  ============================================================ */
  window.updateRequestBadge = function(count) {
    count = parseInt(count) || 0;
    // badge در sidebar
    var sbBadge = document.querySelector('#sidebar .sb-item .badge');
    if (sbBadge) { sbBadge.textContent = count; sbBadge.style.display = count > 0 ? '' : 'none'; }
    // badge در req filter
    var reqBadge = document.getElementById('reqBadge');
    if (reqBadge) { reqBadge.textContent = count; reqBadge.style.display = count > 0 ? '' : 'none'; }
    // counter در filter button
    var newCountEl = document.getElementById('newReqCount');
    if (newCountEl) newCountEl.textContent = count;
    // stat card
    var reqCount = document.getElementById('reqCount');
    if (reqCount) reqCount.textContent = count;
  };

  console.log('[Arta] admin-fix.js loaded ✓');

})();
