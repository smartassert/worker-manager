<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Exception\MachineNotFindableException;
use App\Exception\MachineProvider\Exception;
use App\Model\MachineActionInterface;
use App\Services\MessageHandlerExceptionStackFactory;
use PHPUnit\Framework\TestCase;

class MessageHandlerExceptionStackFactoryTest extends TestCase
{
    /**
     * @dataProvider createDataProvider
     *
     * @param \Throwable[] $expected
     */
    public function testCreate(\Throwable $throwable, array $expected): void
    {
        $factory = new MessageHandlerExceptionStackFactory();

        self::assertSame($expected, $factory->create($throwable));
    }

    /**
     * @return array<mixed>
     */
    public function createDataProvider(): array
    {
        $plainException = new \Exception('plain exception');
        $remoteException = new \Exception('remote exception');

        $machineProviderException = new Exception(
            'machine_id',
            MachineActionInterface::ACTION_GET,
            $remoteException
        );

        $stackedExceptions = [
            new \Exception('stacked exception one'),
            new \Exception('stacked exception two'),
        ];

        $stackedMachineProviderException = new MachineNotFindableException('machine_id', $stackedExceptions);

        return [
            'Not ExceptionInterface, not StackedExceptionInterface' => [
                'throwable' => $plainException,
                'expected' => [
                    $plainException,
                ],
            ],
            'Is ExceptionInterface' => [
                'throwable' => $machineProviderException,
                'expected' => [
                    $machineProviderException,
                    $remoteException,
                ],
            ],
            'Is StackedExceptionInterface' => [
                'throwable' => $stackedMachineProviderException,
                'expected' => array_merge(
                    [
                        $stackedMachineProviderException,
                    ],
                    $stackedExceptions
                ),
            ],
        ];
    }
}
