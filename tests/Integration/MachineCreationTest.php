<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Controller\MachineController;
use App\Entity\Machine as MachineEntity;
use App\Tests\Model\Machine;

class MachineCreationTest extends AbstractIntegrationTest
{
    private const MAX_DURATION_IN_SECONDS = 120;
    private const MICROSECONDS_PER_SECOND = 1000000;

    private string $machineUrl;

    protected function setUp(): void
    {
        parent::setUp();

        $machineId = md5((string) rand());
        $this->machineUrl = str_replace('{id}', $machineId, MachineController::PATH_MACHINE);

        shell_exec('php bin/console --env=test app:test:clear-database');
    }

    public function testCreateRemoteMachine(): void
    {
        $this->assertMessageQueueIsEmpty();

        $response = $this->httpClient->post($this->machineUrl);
        self::assertSame(202, $response->getStatusCode());

        $this->assertMessageQueueNotEmpty();

        $this->assertEventualMachineState(MachineEntity::STATE_UP_ACTIVE);
        $this->deleteMachine();
        $this->assertEventualMachineState(MachineEntity::STATE_DELETE_DELETED);

        $this->assertMessageQueueIsEmpty();
    }

    public function testStatusForMissingLocalMachine(): void
    {
        $this->assertMessageQueueIsEmpty();

        $response = $this->httpClient->post($this->machineUrl);
        self::assertSame(202, $response->getStatusCode());

        sleep(3);

        shell_exec('php bin/console --env=test app:test:clear-database');

        $response = $this->httpClient->get($this->machineUrl);
        self::assertSame(200, $response->getStatusCode());

        $this->assertMessageQueueNotEmpty();

        $expectedStates = [
            MachineEntity::STATE_FIND_RECEIVED,
            MachineEntity::STATE_FIND_FINDING,
        ];

        $state = $this->getMachine()->getState();

        self::assertTrue(
            in_array($state, $expectedStates),
            'Machine state not ' . implode(', ', $expectedStates) . ': ' . $state
        );

        $this->assertEventualMachineState(MachineEntity::STATE_UP_ACTIVE);
        $this->deleteMachine();
        $this->assertEventualMachineState(MachineEntity::STATE_DELETE_DELETED);

        $this->assertMessageQueueIsEmpty();
    }

    /**
     * @param MachineEntity::STATE_* $stopState
     */
    private function waitUntilMachineStateIs(string $stopState): bool
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

    private function getMachine(): Machine
    {
        $response = $this->httpClient->get($this->machineUrl);
        self::assertSame(200, $response->getStatusCode());

        return new Machine(json_decode((string) $response->getBody()->getContents(), true));
    }

    private function deleteMachine(): void
    {
        $response = $this->httpClient->delete($this->machineUrl);
        self::assertSame(202, $response->getStatusCode());
    }

    /**
     * @param MachineEntity::STATE_* $state
     */
    private function assertEventualMachineState(string $state): void
    {
        $waitResult = $this->waitUntilMachineStateIs($state);
        if (false === $waitResult) {
            $this->fail(sprintf(
                'Timed out waiting for expected machine state. Expected: %s, actual: %s',
                $state,
                $this->getMachine()->getState()
            ));
        }

        self::assertSame($state, $this->getMachine()->getState());
    }

    private function assertMessageQueueIsEmpty(): void
    {
        self::assertSame(0, $this->getMessageQueueSize());
    }

    private function assertMessageQueueNotEmpty(): void
    {
        self::assertGreaterThan(0, $this->getMessageQueueSize());
    }

    private function getMessageQueueSize(): int
    {
        $response = $this->httpClient->get('/message-queue-size');
        self::assertSame(200, $response->getStatusCode());

        return (int) $response->getBody()->getContents();
    }
}
