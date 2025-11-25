<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application;

use App\Model\DigitalOcean\RemoteMachine;
use App\Tests\Application\AbstractHealthCheckTestCase;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;

class HealthCheckTest extends AbstractHealthCheckTestCase
{
    use GetApplicationClientTrait;

    protected function getHealthCheckSetup(): void
    {
        $mockHandler = self::getContainer()->get('app.tests.httpclient.mocked.handler');
        if (!$mockHandler instanceof MockHandler) {
            return;
        }

        $mockHandler->append(new Response(
            200,
            [
                'Content-Type' => 'application/json',
            ],
            (string) json_encode([
                'droplets' => [
                    [
                        'id' => rand(1, PHP_INT_MAX),
                        'status' => RemoteMachine::STATE_NEW,
                    ],
                ],
            ]),
        ));
    }
}
