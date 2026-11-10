(function () {
    const staffHeaderContext = JSON.parse(document.getElementById('appStaffHeaderConfig').textContent);
// ── Web Push — registrace Service Workeru a subscribe ───────────────────────
(function() {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;

    const PUSH_PUBLIC_KEY = staffHeaderContext.pushPublicKey;
    if (!PUSH_PUBLIC_KEY) return; // VAPID klíče ještě nejsou nastaveny

    function urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64  = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        const raw     = window.atob(base64);
        return new Uint8Array([...raw].map(c => c.charCodeAt(0)));
    }

    navigator.serviceWorker.register(staffHeaderContext.serviceWorkerUrl, { scope: staffHeaderContext.appBaseUrl })
        .then(function(reg) {
            // Zkontrolovat stav subscripce
            return reg.pushManager.getSubscription().then(function(sub) {
                const btn = document.getElementById('pushToggleBtn');
                if (!btn) return;
                if (sub) {
                    btn.innerHTML = '<i class="bi bi-bell-fill"></i>';
                    btn.title = 'Push notifikace aktivní — klik pro vypnutí';
                    btn.dataset.subscribed = '1';
                } else {
                    btn.innerHTML = '<i class="bi bi-bell-slash"></i>';
                    btn.title = 'Zapnout push notifikace';
                    btn.dataset.subscribed = '0';
                }
                btn.style.display = 'inline-block';
            });
        })
        .catch(err => console.warn('SW registration failed:', err));

    // Toggle subscribe/unsubscribe
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('#pushToggleBtn');
        if (!btn) return;

        navigator.serviceWorker.ready.then(function(reg) {
            if (btn.dataset.subscribed === '1') {
                // Odhlásit
                reg.pushManager.getSubscription().then(sub => {
                    if (!sub) return;
                    sub.unsubscribe().then(() => {
                        fetch(staffHeaderContext.pushEndpoint, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ action: 'unsubscribe', csrf_token: staffHeaderContext.csrfToken, subscription: { endpoint: sub.endpoint } })
                        });
                        btn.innerHTML = '<i class="bi bi-bell-slash"></i>';
                        btn.dataset.subscribed = '0';
                        btn.title = 'Zapnout push notifikace';
                        if (window.showToast) showToast('Push notifikace vypnuty', 'info');
                    });
                });
            } else {
                // Přihlásit
                Notification.requestPermission().then(function(perm) {
                    if (perm !== 'granted') {
                        if (window.showToast) showToast('Notifikace nebyly povoleny prohlížečem.', 'warning');
                        return;
                    }
                    reg.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: urlBase64ToUint8Array(PUSH_PUBLIC_KEY)
                    }).then(sub => {
                        fetch(staffHeaderContext.pushEndpoint, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ action: 'subscribe', csrf_token: staffHeaderContext.csrfToken, subscription: sub.toJSON() })
                        });
                        btn.innerHTML = '<i class="bi bi-bell-fill"></i>';
                        btn.dataset.subscribed = '1';
                        btn.title = 'Push notifikace aktivní — klik pro vypnutí';
                        if (window.showToast) showToast('Push notifikace aktivovány! 🔔', 'success');
                    }).catch(err => {
                        console.error('Push subscribe failed:', err);
                        if (window.showToast) showToast('Aktivace notifikací selhala.', 'danger');
                    });
                });
            }
        });
    });
})();

// ── Globální vyhledávání ─────────────────────────────────────────────────────
(function () {
    const input    = document.getElementById('globalSearchInput');
    const dropdown = document.getElementById('globalSearchDropdown');
    if (!input || !dropdown) return;

    const SEARCH_URL = staffHeaderContext.searchUrl;

    let controller = null;
    let timer      = null;
    let focusIdx   = -1;

    function getItems() {
        return Array.from(dropdown.querySelectorAll('.gs-item'));
    }

    function setFocus(idx) {
        const items = getItems();
        items.forEach((el, i) => el.classList.toggle('gs-focus', i === idx));
        focusIdx = idx;
    }

    function showDropdown(html) {
        dropdown.innerHTML = html;
        dropdown.classList.add('show');
        input.setAttribute('aria-expanded', 'true');
        focusIdx = -1;
    }

    function hideDropdown() {
        dropdown.classList.remove('show');
        dropdown.innerHTML = '';
        input.setAttribute('aria-expanded', 'false');
        focusIdx = -1;
    }

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function buildHtml(data) {
        const groups = [
            { key: 'sportovci', label: 'Sportovci' },
            { key: 'treninky',  label: 'Tréninky' },
            { key: 'zavody',    label: 'Závody' },
        ];
        let html = '';
        let total = 0;
        groups.forEach(g => {
            const rows = data[g.key] || [];
            if (!rows.length) return;
            total += rows.length;
            html += `<div class="gs-group-header">${esc(g.label)}</div>`;
            rows.forEach(r => {
                html += `<a class="gs-item" href="${esc(r.url)}" role="option">
                    <i class="bi ${esc(r.icon)} text-primary"></i>
                    <span>${esc(r.label)}</span>
                </a>`;
            });
        });
        if (total === 0) {
            html = '<div class="gs-empty"><i class="bi bi-search me-1"></i>Nic nenalezeno.</div>';
        }
        return html;
    }

    function doSearch() {
        const q = input.value.trim();
        if (q.length < 2) { hideDropdown(); return; }

        if (controller) controller.abort();
        controller = new AbortController();

        showDropdown('<div class="gs-loading"><span class="spinner-border spinner-border-sm me-2"></span>Hledám…</div>');

        const url = new URL(SEARCH_URL, window.location.origin);
        url.searchParams.set('q', q);

        fetch(url.toString(), { signal: controller.signal })
            .then(r => r.json())
            .then(data => {
                showDropdown(buildHtml(data));
            })
            .catch(err => {
                if (err.name !== 'AbortError') {
                    showDropdown('<div class="gs-empty text-danger">Chyba při hledání.</div>');
                }
            });
    }

    input.addEventListener('input', () => {
        clearTimeout(timer);
        const q = input.value.trim();
        if (q.length < 2) { hideDropdown(); return; }
        timer = setTimeout(doSearch, 300);
    });

    input.addEventListener('keydown', e => {
        const items = getItems();
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setFocus(Math.min(focusIdx + 1, items.length - 1));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setFocus(Math.max(focusIdx - 1, 0));
        } else if (e.key === 'Enter') {
            if (focusIdx >= 0 && items[focusIdx]) {
                e.preventDefault();
                items[focusIdx].click();
            }
        } else if (e.key === 'Escape') {
            hideDropdown();
            input.blur();
        }
    });

    // Klik mimo → zavřít
    document.addEventListener('click', e => {
        if (!input.contains(e.target) && !dropdown.contains(e.target)) {
            hideDropdown();
        }
    });

    // Focus → znovu otevřít pokud je co
    input.addEventListener('focus', () => {
        if (input.value.trim().length >= 2 && !dropdown.classList.contains('show')) {
            doSearch();
        }
    });
})();

// ── Potvrzovací modal — window.appConfirm(message) → Promise<bool> ──────────
window.appConfirm = function (message) {
    return new Promise(function (resolve) {
        var modalEl = document.getElementById('appConfirmModal');
        if (!modalEl || typeof bootstrap === 'undefined') {
            resolve(window.confirm(message));
            return;
        }
        document.getElementById('appConfirmModalText').textContent = message;
        var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        var okBtn = document.getElementById('appConfirmModalOk');
        var done = false;
        function onOk() { done = true; modal.hide(); }
        function onHide() {
            okBtn.removeEventListener('click', onOk);
            modalEl.removeEventListener('hidden.bs.modal', onHide);
            resolve(done);
        }
        okBtn.addEventListener('click', onOk);
        modalEl.addEventListener('hidden.bs.modal', onHide);
        modal.show();
    });
};

// ── data-confirm — formuláře a odkazy potvrzované modalem ───────────────────
(function () {
    // capture fáze: běží dřív než zámek proti dvojímu odeslání
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (!form.dataset.confirm || form.dataset.confirmed === '1') return;
        e.preventDefault();
        e.stopImmediatePropagation();
        var submitter = e.submitter || null;
        window.appConfirm(form.dataset.confirm).then(function (ok) {
            if (!ok) return;
            form.dataset.confirmed = '1';
            if (form.requestSubmit) form.requestSubmit(submitter || undefined);
            else form.submit();
            form.dataset.confirmed = '';
        });
    }, true);

    document.addEventListener('click', function (e) {
        if (!e.target.closest) return;
        // odkazy
        var a = e.target.closest('a[data-confirm]');
        if (a) {
            e.preventDefault();
            window.appConfirm(a.dataset.confirm).then(function (ok) {
                if (ok) window.location.href = a.href;
            });
            return;
        }
        // submit tlačítka s vlastním potvrzením
        var btn = e.target.closest('button[data-confirm], input[type="submit"][data-confirm]');
        if (btn && btn.form) {
            e.preventDefault();
            window.appConfirm(btn.dataset.confirm).then(function (ok) {
                if (!ok) return;
                btn.form.dataset.confirmed = '1';
                if (btn.form.requestSubmit) btn.form.requestSubmit(btn);
                else btn.form.submit();
                btn.form.dataset.confirmed = '';
            });
        }
    }, true);
})();

// ── Zámek proti dvojímu odeslání formulářů ──────────────────────────────────
// Vyjmutí: <form data-no-lock> (např. formuláře, které neopouštějí stránku).
// Po 10 s se tlačítka automaticky odemknou (exporty stahující soubor).
(function () {
    function unlock(form) {
        form.querySelectorAll('button[disabled][data-orig-html]').forEach(function (btn) {
            btn.innerHTML = btn.dataset.origHtml;
            btn.disabled = false;
            delete btn.dataset.origHtml;
        });
        form.querySelectorAll('input[type="submit"][disabled][data-locked]').forEach(function (btn) {
            btn.disabled = false;
            delete btn.dataset.locked;
        });
    }

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (form.dataset.noLock !== undefined) return;
        if (form.target && form.target === '_blank') return;

        var buttons = form.querySelectorAll('button[type="submit"], input[type="submit"], button:not([type])');
        // odloženě — hodnota kliknutého tlačítka se musí stihnout serializovat.
        // Kontrola defaultPrevented AŽ tady: AJAX handlery na stránce volají
        // preventDefault v témže dispatchi, ale po tomto (dříve registrovaném) listeneru.
        // Kdyby byla kontrola nahoře, byl by defaultPrevented ještě false a lock by
        // zamkl i AJAX formuláře, které nenavigují → zůstaly by zamčené 10 s.
        setTimeout(function () {
            if (e.defaultPrevented) return;
            buttons.forEach(function (btn) {
                if (btn.disabled) return;
                btn.disabled = true;
                if (btn.tagName === 'BUTTON') {
                    btn.dataset.origHtml = btn.innerHTML;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>' + btn.innerHTML;
                } else {
                    btn.dataset.locked = '1';
                }
            });
        }, 0);
        setTimeout(function () { unlock(form); }, 10000);
    });

    // Návrat tlačítkem Zpět z bfcache → odemknout vše
    window.addEventListener('pageshow', function (e) {
        if (!e.persisted) return;
        document.querySelectorAll('form').forEach(unlock);
    });
})();

// ── PWA — manifest + service worker (instalovatelná aplikace) ───────────────
(function () {
    var base = staffHeaderContext.appBaseUrl;
    var link = document.createElement('link');
    link.rel = 'manifest';
    link.href = base + 'manifest.json';
    document.head.appendChild(link);
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register(base + 'sw.js', { scope: base }).catch(function () {});
    }
})();

})();
