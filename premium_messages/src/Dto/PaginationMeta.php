<?php

namespace App\Dto;

use Doctrine\ORM\Tools\Pagination\WindowPage;

final class PaginationMeta
{
    public function __construct(
        public readonly int $pageNr,
        public readonly int $pageSize,
        public readonly int $pageRecordCount,
        public readonly int $totalNrOfPages,
        public readonly int $totalRecordCount,
        public readonly int $filteredRecordCount,
        public readonly bool $hasPreviousPage,
        public readonly bool $hasNextPage,
    ) {
    }

    /**
     * @param int $totalRecordCount number of records before any filter is applied
     */
    public static function fromWindowPage(WindowPage $page, int $totalRecordCount): self
    {
        return new self(
            pageNr: $page->getPageNumber(),
            pageSize: $page->getWindow()->getMaxResults(),
            pageRecordCount: \count($page),
            totalNrOfPages: $page->getPageCount(),
            totalRecordCount: $totalRecordCount,
            filteredRecordCount: $page->getTotalCount(),
            hasPreviousPage: $page->hasPreviousPage(),
            hasNextPage: $page->hasNextPage(),
        );
    }
}
