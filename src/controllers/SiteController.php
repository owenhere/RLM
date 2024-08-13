<?php
/**
 * rlm plugin for Craft CMS 3.x
 *
 * RLM Licence Tools
 *
 * @link      coffeebean.design
 * @copyright Copyright (c) 2018 Coffee Bean Design
 */

namespace cbd\rlm\controllers;

use Craft;
use DateTime;
use cbd\rlm\Rlm;
use craft\elements\Entry as Entry;
use craft\elements\Asset;
use cbd\rlm\elements\RlmLicence;
use enupal\stripe\services\Orders as OrdersService;
use craft\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * @author    Coffee Bean Design
 * @package   Rlm
 * @since     0.0.1
 */
class SiteController extends Controller
{
    public $allowAnonymous = ['status', 'order'];
    public $enableCsrfValidation = false;

    private $sectionIdProducts = 8;
    private $fieldNameRlmLicenceType = 'rlmLicenceType';
    private $fieldNameDownloads = 'productDownloads';

    public function actionOrder() : Response
    {
        $orderService = new OrdersService();
        $number = Craft::$app->request->getParam('number');
        if ($number && null != $order = $orderService->getOrderByNumber($number, 1)) {
            $assign = Rlm::$plugin->service->assignOrderLicences($order);
            echo $assign ? 'ORDER ASSIGNED' : 'CANNOT ASSIGN';
            die();
        }

        echo 'INVALID ORDER NUMBER';
        die();
    }

    /**
     * @param $rlmLicenceString
     * @param $date
     * @return \yii\web\Response
     */
    public function actionStatus(): Response
    {
        $rlmLicenceString = Craft::$app->request->getParam('rlmLicenceString');

        $productVersion = '0.0';
        $expirationDate = '01-jan-2000';

        $trialPeriod = $this->getGlobal('productGlobals', 'trialPeriod');
        $maintenancePeriod = $this->getGlobal('productGlobals', 'maintenancePeriod');
        $rentalPeriod = $this->getGlobal('productGlobals', 'rentalPeriod');
        $gracePeriod = $this->getGlobal('productGlobals', 'rentalGracePeriod');

        $isTrial = false;

        if (!$rlmLicenceString) {
            Craft::getLogger()->log('RLM API - no licence string', 'error', 'rlm');
            return $this->asErrorJson('Invalid licence string.')->setStatusCode(400);
        }
        $licence = Rlm::$plugin->service->getLicenceByString($rlmLicenceString);
        if (!$licence) {
            Craft::getLogger()->log('RLM API - no licence found for licence string', 'error', 'rlm');
            return $this->asErrorJson('Licence not found.')->setStatusCode(404);
        } else {
            $licenceType = Rlm::$plugin->service->getLicenceTypeById($licence->licenceTypeId);
            // trial type
            if ($licenceType->duration == 'trial'){
                // Live Client extended trial
                if ($licenceType->slug == 'faceware-trial'){
                    ## disabled on 13/12/21
                    ## $trialPeriod = 90;
                }
                $isTrial = true;
                $assignedTimestamp = $licence->dateAssigned ? $licence->dateAssigned->getTimestamp() : time();
                $trialEndTimestamp = $assignedTimestamp + ($trialPeriod * (60 * 60 * 24));
                $expirationDate = date('d-M-y', $trialEndTimestamp);
                $productVersion = date('Y.md', $trialEndTimestamp);
            }
            // rental/permament type
            else {
                $order = Rlm::$plugin->service->getOrderById($licence->orderId);
                if (!$order) {
                    Craft::getLogger()->log('RLM API - no order found for licence string', 'error', 'rlm');
                    return $this->asErrorJson('Licence order not found.')->setStatusCode(402);
                }
                $subscription = $order->getSubscription();
                if ($subscription->endDate) {
                    $endDate = new DateTime($subscription->endDate);

                    if ($licenceType->duration == 'rental') {
                        $rentalTimestamp = $endDate->getTimestamp() + ($rentalPeriod * (60 * 60 * 24));
                        $productVersion = date('Y.md', $rentalTimestamp);

                        $expirationTimestamp = $endDate->getTimestamp() + (($rentalPeriod + $gracePeriod) * (60 * 60 * 24));
                        $expirationDate = strtolower(date('d-M-y', $expirationTimestamp));
                    }
                    else {
                        $maintenanceTimestamp = $endDate->getTimestamp() + ($maintenancePeriod * (60 * 60 * 24));
                        $productVersion = date('Y.md', $maintenanceTimestamp);
                        $expirationDate = "0";
                    }
                }
            }
        }

        return $this->asJson([
            'rlmLicenceString' => $rlmLicenceString,
            'productVersion' => $productVersion,
            'expirationDate' => $expirationDate,
            'gracePeriod' => $gracePeriod,
            'isTrial' => $isTrial,
            'configHash' => getenv('RLM_CONFIG'),
        ]);
    }

    /**
     * @param int|null $productId
     * @param int|null $assetId
     * @return Response
     * @throws NotFoundHttpException
     * @throws \Throwable
     * @throws \craft\errors\ElementNotFoundException
     * @throws \yii\base\Exception
     */
    public function actionDownload(int $productId = null, int $assetId = null) : Response
    {
        $user = Craft::$app->getUser()->getIdentity();
        $product = Craft::$app->entries->getEntryById($productId);
        $asset = Asset::findOne($assetId);

        if (!$productId || !$product || !$assetId || !$asset || !$user) {
            throw new NotFoundHttpException('Invalid download parameters.');
        }

        ## cache download locally
        $filePath = Craft::$app->path->tempPath . '/rlm' . $assetId . '.' . $asset->extension;
        if (!is_file($filePath)) {
            copy($asset->url, $filePath);
        }

        ## save download data to user table
        $row = [
            'col1' => date('Y-m-d'),
            'col2' => $productId,
            'col3' => $product->title,
            'col4' => $assetId,
            'col5' => $asset->title,
        ];
        $userDownloads = array_merge([$row], (array) $user->userDownloads);
        $user->setFieldValue('userDownloads', $userDownloads);
        Craft::$app->elements->saveElement($user, false);

        ## send the file
        return Craft::$app->response->sendFile($filePath, $asset->filename);
    }

    private function getGlobal($handle, $field)
    {
        $globalSet = \craft\elements\GlobalSet::find()
            ->handle($handle)
            ->one();
        return $globalSet->$field;
    }
}
