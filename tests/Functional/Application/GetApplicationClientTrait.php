<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application;

use App\Tests\Services\ApplicationClient\Client;
use SmartAssert\SymfonyTestClient\SymfonyClient;

trait GetApplicationClientTrait
{
    protected function getApplicationClient(): Client
    {
        $adapter = self::getContainer()->get(SymfonyClient::class);
        \assert($adapter instanceof SymfonyClient);

        $adapter->setKernelBrowser(self::$kernelBrowser);

        $client = self::getContainer()->get('app.tests.services.application.client.symfony');
        \assert($client instanceof Client);

        return $client;
    }
}
