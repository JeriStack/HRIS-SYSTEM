/**
 * Employee HRIS & Leave Management System
 * Real-time Notifications Client (WebSocket)
 *
 * Flow: POST /realtime/ticket (session + CSRF) -> open WebSocket -> send the
 * single-use ticket as the first message -> receive events and unread counts.
 * After every (re)connect the notification history is re-synced over the
 * authenticated HTTP API, so missed events are never lost.
 *
 * Security notes:
 *  - Tickets never appear in URLs and are discarded right after use.
 *  - Notification text is rendered with textContent only (no innerHTML).
 *  - Nothing from the socket is written to the browser console.
 *  - Background requests send X-HRIS-Background: 1 so they do not extend the
 *    server-side inactivity timeout.
 */
(function () {
    'use strict';

    var root = document.getElementById('rtNotify');
    if (!root) {
        return;
    }

    var cfg = {
        enabled: root.getAttribute('data-enabled') === '1' && 'WebSocket' in window,
        wsUrl: root.getAttribute('data-ws-url') || '',
        ticketUrl: root.getAttribute('data-ticket-url') || '',
        historyUrl: root.getAttribute('data-history-url') || '',
        inboxUrl: root.getAttribute('data-inbox-url') || '',
        baseUrl: (root.getAttribute('data-base-url') || '').replace(/\/+$/, ''),
        csrf: root.getAttribute('data-csrf') || ''
    };

    var el = {
        bell: document.getElementById('rtBell'),
        count: document.getElementById('rtCount'),
        dot: document.getElementById('rtDot'),
        panel: document.getElementById('rtPanel'),
        conn: document.getElementById('rtConn'),
        list: document.getElementById('rtList')
    };

    var STATE_LABELS = {
        connecting: 'Connecting…',
        live: 'Live',
        reconnecting: 'Reconnecting…',
        offline: 'Offline – will reconnect',
        expired: 'Session expired. Please sign in again.',
        signed_out: 'Signed out',
        unavailable: 'Live updates unavailable',
        disabled: 'Live updates off'
    };

    var MAX_LIST_ITEMS = 8;
    var MAX_SEEN = 300;

    var ws = null;
    var connecting = false;
    var stopped = false;
    var attempt = 0;
    var authFailures = 0;
    var retryTimer = null;
    var unread = parseInt(root.getAttribute('data-unread') || '0', 10) || 0;
    var seen = new Set();
    var seenOrder = [];
    var historyLoadedAt = 0;

    // ------------------------------------------------------------------
    // UI helpers
    // ------------------------------------------------------------------
    function setState(state) {
        if (el.dot) {
            el.dot.setAttribute('data-state', state);
        }
        if (el.conn) {
            el.conn.textContent = STATE_LABELS[state] || '';
        }
        root.setAttribute('data-state', state);
    }

    function setCount(value) {
        var n = Math.max(0, parseInt(value, 10) || 0);
        unread = n;
        if (el.count) {
            el.count.textContent = n > 99 ? '99+' : String(n);
            el.count.hidden = n === 0;
        }
        if (el.bell) {
            el.bell.setAttribute('aria-label', n > 0 ? 'Notifications, ' + n + ' unread' : 'Notifications');
        }
        var sidebarBadge = document.getElementById('sidebarUnreadBadge');
        if (sidebarBadge) {
            sidebarBadge.textContent = n > 99 ? '99+' : String(n);
            sidebarBadge.hidden = n === 0;
            sidebarBadge.setAttribute('aria-label', n + ' unread notifications');
        }
    }

    function remember(id) {
        if (!id || seen.has(id)) {
            return false;
        }
        seen.add(id);
        seenOrder.push(id);
        if (seenOrder.length > MAX_SEEN) {
            seen.delete(seenOrder.shift());
        }
        return true;
    }

    function safeLink(link) {
        if (typeof link !== 'string' || !/^[a-z0-9][a-z0-9\/_-]{0,120}$/i.test(link)) {
            return cfg.inboxUrl;
        }
        return cfg.baseUrl + '/' + link;
    }

    function buildItem(item) {
        var li = document.createElement('li');
        li.className = 'rt-item' + (item.unread ? ' is-unread' : '') + (item.level ? ' rt-level-' + item.level : '');

        var a = document.createElement('a');
        a.href = item.href || cfg.inboxUrl;

        var title = document.createElement('strong');
        title.className = 'rt-item-title';
        title.textContent = item.title || 'Notification';
        a.appendChild(title);

        if (item.message) {
            var msg = document.createElement('p');
            msg.className = 'rt-item-message';
            msg.textContent = item.message;
            a.appendChild(msg);
        }

        var time = document.createElement('small');
        time.className = 'rt-item-time';
        time.textContent = item.time || '';
        a.appendChild(time);

        li.appendChild(a);
        return li;
    }

    function renderList(items) {
        if (!el.list) {
            return;
        }
        el.list.removeAttribute('aria-busy');
        delete el.list.dataset.loading;
        while (el.list.firstChild) {
            el.list.removeChild(el.list.firstChild);
        }
        if (!items.length) {
            var empty = document.createElement('li');
            empty.className = 'rt-empty';
            empty.textContent = 'You are all caught up.';
            el.list.appendChild(empty);
            return;
        }
        items.slice(0, MAX_LIST_ITEMS).forEach(function (item) {
            el.list.appendChild(buildItem(item));
        });
    }

    function prependItem(item) {
        if (!el.list) {
            return;
        }
        var placeholder = el.list.querySelector('.rt-empty');
        if (placeholder) {
            placeholder.remove();
        }
        el.list.insertBefore(buildItem(item), el.list.firstChild);
        while (el.list.children.length > MAX_LIST_ITEMS) {
            el.list.removeChild(el.list.lastChild);
        }
    }

    function isDashboardOrWorkflowPage() {
        var p = (window.location.pathname || '').toLowerCase();
        return p === '/' ||
               p === '/index.php' ||
               p.indexOf('/dashboard') !== -1 ||
               p.indexOf('/employees') !== -1 ||
               p.indexOf('/leaves') !== -1 ||
               p.indexOf('/employee/profile') !== -1 ||
               p.indexOf('/activity-logs') !== -1 ||
               p.indexOf('/reports') !== -1 ||
               p.indexOf('/notifications') !== -1;
    }

    function isUserActivelyInputting() {
        var active = document.activeElement;
        if (active) {
            var tag = (active.tagName || '').toLowerCase();
            if (tag === 'input' || tag === 'textarea' || tag === 'select') {
                return true;
            }
        }
        if (document.querySelector('.modal.is-open, .modal-backdrop.is-open, .modal.show, [role="dialog"]:not([hidden])')) {
            return true;
        }
        return false;
    }

    function toast(item, willAutoReload) {
        var stack = document.getElementById('rtToasts');
        if (!stack) {
            stack = document.createElement('div');
            stack.id = 'rtToasts';
            stack.className = 'rt-toasts';
            stack.setAttribute('role', 'region');
            stack.setAttribute('aria-live', 'polite');
            stack.setAttribute('aria-label', 'New notifications');
            document.body.appendChild(stack);
        }

        var box = document.createElement('div');
        box.className = 'rt-toast rt-level-' + (item.level || 'info');

        var body = document.createElement('div');
        body.className = 'rt-toast-body';
        var title = document.createElement('strong');
        title.textContent = item.title;
        var msg = document.createElement('p');
        msg.textContent = item.message;
        var link = document.createElement('a');
        link.href = item.href;
        link.textContent = 'View';
        body.appendChild(title);
        body.appendChild(msg);
        body.appendChild(link);

        if (isDashboardOrWorkflowPage()) {
            var reloadBar = document.createElement('div');
            reloadBar.className = 'rt-toast-reload-bar';

            var reloadHint = document.createElement('span');
            reloadHint.className = 'rt-toast-reload-hint';
            reloadHint.textContent = willAutoReload ? '🔄 Updating view in 1.5s…' : '🔄 Live update available:';
            reloadBar.appendChild(reloadHint);

            var reloadBtn = document.createElement('button');
            reloadBtn.type = 'button';
            reloadBtn.className = 'rt-toast-reload-btn';
            reloadBtn.textContent = 'Refresh Now';
            reloadBtn.addEventListener('click', function () {
                window.location.reload();
            });
            reloadBar.appendChild(reloadBtn);
            body.appendChild(reloadBar);
        }

        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'rt-toast-close';
        close.setAttribute('aria-label', 'Dismiss notification');
        close.textContent = '×';
        close.addEventListener('click', function () { box.remove(); });

        box.appendChild(body);
        box.appendChild(close);
        stack.appendChild(box);

        while (stack.children.length > 3) {
            stack.removeChild(stack.firstChild);
        }
        setTimeout(function () { box.remove(); }, 7000);
    }

    // ------------------------------------------------------------------
    // Panel (accessible disclosure)
    // ------------------------------------------------------------------
    function openPanel() {
        el.panel.hidden = false;
        el.bell.setAttribute('aria-expanded', 'true');
        if (Date.now() - historyLoadedAt > 30000) {
            if (window.Skeleton && el.list && (!el.list.firstChild || el.list.querySelector('.rt-empty'))) {
                Skeleton.show(el.list, 'list', { count: 3, hasBadge: false, loadingText: 'Loading notification history...' });
            }
            refreshHistory();
        }
    }

    function closePanel(returnFocus) {
        el.panel.hidden = true;
        el.bell.setAttribute('aria-expanded', 'false');
        if (returnFocus) {
            el.bell.focus();
        }
    }

    if (el.bell && el.panel) {
        el.bell.addEventListener('click', function (e) {
            e.stopPropagation();
            if (el.panel.hidden) {
                openPanel();
            } else {
                closePanel(false);
            }
        });
        document.addEventListener('click', function (e) {
            if (!el.panel.hidden && !root.contains(e.target)) {
                closePanel(false);
            }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !el.panel.hidden) {
                closePanel(true);
            }
        });
    }

    // ------------------------------------------------------------------
    // HTTP: history sync (authoritative source after reconnects)
    // ------------------------------------------------------------------
    function resolveHttpUrl(url) {
        if (!url) {
            return '';
        }
        try {
            var parsed = new URL(url, window.location.href);
            // If ticket or history URL points to the same hostname (e.g. localhost or 127.0.0.1) as current page,
            // return path-relative URL so requests use the active port/proxy (preserving same-origin cookies)
            if (parsed.hostname === window.location.hostname) {
                return parsed.pathname + parsed.search;
            }
        } catch (e) {
            // fallback to original URL
        }
        return url;
    }

    function refreshHistory() {
        if (!cfg.historyUrl) {
            return Promise.resolve();
        }
        return fetch(resolveHttpUrl(cfg.historyUrl), {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'Accept': 'application/json', 'X-HRIS-Background': '1' }
        }).then(function (res) {
            if (res.status === 401) {
                stop('expired');
                return null;
            }
            return res.ok ? res.json() : null;
        }).then(function (body) {
            if (!body || !body.data) {
                return;
            }
            historyLoadedAt = Date.now();
            var rows = Array.isArray(body.data.notifications) ? body.data.notifications : [];
            rows.forEach(function (row) { remember('notification:' + row.id); });
            renderList(rows.map(function (row) {
                return {
                    title: row.title,
                    message: row.message,
                    level: row.type,
                    unread: String(row.is_read) === '0',
                    time: row.created_at ? String(row.created_at).slice(0, 16) : '',
                    href: cfg.inboxUrl
                };
            }));
            setCount(body.data.unread_count);
        }).catch(function () {
            if (el.list && el.list.getAttribute('aria-busy') === 'true' && window.Skeleton) {
                Skeleton.hide(el.list);
            }
        });
    }

    // ------------------------------------------------------------------
    // WebSocket lifecycle
    // ------------------------------------------------------------------
    function resolveWsUrl(url) {
        var u = url || cfg.wsUrl || '/ws';
        if (u.charAt(0) === '/') {
            u = (location.protocol === 'https:' ? 'wss://' : 'ws://') + location.host + u;
        }
        if (location.protocol === 'https:' && u.indexOf('ws://') === 0) {
            u = 'wss://' + u.slice(5); // never downgrade on HTTPS pages
        }
        return u;
    }

    function connect() {
        if (stopped || connecting || ws) {
            return;
        }
        connecting = true;
        clearTimeout(retryTimer);
        setState(attempt === 0 ? 'connecting' : 'reconnecting');

        fetch(resolveHttpUrl(cfg.ticketUrl), {
            method: 'POST',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-Token': cfg.csrf,
                'X-HRIS-Background': '1'
            },
            body: '{}'
        }).then(function (res) {
            if (res.status === 401) { stop('expired'); return null; }
            if (res.status === 403) { stop('unavailable'); return null; }
            if (res.status === 503) { stop('disabled'); return null; }
            if (!res.ok) { scheduleRetry(res.status === 429); return null; }
            return res.json();
        }).then(function (body) {
            connecting = false;
            if (!body) {
                return;
            }
            var ticket = body && body.data ? body.data.ticket : null;
            if (typeof ticket !== 'string') {
                scheduleRetry(false);
                return;
            }
            openSocket(resolveWsUrl(body.data.url), ticket);
        }).catch(function () {
            connecting = false;
            scheduleRetry(false);
        });
    }

    function openSocket(url, ticket) {
        var socket;
        try {
            socket = new WebSocket(url);
        } catch (e) {
            scheduleRetry(true);
            return;
        }
        ws = socket;

        socket.onopen = function () {
            socket.send(JSON.stringify({ type: 'auth', ticket: ticket }));
            ticket = null; // single use; drop the reference immediately
        };
        socket.onmessage = function (ev) { handleMessage(ev.data); };
        socket.onerror = function () { /* onclose handles recovery */ };
        socket.onclose = function (ev) {
            if (ws === socket) {
                ws = null;
            }
            handleClose(ev.code);
        };
    }

    function handleMessage(raw) {
        var msg;
        try {
            msg = JSON.parse(raw);
        } catch (e) {
            return;
        }
        if (!msg || typeof msg.type !== 'string') {
            return;
        }

        switch (msg.type) {
            case 'auth.ok':
                attempt = 0;
                authFailures = 0;
                setState('live');
                refreshHistory();
                break;
            case 'notification.unread_count':
                if (msg.data) {
                    setCount(msg.data.count);
                }
                break;
            case 'event':
                handleEvent(msg);
                break;
            default:
                break; // pong, subscribed, error, session.expired: no UI change needed
        }
    }

    var autoReloadTimer = null;

    function handleEvent(msg) {
        var data = msg.data || {};
        var id = msg.id || ('notification:' + data.notification_id);
        if (!remember(id)) {
            return; // duplicate (reconnect replay or multiple tabs)
        }
        var item = {
            title: String(data.title || 'Notification'),
            message: String(data.message || ''),
            level: /^(info|success|warning|error)$/.test(data.level) ? data.level : 'info',
            unread: true,
            time: 'Just now',
            href: safeLink(data.link)
        };
        prependItem(item);

        var onWorkflowPage = isDashboardOrWorkflowPage();
        var userBusy = isUserActivelyInputting();
        var shouldAutoReload = onWorkflowPage && !userBusy;

        toast(item, shouldAutoReload);

        // Notify any page scripts via standard DOM custom event
        try {
            window.dispatchEvent(new CustomEvent('hris:notification', {
                detail: { msg: msg, item: item, data: data, autoReloading: shouldAutoReload }
            }));
        } catch (e) {}

        // Automatically update the dashboard / workflow page
        if (shouldAutoReload) {
            clearTimeout(autoReloadTimer);
            autoReloadTimer = setTimeout(function () {
                window.location.reload();
            }, 1500);
        }
    }

    function handleClose(code) {
        if (stopped) {
            return;
        }
        switch (code) {
            case 4001: // session deadline reached: a fresh ticket proves the session is still alive
            case 4002: // role changed: re-authenticate
                attempt = 0;
                retryTimer = setTimeout(connect, 250);
                setState('reconnecting');
                break;
            case 4003: // logged out or account deactivated
                stop('signed_out');
                break;
            case 4401:
                authFailures++;
                if (authFailures >= 3) {
                    stop('unavailable');
                } else {
                    scheduleRetry(false);
                }
                break;
            case 4429:
            case 1008:
            case 1009:
                scheduleRetry(true);
                break;
            default:
                scheduleRetry(false);
        }
    }

    function scheduleRetry(slow) {
        if (stopped) {
            return;
        }
        clearTimeout(retryTimer);
        if (navigator.onLine === false) {
            setState('offline');
            return; // the 'online' event resumes
        }
        attempt++;
        var base = Math.min(30000, 1000 * Math.pow(2, Math.min(attempt, 5)));
        var delay = base * (slow ? 2 : 1) + Math.floor(Math.random() * 1000);
        setState('reconnecting');
        retryTimer = setTimeout(connect, delay);
    }

    function stop(state) {
        stopped = true;
        connecting = false;
        clearTimeout(retryTimer);
        if (ws) {
            try { ws.close(1000); } catch (e) { /* ignore */ }
            ws = null;
        }
        setState(state);
    }

    window.addEventListener('online', function () {
        if (!stopped && !ws && !connecting) {
            attempt = 0;
            connect();
        }
    });
    window.addEventListener('offline', function () {
        if (!stopped) {
            setState('offline');
        }
    });
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible' && !stopped && !ws && !connecting) {
            clearTimeout(retryTimer);
            connect();
        }
    });
    window.addEventListener('pagehide', function () {
        stopped = true;
        clearTimeout(retryTimer);
        if (ws) {
            try { ws.close(1000); } catch (e) { /* ignore */ }
        }
    });
    window.addEventListener('pageshow', function (e) {
        // Restored from the back/forward cache: resume with a fresh ticket
        if (e.persisted && cfg.enabled && root.getAttribute('data-state') !== 'signed_out' && root.getAttribute('data-state') !== 'expired') {
            stopped = false;
            attempt = 0;
            connect();
        }
    });

    // ------------------------------------------------------------------
    // Boot
    // ------------------------------------------------------------------
    setCount(unread);
    if (cfg.enabled && cfg.ticketUrl) {
        connect();
    } else {
        setState('disabled');
        refreshHistory();
    }
})();
