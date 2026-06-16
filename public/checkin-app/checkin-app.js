/* global evtmgrCheckin, Html5Qrcode */

(function () {
    'use strict';

    const cfg = window.evtmgrCheckin || {};

    // ── State ──────────────────────────────────────────────────────────────
    let qrScanner  = null;
    let scanActive = false;

    // ── DOM bootstrap ──────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        const root = document.getElementById('checkin-app');
        if (!root) return;

        root.innerHTML = buildShell();

        registerServiceWorker();

        // Auto-lookup when URL contains ?checking=
        const urlParam = new URLSearchParams(window.location.search).get('checking');
        if (urlParam) {
            showScreen('loading');
            lookupPerson(urlParam.trim());
        } else {
            showScreen('idle');
        }

        // Button events (delegated)
        root.addEventListener('click', handleClick);
    });

    // ── Shell HTML ─────────────────────────────────────────────────────────
    function buildShell() {
        return `
<div id="ca-screen-idle" class="ca-screen ca-idle">
    <h1>Event Check-in</h1>
    <button class="ca-btn ca-btn-primary" id="ca-btn-scan">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"
             viewBox="0 0 24 24" aria-hidden="true">
            <path d="M3 7V5a2 2 0 012-2h2M17 3h2a2 2 0 012 2v2M21 17v2a2 2 0 01-2 2h-2M7 21H5a2 2 0 01-2-2v-2"/>
            <rect x="7" y="7" width="4" height="4" rx="1"/>
            <rect x="13" y="7" width="4" height="4" rx="1"/>
            <rect x="7" y="13" width="4" height="4" rx="1"/>
            <rect x="13" y="13" width="4" height="4" rx="1"/>
        </svg>
        QR-Code scannen
    </button>
</div>

<div id="ca-screen-scanner" class="ca-screen">
    <div id="ca-qr-reader"></div>
    <p class="ca-scanner-hint">Halte die Kamera über den QR-Code auf dem Ticket.</p>
    <div style="text-align:center;margin-top:.75rem;">
        <button class="ca-btn ca-btn-outline" id="ca-btn-cancel-scan">Abbrechen</button>
    </div>
</div>

<div id="ca-screen-loading" class="ca-screen ca-loading">
    <div class="ca-spinner"></div>
    <p>Wird geladen…</p>
</div>

<div id="ca-screen-result" class="ca-screen">
    <div id="ca-person-card"></div>
    <div style="margin-top:1rem;text-align:center;">
        <button class="ca-btn ca-btn-outline" id="ca-btn-scan-next">Nächste Person</button>
    </div>
</div>

<div id="ca-screen-error" class="ca-screen">
    <div id="ca-error-box" class="ca-error-box"></div>
    <div style="text-align:center;margin-top:1rem;">
        <button class="ca-btn ca-btn-outline" id="ca-btn-retry">Erneut versuchen</button>
    </div>
</div>`;
    }

    // ── Screen switching ───────────────────────────────────────────────────
    function showScreen(name) {
        document.querySelectorAll('#checkin-app .ca-screen').forEach(function (el) {
            el.classList.remove('ca-active');
        });
        const target = document.getElementById('ca-screen-' + name);
        if (target) target.classList.add('ca-active');
    }

    // ── Click handler ──────────────────────────────────────────────────────
    function handleClick(e) {
        const btn = e.target.closest('button, .ca-btn');
        if (!btn) return;

        const id = btn.id;

        if (id === 'ca-btn-scan') {
            startScanner();
        } else if (id === 'ca-btn-cancel-scan') {
            stopScanner();
            showScreen('idle');
        } else if (id === 'ca-btn-scan-next') {
            showScreen('idle');
        } else if (id === 'ca-btn-retry') {
            showScreen('idle');
        } else if (id === 'ca-btn-checkin') {
            const cookie = btn.dataset.cookie || '';
            if (cookie) performCheckin(cookie);
        }
    }

    // ── QR Scanner ─────────────────────────────────────────────────────────
    function startScanner() {
        stopScanner();
        showScreen('scanner');

        qrScanner  = new Html5Qrcode('ca-qr-reader');
        scanActive = true;

        qrScanner.start(
            { facingMode: 'environment' },
            { fps: 10, qrbox: { width: 260, height: 260 } },
            onQrSuccess,
            function () { /* scan frame errors — ignore */ }
        ).catch(function (err) {
            scanActive = false;
            showError('Kamera konnte nicht geöffnet werden: ' + err);
        });
    }

    function stopScanner() {
        if (qrScanner && scanActive) {
            qrScanner.stop().catch(function () {});
            scanActive = false;
        }
        qrScanner = null;
    }

    function onQrSuccess(decodedText) {
        stopScanner();
        showScreen('loading');

        let cookie = '';
        try {
            const url    = new URL(decodedText);
            const param  = url.searchParams.get('checking');
            if (param) {
                cookie = param.trim();
            }
        } catch (e) {
            // decodedText may already be just the cookie value
            cookie = decodedText.trim();
        }

        if (!cookie) {
            showError('QR-Code enthält keinen gültigen Check-in Link.');
            return;
        }

        lookupPerson(cookie);
    }

    // ── API: Lookup ────────────────────────────────────────────────────────
    function lookupPerson(cookie) {
        const url = cfg.restUrl + '?cookie=' + encodeURIComponent(cookie);

        fetch(url, {
            method:  'GET',
            headers: { 'X-WP-Nonce': cfg.nonce || '' },
        })
        .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
        .then(function (r) {
            if (!r.ok) {
                showError(r.data.message || 'Person nicht gefunden.');
                return;
            }
            showResult(r.data, cookie);
        })
        .catch(function () {
            showError('Netzwerkfehler. Bitte Verbindung prüfen.');
        });
    }

    // ── API: Check-in ──────────────────────────────────────────────────────
    function performCheckin(cookie) {
        const card   = document.getElementById('ca-person-card');
        const btn    = document.getElementById('ca-btn-checkin');
        if (btn) { btn.disabled = true; btn.textContent = 'Wird gespeichert…'; }

        fetch(cfg.restUrl, {
            method:  'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce':   cfg.nonce || '',
            },
            body: JSON.stringify({ cookie: cookie }),
        })
        .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
        .then(function (r) {
            if (!r.ok) {
                if (btn) { btn.disabled = false; btn.textContent = 'Einchecken'; }
                const msg = document.getElementById('ca-inline-error');
                if (msg) msg.textContent = r.data.message || 'Fehler beim Check-in.';
                return;
            }
            // Reload person data to show updated status
            showScreen('loading');
            lookupPerson(cookie);
        })
        .catch(function () {
            if (btn) { btn.disabled = false; btn.textContent = 'Einchecken'; }
        });
    }

    // ── Result card ────────────────────────────────────────────────────────
    function showResult(person, cookie) {
        const cardEl = document.getElementById('ca-person-card');
        if (!cardEl) return;

        const checkedIn   = person.checked_in === true;
        const fullName    = [person.salutation, person.first_name, person.last_name].filter(Boolean).join(' ');
        const dateDisplay = person.date_check_in
            ? formatDateTime(person.date_check_in)
            : '';

        const headerCls   = checkedIn ? 'ca-ok' : 'ca-warn';
        const dotCls      = checkedIn ? 'ca-green' : 'ca-yellow';
        const statusLabel = checkedIn ? 'Bereits eingecheckt' : 'Noch nicht eingecheckt';

        const checkinBtn = checkedIn ? '' : `
<button class="ca-btn ca-btn-success" id="ca-btn-checkin" data-cookie="${escHtml(cookie)}">
    Einchecken
</button>`;

        const inlineError = '<p id="ca-inline-error" style="color:#842029;font-size:.85rem;margin:.5rem 0 0;"></p>';

        cardEl.innerHTML = `
<div class="ca-card">
    <div class="ca-card-header ${headerCls}">
        <span class="ca-status-dot ${dotCls}"></span>
        ${escHtml(statusLabel)}
    </div>
    <div class="ca-card-body">
        <div class="ca-person-name">${escHtml(fullName)}</div>
        <div class="ca-person-meta">${escHtml(person.email)}</div>
        <div class="ca-person-meta">Event: ${escHtml(person.event_uid)}</div>
        ${checkedIn && dateDisplay ? `<div class="ca-check-date">Eingecheckt: ${escHtml(dateDisplay)}</div>` : ''}
        ${inlineError}
    </div>
    <div class="ca-card-actions">
        ${checkinBtn}
    </div>
</div>`;

        showScreen('result');
    }

    // ── Error screen ────────────────────────────────────────────────────────
    function showError(message) {
        const box = document.getElementById('ca-error-box');
        if (box) box.textContent = message;
        showScreen('error');
    }

    // ── Helpers ─────────────────────────────────────────────────────────────
    function escHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function formatDateTime(dtStr) {
        if (!dtStr) return '';
        try {
            const d = new Date(dtStr.replace(' ', 'T'));
            return d.toLocaleDateString('de-CH') + ' ' + d.toLocaleTimeString('de-CH', { hour: '2-digit', minute: '2-digit' });
        } catch (e) {
            return dtStr;
        }
    }

    // ── Service Worker registration ─────────────────────────────────────────
    function registerServiceWorker() {
        if (!('serviceWorker' in navigator) || !cfg.swUrl) return;
        navigator.serviceWorker.register(cfg.swUrl, { scope: '/' })
            .catch(function () {
                // SW scope limitation — app works without offline support
            });
    }
})();
