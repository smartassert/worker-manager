<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application;

use App\Entity\Machine;
use App\Enum\MachineAction;
use App\Enum\MachineState;
use App\Enum\MachineStateCategory;
use App\Exception\MachineProvider\DigitalOcean\ApiLimitExceededException;
use App\Repository\MachineRepository;
use App\Services\Entity\Factory\ActionFailureFactory;
use App\Tests\Application\AbstractMachineTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class MachineTest extends AbstractMachineTestCase
{
    use GetApplicationClientTrait;

    private const MACHINE_ID = 'machine id';

    private MachineRepository $machineRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);
        $this->machineRepository = $machineRepository;
    }

    /**
     * @param callable(MachineRepository): void $setup
     * @param array<mixed>                      $expectedResponseData
     */
    #[DataProvider('createDataProvider')]
    public function testCreate(callable $setup, int $expectedStatusCode, array $expectedResponseData): void
    {
        $setup($this->machineRepository);

        $response = $this->makeValidCreateRequest(self::MACHINE_ID);

        self::assertSame($expectedStatusCode, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('content-type'));

        self::assertEquals(
            $expectedResponseData,
            json_decode($response->getBody()->getContents(), true),
        );
    }

    /**
     * @return array<mixed>
     */
    public static function createDataProvider(): array
    {
        return [
            'no existing machine' => [
                'setup' => function (MachineRepository $machineRepository): void {},
                'expectedStatusCode' => 202,
                'expectedResponseData' => [
                    'id' => self::MACHINE_ID,
                    'state' => MachineState::CREATE_RECEIVED->value,
                    'ip_addresses' => [],
                    'state_category' => MachineStateCategory::PRE_ACTIVE->value,
                    'action_failure' => null,
                    'has_failed_state' => false,
                    'has_active_state' => false,
                    'has_ending_state' => false,
                    'has_end_state' => false,
                ],
            ],
            'has existing machine; find/not-found' => [
                'setup' => function (MachineRepository $machineRepository): void {
                    $machine = new Machine(self::MACHINE_ID);
                    $machine->setState(MachineState::FIND_NOT_FOUND);

                    $machineRepository->add($machine);
                },
                'expectedStatusCode' => 202,
                'expectedResponseData' => [
                    'id' => self::MACHINE_ID,
                    'state' => MachineState::CREATE_RECEIVED->value,
                    'ip_addresses' => [],
                    'state_category' => MachineStateCategory::PRE_ACTIVE->value,
                    'action_failure' => null,
                    'has_failed_state' => false,
                    'has_active_state' => false,
                    'has_ending_state' => false,
                    'has_end_state' => false,
                ],
            ],
            'has existing machine; create/failed, no ip addresses' => [
                'setup' => function (MachineRepository $machineRepository): void {
                    $machine = new Machine(self::MACHINE_ID);
                    $machine->setState(MachineState::CREATE_FAILED);

                    $machineRepository->add($machine);
                },
                'expectedStatusCode' => 202,
                'expectedResponseData' => [
                    'id' => self::MACHINE_ID,
                    'state' => MachineState::CREATE_RECEIVED->value,
                    'ip_addresses' => [],
                    'state_category' => MachineStateCategory::PRE_ACTIVE->value,
                    'action_failure' => null,
                    'has_failed_state' => false,
                    'has_active_state' => false,
                    'has_ending_state' => false,
                    'has_end_state' => false,
                ],
            ],
            'has existing machine; create/failed, has ip addresses' => [
                'setup' => function (MachineRepository $machineRepository): void {
                    $machine = new Machine(self::MACHINE_ID);
                    $machine->setState(MachineState::CREATE_FAILED);
                    $machine->setIpAddresses(['127.0.0.1', '10.0.0.1']);

                    $machineRepository->add($machine);
                },
                'expectedStatusCode' => 202,
                'expectedResponseData' => [
                    'id' => self::MACHINE_ID,
                    'state' => MachineState::CREATE_RECEIVED->value,
                    'ip_addresses' => ['127.0.0.1', '10.0.0.1'],
                    'state_category' => MachineStateCategory::PRE_ACTIVE->value,
                    'action_failure' => null,
                    'has_failed_state' => false,
                    'has_active_state' => false,
                    'has_ending_state' => false,
                    'has_end_state' => false,
                ],
            ],
            'failed, id taken' => [
                'setup' => function (MachineRepository $machineRepository): void {
                    $machine = new Machine(self::MACHINE_ID);
                    $machine->setState(MachineState::CREATE_RECEIVED);

                    $machineRepository->add($machine);
                },
                'expectedStatusCode' => 400,
                'expectedResponseData' => [
                    'type' => 'machine-create-request',
                    'message' => 'id taken',
                    'code' => 100,
                ],
            ],
        ];
    }

    /**
     * @param callable(MachineRepository, ActionFailureFactory): void $setup
     * @param array<mixed>                                            $expectedResponseData
     */
    #[DataProvider('statusDataProvider')]
    public function testStatus(callable $setup, array $expectedResponseData): void
    {
        $actionFailureFactory = self::getContainer()->get(ActionFailureFactory::class);
        \assert($actionFailureFactory instanceof ActionFailureFactory);

        $setup($this->machineRepository, $actionFailureFactory);

        $response = $this->makeValidStatusRequest(self::MACHINE_ID);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('content-type'));

        self::assertEquals(
            $expectedResponseData,
            json_decode($response->getBody()->getContents(), true),
        );
    }

    /**
     * @return array<mixed>
     */
    public static function statusDataProvider(): array
    {
        return [
            'not found' => [
                'setup' => function (): void {},
                'expectedResponseData' => [
                    'id' => self::MACHINE_ID,
                    'state' => MachineState::FIND_RECEIVED->value,
                    'ip_addresses' => [],
                    'state_category' => MachineStateCategory::FINDING->value,
                    'action_failure' => null,
                    'has_failed_state' => false,
                    'has_active_state' => false,
                    'has_ending_state' => false,
                    'has_end_state' => false,
                ],
            ],
            'create/received, no action failure' => [
                'setup' => function (MachineRepository $machineRepository): void {
                    $machine = new Machine(self::MACHINE_ID);
                    $machine->setState(MachineState::CREATE_RECEIVED);
                    $machineRepository->add($machine);
                },
                'expectedResponseData' => [
                    'id' => self::MACHINE_ID,
                    'state' => MachineState::CREATE_RECEIVED->value,
                    'ip_addresses' => [],
                    'state_category' => MachineStateCategory::PRE_ACTIVE->value,
                    'action_failure' => null,
                    'has_failed_state' => false,
                    'has_active_state' => false,
                    'has_ending_state' => false,
                    'has_end_state' => false,
                ],
            ],
            'create/failed, has action failure' => [
                'setup' => function (
                    MachineRepository $machineRepository,
                    ActionFailureFactory $actionFailureFactory,
                ): void {
                    $machine = new Machine(self::MACHINE_ID);
                    $machine->setState(MachineState::CREATE_FAILED);
                    $machineRepository->add($machine);

                    $actionFailureFactory->create(
                        $machine,
                        MachineAction::CREATE,
                        new ApiLimitExceededException(
                            123,
                            self::MACHINE_ID,
                            MachineAction::GET,
                            new \Exception()
                        )
                    );
                },
                'expectedResponseData' => [
                    'id' => self::MACHINE_ID,
                    'state' => MachineState::CREATE_FAILED->value,
                    'ip_addresses' => [],
                    'state_category' => MachineStateCategory::END->value,
                    'action_failure' => [
                        'type' => 'vendor_request_limit_exceeded',
                        'action' => 'create',
                        'context' => [
                            'reset-timestamp' => 123,
                            'provider' => null,
                        ],
                    ],
                    'has_failed_state' => true,
                    'has_active_state' => false,
                    'has_ending_state' => false,
                    'has_end_state' => true,
                ],
            ],
            'state: ' . MachineState::UNKNOWN->value => [
                'setup' => function (MachineRepository $machineRepository): void {
                    $machine = new Machine(self::MACHINE_ID);
                    $machine->setState(MachineState::UNKNOWN);
                    $machineRepository->add($machine);
                },
                'expectedResponseData' => [
                    'id' => self::MACHINE_ID,
                    'state' => MachineState::UNKNOWN->value,
                    'ip_addresses' => [],
                    'state_category' => MachineStateCategory::UNKNOWN->value,
                    'action_failure' => null,
                    'has_failed_state' => false,
                    'has_active_state' => false,
                    'has_ending_state' => false,
                    'has_end_state' => false,
                ],
            ],
            'state: ' . MachineState::FIND_RECEIVED->value => [
                'setup' => function (MachineRepository $machineRepository): void {
                    $machine = new Machine(self::MACHINE_ID);
                    $machine->setState(MachineState::FIND_RECEIVED);
                    $machineRepository->add($machine);
                },
                'expectedResponseData' => [
                    'id' => self::MACHINE_ID,
                    'state' => MachineState::FIND_RECEIVED->value,
                    'ip_addresses' => [],
                    'state_category' => MachineStateCategory::FINDING->value,
                    'action_failure' => null,
                    'has_failed_state' => false,
                    'has_active_state' => false,
                    'has_ending_state' => false,
                    'has_end_state' => false,
                ],
            ],
            'state: ' . MachineState::FIND_FINDING->value => [
                'setup' => function (MachineRepository $machineRepository): void {
                    $machine = new Machine(self::MACHINE_ID);
                    $machine->setState(MachineState::FIND_FINDING);
                    $machineRepository->add($machine);
                },
                'expectedResponseData' => [
                    'id' => self::MACHINE_ID,
                    'state' => MachineState::FIND_FINDING->value,
                    'ip_addresses' => [],
                    'state_category' => MachineStateCategory::FINDING->value,
                    'action_failure' => null,
                    'has_failed_state' => false,
                    'has_active_state' => false,
                    'has_ending_state' => false,
                    'has_end_state' => false,
                ],
            ],
            'state: ' . MachineState::FIND_NOT_FOUND->value => [
                'setup' => function (MachineRepository $machineRepository): void {
                    $machine = new Machine(self::MACHINE_ID);
                    $machine->setState(MachineState::FIND_NOT_FOUND);
                    $machineRepository->add($machine);
                },
                'expectedResponseData' => [
                    'id' => self::MACHINE_ID,
                    'state' => MachineState::FIND_NOT_FOUND->value,
                    'ip_addresses' => [],
                    'state_category' => MachineStateCategory::END->value,
                    'action_failure' => null,
                    'has_failed_state' => true,
                    'has_active_state' => false,
                    'has_ending_state' => false,
                    'has_end_state' => true,
                ],
            ],
            'state: ' . MachineState::FIND_NOT_FINDABLE->value => [
                'setup' => function (MachineRepository $machineRepository): void {
                    $machine = new Machine(self::MACHINE_ID);
                    $machine->setState(MachineState::FIND_NOT_FINDABLE);
                    $machineRepository->add($machine);
                },
                'expectedResponseData' => [
                    'id' => self::MACHINE_ID,
                    'state' => MachineState::FIND_NOT_FINDABLE->value,
                    'ip_addresses' => [],
                    'state_category' => MachineStateCategory::END->value,
                    'action_failure' => null,
                    'has_failed_state' => true,
                    'has_active_state' => false,
                    'has_ending_state' => false,
                    'has_end_state' => true,
                ],
            ],
            'state: ' . MachineState::CREATE_RECEIVED->value => [
                'setup' => function (MachineRepository $machineRepository): void {
                    $machine = new Machine(self::MACHINE_ID);
                    $machine->setState(MachineState::CREATE_RECEIVED);
                    $machineRepository->add($machine);
                },
                'expectedResponseData' => [
                    'id' => self::MACHINE_ID,
                    'state' => MachineState::CREATE_RECEIVED->value,
                    'ip_addresses' => [],
                    'state_category' => MachineStateCategory::PRE_ACTIVE->value,
                    'action_failure' => null,
                    'has_failed_state' => false,
                    'has_active_state' => false,
                    'has_ending_state' => false,
                    'has_end_state' => false,
                ],
            ],
            'state: ' . MachineState::CREATE_REQUESTED->value => [
                'setup' => function (MachineRepository $machineRepository): void {
                    $machine = new Machine(self::MACHINE_ID);
                    $machine->setState(MachineState::CREATE_REQUESTED);
                    $machineRepository->add($machine);
                },
                'expectedResponseData' => [
                    'id' => self::MACHINE_ID,
                    'state' => MachineState::CREATE_REQUESTED->value,
                    'ip_addresses' => [],
                    'state_category' => MachineStateCategory::PRE_ACTIVE->value,
                    'action_failure' => null,
                    'has_failed_state' => false,
                    'has_active_state' => false,
                    'has_ending_state' => false,
                    'has_end_state' => false,
                ],
            ],
            'state: ' . MachineState::CREATE_FAILED->value => [
                'setup' => function (MachineRepository $machineRepository): void {
                    $machine = new Machine(self::MACHINE_ID);
                    $machine->setState(MachineState::CREATE_FAILED);
                    $machineRepository->add($machine);
                },
                'expectedResponseData' => [
                    'id' => self::MACHINE_ID,
                    'state' => MachineState::CREATE_FAILED->value,
                    'ip_addresses' => [],
                    'state_category' => MachineStateCategory::END->value,
                    'action_failure' => null,
                    'has_failed_state' => true,
                    'has_active_state' => false,
                    'has_ending_state' => false,
                    'has_end_state' => true,
                ],
            ],
            'state: ' . MachineState::UP_STARTED->value => [
                'setup' => function (MachineRepository $machineRepository): void {
                    $machine = new Machine(self::MACHINE_ID);
                    $machine->setState(MachineState::UP_STARTED);
                    $machineRepository->add($machine);
                },
                'expectedResponseData' => [
                    'id' => self::MACHINE_ID,
                    'state' => MachineState::UP_STARTED->value,
                    'ip_addresses' => [],
                    'state_category' => MachineStateCategory::PRE_ACTIVE->value,
                    'action_failure' => null,
                    'has_failed_state' => false,
                    'has_active_state' => false,
                    'has_ending_state' => false,
                    'has_end_state' => false,
                ],
            ],
            'state: ' . MachineState::UP_ACTIVE->value => [
                'setup' => function (MachineRepository $machineRepository): void {
                    $machine = new Machine(self::MACHINE_ID);
                    $machine->setState(MachineState::UP_ACTIVE);
                    $machineRepository->add($machine);
                },
                'expectedResponseData' => [
                    'id' => self::MACHINE_ID,
                    'state' => MachineState::UP_ACTIVE->value,
                    'ip_addresses' => [],
                    'state_category' => MachineStateCategory::ACTIVE->value,
                    'action_failure' => null,
                    'has_failed_state' => false,
                    'has_active_state' => true,
                    'has_ending_state' => false,
                    'has_end_state' => false,
                ],
            ],
            'state: ' . MachineState::DELETE_RECEIVED->value => [
                'setup' => function (MachineRepository $machineRepository): void {
                    $machine = new Machine(self::MACHINE_ID);
                    $machine->setState(MachineState::DELETE_RECEIVED);
                    $machineRepository->add($machine);
                },
                'expectedResponseData' => [
                    'id' => self::MACHINE_ID,
                    'state' => MachineState::DELETE_RECEIVED->value,
                    'ip_addresses' => [],
                    'state_category' => MachineStateCategory::ENDING->value,
                    'action_failure' => null,
                    'has_failed_state' => false,
                    'has_active_state' => false,
                    'has_ending_state' => true,
                    'has_end_state' => false,
                ],
            ],
            'state: ' . MachineState::DELETE_REQUESTED->value => [
                'setup' => function (MachineRepository $machineRepository): void {
                    $machine = new Machine(self::MACHINE_ID);
                    $machine->setState(MachineState::DELETE_REQUESTED);
                    $machineRepository->add($machine);
                },
                'expectedResponseData' => [
                    'id' => self::MACHINE_ID,
                    'state' => MachineState::DELETE_REQUESTED->value,
                    'ip_addresses' => [],
                    'state_category' => MachineStateCategory::ENDING->value,
                    'action_failure' => null,
                    'has_failed_state' => false,
                    'has_active_state' => false,
                    'has_ending_state' => true,
                    'has_end_state' => false,
                ],
            ],
            'state: ' . MachineState::DELETE_FAILED->value => [
                'setup' => function (MachineRepository $machineRepository): void {
                    $machine = new Machine(self::MACHINE_ID);
                    $machine->setState(MachineState::DELETE_FAILED);
                    $machineRepository->add($machine);
                },
                'expectedResponseData' => [
                    'id' => self::MACHINE_ID,
                    'state' => MachineState::DELETE_FAILED->value,
                    'ip_addresses' => [],
                    'state_category' => MachineStateCategory::END->value,
                    'action_failure' => null,
                    'has_failed_state' => false,
                    'has_active_state' => false,
                    'has_ending_state' => false,
                    'has_end_state' => true,
                ],
            ],
            'state: ' . MachineState::DELETE_DELETED->value => [
                'setup' => function (MachineRepository $machineRepository): void {
                    $machine = new Machine(self::MACHINE_ID);
                    $machine->setState(MachineState::DELETE_DELETED);
                    $machineRepository->add($machine);
                },
                'expectedResponseData' => [
                    'id' => self::MACHINE_ID,
                    'state' => MachineState::DELETE_DELETED->value,
                    'ip_addresses' => [],
                    'state_category' => MachineStateCategory::END->value,
                    'action_failure' => null,
                    'has_failed_state' => false,
                    'has_active_state' => false,
                    'has_ending_state' => false,
                    'has_end_state' => true,
                ],
            ],
        ];
    }

    /**
     * @param callable(MachineRepository): void $setup
     * @param array<mixed>                      $expectedResponseData
     */
    #[DataProvider('deleteDataProvider')]
    public function testDelete(callable $setup, array $expectedResponseData): void
    {
        $setup($this->machineRepository);

        $response = $this->makeValidDeleteRequest(self::MACHINE_ID);

        self::assertSame(202, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('content-type'));

        self::assertEquals(
            $expectedResponseData,
            json_decode($response->getBody()->getContents(), true),
        );
    }

    /**
     * @return array<mixed>
     */
    public static function deleteDataProvider(): array
    {
        return [
            'local machine exists' => [
                'setup' => function (MachineRepository $machineRepository): void {
                    $machine = new Machine(self::MACHINE_ID);
                    $machine->setState(MachineState::CREATE_RECEIVED);
                    $machineRepository->add($machine);
                },
                'expectedResponseData' => [
                    'id' => self::MACHINE_ID,
                    'state' => MachineState::DELETE_RECEIVED->value,
                    'ip_addresses' => [],
                    'state_category' => MachineStateCategory::ENDING->value,
                    'action_failure' => null,
                    'has_failed_state' => false,
                    'has_active_state' => false,
                    'has_ending_state' => true,
                    'has_end_state' => false,
                ],
            ],
            'local machine does not exist' => [
                'setup' => function (): void {},
                'expectedResponseData' => [
                    'id' => self::MACHINE_ID,
                    'state' => MachineState::DELETE_RECEIVED->value,
                    'ip_addresses' => [],
                    'state_category' => MachineStateCategory::ENDING->value,
                    'action_failure' => null,
                    'has_failed_state' => false,
                    'has_active_state' => false,
                    'has_ending_state' => true,
                    'has_end_state' => false,
                ],
            ],
        ];
    }
}
