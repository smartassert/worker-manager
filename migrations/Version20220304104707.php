<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\MessageState;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220304104707 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create table for ' . MessageState::class;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE message_state (
                id VARCHAR(32) NOT NULL, 
                state VARCHAR(255) NOT NULL, 
                PRIMARY KEY(id)
            )
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE message_state');
    }
}
