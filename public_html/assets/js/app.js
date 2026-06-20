/* TradeVision Pro - front-end API client & helpers (vanilla JS) */
(function (global) {
  'use strict';

  const TOKEN_KEY = 'tvp_token';

  const TVP = {
    token() { return localStorage.getItem(TOKEN_KEY) || ''; },
    setToken(t) { t ? localStorage.setItem(TOKEN_KEY, t) : localStorage.removeItem(TOKEN_KEY); },
    isAuthed() { return !!this.token(); },

    async api(path, opts = {}) {
      const headers = Object.assign(
        { 'Content-Type': 'application/json' },
        opts.headers || {}
      );
      const t = this.token();
      if (t) headers['Authorization'] = 'Bearer ' + t;
      if (opts.body && typeof opts.body === 'object' && !(opts.body instanceof FormData)) {
        opts.body = JSON.stringify(opts.body);
      }
      if (opts.body instanceof FormData) delete headers['Content-Type'];

      let res, json;
      try {
        res = await fetch('/api/' + path.replace(/^\//, ''), Object.assign({ credentials: 'same-origin' }, opts, { headers }));
        json = await res.json().catch(() => ({}));
      } catch (e) {
        throw new Error('Network error. Please try again.');
      }
      if (res.status === 401) {
        this.setToken('');
        if (!path.startsWith('auth/')) { location.href = '/login.php'; }
      }
      if (!res.ok || json.success === false) {
        throw new Error(json.error || ('Request failed (' + res.status + ')'));
      }
      return json.data !== undefined ? json.data : json;
    },

    get(p) { return this.api(p, { method: 'GET' }); },
    post(p, body) { return this.api(p, { method: 'POST', body }); },
    put(p, body) { return this.api(p, { method: 'PUT', body }); },
    patch(p, body) { return this.api(p, { method: 'PATCH', body }); },
    del(p) { return this.api(p, { method: 'DELETE' }); },


    async logout() {
      try { await this.api('auth/logout', { method: 'POST' }); } catch (e) {}
      this.setToken('');
      location.href = '/login.php';
    },

    async requireAuth() {
      if (!this.isAuthed()) { location.href = '/login.php'; return null; }
      try { const d = await this.get('auth/me'); return d.user; }
      catch (e) { location.href = '/login.php'; return null; }
    },

    // ---- formatting helpers ----
    fmt(n, d = 2) {
      if (n === null || n === undefined || isNaN(n)) return '--';
      const num = Number(n);
      if (Math.abs(num) >= 1000) return num.toLocaleString(undefined, { maximumFractionDigits: d });
      if (Math.abs(num) >= 1) return num.toFixed(d);
      return num.toPrecision(4);
    },
    fmtPrice(n) {
      if (n === null || n === undefined || isNaN(n)) return '--';
      const num = Number(n);
      if (num >= 1000) return num.toLocaleString(undefined, { maximumFractionDigits: 2 });
      if (num >= 1) return num.toFixed(4);
      return num.toFixed(8).replace(/0+$/, '').replace(/\.$/, '');
    },
    pct(n) {
      if (n === null || n === undefined || isNaN(n)) return '--';
      const v = Number(n);
      return (v >= 0 ? '+' : '') + v.toFixed(2) + '%';
    },
    pctClass(n) { return Number(n) >= 0 ? 'dir-long' : 'dir-short'; },
    ratingLabel(r) {
      return ({ strong_buy: 'Strong Buy', buy: 'Buy', neutral: 'Neutral', sell: 'Sell', strong_sell: 'Strong Sell' })[r] || r;
    },
    timeAgo(ts) {
      if (!ts) return '--';
      const s = Math.floor((Date.now() - new Date(ts.replace(' ', 'T') + 'Z').getTime()) / 1000);
      if (s < 60) return s + 's ago';
      if (s < 3600) return Math.floor(s / 60) + 'm ago';
      if (s < 86400) return Math.floor(s / 3600) + 'h ago';
      return Math.floor(s / 86400) + 'd ago';
    },


    // ---- toast notifications ----
    toast(msg, type = 'info', ms = 3500) {
      let wrap = document.getElementById('toast-wrap');
      if (!wrap) { wrap = document.createElement('div'); wrap.id = 'toast-wrap'; document.body.appendChild(wrap); }
      const el = document.createElement('div');
      el.className = 'toast ' + type;
      el.textContent = msg;
      wrap.appendChild(el);
      setTimeout(() => { el.style.opacity = '0'; el.style.transition = '.3s'; setTimeout(() => el.remove(), 300); }, ms);
    },

    // ---- browser push permission for alerts ----
    async enableBrowserNotifications() {
      if (!('Notification' in window)) return false;
      if (Notification.permission === 'granted') return true;
      const p = await Notification.requestPermission();
      return p === 'granted';
    },
    browserNotify(title, body) {
      if (('Notification' in window) && Notification.permission === 'granted') {
        new Notification(title, { body, icon: '/assets/img/logo.png' });
      }
    },

    // ---- poll notifications (badge + browser push) ----
    startNotificationPolling(onUpdate) {
      if (!this.isAuthed()) return;
      const seen = new Set();
      const tick = async () => {
        try {
          const d = await this.get('notifications');
          (d.notifications || []).forEach(n => {
            if (!n.is_read && !seen.has(n.id)) { seen.add(n.id); this.browserNotify(n.title, n.body || ''); }
          });
          if (onUpdate) onUpdate(d);
        } catch (e) {}
      };
      tick();
      setInterval(tick, 30000);
    }
  };

  global.TVP = TVP;
})(window);
