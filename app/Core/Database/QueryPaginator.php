<?php

namespace App\Core\Database;

class QueryPaginator
{
    private Database $db;
    private PaginationQueryBuilder $builder;

    public function __construct(Database $db)
    {
        $this->db = $db;
        $this->builder = new PaginationQueryBuilder();
    }

    public function paginate(PaginationRequest $request): array
    {
        $queries = $this->builder->build($request);

        $totalResult = $this->db->selectOne($queries->countSql, $queries->countParams);
        $total = (int) ($totalResult['total'] ?? 0);

        $rows = $this->db->select($queries->selectSql, $queries->selectParams);

        return $this->buildMeta($rows, $request, $total);
    }

    private function buildMeta(array $rows, PaginationRequest $request, int $total): array
    {
        $totalPages = $total > 0 ? (int) ceil($total / $request->perPage) : 0;
        $from = $total > 0 ? ($request->page - 1) * $request->perPage + 1 : 0;
        $to = $total > 0 ? min($request->page * $request->perPage, $total) : 0;

        return [
            'data' => $rows,
            'meta' => [
                'current_page' => $request->page,
                'per_page' => $request->perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'last_page' => $totalPages,
                'from' => $from,
                'to' => $to,
            ],
        ];
    }
}
