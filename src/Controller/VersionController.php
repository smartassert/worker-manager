<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class VersionController
{
    public const ROUTE = '/version';

    public function __construct(
        private string $version,
    ) {
    }

    #[Route(self::ROUTE, name: 'version', methods: ['GET'])]
    public function get(): Response
    {
        return new Response($this->version, 200);
    }
}
