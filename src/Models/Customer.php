<?php

namespace NespressoFTPOrderExport\Models;

class Customer
{
    /**
     * @var Address
     */
    public $delivery_address;

    /**
     * @var string
     */
    public $state_inscription_number;

    /**
     * @var string
     */
    public $vat_number;

    /**
     * @var int
     */
    public $address_different;

    /**
     * @var string
     */
    public $company;

    /**
     * @var Address
     */
    public $invoice_address;

    /**
     * @var ContactPreference
     */
    public $contact_preference;

    /**
     * @var PrivacyPolicy
     */
    public $privacy_policy;

    /**
     * @var string
     */
    public $input_user;

    /**
     * @var string
     */
    public $fiscal_receipt;
}
