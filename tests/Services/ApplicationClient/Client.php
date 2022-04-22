<?php

declare(strict_types=1);

namespace App\Tests\Services\ApplicationClient;

use SmartAssert\SymfonyTestClient\ClientInterface;
use Symfony\Component\Routing\RouterInterface;

class Client
{
    public function __construct(
        private ClientInterface $client,
        private RouterInterface $router,
    ) {
    }
}
