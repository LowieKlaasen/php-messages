<?php

namespace App\Tests\Entity;

use App\Entity\Message;
use App\Enum\MessageStatus;
use App\Exception\MessageAlreadySentException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class SentMessageImmutabilityTest extends KernelTestCase
{
    public static function setters(): iterable
    {
        yield 'subject' => [fn (Message $m) => $m->setSubject('New')];
        yield 'recipient' => [fn (Message $m) => $m->setRecipient('new@example.com')];
        yield 'content' => [fn (Message $m) => $m->setContent('New')];
        yield 'status' => [fn (Message $m) => $m->setStatus(MessageStatus::Draft)];
        yield 'createdAt' => [fn (Message $m) => $m->setCreatedAt(new \DateTimeImmutable())];
        yield 'verifiedAt' => [fn (Message $m) => $m->setVerifiedAt(null)];
        yield 'sentAt' => [fn (Message $m) => $m->setSentAt(null)];
    }

    #[DataProvider('setters')]
    public function testSettersRefuseChangesOnceSent(\Closure $change): void
    {
        $message = $this->createMessage()->setStatus(MessageStatus::Sent);

        $this->expectException(MessageAlreadySentException::class);
        $change($message);
    }

    public function testDatabaseRefusesChangesThatBypassTheSetters(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->createQuery('DELETE FROM '.Message::class)->execute();

        $message = $this->createMessage()->setSentAt(new \DateTimeImmutable())->setStatus(MessageStatus::Sent);
        $entityManager->persist($message);
        $entityManager->flush();

        new \ReflectionProperty(Message::class, 'subject')->setValue($message, 'Tampered');

        $this->expectException(MessageAlreadySentException::class);
        $entityManager->flush();
    }

    private function createMessage(): Message
    {
        return new Message()
            ->setSubject('Subject')
            ->setRecipient('recipient@example.com')
            ->setContent('Content')
            ->setStatus(MessageStatus::Verified)
            ->setCreatedAt(new \DateTimeImmutable());
    }
}
