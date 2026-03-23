<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\ExceptionFactory\MachineProvider;

use App\Enum\MachineAction;
use App\Enum\MachineProvider;
use App\Exception\MachineProvider\AuthenticationException;
use App\Exception\MachineProvider\DigitalOcean\ApiLimitExceededException;
use App\Exception\MachineProvider\DigitalOcean\DropletLimitReachedException;
use App\Exception\MachineProvider\DigitalOcean\HttpException;
use App\Exception\MachineProvider\ExceptionInterface;
use App\Exception\Stack;
use App\Services\ExceptionFactory\MachineProvider\DigitalOceanExceptionFactory;
use App\Services\MachineManager\DigitalOcean\Entity\Error;
use App\Services\MachineManager\DigitalOcean\Exception\ApiLimitExceededException as DOApiLimitExceededException;
use App\Services\MachineManager\DigitalOcean\Exception\AuthenticationException as DigitalOceanAuthenticationException;
use App\Services\MachineManager\DigitalOcean\Exception\DropletLimitReachedException as DODropletLimitReachedException;
use App\Services\MachineManager\DigitalOcean\Exception\ErrorException;
use App\Tests\AbstractBaseFunctionalTestCase;
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
        $errorException400 = new ErrorException(
            new Error(400, 'bad_request', 'Bad request')
        );

        $dropletLimitExceededError = new Error(
            422,
            'droplet_limit_exceeded',
            'creating this/these droplet(s) will exceed your droplet limit',
        );

        $dropletLimitValidationFailedException = new DODropletLimitReachedException($dropletLimitExceededError);

        $vendorApiLimitExceededException = new DOApiLimitExceededException(
            $dropletLimitExceededError,
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
            DropletLimitReachedException::class => [
                'exception' => $dropletLimitValidationFailedException,
                'expectedException' => new DropletLimitReachedException(
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
                ),
            ],
        ];
    }
}
