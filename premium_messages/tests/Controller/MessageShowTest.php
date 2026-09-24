<?php

namespace App\Tests\Controller;

use App\Entity\Message;
use App\Enum\MessageStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class MessageShowTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager->createQuery('DELETE FROM '.Message::class)->execute();
    }

    public function testReturnsTheMessage(): void
    {
        $message = new Message()
            ->setSubject('Subject')
            ->setRecipient('recipient@example.com')
            ->setContent('Content')
            ->setCreatedAt(new \DateTimeImmutable())
            ->setStatus(MessageStatus::Draft);
        $this->entityManager->persist($message);
        $this->entityManager->flush();

        $this->client->request('GET', "/messages/{$message->getId()}");

        self::assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame($message->getId(), $data['id']);
        self::assertSame('Subject', $data['subject']);
        self::assertSame('recipient@example.com', $data['recipient']);
        self::assertSame('Content', $data['content']);
        self::assertSame('draft', $data['status']);
    }

    public function testUnknownMessageReturnsNotFound(): void
    {
        $this->client->request('GET', '/messages/999999');

        self::assertResponseStatusCodeSame(404);
    }

    public function testNonNumericIdReturnsNotFound(): void
    {
        $this->client->request('GET', '/messages/abc');

        self::assertResponseStatusCodeSame(404);
    }
}
