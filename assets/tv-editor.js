(() => {
    'use strict';
    const boot = JSON.parse(document.getElementById('tv-editor-boot').textContent);
    let doc = boot.document;
    let version = boot.version;
    let selected = 0;
    let blockIndex = -1;
    let dirty = false;
    let busy = false;
    let draggedSlide = null;
    const $ = id => document.getElementById(id);
    const clone = value => JSON.parse(JSON.stringify(value));
    const canvas = window.TvCanvas.create($('tv-editor-canvas'), id => boot.image_base + id);
    const slide = () => doc.slides[selected];
    const block = () => slide() && slide().blocks[blockIndex];
    const status = (text, error = false) => {
        $('tv-status').textContent = text;
        $('tv-status').className = `tv-status small mt-2 ${error ? 'text-danger' : 'text-body-secondary'}`;
    };
    function changed() {
        dirty = true;
        status('Neuložené změny. Na TV zatím běží poslední zveřejněná verze.');
    }
    function defaultSlide() {
        return {
            id: `s-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 10)}`,
            title: `Snímek ${doc.slides.length + 1}`, duration: 10, enabled: true,
            background: '#111827', fit: 'contain', asset_id: null, blocks: [],
        };
    }
    function checkOverflow() {
        const outside = [...canvas.stage.querySelectorAll('.tv-text')].some(el => {
            const item = slide().blocks[Number(el.dataset.block)];
            return item.y + el.offsetHeight > 1080;
        });
        $('tv-overflow').hidden = !outside;
        $('tv-overflow').textContent = outside ? 'Text přesahuje spodní okraj. Posuňte jej výše, rozšiřte blok nebo zmenšete písmo.' : '';
    }
    function selectBlock(index, focus = false) {
        blockIndex = index;
        renderProperties();
        renderCanvas();
        if (focus) canvas.stage.querySelector(`[data-block="${index}"]`)?.focus({ preventScroll: true });
    }
    function renderCanvas() {
        const nodes = canvas.render(slide() || null);
        nodes.forEach((el, index) => {
            el.tabIndex = 0;
            el.setAttribute('role', 'button');
            el.setAttribute('aria-label', `Textový blok ${index + 1}: ${slide().blocks[index].text.slice(0, 70)}`);
            if (index === blockIndex) {
                el.classList.add('tv-selected');
                const handle = document.createElement('span');
                handle.className = 'tv-resize-handle';
                handle.setAttribute('aria-hidden', 'true');
                el.append(handle);
            }
            el.addEventListener('pointerdown', event => beginDrag(event, index));
            el.addEventListener('keydown', event => {
                if (boot.archived || busy) return;
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    selectBlock(index, true);
                    return;
                }
                const directions = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] };
                if (!directions[event.key]) return;
                event.preventDefault();
                blockIndex = index;
                const item = block();
                const step = event.shiftKey ? 10 : 1;
                item.x = Math.max(0, Math.min(1920 - item.width, item.x + directions[event.key][0] * step));
                item.y = Math.max(0, Math.min(1079, item.y + directions[event.key][1] * step));
                changed();
                selectBlock(index, true);
            });
        });
        requestAnimationFrame(checkOverflow);
    }
    function beginDrag(event, index) {
        if (event.button !== 0 || boot.archived || busy) return;
        event.preventDefault();
        const resize = event.target.classList.contains('tv-resize-handle');
        blockIndex = index;
        const item = block();
        const start = { x: event.clientX, y: event.clientY, left: item.x, top: item.y, width: item.width };
        const node = canvas.stage.querySelector(`[data-block="${index}"]`);
        // Keep the pointer target in the DOM during the gesture.
        canvas.stage.querySelectorAll('.tv-text').forEach(el => el.classList.toggle('tv-selected', el === node));
        renderProperties();
        let moved = false;
        const move = moveEvent => {
            const dx = Math.round((moveEvent.clientX - start.x) / canvas.scale);
            const dy = Math.round((moveEvent.clientY - start.y) / canvas.scale);
            if (resize) item.width = Math.max(20, Math.min(1920 - item.x, start.width + dx));
            else {
                item.x = Math.max(0, Math.min(1920 - item.width, start.left + dx));
                item.y = Math.max(0, Math.min(1079, start.top + dy));
            }
            Object.assign(node.style, { left: `${item.x}px`, top: `${item.y}px`, width: `${item.width}px` });
            renderProperties();
            checkOverflow();
            moved = true;
        };
        const end = () => {
            window.removeEventListener('pointermove', move);
            window.removeEventListener('pointerup', end);
            window.removeEventListener('pointercancel', end);
            if (moved) changed();
            renderCanvas();
        };
        window.addEventListener('pointermove', move);
        window.addEventListener('pointerup', end);
        window.addEventListener('pointercancel', end);
    }
    function button(label, title, handler) {
        const el = document.createElement('button');
        el.type = 'button';
        el.className = 'btn btn-sm btn-outline-secondary';
        el.textContent = label;
        el.title = title;
        el.setAttribute('aria-label', title);
        el.disabled = boot.archived || busy;
        el.addEventListener('click', handler);
        return el;
    }
    function moveSlide(from, to) {
        if (to < 0 || to >= doc.slides.length || from === to || boot.archived || busy) return;
        const [item] = doc.slides.splice(from, 1);
        doc.slides.splice(to, 0, item);
        selected = to;
        blockIndex = -1;
        changed();
        renderAll();
    }
    function renderList() {
        const list = $('tv-slide-list');
        list.replaceChildren();
        doc.slides.forEach((item, index) => {
            const row = document.createElement('div');
            row.className = 'tv-slide-row';
            row.setAttribute('aria-current', String(index === selected));
            row.draggable = !boot.archived && !busy;
            const select = button(`${index + 1}. ${item.title}`, `Vybrat snímek ${index + 1}`, () => {
                selected = index; blockIndex = -1; renderAll();
            });
            select.className = 'tv-slide-select';
            const meta = document.createElement('div');
            meta.className = 'tv-slide-meta';
            meta.textContent = `${item.duration} s · ${item.enabled ? 'Zařazený' : 'Vypnutý'}`;
            const actions = document.createElement('div');
            actions.className = 'tv-slide-actions';
            const up = button('↑', `Posunout snímek ${index + 1} nahoru`, () => moveSlide(index, index - 1));
            const down = button('↓', `Posunout snímek ${index + 1} dolů`, () => moveSlide(index, index + 1));
            up.disabled ||= index === 0;
            down.disabled ||= index === doc.slides.length - 1;
            actions.append(up, down, button('Kopie', `Kopírovat snímek ${index + 1}`, () => {
                if (doc.slides.length >= 100) return status('Program smí obsahovat nejvýše 100 snímků.', true);
                const copy = clone(item);
                copy.id = defaultSlide().id;
                copy.title = `${copy.title.slice(0, 110)} – kopie`;
                doc.slides.splice(index + 1, 0, copy);
                selected = index + 1; blockIndex = -1; changed(); renderAll();
            }), button('Smazat', `Smazat snímek ${index + 1}`, () => {
                if (!confirm(`Smazat snímek „${item.title}“ z konceptu?`)) return;
                doc.slides.splice(index, 1);
                selected = Math.max(0, Math.min(selected, doc.slides.length - 1));
                blockIndex = -1; changed(); renderAll();
            }));
            row.append(select, meta, actions);
            row.addEventListener('dragstart', event => {
                draggedSlide = index;
                event.dataTransfer.setData('text/plain', String(index));
                event.dataTransfer.effectAllowed = 'move';
            });
            row.addEventListener('dragover', event => { if (draggedSlide !== null) { event.preventDefault(); row.classList.add('drag-over'); } });
            row.addEventListener('dragleave', () => row.classList.remove('drag-over'));
            row.addEventListener('drop', event => { event.preventDefault(); moveSlide(draggedSlide, index); draggedSlide = null; });
            row.addEventListener('dragend', () => { draggedSlide = null; list.querySelectorAll('.drag-over').forEach(el => el.classList.remove('drag-over')); });
            list.append(row);
        });
        if (!doc.slides.length) {
            const empty = document.createElement('p');
            empty.className = 'tv-editor-empty';
            empty.textContent = 'Program zatím nemá snímky.';
            list.append(empty);
        }
    }
    function renderProperties() {
        const current = slide();
        $('tv-no-slide').hidden = Boolean(current);
        $('tv-properties').hidden = !current;
        if (!current) { $('tv-overflow').hidden = true; return; }
        document.querySelectorAll('[data-slide]').forEach(input => {
            if (input.type === 'checkbox') input.checked = current[input.dataset.slide];
            else input.value = current[input.dataset.slide];
        });
        $('tv-remove-image').disabled = !current.asset_id || boot.archived || busy;
        const options = $('tv-block-select');
        options.replaceChildren();
        const none = document.createElement('option');
        none.value = '-1'; none.textContent = 'Vyberte textový blok'; options.append(none);
        current.blocks.forEach((item, index) => {
            const option = document.createElement('option');
            option.value = String(index);
            option.textContent = `${index + 1}. ${item.text.slice(0, 45) || 'Prázdný text'}`;
            options.append(option);
        });
        options.value = String(blockIndex);
        const item = block();
        $('tv-block-properties').hidden = !item;
        $('tv-delete-text').disabled = !item || boot.archived || busy;
        if (item) document.querySelectorAll('[data-block]').forEach(input => {
            // Canvas nodes carry data-block too; only controls have a value.
            if (!input.matches('input,textarea,select')) return;
            if (input.type === 'checkbox') input.checked = item[input.dataset.block];
            else input.value = item[input.dataset.block];
        });
    }
    function renderAll() { renderList(); renderProperties(); renderCanvas(); }
    $('tv-add-slide').addEventListener('click', () => {
        if (doc.slides.length >= 100) return status('Program smí obsahovat nejvýše 100 snímků.', true);
        doc.slides.push(defaultSlide());
        selected = doc.slides.length - 1; blockIndex = -1; changed(); renderAll();
    });
    $('tv-program-name').addEventListener('input', event => { doc.name = event.target.value; changed(); });
    document.querySelectorAll('[data-slide]').forEach(input => input.addEventListener('input', () => {
        if (!slide()) return;
        let value = input.type === 'checkbox' ? input.checked : input.value;
        if (input.type === 'number') {
            if (!Number.isFinite(input.valueAsNumber)) return;
            value = input.valueAsNumber;
        }
        slide()[input.dataset.slide] = value;
        changed(); renderList(); renderCanvas();
    }));
    document.querySelectorAll('[data-block]').forEach(input => input.addEventListener('input', () => {
        const item = block();
        if (!item) return;
        let value = input.type === 'checkbox' ? input.checked : input.value;
        if (input.type === 'number') {
            if (!Number.isFinite(input.valueAsNumber)) return;
            value = input.valueAsNumber;
        }
        item[input.dataset.block] = value;
        if (input.dataset.block === 'x') item.x = Math.max(0, Math.min(1920 - item.width, Math.round(item.x)));
        if (input.dataset.block === 'width') item.width = Math.max(20, Math.min(1920 - item.x, Math.round(item.width)));
        changed(); renderCanvas();
    }));
    $('tv-block-select').addEventListener('change', event => selectBlock(Number(event.target.value)));
    $('tv-add-text').addEventListener('click', () => {
        if (!slide()) return;
        if (slide().blocks.length >= 20) return status('Snímek smí obsahovat nejvýše 20 textových bloků.', true);
        slide().blocks.push({
            text: 'Nový text', x: 120, y: 120, width: 1200, font_size: 72,
            color: '#ffffff', bold: false, align: 'left', background: '#000000', background_opacity: 0,
        });
        blockIndex = slide().blocks.length - 1; changed(); renderProperties(); renderCanvas();
        $('tv-text').focus(); $('tv-text').select();
    });
    $('tv-delete-text').addEventListener('click', () => {
        if (!block()) return;
        slide().blocks.splice(blockIndex, 1); blockIndex = -1; changed(); renderProperties(); renderCanvas();
    });
    $('tv-remove-image').addEventListener('click', () => { if (slide()) { slide().asset_id = null; changed(); renderProperties(); renderCanvas(); } });
    async function request(action, fields = {}) {
        if (busy) throw new Error('Počkejte na dokončení předchozí operace.');
        const data = new FormData();
        Object.entries({ action, csrf_token: boot.csrf, id: boot.id, version, ...fields }).forEach(([key, value]) => data.append(key, value));
        busy = true;
        const controls = [...document.querySelectorAll('main button,main input,main select,main textarea')].map(el => [el, el.disabled]);
        controls.forEach(([el]) => { el.disabled = true; });
        try {
            const response = await fetch(boot.endpoint, { method: 'POST', body: data, headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            let body;
            try { body = await response.json(); } catch (_) { throw new Error('Server nevrátil očekávanou odpověď. Ověřte přihlášení a obnovte stránku.'); }
            if (!response.ok || !body.ok) throw new Error(body.message || 'Operace selhala.');
            return body.result;
        } finally {
            busy = false;
            controls.forEach(([el, disabled]) => { el.disabled = disabled; });
        }
    }
    $('tv-image').addEventListener('change', async event => {
        const file = event.target.files[0];
        const target = slide();
        if (!file || !target) return;
        status('Nahrávám obrázek…');
        try {
            const asset = await request('upload', { image: file });
            target.asset_id = asset.id;
            changed(); renderProperties(); renderCanvas();
        } catch (error) { status(error.message, true); }
        event.target.value = '';
    });
    const save = async action => {
        const inputs = [...document.querySelectorAll('#tv-program-name,[data-slide],[data-block]')].filter(el => el.matches('input,textarea,select'));
        for (const input of inputs) {
            if (!input.disabled && !input.closest('[hidden]') && !input.reportValidity()) return;
        }
        if (action === 'publish' && !confirm('Zveřejnit tento program? Obsah bude veřejně dostupný na jeho TV adrese.')) return;
        status(action === 'publish' ? 'Zveřejňuji…' : 'Ukládám koncept…');
        try {
            const result = await request(action, { document: JSON.stringify(doc) });
            version = result.version; doc = result.document; dirty = false;
            status(action === 'publish' ? 'Zveřejněno. Televize převezmou změnu automaticky mezi snímky.' : 'Koncept je uložený. Pro změnu na TV použijte Zveřejnit změny.');
            renderAll();
            if (action === 'publish') {
                // Reload the history from the server without losing local edits.
                location.reload();
            }
        } catch (error) { status(error.message, true); }
    };
    $('tv-save').addEventListener('click', () => save('save'));
    $('tv-publish').addEventListener('click', () => save('publish'));
    document.querySelectorAll('[data-restore]').forEach(button => button.addEventListener('click', async () => {
        if (!confirm('Načíst tuto verzi do konceptu? Nynější rozpracované změny budou nahrazeny.')) return;
        try {
            const result = await request('restore_revision', { revision_id: button.dataset.restore });
            version = result.version; doc = result.document; dirty = false; selected = 0; blockIndex = -1;
            $('tv-program-name').value = doc.name;
            renderAll(); status('Předchozí verze je načtená do konceptu. Na TV se projeví až po zveřejnění.');
        } catch (error) { status(error.message, true); }
    }));
    const dialog = $('tv-preview-dialog');
    const preview = window.TvCanvas.create($('tv-preview-canvas'), id => boot.image_base + id);
    let previewTimer;
    $('tv-preview').addEventListener('click', () => {
        const slides = clone(doc.slides.filter(item => item.enabled));
        let index = 0;
        const next = () => {
            preview.render(slides[index] || null);
            previewTimer = setTimeout(() => { index = (index + 1) % (slides.length || 1); next(); }, slides[index] ? slides[index].duration * 1000 : 1000);
        };
        dialog.showModal(); preview.resize(); next();
    });
    $('tv-close-preview').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => clearTimeout(previewTimer));
    window.addEventListener('beforeunload', event => { if (dirty || busy) { event.preventDefault(); event.returnValue = ''; } });
    document.querySelectorAll('input[type="number"],#tv-slide-title,#tv-program-name').forEach(input => { input.required = true; });
    renderAll();
    if (boot.archived) document.querySelectorAll('main input,main select,main textarea,main button:not(#tv-preview):not(#tv-close-preview)')
        .forEach(el => { el.disabled = true; });
})();
