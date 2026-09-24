<?php

namespace App\Tests\Controller;

use App\Entity\Message;
use App\Enum\MessageStatus;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class MessageListTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager->createQuery('DELETE FROM '.Message::class)->execute();
    }

    public function testDefaultsToFirstPageOfTen(): void
    {
        $this->createMessages(25, MessageStatus::Draft);

        $data = $this->list();

        self::assertCount(10, $data['data']);
        self::assertSame([
            'pageNr' => 1,
            'pageSize' => 10,
            'pageRecordCount' => 10,
            'totalNrOfPages' => 3,
            'totalRecordCount' => 25,
            'filteredRecordCount' => 25,
            'hasPreviousPage' => false,
            'hasNextPage' => true,
        ], $data['pagination']);
    }

    public function testReturnsRequestedPageNewestFirst(): void
    {
        $this->createMessages(5, MessageStatus::Draft);

        $data = $this->list(['pageNr' => 2, 'pageSize' => 2]);

        self::assertSame(['Message 3', 'Message 2'], array_column($data['data'], 'subject'));
        self::assertSame(3, $data['pagination']['totalNrOfPages']);
        self::assertTrue($data['pagination']['hasPreviousPage']);
        self::assertTrue($data['pagination']['hasNextPage']);
    }

    public function testLastPageIsPartial(): void
    {
        $this->createMessages(5, MessageStatus::Draft);

        $data = $this->list(['pageNr' => 3, 'pageSize' => 2]);

        self::assertSame(['Message 1'], array_column($data['data'], 'subject'));
        self::assertSame(1, $data['pagination']['pageRecordCount']);
        self::assertFalse($data['pagination']['hasNextPage']);
    }

    public function testStatusFilterOnlyAffectsFilteredCount(): void
    {
        $this->createMessages(3, MessageStatus::Draft);
        $this->createMessages(2, MessageStatus::Verified);

        $data = $this->list(['status' => 'verified']);

        self::assertCount(2, $data['data']);
        self::assertSame(['verified', 'verified'], array_column($data['data'], 'status'));
        self::assertSame(5, $data['pagination']['totalRecordCount']);
        self::assertSame(2, $data['pagination']['filteredRecordCount']);
        self::assertSame(1, $data['pagination']['totalNrOfPages']);
    }

    public function testPageBeyondTheLastIsEmpty(): void
    {
        $this->createMessages(3, MessageStatus::Draft);

        $data = $this->list(['pageNr' => 5]);

        self::assertSame([], $data['data']);
        self::assertSame(0, $data['pagination']['pageRecordCount']);
        self::assertFalse($data['pagination']['hasNextPage']);
    }

    #[TestWith([['pageNr' => 0]])]
    #[TestWith([['pageNr' => 'abc']])]
    #[TestWith([['pageSize' => 0]])]
    #[TestWith([['pageSize' => 101]])]
    #[TestWith([['status' => 'unknown']])]
    public function testInvalidQueryIsRefused(array $query): void
    {
        $this->client->request('GET', '/messages', $query);

        self::assertResponseStatusCodeSame(400);
    }

    private function createMessages(int $count, MessageStatus $status): void
    {
        static $created = 0;

        for ($i = 1; $i <= $count; ++$i) {
            ++$created;
            $this->entityManager->persist(new Message()
                ->setSubject("Message {$i}")
                ->setRecipient('recipient@example.com')
                ->setContent('Content')
                // spaced out so "newest first" is deterministic
                ->setCreatedAt(new \DateTimeImmutable("2026-01-01 00:00:00 +{$created} minutes"))
                ->setStatus($status));
        }

        $this->entityManager->flush();
    }

    private function list(array $query = []): array
    {
        $this->client->request('GET', '/messages', $query);
        self::assertResponseIsSuccessful();

        return json_decode($this->client->getResponse()->getContent(), true);
    }
}
