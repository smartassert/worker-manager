<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application;

use App\Entity\Machine;
use App\Entity\MachineProvider;
use App\Enum\MachineState;
use App\Exception\MachineProvider\DigitalOcean\ApiLimitExceededException;
use App\Model\MachineActionInterface;
use App\Repository\MachineProviderRepository;
use App\Repository\MachineRepository;
use App\Services\Entity\Factory\CreateFailureFactory;
use App\Tests\Application\AbstractMachineTest;
use Doctrine\ORM\EntityManagerInterface;

class MachineTest extends AbstractMachineTest
{
    use GetApplicationClientTrait;

    private const MACHINE_ID = 'machine id';

    private MachineRepository $machineRepository;
    private MachineProviderRepository $machineProviderRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);
        $this->machineRepository = $machineRepository;

        $machineProviderRepository = self::getContainer()->get(MachineProviderRepository::class);
        \assert($machineProviderRepository instanceof MachineProviderRepository);
        $this->machineProviderRepository = $machineProviderRepository;
    }

    /**
     * @dataProvider createSuccessDataProvider
     *
     * @param string[] $expectedResponseIpAddresses
     */
    public function testCreateSuccess(
        ?Machine $existingMachine,
        array $expectedResponseIpAddresses,
    ): void {
        if ($existingMachine instanceof Machine) {
            $this->machineRepository->add($existingMachine);
        }

        $response = $this->makeValidCreateRequest(self::MACHINE_ID);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        $entityManager->close();

        $this->machineResponseAsserter->assertCreateResponse(
            $response,
            self::MACHINE_ID,
            $expectedResponseIpAddresses
        );

        $machine = $this->machineRepository->find(self::MACHINE_ID);
        self::assertInstanceOf(Machine::class, $machine);
        self::assertSame(self::MACHINE_ID, $machine->getId());
        self::assertSame(MachineState::CREATE_RECEIVED, $machine->getState());

        if ($existingMachine instanceof Machine) {
            self::assertSame($existingMachine->getIpAddresses(), $machine->getIpAddresses());
        }

        $machineProvider = $this->machineProviderRepository->find(self::MACHINE_ID);
        self::assertInstanceOf(MachineProvider::class, $machineProvider);
        self::assertSame(self::MACHINE_ID, $machineProvider->getId());
    }

    /**
     * @return array<mixed>
     */
    public function createSuccessDataProvider(): array
    {
        return [
            'no existing machine' => [
                'existingMachine' => null,
                'expectedResponseIpAddresses' => [],
            ],
            'existing machine state: find/not-found' => [
                'existingMachine' => new Machine(self::MACHINE_ID, MachineState::FIND_NOT_FOUND),
                'expectedResponseIpAddresses' => [],
            ],
            'existing machine state: create/failed, no ip addresses' => [
                'existingMachine' => new Machine(self::MACHINE_ID, MachineState::CREATE_FAILED),
                'expectedResponseIpAddresses' => [],
            ],
            'existing machine state: create/failed, has ip addresses' => [
                'existingMachine' => new Machine(
                    self::MACHINE_ID,
                    MachineState::CREATE_FAILED,
                    [
                        '127.0.0.1',
                        '10.0.0.1',
                    ]
                ),
                'expectedResponseIpAddresses' => [
                    '127.0.0.1',
                    '10.0.0.1',
                ],
            ],
        ];
    }

    public function testCreateIdTaken(): void
    {
        $this->machineRepository->add(new Machine(self::MACHINE_ID));

        $response = $this->makeValidCreateRequest(self::MACHINE_ID);

        $this->responseAsserter->assertJsonResponse(
            $response,
            400,
            [
                'type' => 'machine-create-request',
                'message' => 'id taken',
                'code' => 100,
            ]
        );
    }

    public function testStatusMachineNotFound(): void
    {
        $response = $this->makeValidStatusRequest(self::MACHINE_ID);

        $this->machineResponseAsserter->assertStatusResponse(
            $response,
            self::MACHINE_ID,
            MachineState::FIND_RECEIVED,
            false,
            false,
            []
        );
    }

    public function testStatusWithoutCreateFailure(): void
    {
        $this->machineRepository->add(new Machine(self::MACHINE_ID));

        $response = $this->makeValidStatusRequest(self::MACHINE_ID);

        $this->machineResponseAsserter->assertStatusResponse(
            $response,
            self::MACHINE_ID,
            MachineState::CREATE_RECEIVED,
            false,
            false,
            []
        );
    }

    public function testStatusWithCreateFailure(): void
    {
        $machine = new Machine(self::MACHINE_ID, MachineState::CREATE_FAILED);

        $this->machineRepository->add($machine);

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

        $this->machineResponseAsserter->assertStatusResponse(
            $response,
            self::MACHINE_ID,
            MachineState::CREATE_FAILED,
            true,
            false,
            [],
            [
                'code' => 2,
                'reason' => 'api limit exceeded',
                'context' => [
                    'reset-timestamp' => 123,
                ],
            ]
        );
    }

    public function testStatusHasActiveState(): void
    {
        $machine = new Machine(self::MACHINE_ID, MachineState::UP_ACTIVE);

        $this->machineRepository->add($machine);

        $response = $this->makeValidStatusRequest(self::MACHINE_ID);

        $this->machineResponseAsserter->assertStatusResponse(
            $response,
            self::MACHINE_ID,
            MachineState::UP_ACTIVE,
            false,
            true,
            []
        );
    }

    public function testDeleteLocalMachineExists(): void
    {
        $this->machineRepository->add(new Machine(self::MACHINE_ID));

        $response = $this->makeValidDeleteRequest(self::MACHINE_ID);

        $this->machineResponseAsserter->assertDeleteResponse($response, self::MACHINE_ID, false, []);
    }

    public function testDeleteLocalMachineDoesNotExist(): void
    {
        $machine = $this->machineRepository->find(self::MACHINE_ID);
        self::assertNull($machine);

        $response = $this->makeValidDeleteRequest(self::MACHINE_ID);
        $this->machineResponseAsserter->assertDeleteResponse($response, self::MACHINE_ID, false, []);

        $machine = $this->machineRepository->find(self::MACHINE_ID);
        self::assertInstanceOf(Machine::class, $machine);
    }
}
