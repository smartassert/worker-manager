<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Entity\ActionFailure;
use App\Entity\Machine;
use App\Tests\Services\ApplicationClient\Client;
use App\Tests\Services\Asserter\JsonResponseAsserter;
use App\Tests\Services\EntityRemover;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class AbstractApplicationTest extends WebTestCase
{
    protected JsonResponseAsserter $jsonResponseAsserter;
    protected static KernelBrowser $kernelBrowser;
    protected Client $applicationClient;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$kernelBrowser = self::createClient();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->applicationClient = $this->getApplicationClient();

        $jsonResponseAsserter = self::getContainer()->get(JsonResponseAsserter::class);
        \assert($jsonResponseAsserter instanceof JsonResponseAsserter);
        $this->jsonResponseAsserter = $jsonResponseAsserter;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeAllForEntity(ActionFailure::class);
            $entityRemover->removeAllForEntity(Machine::class);
        }
    }

    abstract protected function getApplicationClient(): Client;
}
