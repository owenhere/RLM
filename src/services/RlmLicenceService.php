<?php
/**
 * rlm plugin for Craft CMS 3.x
 *
 * RLM Licence Tools
 *
 * @link      coffeebean.design
 * @copyright Copyright (c) 2018 Coffee Bean Design
 */

namespace cbd\rlm\services;

use cbd\rlm\Rlm;
use cbd\rlm\elements\RlmLicence;
use cbd\rlm\elements\RlmLicenceType;

use Craft;
use craft\elements\Entry as Entry;
use craft\web\User as User;
use craft\helpers\Db;
use craft\base\Component;
use craft\elements\GlobalSet;
use craft\elements\db\ElementQuery;
use enupal\stripe\elements\Order;
use enupal\stripe\services\Orders as OrdersService;


use DateTime;

/**
 * @author    Coffee Bean Design
 * @package   Rlm
 * @since     0.0.1
 */
class RlmLicenceService extends Component
{
    private $sectionIdProducts = 8;
    private $fieldNameRlmLicenceType = 'rlmLicenceType';
    private $fieldNameDownloads = 'productDownloads';

    /**
     * @param $userId
     * @param string $duration
     * @return array
     *
     * return licences grouped by trial submission (for trial page)
     */
    public function getUserProductLicences($userId, $duration = 'permanent')
    {
        $allLicences = $this->getUserLicences($userId, $duration);
        $productLicenceStrings = [];
        foreach($allLicences as $licence) {
            if (null == $product = $this->getProductByLicenceTypeId($licence->licenceTypeId)) {
                continue;
            }
            ## group by submission (zero before May 2022)
            $key = $licence->submissionId ?: $product->id;
            if (!isset($productLicenceStrings[$key])) {
                $productLicenceStrings[$key] = [
                    'product' => $product,
                    'licenceStrings' => [],
                    'expirationDate' => $this->getLicenceExpirationDate($licence)
                ];
            }
            ## multiple licences assigned by product
            if ($product->numberOfTrialLicensesToIssue > 1) {
                $user = Craft::$app->users->getUserById($userId);
                $productLicenceStrings[$key]['licenceStrings'] = $this->getMultipleTrialLicence($product->rlmLicenceType, $product->numberOfTrialLicensesToIssue, $user, $licence->submissionId);
            }
            else {
                $productLicenceStrings[$key]['licenceStrings'][] = $licence->licenceString;
            }
        }

        return $productLicenceStrings;
    }

    /**
     * @param $licence
     * @return DateTime
     */
    public function getLicenceExpirationDate($licence)
    {
        $trialPeriod = $this->getGlobal('productGlobals', 'trialPeriod');

        ## @todo change to set trial period on product
        $licenceType = $this->getLicenceTypeById($licence->licenceTypeId);

        /*
        if ($licenceType->slug == 'faceware-trial'){
            $trialPeriod = 90;
        }
        */

        $assignedTimestamp = $licence->dateAssigned ? $licence->dateAssigned->getTimestamp() : time();
        $trialEndTimestamp = $assignedTimestamp + ($trialPeriod * (60 * 60 * 24));

        $date = new DateTime();
        $date->setTimestamp($trialEndTimestamp);
        return $date;
    }

    /**
     * @param int $userId
     * @param string $duration
     * @return mixed
     */
    public function getUserLicences($userId, $duration = 'permanent')
    {
        return RlmLicence::find()->userId($userId)->licenceTypeDuration($duration)->all();
    }


    /**
     * @param $typeId
     * @return \craft\base\ElementInterface|Entry|null
     */
    public function getProductByLicenceTypeId($typeId)
    {
        $products = Entry::find()
            ->sectionId($this->sectionIdProducts)
            ->all();

        ## loop through all the product pages
        foreach($products as $product) {
            if ($product->rlmLicenceType == $typeId) {
                return $product;
            }
        }
        return null;
    }

     /**
     * @param Order $order
     */
    public function updateLicencePaymentDate(Order $order)
    {
        foreach($this->getLicencesByOrderId($order->id) as $licenceModel)
        {
            $now = new DateTime();
            $licenceModel->datePayment = $now->getTimestamp();
            Craft::$app->elements->saveElement($licenceModel, false);
        }
    }

    /**
     * @param Order $order
     * @throws \Throwable
     */
    public function assignOrderLicences(Order $order, $testMode = false)
    {
        if (!$order->isCompleted) {
            Craft::getLogger()->log('RlmLicenceService::assignOrderLicences() - order not compelted', 'error', 'rlm');
            return;
        }

        if (null == $product = $this->getProductByPaymentFormId($order->formId)) {
            Craft::getLogger()->log('No product found for order #' . $order->id, 'error', 'rlm');
            return;
        }

        $variants = $order->getFormFields();

        if (isset($variants['licence']) && !is_null($variants['licence'])) {
            Craft::getLogger()->log('RLM licence already assigned for order #' . $order->id, 'error', 'rlm');
            return;
        }

        if (!isset($variants['licencetypeid'])) {
            Craft::getLogger()->log('No RLM licence type for ' . $product->title, 'error', 'rlm');
            return;
        }

        $licenceTypeId = $variants['licencetypeid'];
        unset($variants['licencetypeid']);
        $licenceString = '';

        for ($q = 0 ; $q < $order->quantity; $q++)
        {
            if (null == $licence = $this->getAvailableLicenceByType($licenceTypeId)) {
                Craft::getLogger()->log('No RLM licence available for ' . $product->title, 'error', 'rlm');
                continue;
            }
            // update {rlm_licence_strings} with the order id
            $licence->dateAssigned = Db::prepareDateForDb(new DateTime());
            $licence->orderId = $order->id;
            // get the latest build date
            $licence->productBuild = $this->getCurrentProductBuild($product);
            Craft::$app->getElements()->saveElement($licence, false);
            // save the licence string to the order
            $licenceString .= $licence->licenceString . "\n";
        }

        ## still no licence, save dummy
        if (!$licenceString) {
            $licenceString = 'xxxx-xxxx-xxxx-xxxx';
        }

        $variants['licence'] = $licenceString;

        $order->variants = json_encode($variants);
        Craft::$app->getElements()->saveElement($order, false);

        Craft::getLogger()->log('RLM updated licence for order #' . $order->id, 'info', 'rlm');
        return true;
    }

    /**
     * @param $orderId
     * @return Order|null
     */
    public function getOrderById($orderId)
    {
        $orderService = new OrdersService();
        return $orderService->getOrderById($orderId);
    }

    /**
     * @param $formId
     * @return Entry
     */
    public function getProductByPaymentFormId($formId)
    {
        $products = Entry::find()
            ->sectionId($this->sectionIdProducts)
            ->all();

        ## loop through all the product pages
        foreach ($products as $product) {
            foreach ($product->licenceGroups as $licenceGroup) {
                foreach ($licenceGroup->groupLicences as $licence) {
                    $form = $licence->stripePaymentForm->one();
                    if ($form->id == $formId) {
                        return $product;
                    }
                }
            }
        }
        return null;
    }

    /**
     * @param $productId
     * @return array
     */
    public function getProductPaymentFormIds($productId)
    {
        $paymentFormIds = [];
        $product = Entry::find()
            ->sectionId($this->sectionIdProducts)
            ->id($productId)
            ->one();

        if (null != $product) {
            foreach ($product->licenceGroups as $licenceGroup) {
                foreach ($licenceGroup->groupLicences as $licence) {
                    if (null != $form = $licence->stripePaymentForm->one()) {
                        $paymentFormIds[] = $form->id;
                    }
                }
            }
        }
        return $paymentFormIds;
    }

    /**
     * @param $product
     * @return null
     */
    private function getProductLicenceTypeId($product)
    {
        $fieldName = $this->fieldNameRlmLicenceType;
        return ($product && isset($product->$fieldName) && count($product->$fieldName)) ? $product->$fieldName : null;
    }

    /**
     * @param $orderId
     * @return array
     */
    public function getLicenceStringsByOrder($orderId)
    {
       $return = '';
       return $return;
    }

    /**
     * @param $string
     * @return array
     */
    public function getOrderByLicenceString($string)
    {
        $licence = $this->getLicenceByString($string);
        if ( ! $licence)
        {
            return;
        }
        return $licence->orderId ? $this->getOrderById($licence->orderId) : null;
    }

    /**
     * @param $product
     */
    public function getCurrentProductBuild($product)
    {
        $fieldName = $this->fieldNameDownloads;
        $row = $product->$fieldName->first();
        return $row ? $row->buildDate : '';
    }

    /**
     * @param $string
     * @return array
     */
    public function getProductByLicenceString($string)
    {
        $order = $this->getOrderByLicenceString($string);
        if ( ! $order )
        {
            return;
        }
        return $this->getProductByPaymentFormId($order->formId);
    }

    /**
     * @param $typeId
     * @param User $user
     */
    public function getTrialLicence($typeId, User $user, $submissionId = null)
    {
        $query = RlmLicence::find()->licenceType($typeId)->userId($user->id);

        if ($submissionId) {
            $query->submissionId($submissionId);
        }

        $existing = $query->one();

        if ($existing) {
            return $existing->licenceString;
        }

        $licence = $this->getAvailableLicenceByType($typeId);

        if (!$licence) {
            return 'LICENCE NOT AVAILABLE';
        }

        $licence->dateAssigned = Db::prepareDateForDb(new DateTime());
        $licence->userId = $user->id;
        $licence->submissionId = $submissionId;
        Craft::$app->getElements()->saveElement($licence, false);

        return $licence->licenceString;
    }

    /**
     * @return array
     */
    public function getLicenceTypes()
    {
        return RlmLicenceType::find()->all();
    }

    /**
     * @return array
     */
    public function getLicenceTypeOptions() {
        $options = [];
        $types = $this->getLicenceTypes();
        foreach ($types as $type) {
            $options[] =[
                'value' => $type->id,
                'label' => $type->title
            ];
        }
        return $options;
    }

    /**
     * @return array
     */
    public function getLicenceTypesArray()
    {
        $types = $this->getLicenceTypes();
        $return = [];
        foreach ($types as $type) {
            $return[$type->id] = $type->title;
        }
        return $return;
    }

    /**
     * @param $typeId
     * @return mixed
     */
    public function getLicenceTypeById($typeId)
    {
        return RlmLicenceType::find()->id($typeId)->one();
    }

    /**
     * @param $typeId
     * @return string
     */
    public function getLicenceTypeLabel($typeId)
    {
        if (false != $type = $this->getLicenceTypeById($typeId)) {
            return $type->title;
        }
        return '';
    }

    /**
     * @return array
     */
    public function getLicenceTotals()
    {
        $types = $this->getLicenceTypes();
        $return = [];
        foreach($types as $type) {
            $return[] = [
                'type'              => $type,
                'totalAssigned'     => $this->getTotalAssignedByType($type->id),
                'totalAvailable'    => $this->getTotalAvailableByType($type->id),
            ];
        }
        return $return;
    }

    /**
     * @param $typeId
     * @return mixed
     */
    public function getAvailableLicenceByType($typeId)
    {
        return RlmLicence::find()->licenceType($typeId)->available()->one();
    }

    /**
     * @param $type
     * @return array
     */
    public function getAssignedByType($typeId)
    {
        return RlmLicence::find()->licenceType($typeId)->assigned()->all();
    }

    /**
     * @param $type
     * @return array
     */
    public function getAvailableByType($typeId)
    {
        return RlmLicence::find()->licenceType($typeId)->available()->all();
    }

    /**
     * @param $type
     * @return int
     */
    public function getTotalAssignedByType($typeId)
    {
        return RlmLicence::find()->licenceType($typeId)->assigned()->count();
    }

    /**
     * @param $type
     * @return int
     */
    public function getTotalAvailableByType($typeId)
    {
        return RlmLicence::find()->licenceType($typeId)->available()->count();
    }

    /**
     * @param $licenceId
     * @return \craft\base\ElementInterface|null
     */
    public function getLicenceById($licenceId)
    {
        return Craft::$app->getElements()->getElementById($licenceId, 'RlmLicence');
    }

    /**
     * @param $licenceId
     * @return \craft\base\ElementInterface|null
     */
    public function getLicencesByOrderId($orderId)
    {
        return RlmLicence::find()->orderId($orderId)->all();
    }

    /**
     * @param $licenceString
     * @return mixed
     */
    public function getLicenceByString($licenceString)
    {
        return RlmLicence::find()->licenceString($licenceString)->one();
    }

    /**
     * @param $licenceString
     * @return mixed
     */
    public function getTrialExpiry($typeId, User $user)
    {
        $existing = RlmLicence::find()->licenceType($typeId)->userId($user->id)->one();
        if ($existing)
        {

            return $existing->dateAssigned;
        }
        return $existing->expirationDate;
    }

    /**
     * @param $typeId
     * @param User $user
     */
    public function getMultipleTrialLicence($typeId, $trialQuantity, $user, $submissionId = null)
    {
        // Get array of licence ojects for the user
        $submissionId = $submissionId ? $submissionId : ':empty:';
        $query = RlmLicence::find()->licenceType($typeId)->userId($user->id)->submissionId($submissionId);

        $licenceArray = $query->all();

        //If they have any licences
        $trialLicences = array();
       if (is_array($licenceArray) && count($licenceArray) > 0)
        {

            //for each licence
            foreach($licenceArray as $licence) {

                //check type against passed in type
                if($licence->licenceTypeId == $typeId){

                    //if true push to the return array
                    $trialLicences[] =  $licence->licenceString;

                    //print_r($licence->licenceString);
                    //print_r($trialLicences);

                }
            }
        }else{
            $licence = $this->getAvailableLicenceByType($typeId);
            if ( ! $licence)
            {
                return 'LICENCE NOT AVAILABLE';
            }

            for ($q = 0 ; $q < $trialQuantity; $q++)
            {
                $licence = $this->getAvailableLicenceByType($typeId);

                if (null == $licence = $this->getAvailableLicenceByType($typeId)) {
                    Craft::getLogger()->log('No RLM licence available.', 'error', 'rlm');
                    continue;
                }

                $trialLicences[] = $licence->licenceString;
                $licence->dateAssigned = Db::prepareDateForDb(new DateTime());
                $licence->userId = $user->id;
                $licence->submissionId = $submissionId;
                Craft::$app->getElements()->saveElement($licence, false);
            }
        }

        return $trialLicences;
    }

    /**
     * @param array $licenceData
     * @param $licenceTypeId
     * @param int $imported
     * @return bool|int
     * @throws \Throwable
     */
    public function importLicences($licenceData = [], $licenceTypeId, $imported = 0)
    {
        try {
            foreach ($licenceData as $licenceString)
            {
                $licence = new RlmLicence();
                $licence->licenceTypeId = $licenceTypeId;
                $licence->licenceString = $licenceString;
                if (Craft::$app->elements->saveElement($licence)) {
                    $imported++;
                }
            }
            return $imported;

        } catch (\Throwable $e) {
            throw $e;
        }
        return false;
    }

    /**
     * @param $handle
     * @param $field
     * @return \craft\base\ElementInterface[]|mixed|null
     */
    private function getGlobal($handle, $field)
    {
        $globalSet = GlobalSet::find()->handle($handle)->one();
        return $globalSet->$field;
    }
}
