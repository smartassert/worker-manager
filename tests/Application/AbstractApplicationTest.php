<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Entity\CreateFailure;
use App\Entity\Machine;
use App\Entity\MachineProvider;
use App\Tests\Services\ApplicationClient\Client;
use App\Tests\Services\Asserter\ResponseAsserter;
use App\Tests\Services\AuthenticationConfiguration;
use App\Tests\Services\EntityRemover;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class AbstractApplicationTest extends WebTestCase
{
    protected ResponseAsserter $responseAsserter;
    protected static AuthenticationConfiguration $authenticationConfiguration;
    protected static KernelBrowser $kernelBrowser;
    protected Client $applicationClient;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$kernelBrowser = self::createClient();

        $authenticationConfiguration = self::getContainer()->get(AuthenticationConfiguration::class);
        \assert($authenticationConfiguration instanceof AuthenticationConfiguration);
        self::$authenticationConfiguration = $authenticationConfiguration;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->applicationClient = $this->getApplicationClient();

        $responseAsserter = self::getContainer()->get(ResponseAsserter::class);
        \assert($responseAsserter instanceof ResponseAsserter);
        $this->responseAsserter = $responseAsserter;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeAllForEntity(CreateFailure::class);
            $entityRemover->removeAllForEntity(Machine::class);
            $entityRemover->removeAllForEntity(MachineProvider::class);
        }
    }

    abstract protected function getApplicationClient(): Client;
}
