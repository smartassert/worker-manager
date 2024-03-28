<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\Entity\Factory;

use App\Entity\CreateFailure;
use App\Enum\CreateFailure\Code;
use App\Enum\CreateFailure\Reason;
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
use App\Model\DigitalOcean\RemoteMachine;
use App\Repository\CreateFailureRepository;
use App\Services\Entity\Factory\CreateFailureFactory;
use App\Tests\Functional\AbstractEntityTestCase;
use App\Tests\Services\EntityRemover;
use DigitalOceanV2\Exception\RuntimeException;
use DigitalOceanV2\Exception\ValidationFailedException;

class CreateFailureFactoryTest extends AbstractEntityTestCase
{
    private CreateFailureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $factory = self::getContainer()->get(CreateFailureFactory::class);
        \assert($factory instanceof CreateFailureFactory);
        $this->factory = $factory;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeAllForEntity(CreateFailure::class);
        }
    }

    /**
     * @dataProvider createDataProvider
     */
    public function testCreate(\Throwable $throwable, CreateFailure $expectedCreateFailure): void
    {
        $createFailure = $this->factory->create(self::MACHINE_ID, $throwable);

        self::assertEquals($expectedCreateFailure, $createFailure);

        $createFailureRepository = self::getContainer()->get(CreateFailureRepository::class);
        \assert($createFailureRepository instanceof CreateFailureRepository);
        $retrievedCreateFailure = $createFailureRepository->find(self::MACHINE_ID);

        self::assertInstanceOf(CreateFailure::class, $retrievedCreateFailure);
        self::assertEquals($createFailure, $retrievedCreateFailure);
    }

    /**
     * @return array<mixed>
     */
    public function createDataProvider(): array
    {
        $unprocessableReason = UnprocessableRequestExceptionInterface::REASON_REMOTE_PROVIDER_RESOURCE_LIMIT_REACHED;

        return [
            UnsupportedProviderException::class => [
                'throwable' => new UnsupportedProviderException(RemoteMachine::TYPE),
                'expectedCreateFailure' => new CreateFailure(
                    self::MACHINE_ID,
                    Code::UNSUPPORTED_PROVIDER,
                    Reason::UNSUPPORTED_PROVIDER,
                ),
            ],
            ApiLimitExceptionInterface::class => [
                'throwable' => new ApiLimitExceededException(
                    123,
                    self::MACHINE_ID,
                    MachineAction::GET,
                    new \Exception()
                ),
                'expectedCreateFailure' => new CreateFailure(
                    self::MACHINE_ID,
                    Code::API_LIMIT_EXCEEDED,
                    Reason::API_LIMIT_EXCEEDED,
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
                'expectedCreateFailure' => new CreateFailure(
                    self::MACHINE_ID,
                    Code::API_AUTHENTICATION_FAILURE,
                    Reason::API_AUTHENTICATION_FAILURE,
                ),
            ],
            CurlExceptionInterface::class => [
                'throwable' => new CurlException(
                    7,
                    self::MACHINE_ID,
                    MachineAction::GET,
                    new \Exception()
                ),
                'expectedCreateFailure' => new CreateFailure(
                    self::MACHINE_ID,
                    Code::CURL_ERROR,
                    Reason::CURL_ERROR,
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
                'expectedCreateFailure' => new CreateFailure(
                    self::MACHINE_ID,
                    Code::HTTP_ERROR,
                    Reason::HTTP_ERROR,
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
                'expectedCreateFailure' => new CreateFailure(
                    self::MACHINE_ID,
                    Code::UNPROCESSABLE_REQUEST,
                    Reason::UNPROCESSABLE_REQUEST,
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
                'expectedCreateFailure' => new CreateFailure(
                    self::MACHINE_ID,
                    Code::UNKNOWN_MACHINE_PROVIDER_ERROR,
                    Reason::UNKNOWN_MACHINE_PROVIDER_ERROR,
                ),
            ],
            'unknown exception' => [
                'throwable' => new \RuntimeException('Runtime error'),
                'expectedCreateFailure' => new CreateFailure(
                    self::MACHINE_ID,
                    Code::UNKNOWN,
                    Reason::UNKNOWN,
                ),
            ],
        ];
    }
}
