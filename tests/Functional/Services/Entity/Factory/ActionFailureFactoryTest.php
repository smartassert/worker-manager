<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\Entity\Factory;

use App\Entity\ActionFailure;
use App\Entity\Machine;
use App\Enum\ActionFailureType;
use App\Enum\MachineAction;
use App\Enum\MachineProvider;
use App\Enum\MachineState;
use App\Exception\MachineProvider\ApiLimitExceptionInterface;
use App\Exception\MachineProvider\AuthenticationException;
use App\Exception\MachineProvider\AuthenticationExceptionInterface;
use App\Exception\MachineProvider\CurlException;
use App\Exception\MachineProvider\CurlExceptionInterface;
use App\Exception\MachineProvider\DigitalOcean\ApiLimitExceededException;
use App\Exception\MachineProvider\DigitalOcean\DropletLimitExceededException;
use App\Exception\MachineProvider\DigitalOcean\HttpException;
use App\Exception\MachineProvider\HttpExceptionInterface;
use App\Exception\MachineProvider\UnknownException;
use App\Exception\MachineProvider\UnknownExceptionInterface;
use App\Exception\MachineProvider\UnprocessableRequestExceptionInterface;
use App\Exception\Stack;
use App\Exception\UnsupportedProviderException;
use App\Repository\ActionFailureRepository;
use App\Services\Entity\Factory\ActionFailureFactory;
use App\Tests\Functional\AbstractEntityTestCase;
use App\Tests\Services\EntityRemover;
use DigitalOceanV2\Exception\RuntimeException;
use DigitalOceanV2\Exception\ValidationFailedException;
use PHPUnit\Framework\Attributes\DataProvider;

class ActionFailureFactoryTest extends AbstractEntityTestCase
{
    private ActionFailureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $factory = self::getContainer()->get(ActionFailureFactory::class);
        \assert($factory instanceof ActionFailureFactory);
        $this->factory = $factory;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeAllForEntity(ActionFailure::class);
        }
    }

    #[DataProvider('createDataProvider')]
    public function testCreate(Machine $machine, \Throwable $throwable, ActionFailure $expectedActionFailure): void
    {
        $actionFailure = $this->factory->create($machine, MachineAction::CREATE, $throwable);

        self::assertEquals($expectedActionFailure, $actionFailure);

        $actionFailureRepository = self::getContainer()->get(ActionFailureRepository::class);
        \assert($actionFailureRepository instanceof ActionFailureRepository);
        $retrievedActionFailure = $actionFailureRepository->find(self::MACHINE_ID);

        self::assertInstanceOf(ActionFailure::class, $retrievedActionFailure);
        self::assertEquals($actionFailure, $retrievedActionFailure);
    }

    /**
     * @return array<mixed>
     */
    public static function createDataProvider(): array
    {
        $unprocessableReason = UnprocessableRequestExceptionInterface::REASON_REMOTE_PROVIDER_RESOURCE_LIMIT_REACHED;
        $digitalOceanMachine = new Machine(self::MACHINE_ID);
        $digitalOceanMachine->setState(MachineState::CREATE_RECEIVED);
        $digitalOceanMachine->setProvider(MachineProvider::DIGITALOCEAN);

        return [
            UnsupportedProviderException::class => [
                'machine' => (function () {
                    $machine = new Machine(self::MACHINE_ID);
                    $machine->setState(MachineState::CREATE_RECEIVED);

                    return $machine;
                })(),
                'throwable' => new UnsupportedProviderException(null),
                'expectedActionFailure' => new ActionFailure(
                    self::MACHINE_ID,
                    ActionFailureType::UNSUPPORTED_PROVIDER,
                    MachineAction::CREATE,
                    [
                        'provider' => null,
                    ]
                ),
            ],
            ApiLimitExceptionInterface::class => [
                'machine' => $digitalOceanMachine,
                'throwable' => new ApiLimitExceededException(
                    123,
                    self::MACHINE_ID,
                    MachineAction::GET,
                    new \Exception()
                ),
                'expectedActionFailure' => new ActionFailure(
                    self::MACHINE_ID,
                    ActionFailureType::VENDOR_REQUEST_LIMIT_EXCEEDED,
                    MachineAction::CREATE,
                    [
                        'reset-timestamp' => 123,
                        'provider' => $digitalOceanMachine->getProvider()?->value,
                    ]
                ),
            ],
            AuthenticationExceptionInterface::class => [
                'machine' => $digitalOceanMachine,
                'throwable' => new AuthenticationException(
                    MachineProvider::DIGITALOCEAN,
                    self::MACHINE_ID,
                    MachineAction::GET,
                    new Stack([new \Exception()]),
                ),
                'expectedActionFailure' => new ActionFailure(
                    self::MACHINE_ID,
                    ActionFailureType::VENDOR_AUTHENTICATION_FAILURE,
                    MachineAction::CREATE,
                    [
                        'provider' => $digitalOceanMachine->getProvider()?->value,
                    ]
                ),
            ],
            CurlExceptionInterface::class => [
                'machine' => $digitalOceanMachine,
                'throwable' => new CurlException(
                    7,
                    self::MACHINE_ID,
                    MachineAction::GET,
                    new \Exception()
                ),
                'expectedActionFailure' => new ActionFailure(
                    self::MACHINE_ID,
                    ActionFailureType::CURL_ERROR,
                    MachineAction::CREATE,
                    [
                        'curl-code' => 7,
                        'provider' => $digitalOceanMachine->getProvider()?->value,
                    ]
                ),
            ],
            HttpExceptionInterface::class => [
                'machine' => $digitalOceanMachine,
                'throwable' => new HttpException(
                    self::MACHINE_ID,
                    MachineAction::GET,
                    new RuntimeException('', 500)
                ),
                'expectedActionFailure' => new ActionFailure(
                    self::MACHINE_ID,
                    ActionFailureType::HTTP_ERROR,
                    MachineAction::CREATE,
                    [
                        'status-code' => 500,
                        'provider' => $digitalOceanMachine->getProvider()?->value,
                    ]
                ),
            ],
            UnprocessableRequestExceptionInterface::class => [
                'machine' => $digitalOceanMachine,
                'throwable' => new DropletLimitExceededException(
                    self::MACHINE_ID,
                    MachineAction::GET,
                    new ValidationFailedException(
                        'creating this/these droplet(s) will exceed your droplet limit',
                        422
                    )
                ),
                'expectedActionFailure' => new ActionFailure(
                    self::MACHINE_ID,
                    ActionFailureType::UNPROCESSABLE_REQUEST,
                    MachineAction::CREATE,
                    [
                        'provider-reason' => $unprocessableReason,
                        'provider' => $digitalOceanMachine->getProvider()?->value,
                    ]
                ),
            ],
            UnknownExceptionInterface::class => [
                'machine' => $digitalOceanMachine,
                'throwable' => new UnknownException(
                    self::MACHINE_ID,
                    MachineAction::CREATE,
                    new \Exception()
                ),
                'expectedActionFailure' => new ActionFailure(
                    self::MACHINE_ID,
                    ActionFailureType::UNKNOWN_MACHINE_PROVIDER_ERROR,
                    MachineAction::CREATE,
                    [
                        'provider' => $digitalOceanMachine->getProvider()?->value,
                    ]
                ),
            ],
            'unknown exception' => [
                'machine' => $digitalOceanMachine,
                'throwable' => new \RuntimeException('Runtime error'),
                'expectedActionFailure' => new ActionFailure(
                    self::MACHINE_ID,
                    ActionFailureType::UNKNOWN,
                    MachineAction::CREATE,
                    [
                        'provider' => $digitalOceanMachine->getProvider()?->value,
                    ]
                ),
            ],
        ];
    }
}
