<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Machine;
use App\Entity\MachineProvider;
use App\Exception\MachineProvider\Exception;
use App\Exception\MachineProvider\ExceptionInterface;
use App\Exception\MachineProvider\ProviderMachineNotFoundException;
use App\Model\DigitalOcean\RemoteMachine;
use App\Model\MachineActionInterface;
use App\Model\ProviderInterface;
use App\Repository\MachineProviderRepository;
use App\Services\MachineManager;
use App\Services\MachineNameFactory;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\DataProvider\RemoteRequestThrowsExceptionDataProviderTrait;
use App\Tests\Proxy\DigitalOceanV2\Api\DropletApiProxy;
use App\Tests\Proxy\DigitalOceanV2\ClientProxy;
use App\Tests\Services\EntityRemover;
use DigitalOceanV2\Client;
use DigitalOceanV2\Entity\Droplet as DropletEntity;
use DigitalOceanV2\Exception\ValidationFailedException;
use Psr\Http\Message\ResponseInterface;

class MachineManagerTest extends AbstractBaseFunctionalTest
{
    use RemoteRequestThrowsExceptionDataProviderTrait;

    private const MACHINE_ID = 'machine id';

    private MachineManager $machineManager;
    private DropletApiProxy $dropletApiProxy;
    private string $machineName;
    private Client $digitalOceanClient;

    protected function setUp(): void
    {
        parent::setUp();

        $machineManager = self::getContainer()->get(MachineManager::class);
        \assert($machineManager instanceof MachineManager);
        $this->machineManager = $machineManager;

        $dropletApiProxy = self::getContainer()->get(DropletApiProxy::class);
        \assert($dropletApiProxy instanceof DropletApiProxy);
        $this->dropletApiProxy = $dropletApiProxy;

        $machineNameFactory = self::getContainer()->get(MachineNameFactory::class);
        \assert($machineNameFactory instanceof MachineNameFactory);
        $this->machineName = $machineNameFactory->create(self::MACHINE_ID);

        $digitalOceanClient = self::getContainer()->get(Client::class);
        \assert($digitalOceanClient instanceof Client);
        $this->digitalOceanClient = $digitalOceanClient;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeAllForEntity(Machine::class);
            $entityRemover->removeAllForEntity(MachineProvider::class);
        }
    }

    public function testCreateSuccess(): void
    {
        $ipAddresses = ['10.0.0.1', '127.0.0.1'];

        $dropletData = [
            'networks' => (object) [
                'v4' => [
                    (object) [
                        'ip_address' => $ipAddresses[0],
                        'type' => 'public',
                    ],
                    (object) [
                        'ip_address' => $ipAddresses[1],
                        'type' => 'public',
                    ],
                ],
            ],
        ];

        $droplet = new DropletEntity($dropletData);
        $this->dropletApiProxy->prepareCreateCall($this->machineName, $droplet);

        $remoteMachine = $this->machineManager->create($this->createMachineProvider());

        self::assertEquals(new RemoteMachine($droplet), $remoteMachine);
    }

    /**
     * @dataProvider remoteRequestThrowsExceptionDataProvider
     *
     * @param class-string $expectedExceptionClass
     */
    public function testCreateThrowsException(
        \Exception $dropletApiException,
        ResponseInterface $apiResponse,
        string $expectedExceptionClass
    ): void {
        $this->doActionThrowsExceptionTest(
            function () use ($dropletApiException) {
                $this->dropletApiProxy->prepareCreateCall($this->machineName, $dropletApiException);
                $this->machineManager->create($this->createMachineProvider());
            },
            MachineActionInterface::ACTION_CREATE,
            $dropletApiException,
            $apiResponse,
            $expectedExceptionClass,
        );
    }

    public function testCreateThrowsDropletLimitException(): void
    {
        $dropletApiException = new ValidationFailedException(
            'creating this/these droplet(s) will exceed your droplet limit',
            422
        );

        $this->dropletApiProxy->prepareCreateCall($this->machineName, $dropletApiException);

        try {
            $this->machineManager->create($this->createMachineProvider());
            self::fail(ExceptionInterface::class . ' not thrown');
        } catch (ExceptionInterface $exception) {
            self::assertSame($dropletApiException, $exception->getRemoteException());
        }
    }

    public function testGetSuccess(): void
    {
        $ipAddresses = ['10.0.0.1', '127.0.0.1'];

        $dropletData = [
            'networks' => (object) [
                'v4' => [
                    (object) [
                        'ip_address' => $ipAddresses[0],
                        'type' => 'public',
                    ],
                    (object) [
                        'ip_address' => $ipAddresses[1],
                        'type' => 'public',
                    ],
                ],
            ],
        ];

        $expectedDropletEntity = new DropletEntity($dropletData);
        $this->dropletApiProxy->withGetAllCall($this->machineName, [$expectedDropletEntity]);

        $remoteMachine = $this->machineManager->get($this->createMachineProvider());

        self::assertEquals(new RemoteMachine($expectedDropletEntity), $remoteMachine);
    }

    public function testGetThrowsMachineNotFoundException(): void
    {
        $this->dropletApiProxy->withGetAllCall(
            $this->machineName,
            []
        );

        $machineProvider = $this->createMachineProvider();
        self::expectExceptionObject(new ProviderMachineNotFoundException(
            $machineProvider->getId(),
            $machineProvider->getName()
        ));

        $this->machineManager->get($machineProvider);
    }

    /**
     * @dataProvider remoteRequestThrowsExceptionDataProvider
     *
     * @param class-string $expectedExceptionClass
     */
    public function testGetThrowsException(
        \Exception $dropletApiException,
        ResponseInterface $apiResponse,
        string $expectedExceptionClass
    ): void {
        $this->doActionThrowsExceptionTest(
            function () use ($dropletApiException) {
                $this->dropletApiProxy->withGetAllCall($this->machineName, $dropletApiException);
                $this->machineManager->get($this->createMachineProvider());
            },
            MachineActionInterface::ACTION_GET,
            $dropletApiException,
            $apiResponse,
            $expectedExceptionClass,
        );
    }

    public function testDeleteSuccess(): void
    {
        $this->dropletApiProxy->withRemoveTaggedCall($this->machineName);

        $this->machineManager->delete($this->createMachineProvider());
        self::expectNotToPerformAssertions();
    }

    /**
     * @dataProvider remoteRequestThrowsExceptionDataProvider
     *
     * @param class-string $expectedExceptionClass
     */
    public function testDeleteThrowsException(
        \Exception $dropletApiException,
        ResponseInterface $apiResponse,
        string $expectedExceptionClass
    ): void {
        $this->doActionThrowsExceptionTest(
            function () use ($dropletApiException) {
                $this->dropletApiProxy->withRemoveTaggedCall($this->machineName, $dropletApiException);
                $this->machineManager->delete($this->createMachineProvider());
            },
            MachineActionInterface::ACTION_DELETE,
            $dropletApiException,
            $apiResponse,
            $expectedExceptionClass,
        );
    }

    /**
     * @param MachineActionInterface::ACTION_* $action
     * @param class-string                     $expectedExceptionClass
     */
    private function doActionThrowsExceptionTest(
        callable $callable,
        string $action,
        \Exception $dropletApiException,
        ResponseInterface $apiResponse,
        string $expectedExceptionClass,
    ): void {
        if ($this->digitalOceanClient instanceof ClientProxy) {
            $this->digitalOceanClient->setLastResponse($apiResponse);
        }

        try {
            $callable();
            self::fail($dropletApiException::class . ' not thrown');
        } catch (Exception $exception) {
            self::assertSame($expectedExceptionClass, $exception::class);
            self::assertSame($action, $exception->getAction());
            self::assertEquals($dropletApiException, $exception->getRemoteException());
        }
    }

    private function createMachineProvider(): MachineProvider
    {
        $machineProviderRepository = self::getContainer()->get(MachineProviderRepository::class);
        \assert($machineProviderRepository instanceof MachineProviderRepository);

        $machineProvider = $machineProviderRepository->find(self::MACHINE_ID);
        if (!$machineProvider instanceof MachineProvider) {
            $machineProvider = new MachineProvider(self::MACHINE_ID, ProviderInterface::NAME_DIGITALOCEAN);
            $machineProviderRepository->add($machineProvider);
        }

        return $machineProvider;
    }
}
