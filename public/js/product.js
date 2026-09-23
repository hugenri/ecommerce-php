(function () {
    const mainImage = document.getElementById('mainImage');
    const thumbs = Array.prototype.slice.call(document.querySelectorAll('.gallery-thumb'));
    if (!mainImage || !thumbs.length) return;

    thumbs.forEach(function (thumb) {
        thumb.addEventListener('click', function () {
            mainImage.src = thumb.src;
            thumbs.forEach(function (t) {
                t.classList.remove('active');
            });
            thumb.classList.add('active');
        });
    });
})();
