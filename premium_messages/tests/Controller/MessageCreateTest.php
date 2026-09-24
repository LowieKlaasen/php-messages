<?php

namespace App\Tests\Controller;

use App\Entity\Message;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class MessageCreateTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        static::getContainer()->get(EntityManagerInterface::class)
            ->createQuery('DELETE FROM '.Message::class)->execute();
    }

    public function testDraftIsCreatedWithRecipient(): void
    {
        $this->post(['subject' => 'Subject', 'recipient' => 'recipient@example.com', 'content' => 'Content']);

        self::assertResponseStatusCodeSame(201);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('recipient@example.com', $data['recipient']);
        self::assertSame('draft', $data['status']);
    }

    #[TestWith([['subject' => 'Subject', 'recipient' => '', 'content' => 'Content']])]
    #[TestWith([['subject' => 'Subject', 'content' => 'Content']])]
    public function testRecipientIsRequired(array $payload): void
    {
        $this->post($payload);

        self::assertResponseStatusCodeSame(422);
    }

    private function post(array $payload): void
    {
        $this->client->request(
            'POST',
            '/messages',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload),
        );
    }
}
