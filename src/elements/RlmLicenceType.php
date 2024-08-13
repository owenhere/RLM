<?php
namespace cbd\rlm\elements;

use Craft;
use craft\base\Element;
use craft\elements\actions\Delete;
use craft\elements\actions\Edit;
use craft\elements\db\ElementQueryInterface;
use cbd\rlm\elements\RlmLicence;
use cbd\rlm\elements\db\RlmLicenceTypeQuery;
use cbd\rlm\records\RlmLicenceType as RlmLicenceTypeRecord;
use cbd\rlm\Rlm;

class RlmLicenceType extends Element
{
    /**
     * @var
    */
    public $id;
    public $duration;
    public $usage;
    public $totalAssigned;
    public $totalAvailable;

    /**
     * @inheritdoc
     */
    public static function refHandle()
    {
        return 'rlm_licence_type';
    }

    /**
     * @return array
     */
    public function rules()
    {
        return parent::rules();
    }

    /**
     * @param string|null $context
     * @return array
     */
    protected static function defineSources(string $context = null): array
    {
        $sources = [
            [
                'key' => '*',
                'label' => 'All Types',
                'criteria' => []
            ],
        ];
        return $sources;
    }

    /**
     * @inheritdoc
     */
    protected static function defineActions(string $source = null): array
    {
        $actions[] = Craft::$app->getElements()->createAction([
            'type' => Delete::class,
            'confirmationMessage' => Craft::t('app', 'Are you sure you want to delete the selected licence type?'),
            'successMessage' => Craft::t('app', 'Licence type deleted.'),
        ]);

        return $actions;
    }

    /**
     * @return bool
     */
    public function getIsEditable(): bool
    {
        return true;
    }

    /**
     * @return bool|void
     */
    public function beforeDelete(): bool
    {
        return RlmLicence::find()->licenceType($this->id)->count() == 0;
    }

    /**
     * @param bool $isNew
     * @throws \yii\db\Exception
     */
    public function afterSave(bool $isNew)
    {
        if ($isNew) {
            \Craft::$app->db->createCommand()
                ->insert('{{%rlm_licence_types}}', [
                    'id' => $this->id,
                    'duration' => $this->duration,
                    'usage' => $this->usage
                ])
                ->execute();
        }
        else {
            Craft::$app->db->createCommand()
                ->update('{{%rlm_licence_types}}', [
                    'duration' => $this->duration,
                    'usage' => $this->usage
                ], ['id' => $this->id])
                ->execute();
        }

        parent::afterSave($isNew);
    }

    /**
     * @return ElementQueryInterface
     */
    public static function find(): ElementQueryInterface
    {
        return new RlmLicenceTypeQuery(static::class);
    }

    /**
     * @return bool
     */
    public static function hasStatuses(): bool
    {
        return false;
    }

    /**
     *
     */
    public function getCpEditUrl()
    {
        return 'rlm/types/' . $this->id;
    }

    /**
     * @return bool
     */
    public static function hasContent(): bool
    {
        return true;
    }

    /**
     * @return bool
     */
    public static function hasTitles(): bool
    {
        return true;
    }

    /**
     * @return array
     */
    protected static function defineSortOptions(): array
    {
        return [
            'title' => Craft::t('rlm', 'Title')
        ];
    }

    /**
     * @return array
     */
    protected static function defineTableAttributes(): array
    {
        return [
            'title' => Craft::t('rlm', 'Title'),
            'id' => Craft::t('rlm', 'ID'),
            'duration' => Craft::t('rlm', 'Duration'),
            'usage' => Craft::t('rlm', 'Usage'),
            'slug' => Craft::t('rlm', 'Slug'),
            'totalAssigned' => Craft::t('rlm', 'Total Licences Assigned'),
            'totalAvailable' => Craft::t('rlm', 'Total Licences Available')
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineDefaultTableAttributes(string $source): array
    {
        return ['title', 'id', 'duration', 'usage', 'slug', 'totalAssigned', 'totalAvailable'];
    }

    /**
     * @param string $attribute
     * @return string
     */
    protected function tableAttributeHtml(string $attribute): string
    {
        switch ($attribute) {
            case 'totalAssigned':
                {
                    return Rlm::getInstance()->service->getTotalAssignedByType($this->id);
                }
            case 'totalAvailable':
                {
                    return Rlm::getInstance()->service->getTotalAvailableByType($this->id);
                }
        }

        return parent::tableAttributeHtml($attribute);
    }

    /**
     * @return array
     */
    protected static function defineSearchableAttributes(): array
    {
        return ['title', 'slug'];
    }
}
