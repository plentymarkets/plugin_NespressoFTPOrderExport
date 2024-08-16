<?php

namespace NespressoFTPOrderExport\Controllers;

use Plenty\Plugin\Controller;
use Plenty\Plugin\Log\Loggable;

class TestController extends Controller
{
    use Loggable;

    public function testMethod()
    {
        return 'abc';
    }
}
