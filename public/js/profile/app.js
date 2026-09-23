document.addEventListener('DOMContentLoaded', () => {
  const alertContainer = document.getElementById('alertContainer');

  const values = {
    name: document.getElementById('editName')?.value || '',
    email: document.getElementById('editEmail')?.value || '',
    phone: document.getElementById('editPhone')?.value || '',
  };

  const showAlert = (type, message) => {
    if (!alertContainer) return;
    const id = 'alert-' + Date.now();
    const icon = type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-circle' : 'info-circle';
    const html = `
      <div id="${id}" class="alert alert-${type} alert-dismissible fade show shadow-sm" role="alert">
        <i class="bi bi-${icon} me-2"></i>${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>`;
    alertContainer.insertAdjacentHTML('beforeend', html);
    setTimeout(() => {
      const el = document.getElementById(id);
      if (el) bootstrap.Alert.getOrCreateInstance(el).close();
    }, 5000);
  };

  const errorEl = (field) => document.getElementById('error' + field.charAt(0).toUpperCase() + field.slice(1));

  const clearFieldError = (field) => {
    const el = errorEl(field);
    if (el) { el.textContent = ''; el.classList.add('d-none'); }
  };

  const showFieldError = (field, message) => {
    const el = errorEl(field);
    if (el) { el.textContent = message; el.classList.remove('d-none'); }
  };

  const rowOf = (field) => document.querySelector(`.field-row[data-field="${field}"]`);

  // ---- Reglas de validación (una sola fuente de verdad, usada en blur y submit) ----

  const NAME_PATTERN = /^[A-Za-zÁÉÍÓÚÜáéíóúüÑñ ]+$/;
  const EMAIL_PATTERN = /^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/;
  const PHONE_PATTERN = /^\d{10}$/;
  const PASSWORD_PATTERN = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^\w\s]).{8,16}$/;

  function validateName(value) {
    if (value.length < 4 || value.length > 30) {
      return 'El nombre debe tener entre 4 y 30 caracteres.';
    }
    if (!NAME_PATTERN.test(value)) {
      return 'El nombre solo puede contener letras y espacios.';
    }
    return null;
  }

  function validateEmail(value) {
    if (!value) {
      return 'El correo electrónico es obligatorio.';
    }
    if (value.length > 255) {
      return 'El correo no debe superar 255 caracteres.';
    }
    if (!EMAIL_PATTERN.test(value)) {
      return 'Ingrese un correo electrónico válido.';
    }
    return null;
  }

  function validatePhone(value) {
    if (!value) {
      return null; // opcional
    }
    if (!PHONE_PATTERN.test(value)) {
      return 'El teléfono debe tener exactamente 10 dígitos numéricos.';
    }
    return null;
  }

  function validateNewPassword(value) {
    if (!value) {
      return 'La nueva contraseña es obligatoria.';
    }
    if (!PASSWORD_PATTERN.test(value)) {
      return 'La contraseña debe tener entre 8 y 16 caracteres, con mayúscula, minúscula, número y carácter especial.';
    }
    return null;
  }

  const FIELD_VALIDATORS = {
    name: validateName,
    email: validateEmail,
    phone: validatePhone,
  };

  // ---- Validación en blur + botón deshabilitado ----

  function actualizarBotonGuardar(field) {
    const row = rowOf(field);
    if (!row) return;
    const saveBtn = row.querySelector('[data-save]');
    const input = document.getElementById('edit' + field.charAt(0).toUpperCase() + field.slice(1));
    if (!saveBtn || !input) return;

    const validator = FIELD_VALIDATORS[field];
    const error = validator ? validator(input.value.trim()) : null;
    saveBtn.disabled = !!error;
  }

  ['name', 'email', 'phone'].forEach((field) => {
    const input = document.getElementById('edit' + field.charAt(0).toUpperCase() + field.slice(1));
    if (!input) return;

    input.addEventListener('blur', () => {
      const value = input.value.trim();
      const error = FIELD_VALIDATORS[field](value);
      if (error) {
        showFieldError(field, error);
      } else {
        clearFieldError(field);
      }
      actualizarBotonGuardar(field);
    });

    input.addEventListener('input', () => {
      // Limpia el error en cuanto se corrige, sin esperar a salir del campo
      const value = input.value.trim();
      if (!FIELD_VALIDATORS[field](value)) {
        clearFieldError(field);
      }
      actualizarBotonGuardar(field);
    });
  });

  // Password: mismo patrón, sobre los 3 campos del bloque de contraseña
  function actualizarBotonPassword() {
    const row = rowOf('password');
    if (!row) return;
    const saveBtn = row.querySelector('[data-save="password"]');
    const current = document.getElementById('editPwCurrent')?.value || '';
    const pwNew = document.getElementById('editPwNew')?.value || '';
    const pwConfirm = document.getElementById('editPwConfirm')?.value || '';

    const invalido =
      !current ||
      !!validateNewPassword(pwNew) ||
      pwNew !== pwConfirm;

    if (saveBtn) saveBtn.disabled = invalido;
  }

  ['editPwCurrent', 'editPwNew', 'editPwConfirm'].forEach((id) => {
    const input = document.getElementById(id);
    if (!input) return;
    input.addEventListener('blur', () => {
      if (id === 'editPwNew') {
        const error = validateNewPassword(input.value);
        if (error) showFieldError('password', error); else clearFieldError('password');
      }
      if (id === 'editPwConfirm') {
        const pwNew = document.getElementById('editPwNew')?.value || '';
        if (input.value && input.value !== pwNew) {
          showFieldError('password', 'Las contraseñas no coinciden.');
        } else {
          clearFieldError('password');
        }
      }
      actualizarBotonPassword();
    });
    input.addEventListener('input', actualizarBotonPassword);
  });

  const closeAllEdits = () => {
    document.querySelectorAll('.field-row').forEach(row => {
      const read = row.querySelector('.read-state');
      const edit = row.querySelector('.edit-state');
      if (read) read.classList.remove('d-none');
      if (edit) edit.classList.add('d-none');
    });
    ['name', 'email', 'phone', 'password'].forEach(clearFieldError);
  };

  const openEdit = (field) => {
    closeAllEdits();
    const row = rowOf(field);
    if (!row) return;
    const read = row.querySelector('.read-state');
    const edit = row.querySelector('.edit-state');
    if (read) read.classList.add('d-none');
    if (edit) {
      edit.classList.remove('d-none');
      const input = edit.querySelector('input[type="text"], input[type="email"]');
      if (input) input.focus();
    }
    // Estado inicial del botón al abrir edición
    if (field === 'password') {
      actualizarBotonPassword();
    } else {
      actualizarBotonGuardar(field);
    }
  };

  const readFirstError = (errors) => {
    const flat = Object.values(errors || {}).flat();
    return flat.find(Boolean) || '';
  };

  const saveProfileField = async (field) => {
    clearFieldError(field);

    const input = document.getElementById('edit' + field.charAt(0).toUpperCase() + field.slice(1));
    const value = input ? input.value.trim() : '';

    const error = FIELD_VALIDATORS[field](value);
    if (error) {
      showFieldError(field, error);
      return;
    }

    const data = { ...values };
    data[field] = value;

    try {
      const { ok, json } = await ProfileAPI.updateProfile(data);
      if (ok && json.success) {
        values.name = json.data?.user?.name ?? values.name;
        values.email = json.data?.user?.email ?? values.email;
        values.phone = json.data?.user?.phone ?? values.phone;

        const valueName = document.getElementById('valueName');
        const valueEmail = document.getElementById('valueEmail');
        const valuePhone = document.getElementById('valuePhone');
        if (valueName) valueName.textContent = values.name;
        if (valueEmail) valueEmail.textContent = values.email;
        if (valuePhone) {
          valuePhone.innerHTML = values.phone
            ? values.phone.replace(/[<>&]/g, '')
            : '<span class="text-muted fw-normal">No registrado</span>';
        }

        closeAllEdits();
        showAlert('success', json.message);
      } else if (json.errors) {
        showFieldError(field, readFirstError(json.errors));
      } else {
        showAlert('danger', json.message || 'Error al guardar el perfil.');
      }
    } catch (e) {
      showAlert('danger', 'Error de conexión con el servidor.');
    }
  };

  const savePassword = async () => {
    clearFieldError('password');

    const current = document.getElementById('editPwCurrent').value;
    const pwNew = document.getElementById('editPwNew').value;
    const pwConfirm = document.getElementById('editPwConfirm').value;

    if (!current) {
      showFieldError('password', 'La contraseña actual es obligatoria.');
      return;
    }
    const pwError = validateNewPassword(pwNew);
    if (pwError) {
      showFieldError('password', pwError);
      return;
    }
    if (pwNew !== pwConfirm) {
      showFieldError('password', 'Las contraseñas no coinciden.');
      return;
    }

    try {
      const { ok, json } = await ProfileAPI.changePassword({
        current_password: current,
        new_password: pwNew,
        new_password_confirmation: pwConfirm,
      });
      if (ok && json.success) {
        ['editPwCurrent', 'editPwNew', 'editPwConfirm'].forEach(id => {
          const el = document.getElementById(id);
          if (el) el.value = '';
        });
        closeAllEdits();
        showAlert('success', json.message);
      } else if (json.errors) {
        showFieldError('password', readFirstError(json.errors));
      } else {
        showAlert('danger', json.message || 'Error al actualizar la contraseña.');
      }
    } catch (e) {
      showAlert('danger', 'Error de conexión con el servidor.');
    }
  };

  document.querySelectorAll('[data-edit]').forEach(btn => {
    btn.addEventListener('click', () => openEdit(btn.dataset.edit));
  });

  document.querySelectorAll('[data-save]').forEach(btn => {
    btn.addEventListener('click', () => {
      if (btn.dataset.save === 'password') {
        savePassword();
      } else {
        saveProfileField(btn.dataset.save);
      }
    });
  });

  document.querySelectorAll('[data-cancel]').forEach(btn => {
    btn.addEventListener('click', closeAllEdits);
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeAllEdits();
  });
});