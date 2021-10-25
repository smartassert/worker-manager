<?php

namespace App\Controller;

use App\Services\ServiceStatusInspector\ServiceStatusInspector;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class HealthCheckController
{
    public const ROUTE = '/health-check';

    #[Route(self::ROUTE, name: 'health-check', methods: ['GET'])]
    public function get(ServiceStatusInspector $serviceStatusInspector): JsonResponse
    {
        $serviceStatusInspector->reset();

        return new JsonResponse(
            $serviceStatusInspector->get(),
            $serviceStatusInspector->isAvailable() ? 200 : 503
        );
    }
}
