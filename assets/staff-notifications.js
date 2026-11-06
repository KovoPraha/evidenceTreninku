(() => {
    'use strict';
    const root = document.querySelector('[data-staff-notifications]');
    if (!root) return;
    const count = root.querySelector('[data-staff-notification-count]');
    const items = root.querySelector('[data-staff-notification-items]');
    let running = false;
    async function refresh() {
        if (running || document.hidden) return;
        running = true;
        try {
            const response = await fetch(root.dataset.url + '?format=json', {credentials: 'same-origin', cache: 'no-store', headers: {'Accept': 'application/json'}});
            if (!response.ok) throw new Error('unavailable');
            const data = await response.json();
            if (!Number.isInteger(data.unread) || !Array.isArray(data.items)) throw new Error('unavailable');
            count.textContent = String(data.unread);
            count.hidden = data.unread === 0;
            items.replaceChildren();
            data.items.forEach(item => {
                const link = document.createElement('a');
                link.className = 'dropdown-item text-wrap py-2';
                link.href = root.dataset.url + '?notice=' + encodeURIComponent(item.id);
                link.textContent = item.title;
                items.append(link);
            });
            if (!data.items.length) {
                const empty = document.createElement('p');
                empty.className = 'small text-muted px-2 my-2';
                empty.textContent = 'Žádná nová upozornění.';
                items.append(empty);
            }
        } catch (_) {
            // Keep the last known count; never pretend that a failed refresh means zero.
        } finally { running = false; }
    }
    root.querySelector('button').addEventListener('click', refresh);
    document.addEventListener('visibilitychange', refresh);
    window.setInterval(refresh, 60000);
})();
