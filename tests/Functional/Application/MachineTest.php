<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application;

use App\Entity\Machine;
use App\Enum\MachineAction;
use App\Enum\MachineProvider;
use App\Enum\MachineState;
use App\Enum\MachineStateCategory;
use App\Exception\MachineProvider\DigitalOcean\ApiLimitExceededException;
use App\Repository\MachineRepository;
use App\Services\Entity\Factory\ActionFailureFactory;
use App\Tests\Application\AbstractMachineTestCase;
use Doctrine\ORM\EntityManagerInterface;
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
     * @param string[] $expectedResponseIpAddresses
     */
    #[DataProvider('createSuccessDataProvider')]
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

        self::assertSame(202, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('content-type'));
        self::assertJsonStringEqualsJsonString(
            (string) json_encode([
                'id' => self::MACHINE_ID,
                'ip_addresses' => $expectedResponseIpAddresses,
                'state' => MachineState::CREATE_RECEIVED,
                'state_category' => MachineStateCategory::PRE_ACTIVE,
                'action_failure' => null,
                'has_failed_state' => false,
            ]),
            $response->getBody()->getContents()
        );

        $machine = $this->machineRepository->find(self::MACHINE_ID);
        self::assertInstanceOf(Machine::class, $machine);
        self::assertSame(self::MACHINE_ID, $machine->getId());
        self::assertSame(MachineState::CREATE_RECEIVED, $machine->getState());

        if ($existingMachine instanceof Machine) {
            self::assertSame($existingMachine->getIpAddresses(), $machine->getIpAddresses());
        }
    }

    /**
     * @return array<mixed>
     */
    public static function createSuccessDataProvider(): array
    {
        return [
            'no existing machine' => [
                'existingMachine' => null,
                'expectedResponseIpAddresses' => [],
            ],
            'existing machine state: find/not-found' => [
                'existingMachine' => (function () {
                    $machine = new Machine(self::MACHINE_ID);
                    $machine->setState(MachineState::FIND_NOT_FOUND);

                    return $machine;
                })(),
                'expectedResponseIpAddresses' => [],
            ],
            'existing machine state: create/failed, no ip addresses' => [
                'existingMachine' => (function () {
                    $machine = new Machine(self::MACHINE_ID);
                    $machine->setState(MachineState::CREATE_FAILED);

                    return $machine;
                })(),
                'expectedResponseIpAddresses' => [],
            ],
            'existing machine state: create/failed, has ip addresses' => [
                'existingMachine' => (function () {
                    $machine = new Machine(self::MACHINE_ID);
                    $machine->setState(MachineState::CREATE_FAILED);
                    $machine->setIpAddresses(['127.0.0.1', '10.0.0.1']);

                    return $machine;
                })(),
                'expectedResponseIpAddresses' => [
                    '127.0.0.1',
                    '10.0.0.1',
                ],
            ],
        ];
    }

    public function testCreateIdTaken(): void
    {
        $machine = new Machine(self::MACHINE_ID);
        $machine->setState(MachineState::CREATE_RECEIVED);
        $this->machineRepository->add($machine);

        $response = $this->makeValidCreateRequest(self::MACHINE_ID);

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('content-type'));
        self::assertJsonStringEqualsJsonString(
            (string) json_encode([
                'type' => 'machine-create-request',
                'message' => 'id taken',
                'code' => 100,
            ]),
            $response->getBody()->getContents()
        );
    }

    public function testStatusMachineNotFound(): void
    {
        $response = $this->makeValidStatusRequest(self::MACHINE_ID);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('content-type'));
        self::assertJsonStringEqualsJsonString(
            (string) json_encode([
                'id' => self::MACHINE_ID,
                'ip_addresses' => [],
                'state' => MachineState::FIND_RECEIVED,
                'state_category' => MachineStateCategory::FINDING,
                'action_failure' => null,
                'has_failed_state' => false,
            ]),
            $response->getBody()->getContents()
        );
    }

    public function testStatusWithoutActionFailure(): void
    {
        $machine = new Machine(self::MACHINE_ID);
        $machine->setState(MachineState::CREATE_RECEIVED);
        $this->machineRepository->add($machine);

        $response = $this->makeValidStatusRequest(self::MACHINE_ID);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('content-type'));
        self::assertJsonStringEqualsJsonString(
            (string) json_encode([
                'id' => self::MACHINE_ID,
                'ip_addresses' => [],
                'state' => MachineState::CREATE_RECEIVED,
                'state_category' => MachineStateCategory::PRE_ACTIVE,
                'action_failure' => null,
                'has_failed_state' => false,
            ]),
            $response->getBody()->getContents()
        );
    }

    public function testStatusWithActionFailure(): void
    {
        $machine = new Machine(self::MACHINE_ID);
        $machine->setState(MachineState::CREATE_FAILED);
        $this->machineRepository->add($machine);
        $machine->setProvider(MachineProvider::DIGITALOCEAN);

        $this->machineRepository->add($machine);

        $actionFailureFactory = self::getContainer()->get(ActionFailureFactory::class);
        \assert($actionFailureFactory instanceof ActionFailureFactory);
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

        $response = $this->makeValidStatusRequest(self::MACHINE_ID);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('content-type'));
        self::assertJsonStringEqualsJsonString(
            (string) json_encode([
                'id' => self::MACHINE_ID,
                'ip_addresses' => [],
                'state' => MachineState::CREATE_FAILED,
                'state_category' => MachineStateCategory::END,
                'action_failure' => [
                    'type' => 'vendor_request_limit_exceeded',
                    'action' => 'create',
                    'context' => [
                        'reset-timestamp' => 123,
                        'provider' => $machine->getProvider()?->value,
                    ],
                ],
                'has_failed_state' => true,
            ]),
            $response->getBody()->getContents()
        );
    }

    public function testStatusHasActiveState(): void
    {
        $machine = new Machine(self::MACHINE_ID);
        $machine->setState(MachineState::UP_ACTIVE);
        $this->machineRepository->add($machine);

        $response = $this->makeValidStatusRequest(self::MACHINE_ID);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('content-type'));
        self::assertJsonStringEqualsJsonString(
            (string) json_encode([
                'id' => self::MACHINE_ID,
                'ip_addresses' => [],
                'state' => MachineState::UP_ACTIVE,
                'state_category' => MachineStateCategory::ACTIVE,
                'action_failure' => null,
                'has_failed_state' => false,
            ]),
            $response->getBody()->getContents()
        );
    }

    #[DataProvider('statusHasFailedStateDataProvider')]
    public function testStatusHasFailedState(MachineState $state, bool $expected): void
    {
        $machine = new Machine(self::MACHINE_ID);
        $machine->setState($state);
        $this->machineRepository->add($machine);

        $response = $this->makeValidStatusRequest(self::MACHINE_ID);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('content-type'));

        $responseData = json_decode($response->getBody()->getContents(), true);
        \assert(is_array($responseData) && array_key_exists('has_failed_state', $responseData));

        self::assertSame($expected, $responseData['has_failed_state']);
    }

    /**
     * @return array<mixed>
     */
    public static function statusHasFailedStateDataProvider(): array
    {
        return [
            MachineState::UNKNOWN->value => [
                'state' => MachineState::UNKNOWN,
                'expected' => false,
            ],
            MachineState::FIND_RECEIVED->value => [
                'state' => MachineState::FIND_RECEIVED,
                'expected' => false,
            ],
            MachineState::FIND_FINDING->value => [
                'state' => MachineState::FIND_FINDING,
                'expected' => false,
            ],
            MachineState::FIND_NOT_FOUND->value => [
                'state' => MachineState::FIND_NOT_FOUND,
                'expected' => true,
            ],
            MachineState::FIND_NOT_FINDABLE->value => [
                'state' => MachineState::FIND_NOT_FINDABLE,
                'expected' => true,
            ],
            MachineState::CREATE_RECEIVED->value => [
                'state' => MachineState::CREATE_RECEIVED,
                'expected' => false,
            ],
            MachineState::CREATE_REQUESTED->value => [
                'state' => MachineState::CREATE_REQUESTED,
                'expected' => false,
            ],
            MachineState::CREATE_FAILED->value => [
                'state' => MachineState::CREATE_FAILED,
                'expected' => true,
            ],
            MachineState::UP_STARTED->value => [
                'state' => MachineState::UP_STARTED,
                'expected' => false,
            ],
            MachineState::UP_ACTIVE->value => [
                'state' => MachineState::UP_ACTIVE,
                'expected' => false,
            ],
            MachineState::DELETE_RECEIVED->value => [
                'state' => MachineState::DELETE_RECEIVED,
                'expected' => false,
            ],
            MachineState::DELETE_REQUESTED->value => [
                'state' => MachineState::DELETE_REQUESTED,
                'expected' => false,
            ],
            MachineState::DELETE_FAILED->value => [
                'state' => MachineState::DELETE_FAILED,
                'expected' => false,
            ],
            MachineState::DELETE_DELETED->value => [
                'state' => MachineState::DELETE_DELETED,
                'expected' => false,
            ],
        ];
    }

    public function testDeleteLocalMachineExists(): void
    {
        $machine = new Machine(self::MACHINE_ID);
        $machine->setState(MachineState::CREATE_RECEIVED);
        $this->machineRepository->add($machine);

        $response = $this->makeValidDeleteRequest(self::MACHINE_ID);

        self::assertSame(202, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('content-type'));
        self::assertJsonStringEqualsJsonString(
            (string) json_encode([
                'id' => self::MACHINE_ID,
                'ip_addresses' => [],
                'state' => MachineState::DELETE_RECEIVED,
                'state_category' => MachineStateCategory::ENDING,
                'action_failure' => null,
                'has_failed_state' => false,
            ]),
            $response->getBody()->getContents()
        );
    }

    public function testDeleteLocalMachineDoesNotExist(): void
    {
        $machine = $this->machineRepository->find(self::MACHINE_ID);
        self::assertNull($machine);

        $response = $this->makeValidDeleteRequest(self::MACHINE_ID);

        self::assertSame(202, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('content-type'));
        self::assertJsonStringEqualsJsonString(
            (string) json_encode([
                'id' => self::MACHINE_ID,
                'ip_addresses' => [],
                'state' => MachineState::DELETE_RECEIVED,
                'state_category' => MachineStateCategory::ENDING,
                'action_failure' => null,
                'has_failed_state' => false,
            ]),
            $response->getBody()->getContents()
        );

        $machine = $this->machineRepository->find(self::MACHINE_ID);
        self::assertInstanceOf(Machine::class, $machine);
    }
}
