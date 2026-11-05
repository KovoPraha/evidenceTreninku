(() => {
    'use strict';
    const boot = JSON.parse(document.getElementById('tv-boot').textContent);
    const canvas = window.TvCanvas.create(document.getElementById('tv-screen'), id => boot.image_base + id);
    let current = boot;
    let pending = null;
    let index = 0;
    let timer;
    let fetching = false;
    const activeSlides = () => current.document ? current.document.slides.filter(slide => slide.enabled) : [];
    function show() {
        const slides = activeSlides();
        const slide = slides[index] || null;
        document.title = current.document ? current.document.name : 'TV program';
        canvas.render(slide);
        clearTimeout(timer);
        timer = setTimeout(boundary, slide ? slide.duration * 1000 : 1000);
    }
    function boundary() {
        if (pending !== null) {
            current = pending;
            pending = null;
            index = 0;
            show();
        } else {
            const count = activeSlides().length;
            if (count > 1) {
                index = (index + 1) % count;
                show();
            } else {
                const slide = activeSlides()[0];
                timer = setTimeout(boundary, slide ? slide.duration * 1000 : 1000);
            }
        }
    }
    function preload(data) {
        const ids = new Set((data.document ? data.document.slides : []).map(slide => slide.asset_id).filter(Boolean));
        return Promise.all([...ids].map(id => new Promise(resolve => {
            const image = new Image();
            const timeout = setTimeout(resolve, 5000);
            image.onload = image.onerror = () => { clearTimeout(timeout); resolve(); };
            image.src = boot.image_base + id;
        })));
    }
    async function refresh() {
        if (fetching) return;
        fetching = true;
        try {
            const response = await fetch(boot.source, { cache: 'no-store', credentials: 'omit' });
            if (!response.ok && response.status !== 404) return;
            const data = await response.json();
            if (!Number.isInteger(data.revision) || !('document' in data)) return;
            if (data.revision !== current.revision) {
                await preload(data);
                pending = data;
            } else {
                pending = null;
            }
        } catch (_) {
            // A temporary server/network failure does not interrupt the current slide.
        } finally {
            fetching = false;
        }
    }
    const fullscreen = document.getElementById('tv-fullscreen');
    const message = document.getElementById('tv-fullscreen-message');
    fullscreen.addEventListener('click', async () => {
        try {
            if (document.fullscreenElement) await document.exitFullscreen();
            else if (document.documentElement.requestFullscreen) await document.documentElement.requestFullscreen();
            else throw new Error('unsupported');
            message.textContent = '';
        } catch (_) {
            message.textContent = 'Použijte režim celé obrazovky v nabídce prohlížeče TV.';
        }
    });
    document.addEventListener('fullscreenchange', () => {
        fullscreen.textContent = document.fullscreenElement ? 'Ukončit celou obrazovku' : 'Celá obrazovka';
    });
    let hideControls;
    const wakeControls = () => {
        document.body.classList.add('tv-controls-visible');
        clearTimeout(hideControls);
        hideControls = setTimeout(() => document.body.classList.remove('tv-controls-visible'), 3000);
    };
    ['pointermove', 'pointerdown', 'keydown'].forEach(event => document.addEventListener(event, wakeControls));
    wakeControls();
    show();
    setInterval(refresh, 15000);
})();
