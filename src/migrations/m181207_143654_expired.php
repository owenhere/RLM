<?php

namespace cbd\rlm\migrations;

use Craft;
use craft\db\Migration;

/**
 * m181207_143654_expired migration.
 */
class m181207_143654_expired extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn('{{%rlm_licence_types}}', 'duration', $this->string());
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m181207_143654_expired cannot be reverted.\n";
        return false;
    }
}
