<?php
/**
 * rlm plugin for Craft CMS 3.x
 *
 * RLM Licence Tools
 *
 * @link      coffeebean.design
 * @copyright Copyright (c) 2018 Coffee Bean Design
 */

namespace cbd\rlm\fields;

use cbd\rlm\Rlm;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use yii\db\Schema;

/**
 * @author    Coffee Bean Design
 * @package   Rlm
 * @since     0.0.1
 */
class RlmLicenceType extends Field
{
    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('rlm', 'RLM Licence Type');
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        $rules = parent::rules();
        return $rules;
    }

    /**
     * @inheritdoc
     */
    public function getContentColumnType(): string
    {
        return Schema::TYPE_STRING;
    }

    /**
     * @inheritdoc
     */
    public function normalizeValue($value, ElementInterface $element = null)
    {
        return $value;
    }

    /**
     * @inheritdoc
     */
    public function serializeValue($value, ElementInterface $element = null)
    {
        return parent::serializeValue($value, $element);
    }

    /**
     * @inheritdoc
     */
    public function getInputHtml($value, ElementInterface $element = null): string
    {
        return Craft::$app->getView()->renderTemplate('_includes/forms/select', [
            'name' => $this->handle,
            'value' => $value,
            'options' => Rlm::$plugin->service->getLicenceTypeOptions()
        ]);
    }
}
