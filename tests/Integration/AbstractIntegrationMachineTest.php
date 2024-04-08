<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Enum\MachineState;
use App\Tests\Application\AbstractMachineTest;
use App\Tests\Model\Machine;

abstract class AbstractIntegrationMachineTest extends AbstractMachineTest
{
    use GetApplicationClientTrait;

    private const int MAX_DURATION_IN_SECONDS = 120;
    private const int MICROSECONDS_PER_SECOND = 1000000;

    protected string $machineId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->machineId = md5((string) rand());
    }

    protected function getMachine(): Machine
    {
        $response = $this->makeValidStatusRequest($this->machineId);
        self::assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getBody()->getContents(), true);
        self::assertIsArray($data);

        return new Machine($data);
    }

    protected function assertEventualMachineState(MachineState $state): void
    {
        $waitResult = $this->waitUntilMachineStateIs($state);
        if (false === $waitResult) {
            $this->fail(sprintf(
                'Timed out waiting for expected machine state. Expected: %s, actual: %s',
                $state->value,
                $this->getMachine()->getState()->value
            ));
        }

        self::assertSame($state, $this->getMachine()->getState());
    }

    private function waitUntilMachineStateIs(MachineState $stopState): bool
    {
        $duration = 0;
        $maxDuration = self::MAX_DURATION_IN_SECONDS * self::MICROSECONDS_PER_SECOND;
        $intervalInMicroseconds = 100000;

        while ($stopState !== $this->getMachine()->getState()) {
            usleep($intervalInMicroseconds);
            $duration += $intervalInMicroseconds;

            if ($duration >= $maxDuration) {
                return false;
            }
        }

        return true;
    }
}
