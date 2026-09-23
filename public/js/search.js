/**
 * Buscador del header (interfaz pública).
 *
 * - Debounce de peticiones de sugerencias (300 ms).
 * - AbortController para descartar respuestas obsoletas.
 * - El dropdown se construye con createElement/textContent (XSS-safe).
 * - Seleccionar una sugerencia navega al catálogo (/shop?search=...) usando el nombre como término.
 * - El formulario navega a /shop?search=... (Enter y botón de lupa).
 */
(function (global) {
    'use strict';

    var MIN_LENGTH = 2;
    var DEBOUNCE_MS = 300;
    var SUGGESTIONS_URL = '/search/suggestions';
    var CATALOG_SEARCH_URL = '/shop?search=';
    var FORM_SELECTOR = '[data-search-form]';
    var INPUT_SELECTOR = '[data-search-input]';
    var DROPDOWN_SELECTOR = '[data-search-dropdown]';

    var form = document.querySelector(FORM_SELECTOR);
    if (!form) {
        return;
    }

    var input = form.querySelector(INPUT_SELECTOR);
    var dropdown = form.querySelector(DROPDOWN_SELECTOR);
    if (!input || !dropdown) {
        return;
    }

    var debounceTimer = null;
    var controller = null;

    function clearDropdown() {
        dropdown.hidden = true;
        dropdown.textContent = '';
    }

    function showStatus(message) {
        dropdown.textContent = '';
        var status = document.createElement('div');
        status.className = 'search-status';
        status.textContent = message;
        dropdown.appendChild(status);
        dropdown.hidden = false;
    }

    function showLoading() {
        dropdown.textContent = '';
        var status = document.createElement('div');
        status.className = 'search-status';
        var spinner = document.createElement('span');
        spinner.className = 'spinner-border spinner-border-sm';
        spinner.setAttribute('role', 'status');
        spinner.setAttribute('aria-hidden', 'true');
        status.appendChild(spinner);
        status.appendChild(document.createTextNode(' Buscando...'));
        dropdown.appendChild(status);
        dropdown.hidden = false;
    }

    function renderSuggestions(items) {
        dropdown.textContent = '';
        if (!items.length) {
            showStatus('No encontramos productos.');
            return;
        }
        var fragment = document.createDocumentFragment();
        items.forEach(function (item) {
            var link = document.createElement('a');
            link.href = CATALOG_SEARCH_URL + encodeURIComponent(item.name);
            link.className = 'search-suggestion';

            var icon = document.createElement('i');
            icon.className = 'bi bi-search';
            icon.setAttribute('aria-hidden', 'true');

            var name = document.createElement('span');
            name.className = 'search-suggestion-name';
            name.textContent = item.name;

            link.appendChild(icon);
            link.appendChild(name);
            fragment.appendChild(link);
        });
        dropdown.appendChild(fragment);
        dropdown.hidden = false;
    }

    const fetchSuggestions = async (term) => {
        if (controller) {
            controller.abort();
        }
        controller = new AbortController();
        showLoading();

        try {
            const response = await fetch(SUGGESTIONS_URL + '?q=' + encodeURIComponent(term), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal: controller.signal
            });
            const json = await response.json();
            if (!response.ok || !json || json.success !== true) {
                if (input.value.trim() === term) {
                    showStatus('No fue posible realizar la búsqueda.');
                }
                return;
            }
            if (input.value.trim() !== term) {
                return;
            }
            renderSuggestions(Array.isArray(json.data) ? json.data : []);
        } catch (error) {
            if (error && error.name === 'AbortError') {
                return;
            }
            if (input.value.trim() === term) {
                showStatus('No fue posible realizar la búsqueda.');
            }
        }
    };

    function scheduleSearch() {
        var term = input.value.trim();
        if (debounceTimer) {
            global.clearTimeout(debounceTimer);
        }
        if (term === '' || term.length < MIN_LENGTH) {
            if (controller) {
                controller.abort();
                controller = null;
            }
            clearDropdown();
            return;
        }
        debounceTimer = global.setTimeout(function () {
            fetchSuggestions(term);
        }, DEBOUNCE_MS);
    }

    input.addEventListener('input', scheduleSearch);
    input.addEventListener('focus', scheduleSearch);

    input.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            if (controller) {
                controller.abort();
                controller = null;
            }
            clearDropdown();
        }
    });

    document.addEventListener('click', function (event) {
        if (form.contains(event.target)) {
            return;
        }
        clearDropdown();
    });
})(window);
