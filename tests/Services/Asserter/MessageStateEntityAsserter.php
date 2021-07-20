<?php

declare(strict_types=1);

namespace App\Tests\Services\Asserter;

use App\Entity\MessageState;
use App\Services\Entity\Store\MessageStateStore;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;

class MessageStateEntityAsserter
{
    /**
     * @var ObjectRepository<MessageState>
     */
    private ObjectRepository $repository;

    public function __construct(
        EntityManagerInterface $entityManager,
        private MessageStateStore $messageStateStore,
    ) {
        $this->repository = $entityManager->getRepository(MessageState::class);
    }

    public function assertCount(int $expected): void
    {
        TestCase::assertCount($expected, $this->repository->findAll());
    }

    public function assertHas(MessageState $expected): void
    {
        $actual = $this->messageStateStore->find($expected->getId());
        TestCase::assertEquals($expected, $actual);
    }
}
