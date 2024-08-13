<?php
namespace cbd\rlm\elements;

use Craft;
use craft\elements\db\ElementQuery;
use DateTime;
use craft\base\Element;
use craft\elements\actions\Delete;
use craft\elements\actions\Edit;
use craft\elements\db\ElementQueryInterface;
use cbd\rlm\Rlm;
use cbd\rlm\elements\db\RlmLicenceQuery;
use cbd\rlm\records\RlmLicence as RlmLicenceRecord;
use enupal\stripe\services\Orders as OrdersService;
use Solspace\Freeform\Freeform;


class RlmLicence extends Element
{
    /**
     * @var string
     */
    const STATUS_ASSIGNED = 'assigned';
    const STATUS_AVAILABLE = 'available';

    /**
     * @var
     */
    public $id;
    public $licenceString;
    public $licenceTypeId;
    public $dateAssigned;
    public $subscriptionEndDate;
    public $orderId;
    public $userId;
    public $submissionId;
    public $productBuild;
    public $orderCustomer;

    /**
     * @inheritdoc
     */
    public static function refHandle()
    {
        return 'rlm_licence';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        $rules = parent::rules();
        $rules[] = [
            'licenceString',
            'unique',
            'targetClass' => RlmLicenceRecord::class,
            'filter' => function($query) {
                if ($this->id !== null) {
                    $query->andWhere('`id` != :id', [ 'id' => $this->id ]);
                }
            }
        ];
        return $rules;
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
                'label' => 'All Licences',
                'criteria' => []
            ],
        ];
        foreach(Rlm::$plugin->getInstance()->service->getLicenceTypesArray() as $id => $title) {
            $sources[] =
                [
                    'key' => 'rlm_licence_type:' . $id,
                    'label' => $title,
                    'criteria' => [
                        'licenceTypeId' => $id
                    ]
                ];
        }
        return $sources;
    }

    /**
     * @inheritdoc
     */
    protected static function defineActions(string $source = null): array
    {
        $actions[] = Craft::$app->getElements()->createAction([
            'type' => Delete::class,
            'confirmationMessage' => Craft::t('app', 'Are you sure you want to delete the selected licence?'),
            'successMessage' => Craft::t('app', 'Licences deleted.'),
        ]);

        return $actions;
    }

    /**
     * @return bool|void
     */
    public function beforeDelete(): bool
    {
        return ( ! $this->dateAssigned);
    }

    /**
     * @param bool $isNew
     * @throws \yii\db\Exception
     */
    public function afterSave(bool $isNew)
    {
        if ($isNew) {
            Craft::$app->db->createCommand()
                ->insert('{{%rlm_licence_strings}}', [
                    'id' => $this->id,
                    'licenceString' => $this->licenceString,
                    'licenceTypeId' => $this->licenceTypeId
                ])
                ->execute();
        } else {
            Craft::$app->db->createCommand()
                ->update('{{%rlm_licence_strings}}', [
                    'licenceString' => $this->licenceString,
                    'licenceTypeId' => $this->licenceTypeId,
                    'orderId' => $this->orderId,
                    'userId' => $this->userId,
                    'submissionId' => $this->submissionId,
                    'productBuild' => $this->productBuild,
                    'dateAssigned' => $this->dateAssigned
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
        return new RlmLicenceQuery(static::class);
    }

    /**
     * @return bool
     */
    public static function hasStatuses(): bool
    {
        return true;
    }

    /**
     * @return array
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_ASSIGNED => ['label' => Craft::t('rlm', 'Assigned'), 'color' => 'red'],
            self::STATUS_AVAILABLE => ['label' => Craft::t('rlm', 'Available'), 'color' => 'green']
        ];
    }

    /**
     * @return null|string
     */
    public function getStatus()
    {
        if ($this->dateAssigned) {
            return self::STATUS_ASSIGNED;
        }
        return self::STATUS_AVAILABLE;
    }

    /**
     * @return bool
     */
    public static function hasContent(): bool
    {
        return false;
    }

    /**
     * @return bool
     */
    public static function hasTitles(): bool
    {
        return false;
    }

    /**
     * @return array
     */
    protected static function defineSortOptions(): array
    {
        return [
            'id' => Craft::t('rlm', 'ID'),
            'licenceTypeId' => Craft::t('rlm', 'Licence Type'),
            'licenceString' => Craft::t('rlm', 'Licence String'),
            'dateAssigned' => Craft::t('rlm', 'Date Assigned')
        ];
    }

    /**
     * @return array
     */
    protected static function defineTableAttributes(): array
    {
        return [
            'id' => Craft::t('rlm', 'ID'),
            'licenceTypeId' => Craft::t('rlm', 'Licence Type'),
            'licenceString' => Craft::t('rlm', 'Licence String'),
            'dateAssigned' => Craft::t('rlm', 'Date Assigned'),
            'subscriptionEndDate' => Craft::t('rlm', 'Subscription End Date'),
            'orderId' => Craft::t('rlm', 'Order ID'),
            'userId' => Craft::t('rlm', 'Trial User'),
            'downloaded' => Craft::t('rlm', 'Downloaded?'),
            'downloadDate' => Craft::t('rlm', 'Download Date'),
            'submissionId' => Craft::t('rlm', 'Form Submission'),
            'productBuild' => Craft::t('rlm', 'Product Build'),
            'status' => Craft::t('rlm', 'Status'),
        ];
    }

    /**
     * @inheritdoc
     */
    public function datetimeAttributes(): array
    {
        $attributes = parent::datetimeAttributes();
        $attributes[] = 'dateAssigned';
        $attributes[] = 'subscriptionEndDate';
        return $attributes;
    }

    /**
     * @inheritdoc
     */
    protected static function defineDefaultTableAttributes(string $source): array
    {
        return ['id', 'licenceTypeId', 'licenceString', 'dateAssigned', 'subscriptionEndDate', 'orderId', 'userId', 'downloaded', 'downloadDate', 'submissionId', 'productBuild', 'status'];
    }

    /**
     * @param string $attribute
     * @return string
     */
    protected function tableAttributeHtml(string $attribute): string
    {
        $licenceType = Rlm::getInstance()->service->getLicenceTypeById($this->licenceTypeId);
        switch ($attribute) {
            case 'licenceTypeId':
            {
                return $licenceType;
            }
            case 'licenceString':
            {
                return (string) substr($this->licenceString, 0, 20);
            }
            case 'userId':
            {
                if ( ! $this->userId) {
                    return '~';
                }
                $user = Craft::$app->users->getUserById($this->userId);
                return $user ? $user->email : '<span style="color:#880000">[not found]</span>';
            }
            case 'submissionId':
            {
                if ( ! $this->submissionId) {
                    return '~';
                }
                $freeform = Freeform::getInstance();
                $submission = $freeform->submissions->getSubmission($this->submissionId);
                return $submission ? $submission->id : '<span style="color:#880000">[not found]</span>';
            }
            case 'downloaded':
            {
                $downloaded = (bool) $this->getDownloadDate();
                return $downloaded ? '<span style="color:#008800">Yes</span>' : '<span style="color:#880000">No</span>';
            }
            case 'downloadDate':
            {
                return $this->getDownloadDate();
            }
            case 'orderId':
            case 'subscriptionEndDate':
            {
                if ( ! $this->orderId) {
                    return '~';
                }
                $orderService = new OrdersService();
                if (null == $order = $orderService->getOrderById($this->orderId))
                {
                    return '<span style="color:#880000">[not found]</span>';
                }
                if ($attribute == 'subscriptionEndDate')
                {
                    $subscription = $order->getSubscription();
                    if ( ! $subscription->endDate) {
                        return '~';
                    }
                    $date = DateTime::createFromFormat('m/d/Y', $subscription->endDate);
                    // option to change format - getSubscription() returning m/d/Y rather than date object
                    return $date->format('m/d/Y');
                }
                if ($attribute == 'orderId')
                {
                    return '<a href="' . $order->getCpEditUrl() .'">' . $order->id . '</a>';
                }
                return $order->getUserHtml();
            }
            case 'id':
            {
                return $this->id;
            }
            case 'status':
            {
                return '<span style="color:' . ($this->status == 'available' ? '#008000' : '#880000') . '">' . $this->status . '</span>';
            }
        }

        return parent::tableAttributeHtml($attribute);
    }

    /**
     * @return array
     */
    protected static function defineSearchableAttributes(): array
    {
        return ['licenceTypeId', 'licenceString'];
    }

    private $_downloadDate = false;

    /**
     *
     */
    private function getDownloadDate()
    {
        if ($this->_downloadDate !== false) {
            return $this->_downloadDate;
        }

        $this->_downloadDate = '';

        ## userId means this is a trial
        if ($this->userId) {
            if (null == $product = $this->getProductByLicenceTypeId($this->licenceTypeId)) {
                return $this->_downloadDate;
            }
            if (null == $user = Craft::$app->users->getUserById($this->userId)) {
                return $this->_downloadDate;
            }
        }
        ## orderId means purchased licence
        else {
            if (!$this->orderId) {
                return $this->_downloadDate;
            }
            $orderService = new OrdersService();
            if (null == $order = $orderService->getOrderById($this->orderId)) {
                return $this->_downloadDate;
            }
            if (null == $product = $this->getProductByPaymentFormId($order->formId)) {
                return $this->_downloadDate;
            }
            if (null == $user = Craft::$app->users->getUserById($order->userId)) {
                return $this->_downloadDate;
            }
        }

        if ( ! is_iterable($user->userDownloads)) {
            return $this->_downloadDate;
        }

        foreach ($user->userDownloads as $row) {
            if ($row['productId'] == $product->id) {
                $this->_downloadDate = $row['date']->format('m/d/Y');
                break;
            }
        }

        return $this->_downloadDate;
    }

    private $_licenceTypeProducts= [];

    private function getProductByLicenceTypeId($licenceTypeId)
    {
        if (!isset($this->_licenceTypeProducts[$licenceTypeId])) {
            $this->_licenceTypeProducts[$licenceTypeId] = Rlm::$plugin->getInstance()->service->getProductByLicenceTypeId($this->licenceTypeId);
        }
        return $this->_licenceTypeProducts[$licenceTypeId];
    }

    private $_paymentFormProducts= [];

    private function getProductByPaymentFormId($formId)
    {
        if (!isset($this->_paymentFormProducts[$formId])) {
            $this->_paymentFormProducts[$formId] = Rlm::$plugin->getInstance()->service->getProductByPaymentFormId($formId);
        }
        return $this->_paymentFormProducts[$formId];
    }
}
