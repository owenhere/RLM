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

use cbd\rlm\elements\RlmLicenceType;
use cbd\rlm\Rlm;
use Craft;
use craft\web\Controller;
use yii\base\Exception;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\ServerErrorHttpException;

/**
 * @author    Coffee Bean Design
 * @package   Rlm
 * @since     0.0.1
 */
class CpController extends Controller
{
    /**
     * @return \yii\web\Response
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionEditType(int $rlmLicenceTypeId = null, RlmLicenceType $rlmLicenceType = null): Response
    {
        $variables = [
            'rlmLicenceTypeId' => $rlmLicenceTypeId,
            'rlmLicenceType' => $rlmLicenceType
        ];

        $this->_prepEditTypeVariables($variables);
        $rlmLicenceType = $variables['rlmLicenceType'];

        // Set the base CP edit URL
        $variables['baseCpEditUrl'] = $variables['continueEditingUrl'] = "rlm/types/{id}";

        return $this->renderTemplate('rlm/types/_edit', $variables);
    }


    /**
     * @return \yii\web\Response
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionSaveType()
    {
        $this->requirePostRequest();
        $this->requireAdmin();

        $rlmLicenceType = $this->_getRlmLicenceTypeModel();

        // Populate the licence type with post data
        $this->_populateLicenceTypeModel($rlmLicenceType);

        $request = Craft::$app->getRequest();

        if (!Craft::$app->getElements()->saveElement($rlmLicenceType)) {
            if ($request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => false,
                    'errors' => $rlmLicenceType->getErrors(),
                ]);
            }

            Craft::$app->getSession()->setError(Craft::t('app', 'Couldn’t save licence type.'));

            // Send the licence type back to the template
            Craft::$app->getUrlManager()->setRouteParams([
                'rlmLicenceType' => $rlmLicenceType
            ]);

            return null;
        }

        if ($request->getAcceptsJson()) {
            return $this->asJson([
                'success' => true,
                'id' => $rlmLicenceType->id,
                'title' => $rlmLicenceType->title,
                'slug' => $rlmLicenceType->slug,
                'status' => $rlmLicenceType->getStatus(),
                'cpEditUrl' => $rlmLicenceType->getCpEditUrl()
            ]);
        }

        Craft::$app->getSession()->setNotice(Craft::t('app', 'Licence type saved.'));

        return $this->redirectToPostedUrl($rlmLicenceType);
    }

    /**
     * Deletes a licence type.
     *
     * @return Response|null
     * @throws NotFoundHttpException if the requested licence type cannot be found
     */
    public function actionDeleteType()
    {
        $this->requirePostRequest();

        $rlmLiceneTypeId = Craft::$app->getRequest()->getRequiredBodyParam('rlmLiceneTypeId');
        $rlmLicenceType = Rlm::$plugin->service->getLicenceTypeById($rlmLiceneTypeId);

        if (!$rlmLicenceType) {
            throw new NotFoundHttpException('Licence type not found');
        }

        // @todo Make sure that no licences exist for this type

        // Delete it
        if (!Craft::$app->getElements()->deleteElement($rlmLicenceType)) {
            if (Craft::$app->getRequest()->getAcceptsJson()) {
                return $this->asJson(['success' => false]);
            }

            Craft::$app->getSession()->setError(Craft::t('app', 'Couldn’t delete licence type.'));

            // Send the licence type back to the template
            Craft::$app->getUrlManager()->setRouteParams([
                'rlmLicenceType' => $rlmLicenceType
            ]);

            return null;
        }

        if (Craft::$app->getRequest()->getAcceptsJson()) {
            return $this->asJson(['success' => true]);
        }

        Craft::$app->getSession()->setNotice(Craft::t('app', 'Licence type deleted.'));

        return $this->redirectToPostedUrl($rlmLicenceType);
    }

    /**
     * Preps licence type category variables.
     *
     * @param array &$variables
     * @throws ForbiddenHttpException
     */
    private function _prepEditTypeVariables(array &$variables)
    {
        // Get the licence type
        // ---------------------------------------------------------------------

        if (empty($variables['rlmLicenceType'])) {
            if (!empty($variables['rlmLicenceTypeId'])) {
                $variables['rlmLicenceType'] = Rlm::$plugin->service->getLicenceTypeById($variables['rlmLicenceTypeId']);

                if (!$variables['rlmLicenceType']) {
                    throw new NotFoundHttpException('Licence type not found');
                }
            } else {
                $variables['rlmLicenceType'] = new RlmLicenceType();
                $variables['rlmLicenceType']->enabled = true;
            }
        }
    }


    /**
     * @return RlmLicenceType
     * @throws NotFoundHttpException
     */
    private function _getRlmLicenceTypeModel()
    {
        $rlmLicenceTypeId = Craft::$app->getRequest()->getBodyParam('rlmLicenceTypeId');
        if ($rlmLicenceTypeId) {
            $rlmLicenceType = Rlm::$plugin->service->getLicenceTypeById($rlmLicenceTypeId);
            if (!$rlmLicenceType) {
                throw new NotFoundHttpException('Licence type not found');
            }
        } else {
            $rlmLicenceType = new RlmLicenceType();
        }
        return $rlmLicenceType;
    }

    /**
     *
     */
    private function _populateLicenceTypeModel (RlmLicenceType $rlmLicenceType)
    {
        $rlmLicenceType->slug = Craft::$app->getRequest()->getBodyParam('slug', $rlmLicenceType->slug);
        $rlmLicenceType->enabled = (bool)Craft::$app->getRequest()->getBodyParam('enabled', $rlmLicenceType->enabled);
        $rlmLicenceType->title = Craft::$app->getRequest()->getBodyParam('title', $rlmLicenceType->title);
        $rlmLicenceType->duration = Craft::$app->getRequest()->getBodyParam('duration', $rlmLicenceType->duration);
        $rlmLicenceType->usage = Craft::$app->getRequest()->getBodyParam('usage', $rlmLicenceType->usage);
    }

    /**
     * @return null|\yii\web\Response
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionImport()
    {
        $this->requirePostRequest();
        $licenceTypeId = Craft::$app->request->post('licenceTypeId');
        $licenceCsv = Craft::$app->request->post('licenceCsv');
        $licenceData = str_getcsv($licenceCsv, "\n");
        if (false !== $imported = Rlm::$plugin->service->importLicences($licenceData, $licenceTypeId)) {
            Craft::$app->getSession()->setNotice(Craft::t('rlm', $imported . ' licences imported.'));
            return $this->redirectToPostedUrl();
        }
        Craft::$app->getSession()->setError(Craft::t('rlm','Error importing licences!'));
        return null;
    }
}
