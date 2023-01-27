<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Enum\MachineState;
use App\Tests\Application\AbstractMachineTest;
use App\Tests\Model\Machine;

class MachineCreationTest extends AbstractMachineTest
{
    use GetApplicationClientTrait;

    private const MAX_DURATION_IN_SECONDS = 120;
    private const MICROSECONDS_PER_SECOND = 1000000;

    private string $machineId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->machineId = md5((string) rand());
    }

    public function testCreateRemoteMachine(): void
    {
        $response = $this->makeValidCreateRequest($this->machineId);
        $this->responseAsserter->assertMachineCreateResponse(
            $response,
            $this->machineId,
            null
        );

        $this->assertEventualMachineState(MachineState::UP_ACTIVE);
        $this->deleteMachine();
        $this->assertEventualMachineState(MachineState::DELETE_DELETED);
    }

    public function testStatusForMissingLocalMachine(): void
    {
        $createResponse = $this->makeValidCreateRequest($this->machineId);
        $this->responseAsserter->assertMachineCreateResponse(
            $createResponse,
            $this->machineId,
            []
        );

        sleep(3);

        shell_exec(sprintf(
            'docker-compose -f tests/build/docker-compose.yml exec -T app php bin/console doctrine:query:sql "%s"',
            'DELETE From machine;'
        ));

        $statusResponse = $this->makeValidStatusRequest($this->machineId);
        self::assertSame(200, $statusResponse->getStatusCode());

        /**
         * @var MachineState[] $expectedStates
         */
        $expectedStates = [
            MachineState::FIND_RECEIVED,
            MachineState::FIND_FINDING,
        ];

        $expectedStatesAsStrings = [];
        foreach ($expectedStates as $expectedState) {
            $expectedStatesAsStrings[] = $expectedState->value;
        }

        $state = $this->getMachine()->getState();

        self::assertTrue(
            in_array($state, $expectedStates),
            'Machine state not ' . implode(', ', $expectedStatesAsStrings) . ': ' . $state->value
        );

        $this->assertEventualMachineState(MachineState::UP_ACTIVE);
        $this->deleteMachine();
        $this->assertEventualMachineState(MachineState::DELETE_DELETED);
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

    private function getMachine(): Machine
    {
        $response = $this->makeValidStatusRequest($this->machineId);
        self::assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getBody()->getContents(), true);
        self::assertIsArray($data);

        return new Machine($data);
    }

    private function deleteMachine(): void
    {
        $response = $this->makeValidDeleteRequest($this->machineId);
        $this->responseAsserter->assertMachineDeleteResponse($response, $this->machineId, false, null);
    }

    private function assertEventualMachineState(MachineState $state): void
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
}
