(() => {
    'use strict';
    const form = document.getElementById('treninkForm');
    if (!form) return;
    const timePattern = /^(?:[0-9]{1,5}:[0-5][0-9]|[0-9]{1,3}:[0-5][0-9]:[0-5][0-9])(?:\.[0-9]{1,3})?$/;
    function validateTime(field) {
        if (!field.matches('.js-cas')) return;
        const value = field.value.trim();
        const valid = !value || (timePattern.test(value) && /[1-9]/.test(value));
        field.setCustomValidity(valid ? '' : 'Zadejte čas např. 00:13.456 (13,456 sekundy) nebo 02:13.45. Čas musí být větší než nula.');
        field.classList.toggle('is-invalid', !valid);
    }
    form.addEventListener('input', e => validateTime(e.target));
    form.addEventListener('change', e => validateTime(e.target));
    form.addEventListener('invalid', e => {
        validateTime(e.target);
        const collapse = e.target.closest('.collapse');
        if (collapse) collapse.classList.add('show');
    }, true);
    form.addEventListener('submit', e => {
        form.querySelectorAll('.js-cas').forEach(validateTime);
        if (!form.checkValidity()) {
            e.preventDefault();
            form.reportValidity();
        }
    }, true);
    const configElement = document.getElementById('training-draft-config');
    if (!configElement) return; // Edit form shares the time guard only.
    const config = JSON.parse(configElement.textContent);
    const prefix = 'kis-training-draft:v1:' + config.actor + ':';
    const key = prefix + config.key;
    const status = document.getElementById('training-draft-status');
    const recovery = document.getElementById('training-draft-recovery');
    const snapshotField = document.getElementById('training_draft');
    const excluded = new Set(['csrf_token', 'submission_key', 'plan_id', 'duplikat', 'training_draft']);
    let dirty = false, pending = false, restoring = false, uncertain = false;
    function capture() {
        const fields = {};
        form.querySelectorAll('input[name],select[name],textarea[name]').forEach(field => {
            if (excluded.has(field.name) || ['file','submit','button'].includes(field.type)) return;
            fields[field.name] ??= [];
            if (['checkbox','radio'].includes(field.type) && !field.checked) return;
            if (field.multiple) fields[field.name].push(...Array.from(field.selectedOptions, o => o.value));
            else fields[field.name].push(field.value);
        });
        const rows = Array.from(form.querySelectorAll('.mereni-row'), row =>
            Array.from(row.querySelectorAll('input,select,textarea'), field => ({value: field.value})));
        return {version: 1, key: config.key, context: config.context, fields, rows,
            participants: window.__ucastniciSelected || [], updated: Date.now(), pending};
    }
    function save() {
        if (!dirty || restoring || uncertain) return false;
        const serialized = JSON.stringify(capture());
        snapshotField.value = serialized;
        try {
            localStorage.setItem(key, serialized);
            status.textContent = 'Koncept uložen v tomto prohlížeči. Přílohy po obnovení vyberte znovu.';
            return true;
        } catch (_) {
            status.textContent = 'Koncept se nepodařilo uložit v prohlížeči. Před odchodem si zkopírujte text.';
        }
    }
    function restore(draft) {
        restoring = true;
        for (const [name, values] of Object.entries(draft.fields || {})) {
            if (excluded.has(name) || !Array.isArray(values)) continue;
            const fields = Array.from(form.elements).filter(f => f.name === name && f.type !== 'file');
            fields.forEach((field, i) => {
                if (['checkbox','radio'].includes(field.type)) field.checked = values.includes(field.value);
                else if (field.multiple) Array.from(field.options).forEach(o => { o.selected = values.includes(o.value); });
                else field.value = values[i] ?? values[0] ?? '';
            });
        }
        form.dispatchEvent(new CustomEvent('training-draft-restore', {detail: draft}));
        form.querySelectorAll('.js-cas').forEach(validateTime);
        form.querySelector('[name="datum"]')?.dispatchEvent(new Event('change', {bubbles: true}));
        form.querySelectorAll('.collapse').forEach(el => el.classList.add('show'));
        restoring = false;
        dirty = true;
        if (save() && draft.key !== config.key) {
            try { localStorage.removeItem(prefix + draft.key); } catch (_) { /* Keep either copy. */ }
        }
    }
    function offer(draft) {
        const box = document.createElement('div');
        box.className = 'alert alert-warning';
        const label = document.createElement('p');
        label.textContent = 'Rozepsaný trénink z ' + new Date(draft.updated).toLocaleString('cs-CZ') +
            (draft.pending ? '. Výsledek odeslání není potvrzen. Nejdříve ověřte přehled tréninků; text lze zkopírovat níže.' : '. Můžete obnovit vyplněné údaje.');
        box.append(label);
        const text = document.createElement('textarea');
        text.className = 'form-control mb-2';
        text.readOnly = true;
        text.setAttribute('aria-label', 'Text uloženého konceptu');
        text.value = ['napln','poznamka'].map(n => (draft.fields?.[n] || []).join('\n')).join('\n\n');
        box.append(text);
        if (!draft.pending) {
            const button = document.createElement('button');
            button.type = 'button'; button.className = 'btn btn-outline-primary'; button.textContent = 'Obnovit koncept';
            button.addEventListener('click', () => { restore(draft); box.remove(); });
            box.append(button);
        }
        const discard = document.createElement('button');
        discard.type = 'button'; discard.className = 'btn btn-outline-secondary ms-2'; discard.textContent = 'Zahodit koncept';
        discard.addEventListener('click', () => {
            if (!window.confirm('Opravdu zahodit tento rozepsaný koncept?')) return;
            try { localStorage.removeItem(prefix + draft.key); box.remove(); }
            catch (_) { status.textContent = 'Koncept se nepodařilo odstranit.'; }
        });
        box.append(discard);
        recovery.append(box);
    }
    let own = config.retry;
    try {
        (config.completed || []).forEach(id => localStorage.removeItem(prefix + id));
        const local = JSON.parse(localStorage.getItem(key) || 'null');
        own = config.retry && (!local || config.retry.updated >= local.updated) ? config.retry : local;
        for (let i = 0; i < localStorage.length; i++) {
            const candidate = localStorage.key(i);
            if (!candidate.startsWith(prefix) || candidate === key) continue;
            try {
                const draft = JSON.parse(localStorage.getItem(candidate));
                if (draft.version === 1 && draft.context === config.context) offer(draft);
            } catch (_) { /* A broken draft must not break the form. */ }
        }
    } catch (_) { status.textContent = 'Úložiště konceptů není dostupné. Před odchodem si zkopírujte text.'; }
    if (own && own.version === 1 && own.context === config.context) {
        if (!own.pending || config.retry) restore(own);
        else { uncertain = true; offer(own); }
    }
    form.addEventListener('submit', event => {
        if (uncertain) {
            event.preventDefault();
            status.textContent = 'Nejdříve ověřte výsledek předchozího odeslání v přehledu tréninků. Koncept zůstává zachovaný.';
        }
    }, true);
    window.addEventListener('pageshow', event => { if (event.persisted) location.reload(); });
    ['input','change'].forEach(event => form.addEventListener(event, () => { dirty = true; save(); }));
    form.addEventListener('click', () => queueMicrotask(save));
    form.addEventListener('submit', event => {
        // This handler runs after the form's domain validation and never deletes a draft on submit.
        if (event.defaultPrevented) { save(); return; }
        dirty = true; pending = true; save();
    });
    window.addEventListener('pagehide', save);
    document.addEventListener('visibilitychange', () => { if (document.hidden) save(); });
})();
