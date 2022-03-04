<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\MachineProvider;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20220304104551 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create table for ' . MachineProvider::class;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE machine_provider (
                id VARCHAR(32) NOT NULL, 
                provider VARCHAR(255) NOT NULL, 
                PRIMARY KEY(id)
            )
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE machine_provider');
    }
}
