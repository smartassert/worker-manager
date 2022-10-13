<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\ExceptionFactory\MachineProvider;

use App\Exception\MachineProvider\AuthenticationException;
use App\Exception\MachineProvider\DigitalOcean\ApiLimitExceededException;
use App\Exception\MachineProvider\DigitalOcean\DropletLimitExceededException;
use App\Exception\MachineProvider\DigitalOcean\HttpException;
use App\Exception\MachineProvider\Exception;
use App\Exception\MachineProvider\ExceptionInterface;
use App\Model\MachineActionInterface;
use App\Services\ExceptionFactory\MachineProvider\DigitalOceanExceptionFactory;
use App\Tests\AbstractBaseFunctionalTest;
use DigitalOceanV2\Exception\ApiLimitExceededException as VendorApiLimitExceededException;
use DigitalOceanV2\Exception\ExceptionInterface as VendorExceptionInterface;
use DigitalOceanV2\Exception\RuntimeException;
use DigitalOceanV2\Exception\ValidationFailedException;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

class DigitalOceanExceptionFactoryTest extends AbstractBaseFunctionalTest
{
    private const ID = 'resource_id';
    private const ACTION = MachineActionInterface::ACTION_CREATE;

    private DigitalOceanExceptionFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $factory = self::getContainer()->get(DigitalOceanExceptionFactory::class);
        \assert($factory instanceof DigitalOceanExceptionFactory);
        $this->factory = $factory;
    }

    /**
     * @dataProvider createDataProvider
     */
    public function testCreate(
        VendorExceptionInterface $exception,
        ?ResponseInterface $lastResponse,
        ExceptionInterface $expectedException
    ): void {
        self::assertEquals(
            $expectedException,
            $this->factory->create(self::ID, MachineActionInterface::ACTION_CREATE, $exception, $lastResponse)
        );
    }

    /**
     * @return array<mixed>
     */
    public function createDataProvider(): array
    {
        $runtimeException400 = new RuntimeException('message', 400);
        $runtimeException401 = new RuntimeException('message', 401);
        $genericValidationFailedException = new ValidationFailedException('generic');
        $dropletLimitValidationFailedException = new ValidationFailedException(
            'creating this/these droplet(s) will exceed your droplet limit',
            422
        );

        return [
            RuntimeException::class . ' 400' => [
                'exception' => $runtimeException400,
                'lastResponse' => null,
                'expectedException' => new HttpException(self::ID, self::ACTION, $runtimeException400),
            ],
            RuntimeException::class . ' 401' => [
                'exception' => $runtimeException401,
                'lastResponse' => null,
                'expectedException' => new AuthenticationException(self::ID, self::ACTION, $runtimeException401),
            ],
            ValidationFailedException::class . ' generic' => [
                'exception' => $genericValidationFailedException,
                'lastResponse' => null,
                'expectedException' => new Exception(self::ID, self::ACTION, $genericValidationFailedException),
            ],
            ValidationFailedException::class . ' droplet limit will be exceeded' => [
                'exception' => $dropletLimitValidationFailedException,
                'lastResponse' => null,
                'expectedException' => new DropletLimitExceededException(
                    self::ID,
                    self::ACTION,
                    $dropletLimitValidationFailedException
                ),
            ],
            ApiLimitExceededException::class => [
                'exception' => new VendorApiLimitExceededException(),
                'lastResponse' => new Response(429, ['ratelimit-reset' => '123']),
                'expectedException' => new ApiLimitExceededException(
                    123,
                    self::ID,
                    self::ACTION,
                    new VendorApiLimitExceededException()
                )
            ],
        ];
    }
}
