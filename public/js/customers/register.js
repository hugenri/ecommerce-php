document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("registerForm");
  const btnRegister = document.getElementById("btnRegister");
  const alertBox = document.getElementById("alertBox");

  const clearValidationErrors = (form) => {
    form.querySelectorAll('[aria-invalid="true"]').forEach((input) => {
      window.FormValidation.limpiarError(input);
    });
  };

  const showValidationErrors = (form, errors) => {
    Object.entries(errors).forEach(([field, messages]) => {
      const input = form.elements[field];
      if (!input || !Array.isArray(messages) || messages.length === 0) return;
      window.FormValidation.marcarError(input, messages[0]);
    });
  };

  const isValidationErrorsObject = (errors) =>
    typeof errors === "object" &&
    errors !== null &&
    !Array.isArray(errors) &&
    Object.keys(errors).length > 0;

  const showGeneralError = (json) => {
    const message =
      json.message ||
      (typeof json.errors === "string" ? json.errors : null) ||
      "Error desconocido";

    alertBox.textContent = message;
    alertBox.className = "form-alert form-alert-error";
    alertBox.hidden = false;
  };

  form.addEventListener("submit", async (e) => {
    e.preventDefault();

    if (!window.FormValidation.formularioEsValido(form)) {
      form.querySelectorAll("input:not([type='hidden']), select, textarea").forEach((input) => {
        if (!input.checkValidity()) {
          window.FormValidation.marcarError(input);
        }
      });
      return;
    }

    alertBox.hidden = true;
    alertBox.textContent = "";
    clearValidationErrors(form);

    btnRegister.disabled = true;
    btnRegister.textContent = "Creando cuenta...";

    const data = {
      first_name: form.first_name.value.trim(),
      last_name_paternal: form.last_name_paternal.value.trim(),
      last_name_maternal: form.last_name_maternal.value.trim(),
      email: form.email.value.trim(),
      phone: form.phone.value.trim(),
      password: form.password.value,
    };

    try {
      const res = await fetch("/register", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data),
      });

      let json;
      try {
        json = await res.json();
      } catch (err) {
        alertBox.textContent = "Ocurrió un error inesperado.";
        alertBox.className = "form-alert form-alert-error";
        alertBox.hidden = false;
        return;
      }

      if (!res.ok) {
        if (isValidationErrorsObject(json.errors)) {
          showValidationErrors(form, json.errors);
        } else {
          showGeneralError(json);
        }
        return;
      }

      alertBox.textContent = json.message || "Tu cuenta fue creada correctamente.";
      alertBox.className = "form-alert form-alert-success";
      alertBox.hidden = false;
      if (json.data?.redirect) {
        window.location.href = json.data.redirect;
        return;
      }
    } catch (err) {
      alertBox.textContent = "No fue posible conectar con el servidor.";
      alertBox.className = "form-alert form-alert-error";
      alertBox.hidden = false;
    } finally {
      btnRegister.disabled = false;
      btnRegister.textContent = "Crear cuenta";
    }
  });
});