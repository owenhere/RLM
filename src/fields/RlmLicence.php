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
class RlmLicence extends Field
{
    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('rlm', 'RLM Licence');
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
        $licences = Rlm::$plugin->service->getLicenceStringsByOrder($element->id);

        if ( ! count($licences)) {
            return $this->wrapCode('NO LICENCES');
        }
        $return = '';
        foreach ($licences as $string) {
            $return .= $this->wrapCode($string);
        }

        return $return;
    }
    
    private function wrapCode($string) {
        return '<code style="padding:10px;border:1px solid rgba(0, 0, 20, 0.1);border-radius:2px;margin:10px 0 10px 0;display:block;">' . $string . '</code>';
    }    
}
