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
