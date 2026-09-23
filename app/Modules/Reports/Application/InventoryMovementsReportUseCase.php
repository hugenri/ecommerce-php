<?php

declare(strict_types=1);

namespace App\Modules\Reports\Application;

use App\Core\Validation\Validator;
use App\Modules\Inventory\Application\UseCases\ListInventoryMovementsUseCase;

class InventoryMovementsReportUseCase
{
    public function __construct(
        private ListInventoryMovementsUseCase $listMovements,
        private Validator $validator,
    ) {}

    public function execute(int $page = 1, int $perPage = 10, array $filters = []): array
    {
        $errors = $this->validator->validate($filters, [
            'product_id' => 'nullable|numeric',
            'movement_type' => 'nullable|in:purchase,sale,adjustment',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        if ($this->validator->hasErrors($errors)) {
            throw new \DomainException('Filtros de reporte de movimientos inválidos.');
        }

        return $this->listMovements->execute($page, $perPage, $filters);
    }
}
