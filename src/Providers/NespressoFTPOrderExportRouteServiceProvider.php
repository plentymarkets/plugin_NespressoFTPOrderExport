<?php
namespace NespressoFTPOrderExport\Providers;

use Plenty\Plugin\Routing\ApiRouter;
use Plenty\Plugin\RouteServiceProvider;

/**
 * Class NespressoFTPOrderExportRouteServiceProvider
 * @package NespressoFTPOrderExport\Providers
 */
class NespressoFTPOrderExportRouteServiceProvider extends RouteServiceProvider
{
    /**
     * @param  ApiRouter  $apiRouter
     */
    public function map(ApiRouter $apiRouter)
    {
        $apiRouter->version(['v1'], ['namespace' => 'NespressoFTPOrderExport\Controllers', 'middleware' => 'oauth'],
            function ($apiRouter) {
                $apiRouter->get('NespressoFTPOrderExport/test/', 'TestController@testMethod');
                $apiRouter->get('NespressoFTPOrderExport/cleartable/', 'TestController@clearDataTable');
            }
        );
    }
}
