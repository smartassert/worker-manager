<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Machine;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20220304104056 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create table for ' . Machine::class;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE machine (
                id VARCHAR(32) NOT NULL, 
                state VARCHAR(255) NOT NULL, 
                ip_addresses JSON DEFAULT NULL, 
                PRIMARY KEY(id)
            )
        ');
        $this->addSql('COMMENT ON COLUMN machine.ip_addresses IS NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE machine');
    }
}
