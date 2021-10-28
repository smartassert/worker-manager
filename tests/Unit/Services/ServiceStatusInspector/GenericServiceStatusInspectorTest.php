<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\ServiceStatusInspector;

use App\Services\ServiceStatusInspector\ComponentInspectorInterface;
use App\Services\ServiceStatusInspector\GenericServiceStatusInspector;
use PHPUnit\Framework\TestCase;

class GenericServiceStatusInspectorTest extends TestCase
{
    /**
     * @dataProvider isAvailableDataProvider
     */
    public function testIsAvailable(GenericServiceStatusInspector $inspector, bool $expected): void
    {
        self::assertSame($expected, $inspector->isAvailable());
    }

    /**
     * @return array<mixed>
     */
    public function isAvailableDataProvider(): array
    {
        return [
            'no components' => [
                'inspector' => new GenericServiceStatusInspector([]),
                'expected' => true,
            ],
            'single component, component is available' => [
                'inspector' => new GenericServiceStatusInspector([
                    'service1' => $this->createComponentInspector(),
                ]),
                'expected' => true,
            ],
            'single component, component is unavailable by means of throwing an exception' => [
                'inspector' => new GenericServiceStatusInspector([
                    'service1' => $this->createComponentInspector(new \Exception()),
                ]),
                'expected' => false,
            ],
            'multiple component, components are all available' => [
                'inspector' => new GenericServiceStatusInspector([
                    'service1' => $this->createComponentInspector(),
                    'service2' => $this->createComponentInspector(),
                    'service3' => $this->createComponentInspector(),
                ]),
                'expected' => true,
            ],
            'multiple component, one component is unavailable by means of throwing an exception' => [
                'inspector' => new GenericServiceStatusInspector([
                    'service1' => $this->createComponentInspector(),
                    'service2' => $this->createComponentInspector(new \Exception()),
                    'service3' => $this->createComponentInspector(),
                ]),
                'expected' => false,
            ],
        ];
    }

    /**
     * @dataProvider getDataProvider
     *
     * @param array<string, bool> $expected
     */
    public function testGet(GenericServiceStatusInspector $inspector, array $expected): void
    {
        self::assertSame($expected, $inspector->get());
    }

    /**
     * @return array<mixed>
     */
    public function getDataProvider(): array
    {
        return [
            'no components' => [
                'inspector' => new GenericServiceStatusInspector([]),
                'expected' => [],
            ],
            'single component, component is available' => [
                'inspector' => new GenericServiceStatusInspector([
                    'service1' => $this->createComponentInspector(),
                ]),
                'expected' => [
                    'service1' => true,
                ],
            ],
            'single component, component is unavailable by means of throwing an exception' => [
                'inspector' => new GenericServiceStatusInspector([
                    'service1' => $this->createComponentInspector(new \Exception()),
                ]),
                'expected' => [
                    'service1' => false,
                ],
            ],
            'multiple component, components are all available' => [
                'inspector' => new GenericServiceStatusInspector([
                    'service1' => $this->createComponentInspector(),
                    'service2' => $this->createComponentInspector(),
                    'service3' => $this->createComponentInspector(),
                ]),
                'expected' => [
                    'service1' => true,
                    'service2' => true,
                    'service3' => true,
                ],
            ],
            'multiple component, one component is unavailable by means of throwing an exception' => [
                'inspector' => new GenericServiceStatusInspector([
                    'service1' => $this->createComponentInspector(),
                    'service2' => $this->createComponentInspector(new \Exception()),
                    'service3' => $this->createComponentInspector(),
                ]),
                'expected' => [
                    'service1' => true,
                    'service2' => false,
                    'service3' => true,
                ],
            ],
        ];
    }

    private function createComponentInspector(?\Exception $exception = null): ComponentInspectorInterface
    {
        $componentInspector = \Mockery::mock(ComponentInspectorInterface::class);

        if ($exception instanceof \Exception) {
            $componentInspector
                ->shouldReceive('__invoke')
                ->andThrow($exception)
            ;
        } else {
            $componentInspector
                ->shouldReceive('__invoke')
                ->andReturn(true)
            ;
        }

        return $componentInspector;
    }
}
