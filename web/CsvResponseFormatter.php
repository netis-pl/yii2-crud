<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\web;

use yii\base\Component;
use yii\web\ResponseFormatterInterface;

/**
 * If the response contains an 'items' key, it will be exported as CSV lines.
 * @package netis\utils\web
 */
class CsvResponseFormatter extends Component implements ResponseFormatterInterface
{
    /**
     * Formats the specified response.
     * @param \yii\web\Response $response the response to be formatted.
     */
    public function format($response)
    {
        $response->getHeaders()->set('Content-Type', 'text/csv; charset=UTF-8');
        if ($response->data === null) {
            return;
        }

        $handle = fopen('php://memory', 'r+');

        /** @var array $data should be output of \yii\rest\Serializer configured in current controller */
        $data = $response->data;
        if (!isset($data['items'])) {
            // single model
            fputcsv($handle, array_keys($data));
            fputcsv($handle, array_values($data));
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
