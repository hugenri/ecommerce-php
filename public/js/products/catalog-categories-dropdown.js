/* Catálogo: dropdown de categorías (drill-down) compartido entre el navbar
   desktop y la barra de navegación inferior móvil. Un solo panel en el DOM. */
document.addEventListener("DOMContentLoaded", () => {
  const panel = document.querySelector("[data-catalog-categories-panel]");
  const triggers = Array.from(document.querySelectorAll("[data-catalog-categories-toggle]"));
  if (!panel || triggers.length === 0) return;

  let activeTrigger = triggers[0];
  const rootView = panel.querySelector("[data-catalog-drill-root]");
  const subViews = panel.querySelectorAll("[data-catalog-drill-sub]");
  const drillTriggers = panel.querySelectorAll("[data-catalog-drill-trigger]");
  const isOpen = () => !panel.hidden;

  const showRoot = () => {
    if (rootView) rootView.hidden = false;
    subViews.forEach((sub) => {
      sub.hidden = true;
    });
    drillTriggers.forEach((btn) => {
      btn.setAttribute("aria-expanded", "false");
    });
  };

  const setPosition = () => {
    const gap = 8;
    const rect = activeTrigger.getBoundingClientRect();
    const anchorBottom = activeTrigger.dataset.catalogCategoriesAnchor === "bottom";
    const viewportWidth = window.innerWidth;
    const panelWidth = panel.offsetWidth;
    const maxLeft = Math.max(gap, viewportWidth - panelWidth - gap);

    panel.classList.toggle("catalog-categories-panel--anchor-bottom", anchorBottom);
    panel.style.left = String(Math.min(Math.max(rect.left, gap), maxLeft)) + "px";

    if (anchorBottom) {
      panel.style.top = "auto";
      panel.style.bottom = String(Math.round(window.innerHeight - rect.top) + gap) + "px";
    } else {
      panel.style.bottom = "auto";
      panel.style.top = String(Math.round(rect.bottom) + gap) + "px";
    }
  };

  const setOpen = (open) => {
    panel.hidden = !open;
    triggers.forEach((trigger) => {
      trigger.setAttribute("aria-expanded", String(open));
      const arrow = trigger.querySelector(".catalog-categories-arrow");
      arrow?.classList.toggle("bi-chevron-up", open);
      arrow?.classList.toggle("bi-chevron-down", !open);
    });
    if (open) {
      showRoot();
      setPosition();
    }
  };

  triggers.forEach((trigger) => {
    trigger.addEventListener("click", (event) => {
      event.preventDefault();
      activeTrigger = trigger;
      setOpen(!isOpen());
    });
  });

  panel.querySelectorAll("a").forEach((link) => {
    link.addEventListener("click", () => setOpen(false));
  });

  drillTriggers.forEach((btn) => {
    btn.addEventListener("click", () => {
      const target = panel.querySelector(
        '[data-catalog-drill-sub="' + btn.dataset.catalogDrillTrigger + '"]'
      );
      if (!target) return;
      if (rootView) rootView.hidden = true;
      subViews.forEach((view) => {
        view.hidden = view !== target;
      });
      btn.setAttribute("aria-expanded", "true");
    });
  });

  panel.querySelectorAll("[data-catalog-drill-back]").forEach((backBtn) => {
    backBtn.addEventListener("click", () => {
      showRoot();
    });
  });

  document.addEventListener("click", (event) => {
    if (!isOpen()) return;
    const insideTrigger = triggers.some(
      (trigger) => trigger === event.target || trigger.contains(event.target)
    );
    if (!panel.contains(event.target) && !insideTrigger) {
      setOpen(false);
    }
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && isOpen()) {
      setOpen(false);
      activeTrigger.focus();
    }
  });
});