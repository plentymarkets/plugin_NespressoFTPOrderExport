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
                $apiRouter->get('NespressoFTPOrderExport/cleartable/', 'TestController@clearDataTable');
                $apiRouter->get('NespressoFTPOrderExport/getB2BProductList/', 'TestController@getB2BProductList');
                $apiRouter->put('NespressoFTPOrderExport/addProductCode/{newProductCode}', 'TestController@addProductCode');
                $apiRouter->put('NespressoFTPOrderExport/deleteProductCode/{productCode}', 'TestController@deleteProductCode');
                $apiRouter->get('NespressoFTPOrderExport/callExportXml/', 'TestController@callExportXml');
                $apiRouter->put('NespressoFTPOrderExport/set_b2b_batch_number/{number}', 'TestController@setBatchNumberForB2B');
            }
        );
    }
}
