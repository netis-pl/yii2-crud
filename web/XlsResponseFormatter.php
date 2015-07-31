<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\web;

use yii\base\Component;
use yii\web\ResponseFormatterInterface;

/**
 * @package netis\utils\web
 */
class XlsResponseFormatter extends Component implements ResponseFormatterInterface
{
    /**
     * Formats the specified response.
     * @param \yii\web\Response $response the response to be formatted.
     */
    public function format($response)
    {
        $response->getHeaders()->set('Content-Type', 'application/vnd.ms-excel');
        if ($response->data === null) {
            return;
        }

        \PHPExcel_Settings::setCacheStorageMethod(\PHPExcel_CachedObjectStorageFactory::cache_to_sqlite3);

        $styles = $this->getStyles();

        $objPHPExcel = new \PHPExcel();
        $sheet       = $objPHPExcel->getActiveSheet();

        $offset = 1;

        /*
         * serialize filter
        $sheet->setCellValue('A1', $opcje['nazwaAnaliza']);
        $sheet->duplicateStyle($styles['default'], 'A1:C4');
        $sheet->getRowDimension(1)->setRowHeight(18);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(15);
        $sheet->getStyle('C3:C4')->getFont()->setBold(true);
        $offset = 6;
        */

        $data = $response->data;
        if (!isset($data['items'])) {
            // single model
            $this->addLine($sheet, $offset, array_keys($data));
            $this->addLine($sheet, $offset + 1, array_values($data));
            for ($i = 1, $lastColumn = 'A'; $i < count($data); $i++, $lastColumn++);
            $sheet->duplicateStyle($styles['header'], 'A'.$offset.':'.$lastColumn.$offset);
        } else {
            // a collection of models
            if (($firstRow = reset($data['items'])) !== false) {
                $this->addLine($sheet, $offset, array_keys($firstRow));
            }
            $startOffset = ++$offset;
            $item = [];
            foreach ($data['items'] as $item) {
                $this->addLine($sheet, $offset++, $item);
            }
            $column = 'A';
            foreach ($item as $value) {
                $rangeCoordinates = $column . $startOffset . ':' . $column . ($offset - 1);
                $cellCoordinates = $column++ . $offset;
                $formula = "=SUM($rangeCoordinates)";
                $sheet->setCellValue($cellCoordinates, $formula, true);//->duplicateStyle($styles['summaryFloat']);
            }
        }

        $filename = tempnam(\Yii::getAlias('@runtime'), 'xls');
        $objWriter = new \PHPExcel_Writer_Excel5($objPHPExcel);
        $objWriter->save($filename);

        $response->content = file_get_contents($filename);
        unlink($filename);
    }

    /**
     * @param \PHPExcel_Worksheet $sheet
     * @param integer $offset
     * @param array $values
     */
    public function addLine($sheet, $offset, $values)
    {
        $column = 'A';
        foreach ($values as $value) {
            $cellCoordinates = ($column++).(string)$offset;
            $sheet->setCellValue($cellCoordinates, $value);
        }
    }

    public function getStyles()
    {
        $configurations = [
            'header' => [
                'font'      => ['bold' => true, 'name' => 'Arial', 'size' => 10],
                'alignment' => ['horizontal' => \PHPExcel_Style_Alignment::HORIZONTAL_CENTER],
            ],
            'default' => [
                'font' => ['name' => 'Arial', 'size' => 10],
            ],
            'int' => [
                'font'         => ['name' => 'Arial', 'size' => 10],
                'numberformat' => ['code' => '# ##0;[RED]-# ##0'],
            ],
            'intSummary' => [
                'font'         => ['bold' => true, 'italic' => true, 'name' => 'Arial', 'size' => 10],
                'numberformat' => ['code' => '# ##0;[RED]-# ##0'],
            ],
            'float' => [
                'font'         => ['name' => 'Arial', 'size' => 10],
                'numberformat' => ['code' => '# ##0.000;[RED]-# ##0.000'],
            ],
            'floatSummary' => [
                'font'         => ['bold' => true, 'italic' => true, 'name' => 'Arial', 'size' => 10],
                'numberformat' => ['code' => '# ##0.000;[RED]-# ##0.000'],
            ],
            'money' => [
                'font'         => ['name' => 'Arial', 'size' => 10],
                'numberformat' => ['code' => '# ##0.00 [$PLN];[RED]-# ##0.00 [$PLN]'],
            ],
            'moneySummary' => [
                'font'         => ['bold' => true, 'italic' => true, 'name' => 'Arial', 'size' => 10],
                'numberformat' => ['code' => '# ##0.00 [$PLN];[RED]-# ##0.00 [$PLN]'],
            ],
            'percent' => [
                'font'         => ['name' => 'Arial', 'size' => 10],
                'numberformat' => ['code' => '# ##0.000 %'],
            ],
            'percentSummary' => [
                'font'         => ['bold' => true, 'italic' => true, 'name' => 'Arial', 'size' => 10],
                'numberformat' => ['code' => '# ##0.000 %'],
            ],
            'date' => [
                'font'         => ['name' => 'Arial', 'size' => 10],
                'numberformat' => ['code' => 'YY-MM-DD'],
            ],
        ];

        $result = [];
        foreach ($configurations as $name => $configuration) {
            $style = new \PHPExcel_Style();
            $style->applyFromArray($configuration);
            $result[$name] = $style;
        }
        return $result;
    }
}
