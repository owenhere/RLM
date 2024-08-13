<?php
/**
 * rlm plugin for Craft CMS 3.x
 *
 * RLM Licence Tools
 *
 * @link      coffeebean.design
 * @copyright Copyright (c) 2018 Coffee Bean Design
 */

namespace cbd\rlm\models;

use cbd\rlm\Rlm;

use Craft;
use craft\base\Model;

/**
 * @author    Coffee Bean Design
 * @package   Rlm
 * @since     0.0.1
 */
class Settings extends Model
{
    /**
     * @var string
     */
    public $licenceTypes = [];

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [];
    }

    public function getLicenceTypes()
    {
        return [];

        return $this->licenceTypes;
    }
}
