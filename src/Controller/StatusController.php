<?php

namespace App\Controller;

use App\Model\Status;
use App\Services\Entity\Store\MessageStateStore;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class StatusController
{
    public const ROUTE = '/';

    public function __construct(
        private string $version,
        private MessageStateStore $messageStateStore,
    ) {
    }

    #[Route(self::ROUTE, name: 'status', methods: ['GET'])]
    public function get(): JsonResponse
    {
        return new JsonResponse(new Status(
            $this->version,
            $this->messageStateStore->count(),
        ));
    }
}
