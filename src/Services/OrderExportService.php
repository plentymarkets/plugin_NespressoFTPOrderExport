<?php

namespace NespressoFTPOrderExport\Services;

use Carbon\Carbon;
use NespressoFTPOrderExport\Clients\ClientForSFTP;
use NespressoFTPOrderExport\Configuration\PluginConfiguration;
use NespressoFTPOrderExport\Helpers\ExportHelper;
use NespressoFTPOrderExport\Helpers\OrderHelper;
use NespressoFTPOrderExport\Models\TableRow;
use NespressoFTPOrderExport\Repositories\ExportDataRepository;
use NespressoFTPOrderExport\Repositories\SettingRepository;
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
     * @var ExportHelper
     */
   private $exportHelper;


    /**
     * @param ClientForSFTP $ftpClient
     * @param PluginConfiguration $configRepository
     * @param OrderRepositoryContract $orderRepository
     * @param OrderHelper $orderHelper
     * @param ExportHelper $exportHelper
     */
    public function __construct(
        ClientForSFTP           $ftpClient,
        PluginConfiguration     $configRepository,
        OrderRepositoryContract $orderRepository,
        OrderHelper             $orderHelper,
        ExportHelper            $exportHelper
    )
    {
        $this->ftpClient            = $ftpClient;
        $this->configRepository     = $configRepository;
        $this->pluginVariant        = $this->configRepository->getPluginVariant();
        $this->totalOrdersPerBatch  = $this->configRepository->getTotalOrdersPerBatch();
        $this->orderRepository      = $orderRepository;
        $this->orderHelper          = $orderHelper;
        $this->exportHelper         = $exportHelper;
    }

    /**
     * @param Order $order
     * @return void
     */
    public function processOrder(Order $order)
    {
        $this->exportHelper->addHistoryData('Start processing order ' . $order->id, $order->id);

        $isFBM = $this->orderHelper->isFBM($order, $this->pluginVariant);
        if ($isFBM){
            $isB2B = false;
        } else {
            $isB2B = $this->orderHelper->isB2B($order, $this->pluginVariant);
        }
        $xml_destination = 0;
        if ($this->pluginVariant == 'DE') {
            if ($isFBM) {
                $xml_destination = 2;
            } else {
                if ($isB2B){
                    $xml_destination = 1;
                }
            }
        }

        $namesFromOrder = $this->exportHelper->getNamesFromOrder($order, $this->pluginVariant);
        $orderDeliveryName1 = $namesFromOrder['orderDeliveryName1'];
        $orderBillingName1  = $namesFromOrder['orderBillingName1'];

        $deliveryAddress = [];
        $deliveryAddress['company'] = ($orderDeliveryName1 != '') ? 1 : 0;
        $deliveryAddress['name'] = $this->exportHelper->getDeliveryNameValue($order, $this->pluginVariant, $orderDeliveryName1);
        $deliveryAddress['first_name'] = ($orderDeliveryName1 != '') ? '' : $order->deliveryAddress->name2;

        if ($this->pluginVariant == 'DE') {
            $deliveryAddress['contact'] = ($orderDeliveryName1 != '') ? $order->deliveryAddress->name2 . ' ' . $order->deliveryAddress->name3 : '';
        } else {
            $deliveryAddress['contact'] = ($order->deliveryAddress->companyName != '') ? $orderDeliveryName1 : '';
        }

        $deliveryAddress['civility'] = ($this->pluginVariant == 'DE') ? 5 : 10;

        if ($this->pluginVariant == 'DE') {
            $deliveryAddress['extra_name'] = '';
        }

        $deliveryAddress['address_line1'] = $this->exportHelper->getDeliveryAddressLine1Value($order, $this->pluginVariant);
        $deliveryAddress['address_line2'] = $this->exportHelper->getDeliveryAddressLine2Value($order, $this->pluginVariant, $orderDeliveryName1, $deliveryAddress['address_line1']);

        //fix for DE Packstation case
        if ($this->pluginVariant == 'DE') {
            if (($order->deliveryAddress->isPackstation === true) || $order->deliveryAddress->isPostfiliale === true) {
                $this->exportHelper->addHistoryData('Delivery Packstation. Change first_name, name, company, contact', $order->id);
                $deliveryAddress['first_name'] = $order->deliveryAddress->name2;
                $deliveryAddress['name'] = $order->deliveryAddress->name3;
                $deliveryAddress['company'] = 0;
                $deliveryAddress['contact'] = '';
            }
        }

        $deliveryAddress['post_code'] = $order->deliveryAddress->postalCode;
        if ($this->pluginVariant == 'AT') {
            preg_replace("/[^0-9]/", "", $deliveryAddress['post_code']);
        }

        $deliveryAddress['city'] = $order->deliveryAddress->town;
        $deliveryAddress['country'] = $order->deliveryAddress->country->isoCode2;
        $deliveryAddress['area1'] = ($this->pluginVariant == 'DE') ? '' : $order->deliveryAddress->country->isoCode2;
        if ($this->pluginVariant == 'DE') {
            $deliveryAddress['area2'] = '';
        }
        $deliveryAddress['remark'] = ($this->pluginVariant == 'DE') ? '' : $order->id;


        $customer = [];

        $invoiceAddress = [];
        $customer['address_different'] = (int)($order->deliveryAddress->id != $order->billingAddress->id);
        if ($customer['address_different']) {
            $invoiceAddress['company'] = ($orderBillingName1 != '') ? '1' : '0';
            $invoiceAddress['name'] = $this->exportHelper->getInvoiceNameValue($order, $this->pluginVariant, $orderBillingName1);
            $invoiceAddress['first_name'] = ($orderBillingName1 != '') ? '' : $order->billingAddress->name2;
            if ($this->pluginVariant == 'DE') {
                $invoiceAddress['contact'] = ($orderBillingName1 != '') ? $order->billingAddress->name2 . ' ' . $order->billingAddress->name3 : '';
            } else {
                $invoiceAddress['contact'] = ($order->billingAddress->companyName != '') ? $order->billingAddress->name2 . ' ' . $order->billingAddress->name3 : '';
            }
            $invoiceAddress['civility'] = ($this->pluginVariant == 'DE') ? 5 : 10;
            if ($this->pluginVariant == 'DE') {
                $invoiceAddress['extra_name'] = '';
            }
            $invoiceAddress['address_line1'] = $this->exportHelper->getInvoiceAddressLine1Value($order, $this->pluginVariant);
            $invoiceAddress['address_line2'] = $this->exportHelper->getInvoiceAddressLine2Value($order, $this->pluginVariant, $orderBillingName1, $invoiceAddress['address_line1']);

            //fix for DE Packstation case
            if ($this->pluginVariant == 'DE') {
                if (($order->billingAddress->isPackstation === true) || $order->billingAddress->isPostfiliale === true) {
                    $this->exportHelper->addHistoryData('Billing Packstation. Change first_name, name, company, contact', $order->id);
                    $invoiceAddress['first_name'] = $order->billingAddress->name2;
                    $invoiceAddress['name'] = $order->billingAddress->name3;
                    $invoiceAddress['company'] = 0;
                    $invoiceAddress['contact'] = '';
                }
            }

            $invoiceAddress['post_code'] = $order->billingAddress->postalCode;
            if ($this->pluginVariant == 'AT') {
                preg_replace("/[^0-9]/", "", $invoiceAddress['post_code']);
            }

            $invoiceAddress['city'] = $order->billingAddress->town;
            $invoiceAddress['country'] = $order->billingAddress->country->isoCode2;

            $invoiceAddress['area1'] = ($this->pluginVariant == 'DE') ? '' : $order->billingAddress->country->isoCode2;
            if ($this->pluginVariant == 'DE') {
                $invoiceAddress['area2'] = '';
                $invoiceAddress['remark'] = '';
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
                $this->exportHelper->addHistoryData('Rule Delivery Address Number. Change address_line1, name, company, contact', $order->id);
                $deliveryAddress['address_line1'] = $orderDeliveryName1 . ' ' . $order->deliveryAddress->address1;
                $deliveryAddress['name'] = $order->deliveryAddress->name3;
                $deliveryAddress['company'] = '0';
                $deliveryAddress['contact'] = '';
            }
            if (preg_match('/\b\d+[a-zA-Z]?\b/', $order->billingAddress->address1)){
                $this->exportHelper->addHistoryData('Rule Invoice Address Number. Change address_line1, name, company, contact', $order->id);
                $invoiceAddress['address_line1'] = $orderBillingName1 . ' ' . $order->billingAddress->address1;
                $invoiceAddress['name'] = $order->billingAddress->name3;
                $invoiceAddress['company'] = '0';
                $invoiceAddress['contact'] = '';
            }

            //space plus number
            if (preg_match('/\s\d/', $orderDeliveryName1)) {
                $this->exportHelper->addHistoryData('Rule Delivery Name Number. Change address_line1, first_name, name, company, contact', $order->id);
                $deliveryAddress['address_line1'] = $orderDeliveryName1;
                $deliveryAddress['first_name'] = $order->deliveryAddress->name2;
                $deliveryAddress['name'] = $order->deliveryAddress->name3;
                $deliveryAddress['contact'] = '';
                $deliveryAddress['company'] = '0';
            }
            if (preg_match('/\s\d/', $orderBillingName1)) {
                $this->exportHelper->addHistoryData('Rule Invoice Name Number. Change address_line1, first_name, name, company, contact', $order->id);
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
                    $this->exportHelper->addHistoryData('Rule Company Markers ('.$companyMarker.') DeliveryName3Array. Change first_name, name, company', $order->id);
                    $deliveryAddress['name'] = $order->deliveryAddress->name2 . ' ' . $order->deliveryAddress->name3;
                    $deliveryAddress['company'] = '1';
                    $deliveryAddress['first_name'] = '';
                }
                if (in_array(strtolower($companyMarker), $billingName3Array)) {
                    $this->exportHelper->addHistoryData('Rule Company Markers ('.$companyMarker.') InvoiceName3Array. Change first_name, name, company', $order->id);
                    $invoiceAddress['name'] = $order->billingAddress->name2 . ' ' . $order->billingAddress->name3;
                    $invoiceAddress['company'] = '1';
                    $invoiceAddress['first_name'] = '';
                }
            }
        }

        $customer['delivery_address'] = $deliveryAddress;
        if ($this->pluginVariant == 'DE') {
            $customer['state_inscription_number'] = '';
            $customer['vat_number'] = $order->billingAddress->taxIdNumber;
            $customer['company'] = ($orderBillingName1 != '') ? '1' : '0';
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
        $orderData['external_order_id'] = $order->id;

        $orderData['third_reference'] = $order->getPropertyValue(OrderPropertyType::EXTERNAL_ORDER_ID);

        $orderData['movement_code'] = $this->exportHelper->getMovementCodeValue($this->pluginVariant, $isB2B, $isFBM);

        if ($this->pluginVariant == 'DE') {
            $orderData['order_date'] = $order->dates->filter(
                function ($date) {
                    return $date->typeId == OrderDateType::ORDER_ENTRY_AT;
                }
            )->first()->date->isoFormat("DD/MM/YYYY");
        }


        $orderData['order_source'] = $this->exportHelper->getSourceCodeValue($this->pluginVariant, $isB2B);
        $orderData['delivery_mode'] = $this->exportHelper->getDeliveryModeValue($this->pluginVariant, $isFBM);
        $orderData['payment_mode'] = $this->exportHelper->getPaymentModeValue($this->pluginVariant, $isB2B);

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
        }

        $record['external_ref'] = ($this->pluginVariant == 'DE') ?
            $order->id :
            $order->getPropertyValue(OrderPropertyType::EXTERNAL_ORDER_ID);


        if ($this->pluginVariant == 'AT') {
            $record['identification_mode'] = "N";
        }

        if ($this->pluginVariant == 'DE') {
            $record['member_number'] = "";
        }

        $record['address_changed'] = 1;
        $record['order_source'] = $this->exportHelper->getOrderSourceValue($this->pluginVariant, $isB2B);
        $record['channel'] = $this->exportHelper->getChannelValue($this->pluginVariant, $isB2B);
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
                    mb_substr($record['customer']['delivery_address'][$maxNumber['field']], 0, $maxNumber['limit']);
                $this->exportHelper->addHistoryData('Reduce delivery ' . $maxNumber['field'] . ' to ' . $maxNumber['limit'], $order->id);
            }
            if (strlen($record['customer']['invoice_address'][$maxNumber['field']]) > $maxNumber['limit']){
                $record['customer']['invoice_address'][$maxNumber['field']] =
                    mb_substr($record['customer']['invoice_address'][$maxNumber['field']], 0, $maxNumber['limit']);
                $this->exportHelper->addHistoryData('Reduce invoice ' . $maxNumber['field'] . ' to ' . $maxNumber['limit'], $order->id);
            }
        }

        $this->exportHelper->addHistoryData('Record data for order: ' . $order->id, $order->id, json_encode($record));
        $this->saveRecord($order->id, $record, $xml_destination);

        $this->exportHelper->addHistoryData('End processing order ' . $order->id, $order->id);
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
    public function saveRecord(int $plentyOrderId, array $record, int $xml_destination){

        $exportData = [
            'plentyOrderId'    => $plentyOrderId,
            'exportedData'     => json_encode($record),
            'savedAt'          => Carbon::now()->toDateTimeString(),
            'sentdAt'          => '',
            'xml_destination'  => $xml_destination
        ];

        /** @var ExportDataRepository $exportDataRepository */
        $exportDataRepository = pluginApp(ExportDataRepository::class);
        try {
            if (!$exportDataRepository->orderExists($plentyOrderId)) {
                /** @var TableRow $savedObject */
                $exportDataRepository->save($exportData);
                $this->exportHelper->addHistoryData('Saved to export stack', $plentyOrderId);
                $statusOfProcessedOrder = $this->configRepository->getProcessedOrderStatus();
                if ($statusOfProcessedOrder != ''){
                    $this->orderRepository->updateOrder(['statusId' => $statusOfProcessedOrder], $plentyOrderId);
                    $this->exportHelper->addHistoryData('Order status updated to ' . $statusOfProcessedOrder, $plentyOrderId);
                }
                return true;
            }
            $this->getLogger(__METHOD__)
                ->addReference('orderId', $plentyOrderId)
                ->report(PluginConfiguration::PLUGIN_NAME . '::error.orderExists', $exportData);
            $this->exportHelper->addHistoryData('Order already exists in the export stack', $plentyOrderId);
            return false;
        } catch (\Throwable $e) {
            $this->getLogger(__METHOD__)
                ->addReference('orderId', $plentyOrderId)
                ->error(PluginConfiguration::PLUGIN_NAME . '::error.saveExportError',
                [
                    'message'     => $e->getMessage(),
                    'exportData'  => $exportData
                ]);
            $this->exportHelper->addHistoryData('Exception when writing to the export table: ' . $e->getMessage(), $plentyOrderId);
        }
        return false;
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
     * @param $exportList
     * @param $generationTime
     * @param $batchNo
     * @param int $xml_destination
     * @return string
     */
    public function generateXMLFromOrderData($exportList, $generationTime, $batchNo, int $xml_destination): string
    {
        $resultedXML = '<?xml version="1.0" encoding="UTF-8" standalone="no" ?>
<import_batch version_number="1.0" xmlns="http://nesclub.nespresso.com/webservice/club/xsd/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://nesclub.nespresso.com/webservice/club/xsd/ http://nesclub.nespresso.com/webservice/club/xsd/">
  	<!-- HEADER STARTS HERE--> 
	<batch_date_time>'.$generationTime.'</batch_date_time>
	<batch_number>'.$batchNo.'</batch_number> <!-- Batch Number is continous, starting with 01 -->
	<sender_id>'.$this->exportHelper->getSenderIdValue($this->pluginVariant, $xml_destination).'</sender_id>
';
        if ($this->pluginVariant == 'AT') {
            $resultedXML .= "<entity>5</entity>\n";
        }

        $totalQuantities = 0;
        $totalCustomers = 0; //removed from totals in version 1.1.12
        $customerList = [];

        /** @var TableRow $order */
        foreach ($exportList as $order){
            $orderData = json_decode($order->exportedData, true);

            //convert data into XML format with particular attributes
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
     * @param string $fileName
     * @param int $xml_destination
     * @return bool
     */
    public function sendToFTP(string $xmlContent, string $fileName, int $xml_destination)
    {
        try {
            $this->getLogger(__METHOD__)->info(
                PluginConfiguration::PLUGIN_NAME . '::general.logMessage',
                [
                    'xmlContent' => $xmlContent,
                    'fileName'=> $fileName
                ]
            );
            $result = $this->ftpClient->uploadXML($fileName, $xmlContent, $xml_destination);
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
                    'xml_destination'  => $order->xml_destination
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

    /**
     * @return void
     */
    public function sendDataToClient(): bool
    {
        /** @var ExportDataRepository $exportDataRepository */
        $exportDataRepository = pluginApp(ExportDataRepository::class);

        /** @var SettingRepository $settingsRepository */
        $settingsRepository = pluginApp(SettingRepository::class);

        $this->sendToOneDestination(
            $exportDataRepository,
            $settingsRepository,
            PluginConfiguration::STANDARD_DESTINATION
        );

        if ($this->pluginVariant == 'DE') {
            $this->sendToOneDestination(
                $exportDataRepository,
                $settingsRepository,
                PluginConfiguration::B2B_DESTINATION
            );

            $this->sendToOneDestination(
                $exportDataRepository,
                $settingsRepository,
                PluginConfiguration::FBM_DESTINATION
            );
        }
        return true;
    }

    /**
     * @param ExportDataRepository $exportDataRepository
     * @param SettingRepository $settingsRepository
     * @param int $xml_destination
     * @return bool
     */
    private function sendToOneDestination(
        ExportDataRepository $exportDataRepository,
        SettingRepository $settingsRepository,
        int $xml_destination
    )
    {
        try {
            $exportList = $exportDataRepository->listUnsent($this->totalOrdersPerBatch, $xml_destination);
            if (count($exportList) > 0) {
                $thisTime = Carbon::now();
                $generationTime = $thisTime->toDateTimeString();
                $batchNo = $settingsRepository->getBatchNumber($xml_destination);
                if (($this->pluginVariant == 'AT') && ((int)$batchNo == 2000)) {
                    $batchNo = "2001";
                    $settingsRepository->incrementBatchNumber($xml_destination);
                }
                $xmlContent = $this->generateXMLFromOrderData($exportList, $generationTime, $batchNo, $xml_destination);
                if (!$this->sendToFTP(
                    $xmlContent,
                    $this->exportHelper->getFileNameForExport(
                        $thisTime,
                        $xml_destination,
                        $this->pluginVariant,
                        $batchNo),
                    $xml_destination
                )) {
                    $this->exportHelper->addHistoryData('Export to ' . $xml_destination . ' failed!');
                    return false;
                }

                $settingsRepository->incrementBatchNumber($xml_destination);
                $this->markRowsAsSent($exportList, $generationTime);
                $this->exportHelper->addHistoryData('Export to ' . $xml_destination . ' succeeded! (Batch: '.$batchNo.')');
            } else {
                $this->exportHelper->addHistoryData('No data for ' . $xml_destination . ' destination.');
            }

        } catch (\Throwable $e) {
            $this->getLogger(__METHOD__)->error(PluginConfiguration::PLUGIN_NAME . '::error.readExportError',
                [
                    'message'     => $e->getMessage(),
                ]);
            $this->exportHelper->addHistoryData('Exception when sending to ' . $xml_destination . ' dest.: ' . $e->getMessage());
            return false;
        }
        return true;
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