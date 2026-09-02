<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Machine;
use App\Repository\MachineRepository;
use App\Tests\Application\AbstractMachineTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use SmartAssert\CallbackReceiverLogReader\Parser;
use Symfony\Component\Process\Process;
use Symfony\Component\Uid\Ulid;
use webignition\WaitFor\WaitFor;

class NotificationDeliveryTest extends AbstractMachineTestCase
{
    use GetApplicationClientTrait;

    /**
     * @param callable(Machine): array<array<mixed>> $expectedRequestBodiesCreator
     */
    #[DataProvider('machineStateChangeNotificationsForMachineStatusDataProvider')]
    public function testMachineStateChangeNotificationsForMachineStatus(
        int $expectedDispatchedNotificationsCount,
        callable $expectedRequestBodiesCreator,
    ): void {
        $machineId = (string) new Ulid();
        $notifyUrl = 'http://callback-receiver:8080';
        $stopState = 'find/not-findable';

        $this->makeValidStatusRequest($machineId, $notifyUrl);

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);

        $machine = $machineRepository->find($machineId);
        self::assertInstanceOf(Machine::class, $machine);

        new WaitFor()->waitFor(
            30,
            function () use ($machineId, $stopState) {
                $jobState = $this->getMachineState($machineId);

                return $stopState === $jobState;
            },
            $machineId . '" to be in "' . $stopState . '"',
        );

        $process = Process::fromShellCommandline('docker logs callback-receiver');
        $process->run();

        $output = $process->getOutput();
        $parser = new Parser();

        $requests = $parser->parse($output, $expectedDispatchedNotificationsCount);
        self::assertCount($expectedDispatchedNotificationsCount, $requests);

        $expectedRequestBodies = $expectedRequestBodiesCreator($machine);

        foreach ($expectedRequestBodies as $requestIndex => $expectedRequestBody) {
            $request = $requests[$requestIndex];

            self::assertSame('POST', $request->getMethod());
            self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
            self::assertEquals('/worker-manager.machine.state_changed', (string) $request->getUri());

            $requestData = json_decode($request->getBody()->getContents(), true);
            self::assertIsArray($requestData);
            self::assertEquals($expectedRequestBody, $requestData);
        }
    }

    /**
     * @return array<mixed>
     */
    public static function machineStateChangeNotificationsForMachineStatusDataProvider(): array
    {
        return [
            'no pre-existing machine' => [
                'expectedDispatchedNotificationsCount' => 2,
                'expectedRequestBodiesCreator' => function (Machine $machine) {
                    return [
                        [
                            'id' => $machine->getId(),
                            'state' => 'find/finding',
                            'ip_addresses' => [],
                            'state_category' => 'finding',
                            'action_failure' => null,
                            'has_active_state' => false,
                            'has_ending_state' => false,
                            'meta_state' => [
                                'pending' => true,
                                'ended' => false,
                                'succeeded' => false,
                            ],
                            'previous_states' => [
                                'find/received',
                            ],
                        ], [
                            'id' => $machine->getId(),
                            'state' => 'find/not-findable',
                            'ip_addresses' => [],
                            'state_category' => 'end',
                            'action_failure' => null,
                            'has_active_state' => false,
                            'has_ending_state' => false,
                            'meta_state' => [
                                'pending' => false,
                                'ended' => true,
                                'succeeded' => false,
                            ],
                            'previous_states' => [
                                'unknown',
                                'find/received',
                                'find/finding',
                                'create/received',
                                'create/requested',
                                'up/started',
                                'up/active',
                                'delete/received',
                                'delete/requested',
                            ],
                        ],
                    ];
                },
            ],
        ];
    }

    private function getMachineState(string $machineId): ?string
    {
        $response = $this->makeValidStatusRequest($machineId);

        $responseData = json_decode($response->getBody()->getContents(), true);
        \assert(is_array($responseData));

        $state = $responseData['state'] ?? null;

        return is_string($state) ? $state : null;
    }
}
