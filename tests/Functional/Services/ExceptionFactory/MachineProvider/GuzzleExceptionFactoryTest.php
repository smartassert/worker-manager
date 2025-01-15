<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\ExceptionFactory\MachineProvider;

use App\Enum\MachineAction;
use App\Exception\MachineProvider\CurlException;
use App\Exception\MachineProvider\ExceptionInterface;
use App\Exception\MachineProvider\HttpClientException;
use App\Services\ExceptionFactory\MachineProvider\GuzzleExceptionFactory;
use App\Tests\AbstractBaseFunctionalTestCase;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\TransferException;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\RequestInterface;

class GuzzleExceptionFactoryTest extends AbstractBaseFunctionalTestCase
{
    private const ID = 'resource_id';
    private const ACTION = MachineAction::CREATE;

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
        $request = \Mockery::mock(RequestInterface::class);

        $curl7ConnectException = new ConnectException(
            'cURL error 7: Further non-relevant information including "cURL error: 88"',
            $request
        );

        $curl28ConnectException = new ConnectException(
            'cURL error 28: Further non-relevant information',
            $request
        );

        $transferException = new TransferException();

        return [
            'curl 7' => [
                'exception' => $curl7ConnectException,
                'expectedException' => new CurlException(7, self::ID, self::ACTION, $curl7ConnectException),
            ],
            'curl 28' => [
                'exception' => $curl28ConnectException,
                'expectedException' => new CurlException(28, self::ID, self::ACTION, $curl28ConnectException),
            ],
            'transfer exception' => [
                'exception' => $transferException,
                'expectedException' => new HttpClientException(self::ID, self::ACTION, $transferException),
            ],
        ];
    }

    public function testCreateForUnhandledException(): void
    {
        self::assertNull(
            $this->factory->create(
                self::ID,
                MachineAction::GET,
                new \Exception()
            )
        );
    }
}
