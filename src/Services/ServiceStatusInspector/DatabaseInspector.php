<?php

namespace App\Services\ServiceStatusInspector;

use App\Entity\CreateFailure;
use App\Entity\Machine;
use App\Entity\MachineIdInterface;
use App\Entity\MachineProvider;
use Doctrine\ORM\EntityManagerInterface;

class DatabaseInspector implements ComponentInspectorInterface
{
    public const ENTITY_CLASS_NAMES = [
        CreateFailure::class,
        Machine::class,
        MachineProvider::class,
    ];

    private const MACHINE_ID_PREFIX = 'di-';

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(): void
    {
        $machineId = $this->generateMachineId();

        foreach (self::ENTITY_CLASS_NAMES as $entityClassName) {
            $this->entityManager->find($entityClassName, $machineId);
        }
    }

    private function generateMachineId(): string
    {
        $suffixLength = MachineIdInterface::LENGTH - strlen(self::MACHINE_ID_PREFIX);
        $suffix = substr(md5((string) rand()), 0, $suffixLength);

        return self::MACHINE_ID_PREFIX . $suffix;
    }
}
