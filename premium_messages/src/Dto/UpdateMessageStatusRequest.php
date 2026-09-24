<?php

namespace App\Dto;

use App\Enum\MessageStatus;
use Symfony\Component\Validator\Constraints as Assert;

final class UpdateMessageStatusRequest
{
    public function __construct(
        #[Assert\Choice(choices: [MessageStatus::Verified, MessageStatus::Rejected], message: 'Status can only be changed to "verified" or "rejected".')]
        public readonly MessageStatus $status,
    ) {
    }
}
