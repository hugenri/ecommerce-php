<?php

declare(strict_types=1);

namespace App\Modules\Reports\Application;

use App\Core\Validation\Validator;
use App\Modules\Deliveries\Domain\DeliveryRepositoryInterface;
use App\Modules\Reports\Domain\ReportRepositoryInterface;

class DeliveriesReportUseCase
{
    public function __construct(
        private DeliveryRepositoryInterface $deliveryRepository,
        private ReportRepositoryInterface $reportRepository,
        private Validator $validator,
    ) {}

    public function execute(array $filters, int $page = 1, int $perPage = 10): array
    {
        $errors = $this->validator->validate($filters, [
            'status' => 'nullable|in:pending,preparing,shipped,delivered,cancelled',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        if ($this->validator->hasErrors($errors)) {
            throw new \DomainException('Filtros de reporte de entregas inválidos.');
        }

        $filters = $this->normalize($filters);

        $deliveries = $this->deliveryRepository->findAll(
            page: $page,
            perPage: $perPage,
            filters: $filters,
        );

        return [
            'data' => $deliveries['data'],
            'meta' => $deliveries['meta'],
            'summary' => $this->reportRepository->deliveriesSummary($filters),
        ];
    }

    private function normalize(array $filters): array
    {
        return array_filter($filters, fn($value) => $value !== null && $value !== '');
    }
}
