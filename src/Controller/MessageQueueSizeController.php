<?php

namespace App\Controller;

use App\Services\Entity\Store\MessageStateStore;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MessageQueueSizeController
{
    public const ROUTE = '/message-queue-size';

    public function __construct(
        private MessageStateStore $messageStateStore,
    ) {
    }

    #[Route(self::ROUTE, name: 'message-queue-size', methods: ['GET'])]
    public function get(): Response
    {
        return new Response((string) $this->messageStateStore->count());
    }
}
