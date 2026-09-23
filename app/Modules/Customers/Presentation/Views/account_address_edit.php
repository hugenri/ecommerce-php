<?php
if (!isset($editAddress)) return;
?>
<div class="row">
    <div class="col-md-8 mb-3">
        <label for="editStreet" class="form-label">Calle *</label>
        <input type="text" class="form-control" id="editStreet" name="street" required minlength="3" maxlength="80"
               aria-describedby="editStreet-feedback" value="<?= htmlspecialchars($editAddress->getStreet()) ?>">
        <div id="editStreet-feedback" class="field-feedback" role="alert" hidden
             data-mensaje="La calle es obligatoria."></div>
    </div>
    <div class="col-md-4 mb-3">
        <label for="editNumber" class="form-label">Número *</label>
        <input type="text" class="form-control" id="editNumber" name="number" required maxlength="15"
               aria-describedby="editNumber-feedback" value="<?= htmlspecialchars($editAddress->getNumber()) ?>">
        <div id="editNumber-feedback" class="field-feedback" role="alert" hidden
             data-mensaje="El número es obligatorio."></div>
    </div>
</div>
<div class="mb-3">
    <label for="editNeighborhood" class="form-label">Colonia *</label>
    <input type="text" class="form-control" id="editNeighborhood" name="neighborhood" required minlength="3" maxlength="80"
           aria-describedby="editNeighborhood-feedback" value="<?= htmlspecialchars($editAddress->getNeighborhood()) ?>">
    <div id="editNeighborhood-feedback" class="field-feedback" role="alert" hidden
         data-mensaje="La colonia es obligatoria."></div>
</div>
<div class="row">
    <div class="col-md-6 mb-3">
        <label for="editMunicipality" class="form-label">Municipio *</label>
        <input type="text" class="form-control" id="editMunicipality" name="municipality" required minlength="3" maxlength="80"
               aria-describedby="editMunicipality-feedback" value="<?= htmlspecialchars($editAddress->getMunicipality()) ?>">
        <div id="editMunicipality-feedback" class="field-feedback" role="alert" hidden
             data-mensaje="El municipio es obligatorio."></div>
    </div>
    <div class="col-md-6 mb-3">
        <label for="editState" class="form-label">Estado *</label>
        <input type="text" class="form-control" id="editState" name="state" required minlength="3" maxlength="80"
               aria-describedby="editState-feedback" value="<?= htmlspecialchars($editAddress->getState()) ?>">
        <div id="editState-feedback" class="field-feedback" role="alert" hidden
             data-mensaje="El estado es obligatorio."></div>
    </div>
</div>
<div class="row">
    <div class="col-md-6 mb-3">
        <label for="editZipCode" class="form-label">Código postal *</label>
        <input type="text" class="form-control" id="editZipCode" name="zip_code" required maxlength="5"
               pattern="^[0-9]{5}$" aria-describedby="editZipCode-feedback" value="<?= htmlspecialchars($editAddress->getZipCode()) ?>">
        <div id="editZipCode-feedback" class="field-feedback" role="alert" hidden
             data-mensaje="El código postal debe tener 5 dígitos."></div>
    </div>
    <div class="col-md-6 mb-3">
        <label for="editAlias" class="form-label">Alias</label>
        <input type="text" class="form-control" id="editAlias" name="alias" maxlength="50" placeholder="Casa, Trabajo..."
               value="<?= htmlspecialchars($editAddress->getAlias() ?? '') ?>">
    </div>
</div>
<div class="mb-3">
    <label for="editReference" class="form-label">Referencia</label>
    <textarea class="form-control" id="editReference" name="reference" rows="2" maxlength="150"><?= htmlspecialchars($editAddress->getReference() ?? '') ?></textarea>
</div>
<div class="form-check">
    <input class="form-check-input" type="checkbox" name="is_default" value="1" id="editIsDefault" <?= $editAddress->isDefault() ? 'checked' : '' ?>>
    <label class="form-check-label" for="editIsDefault">Dirección predeterminada</label>
</div>