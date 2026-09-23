(function (global) {
    'use strict';

    var STORAGE_KEY = 'cart_count';
    var CART_LINK_SELECTOR = '[data-cart-count]';
    var BADGE_SELECTOR = '.cart-badge';

    var badgeNode = null;

    function findBadge() {
        if (badgeNode && document.contains(badgeNode)) {
            return badgeNode;
        }
        var link = document.querySelector(CART_LINK_SELECTOR);
        badgeNode = link ? link.querySelector(BADGE_SELECTOR) : null;
        return badgeNode;
    }

    function readCache() {
        try {
            var n = parseInt(global.localStorage.getItem(STORAGE_KEY), 10);
            return Number.isFinite(n) && n >= 0 ? n : 0;
        } catch (e) {
            return 0;
        }
    }

    function writeCache(count) {
        try {
            global.localStorage.setItem(STORAGE_KEY, String(count));
        } catch (e) {
            // storage no disponible: el render de la página actual sigue sincronizándose
        }
    }

    function render(count) {
        var badge = findBadge();
        if (!badge) {
            return;
        }
        badge.textContent = String(count);
        badge.hidden = count <= 0;
    }

    function setCount(count) {
        var n = Math.max(0, parseInt(count, 10) || 0);
        writeCache(n);
        render(n);
    }

    function getCount() {
        return readCache();
    }

    function sync() {
        var link = document.querySelector(CART_LINK_SELECTOR);
        if (!link) {
            return;
        }
        var serverCount = Math.max(0, parseInt(link.getAttribute('data-cart-count'), 10) || 0);
        var authoritative = link.getAttribute('data-cart-count-authoritative') === '1';

        if (authoritative) {
            setCount(serverCount);
        } else {
            render(readCache());
        }
    }

    sync();

    global.CartCounter = {
        get: getCount,
        set: setCount,
        sync: sync
    };
})(window);
