<?php

declare(strict_types=1);

namespace NsCrawler\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230409135350 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            create table Settings
            (
                id int not null PRIMARY KEY AUTO_INCREMENT,
                name varchar(255) not null,
                value text default null
            );
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("drop table Settings");
    }
}
