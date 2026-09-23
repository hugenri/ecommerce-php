<?php

namespace App\Core\Database;

class PaginationRequest
{
    public string $table;
    public string $select;
    public string $joins;
    public string $groupBy;
    public string $having;
    public string $search;
    public array $searchableColumns;
    public string $sortBy;
    public string $sortDir;
    public array $allowedSorts;
    public string $defaultSort;
    public array $filters;
    public array $filterRules;
    public int $page;
    public int $perPage;
    public array $baseConditions;
    public bool $relevanceSearch;

    public function __construct(
        string $table,
        string $select = '*',
        string $joins = '',
        string $groupBy = '',
        string $having = '',
        string $search = '',
        array $searchableColumns = [],
        string $sortBy = 'id',
        string $sortDir = 'ASC',
        array $allowedSorts = [],
        string $defaultSort = 'id',
        array $filters = [],
        array $filterRules = [],
        int $page = 1,
        int $perPage = 15,
        array $baseConditions = [],
        bool $relevanceSearch = false
    ) {
        $this->table = $table;
        $this->select = $select;
        $this->joins = $joins;
        $this->groupBy = $groupBy;
        $this->having = $having;
        $this->search = $search;
        $this->searchableColumns = $searchableColumns;
        $this->sortBy = $sortBy;
        $this->sortDir = $sortDir;
        $this->allowedSorts = $allowedSorts;
        $this->defaultSort = $defaultSort;
        $this->filters = $filters;
        $this->filterRules = $filterRules;
        $this->page = $page;
        $this->perPage = $perPage;
        $this->baseConditions = $baseConditions;
        $this->relevanceSearch = $relevanceSearch;
    }

    public static function fromRepository(
        object $repository,
        int $page,
        int $perPage,
        string $search = '',
        string $sortBy = 'id',
        string $sortDir = 'ASC',
        array $filters = [],
        array $baseConditions = [],
        bool $relevanceSearch = false
    ): self {
        return new self(
            table: $repository->getTable(),
            select: $repository->getSelect(),
            joins: $repository->getJoins(),
            groupBy: $repository->getGroupBy(),
            having: $repository->getHaving(),
            search: $search,
            searchableColumns: $repository->getSearchableColumns(),
            sortBy: $sortBy,
            sortDir: $sortDir,
            allowedSorts: $repository->getAllowedSorts(),
            defaultSort: $repository->getDefaultSort(),
            filters: $filters,
            filterRules: $repository->getFilterRules(),
            page: $page,
            perPage: $perPage,
            baseConditions: $baseConditions,
            relevanceSearch: $relevanceSearch
        );
    }
}
