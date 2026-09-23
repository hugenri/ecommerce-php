/* Menú kebab genérico (singleton). Sin conocimiento de dominio.
 *
 * Uso:
 *   ActionMenu.open(triggerEl, items, { id, name, onSelect })
 *   ActionMenu.hide()
 *
 * items: Array de acciones { action, icon, label, className }
 *        o separadores { divider: true }.
 * options.id / options.name: contexto de la fila (obligatorio en la práctica);
 *        el módulo lo inyecta como data-id/data-name en TODOS los ítems.
 * options.onSelect(item): callback opcional; item = { action, id, name }. Si se
 *        omite, el click sobre un ítem burbujea libremente (delegación externa).
 *
 * Requisitos del trigger: atributo data-action-menu-trigger. El módulo ignora
 * los clicks sobre triggers para que el toggle (abrir/cerrar en re-click)
 * lo resuelva ActionMenu.open() y no el cierre por click-fuera.
 */
const ActionMenu = (() => {
    let menuEl = null;
    let currentTrigger = null;
    let onSelect = null;

    const isOpen = () => Boolean(menuEl) && !menuEl.classList.contains('d-none');

    const esc = (value) => {
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(String(value ?? '')));
        return div.innerHTML;
    };

    const ensureMenu = () => {
        if (!menuEl) {
            menuEl = document.createElement('ul');
            menuEl.className = 'action-menu d-none';
            menuEl.setAttribute('role', 'menu');
            document.body.appendChild(menuEl);
        }
        return menuEl;
    };

    const buildItems = (items, context) => items.map((item) => {
        if (item.divider) {
            return '<li><hr class="dropdown-divider"></li>';
        }
        const idAttr = context.id !== undefined ? ` data-id="${esc(context.id)}"` : '';
        const nameAttr = context.name !== undefined ? ` data-name="${esc(context.name)}"` : '';
        return `<li><button type="button" class="action-menu-item ${item.className || ''}"`
            + ` data-action="${esc(item.action)}"${idAttr}${nameAttr}>`
            + `<i class="bi ${esc(item.icon)}"></i><span>${esc(item.label)}</span></button></li>`;
    }).join('');

    const position = (trigger) => {
        const rect = trigger.getBoundingClientRect();
        menuEl.classList.remove('d-none');
        menuEl.style.visibility = 'hidden';
        const width = menuEl.offsetWidth;
        const height = menuEl.offsetHeight;
        let top = rect.bottom + 6;
        if (top + height > window.innerHeight - 8) {
            top = Math.max(8, rect.top - height - 6);
        }
        let left = rect.right - width;
        left = Math.max(8, Math.min(left, window.innerWidth - width - 8));
        menuEl.style.top = `${top}px`;
        menuEl.style.left = `${left}px`;
        menuEl.style.visibility = '';
    };

    const hide = () => {
        if (!menuEl || !isOpen()) return;
        menuEl.classList.add('d-none');
        if (currentTrigger) {
            currentTrigger.setAttribute('aria-expanded', 'false');
        }
        currentTrigger = null;
        onSelect = null;
    };

    const open = (trigger, items, options) => {
        if (isOpen() && currentTrigger === trigger) {
            hide();
            return;
        }
        if (!Array.isArray(items) || items.length === 0) return;
        const opts = options || {};
        const el = ensureMenu();
        el.innerHTML = buildItems(items, opts);
        if (currentTrigger) currentTrigger.setAttribute('aria-expanded', 'false');
        currentTrigger = trigger;
        trigger.setAttribute('aria-expanded', 'true');
        onSelect = typeof opts.onSelect === 'function' ? opts.onSelect : null;
        position(trigger);
    };

    document.addEventListener('click', (e) => {
        if (!isOpen()) return;
        if (e.target.closest('[data-action-menu-trigger]')) return;
        if (menuEl.contains(e.target)) {
            const itemBtn = e.target.closest('.action-menu-item');
            if (itemBtn) {
                const selectFn = onSelect;
                hide();
                if (selectFn) {
                    selectFn({
                        action: itemBtn.dataset.action,
                        id: itemBtn.dataset.id,
                        name: itemBtn.dataset.name,
                    });
                }
            }
            return;
        }
        hide();
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && isOpen()) hide();
    });

    window.addEventListener('scroll', () => {
        if (isOpen()) hide();
    }, true);

    window.addEventListener('resize', () => {
        if (isOpen()) hide();
    });

    return { open, hide };
})();
