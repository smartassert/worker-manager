<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Controller\MachineController;
use App\Entity\Machine;
use App\Message\MachineRequestInterface;
use App\Repository\ActionFailureRepository;
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
        }
    }

    public function testCreateCallsMachineRequestDispatcher(): void
    {
        $expectedMachineRequest = $this->callMachineRequestFactory(function (MachineRequestFactory $factory) {
            return $factory->createFindThenCreate(self::MACHINE_ID);
        });

        $machineRequestDispatcher = \Mockery::mock(MachineRequestDispatcher::class);
        $machineRequestDispatcher
            ->shouldReceive('dispatch')
            ->withArgs(function ($machineRequest) use ($expectedMachineRequest) {
                self::assertEquals($expectedMachineRequest, $machineRequest);

                return true;
            })
            ->andReturn(new Envelope($expectedMachineRequest))
        ;

        $controller = $this->createController($machineRequestDispatcher);

        $controller->create(self::MACHINE_ID);
    }

    public function testStatusMachineNotFoundCallsMachineRequestDispatcher(): void
    {
        $expectedMachineRequest = $this->callMachineRequestFactory(function (MachineRequestFactory $factory) {
            return $factory->createFindThenCheckIsActive(self::MACHINE_ID);
        });

        $machineRequestDispatcher = \Mockery::mock(MachineRequestDispatcher::class);
        $machineRequestDispatcher
            ->shouldReceive('dispatch')
            ->withArgs(function ($machineRequest) use ($expectedMachineRequest) {
                self::assertEquals($expectedMachineRequest, $machineRequest);

                return true;
            })
            ->andReturn(new Envelope($expectedMachineRequest))
        ;

        $controller = $this->createController($machineRequestDispatcher);

        $actionFailureRepository = self::getContainer()->get(ActionFailureRepository::class);
        \assert($actionFailureRepository instanceof ActionFailureRepository);

        $controller->status(self::MACHINE_ID, $actionFailureRepository);
    }

    public function testStatusMachineFoundDoesNotCallMachineRequestDispatcher(): void
    {
        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);
        $machineRepository->add(new Machine(self::MACHINE_ID));

        $machineRequestDispatcher = \Mockery::mock(MachineRequestDispatcher::class);
        $machineRequestDispatcher
            ->shouldNotReceive('dispatch')
        ;

        $controller = $this->createController($machineRequestDispatcher);

        $actionFailureRepository = self::getContainer()->get(ActionFailureRepository::class);
        \assert($actionFailureRepository instanceof ActionFailureRepository);

        $controller->status(self::MACHINE_ID, $actionFailureRepository);
    }

    public function testDeleteCallsMachineRequestDispatcher(): void
    {
        $expectedMachineRequest = $this->callMachineRequestFactory(function (MachineRequestFactory $factory) {
            return $factory->createDelete(self::MACHINE_ID);
        });

        $machineRequestDispatcher = \Mockery::mock(MachineRequestDispatcher::class);
        $machineRequestDispatcher
            ->shouldReceive('dispatch')
            ->withArgs(function ($machineRequest) use ($expectedMachineRequest) {
                self::assertEquals($expectedMachineRequest, $machineRequest);

                return true;
            })
            ->andReturn(new Envelope($expectedMachineRequest))
        ;

        $controller = $this->createController($machineRequestDispatcher);
        $controller->delete(self::MACHINE_ID);
    }

    /**
     * @param callable(MachineRequestFactory $factory): MachineRequestInterface $action
     *
     * @throws \Exception
     */
    private function callMachineRequestFactory(callable $action): MachineRequestInterface
    {
        $machineRequestFactory = self::getContainer()->get(MachineRequestFactory::class);
        \assert($machineRequestFactory instanceof MachineRequestFactory);

        $request = $action($machineRequestFactory);

        $requestIdFactory = self::getContainer()->get(RequestIdFactoryInterface::class);
        \assert($requestIdFactory instanceof SequentialRequestIdFactory);
        $requestIdFactory->reset();

        return $request;
    }

    private function createController(MachineRequestDispatcher $machineRequestDispatcher): MachineController
    {
        $machineRequestFactory = self::getContainer()->get(MachineRequestFactory::class);
        \assert($machineRequestFactory instanceof MachineRequestFactory);

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);

        return new MachineController($machineRequestDispatcher, $machineRequestFactory, $machineRepository);
    }
}
