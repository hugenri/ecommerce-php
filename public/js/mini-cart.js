(function (global) {
    'use strict';

    var CART_LINK_SELECTOR = '[data-cart-count]';
    var ADD_FORM_SELECTOR = 'form[action="/cart/add"]';
    var OFFCANVAS_ID = 'miniCartOffcanvas';
    var ITEMS_ID = 'miniCartItems';
    var EMPTY_ID = 'miniCartEmpty';
    var FOOTER_ID = 'miniCartFooter';
    var SKELETON_ID = 'miniCartSkeleton';
    var LIVE_ID = 'miniCartLive';
    var COUNT_ID = 'miniCartCount';
    var SUBTOTAL_ID = 'miniCartSubtotal';
    var CLOSE_ID = 'miniCartClose';

    var offcanvasEl = document.getElementById(OFFCANVAS_ID);
    if (!offcanvasEl || typeof global.bootstrap === 'undefined') {
        return;
    }

    var offcanvas = new global.bootstrap.Offcanvas(offcanvasEl);

    var itemsEl = document.getElementById(ITEMS_ID);
    var emptyEl = document.getElementById(EMPTY_ID);
    var footerEl = document.getElementById(FOOTER_ID);
    var skeletonEl = document.getElementById(SKELETON_ID);
    var liveEl = document.getElementById(LIVE_ID);
    var countEl = document.getElementById(COUNT_ID);
    var subtotalEl = document.getElementById(SUBTOTAL_ID);
    var closeBtnEl = document.getElementById(CLOSE_ID);

    var cartCache = null;
    var rows = {};
    var pending = {};
    var openedFrom = null;
    var headerEl = null;
    var isOpen = false;

    function money(n) {
        return '$' + Number(n).toFixed(2);
    }

    function announce(msg) {
        if (!liveEl) {
            return;
        }
        liveEl.textContent = '';
        void liveEl.offsetWidth;
        liveEl.textContent = msg;
    }

    // ── Posición: debajo del navbar (panel y fondo oscuro), sin valores fijos ──

    function findHeader() {
        if (headerEl && document.body.contains(headerEl)) {
            return headerEl;
        }
        headerEl = document.querySelector('body > header') || document.querySelector('header') || null;
        return headerEl;
    }

    function headerTop() {
        var header = findHeader();
        var top = header ? header.getBoundingClientRect().bottom : 0;
        return top < 0 ? 0 : top;
    }

    function viewportHeight() {
        return global.innerHeight || document.documentElement.clientHeight;
    }

    function applyPosition() {
        var top = headerTop();
        var height = Math.max(0, viewportHeight() - top);
        offcanvasEl.style.top = top + 'px';
        offcanvasEl.style.height = height + 'px';
        positionBackdrop();
    }

    function positionBackdrop() {
        if (!isOpen) {
            return;
        }
        var backdrop = document.body.querySelector('.offcanvas-backdrop');
        if (!backdrop) {
            return;
        }
        var top = headerTop();
        backdrop.style.top = top + 'px';
        backdrop.style.height = Math.max(0, viewportHeight() - top) + 'px';
    }

    // El fondo oscuro lo crea Bootstrap en cada apertura y lo inserta como hijo
    // directo de <body>; se posiciona debajo del navbar en cuanto aparece, para
    // no cubrir el menú. childList sin subtree evita mediciones en cada render.
    if (global.MutationObserver) {
        var backdropObserver = new global.MutationObserver(function () {
            positionBackdrop();
        });
        backdropObserver.observe(document.body, { childList: true });
    }

    // ── Skeleton ──

    function showSkeleton() {
        if (skeletonEl) {
            skeletonEl.classList.remove('d-none');
        }
        if (emptyEl) {
            emptyEl.classList.add('d-none');
        }
        if (footerEl) {
            footerEl.classList.add('d-none');
        }
    }

    function hideSkeleton() {
        if (skeletonEl) {
            skeletonEl.classList.add('d-none');
        }
    }

    // ── Construcción de filas ──

    function buildItem(item) {
        var row = document.createElement('div');
        row.className = 'mini-cart-item d-flex align-items-center gap-3 p-3 border-bottom';
        row.dataset.productId = item.product_id;

        var imgWrap = document.createElement('div');
        imgWrap.className = 'mini-cart-img-wrap';
        var imgIcon = document.createElement('i');
        imgIcon.className = 'bi bi-image text-muted fs-3';
        imgIcon.setAttribute('aria-hidden', 'true');
        if (item.image) {
            var img = document.createElement('img');
            img.src = item.image;
            img.alt = item.name;
            img.className = 'mini-cart-img';
            img.addEventListener('error', function () {
                imgWrap.innerHTML = '';
                imgWrap.appendChild(imgIcon);
            });
            imgWrap.appendChild(img);
        } else {
            imgWrap.appendChild(imgIcon);
        }

        var info = document.createElement('div');
        info.className = 'flex-grow-1 min-w-0';

        var link = document.createElement('a');
        link.href = '/product/' + item.product_id;
        link.className = 'text-decoration-none fw-semibold d-block text-truncate';
        link.textContent = item.name;
        info.appendChild(link);

        var priceEl = document.createElement('div');
        priceEl.className = 'text-muted small';
        priceEl.textContent = money(item.price) + ' c/u';
        info.appendChild(priceEl);

        var stockBadge = document.createElement('span');
        stockBadge.className = 'badge bg-warning text-dark mini-cart-stock-badge d-none';
        stockBadge.textContent = 'Agotado';
        info.appendChild(stockBadge);

        var stepper = document.createElement('div');
        stepper.className = 'qty-stepper input-group input-group-sm mt-1 d-inline-flex';
        stepper.style.width = 'fit-content';
        stepper.setAttribute('data-quantity-stepper', '');
        stepper.setAttribute('data-min', '1');
        stepper.setAttribute('data-max', String(item.stock > 0 ? item.stock : 0));

        var dec = document.createElement('button');
        dec.type = 'button';
        dec.className = 'btn btn-outline-secondary';
        dec.dataset.qtyDec = '';
        dec.innerHTML = '&minus;';
        dec.setAttribute('aria-label', 'Disminuir cantidad');

        var input = document.createElement('input');
        input.type = 'text';
        input.className = 'form-control text-center';
        input.value = String(item.quantity);
        input.readOnly = true;
        input.dataset.qtyInput = '';
        input.setAttribute('inputmode', 'numeric');
        input.setAttribute('aria-label', 'Cantidad');

        var inc = document.createElement('button');
        inc.type = 'button';
        inc.className = 'btn btn-outline-secondary';
        inc.dataset.qtyInc = '';
        inc.innerHTML = '+';
        inc.setAttribute('aria-label', 'Aumentar cantidad');

        stepper.appendChild(dec);
        stepper.appendChild(input);
        stepper.appendChild(inc);
        info.appendChild(stepper);

        var busy = document.createElement('div');
        busy.className = 'mini-cart-busy ms-1';
        var spinner = document.createElement('span');
        spinner.className = 'spinner-border spinner-border-sm';
        spinner.setAttribute('role', 'status');
        spinner.setAttribute('aria-hidden', 'true');
        busy.appendChild(spinner);
        info.appendChild(busy);

        var right = document.createElement('div');
        right.className = 'mini-cart-right d-flex flex-column align-items-end gap-2';

        var lineSubtotal = document.createElement('span');
        lineSubtotal.className = 'mini-cart-line-total fw-bold';
        lineSubtotal.dataset.miniSubtotal = '';
        lineSubtotal.textContent = money(item.subtotal);
        right.appendChild(lineSubtotal);

        var removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'btn btn-sm btn-outline-danger';
        removeBtn.dataset.miniRemove = '';
        removeBtn.setAttribute('aria-label', 'Eliminar producto del carrito');
        var trash = document.createElement('i');
        trash.className = 'bi bi-trash';
        removeBtn.appendChild(trash);
        right.appendChild(removeBtn);

        row.appendChild(imgWrap);
        row.appendChild(info);
        row.appendChild(right);

        syncItemState(row, item);
        return row;
    }

    function syncItemState(row, item) {
        var input = row.querySelector('[data-qty-input]');
        var subtotal = row.querySelector('[data-mini-subtotal]');
        var badge = row.querySelector('.mini-cart-stock-badge');

        var outOfStock = typeof item.stock === 'number' && item.stock <= 0;

        if (input) {
            input.value = String(item.quantity);
        }
        if (subtotal) {
            subtotal.textContent = money(item.subtotal);
        }
        if (badge) {
            badge.classList.toggle('d-none', !outOfStock);
        }
        if (global.QuantityStepper) {
            global.QuantityStepper.sync(row);
        }
    }

    function renderItem(item) {
        var key = String(item.product_id);
        var row = rows[key];
        if (!row) {
            row = buildItem(item);
            rows[key] = row;
            itemsEl.appendChild(row);
            return;
        }
        syncItemState(row, item);
    }

    function render(payload) {
        var items = payload && Array.isArray(payload.items) ? payload.items : [];
        var count = parseInt(payload.count || 0, 10);
        var hasItems = items.length > 0;
        var seen = {};

        items.forEach(function (item) {
            seen[String(item.product_id)] = true;
            renderItem(item);
        });

        Object.keys(rows).forEach(function (key) {
            if (!seen[key]) {
                var row = rows[key];
                if (row && row.parentNode) {
                    row.parentNode.removeChild(row);
                }
                delete rows[key];
            }
        });

        hideSkeleton();
        itemsEl.classList.toggle('d-none', !hasItems);
        emptyEl.classList.toggle('d-none', hasItems);
        footerEl.classList.toggle('d-none', !hasItems);

        if (global.CartCounter) {
            global.CartCounter.set(count);
        }
        if (!hasItems) {
            announce('Tu carrito está vacío.');
            return;
        }
        if (countEl) {
            countEl.textContent = String(count);
        }
        if (subtotalEl) {
            subtotalEl.textContent = money(payload.subtotal);
        }
    }

    // ── Carga con caché en memoria ──

    const load = async () => {
        if (cartCache !== null) {
            render(cartCache);
            return;
        }
        showSkeleton();
        try {
            const response = await fetch('/cart', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            const json = await response.json();
            if (json && json.success) {
                cartCache = json.data;
                render(cartCache);
            } else {
                hideSkeleton();
            }
        } catch (error) {
            console.error(error);
            hideSkeleton();
            if (emptyEl) {
                emptyEl.classList.remove('d-none');
            }
        }
    };

    // ── Mutaciones (sin peticiones concurrentes por producto) ──

    function setBusy(productId, busy) {
        var key = String(productId);
        var row = rows[key];
        if (row) {
            row.classList.toggle('is-busy', busy);
            var dec = row.querySelector('[data-qty-dec]');
            var inc = row.querySelector('[data-qty-inc]');
            var rem = row.querySelector('[data-mini-remove]');
            if (busy) {
                if (dec) dec.disabled = true;
                if (inc) inc.disabled = true;
                if (rem) rem.disabled = true;
            } else if (global.QuantityStepper) {
                global.QuantityStepper.sync(row);
                if (rem) rem.disabled = false;
            }
        }
        if (busy) {
            pending[key] = true;
        } else {
            delete pending[key];
        }
    }

    const mutateQuantity = async (productId, quantity) => {
        const key = String(productId);
        if (pending[key]) {
            return;
        }
        setBusy(productId, true);

        try {
            const form = new FormData();
            form.append('product_id', key);
            form.append('quantity', String(quantity));

            const response = await fetch('/cart/update', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: form
            });
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            const json = await response.json();
            if (json && json.success) {
                cartCache = json.data;
                render(cartCache);
            } else if (json && json.message) {
                announce(json.message);
            }
        } catch (error) {
            console.error(error);
            announce('No se pudo actualizar el carrito.');
        } finally {
            setBusy(productId, false);
        }
    };

    const removeProduct = async (productId) => {
        const key = String(productId);
        if (pending[key]) {
            return;
        }
        setBusy(productId, true);

        try {
            const response = await fetch('/cart/remove/' + productId, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': window.CSRF_TOKEN || ''
                }
            });
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            const json = await response.json();
            if (json && json.success) {
                cartCache = json.data;
                render(cartCache);
            } else if (json && json.message) {
                announce(json.message);
            }
        } catch (error) {
            console.error(error);
            announce('No se pudo eliminar el producto.');
        } finally {
            setBusy(productId, false);
        }
    };

    // ── Add-to-cart AJAX (sin redirección, abre el Mini Cart) ──

    const addToCart = async (form) => {
        const data = new FormData(form);
        const submitBtn = form.querySelector('[type="submit"]');
        const wasDisabled = submitBtn ? submitBtn.disabled : false;

        if (submitBtn && !wasDisabled) {
            submitBtn.disabled = true;
        }

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: data
            });
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            const json = await response.json();
            if (json && json.success) {
                cartCache = json.data;
                render(cartCache);
                announce('Producto agregado al carrito.');
                openedFrom = submitBtn || form;
                offcanvas.show();
            } else if (json && json.message) {
                announce(json.message);
            }
        } catch (error) {
            console.error(error);
            announce('No se pudo agregar el producto al carrito.');
        } finally {
            if (submitBtn && !wasDisabled) {
                submitBtn.disabled = false;
            }
        }
    };

    // ── Eventos ──

    document.addEventListener('click', function (e) {
        var link = e.target.closest(CART_LINK_SELECTOR);
        if (!link) {
            return;
        }
        e.preventDefault();
        openedFrom = link;
        offcanvas.show();
    });

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (typeof form.matches !== 'function' || !form.matches(ADD_FORM_SELECTOR)) {
            return;
        }
        e.preventDefault();
        addToCart(form);
    });

    itemsEl.addEventListener('click', function (e) {
        var rem = e.target.closest('[data-mini-remove]');
        if (!rem) {
            return;
        }
        e.preventDefault();

        var row = e.target.closest('.mini-cart-item');
        if (!row) {
            return;
        }
        removeProduct(row.dataset.productId);
    });

    itemsEl.addEventListener('qtychange', function (e) {
        var row = e.target.closest('.mini-cart-item');
        if (!row) {
            return;
        }
        var value = e.detail && e.detail.value;
        if (!value) {
            return;
        }
        mutateQuantity(row.dataset.productId, value);
    });

    offcanvasEl.addEventListener('show.bs.offcanvas', function () {
        isOpen = true;
        applyPosition();
        load();
    });

    offcanvasEl.addEventListener('shown.bs.offcanvas', function () {
        if (closeBtnEl && closeBtnEl.focus) {
            closeBtnEl.focus();
        }
    });

    var resizeTimer = null;
    global.addEventListener('resize', function () {
        if (!isOpen) {
            return;
        }
        if (resizeTimer) {
            global.clearTimeout(resizeTimer);
        }
        resizeTimer = global.setTimeout(function () {
            applyPosition();
            positionBackdrop();
        }, 150);
    });

    offcanvasEl.addEventListener('hidden.bs.offcanvas', function () {
        isOpen = false;
        if (openedFrom && document.body.contains(openedFrom) && openedFrom.focus) {
            openedFrom.focus();
        }
        openedFrom = null;
    });
})(window);
