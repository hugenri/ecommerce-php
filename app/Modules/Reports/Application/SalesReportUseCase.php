<?php

declare(strict_types=1);

namespace App\Modules\Reports\Application;

use App\Core\Validation\Validator;
use App\Modules\Checkout\Application\Admin\ListSalesUseCase;
use App\Modules\Reports\Domain\ReportRepositoryInterface;

class SalesReportUseCase
{
    public function __construct(
        private ListSalesUseCase $listSales,
        private ReportRepositoryInterface $reportRepository,
        private Validator $validator,
    ) {}

    public function execute(array $filters, int $page = 1, int $perPage = 10): array
    {
        $errors = $this->validator->validate($filters, [
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'status' => 'nullable|in:pending,processing,shipped,delivered,cancelled',
            'payment_status' => 'nullable|in:pending,paid,failed,refunded',
        ]);

        if ($this->validator->hasErrors($errors)) {
            throw new \DomainException('Filtros de reporte de ventas inválidos.');
        }

        $filters = $this->normalize($filters);

        $sales = $this->listSales->execute(
            page: $page,
            perPage: $perPage,
            sortBy: 'sale_date',
            sortDir: 'DESC',
            filters: $filters,
        );

        return [
            'data' => $sales['data'],
            'meta' => $sales['meta'],
            'summary' => $this->reportRepository->salesSummary($filters),
        ];
    }

    private function normalize(array $filters): array
    {
        return array_filter($filters, fn($value) => $value !== null && $value !== '');
    }
}
