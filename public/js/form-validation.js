(function () {
  function obtenerFeedback(input) {
    const descritoPor = input.getAttribute('aria-describedby');
    if (!descritoPor) return null;

    for (const id of descritoPor.split(/\s+/)) {
      const elemento = document.getElementById(id);
      if (elemento && elemento.classList.contains('field-feedback')) {
        return elemento;
      }
    }

    return null;
  }

  function marcarError(input, mensaje) {
    input.setAttribute('aria-invalid', 'true');

    const feedback = obtenerFeedback(input);
    if (!feedback) return;

    feedback.textContent = mensaje || feedback.dataset.mensaje || input.validationMessage || '';
    feedback.hidden = false;
  }

  function limpiarError(input) {
    input.removeAttribute('aria-invalid');

    const feedback = obtenerFeedback(input);
    if (!feedback) return;

    feedback.textContent = '';
    feedback.hidden = true;
  }

  function formularioEsValido(formulario) {
    const inputs = formulario.querySelectorAll(
      'input:not([type="hidden"]), select, textarea'
    );

    let todosValidos = true;

    inputs.forEach((input) => {
      if (input.disabled) return;

      if (input.required && !input.value) {
        todosValidos = false;
        return;
      }

      if (input.value && !input.checkValidity()) {
        todosValidos = false;
      }
    });

    return todosValidos;
  }

  function actualizarBoton(formulario) {
    const boton = formulario.querySelector('button[type="submit"]');
    if (!boton) return;

    boton.disabled = !formularioEsValido(formulario);
  }

  document.querySelectorAll('form[data-validate]').forEach((formulario) => {
    // Estado inicial: botón deshabilitado si el form arranca inválido
    actualizarBoton(formulario);

    // Validación al salir del campo
    formulario.addEventListener('blur', (event) => {
      const input = event.target;
      if (!input.matches('input, select, textarea')) return;

      if (!input.value.trim()) {
        limpiarError(input);
      } else if (input.checkValidity()) {
        limpiarError(input);
      } else {
        marcarError(input);
      }

      actualizarBoton(formulario);
    }, true);

    // Tiempo real mientras escribe (no solo al salir del campo)
    formulario.addEventListener('input', (event) => {
      const input = event.target;
      if (!input.matches('input, select, textarea')) return;

      if (input.value.trim() && input.checkValidity()) {
        limpiarError(input);
      }

      actualizarBoton(formulario);
    });

    formulario.addEventListener('submit', (event) => {
      if (!formularioEsValido(formulario)) {
        event.preventDefault();
        event.stopPropagation();
      }
    });
  });

  function limpiarErrores(formulario) {
    formulario.querySelectorAll('[aria-invalid="true"]').forEach((input) => {
      limpiarError(input);
    });
  }

  function mostrarErrores(formulario, errors) {
    if (!errors || typeof errors !== 'object' || Array.isArray(errors)) {
      return;
    }

    Object.entries(errors).forEach(([field, mensajes]) => {
      const input = formulario.elements[field];
      if (!input || !Array.isArray(mensajes) || mensajes.length === 0) return;
      if (input.disabled || input.type === 'hidden') return;

      marcarError(input, mensajes[0]);
    });
  }

  window.FormValidation = {
    marcarError,
    limpiarError,
    formularioEsValido,
    limpiarErrores,
    mostrarErrores,
  };
})();