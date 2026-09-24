<?php

namespace App\Controller;

use App\Dto\CreateMessageRequest;
use App\Dto\ListMessagesQuery;
use App\Dto\UpdateMessageRequest;
use App\Dto\UpdateMessageStatusRequest;
use App\Entity\Message;
use App\Exception\MessageSendInProgressException;
use App\Exception\MessageSendingFailedException;
use App\Exception\UnchangedMessageException;
use App\Service\MessageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Workflow\Exception\NotEnabledTransitionException;

class MessageController extends AbstractController
{
    public function __construct(
        private readonly MessageService $messageService,
    ) {
    }

    #[Route('/messages', methods: ['GET'])]
    public function list(
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)] ListMessagesQuery $query = new ListMessagesQuery(),
    ): JsonResponse {
        return $this->json($this->messageService->findMessages($query));
    }

    #[Route('/messages/{id}', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Message $message): JsonResponse
    {
        return $this->json($message);
    }

    #[Route('/messages', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreateMessageRequest $request): JsonResponse
    {
        $message = $this->messageService->createDraft($request);

        return $this->json($message, Response::HTTP_CREATED);
    }

    #[Route('/messages/{id}/status', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function updateStatus(Message $message, #[MapRequestPayload] UpdateMessageStatusRequest $request): JsonResponse
    {
        try {
            $message = $this->messageService->changeStatus($message, $request->status);
        } catch (NotEnabledTransitionException) {
            throw new ConflictHttpException(\sprintf('Only draft messages can be verified or rejected; this message is "%s".', $message->getStatus()->value));
        }

        return $this->json($message);
    }

    #[Route('/messages/{id}', requirements: ['id' => '\d+'], methods: ['PUT'])]
    public function update(Message $message, #[MapRequestPayload] UpdateMessageRequest $request): JsonResponse
    {
        try {
            $message = $this->messageService->revise($message, $request);
        } catch (NotEnabledTransitionException) {
            throw new ConflictHttpException(\sprintf('Only rejected messages can be updated; this message is "%s".', $message->getStatus()->value));
        } catch (UnchangedMessageException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage(), $e);
        }

        return $this->json($message);
    }

    #[Route('/messages/{id}/send', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function send(Message $message): JsonResponse
    {
        try {
            $message = $this->messageService->send($message);
        } catch (NotEnabledTransitionException) {
            throw new ConflictHttpException(\sprintf('Only verified messages can be sent; this message is "%s".', $message->getStatus()->value));
        } catch (MessageSendInProgressException $e) {
            throw new ConflictHttpException($e->getMessage(), $e);
        } catch (MessageSendingFailedException $e) {
            throw new HttpException(Response::HTTP_BAD_GATEWAY, $e->getMessage(), $e);
        }

        return $this->json($message);
    }
}
