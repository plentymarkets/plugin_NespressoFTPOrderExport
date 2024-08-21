<?php

namespace NespressoFTPOrderExport\Models;

class ContactPreference
{
    /**
     * @var string
     */
    public $email;

    /**
     * @var int
     */
    public $mailing_authorization;

    /**
     * @var int
     */
    public $post_mailing_active;

    /**
     * @var int
     */
    public $contact_by_phone_allowed;

    /**
     * @var int
     */
    public $mobile_notification_active;
}
