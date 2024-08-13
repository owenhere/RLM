<?php

namespace cbd\rlm\migrations;

use Craft;
use craft\db\Migration;
use craft\helpers\MigrationHelper;
use cbd\rlm\records\RlmLicence;

/**
 * m181123_155643_productbuild migration.
 */
class m181123_155643_productbuild extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        MigrationHelper::renameColumn(RlmLicence::tableName(), 'transactionId', 'lineItemId', $this);
        $this->addColumn('{{%rlm_licence_strings}}', 'productBuild', $this->string());
        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m181123_155643_productbuild cannot be reverted.\n";
        return false;
    }
}
