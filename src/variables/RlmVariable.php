<?php
/**
 * rlm plugin for Craft CMS 3.x
 *
 * RLM Licence Tools
 *
 * @link      coffeebean.design
 * @copyright Copyright (c) 2018 Coffee Bean Design
 */

namespace cbd\rlm\variables;

use cbd\rlm\Rlm;

use Craft;

/**
 * @author    Coffee Bean Design
 * @package   Rlm
 * @since     0.0.1
 */
class RlmVariable
{
    /**
     * @return mixed
     */
    public function licence()
    {
        return craft()->elements->getCriteria('RlmLicence');
    }

    /**
     * @return mixed
     */
    public function licenceType()
    {
        return craft()->elements->getCriteria('RlmLicenceType');
    }

    /**
     * @return int
     */
    public function totalAvailableLicences($typeId = null)
    {
        return $typeId ? Rlm::$plugin->service->getTotalAvailableByType($typeId) : 0;
    }

    /**
     * @return mixed
     */
    public function getProductByPaymentFormId($formId)
    {
        return Rlm::$plugin->service->getProductByPaymentFormId($formId);
    }

    /**
     * @return mixed
     */
    public function getProductPaymentFormIds($productId)
    {
        return Rlm::$plugin->service->getProductPaymentFormIds($productId);
    }

    /**
     * @return mixed
     */
    public function getLicenceTotals()
    {        
        return Rlm::$plugin->service->getLicenceTotals();
    }

    /**
     * @return mixed
     */
    public function getLicenceTypes()
    {
        return Rlm::$plugin->service->getLicenceTypes();
    }

    /**
     * @return mixed
     */
    public function getLicenceTypeOptions()
    {
        return Rlm::$plugin->service->getLicenceTypeOptions();
    }

    /**
     * @return mixed
     */
    public function getTrialLicence($typeId, $userId = null, $submissionId = null)
    {
        return Rlm::$plugin->service->getTrialLicence($typeId, $this->getUser($userId), $submissionId);
    }

    /**
     * @return mixed
     */
    public function getMultipleTrialLicence($typeId, $trialQuantity, $userId = null, $submissionId = null)
    {
        return Rlm::$plugin->service->getMultipleTrialLicence($typeId, $trialQuantity, $this->getUser($userId), $submissionId);
    }

    /**
     * @return mixed
     */
    public function getTrialExpiry($typeId, $userId = null)
    {
        return Rlm::$plugin->service->getTrialExpiry($typeId, $this->getUser($userId));
    }

    /**
     * @param null $userId
     * @param string $duration
     * @return mixed
     */
    public function getUserLicences($userId = null, $duration = 'permanent')
    {
        return Rlm::$plugin->service->getUserLicences($this->getUser($userId)->id, $duration);
    }

    /**
     * @param null $userId
     * @param string $duration
     * @return mixed
     */
    public function getUserProductLicences($userId = null, $duration = 'permanent')
    {
        return Rlm::$plugin->service->getUserProductLicences($this->getUser($userId)->id, $duration);
    }

    /**
     * @param $licenceString
     */
    public function getLicenceExpirationDate($licenceString)
    {
        Rlm::$plugin->service->getLicenceExpirationDate($licenceString);
    }

    /**
     * @param $productId
     * @param $assetId
     * @return string
     */
    public function getDownloadLink($productId, $assetId)
    {
        return '/rlm/download/' . $productId . '/' . $assetId;
    }

    /**
     * @param null $userId
     * @return UserModel
     */
    private function getUser($userId = null)
    {
        if (is_null($userId)) {
            $user = Craft::$app->getUser();
        }
        else {
            $user = Craft::$app->users->getUserById($userId);
        }
        return $user;
    }
}
