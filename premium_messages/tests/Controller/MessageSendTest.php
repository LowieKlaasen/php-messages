<?php

namespace App\Tests\Controller;

use App\Entity\Message;
use App\Enum\MessageStatus;
use App\Service\MessageService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Workflow\Exception\NotEnabledTransitionException;

class MessageSendTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager->createQuery('DELETE FROM '.Message::class)->execute();
    }

    public function testVerifiedMessageIsSent(): void
    {
        $message = $this->createMessage(MessageStatus::Verified);

        $this->client->request('POST', "/messages/{$message->getId()}/send");

        self::assertResponseIsSuccessful();
        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertEmailAddressContains($email, 'To', 'recipient@example.com');
        self::assertEmailHeaderSame($email, 'Subject', 'Subject');
        self::assertEmailTextBodyContains($email, 'Content');

        $this->entityManager->clear();
        $stored = $this->entityManager->find(Message::class, $message->getId());
        self::assertSame(MessageStatus::Sent, $stored->getStatus());
        self::assertNotNull($stored->getSentAt());
    }

    #[TestWith(['draft'])]
    #[TestWith(['rejected'])]
    #[TestWith(['sent'])]
    public function testOnlyVerifiedMessagesCanBeSent(string $status): void
    {
        $message = $this->createMessage(MessageStatus::from($status));

        $this->client->request('POST', "/messages/{$message->getId()}/send");

        self::assertResponseStatusCodeSame(409);
        self::assertEmailCount(0);
    }

    public function testUnknownMessageReturnsNotFound(): void
    {
        $this->client->request('POST', '/messages/999999/send');

        self::assertResponseStatusCodeSame(404);
    }

    public function testFailedDeliveryKeepsMessageVerified(): void
    {
        $message = $this->createMessage(MessageStatus::Verified, recipient: 'not an email address');

        $this->client->request('POST', "/messages/{$message->getId()}/send");

        self::assertResponseStatusCodeSame(502);
        $this->entityManager->clear();
        $stored = $this->entityManager->find(Message::class, $message->getId());
        self::assertSame(MessageStatus::Verified, $stored->getStatus());
        self::assertNull($stored->getSentAt());
    }

    public function testMessageBeingSentByAnotherRequestIsRefused(): void
    {
        $message = $this->createMessage(MessageStatus::Verified);
        $lock = static::getContainer()->get(LockFactory::class)->createLock('message-send-'.$message->getId());
        self::assertTrue($lock->acquire());

        try {
            $this->client->request('POST', "/messages/{$message->getId()}/send");
        } finally {
            $lock->release();
        }

        self::assertResponseStatusCodeSame(409);
        self::assertEmailCount(0);
        $this->entityManager->clear();
        self::assertSame(MessageStatus::Verified, $this->entityManager->find(Message::class, $message->getId())->getStatus());
    }

    public function testStaleMessageSentInTheMeantimeIsNotSentAgain(): void
    {
        $message = $this->createMessage(MessageStatus::Verified);

        // Simulates another request that sent the message after this one loaded it.
        $this->entityManager->createQuery('UPDATE '.Message::class.' m SET m.status = :sent WHERE m.id = :id')
            ->execute(['sent' => MessageStatus::Sent, 'id' => $message->getId()]);

        $this->expectException(NotEnabledTransitionException::class);

        try {
            static::getContainer()->get(MessageService::class)->send($message);
        } finally {
            self::assertEmailCount(0);
        }
    }

    public function testSentMessageCannotBeRevised(): void
    {
        $message = $this->createMessage(MessageStatus::Sent);

        $this->client->request(
            'PUT',
            "/messages/{$message->getId()}",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['subject' => 'New', 'recipient' => 'new@example.com', 'content' => 'New']),
        );

        self::assertResponseStatusCodeSame(409);
        $this->entityManager->clear();
        self::assertSame('Subject', $this->entityManager->find(Message::class, $message->getId())->getSubject());
    }

    #[TestWith(['verified'])]
    #[TestWith(['rejected'])]
    public function testSentMessageStatusCannotBeChanged(string $status): void
    {
        $message = $this->createMessage(MessageStatus::Sent);

        $this->client->request(
            'POST',
            "/messages/{$message->getId()}/status",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['status' => $status]),
        );

        self::assertResponseStatusCodeSame(409);
    }

    private function createMessage(MessageStatus $status, string $recipient = 'recipient@example.com'): Message
    {
        $message = new Message()
            ->setSubject('Subject')
            ->setRecipient($recipient)
            ->setContent('Content')
            ->setCreatedAt(new \DateTimeImmutable())
            ->setStatus($status);

        $this->entityManager->persist($message);
        $this->entityManager->flush();

        return $message;
    }
}
