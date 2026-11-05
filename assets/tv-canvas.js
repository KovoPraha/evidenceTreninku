/* Shared 1920 × 1080 layout for the editor, preview and public player. */
(() => {
    'use strict';
    const WIDTH = 1920;
    const HEIGHT = 1080;
    function create(host, imageUrl) {
        const stage = document.createElement('div');
        stage.className = 'tv-stage';
        host.replaceChildren(stage);
        let scale = 1;
        const resize = () => {
            scale = Math.min(host.clientWidth / WIDTH, host.clientHeight / HEIGHT) || 1;
            stage.style.transform = `translate(-50%, -50%) scale(${scale})`;
        };
        const observer = window.ResizeObserver ? new ResizeObserver(resize) : null;
        if (observer) observer.observe(host);
        window.addEventListener('resize', resize);
        resize();
        function render(slide) {
            stage.replaceChildren();
            stage.style.backgroundColor = slide ? slide.background : '#111827';
            if (!slide) {
                const blank = document.createElement('div');
                blank.className = 'tv-empty';
                blank.textContent = 'KOVO Praha';
                stage.append(blank);
                return [];
            }
            if (slide.asset_id) {
                const img = document.createElement('img');
                img.className = 'tv-background';
                img.alt = '';
                img.draggable = false;
                img.style.objectFit = slide.fit;
                img.src = imageUrl(slide.asset_id);
                stage.append(img);
            }
            return slide.blocks.map((block, index) => {
                const el = document.createElement('div');
                el.className = 'tv-text';
                el.dataset.block = String(index);
                el.textContent = block.text;
                const rgb = block.background.match(/[a-f0-9]{2}/gi).map(hex => parseInt(hex, 16));
                Object.assign(el.style, {
                    left: `${block.x}px`, top: `${block.y}px`, width: `${block.width}px`,
                    fontSize: `${block.font_size}px`, color: block.color,
                    fontWeight: block.bold ? '700' : '400', textAlign: block.align,
                    backgroundColor: `rgba(${rgb.join(',')},${block.background_opacity})`,
                });
                stage.append(el);
                return el;
            });
        }
        return {
            stage, render, resize, get scale() { return scale; },
            destroy() { if (observer) observer.disconnect(); window.removeEventListener('resize', resize); },
        };
    }
    window.TvCanvas = { create, WIDTH, HEIGHT };
})();
