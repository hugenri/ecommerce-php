<?php

namespace App\Core\Database;

class PaginationQueries
{
    public string $selectSql;
    public array $selectParams;
    public string $countSql;
    public array $countParams;

    public function __construct(
        string $selectSql,
        array $selectParams,
        string $countSql,
        array $countParams
    ) {
        $this->selectSql = $selectSql;
        $this->selectParams = $selectParams;
        $this->countSql = $countSql;
        $this->countParams = $countParams;
    }
}
