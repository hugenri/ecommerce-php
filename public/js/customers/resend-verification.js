document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll("form[data-resend-verification]").forEach((form) => {
    const alertBox = document.getElementById(form.dataset.alert);
    const btnSubmit = form.querySelector("button[type='submit']");
    if (!alertBox || !btnSubmit) return;

    const showMessage = (message, type) => {
      alertBox.textContent = message;
      alertBox.className = "form-alert form-alert-" + type;
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
      btnSubmit.disabled = true;

      try {
        const res = await fetch("/customer/resend-verification", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ email: form.email.value.trim() }),
        });

        let json;
        try {
          json = await res.json();
        } catch (err) {
          showMessage("Ocurrió un error inesperado.", "error");
          return;
        }

        if (!res.ok) {
          const errors = json.errors;
          if (
            errors &&
            typeof errors === "object" &&
            !Array.isArray(errors) &&
            Object.keys(errors).length > 0 &&
            errors.email
          ) {
            window.FormValidation.marcarError(form.elements.email, errors.email[0]);
          } else {
            showMessage(json.message || "Error desconocido", "error");
          }
          return;
        }

        if (json.data?.redirect) {
          window.location.href = json.data.redirect;
          return;
        }

        showMessage(json.message || "Correo enviado.", "success");
      } catch (err) {
        showMessage("No fue posible conectar con el servidor.", "error");
      } finally {
        btnSubmit.disabled = false;
      }
    });
  });
});