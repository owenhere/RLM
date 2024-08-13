<?php
namespace cbd\rlm\elements\db;

use craft\db\Query;
use craft\elements\db\ElementQuery;
use craft\helpers\Db;
use cbd\rlm\elements\RlmLicence;

class RlmLicenceQuery extends ElementQuery
{
    /**
     * @var
     */
    public $licenceString;
    public $licenceTypeId;
    public $licenceTypeDuration;
    public $dateAssigned;
    public $orderId;
    public $productBuild;
    public $assigned;
    public $available;
    public $userId;
    public $submissionId;

    /**
     * Licence string where query
     *
     * @param $value
     * @return $this
     */
    public function licenceString($value)
    {
        $this->licenceString = $value;
        return $this;
    }

    /**
     * Licence type where query
     *
     * @param $value
     * @return $this
     */
    public function licenceType($value)
    {
        $this->licenceTypeId = $value;
        return $this;
    }

    /**
     * Licence type where query
     *
     * @param $value
     * @return $this
     */
    public function licenceTypeDuration($value)
    {
        $this->licenceTypeDuration = $value;
        return $this;
    }

    /**
     * Licence date assigned where query
     *
     * @param $value
     * @return $this
     */
    public function dateAssigned($value)
    {
        $this->dateAssigned = $value;
        return $this;
    }

    /**
     * @return $this
     */
    public function assigned()
    {
        $this->assigned = true;
        return $this;
    }

    /**
     * @return $this
     */
    public function available()
    {
        $this->available = true;
        return $this;
    }

    /**
     * Licence product build where query
     *
     * @param $value
     * @return $this
     */
    public function productBuild($value)
    {
        $this->productBuild = $value;
        return $this;
    }

    /**
     * Licence order id where query
     *
     * @param $value
     * @return $this
     */
    public function orderId($value)
    {
        $this->orderId = $value;
        return $this;
    }

    /**
     * Licence author id where query
     *
     * @param $value
     * @return $this
     */
    public function userId($value)
    {
        $this->userId = $value;
        return $this;
    }

    /**
     * Licence author id where query
     *
     * @param $value
     * @return $this
     */
    public function submissionId($value)
    {
        $this->submissionId = $value;
        return $this;
    }

    /**
     * @return bool
     */
    protected function beforePrepare(): bool
    {
        $this->joinElementTable('rlm_licence_strings');

        $this->query->select([
            'rlm_licence_strings.licenceString',
            'rlm_licence_strings.licenceTypeId',
            'rlm_licence_strings.dateAssigned',
            'rlm_licence_strings.orderId',
            'rlm_licence_strings.productBuild',
            'rlm_licence_strings.userId',
            'rlm_licence_strings.submissionId',
        ]);

        if ($this->licenceString) {
            $this->subQuery->andWhere(Db::parseParam('rlm_licence_strings.licenceString', $this->licenceString));
        }

        if ($this->licenceTypeId) {
            $this->subQuery->andWhere(Db::parseParam('rlm_licence_strings.licenceTypeId', $this->licenceTypeId));
        }

        if ($this->licenceTypeDuration) {
            $this->join('INNER JOIN', 'rlm_licence_types','`rlm_licence_types`.`id` = `rlm_licence_strings`.`licenceTypeId`');
            $this->subQuery->andWhere(Db::parseParam('rlm_licence_types.duration', $this->licenceTypeDuration));
        }

        if ($this->dateAssigned) {
            $this->subQuery->andWhere(Db::parseParam('rlm_licence_strings.dateAssigned', $this->licenceType));
        }

        if ($this->assigned) {
            $this->subQuery->andWhere('rlm_licence_strings.dateAssigned IS NOT NULL');
        }

        if ($this->available) {
            $this->subQuery->andWhere('rlm_licence_strings.dateAssigned IS NULL');
        }

        if ($this->orderId) {
            $this->subQuery->andWhere(Db::parseParam('rlm_licence_strings.orderId', $this->orderId));
        }

        if ($this->productBuild) {
            $this->subQuery->andWhere(Db::parseParam('rlm_licence_strings.productBuild', $this->productBuild));
        }

        if ($this->userId) {
            $this->subQuery->andWhere(Db::parseParam('rlm_licence_strings.userId', $this->userId));
        }

        if ($this->submissionId) {
            $this->subQuery->andWhere(Db::parseParam('rlm_licence_strings.submissionId', $this->submissionId));
        }

        return parent::beforePrepare();
    }

    /**
     * @inheritdoc
     */
    protected function statusCondition(string $status)
    {
        switch ($status) {
            case RlmLicence::STATUS_ASSIGNED:
                return [
                    'not', ['rlm_licence_strings.dateAssigned' => null],
                ];
            case RlmLicence::STATUS_AVAILABLE:
                return [
                    'rlm_licence_strings.dateAssigned' => null,
                ];
            default:
                return parent::statusCondition($status);
        }
    }
}
