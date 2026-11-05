/* One manually recoverable confirmation per tab. No offline queue and no automatic POST replay. */
(() => {
    'use strict';
    const mount = document.getElementById('bar-request-recovery');
    if (!mount || !/^\d+:\d+$/.test(mount.dataset.user || '')) return;
    const namespace = 'kis.bar.pending:v1:';
    const key = namespace + mount.dataset.user;
    const prefix = mount.dataset.prefix;
    const endpoints = new Set(['checkout.php', 'consumption.php', 'cashbox.php', 'invoices.php', 'offers.php',
        'accounting.php', 'settings.php', 'operations.php', 'stock.php']);
    const results = new Set([...endpoints, 'receipt.php']);
    let blocked = false;
    let checking = false;

    function safeUrl(value, result = false) {
        try {
            const url = new URL(value, window.location.href);
            const allowed = result ? results : endpoints;
            return url.origin === window.location.origin && url.pathname.startsWith(prefix)
                && allowed.has(url.pathname.slice(prefix.length)) ? url : null;
        } catch (_) { return null; }
    }

    function load() {
        const raw = window.sessionStorage.getItem(key);
        if (!raw) return null;
        if (raw.length > 1000000) throw new Error('invalid confirmation');
        const record = JSON.parse(raw);
        if (!record || record.user !== mount.dataset.user || !safeUrl(record.url)
            || !/^[a-f0-9]{64}$/.test(record.token) || !Array.isArray(record.fields) || record.fields.length > 5000
            || !record.fields.every(pair => Array.isArray(pair) && pair.length === 2 && typeof pair[0] === 'string'
                && /^[a-zA-Z0-9_:\[\]-]+$/.test(pair[0]) && typeof pair[1] === 'string' && pair[1].length <= 500000)) {
            throw new Error('invalid confirmation');
        }
        const tokens = record.fields.filter(pair => pair[0] === 'token');
        if (tokens.length !== 1 || tokens[0][1] !== record.token) throw new Error('invalid token');
        return record;
    }

    function node(tag, text, className) {
        const element = document.createElement(tag);
        if (text) element.textContent = text;
        if (className) element.className = className;
        return element;
    }

    function button(text, action) {
        const element = node('button', text, 'btn btn-outline-primary me-2 my-2');
        element.type = 'button';
        element.addEventListener('click', action);
        return element;
    }

    function notice(text, type = 'warning') {
        mount.replaceChildren();
        const panel = node('section', '', 'alert alert-' + type + ' my-3');
        panel.appendChild(node('p', text));
        mount.appendChild(panel);
        return panel;
    }

    function clear() {
        window.sessionStorage.removeItem(key);
        blocked = false;
        mount.replaceChildren();
    }

    function reviewedDismiss(panel) {
        const label = node('label', '', 'd-block mt-3');
        const check = node('input');
        check.type = 'checkbox';
        label.appendChild(check);
        label.appendChild(node('span', ' Ověřil(a) jsem poslední doklady a skutečný stav kasy.'));
        const dismiss = button('Zavřít ověřené hlášení', () => {
            if (check.checked) clear();
        });
        dismiss.disabled = true;
        check.addEventListener('change', () => { dismiss.disabled = !check.checked; });
        panel.appendChild(label);
        panel.appendChild(dismiss);
    }

    function unknown(record) {
        const panel = notice('Výsledek posledního potvrzení není ověřený. Po obnovení spojení můžete zopakovat původní potvrzení.');
        panel.appendChild(button('Ověřit výsledek znovu', recover));
        const form = node('form');
        form.method = 'post';
        form.action = record.url;
        form.enctype = 'multipart/form-data';
        form.dataset.barRetry = '1';
        for (const [name, value] of [...record.fields.filter(pair => pair[0] !== 'csrf_token'), ['csrf_token', mount.dataset.csrf]]) {
            const input = node('input');
            input.type = 'hidden'; input.name = name; input.value = value;
            form.appendChild(input);
        }
        const submit = node('button', 'Zopakovat původní potvrzení', 'btn btn-primary my-2');
        submit.type = 'submit';
        form.appendChild(submit);
        panel.appendChild(form);
        reviewedDismiss(panel);
    }

    async function recover() {
        if (checking) return;
        let record;
        try { record = load(); }
        catch (_) {
            blocked = true;
            reviewedDismiss(notice('Uložené potvrzení této karty nelze načíst. Zkontrolujte poslední doklady a skutečný stav kasy.'));
            return;
        }
        if (!record) return;
        blocked = true;
        checking = true;
        notice('Ověřuji výsledek posledního potvrzení…', 'info');
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), 8000);
        try {
            const response = await fetch(prefix + 'request-status.php',
                { method: 'GET', headers: {'X-Bar-Request-Token': record.token}, credentials: 'same-origin',
                    cache: 'no-store', redirect: 'error', signal: controller.signal });
            if (!response.ok) throw new Error('unavailable');
            const data = await response.json();
            if (data.state === 'completed') {
                const target = safeUrl(data.result_url, true);
                if (target && target.pathname + target.search === window.location.pathname + window.location.search) {
                    window.sessionStorage.removeItem(key);
                    blocked = false;
                    mount.replaceChildren();
                    return;
                }
                const panel = notice('Poslední potvrzení bylo uloženo. Před další operací zkontrolujte jeho výsledek.', 'success');
                if (target) {
                    const link = node('a', 'Otevřít uložený výsledek', 'btn btn-success me-2 my-2');
                    link.href = target.href;
                    panel.appendChild(link);
                }
                panel.appendChild(button('Pokračovat novou operací', clear));
            } else if (data.state === 'unknown' && mount.dataset.rejected === record.token) {
                clear();
                notice('Poslední potvrzení bylo odmítnuté a nebylo uloženo. Opravte údaje podle zprávy ve formuláři.', 'info');
            } else {
                unknown(record);
            }
        } catch (_) { unknown(record); }
        finally { clearTimeout(timer); checking = false; }
    }

    const canonical = fields => JSON.stringify(fields.filter(pair => pair[0] !== 'csrf_token'));
    document.addEventListener('submit', event => {
        const form = event.target;
        if (event.defaultPrevented || !(form instanceof HTMLFormElement) || (form.getAttribute('method') || 'get').toLowerCase() !== 'post') return;
        const action = new URL(form.getAttribute('action') || window.location.href, window.location.href);
        if (action.origin === window.location.origin && action.pathname === prefix.slice(0, -4) + 'logout.php') {
            try { window.sessionStorage.removeItem(key); } catch (_) { /* Logout must remain available. */ }
            return;
        }
        // Fields named method/action shadow native HTMLFormElement properties.
        const url = safeUrl(form.getAttribute('action') || window.location.href);
        if (!url) return;
        const data = new FormData(form);
        const token = data.get('token');
        if (typeof token !== 'string' || !/^[a-f0-9]{64}$/.test(token)) return;
        try {
            const previous = load();
            const fields = [];
            let hasFile = false;
            for (const [name, value] of data) {
                if (typeof value === 'string') fields.push([name, value]);
                else if (value.size > 0) hasFile = true;
            }
            const same = previous && previous.token === token && previous.url === url.href && canonical(previous.fields) === canonical(fields);
            if ((blocked && !(form.dataset.barRetry === '1' && same)) || (previous && !same)) {
                event.preventDefault();
                mount.tabIndex = -1; mount.focus();
                if (!blocked) recover();
                return;
            }
            // File bytes are never stored in browser storage; the PDF upload itself deduplicates by content.
            if (hasFile) return;
            const serialized = JSON.stringify({ user: mount.dataset.user, token, url: url.href, fields });
            if (serialized.length > 1000000) throw new Error('confirmation too large');
            window.sessionStorage.setItem(key, serialized);
        } catch (_) {
            event.preventDefault();
            blocked = true;
            notice('Prohlížeč nyní neumožňuje bezpečně uchovat potvrzení této karty. Zkuste stránku znovu otevřít.');
        }
    }, true);

    try {
        for (let index = window.sessionStorage.length - 1; index >= 0; index--) {
            const other = window.sessionStorage.key(index);
            if (typeof other === 'string' && other.startsWith(namespace) && other !== key) window.sessionStorage.removeItem(other);
        }
    } catch (_) { /* The submit handler refuses an unsafe confirmation if storage is unavailable. */ }
    window.addEventListener('pageshow', recover);
    recover();
})();
