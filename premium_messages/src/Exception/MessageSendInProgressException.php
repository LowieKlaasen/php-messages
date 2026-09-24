<?php

namespace App\Exception;

final class MessageSendInProgressException extends \RuntimeException
{
    public function __construct(?int $id = null)
    {
        parent::__construct(\sprintf('Message%s is already being sent.', $id !== null ? ' #'.$id : ''));
    }
}
