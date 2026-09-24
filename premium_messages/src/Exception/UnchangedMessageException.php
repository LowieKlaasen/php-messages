<?php

namespace App\Exception;

final class UnchangedMessageException extends \DomainException
{
    public function __construct()
    {
        parent::__construct('The subject, recipient or content must differ from the rejected version.');
    }
}
