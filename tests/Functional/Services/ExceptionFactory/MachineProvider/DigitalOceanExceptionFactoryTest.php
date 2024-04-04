<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\ExceptionFactory\MachineProvider;

use App\Enum\MachineAction;
use App\Exception\MachineProvider\AuthenticationException;
use App\Exception\MachineProvider\DigitalOcean\ApiLimitExceededException;
use App\Exception\MachineProvider\DigitalOcean\DropletLimitExceededException;
use App\Exception\MachineProvider\DigitalOcean\HttpException;
use App\Exception\MachineProvider\Exception;
use App\Exception\MachineProvider\ExceptionInterface;
use App\Services\ExceptionFactory\MachineProvider\DigitalOceanExceptionFactory;
use App\Tests\AbstractBaseFunctionalTest;
use DigitalOceanV2\Entity\RateLimit;
use DigitalOceanV2\Exception\ApiLimitExceededException as VendorApiLimitExceededException;
use DigitalOceanV2\Exception\ExceptionInterface as VendorExceptionInterface;
use DigitalOceanV2\Exception\RuntimeException;
use DigitalOceanV2\Exception\ValidationFailedException;

class DigitalOceanExceptionFactoryTest extends AbstractBaseFunctionalTest
{
    private const ID = 'resource_id';
    private const ACTION = MachineAction::CREATE;

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
    public function testCreate(VendorExceptionInterface $exception, ExceptionInterface $expectedException): void
    {
        self::assertEquals(
            $expectedException,
            $this->factory->create(self::ID, MachineAction::CREATE, $exception)
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

        $vendorApiLimitExceededException = new VendorApiLimitExceededException(
            'api limit exceeded',
            429,
            new RateLimit([
                'reset' => 123,
                'limit' => 456,
                'remaining' => 798,
            ])
        );

        return [
            RuntimeException::class . ' 400' => [
                'exception' => $runtimeException400,
                'expectedException' => new HttpException(self::ID, self::ACTION, $runtimeException400),
            ],
            RuntimeException::class . ' 401' => [
                'exception' => $runtimeException401,
                'expectedException' => new AuthenticationException(self::ID, self::ACTION, $runtimeException401),
            ],
            ValidationFailedException::class . ' generic' => [
                'exception' => $genericValidationFailedException,
                'expectedException' => new Exception(self::ID, self::ACTION, $genericValidationFailedException),
            ],
            ValidationFailedException::class . ' droplet limit will be exceeded' => [
                'exception' => $dropletLimitValidationFailedException,
                'expectedException' => new DropletLimitExceededException(
                    self::ID,
                    self::ACTION,
                    $dropletLimitValidationFailedException
                ),
            ],
            ApiLimitExceededException::class => [
                'exception' => $vendorApiLimitExceededException,
                'expectedException' => new ApiLimitExceededException(
                    123,
                    self::ID,
                    self::ACTION,
                    $vendorApiLimitExceededException
                )
            ],
        ];
    }
}
