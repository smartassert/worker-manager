<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\ActionFailure;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20220304104340 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create table for ' . ActionFailure::class;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE action_failure (
                id VARCHAR(32) NOT NULL, 
                code INT NOT NULL, 
                reason TEXT NOT NULL, 
                context JSON DEFAULT NULL, 
                PRIMARY KEY(id)
            )
        ');
        $this->addSql('COMMENT ON COLUMN action_failure.context IS NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE action_failure');
    }
}
