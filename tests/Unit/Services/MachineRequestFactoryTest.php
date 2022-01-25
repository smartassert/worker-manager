<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Entity\Machine;
use App\Message\CheckMachineIsActive;
use App\Message\CreateMachine;
use App\Message\DeleteMachine;
use App\Message\FindMachine;
use App\Message\GetMachine;
use App\Services\MachineRequestFactory;
use App\Tests\Services\SequentialRequestIdFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Stamp\DelayStamp;

class MachineRequestFactoryTest extends TestCase
{
    private const MACHINE_ID = 'machine id';
    private const CHECK_IS_ACTIVE_DISPATCH_DELAY = 10000;

    private MachineRequestFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = new MachineRequestFactory(
            new SequentialRequestIdFactory(),
            self::CHECK_IS_ACTIVE_DISPATCH_DELAY
        );
    }

    public function testCreateFindThenCreate(): void
    {
        $request = $this->factory->createFindThenCreate(self::MACHINE_ID);

        self::assertInstanceOf(FindMachine::class, $request);
        self::assertSame([], $request->getOnSuccessCollection());
        self::assertSame(Machine::STATE_FIND_NOT_FOUND, $request->getOnNotFoundState());

        $expectedGetMachineRequest = new GetMachine('id2', self::MACHINE_ID);

        $expectedCheckMachineIsActiveRequest = new CheckMachineIsActive(
            'id1',
            self::MACHINE_ID,
            [
                new DelayStamp(self::CHECK_IS_ACTIVE_DISPATCH_DELAY),
            ],
            [
                $expectedGetMachineRequest,
            ]
        );

        $expectedCreateMachineRequest = new CreateMachine(
            'id0',
            self::MACHINE_ID,
            [
                $expectedCheckMachineIsActiveRequest,
            ]
        );

        self::assertEquals([$expectedCreateMachineRequest], $request->getOnFailureCollection());
    }

    public function testCreateDelete(): void
    {
        $request = $this->factory->createDelete(self::MACHINE_ID);

        self::assertInstanceOf(DeleteMachine::class, $request);

        $expectedFindMachineRequest = new FindMachine('id0', self::MACHINE_ID);
        $expectedFindMachineRequest = $expectedFindMachineRequest->withOnNotFoundState(Machine::STATE_DELETE_DELETED);
        $expectedFindMachineRequest = $expectedFindMachineRequest->withReDispatchOnSuccess(true);

        self::assertEquals([$expectedFindMachineRequest], $request->getOnSuccessCollection());
        self::assertSame([], $request->getOnFailureCollection());
    }

    public function testCreateFindThenCheckIsActive(): void
    {
        $request = $this->factory->createFindThenCheckIsActive(self::MACHINE_ID);

        self::assertInstanceOf(FindMachine::class, $request);

        $expectedGetMachineRequest = new GetMachine('id1', self::MACHINE_ID);
        $expectedCheckMachineIsActiveRequest = new CheckMachineIsActive(
            'id0',
            self::MACHINE_ID,
            [
                new DelayStamp(self::CHECK_IS_ACTIVE_DISPATCH_DELAY),
            ],
            [
                $expectedGetMachineRequest,
            ]
        );

        self::assertEquals([$expectedCheckMachineIsActiveRequest], $request->getOnSuccessCollection());
        self::assertSame([], $request->getOnFailureCollection());
    }
}
