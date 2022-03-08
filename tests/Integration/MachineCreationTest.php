<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Controller\MachineController;
use App\Entity\Machine as MachineEntity;
use App\Tests\Model\Machine;
use Psr\Http\Message\ResponseInterface;

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
    }

    public function testCreateRemoteMachine(): void
    {
        $response = $this->makeMachineRequest('POST');
        self::assertSame(202, $response->getStatusCode());

        $this->assertEventualMachineState(MachineEntity::STATE_UP_ACTIVE);
        $this->deleteMachine();
        $this->assertEventualMachineState(MachineEntity::STATE_DELETE_DELETED);
    }

    public function testStatusForMissingLocalMachine(): void
    {
        $response = $this->makeMachineRequest('POST');
        self::assertSame(202, $response->getStatusCode());

        sleep(3);

        shell_exec(sprintf(
            'docker-compose -f tests/docker/docker-compose.yml exec -T app php bin/console doctrine:query:sql "%s"',
            'DELETE From machine;'
        ));

        $response = $this->makeMachineRequest('GET');
        self::assertSame(200, $response->getStatusCode());

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
    }

    protected function makeMachineRequest(string $method): ResponseInterface
    {
        return parent::makeRequest($method, $this->machineUrl, 'valid-token');
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
        $response = $this->makeMachineRequest('GET');

        self::assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getBody()->getContents(), true);
        self::assertIsArray($data);

        return new Machine($data);
    }

    private function deleteMachine(): void
    {
        $response = $this->makeMachineRequest('DELETE');
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
}
