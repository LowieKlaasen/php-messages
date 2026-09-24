<?php

namespace App\Tests\Controller;

use App\Entity\Message;
use App\Enum\MessageStatus;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class MessageUpdateTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager->createQuery('DELETE FROM '.Message::class)->execute();
    }

    public function testRejectedMessageIsUpdatedAndBackToDraft(): void
    {
        $message = $this->createMessage(MessageStatus::Rejected);

        $this->put($message->getId(), 'New subject', 'New content');

        self::assertResponseIsSuccessful();
        $this->entityManager->clear();
        $stored = $this->entityManager->find(Message::class, $message->getId());
        self::assertSame(MessageStatus::Draft, $stored->getStatus());
        self::assertSame('New subject', $stored->getSubject());
        self::assertSame('New content', $stored->getContent());
    }

    public function testChangingOnlyTheContentIsEnough(): void
    {
        $message = $this->createMessage(MessageStatus::Rejected);

        $this->put($message->getId(), 'Subject', 'New content');

        self::assertResponseIsSuccessful();
    }

    public function testChangingOnlyTheRecipientIsEnough(): void
    {
        $message = $this->createMessage(MessageStatus::Rejected);

        $this->put($message->getId(), 'Subject', 'Content', 'other@example.com');

        self::assertResponseIsSuccessful();
        $this->entityManager->clear();
        self::assertSame('other@example.com', $this->entityManager->find(Message::class, $message->getId())->getRecipient());
    }

    public function testBlankRecipientIsRefused(): void
    {
        $message = $this->createMessage(MessageStatus::Rejected);

        $this->put($message->getId(), 'New subject', 'Content', '');

        self::assertResponseStatusCodeSame(422);
    }

    public function testIdenticalMessageIsRefused(): void
    {
        $message = $this->createMessage(MessageStatus::Rejected);

        $this->put($message->getId(), 'Subject', 'Content');

        self::assertResponseStatusCodeSame(422);
        $this->entityManager->clear();
        self::assertSame(MessageStatus::Rejected, $this->entityManager->find(Message::class, $message->getId())->getStatus());
    }

    #[TestWith(['draft'])]
    #[TestWith(['verified'])]
    #[TestWith(['sent'])]
    public function testOnlyRejectedMessagesCanBeUpdated(string $status): void
    {
        $message = $this->createMessage(MessageStatus::from($status));

        $this->put($message->getId(), 'New subject', 'New content');

        self::assertResponseStatusCodeSame(409);
    }

    public function testUnknownMessageReturnsNotFound(): void
    {
        $this->put(999999, 'New subject', 'New content');

        self::assertResponseStatusCodeSame(404);
    }

    private function createMessage(MessageStatus $status): Message
    {
        $message = new Message()
            ->setSubject('Subject')
            ->setRecipient('recipient@example.com')
            ->setContent('Content')
            ->setCreatedAt(new \DateTimeImmutable())
            ->setStatus($status);

        $this->entityManager->persist($message);
        $this->entityManager->flush();

        return $message;
    }

    private function put(int $id, string $subject, string $content, string $recipient = 'recipient@example.com'): void
    {
        $this->client->request(
            'PUT',
            "/messages/{$id}",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['subject' => $subject, 'recipient' => $recipient, 'content' => $content]),
        );
    }
}
