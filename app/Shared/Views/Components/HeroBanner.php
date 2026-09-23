<?php
/*
 * Componente del Hero de la página principal.
 *
 * Espera en el scope: $homeBanner (App\Modules\Settings\Domain\HomeBanner|null).
 *
 * El Hero siempre se muestra como imagen de portada de gran tamaño.
 *  - Con banner activo: imagen, título y subtítulo provienen SOLO de home_banners.
 *  - Sin banner activo: se usa la imagen por defecto (/public/images/default-hero.jpg)
 *    y NO se muestra título, subtítulo (jamás el nombre de la tienda).
 *  - El título y subtítulo se renderizan únicamente si tienen contenido.
 */
use App\Shared\Support\ImageHelper;

$homeBanner = $homeBanner ?? null;
$defaultHero = '/public/images/default-hero.jpg';

$heroImage = $defaultHero;
if ($homeBanner !== null && $homeBanner->getImage() !== '') {
    $heroImage = ImageHelper::url($homeBanner->getImage());
}

$heroTitle = $homeBanner !== null ? trim($homeBanner->getTitle() ?? '') : '';
$heroSubtitle = $homeBanner !== null ? trim($homeBanner->getSubtitle() ?? '') : '';
?>
<section class="hero text-center" style="background-image: url('<?= htmlspecialchars($heroImage, ENT_QUOTES) ?>');">
    <div class="hero-overlay"></div>
    <div class="container hero-content">
        <?php if ($heroTitle !== ''): ?>
            <h1><?= htmlspecialchars($heroTitle) ?></h1>
        <?php endif; ?>
        <?php if ($heroSubtitle !== ''): ?>
            <p class="lead mt-3"><?= htmlspecialchars($heroSubtitle) ?></p>
        <?php endif; ?>
    </div>
</section>