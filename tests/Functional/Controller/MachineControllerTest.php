<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Controller\MachineController;
use App\Entity\Machine;
use App\Entity\MachineProvider;
use App\Repository\MachineProviderRepository;
use App\Repository\MachineRepository;
use App\Services\MachineRequestDispatcher;
use App\Services\MachineRequestFactory;
use App\Services\RequestIdFactoryInterface;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\SequentialRequestIdFactory;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Symfony\Component\Messenger\Envelope;

class MachineControllerTest extends AbstractBaseFunctionalTest
{
    use MockeryPHPUnitIntegration;

    private const MACHINE_ID = 'machine id';

    protected function setUp(): void
    {
        parent::setUp();

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeAllForEntity(Machine::class);
            $entityRemover->removeAllForEntity(MachineProvider::class);
        }
    }

    public function testCreateCallsMachineRequestDispatcher(): void
    {
        $machineRequestFactory = self::getContainer()->get(MachineRequestFactory::class);
        \assert($machineRequestFactory instanceof MachineRequestFactory);

        $expectedMachineRequest = $machineRequestFactory->createFindThenCreate(self::MACHINE_ID);

        $requestIdFactory = self::getContainer()->get(RequestIdFactoryInterface::class);
        \assert($requestIdFactory instanceof SequentialRequestIdFactory);
        $requestIdFactory->reset();

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);

        $machineProviderRepository = self::getContainer()->get(MachineProviderRepository::class);
        \assert($machineProviderRepository instanceof MachineProviderRepository);

        $machineRequestDispatcher = \Mockery::mock(MachineRequestDispatcher::class);
        $machineRequestDispatcher
            ->shouldReceive('dispatch')
            ->withArgs(function ($machineRequest) use ($expectedMachineRequest) {
                self::assertEquals($expectedMachineRequest, $machineRequest);

                return true;
            })
            ->andReturn(new Envelope($expectedMachineRequest))
        ;

        $controller = new MachineController($machineRequestDispatcher, $machineRequestFactory, $machineRepository);

        $controller->create(self::MACHINE_ID, $machineProviderRepository);
    }
}
