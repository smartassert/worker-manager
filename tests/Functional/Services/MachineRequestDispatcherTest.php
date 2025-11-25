<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Message\CheckMachineIsActive;
use App\Message\GetMachine;
use App\Message\MachineRequestInterface;
use App\Services\MachineRequestDispatcher;
use App\Tests\AbstractBaseFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;
use webignition\ObjectReflector\ObjectReflector;

class MachineRequestDispatcherTest extends AbstractBaseFunctionalTestCase
{
    private MachineRequestDispatcher $machineRequestDispatcher;
    private int $checkMachineIsActiveDispatchDelay;

    protected function setUp(): void
    {
        parent::setUp();

        $machineRequestDispatcher = self::getContainer()->get(MachineRequestDispatcher::class);
        \assert($machineRequestDispatcher instanceof MachineRequestDispatcher);
        $this->machineRequestDispatcher = $machineRequestDispatcher;

        $checkMachineIsActiveDispatchDelay = self::getContainer()->getParameter('machine_is_active_dispatch_delay');
        \assert(is_int($checkMachineIsActiveDispatchDelay));
        $this->checkMachineIsActiveDispatchDelay = $checkMachineIsActiveDispatchDelay;
    }

    public function testDispatchDelayConfiguration(): void
    {
        self::assertGreaterThan(0, $this->checkMachineIsActiveDispatchDelay);

        self::assertSame(
            [
                CheckMachineIsActive::class => $this->checkMachineIsActiveDispatchDelay,
            ],
            ObjectReflector::getProperty(
                $this->machineRequestDispatcher,
                'dispatchDelays',
                MachineRequestDispatcher::class
            )
        );
    }

    public function testMessageBusIsMessageBus(): void
    {
        $messageBus = ObjectReflector::getProperty($this->machineRequestDispatcher, 'messageBus');
        self::assertInstanceOf(MessageBusInterface::class, $messageBus);
    }

    /**
     * @param callable(int $checkMachineIsActiveDispatchDelay): StampInterface[] $expectedStampsCreator
     */
    #[DataProvider('dispatchDataProvider')]
    public function testDispatch(MachineRequestInterface $request, callable $expectedStampsCreator): void
    {
        $expectedStamps = $expectedStampsCreator($this->checkMachineIsActiveDispatchDelay);

        $messageBus = \Mockery::mock(MessageBusInterface::class);
        $messageBus
            ->shouldReceive('dispatch')
            ->withArgs(function ($passedRequest, $passedStamps) use ($request, $expectedStamps) {
                self::assertSame($request, $passedRequest);
                self::assertEquals($expectedStamps, $passedStamps);

                return true;
            })
            ->andReturn(new Envelope($request))
        ;

        ObjectReflector::setProperty(
            $this->machineRequestDispatcher,
            MachineRequestDispatcher::class,
            'messageBus',
            $messageBus
        );

        $this->machineRequestDispatcher->dispatch($request);
    }

    /**
     * @return array<mixed>
     */
    public static function dispatchDataProvider(): array
    {
        return [
            'with delay' => [
                'request' => new CheckMachineIsActive('uniqueId', 'machineId'),
                'expectedStampsCreator' => function (int $checkMachineIsActiveDispatchDelay) {
                    return [
                        new DelayStamp($checkMachineIsActiveDispatchDelay),
                    ];
                },
            ],
            'without delay' => [
                'request' => new GetMachine('uniqueId', 'machineId'),
                'expectedStampsCreator' => function () {
                    return [];
                },
            ],
        ];
    }

    public function testDispatchCollection(): void
    {
        $requestIndex = 0;

        $requests = [
            new CheckMachineIsActive('uniqueId', 'machineId'),
            new GetMachine('uniqueId', 'machineId'),
        ];

        $stampCollections = [
            [new DelayStamp($this->checkMachineIsActiveDispatchDelay)],
            [],
        ];

        $messageBus = \Mockery::mock(MessageBusInterface::class);
        $messageBus
            ->shouldReceive('dispatch')
            ->withArgs(function ($passedRequest, $passedStamps) use (&$requestIndex, $requests, $stampCollections) {
                $expectedRequest = $requests[$requestIndex] ?? null;
                self::assertInstanceOf(MachineRequestInterface::class, $expectedRequest);
                self::assertSame($expectedRequest, $passedRequest);

                $expectedStamps = $stampCollections[$requestIndex] ?? null;
                self::assertIsArray($expectedStamps);
                self::assertEquals($expectedStamps, $passedStamps);

                ++$requestIndex;

                return true;
            })
            ->andReturn(new Envelope(new GetMachine('uniqueId', 'machineId')))
        ;

        ObjectReflector::setProperty(
            $this->machineRequestDispatcher,
            MachineRequestDispatcher::class,
            'messageBus',
            $messageBus
        );

        $this->machineRequestDispatcher->dispatchCollection($requests);
    }
}
