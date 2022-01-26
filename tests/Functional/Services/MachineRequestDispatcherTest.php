<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Message\CheckMachineIsActive;
use App\Services\MachineRequestDispatcher;
use App\Tests\AbstractBaseFunctionalTest;
use webignition\ObjectReflector\ObjectReflector;

class MachineRequestDispatcherTest extends AbstractBaseFunctionalTest
{
    public function testDispatchDelayConfiguration(): void
    {
        $machineRequestDispatcher = self::getContainer()->get(MachineRequestDispatcher::class);
        \assert($machineRequestDispatcher instanceof MachineRequestDispatcher);

        $checkMachineIsActiveDispatchDelay = self::getContainer()->getParameter('machine_is_active_dispatch_delay');
        self::assertIsInt($checkMachineIsActiveDispatchDelay);
        self::assertGreaterThan(0, $checkMachineIsActiveDispatchDelay);

        self::assertSame(
            [
                CheckMachineIsActive::class => $checkMachineIsActiveDispatchDelay,
            ],
            ObjectReflector::getProperty($machineRequestDispatcher, 'dispatchDelays', MachineRequestDispatcher::class)
        );
    }
}
