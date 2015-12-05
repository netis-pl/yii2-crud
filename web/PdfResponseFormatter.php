<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\crud\web;

use yii\base\Component;
use yii\web\ResponseFormatterInterface;

/**
 * @package netis\crud\web
 */
class PdfResponseFormatter extends Component implements ResponseFormatterInterface
{
    /**
     * Formats the specified response.
     * @param \yii\web\Response $response the response to be formatted.
     */
    public function format($response)
    {
        $response->getHeaders()->set('Content-Type', 'text');
        //$response->setDownloadHeaders('error.pdf', 'application/pdf');
        //$response->setStatusCode(200);
        if ($response->data === null) {
            return;
        }

        echo '<pre>';
        var_export($response->data);

        /*
        $renderer = new \mPDF(
            'pl-x', // mode
            'A4', // format
            0, // font-size
            '', // font
            12, // margin-left
            12, // margin-right
            5, // margin-top
            5, // margin-bottom
            2, // margin-header
            2, // margin-footer
            'P' // orientation
        );
        $renderer->useSubstitutions = true;
        $renderer->simpleTables = false;
        if ($response->data !== null) {
            @$renderer->WriteHTML(\yii\helpers\Html::encode(var_export($response->data, true)));
        }
        $response->content = $renderer->Output('print', 'S');
        */
    }
}
