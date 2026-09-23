const FilterBar = (() => {
    const openOffcanvas = (offcanvasId) => {
        const element = document.getElementById(offcanvasId);
        if (!element) return null;
        return bootstrap.Offcanvas.getOrCreateInstance(element);
    };

    const closeOffcanvas = (offcanvasId) => {
        const element = document.getElementById(offcanvasId);
        if (!element) return;
        const instance = bootstrap.Offcanvas.getInstance(element);
        if (instance) instance.hide();
    };

    const buildChips = (filters, config) => {
        const chips = [];
        Object.keys(config).forEach((key) => {
            const value = filters[key];
            if (value === undefined || value === null || value === '') return;
            const def = config[key];
            let text = value;
            const input = document.getElementById(def.inputId);
            if (input && input.tagName === 'SELECT') {
                const option = input.options[input.selectedIndex];
                if (option) text = option.text;
            }
            chips.push({ key, label: def.label, text, inputId: def.inputId });
        });
        return chips;
    };

    const renderChips = ({ chipsId, badgeId, chips = [], onRemove = () => {} }) => {
        const chipsContainer = document.getElementById(chipsId);
        const badge = document.getElementById(badgeId);

        if (badge) {
            badge.textContent = chips.length;
            badge.classList.toggle('d-none', chips.length === 0);
        }

        if (!chipsContainer) return;

        chipsContainer.innerHTML = '';
        if (chips.length === 0) {
            chipsContainer.classList.add('d-none');
            return;
        }

        chipsContainer.classList.remove('d-none');

        chips.forEach((chip) => {
            const wrapper = document.createElement('span');
            wrapper.className = 'badge text-bg-light border border-secondary-subtle rounded-pill d-inline-flex align-items-center gap-1 px-2 py-1 me-2 mb-1';
            wrapper.innerHTML = chip.label + ': <span class="fw-normal">' + chip.text + '</span>';
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn-close btn-close-sm ms-1';
            button.style.fontSize = '0.6rem';
            button.setAttribute('aria-label', 'Quitar filtro ' + chip.label);
            button.addEventListener('click', () => {
                const input = document.getElementById(chip.inputId);
                if (input) input.value = '';
                onRemove(chip.key);
            });
            wrapper.appendChild(button);
            chipsContainer.appendChild(wrapper);
        });
    };

    const clearChips = (chipsId, badgeId) => {
        const chipsContainer = document.getElementById(chipsId);
        if (chipsContainer) {
            chipsContainer.innerHTML = '';
            chipsContainer.classList.add('d-none');
        }
        const badge = document.getElementById(badgeId);
        if (badge) badge.classList.add('d-none');
    };

    return {
        openOffcanvas,
        closeOffcanvas,
        buildChips,
        renderChips,
        clearChips,
    };
})();