<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Controller\MachineController;
use App\Entity\Machine;
use App\Entity\MachineProvider;
use App\Entity\MessageState;
use App\Exception\MachineProvider\DigitalOcean\ApiLimitExceededException;
use App\Model\MachineActionInterface;
use App\Services\Entity\Factory\CreateFailureFactory;
use App\Services\Entity\Store\MachineStore;
use App\Services\RequestIdFactoryInterface;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Services\Asserter\MessageStateEntityAsserter;
use App\Tests\Services\Asserter\MessengerAsserter;
use App\Tests\Services\SequentialRequestIdFactory;
use App\Tests\Services\TestMachineRequestFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class MachineControllerTest extends AbstractBaseFunctionalTest
{
    private const MACHINE_ID = 'machine id';

    private EntityManagerInterface $entityManager;
    private MessengerAsserter $messengerAsserter;
    private TestMachineRequestFactory $machineRequestFactory;
    private string $machineUrl;
    private SequentialRequestIdFactory $requestIdFactory;
    private MessageStateEntityAsserter $messageStateEntityAsserter;

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

        $messageStateEntityAsserter = self::getContainer()->get(MessageStateEntityAsserter::class);
        \assert($messageStateEntityAsserter instanceof MessageStateEntityAsserter);
        $this->messageStateEntityAsserter = $messageStateEntityAsserter;

        $this->machineUrl = str_replace(
            MachineController::PATH_COMPONENT_ID,
            self::MACHINE_ID,
            MachineController::PATH_MACHINE
        );
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

        $response = $this->makeCreateRequest();

        self::assertSame(202, $response->getStatusCode());
        self::assertInstanceOf(Response::class, $response);

        $machine = $this->entityManager->find(Machine::class, self::MACHINE_ID);
        self::assertInstanceOf(Machine::class, $machine);
        \assert($machine instanceof Machine);
        self::assertSame(self::MACHINE_ID, $machine->getId());

        $machineProvider = $this->entityManager->find(MachineProvider::class, self::MACHINE_ID);
        self::assertInstanceOf(MachineProvider::class, $machineProvider);
        self::assertSame(self::MACHINE_ID, $machineProvider->getId());
        \assert($machineProvider instanceof MachineProvider);

        $this->messengerAsserter->assertQueueCount(1);

        $this->requestIdFactory->reset();
        $expectedMessage = $this->machineRequestFactory->createFindThenCreate(self::MACHINE_ID);
        $this->messengerAsserter->assertMessageAtPositionEquals(0, $expectedMessage);

        $this->messageStateEntityAsserter->assertCount(1);
        $this->messageStateEntityAsserter->assertHas(new MessageState($expectedMessage->getUniqueId()));
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

        $response = $this->makeCreateRequest();

        $this->assertBadRequestResponse(
            [
                'type' => 'machine-create-request',
                'message' => 'id taken',
                'code' => 100,
            ],
            $response
        );
    }

    public function testStatusMachineNotFound(): void
    {
        $response = $this->makeStatusRequest();

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertJsonStringEqualsJsonString(
            (string) json_encode([
                'id' => self::MACHINE_ID,
                'state' => Machine::STATE_FIND_RECEIVED,
                'ip_addresses' => [],
            ]),
            (string) $response->getContent()
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

        $response = $this->makeStatusRequest();

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertJsonStringEqualsJsonString(
            (string) json_encode([
                'id' => self::MACHINE_ID,
                'state' => Machine::STATE_CREATE_RECEIVED,
                'ip_addresses' => [],
            ]),
            (string) $response->getContent()
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

        $response = $this->makeStatusRequest();

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertJsonStringEqualsJsonString(
            (string) json_encode([
                'id' => self::MACHINE_ID,
                'state' => Machine::STATE_CREATE_FAILED,
                'ip_addresses' => [],
                'create_failure' => [
                    'code' => 2,
                    'reason' => 'api limit exceeded',
                    'context' => [
                        'reset-timestamp' => 123,
                    ],
                ],
            ]),
            (string) $response->getContent()
        );

        $this->messengerAsserter->assertQueueIsEmpty();
    }

    public function testDeleteLocalMachineExists(): void
    {
        $machineStore = self::getContainer()->get(MachineStore::class);
        \assert($machineStore instanceof MachineStore);
        $machineStore->store(new Machine(self::MACHINE_ID));

        $response = $this->makeDeleteRequest();
        self::assertSame(202, $response->getStatusCode());

        $this->messengerAsserter->assertQueueCount(1);

        $this->requestIdFactory->reset();
        $expectedMessage = $this->machineRequestFactory->createDelete(self::MACHINE_ID);
        $this->messengerAsserter->assertMessageAtPositionEquals(0, $expectedMessage);
    }

    public function testDeleteLocalMachineDoesNotExist(): void
    {
        self::assertNull($this->entityManager->find(Machine::class, self::MACHINE_ID));

        $response = $this->makeDeleteRequest();
        self::assertSame(202, $response->getStatusCode());

        self::assertInstanceOf(Machine::class, $this->entityManager->find(Machine::class, self::MACHINE_ID));
        $this->messengerAsserter->assertQueueCount(1);

        $this->requestIdFactory->reset();
        $expectedMessage = $this->machineRequestFactory->createDelete(self::MACHINE_ID);
        $this->messengerAsserter->assertMessageAtPositionEquals(0, $expectedMessage);
    }

    /**
     * @param array<mixed> $expectedResponseBody
     */
    private function assertBadRequestResponse(array $expectedResponseBody, Response $response): void
    {
        self::assertSame(400, $response->getStatusCode());
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame($expectedResponseBody, json_decode((string) $response->getContent(), true));
    }

    private function makeCreateRequest(): Response
    {
        $this->client->request('POST', $this->machineUrl);

        return $this->client->getResponse();
    }

    private function makeStatusRequest(): Response
    {
        return $this->makeMachineRequest('GET');
    }

    private function makeDeleteRequest(): Response
    {
        return $this->makeMachineRequest('DELETE');
    }

    private function makeMachineRequest(string $method): Response
    {
        $this->client->request($method, $this->machineUrl);

        return $this->client->getResponse();
    }
}
