<?php

declare(strict_types=1);

namespace NsCrawler\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221113212756 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            create table History
            (
                id int not null,
                source varchar(255) not null,
                sourceId varchar(255) not null,
                insertedOn DATETIME not null
            );
        ");

        $this->addSql("create unique index id_uindex on History (id);");
        $this->addSql("create index source_uindex on History (source, sourceId);");
        $this->addSql("alter table History add constraint id_pk primary key (id);");
        $this->addSql("alter table History modify id int auto_increment;");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("drop table History");
    }
}
