(function () {
    document.querySelectorAll('.categories-scroll').forEach(function (wrap) {
        const track = wrap.querySelector('.categories-track');
        const prev = wrap.querySelector('.carousel-btn.prev');
        const next = wrap.querySelector('.carousel-btn.next');
        if (!track || !prev || !next) return;

        function updateButtons() {
            const maxScroll = track.scrollWidth - track.clientWidth;
            prev.hidden = maxScroll <= 0 || Math.round(track.scrollLeft) <= 0;
            next.hidden = maxScroll <= 0 || Math.round(track.scrollLeft) >= maxScroll - 1;
        }

        function scrollByStep(dir) {
            const card = track.querySelector('.category-col');
            const step = card ? card.offsetWidth + 16 : Math.round(track.clientWidth * 0.8);
            track.scrollBy({ left: dir * step, behavior: 'smooth' });
        }

        prev.addEventListener('click', function () { scrollByStep(-1); });
        next.addEventListener('click', function () { scrollByStep(1); });
        track.addEventListener('scroll', updateButtons, { passive: true });
        window.addEventListener('resize', updateButtons);
        updateButtons();
    });
})();
