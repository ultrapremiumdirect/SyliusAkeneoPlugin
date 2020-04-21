<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200417054404 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Renamed akeneo attribute to akeneo attribute type.
        Makes akeneo attribute type unique on attribute type mapping table.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE 
          akeneo_attribute_type_mapping CHANGE akeneoattribute akeneoAttributeType VARCHAR(255) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_FF5E270FA2851109 ON akeneo_attribute_type_mapping (akeneoAttributeType)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP INDEX UNIQ_FF5E270FA2851109 ON akeneo_attribute_type_mapping');
        $this->addSql('ALTER TABLE 
          akeneo_attribute_type_mapping CHANGE akeneoattributetype akeneoAttribute VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`');
    }
}
