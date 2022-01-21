<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\Entity\Factory;

use App\Entity\CreateFailure;
use App\Exception\MachineProvider\ApiLimitExceptionInterface;
use App\Exception\MachineProvider\AuthenticationException;
use App\Exception\MachineProvider\AuthenticationExceptionInterface;
use App\Exception\MachineProvider\CurlException;
use App\Exception\MachineProvider\CurlExceptionInterface;
use App\Exception\MachineProvider\DigitalOcean\ApiLimitExceededException;
use App\Exception\MachineProvider\DigitalOcean\DropletLimitExceededException;
use App\Exception\MachineProvider\DigitalOcean\HttpException;
use App\Exception\MachineProvider\ExceptionInterface;
use App\Exception\MachineProvider\HttpExceptionInterface;
use App\Exception\MachineProvider\UnknownException;
use App\Exception\MachineProvider\UnknownExceptionInterface;
use App\Exception\MachineProvider\UnprocessableRequestExceptionInterface;
use App\Exception\UnsupportedProviderException;
use App\Model\MachineActionInterface;
use App\Model\ProviderInterface;
use App\Services\Entity\Factory\CreateFailureFactory;
use App\Tests\Functional\AbstractEntityTest;
use DigitalOceanV2\Exception\RuntimeException;
use DigitalOceanV2\Exception\ValidationFailedException;

class CreateFailureFactoryTest extends AbstractEntityTest
{
    private CreateFailureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $factory = self::getContainer()->get(CreateFailureFactory::class);
        \assert($factory instanceof CreateFailureFactory);
        $this->factory = $factory;
    }

    /**
     * @dataProvider createDataProvider
     */
    public function testCreate(
        ExceptionInterface | UnsupportedProviderException $exception,
        CreateFailure $expectedCreateFailure
    ): void {
        $createFailure = $this->factory->create(self::MACHINE_ID, $exception);

        self::assertEquals($expectedCreateFailure, $createFailure);

        $retrievedCreateFailure = $this->entityManager->find(CreateFailure::class, self::MACHINE_ID);
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
                'exception' => new UnsupportedProviderException(ProviderInterface::NAME_DIGITALOCEAN),
                'expectedCreateFailure' => new CreateFailure(
                    self::MACHINE_ID,
                    CreateFailure::CODE_UNSUPPORTED_PROVIDER,
                    CreateFailure::REASON_UNSUPPORTED_PROVIDER,
                ),
            ],
            ApiLimitExceptionInterface::class => [
                'exception' => new ApiLimitExceededException(
                    123,
                    self::MACHINE_ID,
                    MachineActionInterface::ACTION_GET,
                    new \Exception()
                ),
                'expectedCreateFailure' => new CreateFailure(
                    self::MACHINE_ID,
                    CreateFailure::CODE_API_LIMIT_EXCEEDED,
                    CreateFailure::REASON_API_LIMIT_EXCEEDED,
                    [
                        'reset-timestamp' => 123,
                    ]
                ),
            ],
            AuthenticationExceptionInterface::class => [
                'exception' => new AuthenticationException(
                    self::MACHINE_ID,
                    MachineActionInterface::ACTION_GET,
                    new \Exception(),
                ),
                'expectedCreateFailure' => new CreateFailure(
                    self::MACHINE_ID,
                    CreateFailure::CODE_API_AUTHENTICATION_FAILURE,
                    CreateFailure::REASON_API_AUTHENTICATION_FAILURE,
                ),
            ],
            CurlExceptionInterface::class => [
                'exception' => new CurlException(
                    7,
                    self::MACHINE_ID,
                    MachineActionInterface::ACTION_GET,
                    new \Exception()
                ),
                'expectedCreateFailure' => new CreateFailure(
                    self::MACHINE_ID,
                    CreateFailure::CODE_CURL_ERROR,
                    CreateFailure::REASON_CURL_ERROR,
                    [
                        'curl-code' => 7,
                    ]
                ),
            ],
            HttpExceptionInterface::class => [
                'exception' => new HttpException(
                    self::MACHINE_ID,
                    MachineActionInterface::ACTION_GET,
                    new RuntimeException('', 500)
                ),
                'expectedCreateFailure' => new CreateFailure(
                    self::MACHINE_ID,
                    CreateFailure::CODE_HTTP_ERROR,
                    CreateFailure::REASON_HTTP_ERROR,
                    [
                        'status-code' => 500,
                    ]
                ),
            ],
            UnprocessableRequestExceptionInterface::class => [
                'exception' => new DropletLimitExceededException(
                    self::MACHINE_ID,
                    MachineActionInterface::ACTION_GET,
                    new ValidationFailedException(
                        'creating this/these droplet(s) will exceed your droplet limit',
                        422
                    )
                ),
                'expectedCreateFailure' => new CreateFailure(
                    self::MACHINE_ID,
                    CreateFailure::CODE_UNPROCESSABLE_REQUEST,
                    CreateFailure::REASON_UNPROCESSABLE_REQUEST,
                    [
                        'provider-reason' => $unprocessableReason,
                    ]
                ),
            ],
            UnknownExceptionInterface::class => [
                'exception' => new UnknownException(
                    self::MACHINE_ID,
                    MachineActionInterface::ACTION_CREATE,
                    new \Exception()
                ),
                'expectedCreateFailure' => new CreateFailure(
                    self::MACHINE_ID,
                    CreateFailure::CODE_UNKNOWN,
                    CreateFailure::REASON_UNKNOWN,
                ),
            ],
        ];
    }
}
