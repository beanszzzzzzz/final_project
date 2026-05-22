<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260406123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add email verification columns to user table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` ADD is_verified TINYINT(1) DEFAULT 0 NOT NULL, ADD verification_token VARCHAR(64) DEFAULT NULL, ADD verified_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` DROP is_verified, DROP verification_token, DROP verified_at');
    }
}
