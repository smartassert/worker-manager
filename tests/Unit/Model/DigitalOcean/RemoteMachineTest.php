<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model\DigitalOcean;

use App\Model\DigitalOcean\RemoteMachine;
use App\Services\MachineManager\DigitalOcean\Entity\Droplet;
use App\Services\MachineManager\DigitalOcean\Entity\Network;
use App\Services\MachineManager\DigitalOcean\Entity\NetworkCollection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RemoteMachineTest extends TestCase
{
    /**
     * @param string[] $expectedIpAddresses
     */
    #[DataProvider('getIpAddressesDataProvider')]
    public function testGetIpAddresses(Droplet $droplet, array $expectedIpAddresses): void
    {
        $remoteMachine = new RemoteMachine($droplet);

        self::assertSame($expectedIpAddresses, $remoteMachine->getIpAddresses());
    }

    /**
     * @return array<mixed>
     */
    public static function getIpAddressesDataProvider(): array
    {
        return [
            'no networks' => [
                'droplet' => new Droplet(
                    rand(1, PHP_INT_MAX),
                    md5((string) rand()),
                    new NetworkCollection([])
                ),
                'expectedIpAddresses' => [],
            ],
            'no v4 networks' => [
                'droplet' => new Droplet(
                    rand(1, PHP_INT_MAX),
                    md5((string) rand()),
                    new NetworkCollection([
                        new Network('::1', true, 6),
                    ])
                ),
                'expectedIpAddresses' => [],
            ],
            'has v4 networks' => [
                'droplet' => new Droplet(
                    rand(1, PHP_INT_MAX),
                    md5((string) rand()),
                    new NetworkCollection([
                        new Network('127.0.0.1', true, 4),
                        new Network('10.0.0.1', true, 4),
                    ])
                ),
                'expectedIpAddresses' => [
                    '127.0.0.1',
                    '10.0.0.1',
                ],
            ],
            'has v4 and v6 networks' => [
                'droplet' => new Droplet(
                    rand(1, PHP_INT_MAX),
                    md5((string) rand()),
                    new NetworkCollection([
                        new Network('127.0.0.1', true, 4),
                        new Network('10.0.0.1', true, 4),
                        new Network('::1', true, 6),
                    ])
                ),
                'expectedIpAddresses' => [
                    '127.0.0.1',
                    '10.0.0.1',
                ],
            ],
        ];
    }
}
