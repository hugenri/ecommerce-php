async function editAddress(id) {
  const body = document.getElementById("editAddressBody");
  const form = document.getElementById("editAddressForm");
  const alertBox = document.getElementById("editAddressAlert");

  const clearErrors = () => {
    if (form) {
      form.querySelectorAll('[aria-invalid="true"]').forEach((input) => {
        window.FormValidation.limpiarError(input);
      });
    }
    alertBox.hidden = true;
    alertBox.textContent = "";
  };

  try {
    const response = await fetch("/account/address/edit/" + id);
    const html = await response.text();
    clearErrors();
    body.innerHTML = html;
    form.action = "/account/address/edit/" + id;
    const modal = new bootstrap.Modal(document.getElementById("editAddressModal"));
    modal.show();
  } catch (error) {
    alertBox.textContent = "No fue posible cargar la dirección.";
    alertBox.className = "form-alert form-alert-error";
    alertBox.hidden = false;
  }
}

document.addEventListener("DOMContentLoaded", () => {
  const buildPayload = (form) => {
    const payload = {
      street: form.street.value.trim(),
      number: form.number.value.trim(),
      neighborhood: form.neighborhood.value.trim(),
      municipality: form.municipality.value.trim(),
      state: form.state.value.trim(),
      zip_code: form.zip_code.value.trim(),
      reference: form.reference.value.trim(),
      alias: form.alias.value.trim(),
    };

    if (form.is_default.checked) {
      payload.is_default = form.is_default.value;
    }

    return payload;
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

  const showGeneralError = (alertBox, json) => {
    const message =
      json.message ||
      (typeof json.errors === "string" ? json.errors : null) ||
      "Error desconocido";

    alertBox.textContent = message;
    alertBox.className = "form-alert form-alert-error";
    alertBox.hidden = false;
  };

  const markInvalid = (form) => {
    form.querySelectorAll("input:not([type='hidden']), select, textarea").forEach((input) => {
      if (!input.checkValidity()) {
        window.FormValidation.marcarError(input);
      }
    });
  };

  const bindAddressForm = (form, alertBox) => {
    const button = form.querySelector("button[type='submit']");

    form.addEventListener("submit", async (e) => {
      e.preventDefault();

      if (!window.FormValidation.formularioEsValido(form)) {
        markInvalid(form);
        return;
      }

      alertBox.hidden = true;
      alertBox.textContent = "";
      form.querySelectorAll('[aria-invalid="true"]').forEach((input) => {
        window.FormValidation.limpiarError(input);
      });

      const endpoint = form.getAttribute("action") || "/account/address/new";
      const originalLabel = button.innerHTML;
      button.disabled = true;
      button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Guardando...';

      try {
        const res = await fetch(endpoint, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(buildPayload(form)),
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
            showGeneralError(alertBox, json);
          }
          return;
        }

        if (json.data?.redirect) {
          window.location.href = json.data.redirect;
          return;
        }
      } catch (err) {
        alertBox.textContent = "No fue posible conectar con el servidor.";
        alertBox.className = "form-alert form-alert-error";
        alertBox.hidden = false;
      } finally {
        button.disabled = false;
        button.innerHTML = originalLabel;
      }
    });
  };

  const newForm = document.getElementById("newAddressForm");
  const newAlertBox = document.getElementById("newAddressAlert");
  if (newForm && newAlertBox) {
    bindAddressForm(newForm, newAlertBox);
  }

  const editForm = document.getElementById("editAddressForm");
  const editAlertBox = document.getElementById("editAddressAlert");
  if (editForm && editAlertBox) {
    bindAddressForm(editForm, editAlertBox);
  }
});