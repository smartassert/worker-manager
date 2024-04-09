<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\Entity\Factory;

use App\Entity\ActionFailure;
use App\Enum\ActionFailureType;
use App\Enum\MachineAction;
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
use App\Exception\UnsupportedProviderException;
use App\Repository\ActionFailureRepository;
use App\Services\Entity\Factory\ActionFailureFactory;
use App\Tests\Functional\AbstractEntityTestCase;
use App\Tests\Services\EntityRemover;
use DigitalOceanV2\Exception\RuntimeException;
use DigitalOceanV2\Exception\ValidationFailedException;

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

    /**
     * @dataProvider createDataProvider
     */
    public function testCreate(\Throwable $throwable, ActionFailure $expectedActionFailure): void
    {
        $actionFailure = $this->factory->create(self::MACHINE_ID, MachineAction::CREATE, $throwable);

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
    public function createDataProvider(): array
    {
        $unprocessableReason = UnprocessableRequestExceptionInterface::REASON_REMOTE_PROVIDER_RESOURCE_LIMIT_REACHED;

        return [
            UnsupportedProviderException::class => [
                'throwable' => new UnsupportedProviderException(null),
                'expectedActionFailure' => new ActionFailure(
                    self::MACHINE_ID,
                    ActionFailureType::UNSUPPORTED_PROVIDER,
                    MachineAction::CREATE,
                ),
            ],
            ApiLimitExceptionInterface::class => [
                'throwable' => new ApiLimitExceededException(
                    123,
                    self::MACHINE_ID,
                    MachineAction::GET,
                    new \Exception()
                ),
                'expectedActionFailure' => new ActionFailure(
                    self::MACHINE_ID,
                    ActionFailureType::API_LIMIT_EXCEEDED,
                    MachineAction::CREATE,
                    [
                        'reset-timestamp' => 123,
                    ]
                ),
            ],
            AuthenticationExceptionInterface::class => [
                'throwable' => new AuthenticationException(
                    self::MACHINE_ID,
                    MachineAction::GET,
                    new \Exception(),
                ),
                'expectedActionFailure' => new ActionFailure(
                    self::MACHINE_ID,
                    ActionFailureType::API_AUTHENTICATION_FAILURE,
                    MachineAction::CREATE,
                ),
            ],
            CurlExceptionInterface::class => [
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
                    ]
                ),
            ],
            HttpExceptionInterface::class => [
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
                    ]
                ),
            ],
            UnprocessableRequestExceptionInterface::class => [
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
                    ]
                ),
            ],
            UnknownExceptionInterface::class => [
                'throwable' => new UnknownException(
                    self::MACHINE_ID,
                    MachineAction::CREATE,
                    new \Exception()
                ),
                'expectedActionFailure' => new ActionFailure(
                    self::MACHINE_ID,
                    ActionFailureType::UNKNOWN_MACHINE_PROVIDER_ERROR,
                    MachineAction::CREATE,
                ),
            ],
            'unknown exception' => [
                'throwable' => new \RuntimeException('Runtime error'),
                'expectedActionFailure' => new ActionFailure(
                    self::MACHINE_ID,
                    ActionFailureType::UNKNOWN,
                    MachineAction::CREATE,
                ),
            ],
        ];
    }
}
