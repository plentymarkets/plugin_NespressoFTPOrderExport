<?php

namespace NespressoFTPOrderExport\Helpers;

use Plenty\Modules\Order\Models\Order;
use Plenty\Modules\Order\Models\OrderItem;
use Plenty\Modules\Order\Models\OrderItemType;

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
        return [
            '156680',
            '156681',
            '156682',
            '156683',
            '156684',
            '1033'
        ];
    }
    public function isB2B(Order $order)
    {
        /** @var OrderItem $orderItem */
        foreach ($order->orderItems as $orderItem) {
            if (in_array($orderItem->variation->number, $this->b2bProductCodes, true)) {
                return true;
            }
        }
        return false;
    }

}