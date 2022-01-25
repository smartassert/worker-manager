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
     */
    public function testDispatch(MessageBusInterface $messageBus, MachineRequestInterface $request): void
    {
        $dispatcher = new MachineRequestDispatcher($messageBus);

        $dispatcher->dispatch($request);
    }

    /**
     * @return array<mixed>
     */
    public function dispatchDataProvider(): array
    {
        $createMachineRequest = new CreateMachine('id0', self::MACHINE_ID);

        $checkMachineActiveStamps = [new DelayStamp(10000)];
        $checkMachineIsActiveRequest = new CheckMachineIsActive('id1', self::MACHINE_ID, $checkMachineActiveStamps);

        $checkMachineIsActiveRequestWithoutStamps = clone $checkMachineIsActiveRequest;
        $checkMachineIsActiveRequestWithoutStamps->clearStamps();

        return [
            'un-stamped request' => [
                'messageBus' => $this->createMessageBus($createMachineRequest, []),
                'request' => $createMachineRequest,
            ],
            'stamped request' => [
                'messageBus' => $this->createMessageBus(
                    $checkMachineIsActiveRequestWithoutStamps,
                    $checkMachineActiveStamps
                ),
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
