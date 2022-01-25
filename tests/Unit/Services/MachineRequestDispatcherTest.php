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
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;
use webignition\SymfonyMessengerMessageDispatcher\MessageDispatcher;

class MachineRequestDispatcherTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const MACHINE_ID = 'machine id';

    /**
     * @dataProvider dispatchDataProvider
     */
    public function testDispatch(MessageDispatcher $messageDispatcher, MachineRequestInterface $request): void
    {
        $dispatcher = new MachineRequestDispatcher($messageDispatcher);

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
                'messageDispatcher' => $this->createMessageDispatcher($createMachineRequest, []),
                'request' => $createMachineRequest,
            ],
            'stamped request' => [
                'messageDispatcher' => $this->createMessageDispatcher(
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
    private function createMessageDispatcher(
        MachineRequestInterface $expectedRequest,
        array $expectedStamps
    ): MessageDispatcher {
        $messageDispatcher = \Mockery::mock(MessageDispatcher::class);
        $messageDispatcher
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

        return $messageDispatcher;
    }
}
