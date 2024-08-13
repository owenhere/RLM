<?php
namespace cbd\rlm\migrations;

use cbd\rlm\elements\RlmLicence;
use cbd\rlm\elements\RlmLicenceType;
use craft\db\Migration;

class Install extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%rlm_licence_strings}}', [
            'id' => $this->primaryKey(),
            'licenceString' => $this->string(),
            'licenceTypeId' => $this->string(),
            'orderId' => $this->integer(),
            'userId' => $this->integer(),
            'productBuild' => $this->string(),
            'dateAssigned' =>  $this->dateTime(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid()
        ]);

        $this->addForeignKey(
            $this->db->getForeignKeyName('{{%rlm_licence_strings}}', 'id'),
            '{{%rlm_licence_strings}}', 'id', '{{%elements}}', 'id', 'CASCADE', null);

        $this->createTable('{{%rlm_licence_types}}', [
            'id' => $this->primaryKey(),
            'duration' => $this->string(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid()
        ]);

        $this->addForeignKey(
            $this->db->getForeignKeyName('{{%rlm_licence_types}}', 'id'),
            '{{%rlm_licence_types}}', 'id', '{{%elements}}', 'id', 'CASCADE', null);

        return true;
    }

    public function safeDown()
    {
        $this->dropTable('{{%rlm_licence_strings}}');
        $this->dropTable('{{%rlm_licence_types}}');
        $this->delete('{{%elementindexsettings}}', ['type' => [RlmLicence::class]]);
        $this->delete('{{%elementindexsettings}}', ['type' => [RlmLicenceTypes::class]]);
        return true;
    }
}