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

        $this->jsonResponseAsserter->assertJsonResponse(
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

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('content-type'));
        self::assertJsonStringEqualsJsonString(
            (string) json_encode([
                'id' => self::MACHINE_ID,
                'ip_addresses' => [],
                'state' => MachineState::FIND_RECEIVED,
                'state_category' => MachineStateCategory::FINDING,
            ]),
            $response->getBody()->getContents()
        );
    }

    public function testStatusWithoutActionFailure(): void
    {
        $this->machineRepository->add(new Machine(self::MACHINE_ID));

        $response = $this->makeValidStatusRequest(self::MACHINE_ID);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('content-type'));
        self::assertJsonStringEqualsJsonString(
            (string) json_encode([
                'id' => self::MACHINE_ID,
                'ip_addresses' => [],
                'state' => MachineState::CREATE_RECEIVED,
                'state_category' => MachineStateCategory::PRE_ACTIVE,
            ]),
            $response->getBody()->getContents()
        );
    }

    public function testStatusWithActionFailure(): void
    {
        $machine = new Machine(self::MACHINE_ID, MachineState::CREATE_FAILED);
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
            ]),
            $response->getBody()->getContents()
        );
    }

    public function testStatusHasActiveState(): void
    {
        $machine = new Machine(self::MACHINE_ID, MachineState::UP_ACTIVE);

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
            ]),
            $response->getBody()->getContents()
        );
    }

    public function testDeleteLocalMachineExists(): void
    {
        $this->machineRepository->add(new Machine(self::MACHINE_ID));

        $response = $this->makeValidDeleteRequest(self::MACHINE_ID);

        self::assertSame(202, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('content-type'));
        self::assertJsonStringEqualsJsonString(
            (string) json_encode([
                'id' => self::MACHINE_ID,
                'ip_addresses' => [],
                'state' => MachineState::DELETE_RECEIVED,
                'state_category' => MachineStateCategory::ENDING,
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
            ]),
            $response->getBody()->getContents()
        );

        $machine = $this->machineRepository->find(self::MACHINE_ID);
        self::assertInstanceOf(Machine::class, $machine);
    }
}
