document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("loginForm");
  const btnLogin = document.getElementById("btnLogin");
  const alertBox = document.getElementById("alertBox");

  const clearValidationErrors = (form) => {
    form.querySelectorAll("input:not([type='hidden']), select, textarea").forEach((input) => {
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

    btnLogin.disabled = true;
    btnLogin.textContent = "Entrando...";

    const data = {
      email: form.email.value.trim(),
      password: form.password.value,
    };

    try {
      const res = await fetch("/access/login", {
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

      alertBox.textContent = json.message || "Login exitoso";
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
      btnLogin.disabled = false;
      btnLogin.textContent = "Login";
    }
  });

  const togglePassword = document.getElementById("togglePassword");
  if (!togglePassword) return;
  togglePassword.addEventListener("click", function () {
    const input = document.getElementById("password");
    const icon = this.querySelector("i");
    const isPassword = input.type === "password";
    input.type = isPassword ? "text" : "password";
    icon.className = isPassword ? "bi bi-eye-slash" : "bi bi-eye";
    this.title = isPassword ? "Ocultar contraseña" : "Mostrar contraseña";
    this.setAttribute("aria-label", this.title);
  });
});
