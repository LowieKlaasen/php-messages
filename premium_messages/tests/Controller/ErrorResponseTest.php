<?php

namespace App\Tests\Controller;

use App\Entity\Message;
use App\Enum\MessageStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Runs with debug off, like production: Symfony hides exception messages there unless we expose them.
 */
class ErrorResponseTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient(['debug' => false]);
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager->createQuery('DELETE FROM '.Message::class)->execute();
    }

    public function testErrorsAreJsonWithoutAnAcceptHeader(): void
    {
        $message = $this->createMessage(MessageStatus::Draft);

        $this->client->request('POST', "/messages/{$message->getId()}/send");

        self::assertResponseStatusCodeSame(409);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertSame([
            'type' => 'https://tools.ietf.org/html/rfc2616#section-10',
            'title' => 'An error occurred',
            'status' => 409,
            'detail' => 'Only verified messages can be sent; this message is "draft".',
        ], $this->responseData());
    }

    public function testNotFoundDoesNotLeakInternals(): void
    {
        $this->client->request('GET', '/messages/999999');

        self::assertResponseStatusCodeSame(404);
        self::assertSame('Message not found.', $this->responseData()['detail']);
    }

    public function testDomainErrorMessageIsShown(): void
    {
        $message = $this->createMessage(MessageStatus::Rejected);

        $this->client->request(
            'PUT',
            "/messages/{$message->getId()}",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['subject' => 'Subject', 'recipient' => 'recipient@example.com', 'content' => 'Content']),
        );

        self::assertResponseStatusCodeSame(422);
        self::assertSame('The subject, recipient or content must differ from the rejected version.', $this->responseData()['detail']);
    }

    public function testValidationErrorsListTheInvalidFields(): void
    {
        $this->client->request(
            'POST',
            '/messages',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['subject' => '', 'recipient' => '', 'content' => 'Content']),
        );

        self::assertResponseStatusCodeSame(422);
        $data = $this->responseData();
        self::assertSame(['subject', 'recipient'], array_column($data['violations'], 'propertyPath'));
    }

    public function testNoDebugInformationIsExposed(): void
    {
        $this->client->request('GET', '/messages/999999');

        self::assertArrayNotHasKey('trace', $this->responseData());
        self::assertArrayNotHasKey('class', $this->responseData());
    }

    private function responseData(): array
    {
        return json_decode($this->client->getResponse()->getContent(), true);
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
}
