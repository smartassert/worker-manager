<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Enum\MachineState;
use App\Message\CheckMachineIsActive;
use App\Message\CreateMachine;
use App\Message\DeleteMachine;
use App\Message\FindMachine;
use App\Message\GetMachine;
use App\Services\MachineRequestFactory;
use App\Tests\Services\SequentialRequestIdFactory;
use PHPUnit\Framework\TestCase;

class MachineRequestFactoryTest extends TestCase
{
    private const MACHINE_ID = 'machine id';

    private MachineRequestFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = new MachineRequestFactory(
            new SequentialRequestIdFactory()
        );
    }

    public function testCreateFindThenCreate(): void
    {
        $request = $this->factory->createFindThenCreate(self::MACHINE_ID);

        self::assertInstanceOf(FindMachine::class, $request);
        self::assertSame([], $request->getOnSuccessCollection());
        self::assertSame(MachineState::CREATE_RECEIVED, $request->getOnNotFoundState());

        $expectedGetMachineRequest = new GetMachine('id2', self::MACHINE_ID);

        $expectedCheckMachineIsActiveRequest = new CheckMachineIsActive(
            'id1',
            self::MACHINE_ID,
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
        $expectedFindMachineRequest = $expectedFindMachineRequest->withOnNotFoundState(MachineState::DELETE_DELETED);
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
                $expectedGetMachineRequest,
            ]
        );

        self::assertEquals([$expectedCheckMachineIsActiveRequest], $request->getOnSuccessCollection());
        self::assertSame([], $request->getOnFailureCollection());
    }
}
