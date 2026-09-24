<?php

namespace App\Service;

use App\Dto\CreateMessageRequest;
use App\Dto\ListMessagesQuery;
use App\Dto\PaginatedList;
use App\Dto\PaginationMeta;
use App\Dto\UpdateMessageRequest;
use App\Entity\Message;
use App\Enum\MessageStatus;
use App\Exception\MessageSendInProgressException;
use App\Exception\MessageSendingFailedException;
use App\Exception\UnchangedMessageException;
use App\Repository\MessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Exception\RfcComplianceException;
use Symfony\Component\Workflow\Exception\NotEnabledTransitionException;
use Symfony\Component\Workflow\WorkflowInterface;

class MessageService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageRepository $messageRepository,
        #[Target('message')]
        private readonly WorkflowInterface $messageWorkflow,
        // Sends synchronously (bypassing the async Messenger queue) so a message is only marked sent once delivered.
        private readonly TransportInterface $mailerTransport,
        #[Autowire(env: 'MAILER_SENDER')]
        private readonly string $sender,
        private readonly LockFactory $lockFactory,
    ) {
    }

    public function createDraft(CreateMessageRequest $request): Message
    {
        $message = new Message()
            ->setSubject($request->subject)
            ->setRecipient($request->recipient)
            ->setContent($request->content)
            ->setStatus(MessageStatus::Draft)
            ->setCreatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($message);
        $this->entityManager->flush();

        return $message;
    }

    /**
     * @return PaginatedList<Message>
     */
    public function findMessages(ListMessagesQuery $query): PaginatedList
    {
        $page = $this->messageRepository->paginate($query->status, $query->pageNr, $query->pageSize);

        return new PaginatedList(
            $page->getItems(),
            PaginationMeta::fromWindowPage($page, totalRecordCount: $this->messageRepository->count()),
        );
    }

    /**
     * @throws NotEnabledTransitionException when the message is not in a status that allows this change
     */
    public function changeStatus(Message $message, MessageStatus $status): Message
    {
        $transition = match ($status) {
            MessageStatus::Verified => 'verify',
            MessageStatus::Rejected => 'reject',
            default => throw new \InvalidArgumentException(\sprintf('Status cannot be changed to "%s".', $status->value)),
        };

        $this->messageWorkflow->apply($message, $transition);

        if ($status === MessageStatus::Verified) {
            $message->setVerifiedAt(new \DateTimeImmutable());
        }

        $this->entityManager->flush();

        return $message;
    }

    /**
     * Updates a rejected message and puts it back to draft so it can be verified again.
     *
     * @throws NotEnabledTransitionException when the message is not rejected
     * @throws UnchangedMessageException     when the subject, recipient and content are all unchanged
     */
    public function revise(Message $message, UpdateMessageRequest $request): Message
    {
        $unchanged = $request->subject === $message->getSubject()
            && $request->recipient === $message->getRecipient()
            && $request->content === $message->getContent();

        if ($unchanged && $this->messageWorkflow->can($message, 'revise')) {
            throw new UnchangedMessageException();
        }

        $this->messageWorkflow->apply($message, 'revise');

        $message
            ->setSubject($request->subject)
            ->setRecipient($request->recipient)
            ->setContent($request->content);

        $this->entityManager->flush();

        return $message;
    }

    /**
     * Sends a verified message to its recipient, then marks it as sent.
     *
     * @throws NotEnabledTransitionException  when the message is not verified
     * @throws MessageSendingFailedException  when the email could not be delivered; the message stays verified
     * @throws MessageSendInProgressException when another request is sending the same message right now
     */
    public function send(Message $message): Message
    {
        $lock = $this->lockFactory->createLock('message-send-'.$message->getId());

        // Non-blocking: a concurrent request for the same message is refused instead of queued.
        if (!$lock->acquire()) {
            throw new MessageSendInProgressException($message->getId());
        }

        try {
            // Another request may have sent the message between loading it and acquiring the lock.
            $this->entityManager->refresh($message);

            return $this->sendLocked($message);
        } finally {
            $lock->release();
        }
    }

    private function sendLocked(Message $message): Message
    {
        $blockers = $this->messageWorkflow->buildTransitionBlockerList($message, 'send');
        if (!$blockers->isEmpty()) {
            throw new NotEnabledTransitionException($message, 'send', $this->messageWorkflow, $blockers);
        }

        try {
            $email = new Email()
                ->from($this->sender)
                ->to($message->getRecipient())
                ->subject($message->getSubject())
                ->text($message->getContent());

            $this->mailerTransport->send($email);
        } catch (RfcComplianceException $e) {
            throw new MessageSendingFailedException(\sprintf('The recipient "%s" is not a valid email address.', $message->getRecipient()), previous: $e);
        } catch (TransportExceptionInterface $e) {
            throw new MessageSendingFailedException('The message could not be delivered.', previous: $e);
        }

        // sentAt must be set before the transition: once the status is "sent", the message is immutable.
        $message->setSentAt(new \DateTimeImmutable());
        $this->messageWorkflow->apply($message, 'send');

        $this->entityManager->flush();

        return $message;
    }
}
