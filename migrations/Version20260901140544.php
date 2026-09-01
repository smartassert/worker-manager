<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Machine;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260901140544 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ' . Machine::class . '.notifyUrl';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE machine ADD notify_url VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE machine DROP notify_url');
    }
}
