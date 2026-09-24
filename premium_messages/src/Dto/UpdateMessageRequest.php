<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class UpdateMessageRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public readonly string $subject,
        #[Assert\NotBlank]
        public readonly string $recipient,
        public readonly string $content,
    ) {
    }
}
