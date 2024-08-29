<?php

namespace NespressoFTPOrderExport\Services;

use Carbon\Carbon;
use NespressoFTPOrderExport\Clients\SFTPClient;
use NespressoFTPOrderExport\Configuration\PluginConfiguration;
use NespressoFTPOrderExport\Models\Address;
use NespressoFTPOrderExport\Models\ContactPreference;
use NespressoFTPOrderExport\Models\Customer;
use NespressoFTPOrderExport\Models\OrderData;
use NespressoFTPOrderExport\Models\OrderDetails;
use NespressoFTPOrderExport\Models\OrderLine;
use NespressoFTPOrderExport\Models\PrivacyPolicy;
use NespressoFTPOrderExport\Models\Record;
use NespressoFTPOrderExport\Models\TableRow;
use NespressoFTPOrderExport\Repositories\ExportDataRepository;
use NespressoFTPOrderExport\Repositories\SettingRepository;
use Plenty\Modules\Account\Address\Models\AddressOption;
use Plenty\Modules\Order\Date\Models\OrderDateType;
use Plenty\Modules\Order\Models\Order;
use Plenty\Modules\Order\Property\Models\OrderPropertyType;
use Plenty\Plugin\Log\Loggable;

class OrderExportService
{
    use Loggable;

    /**
     * @var SFTPClient
     */
    private $ftpClient;

    /**
     * @param SFTPClient $ftpClient
     */
    public function __construct(SFTPClient $ftpClient)
    {
        $this->ftpClient       = $ftpClient;
    }

    /**
     * @param Order $order
     * @return void
     */
    public function processOrder(Order $order)
    {
        $deliveryAddress = pluginApp(Address::class);
        if ($order->deliveryAddress->companyName != '') {
            $deliveryAddress->company = $order->deliveryAddress->companyName;
        } else {
            $deliveryAddress->company = '0';
        }
        $deliveryAddress->contact = '';
        $deliveryAddress->name = $order->deliveryAddress->name3;
        $deliveryAddress->first_name = $order->deliveryAddress->name2;
        $deliveryAddress->civility = 5;
        $deliveryAddress->extra_name = $order->deliveryAddress->name4;
        if (($order->deliveryAddress->isPackstation === true) || $order->deliveryAddress->isPostfiliale === true) {
            $deliveryAddress->address_line1 = $order->deliveryAddress->options->where('typeId', AddressOption::TYPE_POST_NUMBER)->first();
            $deliveryAddress->address_line2 = $order->deliveryAddress->address4;
        } else {
            $deliveryAddress->address_line1 = $order->deliveryAddress->address1 . ' ' . $order->deliveryAddress->address2;
            $deliveryAddress->address_line2 = '';
        }
        /*
        $deliveryAddress->address_line1 = $order->deliveryAddress->address1 . ' ' . $order->deliveryAddress->address2;
        $deliveryAddress->address_line2 = '';
        if ($deliveryAddress->address_line1 === ''){
            if (($order->deliveryAddress->isPackstation === true) || $order->deliveryAddress->isPostfiliale === true) {
                $deliveryAddress->address_line1 = $order->deliveryAddress->options->where('typeId', AddressOption::TYPE_POST_NUMBER)->first();
                $deliveryAddress->address_line2 = $order->deliveryAddress->address4;
            }
        }
        */
        $deliveryAddress->post_code = $order->deliveryAddress->postalCode;
        $deliveryAddress->city = $order->deliveryAddress->town;
        $deliveryAddress->country = $order->deliveryAddress->country->isoCode2;
        $deliveryAddress->area1 = '';
        $deliveryAddress->area2 = '';
        $deliveryAddress->remark = '';
        $deliveryAddress->language = $this->getOrderLanguage($order);

        $invoiceAddress = pluginApp(Address::class);
        if ($order->billingAddress->companyName != '') {
            $invoiceAddress->company = $order->billingAddress->companyName;
        } else {
            $invoiceAddress->company = '0';
        }
        $invoiceAddress->contact = '';
        $invoiceAddress->name = $order->deliveryAddress->name3;
        $invoiceAddress->first_name = $order->billingAddress->name2;
        $invoiceAddress->civility = 5;
        $invoiceAddress->extra_name = '';
        if (($order->billingAddress->isPackstation === true) || $order->billingAddress->isPostfiliale === true) {
            $invoiceAddress->address_line1 = $order->billingAddress->options->where('typeId', AddressOption::TYPE_POST_NUMBER)->first();
            $invoiceAddress->address_line2 = $order->billingAddress->address4;
        } else {
            $invoiceAddress->address_line1 = $order->billingAddress->address1 . ' ' . $order->billingAddress->address2;
            $invoiceAddress->address_line2 = '';
        }
        /*
        $invoiceAddress->address_line1 = $order->billingAddress->address1 . ' ' . $order->billingAddress->address2;
        $invoiceAddress->address_line2 = '';
        if ($invoiceAddress->address_line1 === ''){
            if (($order->billingAddress->isPackstation === true) || $order->billingAddress->isPostfiliale === true) {
                $invoiceAddress->address_line1 = $order->billingAddress->options->where('typeId', AddressOption::TYPE_POST_NUMBER)->first();
                $invoiceAddress->address_line2 = $order->billingAddress->address4;
            }
        }
        */
        $invoiceAddress->post_code = $order->billingAddress->postalCode;
        $invoiceAddress->city = $order->billingAddress->town;
        $invoiceAddress->country = $order->billingAddress->country->isoCode2;
        $invoiceAddress->area1 = '';
        $invoiceAddress->area2 = '';
        $invoiceAddress->remark = '';
        $invoiceAddress->language = $this->getOrderLanguage($order);

        $contactPreference = pluginApp(ContactPreference::class);
        $contactPreference->email = $order->billingAddress->email;
        $contactPreference->mailing_authorization = 0;
        $contactPreference->post_mailing_active = 0;
        $contactPreference->contact_by_phone_allowed = 0;
        $contactPreference->mobile_notification_active = 0;

        $privacyPolicy = pluginApp(PrivacyPolicy::class);
        $privacyPolicy->terms_and_condition_accepted = 1;
        $privacyPolicy->allow_use_satisfaction_research = 0;
        $privacyPolicy->allow_personalized_management = 0;
        $privacyPolicy->allow_use_of_personal_data_for_marketing = 0;

        $customer = pluginApp(Customer::class);
        $customer->delivery_address = $deliveryAddress;
        $customer->state_inscription_number = '';
        $customer->vat_number = '';
        $customer->address_different = ($order->deliveryAddress->id == $order->billingAddress->id);
        if ($order->deliveryAddress->companyName != '') {
            $customer->company = $order->deliveryAddress->companyName;
        } else {
            $customer->company = '0';
        }
        $customer->invoice_address = $invoiceAddress;
        $customer->contact_preference = $contactPreference;
        $customer->privacy_policy = $privacyPolicy;
        $customer->input_user = '';
        $customer->fiscal_receipt = 'true';

        $orderData = pluginApp(OrderData::class);
        $orderData->external_order_id = $order->getPropertyValue(OrderPropertyType::EXTERNAL_ORDER_ID);
        $orderData->movement_code = "3";
        $orderData->order_date = $order->dates->filter(
            function ($date) {
                return $date->typeId == OrderDateType::ORDER_ENTRY_AT;
            }
        )->first()->date->timestamp;
        $orderData->order_source = 'AMZ';
        $orderData->delivery_mode = 'VZ';
        $orderData->payment_mode = 'XA';

        $orderData->order_details = pluginApp(OrderDetails::class);
        foreach ($order->orderItems as $orderItem) {
            $orderLine = pluginApp(OrderLine::class);
            $orderLine->product_code = $orderItem->variation->number;
            $orderLine->quantity = $orderItem->quantity;
            $orderLine->serial_number = '';

            $orderData->order_details->order_lines[] = $orderLine;
        }

        $record = pluginApp(Record::class);

        $record->record_remarks = "";
        $record->external_ref = $order->getPropertyValue(OrderPropertyType::EXTERNAL_ORDER_ID);
        $record->member_number = "";
        $record->address_changed = 1;
        $record->order_source = "AMZ";
        $record->channel = "32";
        $record->customer = $customer;
        $record->order = $orderData;

        $record->saveRecord($order->id);
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
        $resultedXML = '
<?xml version="1.0" encoding="UTF-16" standalone="no" ?>
<import_batch version_number="1.0" xmlns="http://nesclub.nespresso.com/webservice/club/xsd/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://nesclub.nespresso.com/webservice/club/xsd/ http://nesclub.nespresso.com/webservice/club/xsd/">
  	<!-- HEADER STARTS HERE--> 
	<batch_date_time>'.$generationTime.'</batch_date_time>
	<batch_number>'.$batchNo.'</batch_number> <!-- Batch Number is continous, starting with 01 -->
	<sender_id>89</sender_id>
';

        $totalQuantities = 0;
        $customerList = [];

        /** @var TableRow $order */
        foreach ($exportList as $order){
            $orderData = json_decode($order->exportedData, true);
            $resultedXML .= $this->arrayToXml(['record' => $orderData]);

            foreach ($orderData['order']['order_details']['order_lines'] as $orderLine){
                $totalQuantities += $orderLine['quantity'];
            }

            $currentCustomer = $orderData['customer']['company'];
            if (!in_array($currentCustomer, $customerList)){
                $customerList[] = $currentCustomer;
            }
        }

        $resultedXML .= '
<total_orders>'.count($exportList).'</total_orders> 
<total_quantity>'.$totalQuantities.'</total_quantity> 
<total_customers>'.count($customerList).'</total_customers> 
<total_members>'.count($customerList).'</total_members> 
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
            $this->ftpClient->uploadXML($fileName, $xmlContent);
        } catch (\Throwable $exception) {
            $this->getLogger(__METHOD__)->error(
                PluginConfiguration::PLUGIN_NAME . '::error.writeFtpError',
                [
                    'message' => $exception->getMessage(),
                    'fileNamw'=> $fileName
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
            $this->getLogger(__METHOD__)->error(PluginConfiguration::PLUGIN_NAME . '::error.writeFtpError',
                []);
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