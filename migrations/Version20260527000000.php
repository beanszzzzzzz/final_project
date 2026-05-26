<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Add missing User entity columns
 */
final class Version20260527000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add missing columns to user table: is_verified, verification_token, verified_at';
    }

    public function up(Schema $schema): void
    {
        // Add missing columns to user table if they don't exist
        $this->addSql('ALTER TABLE `user` ADD COLUMN IF NOT EXISTS is_verified TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE `user` ADD COLUMN IF NOT EXISTS verification_token VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE `user` ADD COLUMN IF NOT EXISTS verified_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime)\'');
    }

    public function down(Schema $schema): void
    {
        // Remove the columns if rolling back
        $this->addSql('ALTER TABLE `user` DROP COLUMN IF EXISTS verified_at');
        $this->addSql('ALTER TABLE `user` DROP COLUMN IF EXISTS verification_token');
        $this->addSql('ALTER TABLE `user` DROP COLUMN IF EXISTS is_verified');
    }
}
