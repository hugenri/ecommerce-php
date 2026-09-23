document.addEventListener("DOMContentLoaded", () => {
  const nameForm = document.getElementById("nameForm");
  const phoneForm = document.getElementById("phoneForm");
  if (!nameForm || !phoneForm) return;

  const nameModal = new bootstrap.Modal(document.getElementById("nameModal"));
  const phoneModal = new bootstrap.Modal(document.getElementById("phoneModal"));
  const profileAlert = document.getElementById("profileAlert");

  const btnSaveName = document.querySelector('button[form="nameForm"]');
  const btnSavePhone = document.querySelector('button[form="phoneForm"]');
  const originalNameLabel = btnSaveName ? btnSaveName.innerHTML : "";
  const originalPhoneLabel = btnSavePhone ? btnSavePhone.innerHTML : "";

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

  const showGeneralError = (alertBox, json) => {
    const message =
      json.message ||
      (typeof json.errors === "string" ? json.errors : null) ||
      "Error desconocido";

    alertBox.textContent = message;
    alertBox.className = "form-alert form-alert-error";
    alertBox.hidden = false;
  };

  const showUnexpectedError = (alertBox) => {
    alertBox.textContent = "Ocurrió un error inesperado.";
    alertBox.className = "form-alert form-alert-error";
    alertBox.hidden = false;
  };

  const setLoading = (btn, loading, originalLabel) => {
    if (!btn) return;
    if (loading) {
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Guardando...';
    } else {
      btn.disabled = false;
      btn.innerHTML = originalLabel;
    }
  };

  const handleResponse = async (res, form, alertBox) => {
    let json;
    try {
      json = await res.json();
    } catch (err) {
      showUnexpectedError(alertBox);
      return null;
    }

    if (!res.ok) {
      if (isValidationErrorsObject(json.errors)) {
        showValidationErrors(form, json.errors);
      } else {
        showGeneralError(alertBox, json);
      }
      return null;
    }

    return json;
  };

  const showSuccess = (message) => {
    profileAlert.textContent = message;
    profileAlert.className = "form-alert form-alert-success";
    profileAlert.hidden = false;
  };

  nameForm.addEventListener("submit", async (e) => {
    e.preventDefault();

    if (!window.FormValidation.formularioEsValido(nameForm)) {
      nameForm.querySelectorAll("input:not([type='hidden']), select, textarea").forEach((input) => {
        if (!input.checkValidity()) {
          window.FormValidation.marcarError(input);
        }
      });
      return;
    }

    const alertBox = document.getElementById("nameFormAlert");
    alertBox.hidden = true;
    alertBox.textContent = "";
    clearValidationErrors(nameForm);

    setLoading(btnSaveName, true, originalNameLabel);

    const data = {
      first_name: nameForm.first_name.value.trim(),
      last_name_paternal: nameForm.last_name_paternal.value.trim(),
      last_name_maternal: nameForm.last_name_maternal.value.trim(),
    };

    try {
      const res = await fetch("/account/profile/name", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data),
      });

      const json = await handleResponse(res, nameForm, alertBox);
      if (!json) return;

      const full = [json.data.first_name, json.data.last_name_paternal, json.data.last_name_maternal]
        .filter(Boolean)
        .join(" ");
      document.getElementById("displayFullName").textContent = full;
      nameModal.hide();
      showSuccess(json.message || "Nombre actualizado correctamente.");
    } catch (err) {
      alertBox.textContent = "No fue posible conectar con el servidor.";
      alertBox.className = "form-alert form-alert-error";
      alertBox.hidden = false;
    } finally {
      setLoading(btnSaveName, false, originalNameLabel);
    }
  });

  phoneForm.addEventListener("submit", async (e) => {
    e.preventDefault();

    if (!window.FormValidation.formularioEsValido(phoneForm)) {
      phoneForm.querySelectorAll("input:not([type='hidden']), select, textarea").forEach((input) => {
        if (!input.checkValidity()) {
          window.FormValidation.marcarError(input);
        }
      });
      return;
    }

    const alertBox = document.getElementById("phoneFormAlert");
    alertBox.hidden = true;
    alertBox.textContent = "";
    clearValidationErrors(phoneForm);

    setLoading(btnSavePhone, true, originalPhoneLabel);

    const data = {
      phone: phoneForm.phone.value.trim(),
    };

    try {
      const res = await fetch("/account/profile/phone", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data),
      });

      const json = await handleResponse(res, phoneForm, alertBox);
      if (!json) return;

      document.getElementById("displayPhone").textContent = json.data.phone || "—";
      phoneModal.hide();
      showSuccess(json.message || "Teléfono actualizado correctamente.");
    } catch (err) {
      alertBox.textContent = "No fue posible conectar con el servidor.";
      alertBox.className = "form-alert form-alert-error";
      alertBox.hidden = false;
    } finally {
      setLoading(btnSavePhone, false, originalPhoneLabel);
    }
  });

  ["nameModal", "phoneModal"].forEach((id) => {
    document.getElementById(id).addEventListener("hidden.bs.modal", () => {
      const formId = id === "nameModal" ? "nameForm" : "phoneForm";
      clearValidationErrors(document.getElementById(formId));
      const alertBox = document.getElementById(id === "nameModal" ? "nameFormAlert" : "phoneFormAlert");
      alertBox.hidden = true;
      alertBox.textContent = "";
    });
  });
});