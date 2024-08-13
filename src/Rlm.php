<?php
/**
 * rlm plugin for Craft CMS 3.x
 *
 * RLM Licence Tools
 *
 * @link      coffeebean.design
 * @copyright Copyright (c) 2018 Coffee Bean Design
 */

namespace cbd\rlm;

use cbd\rlm\elements\RlmLicence;
use cbd\rlm\elements\RlmLicenceType;
use cbd\rlm\fields\RlmLicence as RlmLicenceField;
use cbd\rlm\fields\RlmLicenceType as RlmLicenceTypeField;
use cbd\rlm\fields\StripePlanDropdown as StripePlanDropdownField;
use cbd\rlm\models\Settings;
use cbd\rlm\services\RlmLicenceService;
use cbd\rlm\variables\RlmVariable;

use Craft;
use craft\base\Plugin;
use craft\services\Plugins;
use craft\events\PluginEvent;
use craft\web\UrlManager;
use craft\services\Elements;
use craft\services\Fields;
use craft\services\Utilities;
use craft\web\twig\variables\CraftVariable;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use enupal\stripe\services\Orders;
use enupal\stripe\Stripe;
use enupal\stripe\events\OrderCompleteEvent;
use enupal\stripe\events\WebhookEvent;

use enupal\stripe\Stripe as StripePlugin;
use Solspace\Freeform\Services\SubmissionsService;
use Solspace\Freeform\Events\Submissions\SubmitEvent;

use yii\base\Event;

/**
 * Class Rlm
 *
 * @author    Coffee Bean Design
 * @package   Rlm
 * @since     0.0.1
 *
 * @property  RlmServiceService $rlmService
 */
class Rlm extends Plugin
{
    /**
     * @var Rlm
     */
    public static $plugin;

    /**
     * @var string
     */
    public $schemaVersion = '0.0.2';

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        self::$plugin = $this;

        // register the service
        $this->setComponents([
            'service' => RlmLicenceService::class,
        ]);

        // register the field types
        Event::on(
            Fields::class,
            Fields::EVENT_REGISTER_FIELD_TYPES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = RlmLicenceTypeField::class;
                $event->types[] = RlmLicenceField::class;
                $event->types[] = StripePlanDropdownField::class;
            }
        );

        // register the variable
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function (Event $event) {
                $variable = $event->sender;
                $variable->set('rlm', RlmVariable::class);
            }
        );

        // register the element type
        Event::on(
            Elements::class,
            Elements::EVENT_REGISTER_ELEMENT_TYPES,
            function(RegisterComponentTypesEvent $event) {
                $event->types[] = RlmLicence::class;
                $event->types[] = RlmLicenceType::class;
        });

        // register CP routes
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['rlm/types/new'] = 'rlm/cp/edit-type';
                $event->rules['rlm/types/<rlmLicenceTypeId:\d+>'] = 'rlm/cp/edit-type';
                $event->rules['rlm/licences/import'] = ['template' => 'rlm/licences/_import'];
        });

        // register site routes
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['rlm/status'] = 'rlm/site/status';
                $event->rules['rlm/order'] = 'rlm/site/order';
                $event->rules['rlm/download/<productId:\d+>/<assetId:\d+>'] = 'rlm/site/download';
        });

        // register stripe checkout session complete event
        Event::on(
            Orders::class,
            Orders::EVENT_AFTER_PROCESS_WEBHOOK,
            function(WebhookEvent $event) {
                $data  = $event->stripeData;
                $order = $event->order;
                if ($order && $data['type'] == 'invoice.payment_succeeded') {
                    ## @todo datePayment no longer used?
                    ## Rlm::$plugin->service->updateLicencePaymentDate($order);
                }

                if ($order && $data['type'] == 'checkout.session.completed'){
                    $testMode = !$data['livemode'];
                    Rlm::$plugin->service->assignOrderLicences($order, $testMode);
                }
        });

        /*
        Event::on(
            SubmissionsService::class,
            SubmissionsService::EVENT_BEFORE_SUBMIT,
            function (SubmitEvent $event) {
                $form = $event->getForm();
                $submission = $event->getSubmission();
                if ($form->getHandle() == 'subscriptionCancellation') {
                    $subscriptionId = $submission->cancelSubscriptionId;
                    if (!StripePlugin::$app->subscriptions->cancelStripeSubscription($subscriptionId, true)) {
                        $form->addError('Could not cancel subscription, contact support.');
                    }
                }
        });
        */
    }

    /**
     * @inheritdoc
     */
    public function getCpNavItem(): array
    {
        $ret = parent::getCpNavItem();
        $ret['label'] = Craft::t('rlm', 'RLM');
        return $ret;
    }

    /**
     * @inheritdoc
     */
    protected function createSettingsModel()
    {
        return new Settings();
    }

    /**
     * @return mixed|\yii\web\Response
     */
    public function getSettingsResponse()
    {
        return \Craft::$app->controller->renderTemplate('rlm/index');
    }
}
