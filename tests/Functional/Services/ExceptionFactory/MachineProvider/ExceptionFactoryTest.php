<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\ExceptionFactory\MachineProvider;

use App\Exception\MachineProvider\CurlException;
use App\Exception\MachineProvider\ExceptionInterface;
use App\Model\MachineActionInterface;
use App\Services\ExceptionFactory\MachineProvider\ExceptionFactory;
use App\Tests\AbstractBaseFunctionalTest;
use GuzzleHttp\Exception\ConnectException;
use Psr\Http\Message\RequestInterface;

class ExceptionFactoryTest extends AbstractBaseFunctionalTest
{
    private const ID = 'resource_id';
    private const ACTION = MachineActionInterface::ACTION_CREATE;

    private ExceptionFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $factory = self::getContainer()->get(ExceptionFactory::class);
        \assert($factory instanceof ExceptionFactory);
        $this->factory = $factory;
    }

    /**
     * @dataProvider createDataProvider
     */
    public function testCreate(\Throwable $exception, ExceptionInterface $expectedException): void
    {
        self::assertEquals(
            $expectedException,
            $this->factory->create(self::ID, self::ACTION, $exception)
        );
    }

    /**
     * @return array<mixed>
     */
    public function createDataProvider(): array
    {
        $connectException = new ConnectException(
            'cURL error 7: Further non-relevant information including "cURL error: 88"',
            \Mockery::mock(RequestInterface::class)
        );

        return [
            ConnectException::class => [
                'exception' => $connectException,
                'expectedException' => new CurlException(7, self::ID, self::ACTION, $connectException),
            ],
        ];
    }
}
