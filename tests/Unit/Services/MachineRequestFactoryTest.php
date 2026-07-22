<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Enum\MachineState;
use App\Message\CreateMachine;
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

        self::assertSame([], $request->getOnSuccessCollection());
        self::assertSame(MachineState::CREATE_RECEIVED, $request->getOnNotFoundState());

        $expectedCreateMachineRequest = new CreateMachine('id0', self::MACHINE_ID);

        self::assertEquals([$expectedCreateMachineRequest], $request->getOnFailureCollection());
    }

    public function testCreateDelete(): void
    {
        $request = $this->factory->createDelete(self::MACHINE_ID);

        self::assertEquals([], $request->getOnSuccessCollection());
        self::assertSame([], $request->getOnFailureCollection());
    }

    public function testCreateFindThenGet(): void
    {
        $request = $this->factory->createFindThenGet(self::MACHINE_ID);

        $expectedGetMachineRequest = new GetMachine('id0', self::MACHINE_ID);

        self::assertEquals([$expectedGetMachineRequest], $request->getOnSuccessCollection());
        self::assertSame([], $request->getOnFailureCollection());
    }
}
