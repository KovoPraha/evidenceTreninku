(() => {
    'use strict';
    const choice = document.querySelector('[data-medical-person-choice]');
    const selected = document.querySelector('input[name="medical_selected_person"]');
    if (!choice || !selected) return;
    choice.addEventListener('change', () => {
        selected.value = choice.value;
        document.querySelectorAll('[data-medical-person-page]').forEach(link => {
            const url = new URL(link.href);
            url.searchParams.set('medical_selected_person', choice.value);
            link.href = url.toString();
        });
    });
})();
