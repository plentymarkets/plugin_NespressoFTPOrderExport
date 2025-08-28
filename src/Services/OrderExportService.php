<?php

namespace NespressoFTPOrderExport\Services;

use Carbon\Carbon;
use NespressoFTPOrderExport\Clients\ClientForSFTP;
use NespressoFTPOrderExport\Configuration\PluginConfiguration;
use NespressoFTPOrderExport\Helpers\OrderHelper;
use NespressoFTPOrderExport\Models\TableRow;
use NespressoFTPOrderExport\Repositories\ExportDataRepository;
use NespressoFTPOrderExport\Repositories\SettingRepository;
use Plenty\Modules\Account\Address\Models\AddressOption;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\Order\Date\Models\OrderDateType;
use Plenty\Modules\Order\Models\Order;
use Plenty\Modules\Order\Models\OrderItemType;
use Plenty\Modules\Order\Property\Models\OrderPropertyType;
use Plenty\Modules\Order\RelationReference\Models\OrderRelationReference;
use Plenty\Plugin\Log\Loggable;

class OrderExportService
{
    use Loggable;

    /**
     * @var ClientForSFTP
     */
    private $ftpClient;

    /**
     * @var PluginConfiguration
     */
    private $configRepository;

    /**
     * @var String
     */
    private $pluginVariant;

    /**
     * @var int
     */
    private $totalOrdersPerBatch;

    /**
     * @var OrderRepositoryContract
     */
   private $orderRepository;

    /**
     * @var OrderHelper
     */
   private $orderHelper;

    /**
     * @param ClientForSFTP $ftpClient
     */
    public function __construct(
        ClientForSFTP           $ftpClient,
        PluginConfiguration     $configRepository,
        OrderRepositoryContract $orderRepository,
        OrderHelper             $orderHelper
    )
    {
        $this->ftpClient            = $ftpClient;
        $this->configRepository     = $configRepository;
        $this->pluginVariant        = $this->configRepository->getPluginVariant();
        $this->totalOrdersPerBatch  = $this->configRepository->getTotalOrdersPerBatch();
        $this->orderRepository      = $orderRepository;
        $this->orderHelper          = $orderHelper;
    }

    /**
     * @param Order $order
     * @return void
     */
    public function processOrder(Order $order)
    {
        $deliveryAddress = [];
        $isB2B = $this->orderHelper->isB2B($order);

        //dismiss data in name1 if it contains wrong information
        $orderDeliveryName1 = $order->deliveryAddress->name1;
        $orderBillingName1 = $order->billingAddress->name1;
        if ($this->pluginVariant == 'DE') {
            $wrongContents = ['Stock', 'Etage', 'OG', 'Og', 'Zimmer', 'zimmer', 'Wg', 'Floor', 'floor'];
            foreach ($wrongContents as $wrongContent) {
                if (strpos($order->deliveryAddress->name1, $wrongContent) !== false) {
                    $orderDeliveryName1 = '';
                }
                if (strpos($order->billingAddress->name1, $wrongContent) !== false) {
                    $orderBillingName1 = '';
                }
            }
            if (
                (strpos(strtolower($order->deliveryAddress->name1), 'raum') !== false) &&
                (preg_match('/\s\d/', $orderDeliveryName1))
            ){
                $orderDeliveryName1 = '';
            }
            if (
                (strpos(strtolower($order->billingAddress->name1), 'raum') !== false) &&
                (preg_match('/\s\d/', $orderBillingName1))
            ){
                $orderBillingName1 = '';
            }
        }

        if ($orderDeliveryName1 != '') {
            if ($this->pluginVariant == 'DE') {
                $deliveryAddress['company'] = 1;
                $deliveryAddress['name'] = $orderDeliveryName1;
                $deliveryAddress['first_name'] = '';
                $deliveryAddress['contact'] = $order->deliveryAddress->name2 . ' ' . $order->deliveryAddress->name3;
            } else {
                $deliveryAddress['company'] = 1;
                $deliveryAddress['name'] = $order->deliveryAddress->name2 . ' ' . $order->deliveryAddress->name3;
                $deliveryAddress['first_name'] = '';
            }
        } else {
            if ($this->pluginVariant == 'DE') {
                $deliveryAddress['company'] = '0';
                $deliveryAddress['name'] = $order->deliveryAddress->name3;
                $deliveryAddress['first_name'] = $order->deliveryAddress->name2;
                $deliveryAddress['contact'] = '';
            } else {
                $deliveryAddress['company'] = '0';
                $deliveryAddress['name'] = $order->deliveryAddress->name3;
                $deliveryAddress['first_name'] = $order->deliveryAddress->name2;
            }
        }
        if ($this->pluginVariant == 'DE') {
            $deliveryAddress['civility'] = 5;
            $deliveryAddress['extra_name'] = '';
            if (($order->deliveryAddress->isPackstation === true) || $order->deliveryAddress->isPostfiliale === true) {
                $deliveryAddress['first_name'] = $order->deliveryAddress->name2;
                $deliveryAddress['name'] = $order->deliveryAddress->name3;
                $deliveryAddress['company'] = 0;
                $deliveryAddress['contact'] = '';
                $deliveryAddress['address_line1'] = $order->deliveryAddress->address1 . ' ' . $order->deliveryAddress->packstationNo;
                $deliveryAddress['address_line2'] = $orderDeliveryName1;
            } else {
                $deliveryAddress['address_line1'] = $order->deliveryAddress->address1 . ' ' . $order->deliveryAddress->address2;
                $deliveryAddress['address_line2'] = '';
            }
        } else {
            $deliveryAddress['civility'] = 10;
            if ($order->deliveryAddress->companyName != '') {
                $deliveryAddress['extra_name'] = $orderDeliveryName1;
            } else {
                $deliveryAddress['extra_name'] = '';
            }
            $deliveryAddress['address_line1'] = $order->deliveryAddress->address1 . ' ' . $order->deliveryAddress->address2;
            if (strlen($deliveryAddress['address_line1']) > 35){
                $deliveryAddress['address_line2'] = substr(
                    $deliveryAddress['address_line1'],
                    35,
                    strlen($deliveryAddress['address_line1']) - 35);
            } else {
                $deliveryAddress['address_line2'] = '';
            }
        }
        $deliveryAddress['post_code'] = $order->deliveryAddress->postalCode;
        if ($this->pluginVariant == 'AT') {
            $deliveryAddress['post_code'] = preg_replace("/[^0-9]/", "", $deliveryAddress['post_code'] );
        }
        $deliveryAddress['city'] = $order->deliveryAddress->town;
        $deliveryAddress['country'] = $order->deliveryAddress->country->isoCode2;
        if ($this->pluginVariant == 'DE') {
            $deliveryAddress['area1'] = '';
            $deliveryAddress['area2'] = '';
            $deliveryAddress['remark'] = '';
        } else {
            $deliveryAddress['area1'] = $order->deliveryAddress->country->isoCode2;
            $deliveryAddress['remark'] = $order->id;
        }

        $customer = [];

        $invoiceAddress = [];
        $customer['address_different'] = (int)($order->deliveryAddress->id != $order->billingAddress->id);
        if ($customer['address_different']) {
            if ($orderBillingName1 != '') {
                if ($this->pluginVariant == 'DE') {
                    $invoiceAddress['company'] = '1';
                    $invoiceAddress['name'] = $orderBillingName1;
                    $invoiceAddress['first_name'] = '';
                    $invoiceAddress['contact'] = $order->billingAddress->name2 . ' ' . $order->billingAddress->name3;
                } else {
                    $invoiceAddress['company'] = '1';
                    $invoiceAddress['name'] = $orderBillingName1;
                    $invoiceAddress['first_name'] = '';
                }
            } else {
                if ($this->pluginVariant == 'DE') {
                    $invoiceAddress['company'] = '0';
                    $invoiceAddress['name'] = $order->billingAddress->name3;
                    $invoiceAddress['first_name'] = $order->billingAddress->name2;
                    $invoiceAddress['contact'] = '';
                } else {
                    $invoiceAddress['company'] = '0';
                    $invoiceAddress['name'] = $order->billingAddress->name3;
                    $invoiceAddress['first_name'] = $order->billingAddress->name2;
                }
            }
            if ($this->pluginVariant == 'DE') {
                $invoiceAddress['civility'] = 5;
                $invoiceAddress['extra_name'] = '';
                if (($order->billingAddress->isPackstation === true) || $order->billingAddress->isPostfiliale === true) {
                    $invoiceAddress['first_name'] = $order->billingAddress->name2;
                    $invoiceAddress['name'] = $order->billingAddress->name3;
                    $invoiceAddress['company'] = 0;
                    $invoiceAddress['contact'] = '';
                    $invoiceAddress['address_line1'] = $order->billingAddress->address1 . ' ' . $order->billingAddress->packstationNo;
                    $invoiceAddress['address_line2'] = $orderBillingName1;
                } else {
                    $invoiceAddress['address_line1'] = $order->billingAddress->address1 . ' ' . $order->billingAddress->address2;
                    $invoiceAddress['address_line2'] = '';
                }
            } else {
                $invoiceAddress['civility'] = 10;
                if ($order->billingAddress->companyName != '') {
                    $invoiceAddress['extra_name'] = $order->billingAddress->name2 . ' ' . $order->billingAddress->name3;
                } else {
                    $invoiceAddress['extra_name'] = '';
                }
                $invoiceAddress['address_line1'] = $order->billingAddress->address1 . ' ' . $order->billingAddress->address2;
                if (strlen($invoiceAddress['address_line1']) > 35){
                    $invoiceAddress['address_line2'] = substr(
                        $invoiceAddress['address_line1'],
                        35,
                        strlen($invoiceAddress['address_line1']) - 35);
                } else {
                    $invoiceAddress['address_line2'] = '';
                }
            }


            $invoiceAddress['post_code'] = $order->billingAddress->postalCode;
            if ($this->pluginVariant == 'AT') {
                $invoiceAddress['post_code'] = preg_replace("/[^0-9]/", "", $invoiceAddress['post_code'] );
            }
            $invoiceAddress['city'] = $order->billingAddress->town;
            $invoiceAddress['country'] = $order->billingAddress->country->isoCode2;
            if ($this->pluginVariant == 'DE') {
                $invoiceAddress['area1'] = '';
                $invoiceAddress['area2'] = '';
                $invoiceAddress['remark'] = '';
            } else {
                $invoiceAddress['area1'] = $order->billingAddress->country->isoCode2;
            }
        }
        $contactPreference = [];
        $contactPreference['email'] = $order->billingAddress->email;
        if ($this->pluginVariant == 'DE') {
            $contactPreference['mailing_authorization'] = 0;
            $contactPreference['post_mailing_active'] = 0;
            $contactPreference['contact_by_phone_allowed'] = 0;
            $contactPreference['mobile_notification_active'] = 0;
        } else {
            $contactPreference['mailing_authorization'] = "false";
            $contactPreference['post_mailing_active'] = "false";
            $contactPreference['contact_by_phone_allowed'] = "false";
            $contactPreference['mobile_notification_active'] = "false";
            $contactPreference['notification_subscription'] = [
                'descaling' => 'false',
                'reorder'   => 'false'
            ];
            $contactPreference['contact_by_social_media_allowed'] = "false";
        }
        $privacyPolicy = [];
        $privacyPolicy['terms_and_condition_accepted'] = 1;
        $privacyPolicy['allow_use_satisfaction_research'] = 0;
        $privacyPolicy['allow_personalized_management'] = 0;
        $privacyPolicy['allow_use_of_personal_data_for_marketing'] = 0;

        if ($this->pluginVariant == 'AT') {
            $customer['category_1'] = '27';
            $customer['invoicing_condition'] = 'O';
        }

        //check for potential wrong field data in the delivery address
        if ($this->pluginVariant == 'DE') {
            //number or number plus a single letter
            if (preg_match('/\b\d+[a-zA-Z]?\b/', $order->deliveryAddress->address1)){
                $deliveryAddress['address_line1'] = $orderDeliveryName1 . ' ' . $order->deliveryAddress->address1;
                $deliveryAddress['name'] = $order->deliveryAddress->name3;
                $deliveryAddress['company'] = '0';
                $deliveryAddress['contact'] = '';
            }
            if (preg_match('/\b\d+[a-zA-Z]?\b/', $order->billingAddress->address1)){
                $invoiceAddress['address_line1'] = $orderBillingName1 . ' ' . $order->billingAddress->address1;
                $invoiceAddress['name'] = $order->billingAddress->name3;
                $invoiceAddress['company'] = '0';
                $invoiceAddress['contact'] = '';
            }

            //space plus number
            if (preg_match('/\s\d/', $orderDeliveryName1)) {
                $deliveryAddress['address_line1'] = $orderDeliveryName1;
                $deliveryAddress['first_name'] = $order->deliveryAddress->name2;
                $deliveryAddress['name'] = $order->deliveryAddress->name3;
                $deliveryAddress['contact'] = '';
                $deliveryAddress['company'] = '0';
            }
            if (preg_match('/\s\d/', $orderBillingName1)) {
                $invoiceAddress['address_line1'] = $orderBillingName1;
                $invoiceAddress['first_name'] = $order->billingAddress->name2;
                $invoiceAddress['name'] = $order->billingAddress->name3;
                $invoiceAddress['contact'] = '';
                $invoiceAddress['company'] = '0';
            }

            $companyMarkers = ['GmbH', 'UG', 'AG', 'KG', 'OHG', 'GbR', 'SE', 'e.K.', 'eG', 'KGaA', 'GmbH & Co. KG',
                             'UG & Co. KG', 'PartG', 'PartG mbB', 'Ltd.', 'Inc.', 'LLP', 'SARL', 'S.A.', 'S.P.A.',
                             'mbH', 'Aktiengesellschaft'];
            $deliveryName3Array = array_map('strtolower', explode(' ', $order->deliveryAddress->name3));
            $billingName3Array  = array_map('strtolower', explode(' ', $order->billingAddress->name3));
            foreach ($companyMarkers as $companyMarker) {
                if (in_array(strtolower($companyMarker), $deliveryName3Array)) {
                    $deliveryAddress['name'] = $order->deliveryAddress->name2 . ' ' . $order->deliveryAddress->name3;
                    $deliveryAddress['company'] = '1';
                    $deliveryAddress['first_name'] = '';
                }
                if (in_array(strtolower($companyMarker), $billingName3Array)) {
                    $invoiceAddress['name'] = $order->billingAddress->name2 . ' ' . $order->billingAddress->name3;
                    $invoiceAddress['company'] = '1';
                    $invoiceAddress['first_name'] = '';
                }
            }
        }

        $customer['delivery_address'] = $deliveryAddress;
        if ($this->pluginVariant == 'DE') {
            $customer['state_inscription_number'] = '';
            $customer['vat_number'] = '';
            if ($orderBillingName1 != '') {
                $customer['company'] = '1';
            } else {
                $customer['company'] = '0';
            }
        }
        $customer['invoice_address'] = $invoiceAddress;
        $customer['contact_preference'] = $contactPreference;
        $customer['privacy_policy'] = $privacyPolicy;
        if ($this->pluginVariant == 'DE') {
            $customer['input_user'] = '';
        }
        $customer['fiscal_receipt'] = 'true';

        $orderData = [];
        $orderData['client_id'] = $this->getCustomerId($order);
        if ($this->pluginVariant == 'DE') {
            $orderData['external_order_id'] = $order->getPropertyValue(OrderPropertyType::EXTERNAL_ORDER_ID) . '_' . $order->id;
            if ($isB2B){
                $orderData['movement_code'] = "71";
            } else {
                $orderData['movement_code'] = "3";
            }
            $orderData['order_date'] = $order->dates->filter(
                function ($date) {
                    return $date->typeId == OrderDateType::ORDER_ENTRY_AT;
                }
            )->first()->date->isoFormat("DD/MM/YYYY");
        } else {
            $orderData['external_order_id'] = $order->id;
            $orderData['third_reference'] = $order->getPropertyValue(OrderPropertyType::EXTERNAL_ORDER_ID);
            $orderData['movement_code'] = "2010";
        }
        if (($this->pluginVariant == 'DE') && $isB2B) {
            $orderData['order_source'] = 'AMB';
        } else {
            $orderData['order_source'] = 'AMZ';
        }
        if ($this->pluginVariant == 'DE') {
            $orderData['delivery_mode'] = 'VZ';
            if ($isB2B) {
                $orderData['payment_mode'] = 'XB';
            } else {
                $orderData['payment_mode'] = 'XA';
            }
        } else {
            $orderData['delivery_mode'] = 'GP';
            $orderData['payment_mode'] = 'AM';
        }
        if ($this->pluginVariant == 'AT') {
            $orderData['force_stock'] = 'GW3';
            $orderData['order_description'] = $order->getPropertyValue(OrderPropertyType::EXTERNAL_ORDER_ID);
        }

        $orderData['order_details'] = [];
        foreach ($order->orderItems as $orderItem) {
            if ($orderItem->typeId === OrderItemType::TYPE_VARIATION) {
                $orderLine = [];
                $orderLine['product_code'] = $orderItem->variation->number;
                $orderLine['quantity'] = $orderItem->quantity;
                if ($this->pluginVariant == 'DE') {
                    $orderLine['serial_number'] = '';
                }

                $orderData['order_details'][] = $orderLine;
            }
        }

        $record = [];

        if ($this->pluginVariant == 'DE') {
            $record['record_remarks'] = "";
            $record['external_ref'] = $order->getPropertyValue(OrderPropertyType::EXTERNAL_ORDER_ID) . '_' . $order->id;
        } else {
            $record['external_ref'] = $order->getPropertyValue(OrderPropertyType::EXTERNAL_ORDER_ID);
        }
        if ($this->pluginVariant == 'AT') {
            $record['identification_mode'] = "N";
        }
        if ($this->pluginVariant == 'DE') {
            $record['member_number'] = "";
        }
        $record['address_changed'] = 1;
        if (($this->pluginVariant == 'DE') && $isB2B) {
            $record['order_source'] = "AMB";
            $record['channel'] = "33";
        } else {
            $record['order_source'] = "AMZ";
            $record['channel'] = "32";
        }
        $record['customer'] = $customer;
        $record['order'] = $orderData;

        //apply maximum number of characters
        if ($this->pluginVariant == 'AT') {
            $maxNumberList = [
                [
                    'field' => 'name',
                    'limit' => 40
                ],
                [
                    'field' => 'first_name',
                    'limit' => 35
                ],
                [
                    'field' => 'extra_name',
                    'limit' => 70
                ],
                [
                    'field' => 'address_line1',
                    'limit' => 35
                ],
                [
                    'field' => 'address_line2',
                    'limit' => 35
                ],
                [
                    'field' => 'city',
                    'limit' => 35
                ],
                [
                    'field' => 'post_code',
                    'limit' => 8
                ]
            ];
        } else {
            $maxNumberList = [
                [
                    'field' => 'name',
                    'limit' => 18
                ],
                [
                    'field' => 'first_name',
                    'limit' => 17
                ],
                [
                    'field' => 'address_line1',
                    'limit' => 35
                ],
                [
                    'field' => 'post_code',
                    'limit' => 5
                ],
                [
                    'field' => 'contact',
                    'limit' => 35
                ]
            ];
        }
        foreach ($maxNumberList as $maxNumber){
            if (strlen($record['customer']['delivery_address'][$maxNumber['field']]) > $maxNumber['limit']){
                $record['customer']['delivery_address'][$maxNumber['field']] =
                    substr($record['customer']['delivery_address'][$maxNumber['field']], 0, $maxNumber['limit']);
            }
            if (strlen($record['customer']['invoice_address'][$maxNumber['field']]) > $maxNumber['limit']){
                $record['customer']['invoice_address'][$maxNumber['field']] =
                    substr($record['customer']['invoice_address'][$maxNumber['field']], 0, $maxNumber['limit']);
            }
        }

        $this->saveRecord($order->id, $record, $isB2B);
    }

    /**
     * @param Order $order
     * @return null
     */
    public function getCustomerId(Order $order)
    {
        $relation = $order->relations
            ->where('referenceType', OrderRelationReference::REFERENCE_TYPE_CONTACT)
            ->where('relation', OrderRelationReference::RELATION_TYPE_RECEIVER)
            ->first();

        if ($relation !== null) {
            return $relation->referenceId;
        }

        return -1;
    }

    /**
     * @param int $plentyOrderId
     * @param array $record
     * @return bool
     */
    public function saveRecord(int $plentyOrderId, array $record, bool $isB2B){

        $exportData = [
            'plentyOrderId'    => $plentyOrderId,
            'exportedData'     => json_encode($record),
            'savedAt'          => Carbon::now()->toDateTimeString(),
            'sentdAt'          => '',
            'isB2B'            => $isB2B
        ];

        /** @var ExportDataRepository $exportDataRepository */
        $exportDataRepository = pluginApp(ExportDataRepository::class);
        try {
            if (!$exportDataRepository->orderExists($plentyOrderId)) {
                /** @var TableRow $savedObject */
                $exportDataRepository->save($exportData);

                //test logs
                $this->getLogger(__METHOD__)
                    ->addReference('orderId', $plentyOrderId)
                    ->debug(PluginConfiguration::PLUGIN_NAME . '::general.logMessage', [
                        'message'           => 'Saved to export stack',
                    ]);
                if ($exportDataRepository->orderExists($plentyOrderId)){
                    $this->getLogger(__METHOD__)
                        ->addReference('orderId', $plentyOrderId)
                        ->debug(PluginConfiguration::PLUGIN_NAME . '::general.logMessage', [
                            'message'           => 'Record found',
                        ]);
                } else {
                    $this->getLogger(__METHOD__)
                        ->addReference('orderId', $plentyOrderId)
                        ->error(PluginConfiguration::PLUGIN_NAME . '::general.logMessage', [
                            'message'           => 'The record was not found',
                        ]);
                }

                $statusOfProcessedOrder = $this->configRepository->getProcessedOrderStatus();
                if ($statusOfProcessedOrder != ''){
                    $this->orderRepository->updateOrder(['statusId' => $statusOfProcessedOrder], $plentyOrderId);
                }
                return true;
            }
            $this->getLogger(__METHOD__)
                ->addReference('orderId', $plentyOrderId)
                ->report(PluginConfiguration::PLUGIN_NAME . '::error.orderExists', $exportData);
            return false;
        } catch (\Throwable $e) {
            $this->getLogger(__METHOD__)
                ->addReference('orderId', $plentyOrderId)
                ->error(PluginConfiguration::PLUGIN_NAME . '::error.saveExportError',
                [
                    'message'     => $e->getMessage(),
                    'exportData'  => $exportData
                ]);
        }
        return false;
    }

    /**
     * @return string
     */
    public function getBatchNumber($isB2B): string
    {
        $settingsRepository = pluginApp(SettingRepository::class);
        return $settingsRepository->getBatchNumber($isB2B);
    }

    public function escapeValue($value)
    {
        $escaped = str_replace('&', '&amp;', $value);
        $escaped = str_replace('<', '&lt;', $escaped);
        $escaped = str_replace('>', '&gt;', $escaped);
        $escaped = str_replace('"', '&quot;', $escaped);
        $escaped = str_replace("'", '&apos;', $escaped);

        return $escaped;
    }

    /**
     * @param $array
     * @return string
     */
    public function arrayToXml($array): string
    {
        if (($array === null) || (count($array) == 0)){
            return '';
        }

        $str = '';

        foreach ($array as $k => $v) {
            if ($k === 'client_id'){
                continue;
            }
            if (is_array($v)) {
                if (count($v) == 0){
                    $str .= "<$k />\n";
                    continue;
                }
                if (is_int($k)){
                    $str .= "<order_line>\n" . $this->arrayToXml($v) . "</order_line>\n";
                } else {
                    if (is_string($k) && ($k === 'order_lines')){
                        $str .= $this->arrayToXml($v);
                    } else {
                        $str .= "<$k>\n" . $this->arrayToXml($v) . "</$k>\n";
                    }
                }
            }
            else {
                if ((string)$v === ''){
                    $str .= "<$k />\n";
                } else {
                    $str .= "<$k>" . $this->escapeValue($v) . "</$k>\n";
                }
            }
        }
        return $str;
    }

    /**
     * @param TableRow[] $exportList
     * @param string $generationTime
     * @param string $batchNo
     * @return string
     */
    public function generateXMLFromOrderData($exportList, $generationTime, $batchNo, $isB2B): string
    {
        if ($this->pluginVariant == 'DE'){
            if ($isB2B){
                $senderId = 90;
            } else {
                $senderId = 89;
            }
        } else {
            $senderId = 86;
        }
        $resultedXML = '<?xml version="1.0" encoding="UTF-8" standalone="no" ?>
<import_batch version_number="1.0" xmlns="http://nesclub.nespresso.com/webservice/club/xsd/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://nesclub.nespresso.com/webservice/club/xsd/ http://nesclub.nespresso.com/webservice/club/xsd/">
  	<!-- HEADER STARTS HERE--> 
	<batch_date_time>'.$generationTime.'</batch_date_time>
	<batch_number>'.$batchNo.'</batch_number> <!-- Batch Number is continous, starting with 01 -->
	<sender_id>'.$senderId.'</sender_id>
';
        if ($this->pluginVariant == 'AT') {
            $resultedXML .= '<entity>5</entity>';
        }

        $totalQuantities = 0;
        $totalCustomers = 0; //removed from totals in version 1.1.12
        $customerList = [];

        /** @var TableRow $order */
        foreach ($exportList as $order){
            $orderData = json_decode($order->exportedData, true);
            $resultedXML .= $this->arrayToXml(['record' => $orderData]);

            foreach ($orderData['order']['order_details'] as $orderLine){
                $totalQuantities += $orderLine['quantity'];
            }

            $currentCustomer = $orderData['order']['client_id'];
            if ((int)$currentCustomer == -1){
                $totalCustomers++;
            } else {
                if (!in_array($currentCustomer, $customerList)) {
                    $customerList[] = $currentCustomer;
                    $totalCustomers++;
                }
            }
        }
        if ($this->pluginVariant == 'DE') {
            $resultedXML .= '
<total_orders>' . count($exportList) . '</total_orders> 
<total_quantity>' . $totalQuantities . '</total_quantity> 
<total_customers>' . count($exportList) . '</total_customers> 
<total_members>' . count($exportList) . '</total_members>';
        }
        $resultedXML .= "\n</import_batch>";

        return $resultedXML;
    }

    /**
     * @param string $xmlContent
     * @param string $filePrefix
     * @param string $batchNo
     * @param bool $isB2B
     * @return bool
     */
    public function sendToFTP(string $xmlContent, string $filePrefix, string $batchNo, bool $isB2B)
    {
        $fileName = $filePrefix . '-32-'.$batchNo.'.xml';
        try {
            $this->getLogger(__METHOD__)->info(
                PluginConfiguration::PLUGIN_NAME . '::general.logMessage',
                [
                    'xmlContent' => $xmlContent,
                    'fileName'=> $fileName
                ]
            );
            $result = $this->ftpClient->uploadXML($fileName, $xmlContent, $isB2B);
            if (is_array($result) && array_key_exists('error', $result) && $result['error'] === true) {
                $this->getLogger(__METHOD__)
                    ->error(PluginConfiguration::PLUGIN_NAME . '::globals.ftpFileUploadError',
                        [
                            'errorMsg'          => $result['error_msg'],
                            'fileName'          => $fileName,
                        ]
                    );
                return false;
            }
        } catch (\Throwable $exception) {
            $this->getLogger(__METHOD__)->error(
                PluginConfiguration::PLUGIN_NAME . '::error.writeFtpError',
                [
                    'message' => $exception->getMessage(),
                    'fileName'=> $fileName
                ]
            );
            return false;
        }
        return true;
    }

    /**
     * @param TableRow[]$exportList
     * @param string $generationTime
     * @return void
     */
    public function markRowsAsSent($exportList, $generationTime): void
    {
        /** @var ExportDataRepository $exportDataRepository */
        $exportDataRepository = pluginApp(ExportDataRepository::class);

        try {
            /** @var TableRow $order */
            foreach ($exportList as $order){
                $exportData = [
                    'plentyOrderId'    => $order->plentyOrderId,
                    'exportedData'     => $order->exportedData,
                    'savedAt'          => $order->savedAt,
                    'sentAt'           => $generationTime,
                ];
                $exportDataRepository->save($exportData);
            }
        } catch (\Throwable $e) {
            $this->getLogger(__METHOD__)->error(
                PluginConfiguration::PLUGIN_NAME . '::error.updateMarkError',
                [
                    'message' => $e->getMessage(),
                ]
            );
        }
    }

    public function sendDataToClient(): bool
    {
        $rTest = 'A';
        /** @var ExportDataRepository $exportDataRepository */
        $exportDataRepository = pluginApp(ExportDataRepository::class);
        try {
            $rTest .= 'B';
            $exportList = $exportDataRepository->listUnsent($this->totalOrdersPerBatch);
            $rTest .= strlen($exportList);
        } catch (\Throwable $e) {
            $rTest .= 'C';
            $this->getLogger(__METHOD__)->error(PluginConfiguration::PLUGIN_NAME . '::error.readExportError',
                [
                    'message'     => $e->getMessage(),
                ]);
            return $rTest;
        }

        $settingsRepository = pluginApp(SettingRepository::class);

        if (count($exportList) > 0) {
            $rTest .= 'D';
            $thisTime = Carbon::now();
            $generationTime = $thisTime->toDateTimeString();
            $batchNo = $this->getBatchNumber(false);
            if (($this->pluginVariant == 'AT') && ((int)$batchNo == 2000)) {
                $batchNo = "2001";
                $settingsRepository->incrementBatchNumber(false);
            }
            $xmlContent = $this->generateXMLFromOrderData($exportList, $generationTime, $batchNo, false);
            if (!$this->sendToFTP(
                $xmlContent,
                $thisTime->isoFormat("DDMMYY") . '-' . $thisTime->isoFormat("HHmm"),
                $batchNo,
                false
            )) {
                $rTest .= 'E';
                return $rTest;
            }

            $rTest .= 'F';
            $settingsRepository->incrementBatchNumber(false);
            $this->markRowsAsSent($exportList, $generationTime);
        }

        if ($this->pluginVariant == 'DE') {
            $rTest .= 'G';
            //for Nespresso DE, we might have also B2B orders, which we sent separatelly
            try {
                $exportList = $exportDataRepository->listUnsent($this->totalOrdersPerBatch, true);
            } catch (\Throwable $e) {
                $this->getLogger(__METHOD__)->error(
                    PluginConfiguration::PLUGIN_NAME . '::error.readExportError',
                    [
                        'message' => $e->getMessage(),
                    ]
                );
                return $rTest;
            }

            if (count($exportList) > 0) {
                $thisTime = Carbon::now();
                $generationTime = $thisTime->toDateTimeString();
                $batchNo = $this->getBatchNumber(true);
                $xmlContent = $this->generateXMLFromOrderData($exportList, $generationTime, $batchNo, true);
                if (!$this->sendToFTP(
                    $xmlContent,
                    $thisTime->isoFormat("DDMMYY") . '-' . $thisTime->isoFormat("HHmm"),
                    $batchNo,
                    true
                )){
                    return $rTest;
                }

                $settingsRepository->incrementBatchNumber(true);
                $this->markRowsAsSent($exportList, $generationTime);
            }
        }
        return $rTest;
    }

    /**
     * @return void
     */
    public function clearExportTable(): void
    {
        /** @var ExportDataRepository $exportDataRepository */
        $exportDataRepository = pluginApp(ExportDataRepository::class);
        try {
            $exportDataRepository->deleteOldRecords(Carbon::now()->subDays(60)->toDateTimeString());
        } catch (\Throwable $e) {
            $this->getLogger(__METHOD__)->error(PluginConfiguration::PLUGIN_NAME . '::error.clearExportTableError',
                [
                    'message'     => $e->getMessage(),
                ]);
        }
    }

    /**
     * @param Order $order
     * @return string
     */
    public static function getOrderLanguage(Order $order)
    {
        $documentLanguage = $order->properties->where('typeId', OrderPropertyType::DOCUMENT_LANGUAGE)->first()->value;
        if(!empty($documentLanguage))
        {
            return strtolower($documentLanguage);
        }

        if ($order->contactReceiver->lang !== ''){
            return $order->contactReceiver->lang;
        }

        return 'de';
    }

}