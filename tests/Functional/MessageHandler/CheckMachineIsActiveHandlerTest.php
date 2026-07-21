<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Machine;
use App\Enum\MachineState;
use App\Message\MachineRequestInterface;
use App\MessageHandler\CheckMachineIsActiveHandler;
use App\Repository\MachineRepository;
use App\Tests\AbstractBaseFunctionalTestCase;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\TestMachineRequestFactory;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class CheckMachineIsActiveHandlerTest extends AbstractBaseFunctionalTestCase
{
    use MockeryPHPUnitIntegration;

    private const MACHINE_ID = 'id';

    private MachineRepository $machineRepository;
    private Machine $machine;
    private TestMachineRequestFactory $machineRequestFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);
        $this->machineRepository = $machineRepository;

        $machineRequestFactory = self::getContainer()->get(TestMachineRequestFactory::class);
        \assert($machineRequestFactory instanceof TestMachineRequestFactory);
        $this->machineRequestFactory = $machineRequestFactory;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeAllForEntity(Machine::class);
        }

        $this->machine = new Machine(self::MACHINE_ID);
        $this->machine->setState(MachineState::CREATE_RECEIVED);
        $this->machineRepository->add($this->machine);
    }

    public function testHandlerExistsInContainerAndIsAMessageHandler(): void
    {
        $handler = self::getContainer()->get(CheckMachineIsActiveHandler::class);
        self::assertInstanceOf(CheckMachineIsActiveHandler::class, $handler);
        self::assertCount(1, new \ReflectionClass($handler::class)->getAttributes(AsMessageHandler::class));
    }

    #[DataProvider('invokeMachineIsActiveOrEndedDataProvider')]
    public function testInvokeMachineIsActiveOrEnded(MachineState $state): void
    {
        $messageBus = \Mockery::mock(MessageBusInterface::class);
        $messageBus->shouldNotReceive('dispatch');

        $handler = $this->createHandler($messageBus);

        $this->machine->setState($state);
        $this->machineRepository->add($this->machine);

        ($handler)($this->machineRequestFactory->createCheckIsActive(self::MACHINE_ID));
    }

    /**
     * @return array<mixed>
     */
    public static function invokeMachineIsActiveOrEndedDataProvider(): array
    {
        return [
            MachineState::CREATE_FAILED->value => [
                'state' => MachineState::CREATE_FAILED,
            ],
            MachineState::UP_ACTIVE->value => [
                'state' => MachineState::UP_ACTIVE,
            ],
            MachineState::DELETE_RECEIVED->value => [
                'state' => MachineState::DELETE_RECEIVED,
            ],
            MachineState::DELETE_REQUESTED->value => [
                'state' => MachineState::DELETE_REQUESTED,
            ],
            MachineState::DELETE_FAILED->value => [
                'state' => MachineState::DELETE_FAILED,
            ],
            MachineState::DELETE_DELETED->value => [
                'state' => MachineState::DELETE_DELETED,
            ],
        ];
    }

    #[DataProvider('handleMachineIsPreActiveDataProvider')]
    public function testHandleMachineIsPreActive(MachineState $state): void
    {
        $this->machine->setState($state);
        $this->machineRepository->add($this->machine);

        $request = $this->machineRequestFactory->createCheckIsActive(self::MACHINE_ID);
        $expectedMachineRequestCollection = array_merge(
            $request->getOnSuccessCollection(),
            [$request],
        );

        $expectedRequestIndex = 0;
        $messageBus = \Mockery::mock(MessageBusInterface::class);
        $messageBus
            ->shouldReceive('dispatch')
            ->withArgs(function (
                MachineRequestInterface $machineRequest
            ) use (
                $expectedMachineRequestCollection,
                &$expectedRequestIndex
            ) {
                $expectedRequest = $expectedMachineRequestCollection[$expectedRequestIndex];

                self::assertEquals($expectedRequest, $machineRequest);
                ++$expectedRequestIndex;

                return true;
            })
            ->andReturn(new Envelope(\Mockery::mock(MachineRequestInterface::class)))
        ;

        $handler = $this->createHandler($messageBus);

        ($handler)($request);
    }

    /**
     * @return array<mixed>
     */
    public static function handleMachineIsPreActiveDataProvider(): array
    {
        return [
            MachineState::CREATE_RECEIVED->value => [
                'state' => MachineState::CREATE_RECEIVED,
            ],
            MachineState::CREATE_REQUESTED->value => [
                'state' => MachineState::CREATE_REQUESTED,
            ],
            MachineState::UP_STARTED->value => [
                'state' => MachineState::UP_STARTED,
            ],
        ];
    }

    public function testHandleMachineDoesNotExist(): void
    {
        $messageBus = \Mockery::mock(MessageBusInterface::class);
        $messageBus->shouldNotReceive('dispatch');

        $handler = $this->createHandler($messageBus);

        $message = $this->machineRequestFactory->createCheckIsActive('invalid machine id');

        ($handler)($message);
    }

    private function createHandler(MessageBusInterface $messageBus): CheckMachineIsActiveHandler
    {
        return new CheckMachineIsActiveHandler($messageBus, $this->machineRepository);
    }
}
