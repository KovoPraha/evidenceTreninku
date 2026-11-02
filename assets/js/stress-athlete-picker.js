(() => {
    'use strict';
    const picker = document.getElementById('stress-athlete-picker');
    if (!picker) return;
    const input = document.getElementById('stress-athlete-search');
    const hidden = document.getElementById('stress-athlete-id');
    const list = document.getElementById('stress-athlete-results');
    const status = document.getElementById('stress-athlete-status');
    let timer, controller, sequence = 0, active = -1, choices = [];

    function close() {
        clearTimeout(timer);
        if (controller) controller.abort();
        sequence++;
        list.replaceChildren();
        list.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
        active = -1;
        choices = [];
    }

    function select(person) {
        close();
        input.value = person.label;
        hidden.value = String(person.id);
        input.setCustomValidity('');
        status.textContent = 'Vybrán sportovec: ' + person.label;
        input.focus();
        hidden.dispatchEvent(new Event('change', {bubbles: true}));
    }

    function highlight(index) {
        active = index;
        Array.from(list.children).forEach((option, i) => {
            option.classList.toggle('active', i === index);
            option.setAttribute('aria-selected', String(i === index));
        });
        const option = list.children[index];
        if (option) {
            input.setAttribute('aria-activedescendant', option.id);
            option.scrollIntoView({block: 'nearest'});
        }
    }

    async function search(query) {
        const request = ++sequence;
        controller = new AbortController();
        status.textContent = 'Hledám sportovce…';
        try {
            const params = new URLSearchParams({q: query, limit: '20', browse: '1'});
            const response = await fetch('ajax_sportovci.php?' + params, {
                signal: controller.signal, headers: {Accept: 'application/json'}, cache: 'no-store'
            });
            if (response.status === 401 || response.status === 403) throw new Error('session');
            const rows = await response.json();
            if (!response.ok || !Array.isArray(rows)) throw new Error('lookup');
            if (request !== sequence) return;
            choices = rows;
            list.replaceChildren(...rows.map((person, index) => {
                const option = document.createElement('button');
                option.type = 'button';
                option.className = 'list-group-item list-group-item-action text-start';
                option.id = 'stress-athlete-option-' + index;
                option.tabIndex = -1;
                option.setAttribute('role', 'option');
                option.setAttribute('aria-selected', 'false');
                option.textContent = person.label;
                option.addEventListener('mousedown', event => event.preventDefault());
                option.addEventListener('click', () => select(person));
                return option;
            }));
            list.hidden = rows.length === 0;
            input.setAttribute('aria-expanded', String(rows.length > 0));
            status.textContent = rows.length
                ? 'Nalezeno ' + rows.length + ' sportovců. Vyberte kliknutím nebo šipkami a Enterem.' + (rows.length === 20 ? ' Pro další shody upřesněte hledání.' : '')
                : 'Žádný sportovec v dostupném rozsahu neodpovídá hledání. Zkuste jiné jméno nebo ověřte přidělení skupiny a aktivní pracovní pozici.';
        } catch (error) {
            if (request !== sequence || error.name === 'AbortError') return;
            close();
            status.textContent = error.message === 'session'
                ? 'Přihlášení nebo pracovní pozice se změnily. Obnovte stránku a přihlaste se znovu.'
                : 'Sportovce se nepodařilo načíst. Zkuste hledání znovu.';
        }
    }

    function schedule() {
        close();
        const query = input.value.trim();
        if (query.length === 1) {
            status.textContent = 'Napište alespoň 2 znaky pro hledání.';
            return;
        }
        timer = setTimeout(() => search(query), 250);
    }

    input.addEventListener('input', () => {
        hidden.value = '';
        input.setCustomValidity('Vyberte konkrétního sportovce z nabídky.');
        schedule();
    });
    input.addEventListener('focus', () => { if (!hidden.value) schedule(); });
    input.addEventListener('keydown', event => {
        if (event.key === 'Escape') { close(); return; }
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            if (!choices.length) return;
            event.preventDefault();
            highlight(active < 0 ? (event.key === 'ArrowDown' ? 0 : choices.length - 1)
                : (active + (event.key === 'ArrowDown' ? 1 : -1) + choices.length) % choices.length);
        } else if (event.key === 'Enter' && !hidden.value) {
            event.preventDefault();
            if (active >= 0 && choices[active]) select(choices[active]);
            else {
                input.setCustomValidity('Vyberte konkrétního sportovce z nabídky.');
                input.reportValidity();
            }
        }
    });
    picker.addEventListener('focusout', event => { if (!picker.contains(event.relatedTarget)) close(); });
    document.addEventListener('click', event => { if (!picker.contains(event.target)) close(); });
    input.form.addEventListener('submit', event => {
        if (hidden.value) return;
        event.preventDefault();
        input.setCustomValidity('Vyberte konkrétního sportovce z nabídky.');
        input.reportValidity();
    });
})();
