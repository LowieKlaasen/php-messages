<?php

namespace App\Dto;

/**
 * @template T
 */
final class PaginatedList
{
    /**
     * @param list<T> $data
     */
    public function __construct(
        public readonly array $data,
        public readonly PaginationMeta $pagination,
    ) {
    }
}
