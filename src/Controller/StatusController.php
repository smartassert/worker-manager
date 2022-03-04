<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class StatusController
{
    public const ROUTE = '/';

    public function __construct(
        private string $version,
    ) {
    }

    #[Route(self::ROUTE, name: 'status', methods: ['GET'])]
    public function get(): JsonResponse
    {
        return new JsonResponse([
            'version' => $this->version,
        ]);
    }
}
