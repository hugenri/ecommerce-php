<?php
$pageTitle = 'Admin - Configuración Página de Inicio';
$pageActive = 'settings.home';
$topbarTitle = 'Configuración Página de Inicio';
$topbarActions = '<a class="btn btn-sm btn-outline-primary" href="/admin/settings/general"><i class="bi bi-arrow-left me-1"></i>Configuración General</a>';
$editing = $editing ?? null;
$bannersJson = $bannersJson ?? [];
$viewInlineJs = 'window.HOME_BANNERS = ' . json_encode($bannersJson, JSON_UNESCAPED_UNICODE) . ';';
$viewJs = ['/public/js/settings/home.js?v=1'];

ob_start();
?>
<div class="card shadow-sm mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-semibold">Banners del Home</span>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnNewBanner"><i class="bi bi-plus-lg me-1"></i> Nuevo banner</button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 80px;">Orden</th>
                        <th style="width: 100px;">Imagen</th>
                        <th>Título</th>
                        <th style="width: 90px;">Activo</th>
                        <th style="width: 140px;">Acciones</th>
                    </tr>
                </thead>
                <tbody id="bannersTableBody">
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4"><i class="bi bi-inbox me-2"></i>Cargando banners...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header fw-semibold" id="bannerFormTitle">Nuevo banner</div>
    <div class="card-body">
        <form method="POST" action="/admin/settings/banners" id="bannerForm" data-validate novalidate>
            <input type="hidden" id="bannerFormId" value="">

            <div class="row g-3">
                <div class="col-12 col-md-8">
                    <label class="form-label small text-muted" for="bannerImageInput">Imagen (URL) <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="bannerImageInput" name="image" maxlength="255" value="<?= htmlspecialchars($editing ? $editing->getImage() : '') ?>" required aria-describedby="banner-image-feedback">
                    <div id="banner-image-feedback" class="field-feedback" role="alert" hidden data-mensaje="La URL de la imagen es obligatoria."></div>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small text-muted" for="bannerSortOrderInput">Orden</label>
                    <input type="number" class="form-control" id="bannerSortOrderInput" name="sort_order" value="<?= (int) ($editing ? $editing->getSortOrder() : 1) ?>" aria-describedby="banner-sort-order-feedback">
                    <div id="banner-sort-order-feedback" class="field-feedback" role="alert" hidden></div>
                </div>
                <div class="col-6 col-md-4 d-flex align-items-end gap-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" id="bannerIsActiveInput" value="1" <?= $editing && $editing->isActive() ? 'checked' : '' ?>>
                        <label class="form-check-label small text-muted" for="bannerIsActiveInput">Activo</label>
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label small text-muted" for="bannerTitleInput">Título (opcional)</label>
                    <input type="text" class="form-control" id="bannerTitleInput" name="title" maxlength="255" value="<?= htmlspecialchars($editing ? ($editing->getTitle() ?? '') : '') ?>" aria-describedby="banner-title-feedback">
                    <div id="banner-title-feedback" class="field-feedback" role="alert" hidden></div>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label small text-muted" for="bannerSubtitleInput">Subtítulo (opcional)</label>
                    <textarea class="form-control" id="bannerSubtitleInput" name="subtitle" maxlength="1000" rows="2" aria-describedby="banner-subtitle-feedback"><?= htmlspecialchars($editing ? ($editing->getSubtitle() ?? '') : '') ?></textarea>
                    <div id="banner-subtitle-feedback" class="field-feedback" role="alert" hidden></div>
                </div>
            </div>

            <div class="d-flex justify-content-end mt-4">
                <button type="button" class="btn btn-outline-secondary me-2" id="btnCancelBannerEdit" hidden>Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Crear banner</button>
            </div>
        </form>
    </div>
</div>
<?php
$content = ob_get_clean();
require $sharedViewsPath . '/Layouts/AdminLayout.php';