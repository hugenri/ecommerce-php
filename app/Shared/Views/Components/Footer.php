<?php
$siteSettings = $siteSettings ?? null;

$storeName = 'Tienda';
if ($siteSettings !== null) {
    $storeName = $siteSettings->getStoreName() !== '' ? $siteSettings->getStoreName() : 'Tienda';
}

$contactEmail = $siteSettings?->getContactEmail();
$phone = $siteSettings?->getPhone();
$whatsapp = $siteSettings?->getWhatsapp();
$address = $siteSettings?->getAddress();
$businessHours = $siteSettings?->getBusinessHours();
$socials = [
    'facebook' => $siteSettings?->getFacebookUrl(),
    'instagram' => $siteSettings?->getInstagramUrl(),
    'tiktok' => $siteSettings?->getTiktokUrl(),
];
?>
<footer class="py-4 bg-dark text-light">
    <div class="container">
        <div class="row gy-4">
            <div class="col-12 col-md-4">
                <h6 class="fw-semibold text-white mb-2">Contacto</h6>
                <ul class="list-unstyled small mb-0">
                    <?php if ($contactEmail): ?>
                        <li class="mb-1"><i class="bi bi-envelope me-2"></i><a href="mailto:<?= htmlspecialchars($contactEmail) ?>" class="text-light text-decoration-none"><?= htmlspecialchars($contactEmail) ?></a></li>
                    <?php endif; ?>
                    <?php if ($phone): ?>
                        <li class="mb-1"><i class="bi bi-telephone me-2"></i><?= htmlspecialchars($phone) ?></li>
                    <?php endif; ?>
                    <?php if ($whatsapp): ?>
                        <li class="mb-1"><i class="bi bi-whatsapp me-2"></i><a href="https://wa.me/<?= htmlspecialchars(preg_replace('/[^0-9]/', '', $whatsapp)) ?>" class="text-light text-decoration-none"><?= htmlspecialchars($whatsapp) ?></a></li>
                    <?php endif; ?>
                    <?php if ($address): ?>
                        <li class="mb-1"><i class="bi bi-geo-alt me-2"></i><?= htmlspecialchars($address) ?></li>
                    <?php endif; ?>
                    <?php if ($businessHours): ?>
                        <li class="mb-1"><i class="bi bi-clock me-2"></i><?= htmlspecialchars($businessHours) ?></li>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="col-12 col-md-4">
                <h6 class="fw-semibold text-white mb-2">Síguenos</h6>
                <ul class="list-unstyled small mb-0">
                    <?php if ($socials['facebook']): ?>
                        <li class="mb-1"><a href="<?= htmlspecialchars($socials['facebook']) ?>" class="text-light text-decoration-none" target="_blank" rel="noopener"><i class="bi bi-facebook me-2"></i>Facebook</a></li>
                    <?php endif; ?>
                    <?php if ($socials['instagram']): ?>
                        <li class="mb-1"><a href="<?= htmlspecialchars($socials['instagram']) ?>" class="text-light text-decoration-none" target="_blank" rel="noopener"><i class="bi bi-instagram me-2"></i>Instagram</a></li>
                    <?php endif; ?>
                    <?php if ($socials['tiktok']): ?>
                        <li class="mb-1"><a href="<?= htmlspecialchars($socials['tiktok']) ?>" class="text-light text-decoration-none" target="_blank" rel="noopener"><i class="bi bi-tiktok me-2"></i>TikTok</a></li>
                    <?php endif; ?>
                    <?php if (empty(array_filter($socials))): ?>
                        <li class="text-muted">Sin redes configuradas.</li>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="col-12 col-md-4">
                <h6 class="fw-semibold text-white mb-2"><?= htmlspecialchars($storeName) ?></h6>
                <p class="small text-muted mb-0">&copy; <?= date('Y') ?> <?= htmlspecialchars($storeName) ?>. Todos los derechos reservados.</p>
            </div>
        </div>
    </div>
</footer>