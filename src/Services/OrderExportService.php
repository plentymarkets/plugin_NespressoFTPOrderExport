<?php

namespace NespressoFTPOrderExport\Services;

use Carbon\Carbon;
use IO\Builder\Order\OrderItemType;
use NespressoFTPOrderExport\Clients\ClientForSFTP;
use NespressoFTPOrderExport\Configuration\PluginConfiguration;
use NespressoFTPOrderExport\Models\TableRow;
use NespressoFTPOrderExport\Repositories\ExportDataRepository;
use NespressoFTPOrderExport\Repositories\SettingRepository;
use Plenty\Modules\Account\Address\Models\AddressOption;
use Plenty\Modules\Order\Date\Models\OrderDateType;
use Plenty\Modules\Order\Models\Order;
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
     * @param ClientForSFTP $ftpClient
     */
    public function __construct(
        ClientForSFTP $ftpClient,
        PluginConfiguration    $configRepository
    )
    {
        $this->ftpClient        = $ftpClient;
        $this->configRepository = $configRepository;
        $this->pluginVariant    = $this->configRepository->getPluginVariant();
    }

    /**
     * @param Order $order
     * @return void
     */
    public function processOrder(Order $order)
    {
        $deliveryAddress = [];
        if ($order->deliveryAddress->companyName != '') {
            $deliveryAddress['company'] = '1';
            $deliveryAddress['name'] = $order->deliveryAddress->companyName;
            $deliveryAddress['first_name'] = '';
            $deliveryAddress['contact'] = $order->deliveryAddress->name2 . ' ' . $order->deliveryAddress->name3;
        } else {
            $deliveryAddress['company'] = '0';
            $deliveryAddress['name'] = $order->deliveryAddress->name3;
            $deliveryAddress['first_name'] = $order->deliveryAddress->name2;
            $deliveryAddress['contact'] = '';
        }
        $deliveryAddress['civility'] = 5;
        $deliveryAddress['extra_name'] = $order->deliveryAddress->name4;
        if (($order->deliveryAddress->isPackstation === true) || $order->deliveryAddress->isPostfiliale === true) {
            $deliveryAddress['address_line1'] = $order->deliveryAddress->packstationNo;
            $deliveryAddress['address_line2'] = $order->deliveryAddress->options->where('typeId', AddressOption::TYPE_POST_NUMBER)->first();
            if ($deliveryAddress['address_line2'] === '') {
                $deliveryAddress['address_line2'] = $order->deliveryAddress->companyName;
            }
            $deliveryAddress['address_line2'] = 'PACKSTATION ' . $deliveryAddress['address_line2'];
        } else {
            $deliveryAddress['address_line1'] = $order->deliveryAddress->address1 . ' ' . $order->deliveryAddress->address2;
            $deliveryAddress['address_line2'] = '';
        }
        $deliveryAddress['post_code'] = $order->deliveryAddress->postalCode;
        $deliveryAddress['city'] = $order->deliveryAddress->town;
        $deliveryAddress['country'] = $order->deliveryAddress->country->isoCode2;
        $deliveryAddress['area1'] = '';
        $deliveryAddress['area2'] = '';
        $deliveryAddress['remark'] = '';

        $invoiceAddress = [];
        $customer['address_different'] = ($order->deliveryAddress->id == $order->billingAddress->id);
        if ($customer['address_different']) {
            if ($order->billingAddress->companyName != '') {
                $invoiceAddress['company'] = '1';
                $invoiceAddress['name'] = $order->billingAddress->companyName;
                $invoiceAddress['first_name'] = '';
                $invoiceAddress['contact'] = $order->billingAddress->name2 . ' ' . $order->billingAddress->name3;
            } else {
                $invoiceAddress['company'] = '0';
                $invoiceAddress['name'] = $order->deliveryAddress->name3;
                $invoiceAddress['first_name'] = $order->billingAddress->name2;
                $invoiceAddress['contact'] = '';
            }
            $invoiceAddress['civility'] = 5;
            $invoiceAddress['extra_name'] = '';

            if (($order->billingAddress->isPackstation === true) || $order->billingAddress->isPostfiliale === true) {
                $invoiceAddress['address_line1'] = $order->billingAddress->packstationNo;
                $invoiceAddress['address_line2'] = $order->billingAddress->options->where('typeId', AddressOption::TYPE_POST_NUMBER)->first();
                if ($invoiceAddress['address_line2'] === '') {
                    $invoiceAddress['address_line2'] = $order->billingAddress->companyName;
                }
                $invoiceAddress['address_line2'] = 'PACKSTATION ' . $invoiceAddress['address_line2'];
            } else {
                $invoiceAddress['address_line1'] = $order->billingAddress->address1 . ' ' . $order->billingAddress->address2;
                $invoiceAddress['address_line2'] = '';
            }
            $invoiceAddress['post_code'] = $order->billingAddress->postalCode;
            $invoiceAddress['city'] = $order->billingAddress->town;
            $invoiceAddress['country'] = $order->billingAddress->country->isoCode2;
            $invoiceAddress['area1'] = '';
            $invoiceAddress['area2'] = '';
            $invoiceAddress['remark'] = '';
        }
        $contactPreference = [];
        $contactPreference['email'] = $order->billingAddress->email;
        $contactPreference['mailing_authorization'] = 0;
        $contactPreference['post_mailing_active'] = 0;
        $contactPreference['contact_by_phone_allowed'] = 0;
        $contactPreference['mobile_notification_active'] = 0;

        $privacyPolicy = [];
        $privacyPolicy['terms_and_condition_accepted'] = 1;
        $privacyPolicy['allow_use_satisfaction_research'] = 0;
        $privacyPolicy['allow_personalized_management'] = 0;
        $privacyPolicy['allow_use_of_personal_data_for_marketing'] = 0;

        $customer = [];
        $customer['delivery_address'] = $deliveryAddress;
        $customer['state_inscription_number'] = '';
        $customer['vat_number'] = '';
        if ($order->deliveryAddress->companyName != '') {
            $customer['company'] = $order->deliveryAddress->companyName;
        } else {
            $customer['company'] = '0';
        }
        $customer['invoice_address'] = $invoiceAddress;
        $customer['contact_preference'] = $contactPreference;
        $customer['privacy_policy'] = $privacyPolicy;
        $customer['input_user'] = '';
        $customer['fiscal_receipt'] = 'true';

        $orderData = [];
        $orderData['client_id'] = $this->getCustomerId($order);
        $orderData['external_order_id'] = $order->getPropertyValue(OrderPropertyType::EXTERNAL_ORDER_ID);
        $orderData['movement_code'] = "3";
        $orderData['order_date'] = $order->dates->filter(
            function ($date) {
                return $date->typeId == OrderDateType::ORDER_ENTRY_AT;
            }
        )->first()->date->isoFormat("DD/MM/YYYY");
        $orderData['order_source'] = 'AMZ';
        $orderData['delivery_mode'] = 'VZ';
        $orderData['payment_mode'] = 'XA';

        $orderData['order_details'] = [];
        foreach ($order->orderItems as $orderItem) {
            if ($orderItem->typeId === OrderItemType::VARIATION) {
                $orderLine = [];
                $orderLine['product_code'] = $orderItem->variation->number;
                $orderLine['quantity'] = $orderItem->quantity;
                $orderLine['serial_number'] = '';

                $orderData['order_details'][] = $orderLine;
            }
        }

        $record = [];

        $record['record_remarks'] = "";
        $record['external_ref'] = $order->getPropertyValue(OrderPropertyType::EXTERNAL_ORDER_ID);
        $record['member_number'] = "";
        $record['address_changed'] = 1;
        $record['order_source'] = "AMZ";
        $record['channel'] = "32";
        $record['customer'] = $customer;
        $record['order'] = $orderData;

        $this->saveRecord($order->id, $record);
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
    public function saveRecord(int $plentyOrderId, array $record){

        $exportData = [
            'plentyOrderId'    => $plentyOrderId,
            'exportedData'     => json_encode($record),
            'savedAt'          => Carbon::now()->toDateTimeString(),
            'sentdAt'          => '',
        ];

        /** @var ExportDataRepository $exportDataRepository */
        $exportDataRepository = pluginApp(ExportDataRepository::class);
        try {
            if (!$exportDataRepository->orderExists($plentyOrderId)) {
                /** @var TableRow $savedObject */
                $savedObject = $exportDataRepository->save($exportData);
                return true;
            }
            return false;
        } catch (\Throwable $e) {
            $this->getLogger(__METHOD__)->error(PluginConfiguration::PLUGIN_NAME . '::error.saveExportError',
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
    public function getBatchNumber(): string
    {
        $settingsRepository = pluginApp(SettingRepository::class);
        return $settingsRepository->getBatchNumber();
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
                    $str .= "<$k>" . $v . "</$k>\n";
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
    public function generateXMLFromOrderData($exportList, $generationTime, $batchNo): string
    {
        $resultedXML = '<?xml version="1.0" encoding="UTF-8" standalone="no" ?>
<import_batch version_number="1.0" xmlns="http://nesclub.nespresso.com/webservice/club/xsd/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://nesclub.nespresso.com/webservice/club/xsd/ http://nesclub.nespresso.com/webservice/club/xsd/">
  	<!-- HEADER STARTS HERE--> 
	<batch_date_time>'.$generationTime.'</batch_date_time>
	<batch_number>'.$batchNo.'</batch_number> <!-- Batch Number is continous, starting with 01 -->
	<sender_id>89</sender_id>
';

        $totalQuantities = 0;
        $totalCustomers = 0;
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

        $resultedXML .= '
<total_orders>'.count($exportList).'</total_orders> 
<total_quantity>'.$totalQuantities.'</total_quantity> 
<total_customers>'.$totalCustomers.'</total_customers> 
<total_members>'.$totalCustomers.'</total_members> 
</import_batch>
        ';

        return $resultedXML;
    }

    /**
     * @param string $xmlContent
     * @param string $filePrefix
     * @param string $batchNo
     * @return bool
     */
    public function sendToFTP(string $xmlContent, string $filePrefix, string $batchNo)
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
            $result = $this->ftpClient->uploadXML($fileName, $xmlContent);
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

    /**
     * @return bool
     */
    public function sendDataToClient(): bool
    {
        /** @var ExportDataRepository $exportDataRepository */
        $exportDataRepository = pluginApp(ExportDataRepository::class);
        try {
            $exportList = $exportDataRepository->listUnsent(50);
        } catch (\Throwable $e) {
            $this->getLogger(__METHOD__)->error(PluginConfiguration::PLUGIN_NAME . '::error.readExportError',
                [
                    'message'     => $e->getMessage(),
                ]);
            return false;
        }

        if (count($exportList) == 0){
            return false;
        }

        $thisTime = Carbon::now();
        $generationTime = $thisTime->toDateTimeString();
        $batchNo = $this->getBatchNumber();
        $xmlContent = $this->generateXMLFromOrderData($exportList, $generationTime, $batchNo);
        if (!$this->sendToFTP(
            $xmlContent,
            $thisTime->isoFormat("DDMMYY") . '-' . $thisTime->isoFormat("HHmm"),
            $batchNo
        )){
            return false;
        }

        $settingsRepository = pluginApp(SettingRepository::class);
        $settingsRepository->incrementBatchNumber();

        $this->markRowsAsSent($exportList, $generationTime);

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
            $exportDataRepository->deleteOldRecords(Carbon::now()->subDays(30)->toDateTimeString());
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