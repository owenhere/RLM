<?php

namespace cbd\rlm\migrations;

use Craft;
use craft\db\Migration;

/**
 * m220511_000000_submission migration.
 */
class m220511_000000_submission extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn('{{%rlm_licence_strings}}', 'submissionId', $this->string());
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m220511_000000_submission cannot be reverted.\n";
        return false;
    }
}