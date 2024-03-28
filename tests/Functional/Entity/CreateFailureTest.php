<?php

declare(strict_types=1);

namespace App\Tests\Functional\Entity;

use App\Entity\CreateFailure;
use App\Enum\CreateFailure\Code;
use App\Enum\CreateFailure\Reason;
use App\Repository\CreateFailureRepository;
use App\Tests\Functional\AbstractEntityTestCase;
use App\Tests\Services\EntityRemover;

class CreateFailureTest extends AbstractEntityTestCase
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

        $entity = new CreateFailure(self::MACHINE_ID, Code::UNKNOWN, Reason::UNKNOWN);

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        self::assertCount(1, $repository->findAll());
    }

    /**
     * @dataProvider retrieveDataProvider
     */
    public function testRetrieve(CreateFailure $entity): void
    {
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $this->entityManager->clear();

        $createFailureRepository = self::getContainer()->get(CreateFailureRepository::class);
        \assert($createFailureRepository instanceof CreateFailureRepository);

        $retrievedEntity = $createFailureRepository->find(self::MACHINE_ID);
        self::assertInstanceOf(CreateFailure::class, $retrievedEntity);
        self::assertSame($entity->jsonSerialize(), $retrievedEntity->jsonSerialize());
    }

    /**
     * @return array<mixed>
     */
    public function retrieveDataProvider(): array
    {
        return [
            'without context' => [
                'entity' => new CreateFailure(
                    self::MACHINE_ID,
                    Code::UNKNOWN,
                    Reason::UNKNOWN
                ),
            ],
            'with context' => [
                'entity' => new CreateFailure(
                    self::MACHINE_ID,
                    Code::UNKNOWN,
                    Reason::UNKNOWN,
                    [
                        'key1' => 'value1',
                    ]
                ),
            ],
        ];
    }
}
