<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Controller\VersionController;
use App\Tests\AbstractBaseFunctionalTest;

class VersionControllerTest extends AbstractBaseFunctionalTest
{
    public function testGet(): void
    {
        $this->client->request('GET', VersionController::ROUTE);

        $response = $this->client->getResponse();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(
            self::$container->getParameter('version'),
            $response->getContent()
        );
    }
}
