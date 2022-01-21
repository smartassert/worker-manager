<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\ExceptionFactory\MachineProvider;

use App\Exception\MachineProvider\CurlException;
use App\Exception\MachineProvider\ExceptionInterface;
use App\Model\MachineActionInterface;
use App\Services\ExceptionFactory\MachineProvider\GuzzleExceptionFactory;
use App\Tests\AbstractBaseFunctionalTest;
use GuzzleHttp\Exception\ConnectException;
use Psr\Http\Message\RequestInterface;

class GuzzleExceptionFactoryTest extends AbstractBaseFunctionalTest
{
    private const ID = 'resource_id';
    private const ACTION = MachineActionInterface::ACTION_CREATE;

    private GuzzleExceptionFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $factory = self::getContainer()->get(GuzzleExceptionFactory::class);
        if ($factory instanceof GuzzleExceptionFactory) {
            $this->factory = $factory;
        }
    }

    public function testHandles(): void
    {
        self::assertTrue($this->factory->handles(new ConnectException('', \Mockery::mock(RequestInterface::class))));
        self::assertFalse($this->factory->handles(new \Exception()));
    }

    /**
     * @dataProvider createDataProvider
     */
    public function testCreate(ConnectException $exception, ExceptionInterface $expectedException): void
    {
        self::assertEquals(
            $expectedException,
            $this->factory->create(self::ID, MachineActionInterface::ACTION_CREATE, $exception)
        );
    }

    /**
     * @return array<mixed>
     */
    public function createDataProvider(): array
    {
        $request = \Mockery::mock(RequestInterface::class);

        $curl7ConnectException = new ConnectException(
            'cURL error 7: Further non-relevant information including "cURL error: 88"',
            $request
        );

        $curl28ConnectException = new ConnectException(
            'cURL error 28: Further non-relevant information',
            $request
        );

        return [
            'curl 7' => [
                'exception' => $curl7ConnectException,
                'expectedException' => new CurlException(7, self::ID, self::ACTION, $curl7ConnectException),
            ],
            'curl 28' => [
                'exception' => $curl28ConnectException,
                'expectedException' => new CurlException(28, self::ID, self::ACTION, $curl28ConnectException),
            ],
        ];
    }

    public function testCreateForUnhandledException(): void
    {
        self::assertNull(
            $this->factory->create(
                self::ID,
                MachineActionInterface::ACTION_GET,
                new \Exception()
            )
        );
    }
}
