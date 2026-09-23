<?php

declare(strict_types=1);

namespace App\Modules\Reports\Application;

use App\Core\Validation\Validator;
use App\Modules\Reports\Domain\ReportRepositoryInterface;

class TopProductsReportUseCase
{
    public function __construct(
        private ReportRepositoryInterface $reportRepository,
        private Validator $validator,
    ) {}

    public function execute(array $filters, int $page = 1, int $perPage = 10): array
    {
        $errors = $this->validator->validate($filters, [
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        if ($this->validator->hasErrors($errors)) {
            throw new \DomainException('Filtros de reporte de productos más vendidos inválidos.');
        }

        return $this->reportRepository->topProducts(
            filters: $this->normalize($filters),
            page: $page,
            perPage: $perPage,
        );
    }

    private function normalize(array $filters): array
    {
        return array_filter($filters, fn($value) => $value !== null && $value !== '');
    }
}
