<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class StatusController
{
    public const ROUTE = '/';

    public function __construct(
        private string $version,
        private bool $isReady,
    ) {
    }

    #[Route(self::ROUTE, name: 'status', methods: ['GET'])]
    public function get(): JsonResponse
    {
        return new JsonResponse([
            'version' => $this->version,
            'ready' => $this->isReady,
        ]);
    }
}
