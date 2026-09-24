<?php

namespace App\Repository;

use App\Entity\Message;
use App\Enum\MessageStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\OffsetPaginator;
use Doctrine\ORM\Tools\Pagination\Window;
use Doctrine\ORM\Tools\Pagination\WindowPage;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Message>
 */
class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    /**
     * @return WindowPage<Message> newest first; its total count is the number of records matching the filter
     */
    public function paginate(?MessageStatus $status, int $pageNr, int $pageSize): WindowPage
    {
        $queryBuilder = $this->createQueryBuilder('m')
            ->orderBy('m.createdAt', 'DESC')
            ->addOrderBy('m.id', 'DESC');

        if ($status !== null) {
            $queryBuilder->andWhere('m.status = :status')->setParameter('status', $status);
        }

        return new OffsetPaginator(fetchJoinCollection: false)
            ->paginate($queryBuilder, Window::fromPageNumberAndSize($pageNr, $pageSize));
    }
}
