/**
 * Sincroniza el estado aria-expanded del botón hamburguesa del Topbar
 * con el Offcanvas responsive del menú lateral (#sidebarMenu).
 *
 * Bootstrap gestiona la apertura/cierre, el backdrop, ESC, el clic fuera
 * y el foco; aquí solo se mantiene el atributo aria-expanded al día con
 * los dos eventos que lo determinan (show / hidden). No agrega listeners
 * adicionales.
 */
(function (root) {
    'use strict';

    var trigger = root.document.querySelector('[data-bs-toggle="offcanvas"][data-bs-target="#sidebarMenu"]');
    var offcanvas = root.document.getElementById('sidebarMenu');

    if (!trigger || !offcanvas || typeof root.bootstrap === 'undefined') {
        return;
    }

    function setExpanded(expanded) {
        trigger.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    }

    offcanvas.addEventListener('show.bs.offcanvas', function () {
        setExpanded(true);
    });

    offcanvas.addEventListener('hidden.bs.offcanvas', function () {
        setExpanded(false);
    });

    // Breakpoint lg de Bootstrap (992px).
    var LG_BREAKPOINT = 992;
    var previousWidth = root.innerWidth;

    // Si el menú está abierto y la ventana pasa de < lg a >= lg, el Offcanvas
    // responsive (offcanvas-lg) queda estático pero conserva su backdrop;
    // se cierra para limpiarlo (en escritorio el sidebar se muestra siempre).
    root.addEventListener('resize', function () {
        var width = root.innerWidth;
        var crossedToDesktop = previousWidth < LG_BREAKPOINT && width >= LG_BREAKPOINT;
        previousWidth = width;

        if (crossedToDesktop && offcanvas.classList.contains('show')) {
            root.bootstrap.Offcanvas.getOrCreateInstance(offcanvas).hide();
        }
    });
}(typeof window !== 'undefined' ? window : this));
