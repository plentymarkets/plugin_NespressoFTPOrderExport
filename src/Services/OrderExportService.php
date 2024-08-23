<?php

namespace NespressoFTPOrderExport\Services;

use Carbon\Carbon;
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
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\Order\Date\Models\OrderDateType;
use Plenty\Modules\Order\Models\Order;
use Plenty\Modules\Order\Property\Models\OrderPropertyType;
use Plenty\Plugin\Log\Loggable;

use function Kelunik\Acme\Protocol\string;

class OrderExportService
{

    public function __construct(
    ) {
    }

    public function processOrder(Order $order)
    {
        $deliveryAddress = pluginApp(Address::class);
        if ($order->deliveryAddress->companyName != '') {
            $deliveryAddress->company = $order->deliveryAddress->companyName;
        } else {
            $deliveryAddress->company = '0';
        }
        $deliveryAddress->contact = '';
        $deliveryAddress->name = $order->deliveryAddress->firstName . ' ' . $order->deliveryAddress->lastName;
        $deliveryAddress->first_name = $order->deliveryAddress->firstName;
        $deliveryAddress->civility = 5;
        $deliveryAddress->extra_name = '';
        $deliveryAddress->address_line1 = $order->deliveryAddress->address1;
        $deliveryAddress->address_line2 = $order->deliveryAddress->address2;
        $deliveryAddress->post_code = $order->deliveryAddress->postalCode;
        $deliveryAddress->city = $order->deliveryAddress->town;
        $deliveryAddress->country = $order->deliveryAddress->country->isoCode2;
        $deliveryAddress->area1 = '';
        $deliveryAddress->area2 = '';
        $deliveryAddress->remark = '';
        $deliveryAddress->language = $order->contactReceiver->lang;

        $invoiceAddress = pluginApp(Address::class);
        if ($order->billingAddress->companyName != '') {
            $invoiceAddress->company = $order->billingAddress->companyName;
        } else {
            $invoiceAddress->company = '0';
        }
        $invoiceAddress->contact = '';
        $invoiceAddress->name = $order->billingAddress->firstName . ' ' . $order->deliveryAddress->lastName;
        $invoiceAddress->first_name = $order->billingAddress->firstName;
        $invoiceAddress->civility = 5;
        $invoiceAddress->extra_name = '';
        $invoiceAddress->address_line1 = $order->billingAddress->address1;
        $invoiceAddress->address_line2 = $order->billingAddress->address2;
        $invoiceAddress->post_code = $order->billingAddress->postalCode;
        $invoiceAddress->city = $order->billingAddress->town;
        $invoiceAddress->country = $order->billingAddress->country->isoCode2;
        $invoiceAddress->area1 = '';
        $invoiceAddress->area2 = '';
        $invoiceAddress->remark = '';
        $invoiceAddress->language = $order->contactReceiver->lang;

        $contactPreference = pluginApp(ContactPreference::class);
        $contactPreference->email = $order->contactReceiver->email;
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
        $customer->address_different = $this->checkAddressDifferent();
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
            $orderLine->product_code = ''; //!!!
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

        $this->sendDataToClient();
    }

    /**
     * @param \Plenty\Modules\Account\Address\Models\Address $firstAddress
     * @param \Plenty\Modules\Account\Address\Models\Address $secondAddress
     * @return void
     */
    public function checkAddressDifferent(): int
    {
        return 1;
    }

    public function getBatchNumber()
    {
        return '01';
    }

    public function arrayToXml($array) {
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

    public function generateXMLFromOrderData($exportList, $generationTime): string
    {
        $resultedXML = '
<?xml version="1.0" encoding="UTF-16" standalone="no" ?>
<import_batch version_number="1.0" xmlns="http://nesclub.nespresso.com/webservice/club/xsd/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://nesclub.nespresso.com/webservice/club/xsd/ http://nesclub.nespresso.com/webservice/club/xsd/">
  	<!-- HEADER STARTS HERE--> 
	<batch_date_time>'.$generationTime.'</batch_date_time>
	<batch_number>'.$this->getBatchNumber().'</batch_number> <!-- Batch Number is continous, starting with 01 -->
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

    public function sendToFTP($xmlContent)
    {
        $a = $xmlContent;
        return true;
    }

    public function sendDataToClient(): bool
    {
        /** @var ExportDataRepository $exportDataRepository */
        $exportDataRepository = pluginApp(ExportDataRepository::class);
        try {
            $exportList = $exportDataRepository->list(50);
        } catch (\Throwable $e) {
            $this->getLogger(__METHOD__)->error(PluginConfiguration::PLUGIN_NAME . '::error.readExportError',
                [
                    'message'     => $e->getMessage(),
                ]);
            return false;
        }

        $generationTime = Carbon::now()->toDateTimeString();
        $xmlContent = $this->generateXMLFromOrderData($exportList, $generationTime);
        if (!$this->sendToFTP($xmlContent)){
            $this->getLogger(__METHOD__)->error(PluginConfiguration::PLUGIN_NAME . '::error.wrtiteFtpError',
                []);
            return false;
        }

        return true;
    }
}