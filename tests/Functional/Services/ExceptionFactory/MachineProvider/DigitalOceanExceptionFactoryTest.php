<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\ExceptionFactory\MachineProvider;

use App\Enum\MachineAction;
use App\Enum\MachineProvider;
use App\Exception\MachineProvider\AuthenticationException;
use App\Exception\MachineProvider\DigitalOcean\ApiLimitExceededException;
use App\Exception\MachineProvider\DigitalOcean\DropletLimitExceededException;
use App\Exception\MachineProvider\DigitalOcean\HttpException;
use App\Exception\MachineProvider\Exception;
use App\Exception\MachineProvider\ExceptionInterface;
use App\Exception\Stack;
use App\Services\ExceptionFactory\MachineProvider\DigitalOceanExceptionFactory;
use App\Services\MachineManager\DigitalOcean\Exception\ApiLimitExceededException as DOApiLimitExceededException;
use App\Services\MachineManager\DigitalOcean\Exception\AuthenticationException as DigitalOceanAuthenticationException;
use App\Services\MachineManager\DigitalOcean\Exception\ErrorException;
use App\Tests\AbstractBaseFunctionalTestCase;
use DigitalOceanV2\Exception\ValidationFailedException;
use PHPUnit\Framework\Attributes\DataProvider;

class DigitalOceanExceptionFactoryTest extends AbstractBaseFunctionalTestCase
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

    #[DataProvider('createDataProvider')]
    public function testCreate(\Throwable $exception, ExceptionInterface $expectedException): void
    {
        self::assertEquals(
            $expectedException,
            $this->factory->create(self::ID, MachineAction::CREATE, $exception)
        );
    }

    /**
     * @return array<mixed>
     */
    public static function createDataProvider(): array
    {
        $errorException400 = new ErrorException('bad_request', 'Bad request', 400);

        $genericValidationFailedException = new ValidationFailedException('generic');
        $dropletLimitValidationFailedException = new ValidationFailedException(
            'creating this/these droplet(s) will exceed your droplet limit',
            422
        );

        $vendorApiLimitExceededException = new DOApiLimitExceededException(
            'API Rate limit exceeded',
            123,
            0,
            5000
        );

        $digitalOceanAuthenticationException = new DigitalOceanAuthenticationException();

        return [
            ErrorException::class . ' 400' => [
                'exception' => $errorException400,
                'expectedException' => new HttpException(
                    self::ID,
                    self::ACTION,
                    $errorException400
                ),
            ],
            DigitalOceanAuthenticationException::class => [
                'exception' => $digitalOceanAuthenticationException,
                'expectedException' => new AuthenticationException(
                    MachineProvider::DIGITALOCEAN,
                    self::ID,
                    self::ACTION,
                    new Stack([$digitalOceanAuthenticationException])
                ),
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
