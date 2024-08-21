<?php

namespace NespressoFTPOrderExport\Models;

class OrderData
{
    /**
     * @var string
     */
    public $external_order_id;

    /**
     * @var string
     */
    public $movement_code;

    /**
     * @var string
     */
    public $order_date;

    /**
     * @var string
     */
    public $order_source;

    /**
     * @var string
     */
    public $delivery_mode;

    /**
     * @var string
     */
    public $payment_mode;

    /**
     * @var OrderDetails
     */
    public $order_details;
}
