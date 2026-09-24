<?php

namespace App\Dto;

use App\Enum\MessageStatus;
use Symfony\Component\Validator\Constraints as Assert;

final class ListMessagesQuery
{
    public const int MAX_PAGE_SIZE = 100;

    public function __construct(
        public readonly ?MessageStatus $status = null,
        #[Assert\Positive]
        public readonly int $pageNr = 1,
        #[Assert\Range(min: 1, max: self::MAX_PAGE_SIZE)]
        public readonly int $pageSize = 10,
    ) {
    }
}
