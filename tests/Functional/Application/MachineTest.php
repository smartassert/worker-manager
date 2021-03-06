<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application;

use App\Entity\Machine;
use App\Entity\MachineProvider;
use App\Exception\MachineProvider\DigitalOcean\ApiLimitExceededException;
use App\Model\MachineActionInterface;
use App\Services\Entity\Factory\CreateFailureFactory;
use App\Services\Entity\Store\MachineStore;
use App\Services\RequestIdFactoryInterface;
use App\Tests\Application\AbstractMachineTest;
use App\Tests\Services\Asserter\MessengerAsserter;
use App\Tests\Services\SequentialRequestIdFactory;
use App\Tests\Services\TestMachineRequestFactory;
use Doctrine\ORM\EntityManagerInterface;

class MachineTest extends AbstractMachineTest
{
    use GetApplicationClientTrait;

    private const MACHINE_ID = 'machine id';

    private EntityManagerInterface $entityManager;
    private MessengerAsserter $messengerAsserter;
    private TestMachineRequestFactory $machineRequestFactory;
    private SequentialRequestIdFactory $requestIdFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        $this->entityManager = $entityManager;

        $messengerAsserter = self::getContainer()->get(MessengerAsserter::class);
        \assert($messengerAsserter instanceof MessengerAsserter);
        $this->messengerAsserter = $messengerAsserter;

        $machineRequestFactory = self::getContainer()->get(TestMachineRequestFactory::class);
        \assert($machineRequestFactory instanceof TestMachineRequestFactory);
        $this->machineRequestFactory = $machineRequestFactory;

        $requestIdFactory = self::getContainer()->get(RequestIdFactoryInterface::class);
        \assert($requestIdFactory instanceof SequentialRequestIdFactory);
        $this->requestIdFactory = $requestIdFactory;
    }

    /**
     * @dataProvider createSuccessDataProvider
     */
    public function testCreateSuccess(?Machine $existingMachine): void
    {
        $machineStore = self::getContainer()->get(MachineStore::class);
        \assert($machineStore instanceof MachineStore);
        if ($existingMachine instanceof Machine) {
            $machineStore->store($existingMachine);
        }

        $this->messengerAsserter->assertQueueIsEmpty();

        $response = $this->makeValidCreateRequest(self::MACHINE_ID);
        $this->responseAsserter->assertMachineCreateResponse($response);

        $machine = $this->entityManager->find(Machine::class, self::MACHINE_ID);
        self::assertInstanceOf(Machine::class, $machine);
        self::assertSame(self::MACHINE_ID, $machine->getId());

        $machineProvider = $this->entityManager->find(MachineProvider::class, self::MACHINE_ID);
        self::assertInstanceOf(MachineProvider::class, $machineProvider);
        self::assertSame(self::MACHINE_ID, $machineProvider->getId());

        $this->messengerAsserter->assertQueueCount(1);

        $this->requestIdFactory->reset();
        $expectedMessage = $this->machineRequestFactory->createFindThenCreate(self::MACHINE_ID);
        $this->messengerAsserter->assertMessageAtPositionEquals(0, $expectedMessage);
    }

    /**
     * @return array<mixed>
     */
    public function createSuccessDataProvider(): array
    {
        return [
            'no existing machine' => [
                'existingMachine' => null,
            ],
            'existing machine state: find/not-found' => [
                'existingMachine' => new Machine(self::MACHINE_ID, Machine::STATE_FIND_NOT_FOUND),
            ],
            'existing machine state: create/failed' => [
                'existingMachine' => new Machine(self::MACHINE_ID, Machine::STATE_CREATE_FAILED),
            ],
        ];
    }

    public function testCreateIdTaken(): void
    {
        $machineStore = self::getContainer()->get(MachineStore::class);
        \assert($machineStore instanceof MachineStore);
        $machineStore->store(new Machine(self::MACHINE_ID));

        $response = $this->makeValidCreateRequest(self::MACHINE_ID);

        $this->responseAsserter->assertMachineCreateBadRequestResponse($response, [
            'type' => 'machine-create-request',
            'message' => 'id taken',
            'code' => 100,
        ]);
    }

    public function testStatusMachineNotFound(): void
    {
        $response = $this->makeValidStatusRequest(self::MACHINE_ID);

        $this->responseAsserter->assertMachineStatusResponse(
            $response,
            self::MACHINE_ID,
            Machine::STATE_FIND_RECEIVED,
            []
        );

        $this->messengerAsserter->assertQueueCount(1);

        $this->requestIdFactory->reset();
        $expectedMessage = $this->machineRequestFactory->createFindThenCheckIsActive(self::MACHINE_ID);
        $this->messengerAsserter->assertMessageAtPositionEquals(0, $expectedMessage);
    }

    public function testStatusWithoutCreateFailure(): void
    {
        $machineStore = self::getContainer()->get(MachineStore::class);
        \assert($machineStore instanceof MachineStore);
        $machineStore->store(new Machine(self::MACHINE_ID));

        $response = $this->makeValidStatusRequest(self::MACHINE_ID);

        $this->responseAsserter->assertMachineStatusResponse(
            $response,
            self::MACHINE_ID,
            Machine::STATE_CREATE_RECEIVED,
            []
        );

        $this->messengerAsserter->assertQueueIsEmpty();
    }

    public function testStatusWithCreateFailure(): void
    {
        $machineStore = self::getContainer()->get(MachineStore::class);
        \assert($machineStore instanceof MachineStore);
        $machine = new Machine(self::MACHINE_ID, Machine::STATE_CREATE_FAILED);

        $machineStore->store($machine);

        $createFailureFactory = self::getContainer()->get(CreateFailureFactory::class);
        \assert($createFailureFactory instanceof CreateFailureFactory);
        $createFailureFactory->create(
            self::MACHINE_ID,
            new ApiLimitExceededException(
                123,
                self::MACHINE_ID,
                MachineActionInterface::ACTION_GET,
                new \Exception()
            )
        );

        $response = $this->makeValidStatusRequest(self::MACHINE_ID);

        $this->responseAsserter->assertMachineStatusResponse(
            $response,
            self::MACHINE_ID,
            Machine::STATE_CREATE_FAILED,
            [],
            [
                'code' => 2,
                'reason' => 'api limit exceeded',
                'context' => [
                    'reset-timestamp' => 123,
                ],
            ]
        );

        $this->messengerAsserter->assertQueueIsEmpty();
    }

    public function testDeleteLocalMachineExists(): void
    {
        $machineStore = self::getContainer()->get(MachineStore::class);
        \assert($machineStore instanceof MachineStore);
        $machineStore->store(new Machine(self::MACHINE_ID));

        $response = $this->makeValidDeleteRequest(self::MACHINE_ID);
        $this->responseAsserter->assertMachineDeleteResponse($response);

        $this->messengerAsserter->assertQueueCount(1);

        $this->requestIdFactory->reset();
        $expectedMessage = $this->machineRequestFactory->createDelete(self::MACHINE_ID);
        $this->messengerAsserter->assertMessageAtPositionEquals(0, $expectedMessage);
    }

    public function testDeleteLocalMachineDoesNotExist(): void
    {
        self::assertNull($this->entityManager->find(Machine::class, self::MACHINE_ID));

        $response = $this->makeValidDeleteRequest(self::MACHINE_ID);
        $this->responseAsserter->assertMachineDeleteResponse($response);

        self::assertInstanceOf(Machine::class, $this->entityManager->find(Machine::class, self::MACHINE_ID));
        $this->messengerAsserter->assertQueueCount(1);

        $this->requestIdFactory->reset();
        $expectedMessage = $this->machineRequestFactory->createDelete(self::MACHINE_ID);
        $this->messengerAsserter->assertMessageAtPositionEquals(0, $expectedMessage);
    }
}
