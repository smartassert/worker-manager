<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Tests\Services\ApplicationClient\Client;

trait GetApplicationClientTrait
{
    protected function getApplicationClient(): Client
    {
        $client = self::getContainer()->get('app.tests.services.application.client.http');
        \assert($client instanceof Client);

        return $client;
    }
}
