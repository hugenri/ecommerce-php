<?php

declare(strict_types=1);

namespace App\Core\Database;

class PaginationQueryBuilder
{
    public function build(PaginationRequest $request): PaginationQueries
    {
        $conditions = [];
        $params = [];
        $scoreParams = [];

        $this->buildBaseConditions($request, $conditions);
        $this->buildSearch($request, $conditions, $params);
        $this->buildFilters($request, $conditions, $params);

        $where = $this->buildWhere($conditions);
        $orderBy = $this->buildOrderBy($request, $scoreParams);

        $from = $request->table;
        $joins = $this->buildJoins($request);
        $groupBy = $this->buildGroupBy($request);
        $having = $this->buildHaving($request);

        $selectSql = "SELECT {$request->select} FROM {$from}{$joins} {$where}{$groupBy}{$having} ORDER BY {$orderBy} LIMIT {$request->perPage} OFFSET " . (($request->page - 1) * $request->perPage);

        $countSql = "SELECT COUNT(*) as total FROM {$from}{$joins} {$where}";

        return new PaginationQueries(
            selectSql: $selectSql,
            selectParams: array_merge($params, $scoreParams),
            countSql: $countSql,
            countParams: $params
        );
    }

    private function buildBaseConditions(PaginationRequest $request, array &$conditions): void
    {
        foreach ($request->baseConditions as $condition) {
            if ($condition !== '') {
                $conditions[] = $condition;
            }
        }
    }

    private function buildSearch(PaginationRequest $request, array &$conditions, array &$params): void
    {
        if ($request->search === '' || empty($request->searchableColumns)) {
            return;
        }

        if ($request->relevanceSearch) {
            $this->buildRelevanceSearch($request, $conditions, $params);
            return;
        }

        $likes = [];
        foreach ($request->searchableColumns as $column) {
            $param = 'search_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $column);
            $likes[] = "{$column} LIKE :{$param} ESCAPE '\\\\'";
            $params[$param] = '%' . Database::escapeLike($request->search) . '%';
        }

        $conditions[] = '(' . implode(' OR ', $likes) . ')';
    }

    private function buildRelevanceSearch(PaginationRequest $request, array &$conditions, array &$params): void
    {
        [$relevantTerms, $numericTerms] = $this->splitTerms($request->search);

        $matchTerms = $relevantTerms !== [] ? $relevantTerms : $numericTerms;

        if ($matchTerms === []) {
            return;
        }

        $likes = [];
        foreach ($request->searchableColumns as $column) {
            foreach ($matchTerms as $index => $term) {
                $param = 'search_term_' . $index . '_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $column);
                $likes[] = "{$column} LIKE :{$param} ESCAPE '\\\\'";
                $params[$param] = '%' . Database::escapeLike($term) . '%';
            }
        }

        $conditions[] = '(' . implode(' OR ', $likes) . ')';
    }

    private function buildOrderBy(PaginationRequest $request, array &$scoreParams): string
    {
        $sortBy = $this->validateSortBy($request);
        $sortDir = $this->validateSortDir($request);

        if (!$request->relevanceSearch || $request->search === '' || empty($request->searchableColumns)) {
            return "{$sortBy} {$sortDir}";
        }

        $score = $this->buildRelevanceScore($request, $scoreParams);

        return "{$score} DESC, {$sortBy} {$sortDir}";
    }

    private function buildRelevanceScore(PaginationRequest $request, array &$params): string
    {
        $column = $request->searchableColumns[0];

        [$relevantTerms, $numericTerms] = $this->splitTerms($request->search);

        $when = [];

        if (count($relevantTerms) + count($numericTerms) >= 2) {
            $param = 'score_phrase';
            $params[$param] = '%' . Database::escapeLike($request->search) . '%';
            $when[] = "WHEN {$column} LIKE :{$param} ESCAPE '\\\\' THEN 100";
        }

        if (count($relevantTerms) >= 2) {
            $clauses = [];
            foreach ($relevantTerms as $index => $term) {
                $param = 'score_rel_' . $index;
                $params[$param] = '%' . Database::escapeLike($term) . '%';
                $clauses[] = "{$column} LIKE :{$param} ESCAPE '\\\\'";
            }
            $when[] = 'WHEN ' . implode(' AND ', $clauses) . ' THEN 80';
        }

        foreach ($relevantTerms as $index => $term) {
            $param = 'score_main_' . $index;
            $params[$param] = '%' . Database::escapeLike($term) . '%';
            $when[] = "WHEN {$column} LIKE :{$param} ESCAPE '\\\\' THEN " . ($index === 0 ? '60' : '40');
        }

        foreach ($numericTerms as $index => $term) {
            $param = 'score_num_' . $index;
            $params[$param] = '%' . Database::escapeLike($term) . '%';
            $when[] = "WHEN {$column} LIKE :{$param} ESCAPE '\\\\' THEN 20";
        }

        if ($when === []) {
            return '0';
        }

        return '(CASE ' . implode(' ', $when) . ' ELSE 0 END)';
    }

    /**
     * Divide una búsqueda en términos relevantes y secundarios.
     *
     * - Un término es secundario (numérico) si contiene algún dígito: "6", "12"".
     * - Un término relevante debe contener al menos una letra o dígito y tener
     *   una longitud mínima de 3 (se descartan "de", "la", "or"...).
     *
     * @return array{0: string[], 1: string[]} [términosRelevantes, términosNuméricos]
     */
    private function splitTerms(string $search): array
    {
        $rawTerms = preg_split('/\s+/u', trim($search), -1, PREG_SPLIT_NO_EMPTY);

        $relevantTerms = [];
        $numericTerms = [];

        foreach ($rawTerms as $rawTerm) {
            if (preg_match('/[\p{L}\p{N}]/u', $rawTerm) !== 1) {
                continue;
            }

            if (preg_match('/\d/', $rawTerm) === 1) {
                $numericTerms[] = $rawTerm;
                continue;
            }

            if (mb_strlen($rawTerm) < 3) {
                continue;
            }

            $relevantTerms[] = $rawTerm;
        }

        return [$relevantTerms, $numericTerms];
    }

    private function buildFilters(PaginationRequest $request, array &$conditions, array &$params): void
    {
        foreach ($request->filterRules as $filterKey => $rule) {
            if (!array_key_exists($filterKey, $request->filters)) {
                continue;
            }

            $value = $request->filters[$filterKey];

            if ($value === '' || $value === null) {
                continue;
            }

            $column = $rule['column'] ?? $filterKey;
            $operator = $rule['operator'] ?? '=';

            if (isset($rule['allowed'])) {
                if (!in_array($value, $rule['allowed'], true)) {
                    continue;
                }
            } elseif (isset($rule['type'])) {
                $type = $rule['type'];
                if ($type === 'int') {
                    $value = (int) $value;
                } elseif ($type === 'numeric') {
                    if (!is_numeric($value)) {
                        continue;
                    }
                    $value = (float) $value;
                }
            }

            $param = 'filter_' . $filterKey;
            $conditions[] = "{$column} {$operator} :{$param}";
            $params[$param] = $value;
        }
    }

    private function buildWhere(array $conditions): string
    {
        if (empty($conditions)) {
            return '';
        }

        return 'WHERE ' . implode(' AND ', $conditions);
    }

    private function validateSortBy(PaginationRequest $request): string
    {
        if (empty($request->allowedSorts)) {
            return $request->defaultSort;
        }

        return in_array($request->sortBy, $request->allowedSorts, true)
            ? $request->sortBy
            : $request->defaultSort;
    }

    private function validateSortDir(PaginationRequest $request): string
    {
        return strtoupper($request->sortDir) === 'DESC' ? 'DESC' : 'ASC';
    }

    private function buildJoins(PaginationRequest $request): string
    {
        $joins = trim($request->joins);

        if ($joins === '') {
            return '';
        }

        return ' ' . $joins;
    }

    private function buildGroupBy(PaginationRequest $request): string
    {
        $groupBy = trim($request->groupBy);

        if ($groupBy === '') {
            return '';
        }

        return ' GROUP BY ' . $groupBy;
    }

    private function buildHaving(PaginationRequest $request): string
    {
        $having = trim($request->having);

        if ($having === '') {
            return '';
        }

        return ' HAVING ' . $having;
    }
}
