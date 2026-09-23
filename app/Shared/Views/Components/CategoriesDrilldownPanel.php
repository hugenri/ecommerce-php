<?php
$navCategories = $navCategories ?? $categories ?? $catalogCategories ?? [];
?>
<div id="catalogCategoriesPanel"
     class="catalog-categories-panel"
     data-catalog-categories-panel
     hidden>
    <div class="catalog-drill" data-catalog-drill>
        <div class="catalog-drill-view" data-catalog-drill-root>
            <ul class="catalog-category-list">
                <?php foreach ($navCategories as $cat): ?>
                    <?php $catImg = \App\Shared\Support\ImageHelper::url($cat->getImage()); ?>
                    <li class="catalog-category">
                        <button type="button"
                                class="catalog-category-link catalog-drill-trigger"
                                data-catalog-drill-trigger="<?= $cat->getCategoryId() ?>"
                                aria-expanded="false"
                                aria-controls="catalogDrill-<?= $cat->getCategoryId() ?>">
                            <span class="catalog-drill-icon">
                                <img src="<?= htmlspecialchars($catImg !== '' ? $catImg : '/public/images/default-category.svg') ?>"
                                     class="catalog-category-icon"
                                     alt="" loading="lazy">
                            </span>
                            <span class="catalog-category-name"><?= htmlspecialchars($cat->getName()) ?></span>
                            <i class="bi bi-chevron-right catalog-category-arrow" aria-hidden="true"></i>
                        </button>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php foreach ($navCategories as $cat): ?>
            <div id="catalogDrill-<?= $cat->getCategoryId() ?>"
                 class="catalog-drill-view"
                 data-catalog-drill-sub="<?= $cat->getCategoryId() ?>"
                 role="group"
                 aria-label="<?= htmlspecialchars($cat->getName()) ?>"
                 hidden>
                <div class="catalog-drill-header">
                    <button type="button" class="catalog-drill-back" data-catalog-drill-back>
                        <i class="bi bi-chevron-left" aria-hidden="true"></i> Regresar
                    </button>
                </div>
                <a href="/shop?category_id=<?= $cat->getCategoryId() ?>"
                   class="catalog-drill-all">
                    Ver todos de <?= htmlspecialchars($cat->getName()) ?>
                </a>
                <ul class="catalog-drill-sublist">
                    <?php foreach (($catalogSubcategories[$cat->getCategoryId()] ?? []) as $sub): ?>
                        <li>
                            <a href="/shop?category_id=<?= $cat->getCategoryId() ?>&subcategory_id=<?= $sub->getSubcategoryId() ?>"
                               class="catalog-subcategory-link">
                                <?= htmlspecialchars($sub->getName()) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    </div>
</div>