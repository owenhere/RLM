<?php
namespace cbd\rlm\elements\db;

use craft\db\Query;
use craft\elements\db\ElementQuery;
use craft\helpers\Db;
use cbd\rlm\elements\RlmLicenceType;

class RlmLicenceTypeQuery extends ElementQuery
{
    /**
     * @return bool
     */
    protected function beforePrepare(): bool
    {
        $this->joinElementTable('rlm_licence_types');

        $this->query->select([
            'rlm_licence_types.*'
        ]);

        return true;
    }
}