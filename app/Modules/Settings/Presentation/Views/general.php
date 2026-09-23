<?php
$pageTitle = 'Admin - Configuración General';
$pageActive = 'settings.general';
$topbarTitle = 'Configuración General';
$topbarActions = '<a class="btn btn-sm btn-outline-primary" href="/admin/settings/home"><i class="bi bi-house me-1"></i>Página principal</a>';
$settings = $settings ?? null;
$viewJs = ['/public/js/settings/general.js?v=1'];

ob_start();
?>
<form method="POST" action="/admin/settings/general" id="generalForm" data-validate novalidate>
    <div class="card shadow-sm mb-4">
        <div class="card-header fw-semibold">Identidad de la tienda</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label class="form-label small text-muted" for="storeNameInput">Nombre de la tienda <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="storeNameInput" name="store_name" maxlength="150" value="<?= htmlspecialchars($settings?->getStoreName() ?? '') ?>" required aria-describedby="store-name-feedback">
                    <div id="store-name-feedback" class="field-feedback" role="alert" hidden data-mensaje="El nombre de la tienda es obligatorio."></div>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label small text-muted" for="sloganInput">Slogan</label>
                    <input type="text" class="form-control" id="sloganInput" name="slogan" maxlength="255" value="<?= htmlspecialchars($settings?->getSlogan() ?? '') ?>" aria-describedby="slogan-feedback">
                    <div id="slogan-feedback" class="field-feedback" role="alert" hidden></div>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label small text-muted" for="logoInput">Logo (URL)</label>
                    <input type="text" class="form-control" id="logoInput" name="logo" maxlength="255" value="<?= htmlspecialchars($settings?->getLogo() ?? '') ?>" aria-describedby="logo-feedback">
                    <div id="logo-feedback" class="field-feedback" role="alert" hidden></div>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label small text-muted" for="faviconInput">Favicon (URL)</label>
                    <input type="text" class="form-control" id="faviconInput" name="favicon" maxlength="255" value="<?= htmlspecialchars($settings?->getFavicon() ?? '') ?>" aria-describedby="favicon-feedback">
                    <div id="favicon-feedback" class="field-feedback" role="alert" hidden></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header fw-semibold">Contacto</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label class="form-label small text-muted" for="contactEmailInput">Correo de contacto</label>
                    <input type="email" class="form-control" id="contactEmailInput" name="contact_email" maxlength="150" value="<?= htmlspecialchars($settings?->getContactEmail() ?? '') ?>" aria-describedby="contact-email-feedback">
                    <div id="contact-email-feedback" class="field-feedback" role="alert" hidden></div>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label small text-muted" for="phoneInput">Teléfono</label>
                    <input type="text" class="form-control" id="phoneInput" name="phone" maxlength="50" value="<?= htmlspecialchars($settings?->getPhone() ?? '') ?>" aria-describedby="phone-feedback">
                    <div id="phone-feedback" class="field-feedback" role="alert" hidden></div>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label small text-muted" for="whatsappInput">WhatsApp</label>
                    <input type="text" class="form-control" id="whatsappInput" name="whatsapp" maxlength="50" value="<?= htmlspecialchars($settings?->getWhatsapp() ?? '') ?>" aria-describedby="whatsapp-feedback">
                    <div id="whatsapp-feedback" class="field-feedback" role="alert" hidden></div>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label small text-muted" for="businessHoursInput">Horario</label>
                    <input type="text" class="form-control" id="businessHoursInput" name="business_hours" maxlength="255" value="<?= htmlspecialchars($settings?->getBusinessHours() ?? '') ?>" aria-describedby="business-hours-feedback">
                    <div id="business-hours-feedback" class="field-feedback" role="alert" hidden></div>
                </div>
                <div class="col-12">
                    <label class="form-label small text-muted" for="addressInput">Dirección</label>
                    <textarea class="form-control" id="addressInput" name="address" rows="2" aria-describedby="address-feedback"><?= htmlspecialchars($settings?->getAddress() ?? '') ?></textarea>
                    <div id="address-feedback" class="field-feedback" role="alert" hidden></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header fw-semibold">Redes sociales</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <label class="form-label small text-muted" for="facebookUrlInput">Facebook</label>
                    <input type="text" class="form-control" id="facebookUrlInput" name="facebook_url" maxlength="255" value="<?= htmlspecialchars($settings?->getFacebookUrl() ?? '') ?>" aria-describedby="facebook-url-feedback">
                    <div id="facebook-url-feedback" class="field-feedback" role="alert" hidden></div>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label small text-muted" for="instagramUrlInput">Instagram</label>
                    <input type="text" class="form-control" id="instagramUrlInput" name="instagram_url" maxlength="255" value="<?= htmlspecialchars($settings?->getInstagramUrl() ?? '') ?>" aria-describedby="instagram-url-feedback">
                    <div id="instagram-url-feedback" class="field-feedback" role="alert" hidden></div>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label small text-muted" for="tiktokUrlInput">TikTok</label>
                    <input type="text" class="form-control" id="tiktokUrlInput" name="tiktok_url" maxlength="255" value="<?= htmlspecialchars($settings?->getTiktokUrl() ?? '') ?>" aria-describedby="tiktok-url-feedback">
                    <div id="tiktok-url-feedback" class="field-feedback" role="alert" hidden></div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Guardar cambios</button>
    </div>
</form>
<?php
$content = ob_get_clean();
require $sharedViewsPath . '/Layouts/AdminLayout.php';