<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\ServiceStatusInspector;

use App\Services\ServiceStatusInspector\DoctrineEntityMappingInspector;
use Doctrine\ORM\EntityManagerInterface;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class DoctrineEntityMappingInspectorTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function testInvokeSuccess(): void
    {
        $entityManager = \Mockery::mock(EntityManagerInterface::class);

        $inspector = new DoctrineEntityMappingInspector($entityManager, []);

        ($inspector)();
        self::expectNotToPerformAssertions();
    }

    public function testInvokeFailure(): void
    {
        $exception = new \Exception();

        $entityManager = \Mockery::mock(EntityManagerInterface::class);
        $entityManager
            ->shouldReceive('getRepository')
            ->andThrow($exception)
        ;

        $inspector = new DoctrineEntityMappingInspector($entityManager, [
            self::class,
        ]);

        self::expectExceptionObject($exception);

        ($inspector)();
    }
}
