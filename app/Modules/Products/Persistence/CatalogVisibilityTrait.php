<?php

declare(strict_types=1);

namespace App\Modules\Products\Persistence;

/**
 * Regla única de visibilidad del catálogo público.
 *
 * Un producto pertenece al catálogo público únicamente si:
 *  - está activo (products.status = 'active');
 *  - tiene al menos un registro en inventory_movements.
 *
 * El valor de products.stock NO interviene: un producto con stock = 0
 * sigue perteneciendo al catálogo (aparecerá como agotado).
 *
 * Si en el futuro cambia la regla (por ejemplo, publicación programada),
 * únicamente debe modificarse este método.
 */
trait CatalogVisibilityTrait
{
    protected function catalogProductCondition(): string
    {
        return "products.status = 'active'
                AND EXISTS (
                    SELECT 1
                    FROM inventory_movements
                    WHERE inventory_movements.product_id = products.product_id
                )";
    }
}
