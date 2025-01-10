<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Machine;
use App\Enum\MachineAction;
use App\Enum\MachineProvider;
use App\Enum\MachineState;
use App\Exception\MachineActionFailedException;
use App\Exception\MachineProvider\AuthenticationException;
use App\Exception\MachineProvider\DigitalOcean\ApiLimitExceededException as LocalApiLimitExceededException;
use App\Exception\MachineProvider\DigitalOcean\HttpException;
use App\Exception\Stack;
use App\Message\DeleteMachine;
use App\MessageHandler\DeleteMachineHandler;
use App\Repository\MachineRepository;
use App\Services\MachineManager\DigitalOcean\Exception\ErrorException;
use App\Services\MachineManager\MachineManager;
use App\Services\MachineRequestDispatcher;
use App\Tests\AbstractBaseFunctionalTestCase;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\TestMachineRequestFactory;
use DigitalOceanV2\Entity\RateLimit;
use DigitalOceanV2\Exception\ApiLimitExceededException as VendorApiLimitExceededException;
use DigitalOceanV2\Exception\RuntimeException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

class DeleteMachineHandlerTest extends AbstractBaseFunctionalTestCase
{
    use MockeryPHPUnitIntegration;

    private const MACHINE_ID = 'id';

    private Machine $machine;
    private TestMachineRequestFactory $machineRequestFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeAllForEntity(Machine::class);
        }

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);
        $this->machine = new Machine(self::MACHINE_ID);
        $this->machine->setState(MachineState::DELETE_RECEIVED);
        $machineRepository->add($this->machine);

        $machineRequestFactory = self::getContainer()->get(TestMachineRequestFactory::class);
        \assert($machineRequestFactory instanceof TestMachineRequestFactory);
        $this->machineRequestFactory = $machineRequestFactory;
    }

    public function testHandlerExistsInContainerAndIsAMessageHandler(): void
    {
        $handler = self::getContainer()->get(DeleteMachineHandler::class);
        self::assertInstanceOf(DeleteMachineHandler::class, $handler);
        self::assertCount(1, (new \ReflectionClass($handler::class))->getAttributes(AsMessageHandler::class));
    }

    public function testInvokeSuccess(): void
    {
        self::assertSame(MachineState::DELETE_RECEIVED, $this->machine->getState());

        $mockHandler = self::getContainer()->get('app.tests.httpclient.mocked.handler');
        \assert($mockHandler instanceof MockHandler);
        $mockHandler->append(new Response(204));

        $message = $this->machineRequestFactory->createDelete(self::MACHINE_ID);
        $expectedMachineRequestCollection = $message->getOnSuccessCollection();

        $machineRequestDispatcher = \Mockery::mock(MachineRequestDispatcher::class);
        $machineRequestDispatcher
            ->shouldReceive('dispatchCollection')
            ->withArgs(function (array $machineRequestCollection) use ($expectedMachineRequestCollection) {
                self::assertEquals($expectedMachineRequestCollection, $machineRequestCollection);

                return true;
            })
        ;

        $handler = $this->createHandler($machineRequestDispatcher);
        ($handler)($message);

        self::assertSame(MachineState::DELETE_REQUESTED, $this->machine->getState());
    }

    public function testInvokeMachineEntityMissing(): void
    {
        $machineRequestDispatcher = \Mockery::mock(MachineRequestDispatcher::class);
        $machineRequestDispatcher->shouldNotReceive('dispatch');
        $machineRequestDispatcher->shouldNotReceive('dispatchCollection');

        $handler = $this->createHandler($machineRequestDispatcher);
        ($handler)(new DeleteMachine('id0', 'invalid machine id'));
    }

    #[DataProvider('invokeThrowsExceptionDataProvider')]
    public function testInvokeThrowsException(ResponseInterface $httpResponse, \Exception $expectedException): void
    {
        $mockHandler = self::getContainer()->get('app.tests.httpclient.mocked.handler');
        \assert($mockHandler instanceof MockHandler);

        $mockHandler->append($httpResponse);
        $mockHandler->append($httpResponse);

        $machineRequestDispatcher = \Mockery::mock(MachineRequestDispatcher::class);
        $machineRequestDispatcher->shouldNotReceive('dispatch');
        $machineRequestDispatcher->shouldNotReceive('dispatchCollection');

        $handler = $this->createHandler($machineRequestDispatcher);

        $message = new DeleteMachine('id0', self::MACHINE_ID);

        try {
            ($handler)($message);
            $this->fail($expectedException::class . ' not thrown');
        } catch (\Exception $exception) {
            self::assertEquals($expectedException, $exception);
        }

        self::assertSame(MachineState::DELETE_REQUESTED, $this->machine->getState());
    }

    /**
     * @return array<mixed>
     */
    public static function invokeThrowsExceptionDataProvider(): array
    {
        $rateLimitReset = (\time() + 1000);

        $internalServerErrorId = md5((string) rand());
        $internalServerErrorMessage = md5((string) rand());

        $serviceUnavailableErrorId = md5((string) rand());
        $serviceUnavailableErrorMessage = md5((string) rand());

        return [
            'unauthorized' => [
                'httpResponse' => new Response(401),
                'expectedException' => new UnrecoverableMessageHandlingException(
                    'Action "delete" on machine "id" failed',
                    0,
                    new MachineActionFailedException(
                        self::MACHINE_ID,
                        MachineAction::DELETE,
                        new Stack([
                            new AuthenticationException(
                                MachineProvider::DIGITALOCEAN,
                                self::MACHINE_ID,
                                MachineAction::DELETE,
                                new Stack([
                                    new RuntimeException('Unauthorized', 401),
                                ])
                            ),
                        ])
                    )
                ),
            ],
            'api limit exceeded' => [
                'httpResponse' => new Response(
                    429,
                    [
                        'Content-Type' => 'application/json',
                        'RateLimit-limit' => '5000',
                        'RateLimit-Remaining' => '0',
                        'RateLimit-Reset' => (string) $rateLimitReset,
                        'Retry-After' => '1000',
                    ],
                    (string) json_encode([
                        'id' => 'too_many_requests',
                        'message' => 'API Rate limit exceeded',
                    ])
                ),
                'expectedException' => new UnrecoverableMessageHandlingException(
                    'Action "delete" on machine "id" failed',
                    0,
                    new MachineActionFailedException(
                        self::MACHINE_ID,
                        MachineAction::DELETE,
                        new Stack([
                            new LocalApiLimitExceededException(
                                $rateLimitReset,
                                self::MACHINE_ID,
                                MachineAction::DELETE,
                                new VendorApiLimitExceededException(
                                    'API Rate limit exceeded',
                                    429,
                                    new RateLimit([
                                        'reset' => $rateLimitReset,
                                        'remaining' => 0,
                                        'limit' => 5000,
                                    ])
                                ),
                            )
                        ])
                    )
                ),
            ],
            'internal server error' => [
                'httpResponse' => new Response(
                    500,
                    [
                        'Content-Type' => 'application/json',
                    ],
                    (string) json_encode([
                        'id' => $internalServerErrorId,
                        'message' => $internalServerErrorMessage,
                    ])
                ),
                'expectedException' => new UnrecoverableMessageHandlingException(
                    'Action "delete" on machine "id" failed',
                    0,
                    new MachineActionFailedException(
                        self::MACHINE_ID,
                        MachineAction::DELETE,
                        new Stack([
                            new HttpException(
                                self::MACHINE_ID,
                                MachineAction::DELETE,
                                new ErrorException(
                                    $internalServerErrorId,
                                    $internalServerErrorMessage,
                                    500
                                )
                            ),
                        ])
                    )
                ),
            ],
            'service unavailable' => [
                'httpResponse' => new Response(
                    503,
                    [
                        'Content-Type' => 'application/json',
                    ],
                    (string) json_encode([
                        'id' => $serviceUnavailableErrorId,
                        'message' => $serviceUnavailableErrorMessage,
                    ])
                ),
                'expectedException' => new UnrecoverableMessageHandlingException(
                    'Action "delete" on machine "id" failed',
                    0,
                    new MachineActionFailedException(
                        self::MACHINE_ID,
                        MachineAction::DELETE,
                        new Stack([
                            new HttpException(
                                self::MACHINE_ID,
                                MachineAction::DELETE,
                                new ErrorException(
                                    $serviceUnavailableErrorId,
                                    $serviceUnavailableErrorMessage,
                                    503
                                )
                            ),
                        ])
                    )
                ),
            ],
        ];
    }

    private function createHandler(MachineRequestDispatcher $machineRequestDispatcher): DeleteMachineHandler
    {
        $machineManager = self::getContainer()->get(MachineManager::class);
        \assert($machineManager instanceof MachineManager);

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);

        return new DeleteMachineHandler($machineManager, $machineRequestDispatcher, $machineRepository);
    }
}
