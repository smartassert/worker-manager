<?php

namespace App\Controller;

use SmartAssert\ServiceStatusInspector\ServiceStatusInspectorInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class HealthCheckController
{
    public const ROUTE = '/health-check';

    #[Route(self::ROUTE, name: 'health-check', methods: ['GET'])]
    public function get(ServiceStatusInspectorInterface $serviceStatusInspector): JsonResponse
    {
        return new JsonResponse(
            $serviceStatusInspector->get(),
            $serviceStatusInspector->isAvailable() ? 200 : 503
        );
    }
}
