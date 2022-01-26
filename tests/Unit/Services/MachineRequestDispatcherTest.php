<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Message\CheckMachineIsActive;
use App\Message\CreateMachine;
use App\Message\MachineRequestInterface;
use App\Services\MachineRequestDispatcher;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;

class MachineRequestDispatcherTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const MACHINE_ID = 'machine id';

    /**
     * @dataProvider dispatchDataProvider
     *
     * @param array<class-string, int> $dispatchDelays
     */
    public function testDispatch(
        MessageBusInterface $messageBus,
        array $dispatchDelays,
        MachineRequestInterface $request
    ): void {
        $dispatcher = new MachineRequestDispatcher($messageBus, $dispatchDelays);

        $dispatcher->dispatch($request);
    }

    /**
     * @return array<mixed>
     */
    public function dispatchDataProvider(): array
    {
        $checkMachineIsActiveDispatchDelay = 10000;

        $createMachineRequest = new CreateMachine('id0', self::MACHINE_ID);

        $checkMachineActiveStamps = [new DelayStamp($checkMachineIsActiveDispatchDelay)];
        $checkMachineIsActiveRequest = new CheckMachineIsActive('id1', self::MACHINE_ID);

        return [
            'request with no dispatch delay' => [
                'messageBus' => $this->createMessageBus($createMachineRequest, []),
                'dispatchDelays' => [],
                'request' => $createMachineRequest,
            ],
            'request with dispatch delay' => [
                'messageBus' => $this->createMessageBus($checkMachineIsActiveRequest, $checkMachineActiveStamps),
                'dispatchDelays' => [
                    CheckMachineIsActive::class => $checkMachineIsActiveDispatchDelay,
                ],
                'request' => $checkMachineIsActiveRequest,
            ],
        ];
    }

    /**
     * @param StampInterface[] $expectedStamps
     */
    private function createMessageBus(
        MachineRequestInterface $expectedRequest,
        array $expectedStamps
    ): MessageBusInterface {
        $messageBus = \Mockery::mock(MessageBusInterface::class);
        $messageBus
            ->shouldReceive('dispatch')
            ->withArgs(
                function (MachineRequestInterface $request, array $stamps) use ($expectedRequest, $expectedStamps) {
                    self::assertEquals($expectedRequest, $request);
                    self::assertEquals($expectedStamps, $stamps);

                    return true;
                }
            )
            ->andReturn(new Envelope(new \stdClass()))
        ;

        return $messageBus;
    }
}
