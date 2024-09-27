<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model\DigitalOcean;

use App\Model\DigitalOcean\RemoteMachine;
use DigitalOceanV2\Entity\Droplet as DropletEntity;
use PHPUnit\Framework\TestCase;

class RemoteMachineTest extends TestCase
{
    /**
     * @dataProvider getIpAddressesDataProvider
     *
     * @param array<mixed> $dropletData
     * @param string[]     $expectedIpAddresses
     */
    public function testGetIpAddresses(array $dropletData, array $expectedIpAddresses): void
    {
        $dropletEntity = new DropletEntity($dropletData);
        $remoteMachine = new RemoteMachine($dropletEntity);

        self::assertSame($expectedIpAddresses, $remoteMachine->getIpAddresses());
    }

    /**
     * @return array<mixed>
     */
    public static function getIpAddressesDataProvider(): array
    {
        return [
            'empty' => [
                'dropletData' => [],
                'expectedIpAddresses' => [],
            ],
            'no networks' => [
                'dropletData' => [
                    'networks' => (object) [],
                ],
                'expectedIpAddresses' => [],
            ],
            'no v4 networks' => [
                'dropletData' => [
                    'networks' => (object) [
                        'v6' => [
                            (object) [
                                'ip_address' => '::1',
                                'netmask' => 64,
                                'type' => 'public',
                            ],
                        ],
                    ],
                ],
                'expectedIpAddresses' => [],
            ],
            'has v4 networks' => [
                'dropletData' => [
                    'networks' => (object) [
                        'v4' => [
                            (object) [
                                'ip_address' => '127.0.0.1',
                                'type' => 'public',
                            ],
                            (object) [
                                'ip_address' => '10.0.0.1',
                                'type' => 'public',
                            ],
                        ],
                    ],
                ],
                'expectedIpAddresses' => [
                    '127.0.0.1',
                    '10.0.0.1',
                ],
            ],
            'has v4 and v6 networks' => [
                'dropletData' => [
                    'networks' => (object) [
                        'v4' => [
                            (object) [
                                'ip_address' => '127.0.0.1',
                                'type' => 'public',
                            ],
                            (object) [
                                'ip_address' => '10.0.0.1',
                                'type' => 'public',
                            ],
                        ],
                        'v6' => [
                            (object) [
                                'ip_address' => '::1',
                                'netmask' => 64,
                                'type' => 'public',
                            ],
                        ],
                    ],
                ],
                'expectedIpAddresses' => [
                    '127.0.0.1',
                    '10.0.0.1',
                ],
            ],
        ];
    }
}
