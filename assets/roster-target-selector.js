(() => {
    document.querySelectorAll('[data-roster-target-container]').forEach(container => {
        const dateField = document.querySelector(container.dataset.rosterDateSelector || '[name="datum"]');
        if (!dateField) return;
        let controller = null;
        dateField.addEventListener('change', async () => {
            const date = dateField.value.slice(0, 10);
            if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) return;
            const selected = Array.from(container.querySelectorAll('input[name="team_ids[]"]:checked'), input => input.value);
            if (controller) controller.abort();
            controller = new AbortController();
            const query = new URLSearchParams({date});
            selected.forEach(id => query.append('selected[]', id));
            try {
                const response = await fetch('ajax_roster_target_selector.php?' + query, {signal: controller.signal});
                if (!response.ok) throw new Error('HTTP ' + response.status);
                container.innerHTML = await response.text();
                container.dispatchEvent(new Event('roster-targets-refreshed'));
            } catch (error) {
                if (error.name !== 'AbortError') {
                    container.insertAdjacentHTML('beforeend', '<div class="alert alert-warning mt-2">Soupisky se nepodařilo aktualizovat podle data. Obnovte stránku.</div>');
                }
            }
        });
    });
})();
