(function () {
    'use strict';

    function resetLoadingState() {
        document.body?.classList.remove('app-loading');
        document.querySelectorAll('form[aria-busy="true"]').forEach(function (form) {
            form.removeAttribute('aria-busy');
            delete form.dataset.appSubmitting;
        });
    }

    function ensureLoadingBar() {
        if (!document.body || document.querySelector('.app-loading-bar')) return;
        var bar = document.createElement('div');
        bar.className = 'app-loading-bar';
        bar.setAttribute('aria-hidden', 'true');
        document.body.appendChild(bar);
    }

    function ensureAccessibleFieldNames() {
        var fieldLabels = {
            q: 'Hledat', status: 'Stav', code: 'Kód', event_type: 'Typ akce', name: 'Název',
            audience_label: 'Cílová skupina', description_plain: 'Popis', min_age: 'Věk od', max_age: 'Věk do',
            capacity: 'Kapacita', capacity_override: 'Kapacita termínu', pricing_policy: 'Způsob ceny', currency: 'Měna',
            registration_starts_at: 'Začátek registrace', registration_ends_at: 'Konec registrace',
            terms_version: 'Verze souhlasu', consent_text_plain: 'Text souhlasu',
            cancellation_policy_plain: 'Storno podmínky', cancellation_deadline_at: 'Termín bezplatného storna',
            starts_at: 'Začátek termínu', ends_at: 'Konec termínu', location: 'Místo', product_id: 'Produkt',
            team_id: 'Soupiska', team_ids: 'Soupisky', category_path: 'Kategorie', discount_type: 'Druh slevy',
            value: 'Hodnota', amount: 'Cena', sportovec_id: 'Sportovec', account_id: 'Veřejný účet',
            relation_role: 'Vztah', public_name: 'Veřejný název', public_summary: 'Veřejný popis',
            date: 'Datum', starts_at_time: 'Čas od', ends_at_time: 'Čas do', price_minor: 'Cena v haléřích',
            minimum_order: 'Minimální hodnota objednávky', maximum_discount: 'Maximální sleva',
            usage_limit: 'Celkový limit použití', valid_from: 'Platnost od', valid_until: 'Platnost do', note: 'Důvod nebo poznámka'
        };

        document.querySelectorAll('input:not([type="hidden"]):not([type="submit"]):not([type="button"]), select, textarea').forEach(function (field) {
            if (field.hasAttribute('aria-label') || field.hasAttribute('aria-labelledby') || field.hasAttribute('title')) return;
            if (field.labels && field.labels.length > 0) return;

            var label = '';
            var sibling = field.previousElementSibling;
            if (sibling && sibling.matches('label')) label = (sibling.textContent || '').trim();

            var name = String(field.getAttribute('name') || '');
            if (!label && name.indexOf('min_role[') === 0) {
                var row = field.closest('tr');
                var permissionName = row ? row.querySelector('th, td') : null;
                if (permissionName) label = 'Minimální role pro ' + (permissionName.textContent || '').trim();
            }
            if (!label) label = String(field.getAttribute('placeholder') || '').trim();
            if (!label) {
                var normalizedName = name.replace(/\[\]$/, '');
                label = fieldLabels[normalizedName] || normalizedName.replace(/[_-]+/g, ' ').trim();
            }
            if (label) field.setAttribute('aria-label', label.replace(/\s+/g, ' ').trim());
        });
    }

    window.showToast = function (message, type) {
        type = type || 'info';
        var container = document.getElementById('toastContainer');
        if (!container) return;
        var toast = document.createElement('div');
        toast.className = 'toast align-items-center border-0';
        toast.setAttribute('role', 'status');
        toast.setAttribute('aria-live', type === 'danger' ? 'assertive' : 'polite');
        toast.setAttribute('aria-atomic', 'true');
        var body = document.createElement('div');
        body.className = 'toast-body';
        var icon = document.createElement('i');
        var icons = {success:'bi-check-circle-fill text-success',danger:'bi-exclamation-triangle-fill text-danger',warning:'bi-exclamation-circle-fill text-warning',info:'bi-info-circle-fill text-info'};
        icon.className = 'bi ' + (icons[type] || icons.info) + ' fs-5';
        var text = document.createElement('span');
        text.textContent = String(message);
        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'btn-close btn-close-sm ms-auto';
        close.setAttribute('data-bs-dismiss', 'toast');
        close.setAttribute('aria-label', 'Zavřít');
        body.append(icon, text, close);
        toast.appendChild(body);
        container.appendChild(toast);
        if (window.bootstrap?.Toast) {
            var instance = new window.bootstrap.Toast(toast, {delay: 4000, autohide: true});
            instance.show();
            toast.addEventListener('hidden.bs.toast', function () { toast.remove(); });
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        ensureLoadingBar();
        ensureAccessibleFieldNames();
        document.body.classList.add('app-ui-ready');
    });

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement) || String(form.method).toLowerCase() !== 'post') return;
        if (form.dataset.appSubmitting) {
            event.preventDefault();
            return;
        }
        form.dataset.appSubmitting = 'pending';
        requestAnimationFrame(function () {
            if (event.defaultPrevented) {
                delete form.dataset.appSubmitting;
                return;
            }
            form.dataset.appSubmitting = '1';
            form.setAttribute('aria-busy', 'true');
            document.body.classList.add('app-loading');
        });
    });

    document.addEventListener('click', function (event) {
        if (!(event.target instanceof Element)) return;
        var button = event.target.closest('[data-copy-text]');
        if (!button) return;
        var label = button.querySelector('.app-copy-label');
        if (!navigator.clipboard || typeof navigator.clipboard.writeText !== 'function') {
            window.showToast('Kopírování není v tomto prohlížeči dostupné.', 'warning');
            return;
        }
        navigator.clipboard.writeText(button.dataset.copyText || '').then(function () {
            if (label) label.textContent = 'Zkopírováno';
            window.setTimeout(function () { if (label) label.textContent = 'Kopírovat'; }, 2000);
        }).catch(function () {
            window.showToast('Odkaz se nepodařilo zkopírovat.', 'danger');
        });
    });

    window.addEventListener('beforeunload', function () {
        document.body?.classList.add('app-loading');
    });
    window.addEventListener('pageshow', resetLoadingState);
})();
