<?php

declare(strict_types=1);

namespace App\Tests\Functional\Entity;

use App\Entity\CreateFailure;
use App\Repository\CreateFailureRepository;
use App\Tests\Functional\AbstractEntityTest;
use App\Tests\Services\EntityRemover;
use webignition\ObjectReflector\ObjectReflector;

class CreateFailureTest extends AbstractEntityTest
{
    protected function setUp(): void
    {
        parent::setUp();

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeAllForEntity(CreateFailure::class);
        }
    }

    public function testEntityMapping(): void
    {
        $repository = $this->entityManager->getRepository(CreateFailure::class);
        self::assertCount(0, $repository->findAll());

        $entity = new CreateFailure(self::MACHINE_ID, CreateFailure::CODE_UNKNOWN, CreateFailure::REASON_UNKNOWN);

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        self::assertCount(1, $repository->findAll());
    }

    public function testRetrieveWithNullContext(): void
    {
        $entity = new CreateFailure(self::MACHINE_ID, CreateFailure::CODE_UNKNOWN, CreateFailure::REASON_UNKNOWN);

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $this->entityManager->clear();

        $createFailureRepository = self::getContainer()->get(CreateFailureRepository::class);
        \assert($createFailureRepository instanceof CreateFailureRepository);

        $retrievedEntity = $createFailureRepository->find(self::MACHINE_ID);
        self::assertInstanceOf(CreateFailure::class, $retrievedEntity);
        self::assertSame([], ObjectReflector::getProperty($retrievedEntity, 'context'));
    }
}
