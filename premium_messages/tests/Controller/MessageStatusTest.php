<?php

namespace App\Tests\Controller;

use App\Entity\Message;
use App\Enum\MessageStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class MessageStatusTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager->createQuery('DELETE FROM '.Message::class)->execute();
    }

    public function testDraftCanBeVerified(): void
    {
        $message = $this->createMessage(MessageStatus::Draft);

        $this->postStatus($message->getId(), 'verified');

        self::assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('verified', $data['status']);
        self::assertNotNull($data['verifiedAt']);
    }

    public function testDraftCanBeRejected(): void
    {
        $message = $this->createMessage(MessageStatus::Draft);

        $this->postStatus($message->getId(), 'rejected');

        self::assertResponseIsSuccessful();
        $this->entityManager->clear();
        $stored = $this->entityManager->find(Message::class, $message->getId());
        self::assertSame(MessageStatus::Rejected, $stored->getStatus());
        self::assertNull($stored->getVerifiedAt());
    }

    public function testNonDraftMessageCannotChangeStatus(): void
    {
        $message = $this->createMessage(MessageStatus::Verified);

        $this->postStatus($message->getId(), 'rejected');

        self::assertResponseStatusCodeSame(409);
    }

    public function testUnknownMessageReturnsNotFound(): void
    {
        $this->postStatus(999999, 'verified');

        self::assertResponseStatusCodeSame(404);
    }

    public function testOnlyVerifiedOrRejectedAreAllowed(): void
    {
        $message = $this->createMessage(MessageStatus::Draft);

        $this->postStatus($message->getId(), 'sent');

        self::assertResponseStatusCodeSame(422);
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

    private function postStatus(int $id, string $status): void
    {
        $this->client->request(
            'POST',
            "/messages/{$id}/status",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['status' => $status]),
        );
    }
}
