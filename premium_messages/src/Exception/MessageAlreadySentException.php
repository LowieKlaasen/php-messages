<?php

namespace App\Exception;

final class MessageAlreadySentException extends \LogicException
{
    public function __construct(?int $id = null)
    {
        parent::__construct(\sprintf('Message%s has been sent and can no longer be changed.', $id !== null ? ' #'.$id : ''));
    }
}
