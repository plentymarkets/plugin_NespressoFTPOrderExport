<?php

namespace NespressoFTPOrderExport\Helpers;

use NespressoFTPOrderExport\Repositories\SettingRepository;
use Plenty\Modules\Order\Models\Order;
use Plenty\Modules\Order\Models\OrderItem;

class OrderHelper
{

    /** @var int[] */
    private $b2bProductCodes;

    public function __construct()
    {
        $this->b2bProductCodes = $this->getProductCodesForB2B();
    }

    /**
     * @return int[]
     */
    private function getProductCodesForB2B()
    {
        /** @var SettingRepository $settingsRepository */
        $settingsRepository = pluginApp(SettingRepository::class);
        return $settingsRepository->getB2BProductList();
    }

    /**
     * @param Order $order
     * @param string $pluginVariant
     * @return bool
     */
    public function isB2B(Order $order, string $pluginVariant)
    {
        if ($pluginVariant == 'DE') {
            if (!is_array($this->b2bProductCodes)) {
                return false;
            }
            /** @var OrderItem $orderItem */
            foreach ($order->orderItems as $orderItem) {
                if (in_array($orderItem->variation->number, $this->b2bProductCodes, true)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @param Order $order
     * @param string $pluginVariant
     * @return bool
     */
    public function isFBM(Order $order, string $pluginVariant)
    {
        if (($pluginVariant == 'DE') && (($order->referrerId == 4.01) || ($order->referrerId == 4.21))) {
                return true;
        }
        return false;
    }

    /**
     * @param Order $order
     * @param string $pluginVariant
     * @return bool
     */
    public function isMMS(Order $order, string $pluginVariant)
    {
        if (($pluginVariant == 'AT') && ($order->referrerId == 178.01)) {
            return true;
        }
        return false;
    }

}