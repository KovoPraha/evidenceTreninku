(function () {
    'use strict';
    var offerType = document.getElementById('new-type');
    var packeta = document.getElementById('new-packeta');
    if (offerType && packeta) {
        var adaptShipping = function () { packeta.disabled = offerType.value !== 'goods'; };
        offerType.addEventListener('change', adaptShipping);
        adaptShipping();
    }
    var definitions = window.shopAdminDefinitions || {};
    document.querySelectorAll('[data-shop-attribute-editor]').forEach(function (editor) {
        var rows = editor.querySelector('[data-shop-attribute-rows]');
        function adapt(row) {
            var key = row.querySelector('[name="attribute_keys[]"]');
            var field = row.querySelector('[name="attribute_values[]"]');
            if (!key || !field) return;
            var definition = definitions[key.value];
            var replacement;
            if (definition && Number(definition.active) === 1 && definition.value_type === 'choice') {
                replacement = document.createElement('select');
                replacement.className = 'form-select';
                replacement.append(new Option('Vyberte hodnotu', ''));
                var choices = definition.choices || [];
                if (field.value && choices.indexOf(field.value) === -1) replacement.append(new Option(field.value + ' (stávající hodnota)', field.value));
                choices.forEach(function (value) { replacement.append(new Option(value, value)); });
            } else {
                replacement = document.createElement('input');
                replacement.className = 'form-control';
                if (definition && definition.value_type === 'number') replacement.inputMode = 'decimal';
            }
            replacement.name = 'attribute_values[]';
            replacement.setAttribute('aria-label', 'Hodnota parametru ' + key.value);
            replacement.value = field.value;
            field.replaceWith(replacement);
        }
        rows.querySelectorAll('.app-attribute-row').forEach(adapt);
        editor.querySelector('[data-shop-add-attribute]')?.addEventListener('click', function () {
            var row = editor.querySelector('template').content.cloneNode(true);
            rows.append(row);
            rows.lastElementChild.querySelector('input').focus();
        });
        editor.addEventListener('change', function (event) {
            if (event.target.name === 'attribute_keys[]') adapt(event.target.closest('.app-attribute-row'));
        });
        editor.addEventListener('click', function (event) {
            var button = event.target.closest('[data-shop-remove-attribute]');
            if (button) button.closest('.app-attribute-row').remove();
        });
    });
    var colors = document.querySelector('[data-shop-colors]');
    var sizes = document.querySelector('[data-shop-sizes]');
    var preview = document.querySelector('[data-shop-combination-preview]');
    if (colors && sizes && preview) {
        function split(value) {
            var values = Array.from(new Set(value.split(/[,;\r\n]+/).map(function (v) {return v.trim();}).filter(Boolean)));
            return values.length ? values : [''];
        }
        function render() {
            var c = split(colors.value), s = split(sizes.value);
            preview.replaceChildren();
            if (c.length * s.length > 64) {
                preview.textContent = 'Příliš mnoho kombinací. Vyberte nejvýše 64 variant.';
                preview.classList.add('text-danger');
                return;
            }
            preview.classList.remove('text-danger');
            var list = document.createElement('div');
            list.className = 'd-flex gap-2 flex-wrap';
            c.forEach(function (color) {s.forEach(function (size) {
                var item = document.createElement('span');
                item.className = 'badge text-bg-light border';
                item.textContent = [color, size].filter(Boolean).join(' · ') || 'Základní varianta';
                list.append(item);
            });});
            preview.append(list);
            var count = document.createElement('div');
            count.className = 'form-text';
            count.textContent = 'Počet nových kombinací: ' + (c.length * s.length);
            preview.append(count);
        }
        colors.addEventListener('input', render);
        sizes.addEventListener('input', render);
        render();
    }
})();
