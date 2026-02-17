<?php

declare(strict_types=1);

namespace App\Tests\Integration\Live;

use App\Entity\Machine;
use App\Enum\MachineState;
use App\Enum\MachineStateCategory;
use App\Tests\Integration\AbstractIntegrationMachineTestCase;

class MachineCreationTest extends AbstractIntegrationMachineTestCase
{
    public function testCreateRemoteMachine(): void
    {
        $response = $this->makeValidCreateRequest($this->machineId);

        self::assertSame(202, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('content-type'));
        self::assertJsonStringEqualsJsonString(
            (string) json_encode([
                'id' => $this->machineId,
                'ip_addresses' => [],
                'state' => MachineState::CREATE_RECEIVED,
                'state_category' => MachineStateCategory::PRE_ACTIVE,
                'action_failure' => null,
                'has_failed_state' => false,
                'has_active_state' => false,
                'has_ending_state' => false,
                'has_end_state' => false,
                'meta_state' => [
                    'ended' => false,
                    'succeeded' => false,
                ],
            ]),
            $response->getBody()->getContents()
        );

        $this->assertEventualMachineState(MachineState::UP_ACTIVE);
        $this->deleteMachine();
        $this->assertEventualMachineState(MachineState::DELETE_DELETED);
    }

    public function testStatusForMissingLocalMachine(): void
    {
        $createResponse = $this->makeValidCreateRequest($this->machineId);
        self::assertSame(202, $createResponse->getStatusCode());

        sleep(3);

        shell_exec(sprintf(
            'docker compose -f tests/build/docker-compose.yml exec -T app php bin/console doctrine:query:dql "%s"',
            'DELETE FROM ' . Machine::class
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

    private function deleteMachine(): void
    {
        $response = $this->makeValidDeleteRequest($this->machineId);

        self::assertSame(202, $response->getStatusCode());
    }
}
