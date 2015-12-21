<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\crud\web;

use yii\base\Component;
use yii\web\ResponseFormatterInterface;

/**
 * If the response contains an 'items' key, it will be exported as CSV lines.
 * @package netis\crud\web
 */
class CsvResponseFormatter extends Component implements ResponseFormatterInterface
{
    /**
     * Formats the specified response.
     * @param \yii\web\Response $response the response to be formatted.
     */
    public function format($response)
    {
        //$response->getHeaders()->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->setDownloadHeaders(basename(\Yii::$app->request->pathInfo) . '.csv', 'text/csv');
        if ($response->data === null) {
            return;
        }

        $handle = fopen('php://memory', 'r+');

        /** @var array $data should be output of \yii\rest\Serializer configured in current controller */
        $data = $response->data;
        if (!isset($data['items'])) {
            // single model
            fputcsv($handle, array_keys($data));
            fputcsv($handle, array_map(function ($value) {
                return is_array($value) ? print_r($value, true) : (string)$value;
            }, array_values($data)));
        } else {
            // a collection of models
            if (($firstRow = reset($data['items'])) !== false) {
                fputcsv($handle, array_keys($firstRow));
            }
            foreach ($data['items'] as $item) {
                fputcsv($handle, $item);
            }
        }
        rewind($handle);
        // mb_convert_encoding($csv, 'iso-8859-2', 'utf-8')
        $response->content = stream_get_contents($handle);
        fclose($handle);
    }
}
