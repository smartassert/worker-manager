<?php

declare(strict_types=1);

namespace App\Tests\Functional\Messenger;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use webignition\SymfonyMessengerDelayMiddleware\DelayMiddleware;

class DelayMiddlewareTest extends WebTestCase
{
    private const string CONFIGURATION_MESSAGE_DELAYS_NAME = 'message_delays';
    private const string DELAYS_PROPERTY_NAME = 'delays';

    private DelayMiddleware $delayMiddleware;

    protected function setUp(): void
    {
        parent::setUp();

        $delayMiddleware = self::getContainer()->get(DelayMiddleware::class);
        \assert($delayMiddleware instanceof DelayMiddleware);
        $this->delayMiddleware = $delayMiddleware;
    }

    public function testConfiguredMessageDelaysArePresent(): void
    {
        $delayMiddlewareReflector = new \ReflectionClass(DelayMiddleware::class);
        self::assertTrue($delayMiddlewareReflector->hasProperty(self::DELAYS_PROPERTY_NAME));
        $delays = $delayMiddlewareReflector->getProperty(self::DELAYS_PROPERTY_NAME);

        self::assertTrue(self::getContainer()->hasParameter(self::CONFIGURATION_MESSAGE_DELAYS_NAME));
        $configurationMessageDelays = self::getContainer()->getParameter('message_delays');

        self::assertSame($configurationMessageDelays, $delays->getValue($this->delayMiddleware));
    }
}
