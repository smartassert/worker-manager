<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Controller\MachineController;
use App\Entity\Machine;
use App\Enum\MachineState;
use App\MessageDispatcher\DeleteMachineDispatcherInterface;
use App\MessageDispatcher\FindMachineForCreationDispatcherInterface;
use App\MessageDispatcher\FindMachineForRetrievalDispatcherInterface;
use App\Repository\ActionFailureRepository;
use App\Repository\MachineRepository;
use App\Request\CreateMachineRequest;
use App\Tests\AbstractBaseFunctionalTestCase;
use App\Tests\Services\EntityRemover;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

class MachineControllerTest extends AbstractBaseFunctionalTestCase
{
    use MockeryPHPUnitIntegration;

    private const MACHINE_ID = 'machine id';

    private MachineController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeAllForEntity(Machine::class);
        }

        $controller = self::getContainer()->get(MachineController::class);
        \assert($controller instanceof MachineController);
        $this->controller = $controller;
    }

    public function testCreateCallsMachineRequestDispatcher(): void
    {
        $dispatcher = \Mockery::mock(FindMachineForCreationDispatcherInterface::class);
        $dispatcher
            ->shouldReceive('dispatchForMachine')
            ->withArgs(function (Machine $passedMachine) {
                self::assertEquals(
                    (function () {
                        $machine = new Machine(self::MACHINE_ID);
                        $machine->setState(MachineState::CREATE_RECEIVED);

                        return $machine;
                    })(),
                    $passedMachine,
                );

                return true;
            })
        ;

        $request = new CreateMachineRequest(self::MACHINE_ID, null);

        $this->controller->create($request, $dispatcher);
    }

    public function testStatusMachineNotFoundCallsMachineRequestDispatcher(): void
    {
        $dispatcher = \Mockery::mock(FindMachineForRetrievalDispatcherInterface::class);
        $dispatcher
            ->shouldReceive('dispatchForMachine')
            ->withArgs(function (Machine $passedMachine) {
                self::assertEquals(
                    (function () {
                        $machine = new Machine(self::MACHINE_ID);
                        $machine->setState(MachineState::FIND_RECEIVED);

                        return $machine;
                    })(),
                    $passedMachine,
                );

                return true;
            })
        ;

        $actionFailureRepository = self::getContainer()->get(ActionFailureRepository::class);
        \assert($actionFailureRepository instanceof ActionFailureRepository);

        $this->controller->status(self::MACHINE_ID, $dispatcher, $actionFailureRepository);
    }

    public function testStatusMachineFoundDoesNotCallMachineRequestDispatcher(): void
    {
        $machine = new Machine(self::MACHINE_ID);
        $machine->setState(MachineState::CREATE_RECEIVED);

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);
        $machineRepository->add($machine);

        $dispatcher = \Mockery::mock(FindMachineForRetrievalDispatcherInterface::class);
        $dispatcher
            ->shouldNotReceive('dispatchForMachine')
        ;

        $actionFailureRepository = self::getContainer()->get(ActionFailureRepository::class);
        \assert($actionFailureRepository instanceof ActionFailureRepository);

        $this->controller->status(self::MACHINE_ID, $dispatcher, $actionFailureRepository);
    }

    public function testDeleteDispatchesMessage(): void
    {
        $dispatcher = \Mockery::mock(DeleteMachineDispatcherInterface::class);
        $dispatcher
            ->shouldReceive('dispatchForMachine')
            ->withArgs(function (Machine $passedMachine) {
                self::assertEquals(
                    (function () {
                        $machine = new Machine(self::MACHINE_ID);
                        $machine->setState(MachineState::DELETE_RECEIVED);

                        return $machine;
                    })(),
                    $passedMachine,
                );

                return true;
            })
        ;

        $this->controller->delete(self::MACHINE_ID, $dispatcher);
    }
}
