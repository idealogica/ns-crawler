<?php

declare(strict_types=1);

namespace NsCrawler\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221213175031 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE History ADD COLUMN sentOn DATETIME null AFTER sourceId');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE History DROP COLUMN sentOn');
    }
}
