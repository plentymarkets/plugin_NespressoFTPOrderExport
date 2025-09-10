<?php

namespace NespressoFTPOrderExport\Helpers;

use Carbon\Carbon;
use NespressoFTPOrderExport\Configuration\PluginConfiguration;
use NespressoFTPOrderExport\Repositories\HistoryDataRepository;
use Plenty\Modules\Order\Models\Order;

class ExportHelper
{

    /**
     * @var HistoryDataRepository
     */
    private $historyData;

    public function __construct(
        HistoryDataRepository $historyData
    )
    {
        $this->historyData = $historyData;
    }

    public function getNamesFromOrder(Order $order, string $pluginVariant)
    {
        //dismiss data in name1 if it contains wrong information
        $orderDeliveryName1 = $order->deliveryAddress->name1;
        $orderBillingName1 = $order->billingAddress->name1;
        if ($pluginVariant == 'DE') {
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
        return [
            'orderDeliveryName1' => $orderDeliveryName1,
            'orderBillingName1' => $orderBillingName1
        ];
    }

    public function getDeliveryNameValue(Order $order, string $pluginVariant, $orderDeliveryName1)
    {
        if ($orderDeliveryName1 != '') {
            if ($pluginVariant == 'DE') {
                return $orderDeliveryName1;
            }
            return $order->deliveryAddress->name2 . ' ' . $order->deliveryAddress->name3;
        }
        return $order->deliveryAddress->name3;
    }

    public function getInvoiceNameValue(Order $order, string $pluginVariant, $orderBillingName1)
    {
        if ($orderBillingName1 != '') {
            return $orderBillingName1; //ATENTIE DIFERIT DE DELIVERY pentru AT!
        }
        return $order->billingAddress->name3;
    }

    public function getDeliveryExtraNameValue(Order $order, string $pluginVariant, $orderDeliveryName1)
    {
        if ( ($pluginVariant == 'AT') && ($order->deliveryAddress->companyName != '')){
            return $orderDeliveryName1;
        }
        return '';
    }

    public function getInvoiceExtraNameValue(Order $order, string $pluginVariant)
    {
        if ( ($pluginVariant == 'AT') && ($order->billingAddress->companyName != '')){
            return $order->billingAddress->name2 . ' ' . $order->billingAddress->name3;
            //ATENTIE DIFERIT DE DELIVERY pentru AT!
        }
        return '';
    }

    public function getDeliveryAddressLine1Value(Order $order, string $pluginVariant)
    {
        if ($pluginVariant == 'DE') {
            if (($order->deliveryAddress->isPackstation === true) || $order->deliveryAddress->isPostfiliale === true) {
                return $order->deliveryAddress->address1 . ' ' . $order->deliveryAddress->packstationNo;
            }
            return $order->deliveryAddress->address1 . ' ' . $order->deliveryAddress->address2;
        }
        return $order->deliveryAddress->address1 . ' ' . $order->deliveryAddress->address2;
    }

    public function getInvoiceAddressLine1Value(Order $order, string $pluginVariant)
    {
        if ($pluginVariant == 'DE') {
            if (($order->billingAddress->isPackstation === true) || $order->billingAddress->isPostfiliale === true) {
                return $order->billingAddress->address1 . ' ' . $order->billingAddress->packstationNo;
            }
            return $order->billingAddress->address1 . ' ' . $order->billingAddress->address2;
        }
        return $order->billingAddress->address1 . ' ' . $order->billingAddress->address2;
    }

    public function getDeliveryAddressLine2Value(Order $order, string $pluginVariant, $orderDeliveryName1, $addressLine1)
    {
        if ($pluginVariant == 'DE') {
            if (($order->deliveryAddress->isPackstation === true) || $order->deliveryAddress->isPostfiliale === true) {
                return $orderDeliveryName1;
            }
            return '';
        }

        if (strlen($addressLine1) > 35){
            return substr(
                $addressLine1,
                35,
                strlen($addressLine1) - 35);
        }
        return '';
    }

    public function getInvoiceAddressLine2Value(Order $order, string $pluginVariant, $orderBillingName1, $addressLine1)
    {
        if ($pluginVariant == 'DE') {
            if (($order->billingAddress->isPackstation === true) || $order->billingAddress->isPostfiliale === true) {
                return $orderBillingName1;
            }
            return '';
        }

        if (strlen($addressLine1) > 35){
            return substr(
                $addressLine1,
                35,
                strlen($addressLine1) - 35);
        }
        return '';
    }

    public function getMovementCodeValue(string $pluginVariant, bool $isB2B, bool $isFBM)
    {
        if ($pluginVariant == 'DE') {
            if ($isFBM){
                return "52";
            }
            if ($isB2B){
                return "71";
            }
            return "3";
        }
        return "2010";
    }

    public function getDeliveryModeValue(string $pluginVariant, bool $isFBM)
    {
        if ($pluginVariant == 'DE') {
            if ($isFBM){
                return "VZ";
            }
            return "FA";
        }
        return "GP";
    }

    public function getSourceCodeValue(string $pluginVariant, bool $isB2B)
    {
        if (($pluginVariant == 'DE') && $isB2B) {
            return 'AMB';
        }
        return 'AMZ';
    }

    public function getPaymentModeValue(string $pluginVariant, bool $isB2B)
    {
        if ($pluginVariant == 'DE') {
            if ($isB2B) {
                return 'XB';
            }
            return 'XA';
        }
        return 'AM';
    }

    public function getOrderSourceValue(string $pluginVariant, bool $isB2B)
    {
        if (($pluginVariant == 'DE') && $isB2B) {
            return "AMB";
        }
        return "AMZ";
    }

    public function getChannelValue(string $pluginVariant, bool $isB2B)
    {
        if (($pluginVariant == 'DE') && $isB2B) {
            return "33";
        }
        return "32";
    }

    /**
     * @param string $pluginVariant
     * @param int $xml_destination
     * @return int
     */
    public function getSenderIdValue(string $pluginVariant, int $xml_destination)
    {
        switch ($xml_destination){
            case PluginConfiguration::STANDARD_DESTINATION:
                if ($pluginVariant == 'DE'){
                    return 89;
                }
                return 86;
            case PluginConfiguration::B2B_DESTINATION:
                return 90;
            case PluginConfiguration::FBM_DESTINATION:
                return 86;
        }
    }

    public function getFileNameForExport(Carbon $thisTime, int $xml_destination, string $pluginVariant, string $batchNo)
    {
        switch ($xml_destination){
            case PluginConfiguration::STANDARD_DESTINATION:
                if ($pluginVariant == 'DE') {
                    $fileName = 'B2C_FBA' . $thisTime->isoFormat("DDMMYY") . '-' . $thisTime->isoFormat(
                            "HHmm"
                        ) . '-' . $batchNo . '.xml';
                } else {
                    $fileName = $thisTime->isoFormat("DDMMYY") . '-' . $thisTime->isoFormat(
                            "HHmm"
                        ) . '-' . $batchNo . '.xml';
                }
                break;
            case PluginConfiguration::B2B_DESTINATION:
                $fileName = 'B2B_' . $thisTime->isoFormat("DDMMYY") . '-' . $thisTime->isoFormat("HHmm") . '-'.$batchNo.'.xml';
                break;
            case PluginConfiguration::FBM_DESTINATION:
                $fileName = 'B2C_FBM_' . $thisTime->isoFormat("DDMMYY") . '-' . $thisTime->isoFormat("HHmm") . '-'.$batchNo.'.xml';
                break;
        }
        return $fileName;
    }

    public function addHistoryData($message, $plentyOrderId = -1)
    {
        $data = [
            'plentyOrderId' => $plentyOrderId,
            'message'       => $message,
            'savedAt'       => Carbon::now()->toDateTimeString()
        ];

        $this->historyData->save($data);
    }

}