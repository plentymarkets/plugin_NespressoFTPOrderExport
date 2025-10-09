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

    public function isFBM(Order $order, string $pluginVariant)
    {
        if (($pluginVariant == 'DE') && ($order->referrerId == 4.01)) {
                return true;
        }
        return false;
    }
}