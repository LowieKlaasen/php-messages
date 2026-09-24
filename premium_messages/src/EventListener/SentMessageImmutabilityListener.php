<?php

namespace App\EventListener;

use App\Entity\Message;
use App\Enum\MessageStatus;
use App\Exception\MessageAlreadySentException;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

/**
 * Last line of defence: refuses to write any change to a message that was already sent,
 * even when the entity setters were bypassed (e.g. through reflection).
 */
#[AsEntityListener(event: Events::preUpdate, entity: Message::class)]
final class SentMessageImmutabilityListener
{
    public function preUpdate(Message $message, PreUpdateEventArgs $args): void
    {
        $originalStatus = $args->hasChangedField('status') ? $args->getOldValue('status') : $message->getStatus();

        if ($originalStatus === MessageStatus::Sent) {
            throw new MessageAlreadySentException($message->getId());
        }
    }
}
