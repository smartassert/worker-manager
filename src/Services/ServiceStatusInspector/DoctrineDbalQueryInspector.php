<?php

namespace App\Services\ServiceStatusInspector;

use Doctrine\DBAL\Tools\Console\Command\RunSqlCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

class DoctrineDbalQueryInspector
{
    public function __construct(
        private RunSqlCommand $command,
        private string $query = 'SELECT 1',
        private ?string $connection = null,
    ) {
    }

    public function __invoke(): void
    {
        $input = [
            'sql' => $this->query,
        ];

        if (null !== $this->connection) {
            $input['--connection'] = $this->connection;
        }

        $this->command->run(new ArrayInput($input), new NullOutput());
    }
}
