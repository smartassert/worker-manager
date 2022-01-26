<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\HealthCheckController;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SmartAssert\ServiceStatusInspector\ServiceStatusInspectorInterface;

class HealthCheckControllerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function testGetUnavailable(): void
    {
        $serviceStatusInspector = \Mockery::mock(ServiceStatusInspectorInterface::class);
        $serviceStatusInspector
            ->shouldReceive('reset')
        ;
        $serviceStatusInspector
            ->shouldReceive('get')
            ->andReturn([])
        ;
        $serviceStatusInspector
            ->shouldReceive('isAvailable')
            ->andReturn(false)
        ;

        $response = (new HealthCheckController())->get($serviceStatusInspector);

        self::assertSame(503, $response->getStatusCode());
    }
}
