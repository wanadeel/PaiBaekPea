(() => {
    'use strict';
    const root = document.querySelector('.country-carousel');
    if (!root || root.dataset.ready) return;
    root.dataset.ready = 'true';
    const track = root.querySelector('.country-carousel-track');
    const prev = root.querySelector('[data-country-prev]');
    const next = root.querySelector('[data-country-next]');
    const selected = root.querySelector('.is-selected');
    const controls = root.querySelector('.country-carousel-controls');
    if (!track || !prev || !next || !controls) return;
    controls.hidden = false;
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
    function update() {
        prev.disabled = track.scrollLeft <= 3;
        next.disabled = track.scrollLeft >= track.scrollWidth - track.clientWidth - 3;
    }
    function step(direction) {
        const cards = [...track.querySelectorAll('.country-map-card')];
        const gap = cards.length > 1 ? cards[1].offsetLeft - cards[0].offsetLeft : track.clientWidth;
        track.scrollBy({left: direction * gap, behavior: reduced.matches ? 'instant' : 'smooth'});
    }
    prev.addEventListener('click', () => step(-1));
    next.addEventListener('click', () => step(1));
    track.addEventListener('keydown', event => {
        if (event.target !== track) return;
        if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
            event.preventDefault();
            step(event.key === 'ArrowRight' ? 1 : -1);
        }
    });
    track.addEventListener('scroll', update, {passive:true});
    let positioned = false;
    function layout() {
        if (track.clientWidth === 0) return;
        if (!positioned && selected) {
            const left = selected.getBoundingClientRect().left - track.getBoundingClientRect().left + track.scrollLeft;
            track.scrollLeft = Math.max(0, left - (track.clientWidth - selected.clientWidth) / 2);
            positioned = true;
        }
        update();
    }
    if ('ResizeObserver' in window) new ResizeObserver(layout).observe(track);
    window.addEventListener('resize', layout);
    const disclosure = root.closest('details');
    if (disclosure) disclosure.addEventListener('toggle', () => requestAnimationFrame(layout));
    layout();
})();
